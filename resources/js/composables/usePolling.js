import { onBeforeUnmount } from 'vue';

/** Ruft fn alle interval ms auf, solange shouldContinue() true liefert. */
export function createPoller(fn, interval = 2000) {
    let timer = null;
    let active = false;

    const tick = async (shouldContinue) => {
        if (!active) return;
        try {
            await fn();
        } catch {
            // Netzwerkfehler: einfach beim nächsten Tick nochmal
        }
        if (active && shouldContinue()) {
            timer = setTimeout(() => tick(shouldContinue), interval);
        } else {
            active = false;
        }
    };

    return {
        start(shouldContinue) {
            if (active) return;
            active = true;
            timer = setTimeout(() => tick(shouldContinue), interval);
        },
        stop() {
            active = false;
            clearTimeout(timer);
        },
        get running() {
            return active;
        },
    };
}

export function usePolling(fn, interval = 2000) {
    const poller = createPoller(fn, interval);
    onBeforeUnmount(() => poller.stop());
    return poller;
}
