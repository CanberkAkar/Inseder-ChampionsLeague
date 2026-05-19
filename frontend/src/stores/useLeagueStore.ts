// src/stores/useLeagueStore.ts
//
// Lig durumunu (puan tablosu, hafta bilgisi) yöneten Pinia store.
// Composition API stiliyle yazıldı; Options API'ya göre daha esnek
// ve TypeScript ile daha iyi entegre oluyor.
//
// Bu store sadece lig genel durumunu tutuyor.
// Maç detayları useMatchStore'da, tahminler usePredictionStore'da.
// Sorumlulukları ayırmak her store'u daha küçük ve test edilebilir kılıyor.

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'
import type { Standing } from '@/types/Standing'

export const useLeagueStore = defineStore('league', () => {
  // ─── State ───────────────────────────────────────────────────────────────
  const standings   = ref<Standing[]>([])   // Sıralı puan tablosu (backend sıralıyor)
  const currentWeek = ref<number>(1)         // Bir sonraki oynanacak hafta (0 = bitti)
  const totalWeeks  = ref<number>(6)         // Toplam hafta sayısı (sabit: 6)
  const isFinished  = ref<boolean>(false)    // true ise sezon tamamlandı
  const isLoading   = ref<boolean>(false)    // Yükleme göstergesi için
  const error       = ref<string | null>(null)

  // ─── Getters ─────────────────────────────────────────────────────────────

  /**
   * Puan tablosunun birincisi — standings zaten sıralı geldiği için
   * sadece ilk elemana bakıyoruz. Boş dizide null dönüyor.
   */
  const leader = computed(() => standings.value[0] ?? null)

  /**
   * Kaç hafta kaldığını hesaplayan computed.
   * Lig bittiyse 0, devam ediyorsa (totalWeeks - currentWeek + 1).
   * "+1" çünkü currentWeek'in kendisi de henüz oynanmadı.
   *
   * Örnek: currentWeek=4, totalWeeks=6 → 6-4+1 = 3 hafta kaldı
   */
  const weeksRemaining = computed(() =>
    isFinished.value ? 0 : totalWeeks.value - currentWeek.value + 1
  )

  // ─── Actions ─────────────────────────────────────────────────────────────

  /**
   * Backend'den güncel lig durumunu çeker ve state'i günceller.
   *
   * isLoading bayrağı açılıp kapanıyor; bu sayede bileşen "yükleniyor"
   * skeleton'ını gösterebiliyor. finally bloğu hata olsa da olmasa da
   * loading'i false yapıyor — aksi halde takılı kalırdı.
   */
  async function fetchLeague(): Promise<void> {
    isLoading.value = true
    error.value     = null
    try {
      const { data } = await api.getLeague()
      standings.value   = data.standings
      currentWeek.value = data.current_week
      totalWeeks.value  = data.total_weeks
      isFinished.value  = data.is_finished
    } catch (e) {
      error.value = 'Failed to load league data.'
      console.error(e)
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Ligi sıfırlar ve ardından güncel durumu yeniden çeker.
   *
   * Reset işlemi backend'de tamamlanmadan fetchLeague() çağrılmamalı;
   * bu yüzden await sıralı. Hata yönetimi burada değil fetchLeague'de.
   */
  async function resetLeague(): Promise<void> {
    isLoading.value = true
    try {
      await api.resetLeague()
      await fetchLeague()  // Sıfırlanmış durumu yükle
    } finally {
      isLoading.value = false
    }
  }

  return {
    standings,
    currentWeek,
    totalWeeks,
    isFinished,
    isLoading,
    error,
    leader,
    weeksRemaining,
    fetchLeague,
    resetLeague,
  }
})
