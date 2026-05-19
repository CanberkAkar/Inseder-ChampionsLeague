<script setup lang="ts">
/**
 * MatchCard — tek bir maçı kart formatında gösterir.
 *
 * Tasarım: ev sahibi solda, deplasman sağda, skor ortada.
 * Deplasman takımının yönü flex-direction: row-reverse ile tersine çevrildi;
 * logo sağda, isim solda kalıyor. Bu CSS trick bileşen mantığını basit tutuyor.
 *
 * is_played durumuna göre iki görünüm var:
 *   - Oynanmış: skor gösterilir (galibiyet/beraberlik rengi ile), düzenle butonu
 *   - Oynanmamış: "VS" yazısı gösterilir
 *
 * canEdit prop'u MatchList'ten geliyor; sadece is_played=true olan maçlarda true.
 * Teorik olarak is_played ile aynı şey ama prop olarak geçmek bileşeni
 * daha esnek kılıyor — gelecekte başka koşullar eklenebilir.
 */
import type { Match } from '@/types/Match'

const props = defineProps<{
  match: Match
  canEdit?: boolean   // Düzenleme butonunu göster/gizle
}>()

const emit = defineEmits<{
  edit: [match: Match]  // Üst bileşen (MatchList) modal'ı açıyor
}>()
</script>

<template>
  <div class="match-card" :class="{ 'match-card--played': match.is_played }">
    <!-- Ev sahibi: logo solda, isim yanında -->
    <div class="team home">
      <div class="team__logo" :style="{ background: match.home_logo_color }">
        <img v-if="match.home_logo_url" :src="match.home_logo_url" :alt="match.home_team_name" class="team__logo-img" />
        <span v-else>{{ match.home_short_name }}</span>
      </div>
      <span class="team__name long-name">{{ match.home_team_name }}</span>
      <span class="team__name short-name">{{ match.home_short_name }}</span>
    </div>

    <!-- Skor alanı: oynanmışsa skor + düzenleme ikonu, oynanmamışsa VS -->
    <div class="score-area">
      <template v-if="match.is_played">
        <span
          class="score"
          :class="{
            'score--home-win': match.result === 'home_win',
            'score--away-win': match.result === 'away_win',
            'score--draw':     match.result === 'draw',
          }"
        >
          {{ match.home_goals }} – {{ match.away_goals }}
        </span>
        <!-- Kalem ikonu — küçük ve subtle, hover'da netleşiyor -->
        <button
          v-if="canEdit"
          class="edit-btn"
          :aria-label="`Edit result: ${match.home_team_name} vs ${match.away_team_name}`"
          @click="emit('edit', match)"
          title="Edit result"
        >
          <i class="ph ph-pencil-simple" style="font-size: 0.95rem; vertical-align: middle;"></i>
        </button>
      </template>
      <span v-else class="vs">VS</span>
    </div>

    <!-- Deplasman: row-reverse ile logo sağda kalıyor -->
    <div class="team away">
      <span class="team__name long-name">{{ match.away_team_name }}</span>
      <span class="team__name short-name">{{ match.away_short_name }}</span>
      <div class="team__logo" :style="{ background: match.away_logo_color }">
        <img v-if="match.away_logo_url" :src="match.away_logo_url" :alt="match.away_team_name" class="team__logo-img" />
        <span v-else>{{ match.away_short_name }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.match-card {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.875rem 1rem;
  background: var(--color-surface-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  transition: all 0.2s ease;
}

.match-card:hover {
  border-color: #bfdbfe;
  background: #eff6ff;
}

.match-card--played {
  background: #f8fafc;
}

/* Takım alanı: 1/1 genişlik paylaşımı, taşmaları önleme */
.team {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
}

/* Deplasman takımı: flex-direction yok, sadece row-reverse ile logo sağa geçiyor */
.team.away {
  justify-content: flex-end;
  flex-direction: row-reverse;
}

.team__logo {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.5rem;
  font-weight: 800;
  color: white;
  text-shadow: 0 1px 2px rgba(0,0,0,0.4);
  flex-shrink: 0;
  overflow: hidden;
  position: relative;
}

.team__logo-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.team__name {
  font-size: 0.8125rem;
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
  .long-name {
    display: none !important;
  }
  .short-name {
    display: inline-block !important;
  }
}

.score-area {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
  min-width: 70px;
}

.score {
  font-size: 0.95rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  color: var(--color-text);
}

/* Skor renkleri: ev sahibi galibiyeti yeşil, deplasman kırmızı, beraberlik nötr */
.score--home-win { color: #10b981; }
.score--away-win { color: #10b981; }
.score--draw     { color: var(--color-text-muted); }

.vs {
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--color-text-muted);
  border: 1px solid var(--color-border);
  padding: 0.15rem 0.4rem;
  border-radius: 4px;
  background: #f1f5f9;
}

/* Kalem butonu: default'ta soluk, hover'da belirginleşiyor */
.edit-btn {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 0.75rem;
  color: var(--color-text-muted);
  opacity: 0.5;
  transition: opacity 0.15s, color 0.15s;
  padding: 0;
}

.edit-btn:hover {
  opacity: 1;
  color: #ffffff;
}
</style>
