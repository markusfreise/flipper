<script setup>
import { computed } from 'vue';
import { useWizardStore } from '../../stores/wizard';
import PhotoPicker from '../PhotoPicker.vue';
import PhotoTips from '../PhotoTips.vue';
import ProgressLines from '../ProgressLines.vue';
import UploadedPhotos from '../UploadedPhotos.vue';

const wizard = useWizardStore();
const listing = computed(() => wizard.listing);
const photos = computed(() => listing.value.photos);
const working = computed(() => listing.value.status === 'identifying');

const lines = computed(() => {
    const l = listing.value;
    const overview = l.photos.filter((p) => p.kind === 'overview');
    const cut = overview.filter((p) => !['uploaded', 'cutting'].includes(p.status)).length;
    return [
        { label: l.product ? 'Produkt erkannt' : 'Erkenne Produkt …', done: !!l.product },
        { label: l.photo_check ? 'Fotos geprüft' : 'Prüfe Fotos …', done: !!l.photo_check },
        ...(overview.length ? [{ label: cut < overview.length ? `Stelle frei ${cut + 1}/${overview.length} …` : 'Freigestellt', done: cut >= overview.length }] : []),
    ];
});
</script>

<template>
    <section class="card wizard__card">
        <div class="card__header">
            <div>
                <h2 class="card__title">1 · Fotos</h2>
                <p class="muted small">1–10 Fotos vom Gegenstand. Mängel ruhig mitfotografieren und als Nahaufnahme markieren.</p>
            </div>
        </div>
        <div class="card__body wizard__body">
            <template v-if="working">
                <UploadedPhotos :photos="photos" />
                <ProgressLines :lines="lines" />
                <p class="small muted">Das dauert meist unter einer Minute. Du kannst die Seite offen lassen oder später wiederkommen.</p>
            </template>
            <template v-else>
                <UploadedPhotos v-if="photos.length" :photos="photos" editable />
                <PhotoPicker :max="10 - photos.length" :submit-label="photos.length ? 'Fotos hinzufügen' : 'Weiter'" />
                <PhotoTips :open="!photos.length" />
            </template>
        </div>
    </section>
</template>
