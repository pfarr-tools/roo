<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { requestConfirmation } from '../../utils/confirmation'

const props = defineProps({ songs: Array, filters: Object, songStyles: Object })
const query = ref(props.filters?.q ?? '')
const modal = ref(false)
const editorTab = ref('metadata')
const editorVersion = ref(null)
const generatedSheetUrl = ref(null)
const activeImageId = ref(null)
const interaction = ref(null)
const canvas = { width: 420, height: 595.28 }
const form = useForm({ title: '', composer: '', author: '', copyright_notice: '', age_group: '', topics: '', notes: '', version_name: 'Standardfassung', lyrics: '', notation: '', chords: '', rights_status: 'unknown', rights_note: '', text_export_allowed: false, metadata_export_allowed: true, sheet: null })
const editor = useForm({ song: {}, name: '', language: 'de', parts: [], layout_data: { images: [] } })
const imageForm = useForm({ images: [] })
const selectedImage = computed(() => editor.layout_data.images?.find(image => image.id === activeImageId.value) ?? null)
const previewStyles = computed(() => ({
    title: { fontFamily: props.songStyles?.title_font_family, fontSize: `${props.songStyles?.title_font_size ?? 24}px`, fontWeight: props.songStyles?.title_font_weight },
    text: { fontFamily: props.songStyles?.text_font_family, fontSize: `${props.songStyles?.text_font_size ?? 14}px`, fontWeight: props.songStyles?.text_font_weight },
    refrain: { fontFamily: props.songStyles?.refrain_font_family, fontSize: `${props.songStyles?.refrain_font_size ?? 14}px`, fontWeight: props.songStyles?.refrain_font_weight },
}))

function search() { router.get('/lieder', { q: query.value }, { preserveState: true, replace: true }) }
function save() { form.post('/lieder', { forceFormData: true, onSuccess: () => { modal.value = false; form.reset() } }) }
function openEditor(version) {
    editorVersion.value = version
    editorTab.value = 'metadata'
    generatedSheetUrl.value = null
    activeImageId.value = null
    editor.defaults({ song: { title: version.song?.title ?? '', composer: version.song?.composer ?? '', author: version.song?.author ?? '', copyright_notice: version.song?.copyright_notice ?? '', age_group: version.song?.age_group ?? '', topics: version.song?.topics ?? '', notes: version.song?.notes ?? '' }, name: version.name, language: version.language ?? 'de', parts: (version.parts ?? []).map(part => ({ content: part.content, is_refrain: part.is_refrain })), layout_data: version.layout_data ?? { images: [] } })
    editor.reset()
    imageForm.reset()
}
function refreshEditorVersion() {
    const updated = props.songs.flatMap(song => song.versions ?? []).find(version => version.id === editorVersion.value?.id)
    if (updated) editorVersion.value = updated
}
function saveEditor() {
    editor.put(`/lieder/fassungen/${editorVersion.value.id}`, { preserveScroll: true, onSuccess: () => { refreshEditorVersion(); editorVersion.value = null } })
}
function uploadImages() { imageForm.post(`/lieder/fassungen/${editorVersion.value.id}/bilder`, { forceFormData: true, preserveScroll: true, onSuccess: () => { refreshEditorVersion(); imageForm.reset() } }) }
function generateSheet() {
    editor.put(`/lieder/fassungen/${editorVersion.value.id}`, { preserveScroll: true, onSuccess: () => router.post(`/lieder/fassungen/${editorVersion.value.id}/liedblatt/erzeugen`, {}, { preserveScroll: true, onSuccess: () => { generatedSheetUrl.value = `/lieder/fassungen/${editorVersion.value.id}/liedblatt/erzeugt`; refreshEditorVersion() }, onError: errors => window.alert(Object.values(errors)[0] ?? 'Das A5-Liedblatt konnte nicht erzeugt werden.') }) })
}
function addPart() { editor.parts.push({ content: '', is_refrain: false }) }
function addImage(image) { if (!editor.layout_data.images.some(item => item.id === image.id)) editor.layout_data.images.push({ id: image.id, x: 20, y: 20, width: 100, height: 100, rotation: 0, flipX: false, flipY: false }); activeImageId.value = image.id }
function imageStyle(image) { return { left: 0, top: 0, width: `${image.width}px`, height: `${image.height}px`, transform: `rotate(${image.rotation}deg) scale(${image.flipX ? -1 : 1}, ${image.flipY ? -1 : 1})` } }
function beginInteraction(type, event, image, corner = null) {
    event.preventDefault(); activeImageId.value = image.id
    interaction.value = { type, corner, image, startX: event.clientX, startY: event.clientY, x: image.x, y: image.y, width: image.width, height: image.height, rotation: image.rotation }
    window.addEventListener('pointermove', continueInteraction)
    window.addEventListener('pointerup', endInteraction, { once: true })
}
function continueInteraction(event) {
    const state = interaction.value
    if (!state) return
    const dx = event.clientX - state.startX
    const dy = event.clientY - state.startY
    if (state.type === 'move') { state.image.x = Math.max(0, Math.min(canvas.width - state.image.width, state.x + dx)); state.image.y = Math.max(0, Math.min(canvas.height - state.image.height, state.y + dy)); return }
    if (state.type === 'resize') {
        const west = state.corner.includes('w'); const north = state.corner.includes('n')
        const width = Math.max(20, state.width + (west ? -dx : dx)); const height = Math.max(20, state.height + (north ? -dy : dy))
        state.image.width = width; state.image.height = height
        state.image.x = Math.max(0, Math.min(canvas.width - width, west ? state.x + state.width - width : state.x)); state.image.y = Math.max(0, Math.min(canvas.height - height, north ? state.y + state.height - height : state.y)); return
    }
    const centerX = state.x + state.width / 2; const centerY = state.y + state.height / 2
    state.image.rotation = state.rotation + (Math.atan2(event.clientY - centerY - (state.startY - centerY), event.clientX - centerX - (state.startX - centerX)) * 180 / Math.PI)
}
function endInteraction() { interaction.value = null; window.removeEventListener('pointermove', continueInteraction) }
async function removeSong(song) { if (await requestConfirmation({ message: `„${song.title}“ wirklich löschen?` })) router.delete(`/lieder/${song.id}`) }
</script>

