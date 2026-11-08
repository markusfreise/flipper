<script setup>
defineProps({ open: { type: Boolean, default: false } });
</script>

<template>
    <details class="photo-tips" :open="open">
        <summary class="photo-tips__summary">So fotografierst du am besten</summary>
        <ul class="photo-tips__list">
            <li><strong>Untergrund:</strong> Weißer oder hellgrauer Karton, der hinter und unter dem Gegenstand durchgeht (hinten hochbiegen). Keine Schneidematte, kein Holzmuster, keine Kacheln.</li>
            <li><strong>Sichtfenster oder Glas:</strong> Dahinter muss dieselbe helle Fläche liegen, sonst sieht man die Unterlage durch. Gegenstand 20–30 cm von der Rückwand weg.</li>
            <li><strong>Licht:</strong> Tageslicht seitlich vom Fenster, kein Blitz, keine Deckenlampe direkt von oben.</li>
            <li><strong>Kamera:</strong> Hauptkamera, kein Weitwinkel. Auf Höhe des Gegenstands, nicht von oben. Alles im Bild, mit Luft drumherum.</li>
            <li><strong>Reihenfolge:</strong> Front gerade, Dreiviertel-Ansicht, Rückseite oder Unterseite, je eine Nahaufnahme von jedem Mangel, ggf. Zubehör. 4–6 Fotos reichen.</li>
            <li><strong>Mängel absichtlich mitfotografieren</strong> und als „Nahaufnahme“ markieren. Ein ehrliches Foto von der Delle spart drei Nachrichten mit dem Käufer.</li>
        </ul>
    </details>
</template>
