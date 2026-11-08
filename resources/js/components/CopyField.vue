<script setup>
import { onBeforeUnmount, ref } from 'vue';
import { copyText } from '../composables/useClipboard';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: String, default: '' },
    multiline: { type: Boolean, default: false },
});

const copied = ref(false);
const failed = ref(false);
let timer = null;

async function copy() {
    const ok = await copyText(props.value);
    copied.value = ok;
    failed.value = !ok;
    clearTimeout(timer);
    timer = setTimeout(() => {
        copied.value = false;
        failed.value = false;
    }, 2000);
}

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <div class="copy-field">
        <div class="copy-field__head">
            <span class="copy-field__label">{{ label }}</span>
            <span class="copy-field__feedback" aria-live="polite">
                <span v-if="copied" class="badge badge--success">Kopiert</span>
                <span v-else-if="failed" class="badge badge--danger">Bitte markieren und kopieren</span>
            </span>
            <button type="button" class="btn btn--secondary btn--sm copy-field__button" :disabled="!value" @click="copy">
                <svg viewBox="0 0 20 20" width="16" height="16" aria-hidden="true"><rect x="7" y="7" width="10" height="10" rx="2" stroke="currentColor" stroke-width="1.5" fill="none" /><path d="M13 7V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2" stroke="currentColor" stroke-width="1.5" fill="none" /></svg>
                Kopieren
            </button>
        </div>
        <div class="copy-field__value" :class="{ 'copy-field__value--multiline': multiline }">{{ value || '–' }}</div>
        <slot />
    </div>
</template>
