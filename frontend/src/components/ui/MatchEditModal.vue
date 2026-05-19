<script setup lang="ts">
/**
 * MatchEditModal — oynanan bir maçın skorunu düzenlemek için modal.
 *
 * Vue Teleport kullanıyoruz: modal içeriği DOM'da <body>'e taşınıyor.
 * Bu sayede z-index ve overflow sorunları yaşanmıyor.
 * Eğer modal parent bileşenin içinde kalırsa, parent'ın overflow:hidden
 * veya z-index sınırlamaları modal'ı kesebildiğinden Teleport zorunlu.
 *
 * Overlay'e tıklayınca kapanma (@click.self): .self modifier'ı kritik,
 * modal içine tıklayınca da kapanmaması için yalnızca overlay'in
 * kendisine tıklanınca emit yapılıyor.
 *
 * homeGoals ve awayGoals local ref olarak tutuldu:
 *   - Modal açılırken mevcut skorla başlatılıyor (prop'tan).
 *   - Kullanıcı değiştirirse sadece local state değişiyor,
 *     store/parent etkilenmiyor (iptal durumunda sıfırlanmış oluyor).
 *   - Save'e basılınca emit ile üste geçiyor.
 *
 * Gol input'larındaki spinner (ok butonları) CSS ile kaldırıldı;
 * varsayılan number input görünümü tasarımla uyuşmuyordu.
 */
import { ref } from 'vue'
import type { Match } from '@/types/Match'
import BaseButton from '@/components/ui/BaseButton.vue'

const props = defineProps<{
  match: Match
}>()

const emit = defineEmits<{
  save:   [matchId: number, homeGoals: number, awayGoals: number]
  cancel: []
}>()

// Mevcut skorla başlat; null gelirse 0 (oynanmış maç için null olmaz ama tip güvenliği için)
const homeGoals = ref<number>(props.match.home_goals ?? 0)
const awayGoals = ref<number>(props.match.away_goals ?? 0)
const isSaving  = ref<boolean>(false)

/**
 * Kaydeder ve üst bileşene bildirir.
 *
 * isSaving butonu devre dışı bırakıyor; çift tıklamada iki istek atılmasın.
 * Asıl kaydetme işlemi parent (MatchList) tarafından yapılıyor;
 * biz sadece emit ediyoruz. Hata yönetimi de parent'ta.
 */