<template>
    <AppShell>
        <template #toolbar><button class="btn btn-sm btn-primary" type="button" @click="modal = true"><i class="bi bi-plus-lg me-1"></i>Lied anlegen</button></template>
        <div class="container-full px-3 py-4"><h1 class="h2 mb-4">Liedersammlung</h1><form class="d-flex gap-2 mb-3" @submit.prevent="search"><input v-model="query" class="form-control" placeholder="Lieder durchsuchen"><button class="btn btn-outline-secondary">Suchen</button></form><div class="list-group"><div v-for="song in songs" :key="song.id" class="list-group-item"><div class="d-flex align-items-center gap-2"><strong>{{ song.title }}</strong><span class="small text-muted">{{ [song.author, song.composer].filter(Boolean).join(' · ') }}</span><button v-if="song.can_delete" class="btn btn-sm btn-outline-danger ms-auto" type="button" title="Lied löschen" @click="removeSong(song)"><i class="bi bi-trash"></i><span class="visually-hidden">Lied löschen</span></button></div><div v-for="version in song.versions" :key="version.id" class="small mt-1 d-flex gap-2 align-items-center"><span>{{ version.name }} · Rechte: {{ version.rights_status }}</span><span v-if="version.sheet" class="text-success"><i class="bi bi-file-earmark-pdf"></i> Liedblatt vorhanden</span><button class="btn btn-sm btn-outline-secondary ms-auto" type="button" @click="openEditor(version)"><i class="bi bi-pencil"></i> Bearbeiten</button></div></div><p v-if="!songs.length" class="text-muted">Noch keine Lieder vorhanden.</p></div></div>
        <div v-if="modal" class="roo-modal-backdrop" @click.self="modal = false"><form class="roo-modal card" @submit.prevent="save"><div class="card-body"><div class="d-flex justify-content-between"><h2 class="h5">Lied anlegen</h2><button type="button" class="btn-close" @click="modal = false"></button></div><label class="form-label">Titel</label><input v-model="form.title" class="form-control" required><div class="row g-2"><div class="col-md-6"><label class="form-label mt-2">Text:in</label><input v-model="form.author" class="form-control"></div><div class="col-md-6"><label class="form-label mt-2">Komponist:in</label><input v-model="form.composer" class="form-control"></div></div><label class="form-label mt-2">Fassung</label><input v-model="form.version_name" class="form-control" required><label class="form-label mt-2">Rechtestatus</label><select v-model="form.rights_status" class="form-select"><option value="unknown">Ungeklärt</option><option value="cleared">Geklärt</option><option value="restricted">Eingeschränkt</option><option value="licensed">Lizenziert</option></select><label class="form-label mt-2">Vorhandenes A5-Liedblatt (PDF)</label><input class="form-control" type="file" accept="application/pdf" @change="form.sheet = $event.target.files?.[0] ?? null"><button class="btn btn-primary mt-3" :disabled="form.processing">Speichern</button></div></form></div>
        <div v-if="editorVersion" class="roo-modal-backdrop" @click.self="editorVersion = null"><section class="roo-modal roo-modal-song-editor card" role="dialog" aria-modal="true" aria-label="Lied bearbeiten"><div class="card-body d-flex flex-column"><div class="d-flex justify-content-between align-items-center"><h2 class="h5 mb-0">Lied bearbeiten</h2><button class="btn-close" type="button" @click="editorVersion = null"></button></div><ul class="nav nav-tabs mt-3 mb-3"><li class="nav-item"><button class="nav-link" :class="{ active: editorTab === 'metadata' }" type="button" @click="editorTab = 'metadata'">Metadaten</button></li><li class="nav-item"><button class="nav-link" :class="{ active: editorTab === 'liedblatt' }" type="button" @click="editorTab = 'liedblatt'">Liedblatt</button></li></ul><div v-if="editorTab === 'metadata'" class="row g-3 editor-tab-content"><div class="col-md-8"><label class="form-label">Titel</label><input v-model="editor.song.title" class="form-control" required></div><div class="col-md-4"><label class="form-label">Sprache</label><input v-model="editor.language" class="form-control"></div><div class="col-md-6"><label class="form-label">Text:in</label><input v-model="editor.song.author" class="form-control"></div><div class="col-md-6"><label class="form-label">Komponist:in</label><input v-model="editor.song.composer" class="form-control"></div><div class="col-md-6"><label class="form-label">Fassungsname</label><input v-model="editor.name" class="form-control" required></div><div class="col-md-6"><label class="form-label">Altersgruppe</label><input v-model="editor.song.age_group" class="form-control"></div><div class="col-md-6"><label class="form-label">Themen</label><input v-model="editor.song.topics" class="form-control"></div><div class="col-md-6"><label class="form-label">Rechtehinweis</label><input v-model="editor.song.copyright_notice" class="form-control"></div><div class="col-12"><label class="form-label">Notizen</label><textarea v-model="editor.song.notes" class="form-control" rows="5"></textarea></div></div><div v-else class="songbook-editor-grid row g-3 editor-tab-content overflow-hidden"><div class="col-lg-4 songbook-editor-column song-parts-scroll"><div class="d-flex justify-content-between align-items-center"><h3 class="h6 mb-0">Liedteile</h3><button class="btn btn-sm btn-outline-primary" type="button" @click="addPart">Teil hinzufügen</button></div><div v-for="(part, index) in editor.parts" :key="index" class="border rounded p-2 mt-2"><label class="form-check small"><input v-model="part.is_refrain" class="form-check-input" type="checkbox"> Kehrvers</label><textarea v-model="part.content" class="form-control mt-2" rows="6" placeholder="Text"></textarea></div></div><div class="col-lg-5 songbook-editor-column song-preview-pane"><h3 class="h6">A5-Vorschau</h3><div class="song-canvas border bg-white position-relative" @click="activeImageId = null"><div class="p-3"><h4 class="song-preview-title" :style="previewStyles.title">{{ editor.song.title }}</h4><section v-for="(part, index) in editor.parts" :key="index" class="song-preview-part" :style="part.is_refrain ? previewStyles.refrain : previewStyles.text">{{ part.content }}</section></div><div v-for="image in editor.layout_data.images" :key="image.id" class="song-image-selection" :class="{ selected: activeImageId === image.id }" :style="{ left: `${image.x}px`, top: `${image.y}px`, width: `${image.width}px`, height: `${image.height}px` }" @pointerdown.stop="beginInteraction('move', $event, image)"><img :src="`/lieder/fassungen/${editorVersion.id}/bilder/${image.id}`" :style="imageStyle(image)" alt=""><button v-for="corner in ['nw', 'ne', 'sw', 'se']" :key="corner" class="image-handle resize-handle" :class="corner" type="button" @pointerdown.stop="beginInteraction('resize', $event, image, corner)"></button><button class="image-handle rotate-handle" type="button" @pointerdown.stop="beginInteraction('rotate', $event, image)"></button></div></div><p v-if="generatedSheetUrl" class="mt-2 mb-0"><a class="btn btn-sm btn-success" :href="generatedSheetUrl" target="_blank"><i class="bi bi-download me-1"></i>A5-PDF herunterladen</a></p></div><div class="col-lg-3 songbook-editor-column song-image-column"><h3 class="h6">Bilder</h3><label class="form-label">Bilder hinzufügen</label><input class="form-control" type="file" accept="image/*" multiple @change="imageForm.images = [...$event.target.files]"><button class="btn btn-sm btn-outline-secondary mt-2" type="button" :disabled="imageForm.processing || !imageForm.images.length" @click="uploadImages">Bilder hochladen</button><div v-for="image in editorVersion.images" :key="image.id" class="small mt-2"><button class="btn btn-sm btn-outline-secondary w-100 text-start" type="button" @click="addImage(image)">{{ image.original_name }} auf die Vorschau legen</button></div></div></div><div class="d-flex justify-content-end gap-2 mt-4"><button class="btn btn-outline-secondary" type="button" @click="editorVersion = null">Abbrechen</button><button class="btn btn-outline-primary" type="button" :disabled="editor.processing" @click="generateSheet">A5-PDF erzeugen</button><button class="btn btn-primary" type="button" :disabled="editor.processing" @click="saveEditor">Speichern</button></div></div></section></div>
    </AppShell>
</template>
