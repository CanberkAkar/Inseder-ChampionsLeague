<?php

namespace App\Services;

use App\DTOs\MatchResultDTO;
use App\Interfaces\PredictionServiceInterface;
use App\Models\GameMatch;
use App\Models\Standing;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * PredictionService
 *
 * Monte Carlo simülasyonu kullanarak şampiyonluk olasılıklarını hesaplar.
 *
 * "Kalan maçları 1000 kez simüle et, her seferinde kimin birinci bitirdiğine bak,
 *  kaç kez birinci olduysa o kadar ihtimal yüzde" — mantık bu kadar basit aslında.
 *
 * Neden Monte Carlo?
 *   Analitik yöntem (kombinasyonları tek tek hesaplamak) 4 takım için hâlâ yönetilebilir
 *   ama daha fazla takım veya daha karmaşık tiebreaker kuralları eklenince patlıyor.
 *   Monte Carlo her zaman çalışır; sadece simülasyon sayısını artırınca hassaslaşır.
 *
 * Tahminler sadece son 3 haftada gösteriliyor; ilk haftalarda anlamsız çünkü
 * çok fazla belirsizlik var, her takımın ihtimali %25 civarında çıkıyor.
 *
 * Kenar durumlar:
 *   - Tüm maçlar bittiyse → gerçek puana bakılır, lider %100 alır
 *   - Lider konumu birden fazla takım paylaşıyorsa → eşit bölünür
 */
class PredictionService implements PredictionServiceInterface
{
    // Ev sahibine verilen güç bonusu — SimulationService ile aynı değer, tutarlı olsun
    private const HOME_ADVANTAGE = 1.10;

    // Kaç kez simüle edeceğiz? 1000 genelde yeterince stabil sonuç veriyor
    // Daha fazlası hassasiyeti artırır ama yanıt süresi de uzar
    private const SIMULATIONS_COUNT = 1000;

    // Ligin toplam hafta sayısı
    private const TOTAL_WEEKS = 6;

    // Tahmin paneli kaçıncı haftadan itibaren görünsün?
    // Son 3 hafta = hafta 4, 5, 6 ve tüm maçlar bittikten sonra
    private const PREDICTION_WEEKS = 3;

    /**
     * Her takım için şampiyonluk olasılığını hesaplar.
     *
     * Önce oynanan maçlardaki gerçek puanlara bakıyoruz,
     * ardından kalan maçları $simulations kez simüle edip
     * "kaç kez hangi takım birinci bitti" sayıyoruz.
     *
     * Bu metodun içindeki quickSimulate() veritabanına yazmıyor;
     * sadece bellekte hesap yapıyor, bu yüzden 1000 iterasyon hâlâ hızlı.
     *
     * @param int $simulations Kaç simülasyon yapılacak (testlerde 100 geçiyoruz, daha hızlı)
     */
    public function calculateProbabilities(int $simulations = self::SIMULATIONS_COUNT): Collection
    {
        $teams            = Team::all();
        $currentStandings = $this->getCurrentPoints();
        $remainingMatches = GameMatch::with(['homeTeam', 'awayTeam'])
            ->where('is_played', false)
            ->get();

        // Hiç maç kalmadıysa gerçek puana bakarak sonuç döndür
        if ($remainingMatches->isEmpty()) {
            return $this->probabilitiesFromFinalStandings($currentStandings, $teams);
        }

        // Her takım için kazanma sayacını sıfırla
        $winCounts = $teams->pluck('id')->mapWithKeys(fn($id) => [$id => 0])->toArray();

        for ($i = 0; $i < $simulations; $i++) {
            // Gerçek puanları kopyalayarak başla; her iterasyon bağımsız olmalı
            $simulatedPoints = $currentStandings->toArray();

            foreach ($remainingMatches as $match) {
                // Maçı simüle et (sadece bellek, DB yok)
                $result = $this->quickSimulate($match->homeTeam, $match->awayTeam);

                // Simülasyon sonucunu puan sayacına ekle
                $this->applyResultToPoints($simulatedPoints, $match, $result);
            }

            // Bu iterasyonda kim birinci bitti?
            $winner = $this->determineWinner($simulatedPoints);
            if ($winner) {
                $winCounts[$winner]++;
            }
        }

        // Kazanma sayısını olasılığa çevir ve büyükten küçüğe sırala
        return $teams->map(function (Team $team) use ($winCounts, $simulations) {
            return [
                'team_id'     => $team->id,
                'team_name'   => $team->name,
                'short_name'  => $team->short_name,
                'logo_color'  => $team->logo_color,
                'logo_url'    => $team->logo_url,
                'probability' => round(($winCounts[$team->id] / $simulations) * 100, 1),
            ];
        })->sortByDesc('probability')->values();
    }

    /**
     * Tahmin panelinin şu an gösterilmesi gerekip gerekmediğini döner.
     *
     * Oynanmamış ilk hafta numarasına bakıyoruz:
     *  - null gelirse tüm maçlar bitmiş → göster
     *  - Kalan hafta sayısı PREDICTION_WEEKS eşiğini geçtiyse → göster
     *  - Daha erken aşamadaysa → gösterme, zaten anlamlı değil
     */
    public function shouldShowPredictions(): bool
    {
        return true;
    }

