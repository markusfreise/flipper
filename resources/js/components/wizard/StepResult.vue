<script setup>
import { computed, ref } from 'vue';
import { useWizardStore } from '../../stores/wizard';
import { errorMessage } from '../../api/client';
import CopyField from '../CopyField.vue';
import PhotoCheckList from '../PhotoCheckList.vue';

const wizard = useWizardStore();
const listing = computed(() => wizard.listing);
const text = computed(() => listing.value.text ?? {});
const pricing = computed(() => listing.value.pricing ?? {});
const platform = computed(() => listing.value.answers?.platform ?? 'kleinanzeigen');
const showKa = computed(() => platform.value !== 'ebay');
const showEbay = computed(() => platform.value !== 'kleinanzeigen');
const revising = computed(() => listing.value.status === 'writing');

const hint = ref('');
const busy = ref(false);
const error = ref('');

const euro = (v, decimals) => (v === null || v === undefined ? '' : Number(v).toLocaleString('de-DE', { minimumFractionDigits: decimals, maximumFractionDigits: 2 }));

const kaPrice = computed(() => euro(pricing.value.kleinanzeigen_eur, 0));
const ebayPrice = computed(() => euro(pricing.value.ebay_eur, 2));

const shippingText = computed(() => {
    const s = pricing.value.shipping ?? {};
    const parts = [];
    if (showKa.value && s.kleinanzeigen_text) parts.push(`Kleinanzeigen: in der App die angebotenen Versandarten aktivieren (${s.kleinanzeigen_text}).`);
    if (showEbay.value && s.ebay) {
        const price = s.ebay.price_eur !== null && s.ebay.price_eur !== undefined ? ` für ${euro(s.ebay.price_eur, 2)} €` : '';
        parts.push(`eBay: ${s.ebay.service}${price}.${s.ebay.note ? ' ' + s.ebay.note : ''}`);
    }
    if (listing.value.answers?.pickup) parts.push(`Abholung in ${listing.value.pickup_location}.`);
    return parts.join('\n');
});

const ebayAttributes = computed(() => Object.entries(listing.value.ebay_attributes ?? {}).map(([k, v]) => `${k}: ${v}`).join('\n'));

const sources = computed(() => {
    const sold = listing.value.research?.sold_prices ?? [];
    return sold.filter((s) => s.source_url).map((s) => ({
        url: s.source_url,
        label: `${euro(s.price_eur, 2)} €${s.date ? ', ' + s.date : ''}${s.note ? ' (' + s.note + ')' : ''}`,
        host: (() => { try { return new URL(s.source_url).hostname.replace(/^www\./, ''); } catch { return s.source_url; } })(),
    }));
});

const photos = computed(() => [...listing.value.photos].sort((a, b) => (b.is_cover - a.is_cover) || a.position - b.position));
const missing = computed(() => listing.value.photo_check?.missing?.length > 0);

