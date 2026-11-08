<script setup>
defineProps({ lines: { type: Array, required: true } });
</script>

<template>
    <ul class="progress-lines" aria-live="polite">
        <li v-for="line in lines" :key="line.label" class="progress-lines__item" :class="{ 'progress-lines__item--done': line.done }">
            <span v-if="line.done" class="progress-lines__icon" aria-hidden="true">✓</span>
            <span v-else class="spinner progress-lines__icon" aria-hidden="true" />
            <span>{{ line.label }}</span>
        </li>
    </ul>
</template>
