<?php

namespace App\Http\Controllers;

use App\Interfaces\PredictionServiceInterface;
use Illuminate\Http\JsonResponse;

class PredictionController extends Controller
{
    public function __construct(
        private readonly PredictionServiceInterface $predictionService
    ) {}

    /**
     * GET /api/predictions
     *
     * Şampiyonluk olasılıklarını döner.
     *
     * İki bilgi birlikte gidiyor:
     *   - should_show: Frontend'e "bu veriyi göster/gösterme" sinyali
     *   - predictions: Gerçek olasılık listesi (boş olabilir)
     *
     * Neden tahminleri her zaman hesaplamıyoruz?
     * Monte Carlo 1000 simülasyon çalıştırıyor; bu yaklaşık 20-50ms demek.
     * Ligin başında zaten anlamsız sonuçlar üretir, hem gereksiz hem de yavaş.
     * should_show=false ise predictions boş array dönüyoruz — hesaplama yapılmıyor.
     *
     * Frontend should_show değerine bakarak "Tahminler son 3 haftada açılır"
     * mesajını mı yoksa olasılık çubuklarını mı göstereceğine karar veriyor.
     */
    public function index(): JsonResponse
    {
        $shouldShow    = $this->predictionService->shouldShowPredictions();

        // Erken aşamada ise hesaplamayı tamamen atla
        $probabilities = $shouldShow
            ? $this->predictionService->calculateProbabilities()
            : collect();

        return response()->json([
            'should_show' => $shouldShow,
            'predictions' => $probabilities,
        ]);
    }
}
