<script setup lang="ts">
/**
 * BaseButton — Tekrar kullanılabilir, erişilebilir buton bileşeni.
 *
 * Tüm buton eylemleri bu bileşen üzerinden gerçekleştirilir. Stillendirmeler
 * tek noktadan yönetilerek Shadcn UI standartlarını yansıtır.
 *
 * variant prop'u dört farklı stil sağlar:
 *   - primary:   Royal Blue dolgu rengi (ana eylemler için)
 *   - secondary: beyaz arka plan, Slate-200 kenarlık (ikincil eylemler)
 *   - danger:    düz kırmızı dolgu rengi (yıkıcı eylemler: reset vb.)
 *   - ghost:     şeffaf arka plan, Slate-100 hover (link benzeri butonlar)
 *
 * loading=true olduğunda buton hem devre dışı kalır hem de spinner gösterir.
 * Spinner CSS animasyonu ile yapılmıştır.
 */
defineProps<{
  variant?: 'primary' | 'secondary' | 'danger' | 'ghost'
  size?: 'sm' | 'md' | 'lg'
  loading?: boolean
  disabled?: boolean
  id?: string   // E2E test ve erişilebilirlik için açık ID desteği
}>()

defineEmits<{
  click: [event: MouseEvent]
}>()
</script>

<template>
  <button
    :id="id"
    class="base-button"
    :class="[
      `base-button--${variant ?? 'primary'}`,
      `base-button--${size ?? 'md'}`,
      { 'base-button--loading': loading }
    ]"
    :disabled="disabled || loading"
    @click="$emit('click', $event)"
  >
    <!-- Yükleniyor spinner — aria-hidden çünkü görsel öge, ekran okuyucuya bilgi vermemeli -->
    <span v-if="loading" class="base-button__spinner" aria-hidden="true" />
    <slot />
  </button>
</template>

<style scoped>
.base-button {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  border: none;
  border-radius: var(--radius-md);
  font-family: var(--font-sans);
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  white-space: nowrap;
  position: relative;
}

.base-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Boyutlar */
.base-button--sm { padding: 0.375rem 0.75rem; font-size: 0.8125rem; }
.base-button--md { padding: 0.625rem 1.25rem; font-size: 0.9375rem; }
.base-button--lg { padding: 0.875rem 1.75rem; font-size: 1.0625rem; }

/* Primary: Royal Blue Brand Button */
.base-button--primary {
  background: #2563eb;
  color: #ffffff;
  box-shadow: 0 1px 2px 0 rgba(37, 99, 235, 0.2);
}
.base-button--primary:hover:not(:disabled) {
  background: #1d4ed8;
}
.base-button--primary .base-button__spinner {
  border-color: rgba(255, 255, 255, 0.2);
  border-top-color: #ffffff;
}

/* Secondary: White background, Slate borders */
.base-button--secondary {
  background: #ffffff;
  color: #0f172a;
  border: 1px solid #e2e8f0;
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}
.base-button--secondary:hover:not(:disabled) {
  background: #f1f5f9;
  border-color: #cbd5e1;
}
.base-button--secondary .base-button__spinner {
  border-color: rgba(15, 23, 42, 0.2);
  border-top-color: #0f172a;
}

/* Danger: Destructive red button */
.base-button--danger {
  background: #ef4444;
  color: #ffffff;
  box-shadow: 0 1px 2px 0 rgba(239, 68, 68, 0.1);
}
.base-button--danger:hover:not(:disabled) {
  background: #dc2626;
}
.base-button--danger .base-button__spinner {
  border-color: rgba(255, 255, 255, 0.2);
  border-top-color: #ffffff;
}

/* Ghost: transparent, slate text, slate-100 on hover */
.base-button--ghost {
  background: transparent;
  color: #0f172a;
}
.base-button--ghost:hover:not(:disabled) {
  background: #f1f5f9;
}
.base-button--ghost .base-button__spinner {
  border-color: rgba(15, 23, 42, 0.2);
  border-top-color: #0f172a;
}

/* Dönen yükleme göstergesi */
.base-button__spinner {
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.2);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
