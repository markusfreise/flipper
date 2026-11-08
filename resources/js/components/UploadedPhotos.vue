<script setup>
import { ref } from 'vue';
import { useWizardStore } from '../stores/wizard';
import { errorMessage } from '../api/client';

defineProps({
    photos: { type: Array, required: true },
    editable: { type: Boolean, default: false },
});

const wizard = useWizardStore();
const error = ref('');

const STATUS = { uploaded: 'wartet', cutting: 'wird freigestellt', cut: 'wird gebaut', cutout_failed: 'Original', composed: '' };

async function run(fn) {
    error.value = '';
    try {
        await fn();
    } catch (e) {
        error.value = errorMessage(e);
    }
}
</script>

<template>
    <div>
        <ul class="photo-grid" aria-label="Hochgeladene Fotos">
            <li v-for="photo in photos" :key="photo.id" class="photo-grid__item" :class="{ 'photo-grid__item--detail': photo.kind === 'detail' }">
                <img :src="photo.final_url || photo.thumb_url" :alt="`Foto ${photo.position}`" class="photo-grid__img" loading="lazy">
                <span v-if="photo.kind === 'detail'" class="photo-grid__tag badge badge--warning">Nahaufnahme</span>
                <span v-else-if="STATUS[photo.status]" class="photo-grid__tag badge badge--neutral">{{ STATUS[photo.status] }}</span>
                <button v-if="editable" type="button" class="photo-grid__remove" :aria-label="`Foto ${photo.position} löschen`" @click="run(() => wizard.deletePhoto(photo.id))">×</button>
                <label v-if="editable" class="photo-grid__toggle form__check">
                    <input type="checkbox" :checked="photo.kind === 'detail'" @change="run(() => wizard.updatePhoto(photo.id, { kind: photo.kind === 'detail' ? 'overview' : 'detail' }))">
                    Nahaufnahme/Mangel
                </label>
            </li>
        </ul>
        <p v-if="error" class="form__error">{{ error }}</p>
    </div>
</template>
