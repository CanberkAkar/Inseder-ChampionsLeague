<script setup lang="ts">
/**
 * PredictionPanel — şampiyonluk olasılıklarını yatay progress bar ile gösterir.
 *
 * İki durum var:
 *   1. isLoading=true → skeleton bar'lar (shimmer animasyonlu)
 *   2. Geri kalan durum → tahmin olasılık listesi (1. haftadan itibaren aktif)
 *
 * shouldShow bayrağı backend'den gelir; Monte Carlo simülasyonları
 * 1. haftadan itibaren sürekli güncellenerek lig heyecanını artırır.
 *
 * Bar genişliği CSS width olarak verilir (`${pred.probability}%`).
 * Geçiş animasyonu CSS transition ile yapılır (0.8s cubic-bezier);
 * her hafta oynanınca barlar smooth animasyonla hareket eder.
 *
 * Lider takımın bar'ı Royal Blue rengiyle ve gölge efektiyle vurgulanır.
 */
import type { Prediction } from '@/types/Prediction'

defineProps<{
  predictions: Prediction[]
  isLoading: boolean
  shouldShow: boolean    // Backend'den gelen "gösterilmeli mi?" kararı
  currentWeek: number
  totalWeeks: number
}>()
</script>

<template>
  <div class="prediction-panel">
    <!-- Yükleniyor: 4 adet shimmer row (iskelet) -->
    <template v-if="isLoading">
      <div v-for="i in 4" :key="i" class="skeleton-row">
        <div class="skeleton-logo shimmer" />
        <div class="skeleton-name shimmer" />
        <div class="skeleton-bar-wrap">
          <div class="skeleton-bar shimmer" />
        </div>
        <div class="skeleton-pct shimmer" />
      </div>
    </template>

    <!-- Tahminler hazır ve gösterilmeli -->
    <template v-else-if="shouldShow && predictions.length">
      <div
        v-for="(pred, index) in predictions"
        :key="pred.team_id"
        class="prediction-row"
        :class="{ 'prediction-row--leader': index === 0 }"
      >
        <!-- Takım adı ve logosu -->
        <div class="prediction-row__team">
          <div class="team-logo" :style="{ background: pred.logo_color }">
            <img v-if="pred.logo_url" :src="pred.logo_url" :alt="pred.team_name" class="team-logo__img" />
            <span v-else>{{ pred.short_name }}</span>
          </div>
          <span class="team-name long-name">{{ pred.team_name }}</span>
          <span class="team-name short-name">{{ pred.short_name }}</span>
        </div>

        <!-- Olasılık çubuğu: genişlik CSS transition ile animasyonlu -->
        <div class="prediction-row__bar-wrap">
          <div
            class="prediction-row__bar"
            :style="{ width: `${pred.probability}%` }"
            :class="{ 'bar--leader': index === 0 }"
          />
        </div>

        <!-- Yüzde değeri -->
        <span class="prediction-row__pct">{{ pred.probability }}%</span>
      </div>
    </template>
  </div>
</template>

<style scoped>
.prediction-panel {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.prediction-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

/* Takım ismi + logo — sabit genişlik, bar'ın hizalanması için */
.prediction-row__team {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 140px;
  flex-shrink: 0;
}

.team-logo {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.45rem;
  font-weight: 800;
  color: white;
  text-shadow: 0 1px 2px rgba(0,0,0,0.4);
  flex-shrink: 0;
  overflow: hidden;
  position: relative;
}

.team-logo__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.team-name {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--color-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.short-name {
  display: none;
}

@media (max-width: 480px) {
  .prediction-row__team {
    width: 68px !important;
  }
  .long-name {
    display: none !important;
  }
  .short-name {
    display: inline-block !important;
  }
}

/* Bar kapsayıcı: gri arka plan, bar üstüne oturuyor */
.prediction-row__bar-wrap {
  flex: 1;
  height: 8px;
  background: #f1f5f9;
  border-radius: 99px;
  overflow: hidden;
}

/* Bar: genişlik prop'tan geliyor, transition ile animasyonlu */
.prediction-row__bar {
  height: 100%;
  border-radius: 99px;
  background: #bfdbfe;
  transition: width 0.8s cubic-bezier(0.23, 1, 0.32, 1);
}

/* Lider bar: Royal Blue gradient + subtle shadow */
.bar--leader {
  background: var(--gradient-primary);
  box-shadow: 0 1px 3px rgba(37, 99, 235, 0.2);
}

/* Yüzde değeri: sabit genişlik, hizalama için */
.prediction-row__pct {
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--color-text);
  width: 40px;
  text-align: right;
  flex-shrink: 0;
}

/* Lider takımın ismi primary renkte */
.prediction-row--leader .team-name {
  color: var(--color-primary);
}

/* Skeleton (Iskelet Yükleniyor) */
.skeleton-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.25rem 0;
}

.skeleton-logo {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #e2e8f0;
  flex-shrink: 0;
}

.skeleton-name {
  width: 80px;
  height: 14px;
  border-radius: 4px;
  background: #e2e8f0;
  flex-shrink: 0;
}

.skeleton-bar-wrap {
  flex: 1;
  height: 8px;
  border-radius: 99px;
  background: #f1f5f9;
  overflow: hidden;
}

.skeleton-bar {
  width: 100%;
  height: 100%;
}

.skeleton-pct {
  width: 40px;
  height: 14px;
  border-radius: 4px;
  background: #e2e8f0;
  flex-shrink: 0;
}

.shimmer {
  background: linear-gradient(
    90deg,
    #e2e8f0 25%,
    #f1f5f9 50%,
    #e2e8f0 75%
  );
  background-size: 200% 100%;
  animation: shimmer-effect 1.5s infinite;
}

@keyframes shimmer-effect {
  to { background-position: -200% 0; }
}
</style>
