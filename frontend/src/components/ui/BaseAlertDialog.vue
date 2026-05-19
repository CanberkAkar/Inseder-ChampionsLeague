<script setup lang="ts">
/**
 * BaseAlertDialog — Shadcn UI inspired confirmation modal.
 *
 * Exposes a clean v-model interface for visibility and emits 'confirm' when
 * the action button is clicked. Uses Teleport to prevent z-index/overflow issues.
 */
defineProps<{
  modelValue: boolean
  title: string
  description: string
  confirmText?: string
  cancelText?: string
  loading?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'confirm': []
}>()

function closeDialog(): void {
  emit('update:modelValue', false)
}

function handleConfirm(): void {
  emit('confirm')
  closeDialog()
}
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="modelValue" class="alert-dialog-overlay" @click.self="closeDialog">
        <div class="alert-dialog-content" role="alertdialog" aria-modal="true">
          <div class="alert-dialog-header">
            <div class="alert-dialog-icon">
              <i class="ph ph-warning-octagon" style="font-size: 1.5rem; color: var(--color-danger);"></i>
            </div>
            <h2 class="alert-dialog-title">{{ title }}</h2>
            <p class="alert-dialog-description">{{ description }}</p>
          </div>
          
          <div class="alert-dialog-footer">
            <button class="btn btn-cancel" :disabled="loading" @click="closeDialog">
              {{ cancelText || 'Cancel' }}
            </button>
            <button class="btn btn-confirm" :disabled="loading" @click="handleConfirm">
              <i v-if="loading" class="ph ph-spinner spinner" style="margin-right: 0.25rem;"></i>
              {{ confirmText || 'Continue' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.alert-dialog-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.65);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  z-index: 1000;
  overflow-y: auto;
  padding: 2rem 1rem;
}

.alert-dialog-content {
  background: #ffffff;
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: var(--border-radius-lg);
  width: 100%;
  max-width: 440px;
  margin: auto;
  padding: 1.5rem;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.1);
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.alert-dialog-header {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 0.5rem;
}

.alert-dialog-icon {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: rgba(239, 68, 68, 0.08);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 0.25rem;
}

.alert-dialog-title {
  font-size: 1.125rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.alert-dialog-description {
  font-size: 0.875rem;
  color: #475569;
  line-height: 1.5;
  margin: 0;
}

.alert-dialog-footer {
  display: flex;
  gap: 0.75rem;
  justify-content: center;
  margin-top: 0.5rem;
}

/* Aksiyon butonları */
.btn {
  flex: 1;
  padding: 0.625rem 1rem;
  font-size: 0.875rem;
  font-weight: 600;
  border-radius: var(--border-radius-md);
  border: none;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: background 0.15s, opacity 0.15s, transform 0.1s;
}

.btn:active:not(:disabled) {
  transform: scale(0.98);
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-cancel {
  background: #f1f5f9;
  color: #0f172a;
  border: 1px solid #e2e8f0;
}

.btn-cancel:hover:not(:disabled) {
  background: #e2e8f0;
}

.btn-confirm {
  background: var(--gradient-primary);
  color: white;
}

.btn-confirm:hover:not(:disabled) {
  opacity: 0.9;
}

/* Spinner animasyonu */
.spinner {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* Geçiş Animasyonları */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-active .alert-dialog-content {
  animation: scaleIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.fade-leave-active .alert-dialog-content {
  animation: scaleOut 0.15s ease-in;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

@keyframes scaleIn {
  from { transform: scale(0.95); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}

@keyframes scaleOut {
  from { transform: scale(1); opacity: 1; }
  to { transform: scale(0.95); opacity: 0; }
}
</style>
