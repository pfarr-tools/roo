<script setup>
import { computed, onMounted, ref, watch } from 'vue'

const props = defineProps({
    open: Boolean,
    defaultPrompt: { type: String, default: '' },
    defaultRemoveWhite: { type: Boolean, default: false },
    outputLocked: { type: Boolean, default: false },
    userName: { type: String, default: '' },
    models: { type: Array, default: () => [] },
})
const emit = defineEmits(['close', 'selected', 'library', 'toast'])
const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
const prompt = ref(''), model = ref('flux2-flex'), count = ref(1), removeWhite = ref(false), ratio = ref('1:1'), size = ref('1024'), credits = ref(null), results = ref([]), generating = ref(false), error = ref(''), previewResult = ref(null)
const ratios = [{ value: '1:1', label: 'Quadrat (1:1)' }, { value: '4:3', label: 'Querformat (4:3)' }, { value: '3:4', label: 'Hochformat (3:4)' }, { value: '16:9', label: 'Breit (16:9)' }, { value: '9:16', label: 'Hoch (9:16)' }]
const sizes = [{ value: 768, label: '768 px' }, { value: 1024, label: '1024 px' }, { value: 1280, label: '1280 px' }]
const selectedModel = computed(() => props.models.find(item => item.key === model.value) ?? props.models[0] ?? { key: 'flux2-flex', label: 'FLUX.2 [flex]', prompt_upsampling: true })
const modelLabel = computed(() => selectedModel.value.label ?? 'FLUX')
const dimensions = computed(() => { const [width, height] = ratio.value.split(':').map(Number); const base = Number(size.value); return width >= height ? { width: base, height: Math.round(base * height / width) } : { width: Math.round(base * width / height), height: base } })

watch(() => props.open, open => { if (open) { prompt.value = props.defaultPrompt; removeWhite.value = props.defaultRemoveWhite; results.value = []; error.value = ''; loadCredits() } })
onMounted(() => { if (props.models.length) model.value = props.models.find(item => item.key === 'flux2-flex')?.key ?? props.models[0].key })

function headers(json = false) { return { accept: 'application/json', ...(json ? { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf } : {}) } }
async function loadCredits() { try { const response = await fetch('/flux/credits', { headers: headers() }); const payload = await response.json(); credits.value = number(payload.credits) } catch { credits.value = null } }
function number(value) { const parsed = Number(value); return Number.isFinite(parsed) ? parsed : null }
function formatCredits(value) { return value === null ? '–' : new Intl.NumberFormat('de-DE', { maximumFractionDigits: 2 }).format(value) }
function delay(ms) { return new Promise(resolve => window.setTimeout(resolve, ms)) }
async function generate() {
    if (!prompt.value.trim() || generating.value) return
    generating.value = true; error.value = ''; results.value = Array.from({ length: Math.min(Math.max(Number(count.value), 1), 8) }, (_, index) => ({ label: 'Variante ' + (index + 1), status: 'Wird gestartet', src: '', cost: null }))
    const before = credits.value
    try {
        await Promise.all(results.value.map(async result => {
            const response = await fetch('/flux/generate', { method: 'POST', headers: headers(true), body: JSON.stringify({ model: selectedModel.value.key, prompt: prompt.value.trim(), ...dimensions.value, prompt_upsampling: Boolean(selectedModel.value.prompt_upsampling) }) })
            const submission = await response.json(); if (!response.ok) throw new Error(submission.message ?? 'Die FLUX-Erzeugung konnte nicht gestartet werden.')
            result.status = 'In Warteschlange'; result.cost = number(submission.cost); await poll(result, submission.polling_url)
        }))
        await loadCredits(); const cost = before !== null && credits.value !== null ? Math.max(before - credits.value, 0) : results.value.reduce((sum, result) => sum + (result.cost ?? 0), 0)
        if (cost > 0) emit('toast', 'FLUX-Bilderzeugung: ' + formatCredits(cost) + ' Credits verbraucht.')
    } catch (caught) { error.value = caught.message ?? 'Die FLUX-Erzeugung ist fehlgeschlagen.' } finally { generating.value = false }
}
async function poll(result, pollingUrl) {
    if (!pollingUrl) throw new Error('FLUX hat keine Abrufadresse geliefert.')
    for (let attempt = 0; attempt < 180; attempt++) {
        await delay(1000); const response = await fetch('/flux/poll?url=' + encodeURIComponent(pollingUrl), { headers: headers() }); const payload = await response.json()
        if (!response.ok) throw new Error(payload.message ?? 'Das FLUX-Ergebnis konnte nicht abgerufen werden.')
        result.status = payload.status ?? 'Unbekannt'; if (payload.status === 'Ready' && payload.image_data) { result.src = payload.image_data; return } if (['Error', 'Failed', 'Content Moderated', 'Request Moderated'].includes(payload.status)) throw new Error('FLUX konnte dieses Bild nicht erzeugen.')
    }
    throw new Error('FLUX hat zu lange für das Ergebnis benötigt.')
}
async function choose(result) {
    if (!result.src || generating.value) return
    try { const blob = await toPng(result.src, removeWhite.value); previewResult.value = null; emit('selected', { blob, filename: 'flux-' + Date.now() + '.png', credits: modelLabel.value + ' / Black Forest Labs / ' + props.userName }); emit('close') } catch { error.value = 'Das FLUX-Bild konnte nicht verarbeitet werden.' }
}
function toPng(src, eraseWhite) { return new Promise((resolve, reject) => { const image = new Image(); image.onload = () => { const canvas = document.createElement('canvas'); canvas.width = image.naturalWidth; canvas.height = image.naturalHeight; const context = canvas.getContext('2d'); context.drawImage(image, 0, 0); if (eraseWhite) { const pixels = context.getImageData(0, 0, canvas.width, canvas.height); for (let index = 0; index < pixels.data.length; index += 4) { if (pixels.data[index] > 245 && pixels.data[index + 1] > 245 && pixels.data[index + 2] > 245) pixels.data[index + 3] = 0 } context.putImageData(pixels, 0, 0) } canvas.toBlob(blob => blob ? resolve(blob) : reject(new Error('PNG konnte nicht erstellt werden.')), 'image/png') }; image.onerror = reject; image.src = src }) }
async function addToLibrary(result) {
    if (!result.src || generating.value) return
    try { const blob = await toPng(result.src, removeWhite.value); previewResult.value = null; emit('library', { blob, filename: 'flux-' + Date.now() + '.png', description: prompt.value.trim(), copyrights: modelLabel.value + ' / Black Forest Labs / ' + props.userName }) } catch { error.value = 'Das FLUX-Bild konnte nicht verarbeitet werden.' }
}
</script>

