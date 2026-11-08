import { describe, expect, it, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import StepQuestions from '../components/wizard/StepQuestions.vue';
import { useWizardStore } from '../stores/wizard';

describe('StepQuestions', () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = useWizardStore();
        store.listing = { id: 'l1', status: 'questions', answers: null, photos: [], pickup_location: 'Bielefeld-West' };
        store.saveAnswers = vi.fn().mockResolvedValue({});
        globalThis.requestAnimationFrame = (fn) => fn();
    });

    it('Chips füllen die Textarea vor', async () => {
        const wrapper = mount(StepQuestions);
        const chip = wrapper.findAll('.question__chips button').find((b) => b.text() === 'Platz schaffen');
        await chip.trigger('click');
        expect(wrapper.get('textarea').element.value).toBe('Platz schaffen.');

        await wrapper.findAll('.question__chips button')[0].trigger('click');
        expect(wrapper.get('textarea').element.value).toBe('Platz schaffen. Geschenk, nie ausgepackt.');
    });

    it('verlangt einen Hintergrundtext', async () => {
        const wrapper = mount(StepQuestions);
        await wrapper.get('form').trigger('submit');
        expect(store.saveAnswers).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('Erzähl kurz');

        await wrapper.get('textarea').setValue('Für meine Tochter gekauft.');
        await wrapper.findAll('input[type=checkbox]').find((i) => i.element.value === 'Tierfrei').setValue(true);
        await wrapper.findAll('input[type=radio]').find((i) => i.element.value === 'both').setValue(true);
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(store.saveAnswers).toHaveBeenCalledWith(expect.objectContaining({
            background: 'Für meine Tochter gekauft.',
            condition: ['Tierfrei'],
            platform: 'both',
            pickup: true,
        }));
    });

    it('zeigt den Abholort aus der API', () => {
        expect(mount(StepQuestions).text()).toContain('Abholung möglich (Bielefeld-West)');
    });
});
