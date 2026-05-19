<?php

namespace App\Services;

use App\DTOs\MatchResultDTO;
use App\Interfaces\SimulationServiceInterface;
use App\Interfaces\StandingServiceInterface;
use App\Models\GameMatch;
use App\Models\Team;

/**
 * SimulationService
 *
 * Maç sonuçlarını simüle eden servis.
 *
 * Temel mantık şu: her takımın 1-100 arası bir "güç" puanı var.
 * Bu puana ev avantajı ve küçük bir rastgele varyans ekleniyor,
 * ardından iki takımın toplam gücüne oranına göre gol sayısı hesaplanıyor.
 *
 * Tamamen deterministic bir sistem istemiyordum; her maçın biraz
 * farklı çıkması lazım ki simülasyon gerçekçi hissettirsin.
 * Bunun için iki katman rastgelelik var:
 *   1. Takım formunu/kalecisini temsil eden ±10% varyans
 *   2. Her gol denemesinde zar atma (olasılık tabanlı)
 *
 * Gol üretimi için Poisson dağılımına benzer bir yaklaşım kullandım.
 * 5 deneme yapıyoruz, her denemede "bu gol olacak mı?" diye bakıyoruz.
 * Güçlü takımın eşik değeri daha yüksek olduğu için ortalamada
 * daha fazla gol atıyor.
 */
class SimulationService implements SimulationServiceInterface
{
    // Ev sahibi takıma %10 güç bonusu — gerçek hayattaki home advantage
    private const HOME_ADVANTAGE = 1.10;

    // Bir maçta bir takımın atabileceği maksimum gol
    // 5 üzerinden gittiğimiz için bu değer aynı zamanda deneme sayısı
    private const MAX_GOALS = 5;

    // Temel gol olasılığı katsayısı; çok yüksek tutarsak maçlar hep 5-5 biter
    private const GOAL_RATE_BASE = 0.04;

    public function __construct(
        private readonly StandingServiceInterface $standingService
    ) {}

    /**
     * Tek bir maçı simüle eder ve sonucu DTO olarak döner.
     *
     * Her iki takım için efektif güç hesaplanır (ev avantajı + form varyansı dahil),
     * sonra bu güçlere göre kaç gol atılacağına karar verilir.
     * Sonuç veritabanına yazılmaz; bu metod sadece hesaplar.
     */
    public function simulateMatch(GameMatch $match): MatchResultDTO
    {
        $home = $match->homeTeam;
        $away = $match->awayTeam;

        $homePower = $this->calculateEffectivePower($home, isHome: true);
        $awayPower = $this->calculateEffectivePower($away, isHome: false);

        $homeGoals = $this->calculateGoals($homePower, $awayPower);
        $awayGoals = $this->calculateGoals($awayPower, $homePower);

        return new MatchResultDTO($homeGoals, $awayGoals);
    }

    /**
     * Belirtilen haftanın oynanmamış tüm maçlarını simüle eder ve DB'ye kaydeder.
     *
     * Sadece is_played=false olan maçlara dokunuyor; bu sayede
     * kısmen oynanmış bir haftada tekrar çağrılsa bile sorun çıkmaz.
     * Her maçın ardından puan tablosu da güncelleniyor.
     */
    public function simulateWeek(int $week): void
    {
        $matches = GameMatch::with(['homeTeam', 'awayTeam'])
            ->where('week', $week)
            ->where('is_played', false)
            ->get();

        foreach ($matches as $match) {
            $result = $this->simulateMatch($match);

            $match->update([
                'home_goals' => $result->homeGoals,
                'away_goals' => $result->awayGoals,
                'is_played'  => true,
            ]);

            // Maçı fresh() ile yeniden yüklüyoruz çünkü update() sonrası
            // model içindeki ilişkiler (homeTeam, awayTeam) sıfırlanabilir
            $this->standingService->updateFromMatch($match->fresh());
        }
    }

