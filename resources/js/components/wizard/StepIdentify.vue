<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useWizardStore } from '../../stores/wizard';
import { errorMessage } from '../../api/client';
import PhotoCheckList from '../PhotoCheckList.vue';
import UploadedPhotos from '../UploadedPhotos.vue';

const wizard = useWizardStore();
const listing = computed(() => wizard.listing);
const form = reactive({ name: '', brand: '', model: '', number: '', year: '', variant: '' });
const busy = ref(false);
const error = ref('');
const fieldErrors = ref({});

watch(() => listing.value.product, (p) => {
    if (!p) return;
    Object.assign(form, {
        name: p.name ?? '', brand: p.brand ?? '', model: p.model ?? '', number: p.number ?? '',
        year: p.year ?? '', variant: p.variant ?? '',
    });
}, { immediate: true });

const confidence = computed(() => listing.value.product?.confidence ?? 0);
const sure = computed(() => confidence.value >= 0.75);
const identifying = computed(() => listing.value.status === 'identifying');

async function confirm() {
    busy.value = true;
    error.value = '';
    fieldErrors.value = {};
    try {
        const payload = Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v === '' ? null : v]));
        payload.year = payload.year ? Number(payload.year) : null;
        await wizard.saveProduct(payload);
    } catch (e) {
        fieldErrors.value = e?.response?.data?.errors ?? {};
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}

async function reidentify() {
    busy.value = true;
    error.value = '';
    try {
        await wizard.reidentify();
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}

const fields = [
    { key: 'name', label: 'Produkt', required: true },
    { key: 'brand', label: 'Hersteller' },
    { key: 'number', label: 'Modell/Nummer' },
    { key: 'year', label: 'Jahr', type: 'number' },
    { key: 'variant', label: 'Variante' },
];
</script>

<template>
    <section class="card wizard__card">
        <div class="card__header">
            <div>
                <h2 class="card__title">2 · Erkennung</h2>
                <p class="muted small">Stimmt das? Korrigiere, was nicht passt. Darauf baut der Text auf.</p>
            </div>
            <span v-if="listing.product" class="badge" :class="sure ? 'badge--success' : 'badge--warning'">
                {{ sure ? 'ziemlich sicher' : 'unsicher, bitte prüfen' }}
            </span>
        </div>
        <div class="card__body wizard__body">
            <div v-if="identifying" class="fxc g05"><span class="spinner" aria-hidden="true" /> Erkenne Produkt neu …</div>

            <form v-else class="form" novalidate @submit.prevent="confirm">
                <div v-for="f in fields" :key="f.key" class="form__group">
                    <label class="form__label" :for="`p-${f.key}`">{{ f.label }}<span v-if="f.required" aria-hidden="true"> *</span></label>
                    <input
                        :id="`p-${f.key}`"
                        v-model="form[f.key]"
                        class="form__input"
                        :type="f.type || 'text'"
                        :inputmode="f.type === 'number' ? 'numeric' : undefined"
                        :required="f.required"
                    >
                    <span v-if="fieldErrors[f.key]" class="form__error">{{ fieldErrors[f.key][0] }}</span>
                </div>
                <p v-if="listing.product?.notes" class="form__hint">{{ listing.product.notes }}</p>
                <p v-if="error" class="form__alert" role="alert">{{ error }}</p>
                <div class="form__actions">
                    <button type="button" class="btn btn--secondary" :disabled="busy" @click="reidentify">Neu erkennen</button>
                    <button type="submit" class="btn btn--primary" :disabled="busy || !form.name.trim()">
                        <span v-if="busy" class="spinner" aria-hidden="true" />
                        Stimmt so
                    </button>
                </div>
            </form>

            <hr class="divider">
            <UploadedPhotos :photos="listing.photos" />
            <PhotoCheckList :check="listing.photo_check" :photo-count="listing.photos.length" />
        </div>
    </section>
</template>
