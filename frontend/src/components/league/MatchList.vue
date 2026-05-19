<script setup lang="ts">
/**
 * MatchList — haftalık maç sonuçlarını sekme tabanlı gösterir.
 *
 * Tasarım kararı: Tab sistemi burada lokal state olarak tutuldu,
 * store'a taşınmadı. Seçili haftanın saklanması bir "UI tercihi",
 * uygulama verisi değil. Sayfa yenilenince sıfırlanması sorun değil.
 *
 * Maç düzenleme: editingMatch ref'i modal açık/kapalı durumunu kontrol ediyor.
 * null ise modal gösterilmiyor, Match nesnesi atanınca görünüyor.
 * Bu pattern Vue'da yaygın: v-if="editingMatch" ile conditional rendering.
 */
import { ref, computed } from 'vue'
import type { Match } from '@/types/Match'
import MatchCard      from './MatchCard.vue'
import MatchEditModal from '@/components/ui/MatchEditModal.vue'
import { useMatchStore } from '@/stores/useMatchStore'

const props = defineProps<{
  matchesByWeek: Record<number, Match[]>
  currentWeek: number    // Oynanacak hafta (tab'ı otomatik seçmek için)
  isFinished: boolean
}>()

const matchStore = useMatchStore()

// Açılışta mevcut haftayı göster; currentWeek=0 ise (lig bitti) 1. haftayı göster
const selectedWeek = ref<number>(props.currentWeek || 1)
const editingMatch = ref<Match | null>(null)  // null = modal kapalı
const editError    = ref<string | null>(null)

/**
 * Görünür hafta numaraları — maç verisi olan haftaları sıralı listeler.
 * Fikstür oluşturulmamışsa boş dizi dönüyor (skeleton gösterilmiyor, sekme de yok).
 */
const visibleWeeks = computed(() =>
  Object.keys(props.matchesByWeek).map(Number).sort((a, b) => a - b)
)

/**
 * Seçili haftanın maçları.
 * Boş dizi dönmesi "Maç yok" mesajı göstermek için kullanılıyor.
 */
const currentMatches = computed(() =>
  props.matchesByWeek[selectedWeek.value] ?? []
)

/**
 * Düzenleme modalını açar.
 * editingMatch'e maç nesnesi atanması v-if ile modal'ı görünür kılar.
 */
function openEdit(match: Match): void {
  editingMatch.value = match
  editError.value    = null
}

/**
 * Modal'daki "Save" butonuna basınca çalışır.
 *
 * store.updateMatch() başarılıysa modal kapanıyor.
 * Hata olursa editError güncelleniyor ama modal açık kalıyor;
 * kullanıcı tekrar deneyebilir.
 */
async function handleSave(matchId: number, homeGoals: number, awayGoals: number): Promise<void> {
  try {
    await matchStore.updateMatch(matchId, homeGoals, awayGoals)
    editingMatch.value = null  // Başarılıysa modal'ı kapat
  } catch {
    editError.value = 'Failed to update match. Please try again.'
  }
}
</script>

<template>
  <div class="match-list">
    <!-- Hafta sekmeleri: W1, W2, ..., W6 -->
    <div class="week-tabs" role="tablist" aria-label="Match weeks">
      <button
        v-for="week in visibleWeeks"
        :key="week"
        :id="`week-tab-${week}`"
        class="week-tab"
        :class="{
          'week-tab--active':  week === selectedWeek,
          'week-tab--current': week === currentWeek && !isFinished,
          'week-tab--played':  week < currentWeek || isFinished,
        }"
        role="tab"
        :aria-selected="week === selectedWeek"
        @click="selectedWeek = week"
      >
        W{{ week }}
      </button>
    </div>

    <!-- Seçili haftanın maçları -->
    <div class="matches" role="tabpanel">
      <!-- Sadece oynanan maçlar düzenlenebilir (can-edit = is_played) -->
      <MatchCard
        v-for="match in currentMatches"
        :key="match.id"
        :match="match"
        :can-edit="match.is_played"
        @edit="openEdit"
      />

      <p v-if="currentMatches.length === 0" class="no-matches">
        No matches scheduled.
      </p>
    </div>

    <!-- Maç düzenleme modal'ı — editingMatch null değilse görünür -->
    <MatchEditModal
      v-if="editingMatch"
      :match="editingMatch"
      @save="handleSave"
      @cancel="editingMatch = null"
    />
  </div>
</template>

<style scoped>
.match-list { display: flex; flex-direction: column; gap: 1rem; }

.week-tabs {
  display: flex;
  gap: 0.375rem;
  overflow-x: auto;
  padding-bottom: 0.25rem;
  scrollbar-width: none; /* Hide scrollbar for Clean Firefox UI */
  -webkit-overflow-scrolling: touch;
}

.week-tabs::-webkit-scrollbar {
  display: none; /* Hide scrollbar for Chrome/Safari */
}

.week-tab {
  flex-shrink: 0;
  padding: 0.375rem 0.75rem;
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border);
  background: var(--color-surface-2);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

/* Hover: hafif vurgu */
.week-tab:hover {
  background: #f1f5f9;
  color: #0f172a;
  border-color: #cbd5e1;
}

/* Aktif (seçili) sekme: Royal Blue tag */
.week-tab--active {
  background: var(--color-primary);
  color: #ffffff;
  border-color: var(--color-primary);
  box-shadow: 0 1px 2px 0 rgba(37, 99, 235, 0.15);
}

/* Oynanmış haftalar: yeşil tonu */
.week-tab--played:not(.week-tab--active) {
  color: #10b981;
  border-color: rgba(16, 185, 129, 0.2);
}

/* Mevcut hafta (henüz oynanmamış): primary sınır */
.week-tab--current:not(.week-tab--active) {
  border-color: var(--color-primary);
  color: var(--color-primary);
}

.matches { display: flex; flex-direction: column; gap: 0.5rem; }

.no-matches {
  text-align: center;
  color: var(--color-text-muted);
  font-size: 0.875rem;
  padding: 1rem;
}
</style>
