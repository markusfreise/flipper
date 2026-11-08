<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useWizardStore } from '../../stores/wizard';
import { errorMessage } from '../../api/client';
import ProgressLines from '../ProgressLines.vue';
import { CONDITIONS, PLATFORMS, QUICK_ANSWERS } from './questions';

const wizard = useWizardStore();
const listing = computed(() => wizard.listing);


const form = reactive({ background: '', condition: [], condition_notes: '', platform: 'kleinanzeigen', pickup: true });
const busy = ref(false);
const error = ref('');
const touched = ref(false);
const area = ref(null);

watch(() => listing.value.answers, (a) => {
    if (a) Object.assign(form, { ...a, condition: [...(a.condition ?? [])], condition_notes: a.condition_notes ?? '' });
}, { immediate: true });

const backgroundMissing = computed(() => !form.background.trim());
const waiting = computed(() => listing.value.status === 'writing' || (listing.value.status === 'researching' && listing.value.answers));
const pickupLocation = computed(() => listing.value.pickup_location);

function quick(text) {
    form.background = form.background.trim() ? `${form.background.trim()} ${text}.` : `${text}.`;
    autosize();
    area.value?.focus();
}

function autosize() {
    const el = area.value;
    if (!el) return;
    requestAnimationFrame(() => {
        el.style.height = 'auto';
        el.style.height = `${el.scrollHeight + 2}px`;
    });
}

async function submit() {
    touched.value = true;
    if (backgroundMissing.value) return;
    busy.value = true;
    error.value = '';
    try {
        await wizard.saveAnswers({ ...form });
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}

const lines = computed(() => {
    const l = listing.value;
    const photos = l.photos;
    const done = photos.filter((p) => p.final_url).length;
    return [
        { label: l.research ? 'Preise recherchiert' : 'Recherchiere Preise …', done: !!l.research },
        { label: l.text ? 'Text geschrieben' : 'Schreibe Text …', done: !!l.text },
        { label: done < photos.length ? `Baue Bilder ${done}/${photos.length} …` : 'Bilder fertig', done: done >= photos.length },
    ];
});
</script>

<template>
    <section class="card wizard__card">
        <div class="card__header">
            <div>
                <h2 class="card__title">3 · Drei Fragen</h2>
                <p class="muted small">Damit der Text persönlich und ehrlich wird.</p>
            </div>
        </div>

        <div v-if="waiting" class="card__body wizard__body">
            <ProgressLines :lines="lines" />
            <p class="small muted">Recherche und Text dauern meist ein bis zwei Minuten.</p>
        </div>

        <form v-else class="card__body form wizard__body" novalidate @submit.prevent="submit">
            <fieldset class="question">
                <legend class="question__legend">1. Warum hattest du das, und warum geht es weg?</legend>
                <div class="question__chips" role="group" aria-label="Schnellantworten">
                    <button v-for="q in QUICK_ANSWERS" :key="q" type="button" class="btn btn--secondary btn--sm" @click="quick(q)">{{ q }}</button>
                </div>
                <textarea
                    ref="area"
                    v-model="form.background"
                    class="form__textarea question__autosize"
                    rows="3"
                    aria-label="Hintergrund"
                    placeholder="z. B. Hab ich meiner Tochter zum Geburtstag geschenkt, steht seitdem im Regal. Wir räumen das Kinderzimmer um."
                    :aria-invalid="touched && backgroundMissing"
                    @input="autosize"
                />
                <span v-if="touched && backgroundMissing" class="form__error">Erzähl kurz, warum du das hattest und warum es weg soll.</span>
            </fieldset>

            <fieldset class="question">
                <legend class="question__legend">2. Zustand</legend>
                <div class="question__options">
                    <label v-for="c in CONDITIONS" :key="c" class="form__check">
                        <input v-model="form.condition" type="checkbox" :value="c">
                        {{ c }}
                    </label>
                </div>
                <label class="form__label mt05" for="notes">Was sollte man noch wissen?</label>
                <textarea id="notes" v-model="form.condition_notes" class="form__textarea" rows="2" placeholder="z. B. kleine Delle unten links an der Box" />
            </fieldset>

            <fieldset class="question">
                <legend class="question__legend">3. Wo willst du verkaufen?</legend>
                <div class="question__options question__options--row">
                    <label v-for="p in PLATFORMS" :key="p.value" class="form__check">
                        <input v-model="form.platform" type="radio" name="platform" :value="p.value">
                        {{ p.label }}
                    </label>
                </div>
                <label class="form__check mt05">
                    <input v-model="form.pickup" type="checkbox">
                    Abholung möglich ({{ pickupLocation }})
                </label>
            </fieldset>

            <p v-if="error" class="form__alert" role="alert">{{ error }}</p>
            <div class="form__actions">
                <button type="submit" class="btn btn--primary btn--lg" :disabled="busy">
                    <span v-if="busy" class="spinner" aria-hidden="true" />
                    Inserat erstellen
                </button>
            </div>
        </form>
    </section>
</template>
