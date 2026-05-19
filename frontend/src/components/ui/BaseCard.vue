<script setup lang="ts">
// BaseCard.vue — Shadcn UI modern kart bileşeni. Optionally offers light glassmorphism.
defineProps<{
  title?: string
  glass?: boolean
}>()
</script>

<template>
  <div class="base-card" :class="{ 'base-card--glass': glass }">
    <div v-if="title" class="base-card__header">
      <h2 class="base-card__title">{{ title }}</h2>
    </div>
    <div class="base-card__body">
      <slot />
    </div>
  </div>
</template>

<style scoped>
.base-card {
  background: var(--color-surface-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  overflow: hidden;
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
  transition: all 0.2s ease;
  min-width: 0;
}

.base-card--glass {
  background: rgba(255, 255, 255, 0.45);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border-color: rgba(37, 99, 235, 0.08);
}

.base-card__header {
  padding: 1.5rem 1.5rem 0.5rem;
}

.base-card__title {
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--color-text);
  margin: 0;
  letter-spacing: -0.01em;
}

.base-card__body {
  padding: 0 1.5rem 1.5rem;
}

@media (max-width: 480px) {
  .base-card__header {
    padding: 1rem 1rem 0.5rem;
  }
  .base-card__body {
    padding: 0 1rem 1rem;
  }
}
</style>