    /**
     * Kalan tüm oynanmamış haftaları sırayla simüle eder.
     *
     * Hafta sıralaması önemli; puan tablosu her haftanın ardından
     * güncelleniyor, dolayısıyla haftaları karışık işlesek bile
     * nihai sonuç aynı olurdu ama tutarlılık açısından sıralı gitmek daha iyi.
     */
    public function simulateAll(): void
    {
        // Henüz oynanmamış tüm haftaları küçükten büyüğe sırayla al
        $weeks = GameMatch::where('is_played', false)
            ->distinct()
            ->orderBy('week')
            ->pluck('week');

        foreach ($weeks as $week) {
            $this->simulateWeek($week);
        }
    }

    /**
     * Bir sonraki oynanacak haftanın numarasını döner.
     *
     * is_played=false olan maçların en küçük hafta numarasına bakıyoruz.
     * Eğer hiç oynanmamış maç kalmadıysa (tüm maçlar bitti) 0 dönüyor.
     * 0 değeri frontend'de ligin bittiğini anlamak için kullanılıyor.
     */
    public function getCurrentWeek(): int
    {
        $firstUnplayed = GameMatch::where('is_played', false)
            ->orderBy('week')
            ->value('week');

        // null gelirse lig bitti demek; 0 döndürüyoruz
        return $firstUnplayed ?? 0;
    }

    /**
     * Bir takımın o maçtaki gerçek gücünü hesaplar.
     *
     * Sırayla şunları uyguluyoruz:
     *  1. Temel güç (takımın kalıcı power değeri)
     *  2. Ev avantajı — eğer iç sahada oynuyorsa %10 ekle
     *  3. Form/kaleci faktörü — ±10% arası rastgele sapma
     *     (iyi günleri de kötü günleri de olabiliyor)
     *
     * Sonuç en az 1.0 olacak şekilde sınırlandırılıyor;
     * teorik olarak power=1 olan takımda çok kötü varyans gelirse
     * negatife düşmemesi için.
     */
    private function calculateEffectivePower(Team $team, bool $isHome): float
    {
        $basePower = $team->power;

        if ($isHome) {
            $basePower *= self::HOME_ADVANTAGE;
        }

        // -10 ile +10 arasında integer alıp 100'e bölüyoruz → %±10 sapma
        $variance  = 1 + (mt_rand(-10, 10) / 100);
        $basePower *= $variance;

        return max(1.0, $basePower);
    }

    /**
     * Saldırı ve savunma gücüne göre atılan gol sayısını hesaplar.
     *
     * MAX_GOALS kadar deneme yapıyoruz. Her denemede:
     *   - Saldırının toplam güce oranı hesaplanır (ne kadar baskın?)
     *   - Bu orana ve temel katsayıya göre bir eşik değeri çıkarılır
     *   - 0-1 arası rastgele sayı eşiğin altında kalırsa gol sayılır
     *
     * Eşiği 0.75'te kesiyoruz; yoksa çok güçlü takımlar her seferinde
     * 5 gol atardı, o da gerçekçi olmazdı.
     *
     * @param float $attackPower  Gol atacak takımın efektif gücü
     * @param float $defensePower Gol yiyecek takımın efektif gücü
     */
    private function calculateGoals(float $attackPower, float $defensePower): int
    {
        // Saldırının iki takım arasındaki güç oranı (0-1 arası)
        $ratio = $attackPower / ($attackPower + $defensePower);
        $goals = 0;

        for ($i = 0; $i < self::MAX_GOALS; $i++) {
            $threshold = $ratio * self::GOAL_RATE_BASE * $attackPower / 10;

            // Eşiği 0.75 ile kırpıyoruz; sonsuz güçlü takım bile olsa
            // tek bir denemede %75'ten yüksek ihtimalle gol atamaz
            if ((mt_rand(0, 1000) / 1000) < min($threshold, 0.75)) {
                $goals++;
            }
        }

        return $goals;
    }
}
