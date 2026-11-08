<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { useWizardStore } from '../stores/wizard';
import { errorMessage } from '../api/client';

const props = defineProps({
    max: { type: Number, default: 10 },
    submitLabel: { type: String, default: 'Weiter' },
    compact: { type: Boolean, default: false },
});
const emit = defineEmits(['uploaded', 'cancel']);

const wizard = useWizardStore();
const MAX_BYTES = 15 * 1024 * 1024;
const items = ref([]); // { file, url, kind }
const error = ref('');
const progress = ref(null);
const uploading = ref(false);
const dragging = ref(false);
const input = ref(null);

const free = computed(() => props.max - items.value.length);

function isHeic(file) {
    return /heic|heif/i.test(file.type) || /\.(heic|heif)$/i.test(file.name);
}

/** HEIC im Browser zu JPEG wandeln, wenn er es kann (Safari). Sonst wandelt der Server. */
async function toUploadable(file) {
    if (!isHeic(file) || typeof createImageBitmap !== 'function') return file;
    try {
        const bitmap = await createImageBitmap(file);
        const canvas = document.createElement('canvas');
        canvas.width = bitmap.width;
        canvas.height = bitmap.height;
        canvas.getContext('2d').drawImage(bitmap, 0, 0);
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.95));
        return blob ? new File([blob], file.name.replace(/\.(heic|heif)$/i, '.jpg'), { type: 'image/jpeg' }) : file;
    } catch {
        return file;
    }
}

async function add(fileList) {
    error.value = '';
    const files = Array.from(fileList || []).filter((f) => f.type.startsWith('image/') || isHeic(f));
    if (files.length > free.value) {
        error.value = `Höchstens ${props.max} Fotos. ${files.length - Math.max(free.value, 0)} wurden weggelassen.`;
    }
    for (const raw of files.slice(0, Math.max(free.value, 0))) {
        if (raw.size > MAX_BYTES) {
            error.value = `${raw.name} ist größer als 15 MB.`;
            continue;
        }
        const file = await toUploadable(raw);
        const previewable = !isHeic(file);
        items.value.push({ file, url: previewable ? URL.createObjectURL(file) : null, kind: 'overview' });
    }
    if (input.value) input.value.value = '';
}

function onDrop(e) {
    dragging.value = false;
    add(e.dataTransfer?.files);
}

function remove(i) {
    const [item] = items.value.splice(i, 1);
    if (item?.url) URL.revokeObjectURL(item.url);
}

function toggleDetail(item) {
    item.kind = item.kind === 'detail' ? 'overview' : 'detail';
}

async function upload() {
    if (!items.value.length) return;
    uploading.value = true;
    error.value = '';
    progress.value = 0;
    try {
        await wizard.uploadPhotos(items.value.map((i) => i.file), items.value.map((i) => i.kind), (p) => (progress.value = p));
        items.value.forEach((i) => i.url && URL.revokeObjectURL(i.url));
        items.value = [];
        emit('uploaded');
    } catch (e) {
        error.value = errorMessage(e, 'Hochladen hat nicht geklappt.');
    } finally {
        uploading.value = false;
        progress.value = null;
    }
}

onBeforeUnmount(() => items.value.forEach((i) => i.url && URL.revokeObjectURL(i.url)));
</script>

<template>
    <div class="photo-picker" :class="{ 'photo-picker--compact': compact }">
        <div
            class="dropzone"
            :class="{ 'dropzone--active': dragging, 'dropzone--disabled': free <= 0 || uploading }"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <span class="dropzone__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="32" height="32"><path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linejoin="round" /><circle cx="12" cy="13.5" r="3.5" stroke="currentColor" stroke-width="1.5" fill="none" /></svg>
            </span>
            <span class="dropzone__title">Fotos aufnehmen oder auswählen</span>
            <span class="dropzone__actions">
                <label class="btn btn--primary dropzone__button">
                    <input class="sr-only" type="file" accept="image/*" capture="environment" :disabled="free <= 0 || uploading" @change="add($event.target.files); $event.target.value = ''">
                    Kamera
                </label>
                <label class="btn btn--secondary dropzone__button">
                    <input ref="input" class="sr-only" type="file" accept="image/*,.heic,.heif" multiple :disabled="free <= 0 || uploading" @change="add($event.target.files)">
                    Aus Fotos wählen
                </label>
            </span>
            <span class="dropzone__hint">oder hierher ziehen · JPEG, PNG, HEIC bis 15 MB · noch {{ Math.max(free, 0) }} möglich</span>
        </div>

        <ul v-if="items.length" class="photo-grid" aria-label="Ausgewählte Fotos">
            <li v-for="(item, i) in items" :key="i" class="photo-grid__item" :class="{ 'photo-grid__item--detail': item.kind === 'detail' }">
                <img v-if="item.url" :src="item.url" :alt="`Foto ${i + 1}`" class="photo-grid__img">
                <span v-else class="photo-grid__img photo-grid__img--placeholder">{{ item.file.name }}</span>
                <button type="button" class="photo-grid__remove" :aria-label="`Foto ${i + 1} entfernen`" @click="remove(i)">×</button>
                <label class="photo-grid__toggle form__check">
                    <input type="checkbox" :checked="item.kind === 'detail'" @change="toggleDetail(item)">
                    Nahaufnahme/Mangel
                </label>
            </li>
        </ul>

        <p v-if="error" class="form__alert" role="alert">{{ error }}</p>

        <div v-if="uploading" class="photo-picker__progress">
            <div class="bar bar--block"><div class="bar__fill" :style="{ width: (progress ?? 100) + '%' }" /></div>
            <span class="small muted">Lade hoch … {{ progress !== null ? progress + ' %' : '' }}</span>
        </div>

        <div class="form__actions">
            <button v-if="compact" type="button" class="btn btn--ghost" :disabled="uploading" @click="$emit('cancel')">Abbrechen</button>
            <button type="button" class="btn btn--primary" :disabled="!items.length || uploading" @click="upload">
                <span v-if="uploading" class="spinner" aria-hidden="true" />
                {{ submitLabel }}
            </button>
        </div>
    </div>
</template>
