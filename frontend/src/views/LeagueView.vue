<script setup lang="ts">
/**
 * LeagueView — uygulamanın tek sayfası (SPA).
 *
 * Tüm store'ları burada başlatıyor ve bileşenlere prop olarak geçiyoruz.
 * Bu "container component" yaklaşımı: veri yönetimi burada, sunum
 * alt bileşenlerde. Bu ayrım bileşen testini kolaylaştırıyor.
 *
 * onMounted'da üç API çağrısı sıralı atılıyor (await await await).
 * Paralel yapılabilirdi (Promise.all) ama sıralı olması debug'ı kolaylaştırıyor;
 * hangi çağrının hata verdiği net görünüyor.
 *
 * handlePlayWeek ve handlePlayAll her ikisi de tahminleri yeniliyor.
 * Çünkü her hafta değişimi olasılıkları değiştirebilir.
 */
import { ref, onMounted } from 'vue'
import { useLeagueStore }     from '@/stores/useLeagueStore'
import { useMatchStore }      from '@/stores/useMatchStore'
import { usePredictionStore } from '@/stores/usePredictionStore'
import BaseCard               from '@/components/ui/BaseCard.vue'
import BaseButton             from '@/components/ui/BaseButton.vue'
import BaseAlertDialog        from '@/components/ui/BaseAlertDialog.vue'
import LeagueTable            from '@/components/league/LeagueTable.vue'
import MatchList              from '@/components/league/MatchList.vue'
import PredictionPanel        from '@/components/league/PredictionPanel.vue'

const leagueStore     = useLeagueStore()
const matchStore      = useMatchStore()
const predictionStore = usePredictionStore()

const showResetConfirm = ref<boolean>(false)

// Sayfa yüklendiğinde tüm veriyi çek
onMounted(async () => {
  await leagueStore.fetchLeague()
  await matchStore.fetchAllMatches()
  await predictionStore.fetchPredictions()
})

/**
 * Bir sonraki haftayı oynatır.
 * Maç sonuçları + puan tablosu matchStore içinde güncelleniyor;
 * biz sadece tahminleri yeniliyoruz (olasılıklar değişmiş olabilir).
 */
async function handlePlayWeek(): Promise<void> {
  await matchStore.playWeek()
  await predictionStore.fetchPredictions()
}

/**
 * Kalan tüm haftaları simüle eder.
 * Tüm maçlar bitince sezon kapanıyor (isFinished=true),
 * tahmin paneli gerçek sonucu gösterecek şekilde güncelleniyor.
 */
async function handlePlayAll(): Promise<void> {
  await matchStore.playAll()
  await predictionStore.fetchPredictions()
}

/**
 * Lig sıfırlama onay diyaloğunu açar.
 */
function handleReset(): void {
  showResetConfirm.value = true
}

/**
 * Sıfırlama işlemini gerçekleştirir.
 * Onaydan sonra: league sıfırla → maçları yeniden çek → tahminleri sıfırla.
 */
async function executeReset(): Promise<void> {
  await leagueStore.resetLeague()
  await matchStore.fetchAllMatches()
  await predictionStore.fetchPredictions()
}
</script>