async function revise(message) {
    if (!message?.trim()) return;
    busy.value = true;
    error.value = '';
    try {
        await wizard.revise(message.trim());
        hint.value = '';
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}

function withoutInvented() {
    const quoted = (text.value.invented ?? []).map((s) => `„${s}“`).join(', ');
    revise(`Streiche diese ausgeschmückten Formulierungen ersatzlos und erfinde nichts Neues dazu: ${quoted}`);
}

async function setCover(photo) {
    try {
        await wizard.updatePhoto(photo.id, { is_cover: true });
    } catch (e) {
        error.value = errorMessage(e);
    }
}
</script>

<template>
    <div class="result">
        <section class="card wizard__card">
            <div class="card__header">
                <div>
                    <h2 class="card__title">4 · Bilder</h2>
                    <p class="muted small">Das erste Bild wird das Titelbild. Reihenfolge wie im ZIP.</p>
                </div>
                <a v-if="listing.zip_url" class="btn btn--primary btn--sm" :href="listing.zip_url" download>Alle als ZIP</a>
            </div>
            <div class="card__body">
                <ul class="result-images">
                    <li v-for="(photo, i) in photos" :key="photo.id" class="result-images__item">
                        <div class="result-images__frame">
                            <img v-if="photo.final_url" :src="photo.final_url" :alt="`Bild ${i + 1}`" loading="lazy" class="result-images__img">
                            <span v-else class="result-images__pending"><span class="spinner" aria-hidden="true" /> wird gebaut</span>
                            <span v-if="photo.is_cover" class="result-images__cover badge badge--brand">Titelbild</span>
                        </div>
                        <p v-if="photo.status === 'cutout_failed'" class="small muted">Freistellen nicht möglich, Original verwenden.</p>
                        <p v-else-if="photo.kind === 'detail'" class="small muted">Nahaufnahme, unbearbeitet.</p>
                        <div class="result-images__actions">
                            <a v-if="photo.final_url" class="btn btn--ghost btn--sm" :href="`${photo.final_url}&download=1`" download>Laden</a>
                            <button v-if="!photo.is_cover && photo.final_url" type="button" class="btn btn--ghost btn--sm" @click="setCover(photo)">Als Titelbild</button>
                        </div>
                    </li>
                </ul>
                <div v-if="missing" class="alert alert--info mt1">
                    <PhotoCheckList :check="listing.photo_check" :photo-count="listing.photos.length" title="Noch bessere Fotos?" />
                </div>
            </div>
        </section>

        <section class="card wizard__card" :aria-busy="revising">
            <div class="card__header">
                <h2 class="card__title">Texte zum Kopieren</h2>
                <span v-if="revising" class="fxc g05 small"><span class="spinner" aria-hidden="true" /> Überarbeite Text …</span>
            </div>
            <div class="card__body copy-list" :class="{ 'is-loading': revising }">
                <CopyField label="Titel" :value="text.title" />
                <CopyField label="Beschreibung" :value="text.body" multiline>
                    <div v-if="text.invented?.length" class="alert alert--warning mt05">
                        <p><strong>Diese Formulierungen sind ausgeschmückt, nicht von dir:</strong></p>
                        <ul class="invented-list">
                            <li v-for="s in text.invented" :key="s">„{{ s }}“</li>
                        </ul>
                        <button type="button" class="btn btn--secondary btn--sm mt05" :disabled="busy || revising || !listing.revisions_left" @click="withoutInvented">Ohne diese Sätze neu schreiben</button>
                    </div>
                </CopyField>

                <CopyField v-if="showKa" label="Preis Kleinanzeigen (VB)" :value="kaPrice">
                    <p v-if="kaPrice" class="small muted">{{ kaPrice }} € VB</p>
                </CopyField>
                <CopyField v-if="showEbay" label="Preis eBay Sofortkauf" :value="ebayPrice">
                    <p v-if="ebayPrice" class="small muted">{{ ebayPrice }} €</p>
                </CopyField>
                <div class="price-meta small muted">
                    <p>{{ pricing.rationale }}</p>
                    <ul v-if="sources.length" class="price-meta__sources">
                        <li v-for="s in sources" :key="s.url"><a class="link" :href="s.url" target="_blank" rel="noopener noreferrer">{{ s.host }}</a> · {{ s.label }}</li>
                    </ul>
                </div>

                <CopyField label="Versandvorschlag" :value="shippingText" multiline />
                <CopyField v-if="showEbay && ebayAttributes" label="eBay-Artikelmerkmale" :value="ebayAttributes" multiline />

                <form class="form revise" @submit.prevent="revise(hint)">
                    <label class="form__label" for="hint">Text überarbeiten</label>
                    <div class="revise__row">
                        <input id="hint" v-model="hint" class="form__input" placeholder="z. B. kürzer, ohne den Satz über das Regal" :disabled="!listing.revisions_left || revising">
                        <button type="submit" class="btn btn--secondary" :disabled="busy || revising || !hint.trim() || !listing.revisions_left">Überarbeiten</button>
                    </div>
                    <span class="form__hint">{{ listing.revisions_left ? `noch ${listing.revisions_left} Überarbeitung${listing.revisions_left === 1 ? '' : 'en'}` : 'Keine Überarbeitungen mehr übrig.' }}</span>
                    <p v-if="error" class="form__error">{{ error }}</p>
                </form>
            </div>
        </section>

        <section class="card wizard__card finish">
            <div class="card__body">
                <h2 class="heading-3">Und jetzt</h2>
                <ol class="finish__steps">
                    <li v-if="showKa">Kleinanzeigen-App öffnen, Kategorie wählen.</li>
                    <li v-else>eBay-App öffnen, „Verkaufen“ wählen, Produkt suchen.</li>
                    <li>Bilder hochladen (Titelbild zuerst).</li>
                    <li>Titel, Beschreibung und Preis einfügen<span v-if="showEbay">, bei eBay auch die Artikelmerkmale</span>.</li>
                    <li v-if="showKa">Versand: in der App die angebotenen Versandarten aktivieren. Kostenpflichtige Hervorhebungen brauchst du nicht.</li>
                </ol>
                <a v-if="showKa" class="btn btn--primary mt1" href="https://www.kleinanzeigen.de/p-anzeige-aufgeben.html" target="_blank" rel="noopener noreferrer">Kleinanzeigen öffnen</a>
                <a v-else class="btn btn--primary mt1" href="https://www.ebay.de/sl/sell" target="_blank" rel="noopener noreferrer">eBay öffnen</a>
            </div>
        </section>
    </div>
</template>
