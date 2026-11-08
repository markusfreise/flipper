/** Kopiert Text in die Zwischenablage, mit Fallback für ältere Browser / HTTP. */
export async function copyText(text) {
    try {
        if (navigator.clipboard?.writeText && window.isSecureContext !== false) {
            await navigator.clipboard.writeText(text);
            return true;
        }
    } catch {
        // weiter mit Fallback
    }
    const area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();
    let ok = false;
    try {
        ok = document.execCommand('copy');
    } catch {
        ok = false;
    }
    document.body.removeChild(area);
    return ok;
}

export function useClipboard() {
    return { copy: copyText };
}
