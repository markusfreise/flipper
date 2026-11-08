import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { stepFor, shouldPoll, useWizardStore } from '../stores/wizard';
import { api } from '../api/client';

const photo = (status = 'composed') => ({ id: 'p1', status, kind: 'overview' });

describe('stepFor', () => {
    it.each([
        ['photos', 1], ['identifying', 1], ['identified', 2], ['researching', 3],
        ['questions', 3], ['writing', 3], ['ready', 4],
    ])('%s → Schritt %i', (status, step) => {
        expect(stepFor({ status, photos: [] })).toBe(step);
    });

    it('Überarbeitung bleibt in Schritt 4', () => {
        expect(stepFor({ status: 'writing', text: { title: 'x' } })).toBe(4);
    });

    it('failed zeigt den Schritt vor dem Fehler', () => {
        expect(stepFor({ status: 'failed', status_before_failure: 'researching' })).toBe(3);
        expect(stepFor({ status: 'failed', status_before_failure: null })).toBe(1);
    });
});

describe('shouldPoll', () => {
    it('pollt, solange ein Job läuft', () => {
        for (const status of ['identifying', 'researching', 'writing']) {
            expect(shouldPoll({ status, photos: [photo()] })).toBe(true);
        }
    });

    it('stoppt in Endzuständen', () => {
        for (const status of ['identified', 'questions', 'ready', 'failed', 'photos']) {
            expect(shouldPoll({ status, photos: [photo()] })).toBe(false);
        }
    });

    it('pollt weiter, solange Bilder noch gebaut werden', () => {
        expect(shouldPoll({ status: 'ready', photos: [photo('cutting')] })).toBe(true);
        expect(shouldPoll({ status: 'failed', photos: [photo('cutting')] })).toBe(false);
    });
});

describe('wizard store polling', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.useFakeTimers();
    });
    afterEach(() => vi.useRealTimers());

    it('pollt bis ready und hört dann auf', async () => {
        const responses = [
            { id: 'l1', status: 'writing', photos: [photo()] },
            { id: 'l1', status: 'ready', text: { title: 't' }, photos: [photo()] },
        ];
        const get = vi.spyOn(api, 'get').mockImplementation(async () => ({ data: { data: responses.shift() ?? { id: 'l1', status: 'ready', photos: [photo()] } } }));

        const store = useWizardStore();
        await store.load('l1');
        expect(store.currentStep).toBe(3);
        expect(store.poller?.running).toBe(true);

        await vi.advanceTimersByTimeAsync(2000);
        expect(store.listing.status).toBe('ready');
        expect(store.currentStep).toBe(4);

        await vi.advanceTimersByTimeAsync(10000);
        expect(get).toHaveBeenCalledTimes(2);
        expect(store.poller).toBeNull();
    });
});
