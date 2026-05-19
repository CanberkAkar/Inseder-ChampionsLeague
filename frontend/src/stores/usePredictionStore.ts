// src/stores/usePredictionStore.ts
//
// Şampiyonluk tahminlerini yöneten store.
//
// Kasıtlı olarak küçük ve odaklı tutuldu.
// Tek sorumluluğu: backend'den tahmin verisini çekmek ve tutmak.
// "Gösterilmeli mi?" kararı (shouldShow) da backend veriyor;
// bu mantığı frontend'e taşımak backend ile senkronizasyon sorununa yol açardı.
//
// fetchPredictions() her hafta oynandıktan sonra LeagueView'den çağrılıyor;
// bu sayede tahmin paneli her simülasyon sonrası güncel kalıyor.

import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/services/api'
import type { Prediction } from '@/types/Prediction'

export const usePredictionStore = defineStore('predictions', () => {
  // ─── State ───────────────────────────────────────────────────────────────
  const predictions = ref<Prediction[]>([])   // Takım bazlı olasılık listesi (büyükten küçüğe)
  const shouldShow  = ref<boolean>(false)      // Backend'den gelen gösterim kararı
  const isLoading   = ref<boolean>(false)

  // ─── Actions ─────────────────────────────────────────────────────────────

  /**
   * Backend'den tahmin verilerini çeker.
   *
   * should_show=false gelirse predictions boş array olarak dönüyor
   * (backend hesaplama yapmıyor). State'i yine de güncelliyoruz ki
   * panel "Tahminler son 3 haftada açılır" mesajını doğru gösterebilsin.
   *
   * Hata durumunda sessizce geçiyoruz (throw etmiyoruz).
   * Tahmin paneli kritik bir özellik değil; gösteremezsek büyük sorun değil.
   */
  async function fetchPredictions(): Promise<void> {
    isLoading.value = true
    try {
      const { data } = await api.getPredictions()
      shouldShow.value  = data.should_show
      predictions.value = data.predictions
    } catch (e) {
      // Tahmin verisi yüklenemezse panel gizli kalıyor; kullanıcıya hata göstermiyoruz
      console.error('Failed to load predictions:', e)
    } finally {
      isLoading.value = false
    }
  }

  return {
    predictions,
    shouldShow,
    isLoading,
    fetchPredictions,
  }
})
