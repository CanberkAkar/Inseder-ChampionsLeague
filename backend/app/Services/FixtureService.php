<?php

namespace App\Services;

use App\Interfaces\FixtureServiceInterface;
use App\Models\GameMatch;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * FixtureService
 *
 * 4 takım için çift devreli lig fikstürü oluşturur.
 * Yani her takım diğer her takımla hem iç sahada hem de deplasmanda oynuyor,
 * toplamda 6 hafta ve 12 maç ortaya çıkıyor (4*3/2 * 2 = 12).
 *
 * Fikstür oluşturmak için klasik "çember algoritması" (circle method) kullandım.
 * Temel fikir şu: bir takımı sabit tutup diğerlerini her tur döndürüyorsun.
 * Bu sayede dengeli bir program çıkıyor; hiç çakışma olmuyor, her hafta
 * her takım sadece bir kez oynuyor.
 *
 * İlk 3 hafta gidiş turunu, son 3 hafta dönüş turunu oluşturuyor.
 * Dönüş turunda sadece ev sahibi-deplasman yer değiştiriyor.
 */
class FixtureService implements FixtureServiceInterface
{
    // 4 takım, çift devre → 3 hafta gidiş + 3 hafta dönüş = 6 hafta
    private const TOTAL_WEEKS = 6;

    /**
     * Fikstürü veritabanına yazar.
     *
     * Daha önce oluşturulmuşsa tekrar oluşturmaz (idempotent davranış).
     * Bu sayede uygulama her başladığında güvenle çağrılabilir,
     * duplicate maç kaydı oluşma riski yok.
     */
    public function generate(): void
    {
        // Maç tablosunda zaten kayıt varsa bir şey yapma
        if ($this->isGenerated()) {
            return;
        }

        $teams   = Team::all()->toArray();
        $teamIds = array_column($teams, 'id');

        // Gidiş turunu üret, ardından ev/deplasman taraflarını tersine çevirerek dönüş turunu oluştur
        $firstHalf  = $this->generateRoundRobin($teamIds);
        $secondHalf = $this->reverseHomeAway($firstHalf, count($firstHalf));

        $week = 1;
        foreach ([$firstHalf, $secondHalf] as $half) {
            foreach ($half as $weekMatches) {
                foreach ($weekMatches as [$homeId, $awayId]) {
                    GameMatch::create([
                        'week'         => $week,
                        'home_team_id' => $homeId,
                        'away_team_id' => $awayId,
                        'is_played'    => false,
                    ]);
                }
                $week++;
            }
        }
    }

    /**
     * Maç tablosunda en az bir kayıt var mı diye bakıyor.
     * Basit ama yeterli; 0 kayıt varsa fikstür hiç oluşturulmamış demek.
     */
    public function isGenerated(): bool
    {
        return GameMatch::count() > 0;
    }

    /**
     * Toplam hafta sayısını döner.
     * Tahmin paneli ve frontend sayfalama için gerekli.
     */
    public function getTotalWeeks(): int
    {
        return self::TOTAL_WEEKS;
    }

    /**
     * Belirli bir haftanın maçlarını takım bilgileriyle birlikte getirir.
     * "with" ile eager loading yapıyoruz, aksi halde her maç için
     * ayrı bir sorgu gönderilirdi (N+1 problemi).
     */
    public function getMatchesByWeek(int $week): Collection
    {
        return GameMatch::with(['homeTeam', 'awayTeam'])
            ->where('week', $week)
            ->get();
    }

    /**
     * Çember (circle) algoritmasıyla round-robin fikstür üretir.
     *
     * Algoritmanın çalışma mantığı:
     *   - İlk takımı sabit bırak (pivot).
     *   - Kalan takımları bir dizi olarak tut.
     *   - Her turda: pivot + dizinin geri kalanını birleştir,
     *     ilk elemanı son eleman ile, ikinciyi sondan bir öncekiyle eşleştir.
     *   - Turdan sonra diziyi bir adım döndür (son eleman öne geçer).
     *
     * N takım için N-1 tur çıkar; 4 takım → 3 tur = gidiş turu.
     *
     * @return array Haftalara göre gruplandırılmış [homeId, awayId] çiftleri
     */
    private function generateRoundRobin(array $teamIds): array
    {
        $n     = count($teamIds);
        $weeks = [];
        $ids   = $teamIds;

        // İlk takımı pivot olarak ayır, kalanları döndüreceğiz
        $fixed = array_shift($ids);

        for ($round = 0; $round < $n - 1; $round++) {
            $weekMatches = [];
            $all         = array_merge([$fixed], $ids);

            // İlk eleman son eleman ile, ikinci sondan bir önceki ile eşleşir
            for ($i = 0; $i < $n / 2; $i++) {
                $home = $all[$i];
                $away = $all[$n - 1 - $i];

                // Çift turlarda pivot maçının ev sahibini değiştirerek
                // her takımın iç sahada dengeli oynamasını sağlıyoruz
                if ($round % 2 === 1 && $i === 0) {
                    [$home, $away] = [$away, $home];
                }

                $weekMatches[] = [$home, $away];
            }

            $weeks[] = $weekMatches;

            // Diziyi döndür: son eleman öne gelir
            array_unshift($ids, array_pop($ids));
        }

        return $weeks;
    }

    /**
     * Gidiş turundaki her maçın ev sahibi ile deplasman takımını yer değiştirir.
     * Bu şekilde dönüş turu elde edilir; ayrıca algoritma yazılmasına gerek kalmaz.
     *
     * @param array $weeks   Gidiş turundaki hafta-maç yapısı
     * @param int   $offset  Kullanılmıyor şu an, ileride haftayı offset etmek için ayrıldı
     */
    private function reverseHomeAway(array $weeks, int $offset): array
    {
        return array_map(
            fn($weekMatches) => array_map(
                fn($match) => [$match[1], $match[0]], // [home, away] → [away, home]
                $weekMatches
            ),
            $weeks
        );
    }
}
