<?php

namespace App\Http\Controllers;

use App\Interfaces\FixtureServiceInterface;
use App\Interfaces\SimulationServiceInterface;
use App\Interfaces\StandingServiceInterface;
use App\Models\GameMatch;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeagueController extends Controller
{
    public function __construct(
        private readonly FixtureServiceInterface    $fixtureService,
        private readonly SimulationServiceInterface $simulationService,
        private readonly StandingServiceInterface   $standingService,
    ) {}

    /**
     * GET /api/league
     *
     * Puan tablosu, mevcut hafta ve toplam hafta bilgisini döner.
     * Frontend'in açılışta ihtiyaç duyduğu her şey burada.
     *
     * Sayfayı her açtığında fixtureService->generate() çağrılıyor.
     * Bu çağrı idempotent olduğu için sorun yok; maç zaten varsa hiçbir şey yapmıyor.
     * Yani "fikstür oluşturuldu mu?" diye ayrıca kontrol etmeye gerek kalmıyor.
     *
     * is_finished: currentWeek=0 olduğunda tüm maçlar oynanmış demek.
     * Frontend bu flag ile "lig bitti" ekranını gösteriyor.
     */
    public function index(): JsonResponse
    {
        // İlk açılışta fikstür yoksa otomatik oluşturulsun
        $this->fixtureService->generate();

        $standings   = $this->standingService->getOrderedStandings();
        $currentWeek = $this->simulationService->getCurrentWeek();
        $totalWeeks  = $this->fixtureService->getTotalWeeks();

        return response()->json([
            'standings'    => $standings->map(fn($s) => [
                'id'              => $s->id,
                'team_id'         => $s->team_id,
                'team_name'       => $s->team->name,
                'short_name'      => $s->team->short_name,
                'logo_color'      => $s->team->logo_color,
                'logo_url'        => $s->team->logo_url,
                'played'          => $s->played,
                'won'             => $s->won,
                'drawn'           => $s->drawn,
                'lost'            => $s->lost,
                'goals_for'       => $s->goals_for,
                'goals_against'   => $s->goals_against,
                'goal_difference' => $s->goals_for - $s->goals_against, // Hesaplama burada yapılıyor, ayrı kolon değil
                'points'          => $s->points,
            ]),
            'current_week' => $currentWeek,
            'total_weeks'  => $totalWeeks,
            'is_finished'  => $currentWeek === 0, // 0 = oynanmamış maç kalmadı
        ]);
    }

    /**
     * GET /api/teams
     *
     * Sadece temel takım bilgilerini döner.
     * Şu an frontend'de fazla kullanılmıyor ama takım yönetimi
     * sayfası eklenirse işe yarar diye bıraktım.
     */
    public function teams(): JsonResponse
    {
        $teams = Team::all(['id', 'name', 'short_name', 'power', 'logo_color', 'logo_url']);
        return response()->json(['teams' => $teams]);
    }

    /**
     * POST /api/league/reset
     *
     * Ligi baştan başlatır:
     *   1. Tüm maç kayıtları silinir (truncate, delete'den daha hızlı)
     *   2. Puan tablosu sıfırlanır (kayıtlar silinmez, sadece sayılar 0 olur)
     *   3. Fikstür yeniden oluşturulur
     *
     * Takımlar silinmiyor; sadece fikstür ve sonuçlar temizleniyor.
     * Yani reset sonrasında aynı 4 takım yeni sezona başlar.
     */
    public function reset(): JsonResponse
    {
        // Maçları tamamen sil — fikstür de sonuçlar da gidiyor
        GameMatch::truncate();

        // Puan tablosunu sıfırla (kayıtları silmiyoruz, 0'lıyoruz)
        $this->standingService->resetAll();

        // Yeni fikstür oluştur
        $this->fixtureService->generate();

        return response()->json(['message' => 'League has been reset successfully.']);
    }
}
