import { describe, expect, it, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import CopyField from '../components/CopyField.vue';

describe('CopyField', () => {
    beforeEach(() => {
        Object.assign(navigator, { clipboard: { writeText: vi.fn().mockResolvedValue() } });
        window.isSecureContext = true;
    });

    it('kopiert den Wert und zeigt "Kopiert"', async () => {
        const wrapper = mount(CopyField, { props: { label: 'Titel', value: 'Funko Pop! Luna OVP' } });
        expect(wrapper.text()).toContain('Funko Pop! Luna OVP');

        await wrapper.get('button').trigger('click');
        await flushPromises();

        expect(navigator.clipboard.writeText).toHaveBeenCalledWith('Funko Pop! Luna OVP');
        expect(wrapper.get('.copy-field__feedback').text()).toBe('Kopiert');
    });

    it('fällt auf execCommand zurück, wenn die Clipboard-API fehlt', async () => {
        navigator.clipboard.writeText = vi.fn().mockRejectedValue(new Error('nope'));
        document.execCommand = vi.fn().mockReturnValue(true);
        const wrapper = mount(CopyField, { props: { label: 'Preis', value: '12' } });

        await wrapper.get('button').trigger('click');
        await flushPromises();

        expect(document.execCommand).toHaveBeenCalledWith('copy');
        expect(wrapper.text()).toContain('Kopiert');
    });

    it('deaktiviert den Button ohne Wert', () => {
        const wrapper = mount(CopyField, { props: { label: 'Leer', value: '' } });
        expect(wrapper.get('button').attributes('disabled')).toBeDefined();
    });
});
