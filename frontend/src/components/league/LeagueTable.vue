<script setup lang="ts">
/**
 * LeagueTable — puan tablosunu Premier League formatında gösterir.
 *
 * Sıralama backend'den geliyor (standings prop zaten sıralı);
 * bu bileşen sadece görselleştirme yapıyor, sıralama mantığı yok.
 *
 * İki özel görsel durum (Shadcn UI Royal Blue & White):
 *   - 1. sıra (leader): sol kenarda Royal Blue bir çizgi + rank badge Royal Blue
 *   - 2. sıra: soft mavi tonu rank badge
 *
 * Gol farkı renklendirmesi:
 *   - Pozitif → yeşil (iyi defans/hücum dengesi)
 *   - Negatif → kırmızı
 *   - Sıfır → normal renk
 *
 * Yükleme skeleton'ı: gerçek veri gelene kadar shimmer animasyonlu
 * placeholder satırlar gösteriliyor. "Layout shift" yaşanmaması için
 * skeleton da aynı yükseklikte (48px) tasarlandı.
 *
 * tabular-nums font özelliği: sayılar hizalı görünsün diye.
 * "8" ve "1" gibi farklı genişlikteki rakamlar aynı alanı kaplıyor.
 */
import type { Standing } from '@/types/Standing'

defineProps<{
  standings: Standing[]
  isLoading: boolean
}>()
</script>

<template>
  <div class="league-table">
    <!-- Yükleme skeleton: 4 satır (takım sayısına göre) -->
    <div v-if="isLoading" class="league-table__skeleton">
      <div v-for="i in 4" :key="i" class="skeleton-row" />
    </div>

    <!-- Puan tablosu -->
    <div v-else class="table-container">
      <table class="table" aria-label="League standings table">
        <thead>
          <tr>
            <th class="col-rank">#</th>
            <th class="col-team">Team</th>
            <th class="col-stat" title="Played">P</th>
            <th class="col-stat" title="Won">W</th>
            <th class="col-stat" title="Drawn">D</th>
            <th class="col-stat" title="Lost">L</th>
            <th class="col-stat" title="Goal Difference">GD</th>
            <th class="col-pts" title="Points">PTS</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(standing, index) in standings"
            :key="standing.team_id"
            class="table__row"
            :class="{
              'table__row--leader': index === 0,  /* Lider: sol Royal Blue çizgi */
              'table__row--top2':   index === 1,
            }"
          >
            <!-- Sıralama rozeti: 1. sıra Royal Blue, 2. sıra soft mavi tonu -->
            <td class="col-rank">
              <span class="rank-badge" :class="`rank-badge--${index + 1}`">
                {{ index + 1 }}
              </span>
            </td>

            <!-- Takım hücresi: PNG logo veya kısa isim + isim -->
            <td class="col-team">
              <div class="team-cell">
                <div class="team-logo" :style="{ background: standing.logo_color }">
                  <img v-if="standing.logo_url" :src="standing.logo_url" :alt="standing.team_name" class="team-logo__img" />
                  <span v-else>{{ standing.short_name }}</span>
                </div>
                <span class="team-cell__name">{{ standing.team_name }}</span>
              </div>
            </td>

            <!-- İstatistik sütunları -->
            <td class="col-stat">{{ standing.played }}</td>
            <td class="col-stat">{{ standing.won }}</td>
            <td class="col-stat">{{ standing.drawn }}</td>
            <td class="col-stat">{{ standing.lost }}</td>

            <!-- Gol farkı: pozitif/negatif renk sınıfı, pozitifse "+" ön eki -->
            <td
              class="col-stat"
              :class="{
                'positive': standing.goal_difference > 0,
                'negative': standing.goal_difference < 0
              }"
            >
              {{ standing.goal_difference > 0 ? '+' : '' }}{{ standing.goal_difference }}
            </td>

            <!-- Puan: sleek light-blue rozet içinde, öne çıkarılmış -->
            <td class="col-pts">
              <span class="points-badge">{{ standing.points }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.league-table { width: 100%; }

.table-container {
  width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
  min-width: 480px; /* Ensures columns stay legible while container scrolls cleanly */
}

/* Başlık: küçük, büyük harf, seyrek aralık */
.table thead th {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--color-text-muted);
  padding: 0.75rem 0.75rem;
  text-align: center;
  border-bottom: 1px solid var(--color-border);
}

.table thead th.col-team { text-align: left; }

.table__row {
  border-bottom: 1px solid var(--color-border);
  transition: background 0.15s;
}

.table__row:hover { background: var(--color-surface-3); }

/* Lider satırı: sol kenarda subtle Royal Blue çizgi */
.table__row--leader {
  position: relative;
}
.table__row--leader::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 2px;
  background: var(--color-primary);
}

/* Hücreler: tabular-nums ile sayılar hizalı */
.table td {
  padding: 0.875rem 0.75rem;
  text-align: center;
  color: var(--color-text);
  font-variant-numeric: tabular-nums;
}

/* Sütun hizalamaları */
.col-rank { width: 40px; }
.col-team { text-align: left !important; }
.col-stat { color: var(--color-text-muted); }
.col-pts  { font-weight: 700; }

/* Sıralama rozeti */
.rank-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  font-size: 0.75rem;
  font-weight: 700;
  background: #f1f5f9;
  color: var(--color-text-muted);
}
/* 1. sıra: Royal Blue badge */
.rank-badge--1 { background: var(--color-primary); color: #ffffff; }
/* 2. sıra: Light Blue accent badge */
.rank-badge--2 { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }

/* Takım hücresi: logo + isim yan yana */
.team-cell {
  display: flex;
  align-items: center;
  gap: 0.625rem;
}

.team-logo {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.5625rem;
  font-weight: 800;
  color: white;
  text-shadow: 0 1px 3px rgba(0,0,0,0.4);
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

.team-cell__name {
  font-weight: 600;
  color: var(--color-text);
  font-size: 0.875rem;
}

/* Puan rozeti: Sleek Royal Blue Light Tag */
.points-badge {
  display: inline-block;
  background: #eff6ff;
  color: #1e40af;
  border: 1px solid #bfdbfe;
  font-weight: 700;
  font-size: 0.8125rem;
  padding: 0.25rem 0.5rem;
  border-radius: var(--radius-sm);
}

/* Gol farkı renkleri */
.positive { color: #10b981 !important; font-weight: 600; }
.negative { color: #ef4444 !important; font-weight: 600; }

/* Yükleme skeleton */
.league-table__skeleton {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 0.75rem 0;
}

.skeleton-row {
  height: 48px;
  background: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0.03) 25%,
    rgba(255, 255, 255, 0.08) 50%,
    rgba(255, 255, 255, 0.03) 75%
  );
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: var(--radius-md);
}

@keyframes shimmer { to { background-position: -200% 0; } }
</style>
