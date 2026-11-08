<script setup>
import { computed, onBeforeUnmount, watch } from 'vue';
import { useWizardStore } from '../stores/wizard';
import { errorMessage } from '../api/client';
import WizardSteps from '../components/wizard/WizardSteps.vue';
import StepPhotos from '../components/wizard/StepPhotos.vue';
import StepIdentify from '../components/wizard/StepIdentify.vue';
import StepQuestions from '../components/wizard/StepQuestions.vue';
import StepResult from '../components/wizard/StepResult.vue';
import ErrorCard from '../components/ErrorCard.vue';

const props = defineProps({ id: { type: String, required: true } });
const wizard = useWizardStore();

const loadError = computed(() => wizard.error);

watch(() => props.id, async (id) => {
    try {
        await wizard.load(id);
    } catch (e) {
        wizard.error = e?.response?.status === 404 || e?.response?.status === 403
            ? 'Dieses Inserat gibt es nicht (mehr).'
            : errorMessage(e);
    }
}, { immediate: true });

onBeforeUnmount(() => wizard.reset());

const title = computed(() => wizard.listing?.text?.title || wizard.listing?.product?.name || 'Neues Inserat');
</script>

<template>
    <div class="wizard">
        <header class="page__header wizard__header">
            <div>
                <RouterLink to="/" class="link small">← Alle Inserate</RouterLink>
                <h1 class="heading-1 wizard__title">{{ title }}</h1>
            </div>
        </header>

        <p v-if="loadError" class="alert alert--danger" role="alert">{{ loadError }}</p>
        <div v-else-if="!wizard.listing" class="loading"><span class="spinner spinner--center" aria-label="Lädt" /></div>

        <template v-else>
            <WizardSteps :current="wizard.currentStep" :shown="wizard.shownStep" @go="wizard.goTo" />

            <ErrorCard v-if="wizard.listing.status === 'failed'" class="mb1" />

            <StepPhotos v-if="wizard.shownStep === 1" />
            <StepIdentify v-else-if="wizard.shownStep === 2" />
            <StepQuestions v-else-if="wizard.shownStep === 3" />
            <StepResult v-else />
        </template>
    </div>
</template>
