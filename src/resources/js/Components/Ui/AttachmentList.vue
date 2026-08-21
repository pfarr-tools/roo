<script setup>
import de from '../../i18n/de'
import { computed, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { requestConfirmation } from '../../utils/confirmation'

const props = defineProps({ resources: { type: Array, default: () => [] }, resourceLinks: { type: Array, default: () => [] }, materialItems: { type: Array, default: () => [] }, songs: { type: Array, default: () => [] }, songbooks: { type: Array, default: () => [] }, assessmentTasks: { type: Array, default: () => [] }, libraryResources: { type: Array, default: () => [] }, libraryResourceLinks: { type: Array, default: () => [] }, libraryMaterialItems: { type: Array, default: () => [] }, libraryAttachUrl: { type: String, default: '' }, libraryTargetType: { type: String, default: '' }, libraryTargetId: { type: [String, Number], default: null }, materialText: { type: String, default: '' }, downloadBaseUrl: { type: String, required: true }, uploadUrl: { type: String, default: '' }, uploadLessonId: { type: [String, Number], default: null }, manage: { type: Boolean, default: false } })
const emit = defineEmits(['update', 'delete', 'uploaded', 'select-resource', 'update:resource-links', 'update:material-items', 'update:songs', 'delete:resource-link', 'delete:material-item', 'error'])
const previewResource = ref(null)
const activeAdd = ref(null)
const librarySearch = ref('')
const libraryType = ref('all')
const libraryLoading = ref(false)
const libraryItems = ref([])
let librarySearchTimer = null
const editingItem = ref(null)
const editingType = ref(null)
const editingForm = ref({ description: '', copyrights: '', title: '', url: '', name: '', material_number: '', storage_location: '' })
const resourceUpload = useForm({ resource: null, description: '', copyrights: '', lesson_id: props.uploadLessonId })
const resourceLinkForm = ref({ title: '', url: '' })
const materialItemForm = ref({ name: '', material_number: '', storage_location: '', description: '' })
const materialTextItems = computed(() => String(props.materialText ?? '').split('\n').map(item => item.trim()).filter(Boolean))
const hasItems = computed(() => props.resources.length || props.resourceLinks.length || props.materialItems.length || props.songs.length || props.songbooks.length || props.assessmentTasks.length || materialTextItems.value.length)
function searchLibrary() {
    window.clearTimeout(librarySearchTimer)
    librarySearchTimer = window.setTimeout(async () => {
        libraryLoading.value = true
        try {
            const response = await fetch(`/ressourcen/bibliothek?q=${encodeURIComponent(librarySearch.value)}&type=${encodeURIComponent(libraryType.value)}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            if (response.ok) libraryItems.value = await response.json()
        } finally { libraryLoading.value = false }
    }, 250)
}
watch([librarySearch, libraryType], searchLibrary)
watch(activeAdd, value => { if (value === 'library') searchLibrary() })

const isWscDoc = resource => resource.original_name?.toLowerCase().endsWith('.wscdoc')
const iconFor = resource => isWscDoc(resource) ? 'bi-file-earmark-richtext' : resource.mime_type === 'application/pdf' ? 'bi-file-earmark-pdf' : resource.mime_type?.startsWith('image/') ? 'bi-file-earmark-image' : resource.mime_type?.includes('word') ? 'bi-file-earmark-word' : resource.mime_type?.includes('presentation') ? 'bi-file-earmark-slides' : 'bi-file-earmark'
const sizeFor = bytes => !bytes ? '0 KB' : bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / (1024 * 1024)).toFixed(1)} MB`
const isPreviewable = resource => isWscDoc(resource) || resource.mime_type?.startsWith('image/') || resource.mime_type?.startsWith('video/') || resource.mime_type?.startsWith('audio/')
const previewKind = resource => isWscDoc(resource) || resource.mime_type?.startsWith('image/') ? 'image' : resource.mime_type?.startsWith('video/') ? 'video' : 'audio'
const fileTypeFor = resource => isWscDoc(resource) ? de.worksheetCrafter : (resource.mime_type || 'Datei')
const pageLabel = resource => resource.page_count === 1 ? de.page : de.pages
const songTitle = song => song.song?.title || song.title || song.name
const songCredits = song => {
    const author = song.song?.author?.trim()
    const composer = song.song?.composer?.trim()
    if (author && composer && author.toLowerCase() === composer.toLowerCase()) return `Text & Musik: ${author}`
    return [author && `Text: ${author}`, composer && `Musik: ${composer}`].filter(Boolean).join(' / ') || 'Keine Credits'
}
const assessmentTaskDescription = task => {
    const competency = task.education_plan_competency || task.educationPlanCompetency
    const rawIdentifier = competency?.external_identifier
    const identifier = rawIdentifier ? rawIdentifier.replace(/^(\d+\.\d+\.\d+)\.(\d+)$/, '$1 ($2)') : competency?.number
    return [identifier || (task.teaching_unit_competency_id ? `Kompetenz ${task.teaching_unit_competency_id}` : ''), (task.levels ?? []).map(level => level.level || level).filter(Boolean).join(', ')].filter(Boolean).join(' · ')
}

function uploadFile() {
    if (!resourceUpload.resource || !props.uploadUrl) return
    resourceUpload.submit('post', props.uploadUrl, { forceFormData: true, preserveScroll: true, onSuccess: page => { resourceUpload.reset('resource', 'description'); activeAdd.value = null; emit('uploaded', page) }, onError: errors => emit('error', Object.values(errors)[0] || de.uploadAttachmentError) })
}
function addResourceLink() {
    if (!resourceLinkForm.value.title.trim() || !resourceLinkForm.value.url.trim()) return
    if (props.libraryAttachUrl && props.libraryTargetType && props.libraryTargetId) {
        router.post(`${props.libraryAttachUrl}/resource/erstellen`, { ...resourceLinkForm.value, target_type: props.libraryTargetType, target_id: props.libraryTargetId }, { preserveScroll: true, onSuccess: page => { resourceLinkForm.value = { title: '', url: '' }; activeAdd.value = null; emit('uploaded', page) }, onError: errors => emit('error', Object.values(errors)[0] || 'Die Ressource konnte nicht gespeichert werden.') })
        return
    }
    emit('update:resource-links', [...props.resourceLinks, { local_key: `new-link-${Date.now()}`, ...resourceLinkForm.value }])
    resourceLinkForm.value = { title: '', url: '' }
    activeAdd.value = null
}
function addMaterialItem() {
    if (!materialItemForm.value.name.trim()) return
    if (props.libraryAttachUrl && props.libraryTargetType && props.libraryTargetId) {
        router.post(`${props.libraryAttachUrl}/material/erstellen`, { ...materialItemForm.value, target_type: props.libraryTargetType, target_id: props.libraryTargetId }, { preserveScroll: true, onSuccess: page => { materialItemForm.value = { name: '', material_number: '', storage_location: '', description: '' }; activeAdd.value = null; emit('uploaded', page) }, onError: errors => emit('error', Object.values(errors)[0] || 'Das Material konnte nicht gespeichert werden.') })
        return
    }
    emit('update:material-items', [...props.materialItems, { local_key: `new-material-${Date.now()}`, ...materialItemForm.value }])
    materialItemForm.value = { name: '', material_number: '', storage_location: '', description: '' }
    activeAdd.value = null
}
function selectLibraryItem(item) {
    if ((item.kind === 'resource' || item.kind === 'material' || item.kind === 'song' || item.kind === 'songbook' || item.kind === 'assessment-task') && props.libraryAttachUrl && props.libraryTargetType && props.libraryTargetId) {
        router.post(`${props.libraryAttachUrl}/${item.kind}/${item.id}/zuordnen`, { target_type: props.libraryTargetType, target_id: props.libraryTargetId }, { preserveScroll: true, onSuccess: page => emit('uploaded', page), onError: errors => emit('error', Object.values(errors)[0] || 'Die Zuordnung konnte nicht gespeichert werden.') })
        activeAdd.value = null
        return
    }
    if (item.kind === 'resource') emit('update:resource-links', [...props.resourceLinks, { ...item, local_key: `library-link-${item.id}` }])
    if (item.kind === 'material') emit('update:material-items', [...props.materialItems, { ...item, local_key: `library-material-${item.id}` }])
    if (item.kind === 'song') emit('update:songs', [...props.songs, { ...item, local_key: `library-song-${item.id}` }])
    if (item.kind === 'file') {
        if (!props.libraryAttachUrl || !props.libraryTargetType || !props.libraryTargetId) return
        router.post(`${props.libraryAttachUrl}/${item.id}/zuordnen`, { target_type: props.libraryTargetType, target_id: props.libraryTargetId }, { preserveScroll: true, onSuccess: page => emit('uploaded', page) })
    }
    activeAdd.value = null
}
function openEdit(type, item) {
    editingType.value = type
    editingItem.value = item
    editingForm.value = { description: item.description ?? '', copyrights: item.copyrights ?? '', title: item.title ?? '', url: item.url ?? '', name: item.name ?? '', material_number: item.material_number ?? '', storage_location: item.storage_location ?? '' }
}
function closeEdit() { editingItem.value = null; editingType.value = null }
function saveEdit() {
    if (editingType.value === 'file') emit('update', editingItem.value, editingForm.value.description, editingForm.value.copyrights)
    if (editingType.value === 'resource') emit('update:resource-links', props.resourceLinks.map(item => item === editingItem.value ? { ...item, title: editingForm.value.title, url: editingForm.value.url } : item))
    if (editingType.value === 'material') emit('update:material-items', props.materialItems.map(item => item === editingItem.value ? { ...item, name: editingForm.value.name, material_number: editingForm.value.material_number, storage_location: editingForm.value.storage_location, description: editingForm.value.description } : item))
    closeEdit()
}
function removeFromLocalList(type, item, page = null) {
    if (type === 'resource') emit('update:resource-links', props.resourceLinks.filter(candidate => candidate !== item))
    if (type === 'material') emit('update:material-items', props.materialItems.filter(candidate => candidate !== item))
    if (type === 'file') emit('uploaded', page)
    if (type === 'assessment-task') emit('uploaded', page)
}
async function removeItem(type, item) {
    if (!item.id) return removeFromLocalList(type, item)
    if (!props.libraryAttachUrl || !props.libraryTargetType || !props.libraryTargetId) return
    try {
        const statusUrl = `${props.libraryAttachUrl}/${type}/${item.id}/zuordnungsstatus?target_type=${encodeURIComponent(props.libraryTargetType)}&target_id=${encodeURIComponent(props.libraryTargetId)}`
        const response = await fetch(statusUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        if (!response.ok) throw new Error('association-status')
        const status = await response.json()
        let permanent = false
        if (status.association_count <= 1) permanent = await requestConfirmation({ title: de.deleteAttachment, message: de.deleteResourcePermanentlyConfirm, actions: [{ value: false, label: de.keepInLibrary, variant: 'outline-secondary' }, { value: true, label: de.deletePermanently, variant: 'danger' }, { value: 'cancel', label: de.cancel, variant: 'secondary' }] })
        else if (!await requestConfirmation({ title: de.deleteAttachment, message: de.detachResourceConfirm, actions: [{ value: true, label: de.removeAssociation, variant: 'danger' }, { value: false, label: de.cancel, variant: 'secondary' }] })) return
        if (permanent === 'cancel') return
        router.post(`${props.libraryAttachUrl}/${type}/${item.id}/trennen`, { target_type: props.libraryTargetType, target_id: props.libraryTargetId, permanent }, { preserveScroll: true, onSuccess: page => removeFromLocalList(type, item, page), onError: errors => emit('error', Object.values(errors)[0] || de.deleteAttachmentConfirm) })
    } catch {
        emit('error', de.deleteAttachmentConfirm)
    }
}
</script>

<template>
    <div v-if="manage" class="d-flex flex-wrap gap-2 mb-3">
        <button class="btn btn-sm btn-outline-primary" type="button" @click="activeAdd = 'file'"><i class="bi bi-upload me-1" aria-hidden="true"></i>{{ de.addFile }}</button>
        <button class="btn btn-sm btn-outline-secondary" type="button" @click="activeAdd = 'resource'"><i class="bi bi-link-45deg me-1" aria-hidden="true"></i>{{ de.addResource }}</button>
        <button class="btn btn-sm btn-outline-secondary" type="button" @click="activeAdd = 'material'"><i class="bi bi-box-seam me-1" aria-hidden="true"></i>{{ de.addMaterial }}</button>
        <button class="btn btn-sm btn-outline-secondary" type="button" @click="activeAdd = 'library'"><i class="bi bi-collection me-1" aria-hidden="true"></i>Bibliothek</button>
    </div>
    <div v-if="activeAdd" class="roo-modal-backdrop" role="presentation" @click.self="activeAdd = null">
        <form v-if="activeAdd === 'file'" class="roo-modal card border-0" enctype="multipart/form-data" @submit.prevent="uploadFile"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h6 mb-0">{{ de.addFile }}</h2><button class="btn-close" type="button" :aria-label="de.close" @click="activeAdd = null"></button></div><label class="form-label" for="resource-list-file">{{ de.chooseFile }}</label><input id="resource-list-file" class="form-control" type="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.txt,.md,.wscdoc" @change="resourceUpload.resource = $event.target.files?.[0] ?? null"><label class="form-label mt-3" for="resource-list-file-description">{{ de.description }}</label><textarea id="resource-list-file-description" v-model="resourceUpload.description" class="form-control" rows="3"></textarea><div v-if="resourceUpload.errors.resource" class="invalid-feedback d-block">{{ resourceUpload.errors.resource }}</div><button class="btn btn-primary mt-3" type="submit" :disabled="!resourceUpload.resource || resourceUpload.processing">{{ de.uploadAttachment }}</button></div></form>
        <form v-else-if="activeAdd === 'resource'" class="roo-modal card border-0" @submit.prevent="addResourceLink"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h6 mb-0">{{ de.addResource }}</h2><button class="btn-close" type="button" :aria-label="de.close" @click="activeAdd = null"></button></div><label class="form-label" for="resource-list-title">{{ de.resourceTitle }}</label><input id="resource-list-title" v-model="resourceLinkForm.title" class="form-control" required><label class="form-label mt-3" for="resource-list-url">{{ de.resourceUrl }}</label><input id="resource-list-url" v-model="resourceLinkForm.url" class="form-control" type="url" required><button class="btn btn-primary mt-3" type="submit">{{ de.saveChanges }}</button></div></form>
        <form v-else-if="activeAdd === 'material'" class="roo-modal card border-0" @submit.prevent="addMaterialItem"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h6 mb-0">{{ de.addMaterial }}</h2><button class="btn-close" type="button" :aria-label="de.close" @click="activeAdd = null"></button></div><label class="form-label" for="resource-list-material">{{ de.materialItem }}</label><input id="resource-list-material" v-model="materialItemForm.name" class="form-control" required><div class="row g-2"><div class="col-md-6"><label class="form-label mt-3" for="resource-list-material-number">Materialnummer</label><input id="resource-list-material-number" v-model="materialItemForm.material_number" class="form-control"></div><div class="col-md-6"><label class="form-label mt-3" for="resource-list-material-location">Lagerort</label><input id="resource-list-material-location" v-model="materialItemForm.storage_location" class="form-control"></div></div><label class="form-label mt-3" for="resource-list-material-description">{{ de.description }}</label><textarea id="resource-list-material-description" v-model="materialItemForm.description" class="form-control" rows="3"></textarea><button class="btn btn-primary mt-3" type="submit">{{ de.saveChanges }}</button></div></form>
        <section v-else class="roo-modal card border-0" role="dialog" aria-modal="true"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h6 mb-0">Bibliothek</h2><button class="btn-close" type="button" :aria-label="de.close" @click="activeAdd = null"></button></div><input v-model="librarySearch" class="form-control mb-2" type="search" placeholder="Bibliothek durchsuchen"><div class="btn-group btn-group-sm mb-3" role="group"><button v-for="option in [{ value: 'all', label: 'Alle' }, { value: 'file', label: 'Dateien' }, { value: 'resource', label: 'Ressourcen' }, { value: 'material', label: 'Material' }, { value: 'song', label: 'Lieder' }, { value: 'assessment-task', label: 'Prüfungsaufgaben' }]" :key="option.value" class="btn" :class="libraryType === option.value ? 'btn-primary' : 'btn-outline-secondary'" type="button" @click="libraryType = option.value">{{ option.label }}</button></div><div class="list-group library-picker-list"><button v-for="item in libraryItems" :key="`${item.kind}-${item.id}`" class="list-group-item list-group-item-action text-start" type="button" @click="selectLibraryItem(item)"><strong>{{ item.original_name || item.title || item.name }}</strong><span v-if="item.kind === 'song'" class="d-block small text-muted">Lied</span><span v-if="item.kind === 'assessment-task'" class="d-block small text-muted">{{ item.description }}</span><span v-if="item.kind === 'material' && (item.material_number || item.storage_location)" class="d-block small text-muted">{{ item.material_number || '' }}{{ item.material_number && item.storage_location ? ' · ' : '' }}{{ item.storage_location || '' }}</span><span v-if="item.kind === 'resource'" class="d-block small text-muted text-break">{{ item.url }}</span></button><p v-if="!libraryItems.length" class="small text-muted mb-0">{{ libraryLoading ? 'Suche …' : 'Keine passenden Einträge gefunden.' }}</p></div></div></section>
    </div>
    <div v-if="hasItems" class="list-group">
        <div v-for="item in materialTextItems" :key="`text-${item}`" class="list-group-item d-flex align-items-start gap-2 py-2"><i class="bi bi-box-seam mt-1" aria-hidden="true"></i><span>{{ item }}</span></div>
        <article v-for="resource in resources" :key="resource.id" class="list-group-item d-flex align-items-start gap-2 py-2"><i class="bi fs-5 text-primary mt-1" :class="iconFor(resource)" aria-hidden="true"></i><span class="flex-grow-1 min-w-0"><strong class="text-break">{{ resource.display_name || resource.original_name }}</strong><span class="d-block small text-muted">{{ fileTypeFor(resource) }} · {{ sizeFor(resource.size) }}<span v-if="isWscDoc(resource) && resource.page_count"> ({{ resource.page_count }} {{ pageLabel(resource) }})</span></span><span v-if="resource.description" class="d-block small text-muted text-pre-wrap mt-1">{{ resource.description }}</span></span><span class="d-flex gap-1"><button v-if="isPreviewable(resource)" class="btn btn-sm btn-outline-primary" type="button" title="Vorschau öffnen" @click="previewResource = resource"><i class="bi bi-eye" aria-hidden="true"></i></button><button class="btn btn-sm btn-outline-secondary" type="button" title="Bearbeiten" @click="openEdit('file', resource)"><i class="bi bi-pencil" aria-hidden="true"></i></button><a class="btn btn-sm btn-outline-secondary" :href="`${downloadBaseUrl}/${resource.id}/download`" title="Herunterladen"><i class="bi bi-download" aria-hidden="true"></i></a><button class="btn btn-sm btn-outline-danger" type="button" title="Löschen" @click="removeItem('file', resource)"><i class="bi bi-trash" aria-hidden="true"></i></button></span></article>
        <article v-for="link in resourceLinks" :key="link.id || link.local_key" class="list-group-item d-flex align-items-start gap-2 py-2"><i class="bi bi-link-45deg mt-1" aria-hidden="true"></i><span class="flex-grow-1 min-w-0"><strong class="text-break">{{ link.title }}</strong><a class="d-block small text-break" :href="link.url" target="_blank" rel="noreferrer">{{ link.url }}</a></span><span class="d-flex gap-1"><button class="btn btn-sm btn-outline-secondary" type="button" title="Bearbeiten" @click="openEdit('resource', link)"><i class="bi bi-pencil" aria-hidden="true"></i></button><button class="btn btn-sm btn-outline-danger" type="button" title="Löschen" @click="removeItem('resource', link)"><i class="bi bi-trash" aria-hidden="true"></i></button></span></article>
        <article v-for="item in materialItems" :key="item.id || item.local_key" class="list-group-item d-flex align-items-start gap-2 py-2"><i class="bi bi-box-seam mt-1" aria-hidden="true"></i><span class="flex-grow-1 min-w-0"><strong class="text-break">{{ item.name }}</strong><span v-if="item.material_number || item.storage_location" class="d-block small text-muted">{{ item.material_number ? `Materialnummer: ${item.material_number}` : '' }}{{ item.material_number && item.storage_location ? ' · ' : '' }}{{ item.storage_location ? `Lagerort: ${item.storage_location}` : '' }}</span><span v-if="item.description" class="d-block small text-muted text-pre-wrap">{{ item.description }}</span></span><span class="d-flex gap-1"><button class="btn btn-sm btn-outline-secondary" type="button" title="Bearbeiten" @click="openEdit('material', item)"><i class="bi bi-pencil" aria-hidden="true"></i></button><button class="btn btn-sm btn-outline-danger" type="button" title="Löschen" @click="removeItem('material', item)"><i class="bi bi-trash" aria-hidden="true"></i></button></span></article>
        <article v-for="song in songs" :key="song.id || song.local_key" class="list-group-item d-flex align-items-start gap-2 py-2"><i class="bi bi-music-note-beamed mt-1" aria-hidden="true"></i><span class="flex-grow-1"><strong>{{ songTitle(song) }}</strong><span class="d-block small text-muted">{{ songCredits(song) }}</span></span><button v-if="song.id" class="btn btn-sm btn-outline-danger" type="button" title="Löschen" @click="removeItem('song', song)"><i class="bi bi-trash"></i></button></article>
        <article v-for="book in songbooks" :key="book.id" class="list-group-item d-flex align-items-start gap-2 py-2"><i class="bi bi-music-note-list mt-1" aria-hidden="true"></i><span class="flex-grow-1"><strong>Gruppenliederbuch</strong><span class="d-block small text-muted">Ressource der Gruppe</span></span><button class="btn btn-sm btn-outline-danger" type="button" title="Löschen" @click="removeItem('songbook', book)"><i class="bi bi-trash"></i></button></article>
        <article v-for="task in assessmentTasks" :key="task.id" class="list-group-item d-flex align-items-start gap-2 py-2"><i class="bi bi-clipboard-check mt-1" aria-hidden="true"></i><span class="flex-grow-1"><strong>{{ task.title }}</strong><span class="d-block small text-muted">{{ assessmentTaskDescription(task) }}</span></span><button class="btn btn-sm btn-outline-danger" type="button" title="Löschen" @click="removeItem('assessment-task', task)"><i class="bi bi-trash"></i></button></article>
    </div>
    <p v-else class="small text-muted">Noch keine Anhänge vorhanden.</p>
    <div v-if="editingItem" class="roo-modal-backdrop" role="presentation" @click.self="closeEdit"><form class="roo-modal card border-0" @submit.prevent="saveEdit"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h6 mb-0">{{ de.edit }}</h2><button class="btn-close" type="button" :aria-label="de.close" @click="closeEdit"></button></div><template v-if="editingType === 'file'"><label class="form-label" for="resource-edit-description">{{ de.description }}</label><textarea id="resource-edit-description" v-model="editingForm.description" class="form-control" rows="4"></textarea></template><template v-else-if="editingType === 'resource'"><label class="form-label" for="resource-edit-title">{{ de.resourceTitle }}</label><input id="resource-edit-title" v-model="editingForm.title" class="form-control" required><label class="form-label mt-3" for="resource-edit-url">{{ de.resourceUrl }}</label><input id="resource-edit-url" v-model="editingForm.url" class="form-control" type="url" required></template><template v-else><label class="form-label" for="material-edit-name">{{ de.materialItem }}</label><input id="material-edit-name" v-model="editingForm.name" class="form-control" required><div class="row g-2"><div class="col-md-6"><label class="form-label mt-3" for="material-edit-number">Materialnummer</label><input id="material-edit-number" v-model="editingForm.material_number" class="form-control"></div><div class="col-md-6"><label class="form-label mt-3" for="material-edit-location">Lagerort</label><input id="material-edit-location" v-model="editingForm.storage_location" class="form-control"></div></div><label class="form-label mt-3" for="material-edit-description">{{ de.description }}</label><textarea id="material-edit-description" v-model="editingForm.description" class="form-control" rows="3"></textarea></template><button class="btn btn-primary mt-3" type="submit">{{ de.saveChanges }}</button></div></form></div>
    <div v-if="previewResource" class="roo-modal-backdrop" role="presentation" @click.self="previewResource = null"><section class="roo-modal attachment-preview-modal" role="dialog" aria-modal="true" :aria-label="`Vorschau: ${previewResource.original_name}`"><div class="card border-0"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h6 mb-0 text-break">{{ previewResource.original_name }}</h2><button class="btn-close" type="button" aria-label="Schließen" @click="previewResource = null"></button></div><img v-if="previewKind(previewResource) === 'image'" class="attachment-preview-image" :src="`${downloadBaseUrl}/${previewResource.id}/preview`" :alt="previewResource.original_name"><video v-else-if="previewKind(previewResource) === 'video'" class="attachment-preview-media" controls :src="`${downloadBaseUrl}/${previewResource.id}/preview`"></video><audio v-else class="w-100" controls :src="`${downloadBaseUrl}/${previewResource.id}/preview`"></audio></div></div></section></div>
</template>
