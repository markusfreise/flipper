<script setup>
import { computed, ref } from 'vue';
import PhotoPicker from './PhotoPicker.vue';
import PhotoTips from './PhotoTips.vue';

const props = defineProps({
    check: { type: Object, default: null },
    photoCount: { type: Number, default: 0 },
    title: { type: String, default: 'Foto-Check' },
});

const picking = ref(false);

const QUALITY = {
    low_res: 'Fotos sind nicht in voller Auflösung.',
    busy_background: 'Der Untergrund ist unruhig.',
    see_through: 'Durch durchsichtige Teile sieht man die Unterlage.',
    blurry: 'Mindestens ein Foto ist unscharf.',
    poor_light: 'Das Licht ist schwach oder ungleichmäßig.',
};

const missing = computed(() => props.check?.missing ?? []);
const issues = computed(() => Object.entries(props.check?.quality ?? {}).filter(([k, v]) => v && QUALITY[k]).map(([k]) => QUALITY[k]));
const tips = computed(() => props.check?.tips ?? []);
const allGood = computed(() => props.check && !missing.value.length && !issues.value.length);
</script>

<template>
    <div class="photo-check">
        <h3 class="heading-3">{{ title }}</h3>

        <p v-if="!check" class="muted small mt05">Fotos werden noch geprüft …</p>
        <p v-else-if="allGood" class="alert alert--success mt05">Die Fotos zeigen alles, was Käufer sehen wollen.</p>

        <template v-else>
            <div v-if="missing.length" class="mt05">
                <p class="small"><strong>Es fehlen noch:</strong></p>
                <ul class="photo-check__list">
                    <li v-for="m in missing" :key="m.what">
                        <strong>{{ m.what }}</strong>
                        <span class="muted"> · {{ m.why }}</span>
                    </li>
                </ul>
            </div>
            <div v-if="issues.length" class="mt05">
                <p class="small"><strong>Aufgefallen:</strong></p>
                <ul class="photo-check__list">
                    <li v-for="i in issues" :key="i">{{ i }}</li>
                </ul>
            </div>
            <div v-if="tips.length" class="mt05">
                <p class="small"><strong>Tipps:</strong></p>
                <ul class="photo-check__list">
                    <li v-for="t in tips" :key="t">{{ t }}</li>
                </ul>
            </div>
            <p class="small muted mt05">Du kannst trotzdem weitermachen.</p>
        </template>

        <div class="mt1">
            <PhotoPicker v-if="picking" compact :max="10 - photoCount" submit-label="Fotos hochladen" @uploaded="picking = false" @cancel="picking = false" />
            <button v-else-if="photoCount < 10" type="button" class="btn btn--secondary btn--sm" @click="picking = true">Fotos nachladen</button>
        </div>
        <PhotoTips class="mt1" />
    </div>
</template>
