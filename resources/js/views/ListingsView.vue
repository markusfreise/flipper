<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, errorMessage } from '../api/client';
import ListingCard from '../components/ListingCard.vue';

const router = useRouter();
const listings = ref([]);
const loading = ref(true);
const creating = ref(false);
const error = ref('');

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/listings');
        listings.value = data.data;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}

async function create() {
    creating.value = true;
    try {
        const { data } = await api.post('/listings');
        router.push({ name: 'wizard', params: { id: data.id } });
    } catch (e) {
        error.value = errorMessage(e);
        creating.value = false;
    }
}

async function remove(listing) {
    if (!window.confirm('Dieses Inserat mit allen Fotos löschen?')) return;
    try {
        await api.delete(`/listings/${listing.id}`);
        listings.value = listings.value.filter((l) => l.id !== listing.id);
    } catch (e) {
        error.value = errorMessage(e);
    }
}

onMounted(load);
</script>

<template>
    <div class="listings">
        <header class="page__header">
            <div>
                <h1 class="heading-1">Meine Inserate</h1>
                <p class="page__subtitle">Fotos rein, fertiges Inserat raus.</p>
            </div>
            <div class="page__actions">
                <button type="button" class="btn btn--primary" :disabled="creating" @click="create">
                    <span v-if="creating" class="spinner" aria-hidden="true" />
                    <span v-else aria-hidden="true">+</span>
                    Neues Inserat
                </button>
            </div>
        </header>

        <p v-if="error" class="alert alert--danger" role="alert">{{ error }}</p>

        <div v-if="loading" class="loading"><span class="spinner spinner--center" aria-label="Lädt" /></div>

        <div v-else-if="!listings.length" class="card">
            <div class="empty">
                <div class="empty__icon" aria-hidden="true">📷</div>
                <h2 class="empty__title">Noch keine Inserate</h2>
                <p class="empty__text">Mach ein paar Fotos von dem, was weg soll. Den Rest erledigt die App.</p>
                <button type="button" class="btn btn--primary mt1" :disabled="creating" @click="create">Erstes Inserat anlegen</button>
            </div>
        </div>

        <ul v-else class="listing-list">
            <li v-for="listing in listings" :key="listing.id">
                <ListingCard :listing="listing" @delete="remove(listing)" />
            </li>
        </ul>
    </div>
</template>
