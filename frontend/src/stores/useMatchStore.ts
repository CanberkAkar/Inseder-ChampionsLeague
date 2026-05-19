// src/stores/useMatchStore.ts
//
// Maç verilerini ve simülasyon işlemlerini yöneten Pinia store.
//
// Neden maçlar ayrı bir store'da?
// Maç verisi (özellikle matchesByWeek) oldukça büyük olabilir ve
// yalnızca hafta değişiminde kısmi güncelleme yapabilmek için
// lig durum verisinden ayrı tutmak mantıklı geldi.
//
// playWeek() sonrası sadece oynanan haftanın verisini güncelliyoruz —
// tüm maç listesini yeniden çekmiyoruz. Bu detay, gereksiz ağ trafiğini
// önlemek için önemliydi.

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'
import type { Match, MatchesByWeek } from '@/types/Match'
import { useLeagueStore } from './useLeagueStore'

export const useMatchStore = defineStore('matches', () => {
  // ─── State ───────────────────────────────────────────────────────────────
  const matchesByWeek = ref<MatchesByWeek>({})   // { 1: [...], 2: [...], ... }
  const isLoadingWeek = ref<boolean>(false)       // "Play Week" butonu yüklenme durumu
  const isPlayingAll  = ref<boolean>(false)       // "Play All" butonu yüklenme durumu
  const error         = ref<string | null>(null)

  // ─── Getters ─────────────────────────────────────────────────────────────

  /**
   * Mevcut hafta numaralarını sıralı olarak döner.
   * Object.keys her zaman string döndürüyor; Number() ile sayıya çeviriyoruz.
   * sort() olmadan büyük numaralar önce gelebilirdi (lexicografik sıralama sorunu).
   */
  const allWeeks = computed(() =>
    Object.keys(matchesByWeek.value).map(Number).sort((a, b) => a - b)
  )

  /**
   * Belirli bir haftanın maç listesini döner.
   * Henüz yüklenmemişse boş dizi — undefined'den daha güvenli.
   */
  function getMatchesForWeek(week: number): Match[] {
    return matchesByWeek.value[week] ?? []
  }

  // ─── Actions ─────────────────────────────────────────────────────────────

  /**
   * Tüm maçları haftalara gruplandırılmış halde çeker.
   * Sayfa ilk açıldığında ve reset sonrasında çağrılıyor.
   */
  async function fetchAllMatches(): Promise<void> {
    try {
      const { data } = await api.getMatches()
      matchesByWeek.value = data.matches
    } catch (e) {
      error.value = 'Failed to load matches.'
      console.error(e)
    }
  }

  /**
   * Bir sonraki haftayı simüle ettirir.
   *
   * Önemli optimizasyon: Backend response'unda hangi hafta oynanmıştı
   * (played_week) bilgisi geliyor. Yalnızca o haftayı güncelliyoruz;
   * diğer haftalar olduğu gibi kalıyor. Bu tüm maç listesini yeniden
   * çekmekten çok daha verimli.
   *
   * Ardından puan tablosunu güncellemek için leagueStore.fetchLeague() çağrılıyor.
   * Çapraz store erişimi için useLeagueStore() burada çağrılıyor (lazy init).
   */
  async function playWeek(): Promise<void> {
    isLoadingWeek.value = true
    error.value         = null
    try {
      const { data } = await api.playWeek()

      // Sadece oynanan haftayı state'e yaz, geri kalan haftalara dokunma
      matchesByWeek.value[data.played_week] = data.matches

      // Puan tablosu da değişti; league store'u güncelle
      const leagueStore = useLeagueStore()
      await leagueStore.fetchLeague()
    } catch (e: any) {
      // Lig bittiyse backend 422 döner; hata mesajını oradan alıyoruz
      error.value = e?.response?.data?.message ?? 'Failed to play week.'
    } finally {
      isLoadingWeek.value = false
    }
  }

  /**
   * Kalan tüm maçları tek seferde simüle ettirir.
   *
   * playWeek()'ten farklı olarak tüm maç listesini yeniden yazıyoruz
   * çünkü birden fazla hafta aynı anda değişiyor.
   */
  async function playAll(): Promise<void> {
    isPlayingAll.value = true
    error.value        = null
    try {
      const { data } = await api.playAll()
      matchesByWeek.value = data.matches  // Tüm haftalık veriyi güncelle

      const leagueStore = useLeagueStore()
      await leagueStore.fetchLeague()
    } catch (e) {
      error.value = 'Failed to simulate all matches.'
    } finally {
      isPlayingAll.value = false
    }
  }

  /**
   * Belirli bir maçın skorunu günceller.
   *
   * Backend hem güncellenmiş maçı hem de yeniden hesaplanmış puan tablosunu
   * işliyor. Frontend tarafında sadece o maçın kaydını state'de değiştiriyoruz;
   * tüm listeyi yeniden çekmiyoruz (gereksiz ağ trafiği önlendi).
   *
   * findIndex ile doğru maçı bulup weekMatches[idx] = data.match ile değiştiriyoruz.
   * Vue reaktivitesi bu mutasyonu algılıyor ve ekranı güncelliyor.
   *
   * Hata durumunda throw ederek MatchList bileşeninin de hatayı yakalamasına izin veriyoruz.
   */
  async function updateMatch(matchId: number, homeGoals: number, awayGoals: number): Promise<void> {
    try {
      const { data } = await api.updateMatch(matchId, homeGoals, awayGoals)
      const week     = data.match.week

      // State'deki ilgili haftada sadece değişen maçı güncelle
      const weekMatches = matchesByWeek.value[week]
      if (weekMatches) {
        const idx = weekMatches.findIndex((m) => m.id === matchId)
        if (idx !== -1) {
          weekMatches[idx] = data.match
        }
      }

      // Puan tablosu da değişti (backend recalculate yaptı)
      const leagueStore = useLeagueStore()
      await leagueStore.fetchLeague()
    } catch (e) {
      error.value = 'Failed to update match result.'
      throw e  // Modal bileşeninin hata mesajı göstermesi için yeniden fırlatıyoruz
    }
  }

  return {
    matchesByWeek,
    isLoadingWeek,
    isPlayingAll,
    error,
    allWeeks,
    getMatchesForWeek,
    fetchAllMatches,
    playWeek,
    playAll,
    updateMatch,
  }
})
