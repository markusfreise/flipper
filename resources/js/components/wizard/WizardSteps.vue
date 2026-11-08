<script setup>
import { STEPS } from '../../stores/wizard';

defineProps({
    current: { type: Number, required: true },
    shown: { type: Number, required: true },
});
defineEmits(['go']);
</script>

<template>
    <nav class="wizard-steps" aria-label="Schritte">
        <ol class="wizard-steps__list">
            <li v-for="step in STEPS" :key="step.n">
                <button
                    type="button"
                    class="wizard-steps__item"
                    :class="{
                        'wizard-steps__item--done': step.n < current,
                        'wizard-steps__item--active': step.n === shown,
                    }"
                    :disabled="step.n > current"
                    :aria-current="step.n === shown ? 'step' : undefined"
                    @click="$emit('go', step.n)"
                >
                    <span class="wizard-steps__num" aria-hidden="true">{{ step.n < current ? '✓' : step.n }}</span>
                    <span class="wizard-steps__label">{{ step.label }}</span>
                </button>
            </li>
        </ol>
    </nav>
</template>
