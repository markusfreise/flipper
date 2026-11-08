import { defineStore } from 'pinia';
import { api } from '../api/client';
import { createPoller } from '../composables/usePolling';

export const STEPS = [
    { n: 1, label: 'Fotos' },
    { n: 2, label: 'Erkennung' },
    { n: 3, label: 'Fragen' },
    { n: 4, label: 'Ergebnis' },
];

/** Zustände, in denen die App auf die Person wartet (kein Job läuft für den Status). */
export const RESTING = ['photos', 'identified', 'questions', 'ready', 'failed'];

const PHOTO_PENDING = ['uploaded', 'cutting', 'cut'];

/** Schritt aus dem Listing-Status ableiten (nicht aus der URL). */
export function stepFor(listing) {
    if (!listing) return 1;
    const status = listing.status === 'failed' ? listing.status_before_failure || 'photos' : listing.status;
    switch (status) {
        case 'photos':
        case 'identifying':
            return 1;
        case 'identified':
            return 2;
        case 'researching':
        case 'questions':
            return 3;
        case 'writing':
            // Überarbeitung: der alte Text bleibt sichtbar
            return listing.text ? 4 : 3;
        case 'ready':
            return 4;
        default:
            return 1;
    }
}

export function photosPending(listing) {
    return (listing?.photos ?? []).some((p) => PHOTO_PENDING.includes(p.status));
}

/** Weiter pollen? Solange ein Job läuft oder Bilder noch bearbeitet werden. */
export function shouldPoll(listing) {
    if (!listing) return false;
    if (listing.status === 'failed') return false;
    if (!RESTING.includes(listing.status)) return true;
    if (listing.status === 'photos') return false;
    return photosPending(listing);
}

export const useWizardStore = defineStore('wizard', {
    state: () => ({
        listing: null,
        viewStep: null, // manuell zurückgeblättert
        loading: false,
        error: null,
        poller: null,
    }),
    getters: {
        currentStep: (s) => stepFor(s.listing),
        shownStep(s) {
            return s.viewStep && s.viewStep < this.currentStep ? s.viewStep : this.currentStep;
        },
        busy: (s) => !!s.listing && !RESTING.includes(s.listing.status),
    },
    actions: {
        async load(id) {
            this.stopPolling();
            this.loading = true;
            this.error = null;
            this.viewStep = null;
            try {
                const { data } = await api.get(`/listings/${id}`);
                this.setListing(data.data ?? data);
            } finally {
                this.loading = false;
            }
        },
        setListing(listing) {
            const before = this.currentStep;
            this.listing = listing;
            if (this.currentStep !== before) this.viewStep = null;
            this.ensurePolling();
        },
        async refresh() {
            if (!this.listing) return;
            const { data } = await api.get(`/listings/${this.listing.id}`);
            this.setListing(data.data ?? data);
        },
        ensurePolling() {
            if (!shouldPoll(this.listing)) {
                this.stopPolling();
                return;
            }
            if (!this.poller) this.poller = createPoller(() => this.refresh(), 2000);
            this.poller.start(() => shouldPoll(this.listing));
        },
        stopPolling() {
            this.poller?.stop();
            this.poller = null;
        },
        reset() {
            this.stopPolling();
            this.listing = null;
            this.viewStep = null;
        },
        goTo(step) {
            this.viewStep = step < this.currentStep ? step : null;
        },
        async call(method, url, payload, config) {
            const { data } = await api[method](`/listings/${this.listing.id}${url}`, payload, config);
            this.setListing(data.data ?? data);
            return data;
        },
        uploadPhotos(files, kinds, onProgress) {
            const form = new FormData();
            files.forEach((file, i) => {
                form.append('photos[]', file, file.name);
                form.append('kinds[]', kinds[i] ?? 'overview');
            });
            return this.call('post', '/photos', form, {
                onUploadProgress: (e) => onProgress?.(e.total ? Math.round((e.loaded / e.total) * 100) : null),
            });
        },
        deletePhoto(photoId) {
            return this.call('delete', `/photos/${photoId}`);
        },
        updatePhoto(photoId, patch) {
            return this.call('patch', `/photos/${photoId}`, patch);
        },
        reidentify() {
            return this.call('post', '/identify');
        },
        saveProduct(product) {
            this.viewStep = null;
            return this.call('put', '/product', product);
        },
        saveAnswers(answers) {
            return this.call('put', '/answers', answers);
        },
        revise(hint) {
            return this.call('post', '/text/revise', { hint });
        },
        retry() {
            return this.call('post', '/retry');
        },
    },
});
