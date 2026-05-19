<?php

namespace App\Http\Controllers;

use App\Interfaces\FixtureServiceInterface;
use App\Interfaces\SimulationServiceInterface;
use App\Interfaces\StandingServiceInterface;
use App\Models\GameMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MatchController extends Controller
{
    public function __construct(
        private readonly SimulationServiceInterface $simulationService,
        private readonly StandingServiceInterface   $standingService,
        private readonly FixtureServiceInterface    $fixtureService,
    ) {}

    /**
     * GET /api/matches
     *
     * Tüm maçları haftalara göre gruplandırılmış şekilde döner.
     * Frontend hafta sekmeleri için bu endpoint'i kullanıyor.
     *
     * orderBy('week') ile hafta sıralamasını garanti altına alıyoruz;
     * ardından groupBy ile ["1" => [...maçlar], "2" => [...maçlar]] yapısına dönüşüyor.
     * Bu yapı Laravel Collection'dan geliyor, JSON'a çevrilince nesne olarak çıkıyor.
     *
     * Eager loading (with) kritik — her maç için homeTeam ve awayTeam ilişkisi
     * ayrı sorgu açmadan tek seferde çekiliyor.
     */
    public function index(): JsonResponse
    {
        $matches = GameMatch::with(['homeTeam', 'awayTeam'])
            ->orderBy('week')
            ->get()
            ->groupBy('week')
            ->map(fn($weekMatches) => $weekMatches->map(fn($m) => $this->formatMatch($m)));

        return response()->json(['matches' => $matches]);
    }

    /**
     * GET /api/matches/week/{week}
     *
     * Belirli bir hafta için maç listesi döner.
     * Şu an doğrudan kullanılmıyor (frontend tüm maçları index'ten çekiyor)
     * ama ileride sayfa yenilemesi ya da lazy loading için işe yarayabilir.
     */
    public function byWeek(int $week): JsonResponse
    {
        $matches = $this->fixtureService
            ->getMatchesByWeek($week)
            ->map(fn($m) => $this->formatMatch($m));

        return response()->json([
            'week'    => $week,
            'matches' => $matches,
        ]);
    }

    /**
     * POST /api/matches/play-week
     *
     * Bir sonraki oynanmamış haftayı simüle eder.
     *
     * Önce hangi hafta olduğunu öğrenmek için getCurrentWeek() çağrısı yapıyoruz.
     * Bu değer 0 dönerse tüm maçlar bitti demek; 422 ile hata döndürüyoruz.
     * 422 seçimi kasıtlı: 400 "kötü istek" anlamına gelir ama istek formatı doğru,
     * sadece iş kuralı açısından geçersiz. 422 Unprocessable Entity buna daha uygun.
     *
     * Simülasyon sonrası aynı haftanın maçlarını tekrar çekip response'a ekliyoruz
     * ki frontend ayrıca bir GET isteği atmak zorunda kalmasın.
     */
    public function playWeek(): JsonResponse
    {
        $currentWeek = $this->simulationService->getCurrentWeek();

        if ($currentWeek === 0) {
            return response()->json(['message' => 'All matches have been played.'], 422);
        }

        $this->simulationService->simulateWeek($currentWeek);

        // Simüle edilen haftanın güncel sonuçlarını çekiyoruz
        $matches = $this->fixtureService
            ->getMatchesByWeek($currentWeek)
            ->map(fn($m) => $this->formatMatch($m));

        return response()->json([
            'message'     => "Week {$currentWeek} simulated successfully.",
            'played_week' => $currentWeek,
            'matches'     => $matches,
        ]);
    }

    /**
     * POST /api/matches/play-all
     *
     * Kalan tüm oynanmamış maçları tek seferde simüle eder.
     *
     * simulateAll() içinde hafta hafta gidiyoruz; hepsini bitirince
     * tüm maç tablosunu yeniden çekip döndürüyoruz.
     * playWeek()'ten farklı olarak burada tek haftanın değil tüm sezonun
     * güncel halini döndürmek gerekiyor.
     */
    public function playAll(): JsonResponse
    {
        $this->simulationService->simulateAll();

        $matches = GameMatch::with(['homeTeam', 'awayTeam'])
            ->orderBy('week')
            ->get()
            ->groupBy('week')
            ->map(fn($weekMatches) => $weekMatches->map(fn($m) => $this->formatMatch($m)));

        return response()->json([
            'message' => 'All matches simulated successfully.',
            'matches' => $matches,
        ]);
    }

    /**
     * PUT /api/matches/{id}
     *
     * Oynanan bir maçın skorunu düzenlemeye izin verir.
     *
     * Neden yalnızca oynanan maçlar düzenlenebilir?
     * Oynanmamış maçların home_goals/away_goals değerleri null;
     * bunları düzenlemek is_played durumunu bozmadan gol atar, puan tablosu tutarsız kalır.
     * Bu yüzden is_played=false ise 422 dönüyoruz.
     *
     * Maç güncellendikten sonra puan tablosunu sıfırdan hesaplıyoruz.
     * "Sadece bu maçı geri al ve yeniden uygula" yazmak mümkün ama hataya açık;
     * tüm maçları baştan işlemek daha güvenilir.
     *
     * Validasyon: max:20 limiti gerçekçilik için. Teorik olarak daha yüksek
     * gol sayıları girilmesi anlamsız bir simülasyona yol açardı.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'home_goals' => 'required|integer|min:0|max:20',
            'away_goals' => 'required|integer|min:0|max:20',
        ]);

        $match = GameMatch::with(['homeTeam', 'awayTeam'])->findOrFail($id);

        // Henüz oynanmamış maçlara dokunma
        if (!$match->is_played) {
            return response()->json(['message' => 'Cannot edit an unplayed match.'], 422);
        }

        $match->update([
            'home_goals' => $validated['home_goals'],
            'away_goals' => $validated['away_goals'],
        ]);

        // Puan tablosunu tamamen yeniden hesapla
        $this->standingService->recalculateAll();

        // fresh() ile model verilerini ve ilişkilerini yeniden yüklüyoruz
        // Yoksa update() öncesindeki eski değerler response'a girer
        return response()->json([
            'message' => 'Match result updated. Standings recalculated.',
            'match'   => $this->formatMatch($match->fresh()),
        ]);
    }

    /**
     * Bir GameMatch modelini API yanıtı için düz diziye dönüştürür.
     *
     * Bu metodun var olmasının sebebi: aynı formatlama index(), byWeek(),
     * playWeek() ve playAll()'da tekrar tekrar kullanılıyor.
     * Merkezi tutmak, ileride bir alan ekleyince tek yerden değiştirmeyi sağlıyor.
     *
     * result alanı model üzerindeki accessor'dan geliyor (home_win / away_win / draw / null).
     */
    private function formatMatch(GameMatch $match): array
    {
        return [
            'id'              => $match->id,
            'week'            => $match->week,
            'home_team_id'    => $match->home_team_id,
            'home_team_name'  => $match->homeTeam->name,
            'home_short_name' => $match->homeTeam->short_name,
            'home_logo_color' => $match->homeTeam->logo_color,
            'home_logo_url'   => $match->homeTeam->logo_url,
            'away_team_id'    => $match->away_team_id,
            'away_team_name'  => $match->awayTeam->name,
            'away_short_name' => $match->awayTeam->short_name,
            'away_logo_color' => $match->awayTeam->logo_color,
            'away_logo_url'   => $match->awayTeam->logo_url,
            'home_goals'      => $match->home_goals,
            'away_goals'      => $match->away_goals,
            'is_played'       => $match->is_played,
            'result'          => $match->result, // accessor: home_win | away_win | draw | null
        ];
    }
}
