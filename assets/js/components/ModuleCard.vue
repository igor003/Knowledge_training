<template>
  <article class="metric-card" :class="`metric-card--${tone}`">
    <span class="metric-card__label">{{ label }}</span>
    <strong class="metric-card__value">{{ value }}</strong>
  </article>
</template>

<script setup>
defineProps({
  label: {
    type: String,
    required: true,
  },
  value: {
    type: String,
    required: true,
  },
  tone: {
    type: String,
    default: 'neutral',
  },
});
</script>