<template>
    <div v-if="open" class="roo-modal-backdrop" @click.self="emit('close')">
        <section class="roo-modal card flux-generator-modal" role="dialog" aria-modal="true" aria-labelledby="flux-generator-title">
            <div class="card-body d-flex flex-column gap-3">
                <div class="d-flex justify-content-between align-items-center"><h2 id="flux-generator-title" class="h5 mb-0">Bild erzeugen</h2><button class="btn-close" type="button" aria-label="Schließen" @click="emit('close')"></button></div>
                <div class="small text-muted">Verfügbares FLUX-Guthaben: <strong>{{ formatCredits(credits) }}</strong></div>
                <div><label class="form-label" for="flux-model">Modell</label><select id="flux-model" v-model="model" class="form-select"><option v-for="item in models" :key="item.key" :value="item.key">{{ item.label }}</option></select></div>
                <div><label class="form-label" for="flux-prompt">Prompt</label><textarea id="flux-prompt" v-model="prompt" class="form-control" rows="6"></textarea></div>
                <div class="row g-2"><div class="col-sm-4"><label class="form-label" for="flux-count">Anzahl Bilder</label><select id="flux-count" v-model="count" class="form-select"><option v-for="value in 8" :key="value" :value="value">{{ value }}</option></select></div><div class="col-sm-4"><label class="form-label" for="flux-ratio">Seitenverhältnis</label><select id="flux-ratio" v-model="ratio" class="form-select" :disabled="outputLocked"><option v-for="item in ratios" :key="item.value" :value="item.value">{{ item.label }}</option></select></div><div class="col-sm-4"><label class="form-label" for="flux-size">Größe</label><select id="flux-size" v-model="size" class="form-select" :disabled="outputLocked"><option v-for="item in sizes" :key="item.value" :value="item.value">{{ item.label }}</option></select></div></div>
                <label class="form-check"><input v-model="removeWhite" class="form-check-input" type="checkbox"><span class="form-check-label">Weißen Hintergrund entfernen</span></label>
                <div v-if="error" class="alert alert-danger mb-0">{{ error }}</div>
                <div v-if="results.length" class="row row-cols-2 row-cols-md-4 g-2"><div v-for="result in results" :key="result.label" class="col"><button class="btn btn-outline-secondary w-100 p-1" type="button" :disabled="!result.src || generating" @click="previewResult = result"><img v-if="result.src" :src="result.src" class="img-fluid flux-result-image" alt=""><span v-else class="d-block py-5 small">{{ result.status }}</span><span class="small d-block">{{ result.label }}</span></button></div></div>
                <div class="d-flex justify-content-end gap-2"><button class="btn btn-outline-secondary" type="button" :disabled="generating" @click="emit('close')">Abbrechen</button><button class="btn btn-primary" type="button" :disabled="generating || !prompt.trim()" @click="generate">{{ generating ? 'Bilder werden erzeugt …' : 'Bilder erzeugen' }}</button></div>
            </div>
        </section>
    </div>
    <div v-if="previewResult" class="roo-modal-backdrop flux-preview-backdrop" @click.self="previewResult = null">
        <section class="roo-modal card flux-preview-modal" role="dialog" aria-modal="true" aria-labelledby="flux-preview-title">
            <div class="card-body d-flex flex-column gap-3"><div class="d-flex justify-content-between align-items-center"><h2 id="flux-preview-title" class="h5 mb-0">{{ previewResult.label }}</h2><button class="btn-close" type="button" aria-label="Schließen" @click="previewResult = null"></button></div><img :src="previewResult.src" class="flux-preview-image" alt="FLUX-Vorschau"><div class="d-flex justify-content-end gap-2"><button class="btn btn-outline-secondary" type="button" @click="addToLibrary(previewResult)">Zur Bibliothek hinzufügen</button><button class="btn btn-outline-secondary" type="button" @click="previewResult = null">Schließen</button><button class="btn btn-primary" type="button" @click="choose(previewResult)">Dieses Bild übernehmen</button></div></div>
        </section>
    </div>
</template>