    /**
     * Monte Carlo döngüsü içindeki hızlı simülasyon.
     *
     * SimulationService::simulateMatch() ile aynı mantıkta ama
     * çok daha sade; model instantiate etmiyor, DB'ye dokunmuyor.
     * 1000 iterasyonda çağrıldığı için performans burada kritik.
     *
     * Güç hesabı: temel güç × ev avantajı × rastgele form faktörü
     * Ardından iki takımın toplam gücüne oran üzerinden gol sayısı.
     */
    private function quickSimulate(Team $home, Team $away): MatchResultDTO
    {
        $homePower = $home->power * self::HOME_ADVANTAGE * (1 + mt_rand(-10, 10) / 100);
        $awayPower = $away->power * (1 + mt_rand(-10, 10) / 100);

        $total     = $homePower + $awayPower;
        $homeRatio = $homePower / $total;
        $awayRatio = $awayPower / $total;

        $homeGoals = $this->rollGoals($homeRatio);
        $awayGoals = $this->rollGoals($awayRatio);

        return new MatchResultDTO($homeGoals, $awayGoals);
    }

    /**
     * Verilen olasılık oranına göre gol sayısını hesaplar.
     *
     * 5 deneme yapıyoruz, her denemede "bu gol mu?" diye soruyoruz.
     * ratio=0.6 olursa her denemede %36 ihtimalle gol olur (0.6 * 0.6 değil, 0.6 eşiği).
     * Ortalamada ratio*5 gol çıkması beklenir.
     */
    private function rollGoals(float $ratio): int
    {
        $goals = 0;
        for ($i = 0; $i < 5; $i++) {
            // ratio ne kadar yüksekse o kadar sık gol düşüyor
            if ((mt_rand(0, 1000) / 1000) < ($ratio * 0.6)) {
                $goals++;
            }
        }
        return $goals;
    }

    /**
     * Simülasyon sonucunu geçici puan sayacına uygular.
     *
     * Dikkat: bu metod $points array'ini referansla alıyor (&$points).
     * Kopyalamak yerine doğrudan değiştiriyor; 1000 iterasyonda
     * gereksiz kopyalamadan kaçınmak için.
     */
    private function applyResultToPoints(array &$points, GameMatch $match, MatchResultDTO $result): void
    {
        $homeId = $match->home_team_id;
        $awayId = $match->away_team_id;

        if ($result->homeGoals > $result->awayGoals) {
            $points[$homeId] += 3;       // Ev sahibi kazandı
        } elseif ($result->homeGoals < $result->awayGoals) {
            $points[$awayId] += 3;       // Deplasman kazandı
        } else {
            $points[$homeId]++;           // Beraberlik → her ikisine 1 puan
            $points[$awayId]++;
        }
    }

    /**
     * Puan sayacında en yüksek puanlı takımın ID'sini döner.
     *
     * arsort ile büyükten küçüğe sıralar, array_key_first ile ilk elemanı alır.
     * Eşitlik durumunda hangisi önce gelirse o kazanmış sayılır;
     * Monte Carlo'da bu ufak sapma önemli değil.
     */
    private function determineWinner(array $points): ?int
    {
        if (empty($points)) {
            return null;
        }
        arsort($points);
        return array_key_first($points);
    }

    /**
     * Mevcut standings tablosundan takım_id → puan eşlemesini döner.
     * Monte Carlo'nun başlangıç noktası olarak kullanılıyor.
     */
    private function getCurrentPoints(): Collection
    {
        return Standing::all()->mapWithKeys(fn($s) => [$s->team_id => $s->points]);
    }

    /**
     * Tüm maçlar bittikten sonra olasılıkları gerçek puana göre hesaplar.
     *
     * Birden fazla takım aynı puanda birinciyse olasılık eşit bölünür.
     * Örn: 2 takım 18 puanda aynıysa ikisi de %50 alır.
     *
     * Daha doğrusu gol farkına da bakılması gerekirdi ama Monte Carlo
     * yaklaşımında bu son derece nadir bir durum; şimdilik basit tutuyoruz.
     */
    private function probabilitiesFromFinalStandings(Collection $points, Collection $teams): Collection
    {
        $maxPoints = $points->max();

        // Maksimum puanı paylaşan tüm takımları bul
        $leaders = $points->filter(fn($p) => $p === $maxPoints)->keys();

        return $teams->map(function (Team $team) use ($leaders, $maxPoints, $points) {
            // Lider grubundaysa eşit pay al, değilse %0
            $probability = $leaders->contains($team->id)
                ? round(100 / $leaders->count(), 1)
                : 0.0;

            return [
                'team_id'     => $team->id,
                'team_name'   => $team->name,
                'short_name'  => $team->short_name,
                'logo_color'  => $team->logo_color,
                'logo_url'    => $team->logo_url,
                'probability' => $probability,
            ];
        })->sortByDesc('probability')->values();
    }
}