<template>
  <div class="league-view">
    <!-- Header: başlık + aksiyon butonları -->
    <header class="header">
      <div class="header__brand">
        <div class="header__logo">
          <i class="ph ph-soccer-ball" style="font-size: 1.5rem; color: white;"></i>
        </div>
        <div>
          <h1 class="header__title">Champions League</h1>
          <p class="header__subtitle">Group Stage Simulator</p>
        </div>
      </div>

      <div class="header__actions">
        <!-- Lig devam ediyorsa "Week X/6", bittiyse trofeli rozet göster -->
        <span v-if="!leagueStore.isFinished" class="week-badge">
          Week {{ leagueStore.currentWeek }} / {{ leagueStore.totalWeeks }}
        </span>
        <span v-else class="finished-badge">
          <i class="ph ph-trophy" style="font-size: 1.1rem; color: #fbbf24; margin-right: 0.25rem; vertical-align: middle;"></i>
          Season Complete
        </span>

        <!-- Play Week butonu: haftalık simülasyon -->
        <BaseButton
          id="play-week-btn"
          variant="secondary"
          size="sm"
          :loading="matchStore.isLoadingWeek"
          :disabled="leagueStore.isFinished || matchStore.isPlayingAll"
          @click="handlePlayWeek"
        >
          <i class="ph ph-play" style="font-size: 1rem;"></i>
          Play Week
        </BaseButton>

        <!-- Tüm kalan haftaları simüle et — Play Week çalışırken devre dışı -->
        <BaseButton
          id="play-all-btn"
          variant="primary"
          size="sm"
          :loading="matchStore.isPlayingAll"
          :disabled="leagueStore.isFinished || matchStore.isLoadingWeek"
          @click="handlePlayAll"
        >
          <i class="ph ph-lightning" style="font-size: 1rem;"></i>
          Play All
        </BaseButton>

        <!-- Sıfırlama — her zaman aktif, confirm ile güvenceye alındı -->
        <BaseButton
          id="reset-btn"
          variant="danger"
          size="sm"
          @click="handleReset"
        >
          <i class="ph ph-arrow-counter-clockwise" style="font-size: 1rem;"></i>
          Reset
        </BaseButton>
      </div>
    </header>

    <!-- Hata mesajı — matchStore'dan geliyor (play-week/play-all hataları) -->
    <div v-if="matchStore.error" class="error-banner" role="alert">
      <i class="ph ph-warning-octagon" style="font-size: 1.1rem; margin-right: 0.25rem; vertical-align: middle;"></i> {{ matchStore.error }}
    </div>

    <!-- İki kolonlu ana grid: sol=standings+predictions, sağ=matches -->
    <main class="grid">
      <!-- Sol kolon: puan tablosu ve şampiyonluk tahminleri -->
      <section class="col-left">
        <BaseCard title="League Table">
          <LeagueTable
            :standings="leagueStore.standings"
            :is-loading="leagueStore.isLoading"
          />
        </BaseCard>

        <BaseCard title="Championship Predictions" :glass="true">
          <PredictionPanel
            :predictions="predictionStore.predictions"
            :is-loading="predictionStore.isLoading"
            :should-show="predictionStore.shouldShow"
            :current-week="leagueStore.currentWeek"
            :total-weeks="leagueStore.totalWeeks"
          />
        </BaseCard>
      </section>

      <!-- Sağ kolon: haftalık maç sonuçları ve düzenleme -->
      <section class="col-right">
        <BaseCard title="Match Results">
          <MatchList
            :matches-by-week="matchStore.matchesByWeek"
            :current-week="leagueStore.currentWeek"
            :is-finished="leagueStore.isFinished"
          />
        </BaseCard>
      </section>
    </main>

    <!-- Custom Shadcn-like Alert Dialog for League Reset -->
    <BaseAlertDialog
      v-model="showResetConfirm"
      title="Are you absolutely sure?"
      description="This action cannot be undone. This will permanently reset all standings, match scores, and championship predictions to week 1."
      confirm-text="Reset League"
      cancel-text="Cancel"
      :loading="leagueStore.isLoading"
      @confirm="executeReset"
    />
  </div>
</template>

<style scoped>
.league-view {
  min-height: 100vh;
  padding: 1.5rem;
  max-width: 1200px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

/* Header */
.header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
  padding: 1.25rem 1.5rem;
  background: var(--color-surface-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}

.header__brand {
  display: flex;
  align-items: center;
  gap: 0.875rem;
}

.header__logo {
  width: 44px;
  height: 44px;
  background: var(--color-primary);
  border: 1px solid var(--color-primary);
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  color: #ffffff;
}

.header__title {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--color-text);
  margin: 0;
  letter-spacing: -0.02em;
}

.header__subtitle {
  font-size: 0.75rem;
  color: var(--color-text-muted);
  margin: 0;
  font-weight: 500;
}

.header__actions {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  flex-wrap: wrap;
}

/* Hafta rozeti */
.week-badge {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text-muted);
  padding: 0.375rem 0.75rem;
  background: #f1f5f9;
  border-radius: var(--radius-sm);
  border: 1px solid #e2e8f0;
}

/* Sezon bitti rozeti */
.finished-badge {
  font-size: 0.8125rem;
  font-weight: 700;
  color: #fbbf24;
  padding: 0.375rem 0.75rem;
  background: rgba(251,191,36,0.06);
  border-radius: var(--radius-sm);
  border: 1px solid rgba(251,191,36,0.15);
}

/* Hata banner'ı */
.error-banner {
  padding: 0.875rem 1.25rem;
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.15);
  border-radius: var(--radius-md);
  color: #f87171;
  font-size: 0.875rem;
  font-weight: 500;
}

/* İki kolonlu grid — mobilde tek kolona düşer */
.grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.25rem;
  align-items: start;
  min-width: 0;
}

.col-left, .col-right {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  min-width: 0;
}

@media (max-width: 768px) {
  .league-view { padding: 0.75rem; gap: 1rem; }
  .grid { grid-template-columns: 1fr; gap: 1rem; }
  .header { flex-direction: column; align-items: stretch; gap: 1.25rem; }
  .header__brand { justify-content: flex-start; width: 100%; }
  .header__actions { justify-content: flex-start; width: 100%; }
}

@media (max-width: 520px) {
  .header__brand {
    justify-content: center;
    text-align: center;
    flex-direction: column;
    gap: 0.5rem;
  }
  .header__actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
    width: 100%;
  }
  .week-badge, .finished-badge {
    grid-column: span 2;
    text-align: center;
    justify-content: center;
    display: flex;
    align-items: center;
  }
  #reset-btn {
    grid-column: span 2;
  }
}
</style>
