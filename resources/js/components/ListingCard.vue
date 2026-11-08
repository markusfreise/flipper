<script setup>
import { computed } from 'vue';

const props = defineProps({ listing: { type: Object, required: true } });
defineEmits(['delete']);

const STATUS = {
    photos: ['Fotos fehlen', 'neutral'],
    identifying: ['Erkennt …', 'info'],
    identified: ['Erkennung prüfen', 'warning'],
    researching: ['Fragen offen', 'warning'],
    questions: ['Fragen offen', 'warning'],
    writing: ['Schreibt …', 'info'],
    ready: ['Fertig', 'success'],
    failed: ['Fehler', 'danger'],
};

const badge = computed(() => STATUS[props.listing.status] ?? [props.listing.status, 'neutral']);
const title = computed(() => props.listing.text?.title || props.listing.product?.name || 'Ohne Titel');
const date = computed(() => new Date(props.listing.created_at).toLocaleDateString('de-DE', { day: 'numeric', month: 'short', year: 'numeric' }));
</script>

<template>
    <article class="listing-card card card--clickable">
        <RouterLink :to="{ name: 'wizard', params: { id: listing.id } }" class="listing-card__link">
            <span class="listing-card__thumb">
                <img v-if="listing.cover_url" :src="listing.cover_url" alt="" loading="lazy">
                <span v-else class="listing-card__placeholder" aria-hidden="true">📷</span>
            </span>
            <span class="listing-card__body">
                <span class="listing-card__title">{{ title }}</span>
                <span class="listing-card__meta">
                    <span class="badge" :class="`badge--${badge[1]}`">{{ badge[0] }}</span>
                    <span class="muted small">{{ date }}</span>
                </span>
            </span>
        </RouterLink>
        <button type="button" class="btn btn--danger-ghost btn--icon touch-target listing-card__delete" :aria-label="`${title} löschen`" @click="$emit('delete')">
            <svg viewBox="0 0 20 20" width="18" height="18" aria-hidden="true"><path d="M7 3h6m-9 3h12m-1 0-.7 10.2a1 1 0 0 1-1 .8H6.7a1 1 0 0 1-1-.8L5 6m3 3v5m4-5v5" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" /></svg>
        </button>
    </article>
</template>
