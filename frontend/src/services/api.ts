// src/services/api.ts
//
// Tüm backend iletişimi buradan geçiyor. Axios instance tek bir yerde
// oluşturuluyor; baseURL ve ortak header'lar merkezi olarak yönetiliyor.
// Böylece endpoint değişince tek yeri güncellemek yeterli oluyor.
//
// VITE_API_URL ortam değişkeni Docker'da http://localhost/api olarak ayarlı.
// Lokal geliştirmede tanımlı değilse '/api' fallback'i devreye giriyor
// (Vite proxy veya nginx yönlendirmesi varsayılıyor).

import axios from 'axios'
import type { AxiosResponse } from 'axios'
import type { Standing } from '@/types/Standing'
import type { Match, MatchesByWeek } from '@/types/Match'
import type { Prediction } from '@/types/Prediction'
import type { Team } from '@/types/Team'

// Tekil axios instance — tüm istekler bu üzerinden gidiyor
const http = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

// ─── Response Tipleri ─────────────────────────────────────────────────────────
// Her endpoint'in döndürdüğü veri yapısını TypeScript ile tanımlıyoruz.
// Bu sayede store'larda data.standings yazarken otomatik tamamlama çalışıyor
// ve tip hataları derleme zamanında yakalanıyor.

export interface LeagueResponse {
  standings: Standing[]
  current_week: number
  total_weeks: number
  is_finished: boolean  // true ise tüm maçlar tamamlandı
}

export interface MatchesResponse {
  matches: MatchesByWeek  // { "1": [...], "2": [...] } formatında hafta → maçlar
}

export interface WeekMatchesResponse {
  week: number
  matches: Match[]
}

export interface PlayWeekResponse {
  message: string
  played_week: number  // Hangi hafta oynanmıştı? Store'da sadece o haftayı güncellemek için
  matches: Match[]
}

export interface PredictionsResponse {
  should_show: boolean    // Tahmin paneli görünsün mü?
  predictions: Prediction[]
}

export interface UpdateMatchResponse {
  message: string
  match: Match  // Güncellenmiş maç — store'da anlık güncelleme için
}

// ─── API Metodları ────────────────────────────────────────────────────────────
// Her metod sadece HTTP çağrısını yapar ve Promise döner.
// Hata yönetimi store katmanında yapılıyor; burada catch yok.
// Bu ayrımı kasıtlı yaptım: api.ts veri katmanı, store.ts iş mantığı katmanı.

export const api = {
  /**
   * Puan tablosu, mevcut hafta ve sezon durumunu çeker.
   * Uygulama her yüklendiğinde ve reset sonrasında çağrılıyor.
   */
  getLeague(): Promise<AxiosResponse<LeagueResponse>> {
    return http.get('/league')
  },

  /**
   * Tüm takım listesini çeker (id, isim, güç, renk).
   * Şu an aktif olarak kullanılmıyor ama takım yönetimi
   * eklenirse hazır endpoint olarak burada duruyor.
   */
  getTeams(): Promise<AxiosResponse<{ teams: Team[] }>> {
    return http.get('/teams')
  },

  /**
   * Tüm maçları haftalara göre gruplandırılmış halde çeker.
   * Sayfa ilk yüklendiğinde bir kez çağrılıyor.
   */
  getMatches(): Promise<AxiosResponse<MatchesResponse>> {
    return http.get('/matches')
  },

  /**
   * Belirli bir haftanın maçlarını çeker.
   * Şu an kullanılmıyor; ileride lazy loading için hazır.
   */
  getMatchesByWeek(week: number): Promise<AxiosResponse<WeekMatchesResponse>> {
    return http.get(`/matches/week/${week}`)
  },

  /**
   * Bir sonraki oynanmamış haftayı simüle ettirir.
   * Response'da hangi haftanın oynanmıştı bilgisi de geliyor;
   * store sadece o haftayı güncelliyor, tüm maç listesini değil.
   */
  playWeek(): Promise<AxiosResponse<PlayWeekResponse>> {
    return http.post('/matches/play-week')
  },

  /**
   * Kalan tüm maçları tek seferde simüle ettirir.
   * Response'da güncellenmiş tüm maç listesi geliyor.
   */
  playAll(): Promise<AxiosResponse<MatchesResponse>> {
    return http.post('/matches/play-all')
  },

  /**
   * Oynanan bir maçın skorunu günceller.
   * Backend puan tablosunu yeniden hesaplayıp güncellenmiş maçı döner.
   */
  updateMatch(id: number, homeGoals: number, awayGoals: number): Promise<AxiosResponse<UpdateMatchResponse>> {
    return http.put(`/matches/${id}`, { home_goals: homeGoals, away_goals: awayGoals })
  },

  /**
   * Şampiyonluk olasılıklarını çeker (Monte Carlo simülasyonu).
   * Backend should_show=false döndürürse predictions boş gelir.
   */
  getPredictions(): Promise<AxiosResponse<PredictionsResponse>> {
    return http.get('/predictions')
  },

  /**
   * Ligi tamamen sıfırlar: maçlar silinir, puan tablosu temizlenir,
   * fikstür yeniden oluşturulur. Geri alınamaz.
   */
  resetLeague(): Promise<AxiosResponse<{ message: string }>> {
    return http.post('/league/reset')
  },
}

export default api
