<script setup>
import { ref } from 'vue';
import { useWizardStore } from '../stores/wizard';
import { errorMessage } from '../api/client';

const wizard = useWizardStore();
const busy = ref(false);
const error = ref('');

async function retry() {
    busy.value = true;
    error.value = '';
    try {
        await wizard.retry();
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <section class="card error-card" role="alert">
        <div class="card__body">
            <h2 class="heading-3">Hat nicht geklappt.</h2>
            <p class="muted mt05">{{ wizard.listing.last_error || 'Unbekannter Fehler.' }}</p>
            <p v-if="error" class="form__error mt05">{{ error }}</p>
            <button type="button" class="btn btn--primary mt1" :disabled="busy" @click="retry">
                <span v-if="busy" class="spinner" aria-hidden="true" />
                Nochmal versuchen
            </button>
        </div>
    </section>
</template>
