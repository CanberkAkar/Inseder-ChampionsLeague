<?php

namespace App\Services;

use App\Interfaces\StandingServiceInterface;
use App\Models\GameMatch;
use App\Models\Standing;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * StandingService
 *
 * Puan tablosunu yöneten servis.
 *
 * Her maçın ardından iki takımın kayıtlarını günceller.
 * Sıralama kriterleri Premier League kurallarına göre:
 *   1. Puan (yüksekten düşüğe)
 *   2. Gol farkı (yüksekten düşüğe)
 *   3. Atılan gol (yüksekten düşüğe)
 *   4. Takım adı (alfabetik) — son kriter, pratikte nadiren devreye girer
 *
 * Maç düzenleme özelliği eklediğimde "sadece o maçı geri al" mantığı
 * çok karmaşıklaşıyordu; onun yerine tüm tabloyu sıfırlayıp baştan
 * hesaplamak daha güvenilir. Küçük bir lig için performans sorunu yok.
 */
class StandingService implements StandingServiceInterface
{
    /**
     * Oynanan bir maçın sonucunu puan tablosuna işler.
     *
     * İki takımın standing kaydını transaction içinde güncelliyoruz;
     * birinin yazılıp diğerinin yazılmadığı yarım kalan durum olmasın diye.
     *
     * Gol miktarı bire bir karşılıklı yazılıyor:
     *  - Ev sahibinin attığı = deplasman takımının yediği
     *  - Deplasman takımının attığı = ev sahibinin yediği
     */
    public function updateFromMatch(GameMatch $match): void
    {
        DB::transaction(function () use ($match) {
            $homeStanding = $this->getOrCreateStanding($match->home_team_id);
            $awayStanding = $this->getOrCreateStanding($match->away_team_id);

            $homeGoals = $match->home_goals;
            $awayGoals = $match->away_goals;

            // Her iki takım da birer maç oynadı
            $homeStanding->played++;
            $awayStanding->played++;

            // Gol istatistikleri — ev sahibinin attığı, deplasmanın yediği ve tam tersi
            $homeStanding->goals_for     += $homeGoals;
            $homeStanding->goals_against += $awayGoals;
            $awayStanding->goals_for     += $awayGoals;
            $awayStanding->goals_against += $homeGoals;

            // Puan ve galibiyet/beraberlik/mağlubiyet dağıtımı
            if ($homeGoals > $awayGoals) {
                // Ev sahibi kazandı → 3 puan ev sahibine
                $homeStanding->won++;
                $homeStanding->points += 3;
                $awayStanding->lost++;
            } elseif ($homeGoals < $awayGoals) {
                // Deplasman kazandı → 3 puan deplasmanın
                $awayStanding->won++;
                $awayStanding->points += 3;
                $homeStanding->lost++;
            } else {
                // Beraberlik → her iki takıma 1'er puan
                $homeStanding->drawn++;
                $awayStanding->drawn++;
                $homeStanding->points++;
                $awayStanding->points++;
            }

            $homeStanding->save();
            $awayStanding->save();
        });
    }

    /**
     * Tüm puan tablosunu sıfırdan yeniden hesaplar.
     *
     * Bir maç sonucu manuel düzenlendiğinde bu metod çağrılıyor.
     * "Sadece o maçın etkisini geri al" yazmak yerine
     * tabloyu tamamen sıfırlayıp tüm oynanan maçları baştan işlemek
     * hem daha basit hem de hata riskini ortadan kaldırıyor.
     *
     * Transaction içinde yapılıyor; bir maçta hata çıkarsa hiçbir şey yazılmaz,
     * puan tablosu yarım kalmaz.
     */
    public function recalculateAll(): void
    {
        DB::transaction(function () {
            // Önce tabloyu sıfırla
            $this->resetAll();

            // Ardından oynanan tüm maçları hafta sırasıyla tekrar işle
            $playedMatches = GameMatch::with(['homeTeam', 'awayTeam'])
                ->where('is_played', true)
                ->orderBy('week')
                ->get();

            foreach ($playedMatches as $match) {
                $this->updateFromMatch($match);
            }
        });
    }

    /**
     * Puan tablosunu Premier League sıralama kriterlerine göre döner.
     *
     * Gol farkı hesabında CAST kullanmak zorunda kaldım çünkü
     * goals_for ve goals_against unsigned integer olarak tanımlı.
     * MySQL'de unsigned - unsigned işlemi negatif sonuç verdiğinde
     * integer underflow hatası atıyor. SIGNED'a cast edince düzeliyor.
     */
    public function getOrderedStandings(): Collection
    {
        return Standing::with('team')
            ->orderByDesc('points')
            ->orderByRaw('(CAST(goals_for AS SIGNED) - CAST(goals_against AS SIGNED)) DESC')
            ->orderByDesc('goals_for')
            ->orderBy(
                // Alt sorguyla takım adını alıp alfabetik son kriter olarak kullanıyoruz
                Team::select('name')
                    ->whereColumn('teams.id', 'standings.team_id')
                    ->limit(1)
            )
            ->get();
    }

    /**
     * Puan tablosundaki tüm istatistikleri sıfırlar.
     *
     * Kayıtları silmek yerine güncelliyoruz; bu sayede her takımın
     * standing kaydı yerinde kalıyor, sadece sayılar sıfırlanıyor.
     * Lig sıfırlama ve yeniden hesaplama öncesinde çağrılır.
     */
    public function resetAll(): void
    {
        Standing::query()->update([
            'played'        => 0,
            'won'           => 0,
            'drawn'         => 0,
            'lost'          => 0,
            'goals_for'     => 0,
            'goals_against' => 0,
            'points'        => 0,
        ]);
    }

    /**
     * Takımın standing kaydını getirir, yoksa sıfır değerleriyle oluşturur.
     *
     * firstOrCreate kullanıyoruz; böylece seeder çalışmamış ya da
     * bir şekilde kayıt oluşmamışsa bile uygulama çökmüyor.
     */
    private function getOrCreateStanding(int $teamId): Standing
    {
        return Standing::firstOrCreate(
            ['team_id' => $teamId],
            [
                'played' => 0, 'won' => 0, 'drawn' => 0,
                'lost'   => 0, 'goals_for' => 0, 'goals_against' => 0, 'points' => 0,
            ]
        );
    }
}