async function handleSave(): Promise<void> {
  isSaving.value = true
  try {
    emit('save', props.match.id, homeGoals.value, awayGoals.value)
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <!-- Teleport: modal body'ye taşınıyor, z-index/overflow sorunları yok -->
  <Teleport to="body">
    <!-- Overlay: dışarıya tıklayınca kapat (.self = yalnızca overlay'e tıklayınca) -->
    <div class="modal-overlay" @click.self="$emit('cancel')">
      <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">

        <!-- Başlık ve kapat butonu -->
        <div class="modal__header">
          <h3 id="modal-title" class="modal__title">Edit Match Result</h3>
          <button class="modal__close" @click="$emit('cancel')" aria-label="Close">
            <i class="ph ph-x" style="font-size: 1.1rem; vertical-align: middle;"></i>
          </button>
        </div>

        <!-- Maç düzenleme alanı: ev sahibi | VS | deplasman -->
        <div class="modal__body">
          <div class="match-teams">
            <!-- Ev sahibi: logo, isim, gol input -->
            <div class="team-edit">
              <div class="team-badge" :style="{ background: match.home_logo_color }">
                <img v-if="match.home_logo_url" :src="match.home_logo_url" :alt="match.home_team_name" class="team-badge__img" />
                <span v-else>{{ match.home_short_name }}</span>
              </div>
              <span class="team-name">{{ match.home_team_name }}</span>
              <input
                id="home-goals-input"
                v-model.number="homeGoals"
                type="number"
                min="0"
                max="20"
                class="goals-input"
                aria-label="Home team goals"
              />
            </div>

            <span class="vs-divider">VS</span>

            <!-- Deplasman: logo, isim, gol input -->
            <div class="team-edit">
              <div class="team-badge" :style="{ background: match.away_logo_color }">
                <img v-if="match.away_logo_url" :src="match.away_logo_url" :alt="match.away_team_name" class="team-badge__img" />
                <span v-else>{{ match.away_short_name }}</span>
              </div>
              <span class="team-name">{{ match.away_team_name }}</span>
              <input
                id="away-goals-input"
                v-model.number="awayGoals"
                type="number"
                min="0"
                max="20"
                class="goals-input"
                aria-label="Away team goals"
              />
            </div>
          </div>
        </div>

        <!-- Aksiyon butonları -->
        <div class="modal__footer">
          <BaseButton id="cancel-edit-btn" variant="secondary" @click="$emit('cancel')">
            Cancel
          </BaseButton>
          <BaseButton id="save-edit-btn" variant="primary" :loading="isSaving" @click="handleSave">
            Save Result
          </BaseButton>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
/* Arka plan overlay: blur efekti ile */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  z-index: 999;
  overflow-y: auto;
  padding: 2rem 1rem;
}

/* Modal kutusu: max genişlik 480px, mobilde 90vw */
.modal {
  background: var(--color-surface-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  width: 100%;
  max-width: 460px;
  margin: auto;
  overflow: hidden;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
  animation: modalEnter 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modalEnter {
  from { opacity: 0; transform: scale(0.95) translateY(10px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}

.modal__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--color-border);
}

.modal__title {
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--color-text);
  margin: 0;
}

.modal__close {
  background: none;
  border: none;
  color: var(--color-text-muted);
  cursor: pointer;
  padding: 0.25rem;
  border-radius: var(--border-radius-sm);
  transition: color 0.15s, background 0.15s;
}

.modal__close:hover {
  color: var(--color-text);
  background: var(--color-surface-2);
}

.modal__body {
  padding: 2rem 1.5rem;
}

.modal__footer {
  display: flex;
  gap: 0.75rem;
  justify-content: flex-end;
  padding: 1.25rem 1.5rem;
  border-top: 1px solid var(--color-border);
}

/* Maç düzenleme alanı */
.match-teams {
  display: flex;
  align-items: center;
  gap: 1rem;
  justify-content: center;
}

.team-edit {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  flex: 1;
}

@media (max-width: 480px) {
  .modal__body {
    padding: 1.5rem 1rem;
  }
  .match-teams {
    gap: 0.5rem;
  }
  .team-name {
    font-size: 0.75rem;
  }
  .goals-input {
    width: 60px;
    height: 42px;
    font-size: 1.25rem;
  }
  .team-badge {
    width: 40px;
    height: 40px;
  }
}

.team-badge {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.6875rem;
  font-weight: 800;
  color: white;
  text-shadow: 0 1px 3px rgba(0,0,0,0.4);
  overflow: hidden;
  position: relative;
}

.team-badge__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.team-name {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text);
  text-align: center;
}

/* Gol input: büyük font, ortalanmış, ok butonları gizli */
.goals-input {
  width: 72px;
  height: 48px;
  border-radius: var(--radius-md);
  border: 2px solid var(--color-border);
  background: var(--color-surface-2);
  color: var(--color-text);
  font-size: 1.5rem;
  font-weight: 700;
  text-align: center;
  transition: border-color 0.2s;
  -moz-appearance: textfield; /* Firefox ok butonlarını gizle */
}

/* Chrome/Safari ok butonlarını gizle */
.goals-input::-webkit-outer-spin-button,
.goals-input::-webkit-inner-spin-button {
  -webkit-appearance: none;
}

.goals-input:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}

.vs-divider {
  font-size: 0.75rem;
  font-weight: 800;
  color: var(--color-text-muted);
  letter-spacing: 0.1em;
}
</style>
