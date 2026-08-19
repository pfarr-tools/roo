<script setup>
import de from '../../i18n/de'
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({ resources: { type: Array, default: () => [] }, resourceLinks: { type: Array, default: () => [] }, materialItems: { type: Array, default: () => [] }, materialText: { type: String, default: '' }, downloadBaseUrl: { type: String, required: true }, uploadUrl: { type: String, default: '' }, uploadLessonId: { type: [String, Number], default: null }, manage: { type: Boolean, default: false } })
const emit = defineEmits(['update', 'delete', 'update:resource-links', 'update:material-items', 'error'])
const descriptions = ref({})
const previewResource = ref(null)
const activeAdd = ref(null)
const resourceUpload = useForm({ resource: null, description: '', lesson_id: props.uploadLessonId })
const resourceLinkForm = ref({ title: '', url: '', description: '' })
const materialItemForm = ref({ name: '', description: '' })
const materialTextItems = () => String(props.materialText ?? '').split('\n').map(item => item.trim()).filter(Boolean)

const iconFor = resource => {
    if (resource.original_name?.toLowerCase().endsWith('.wscdoc')) return 'bi-file-earmark-richtext'
    if (resource.mime_type === 'application/pdf') return 'bi-file-earmark-pdf'
    if (resource.mime_type?.startsWith('image/')) return 'bi-file-earmark-image'
    if (resource.mime_type?.includes('word')) return 'bi-file-earmark-word'
    if (resource.mime_type?.includes('presentation')) return 'bi-file-earmark-slides'
    return 'bi-file-earmark'
}
const sizeFor = bytes => {
    if (!bytes) return '0 KB'
    if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}
const descriptionFor = resource => descriptions.value[resource.id] ?? resource.description ?? ''
const isWscDoc = resource => resource.original_name?.toLowerCase().endsWith('.wscdoc')
const fileTypeFor = resource => isWscDoc(resource) ? de.worksheetCrafter : (resource.mime_type || 'Datei')
const isPreviewable = resource => isWscDoc(resource) || resource.mime_type?.startsWith('image/') || resource.mime_type?.startsWith('video/') || resource.mime_type?.startsWith('audio/')
const previewKind = resource => isWscDoc(resource) || resource.mime_type?.startsWith('image/') ? 'image' : resource.mime_type?.startsWith('video/') ? 'video' : 'audio'
const pageLabel = resource => resource.page_count === 1 ? de.page : de.pages
function saveDescription(resource) { emit('update', resource, descriptionFor(resource)) }
function uploadFile() {
    if (!resourceUpload.resource || !props.uploadUrl) return
    resourceUpload.submit('post', props.uploadUrl, { forceFormData: true, preserveScroll: true, onSuccess: () => { resourceUpload.reset('resource', 'description'); activeAdd.value = null }, onError: errors => emit('error', Object.values(errors)[0] || de.uploadAttachmentError) })
}
function addResourceLink() {
    if (!resourceLinkForm.value.title.trim() || !resourceLinkForm.value.url.trim()) return
    emit('update:resource-links', [...props.resourceLinks, { local_key: `new-link-${Date.now()}`, ...resourceLinkForm.value }])
    resourceLinkForm.value = { title: '', url: '', description: '' }
    activeAdd.value = null
}
function addMaterialItem() {
    if (!materialItemForm.value.name.trim()) return
    emit('update:material-items', [...props.materialItems, { local_key: `new-material-${Date.now()}`, ...materialItemForm.value }])
    materialItemForm.value = { name: '', description: '' }
    activeAdd.value = null
}
</script>

<template>
    <div v-if="manage" class="d-flex flex-wrap gap-2 mb-3">
        <button class="btn btn-sm btn-outline-primary" type="button" @click="activeAdd = activeAdd === 'file' ? null : 'file'"><i class="bi bi-upload me-1" aria-hidden="true"></i>{{ de.addFile }}</button>
        <button class="btn btn-sm btn-outline-secondary" type="button" @click="activeAdd = activeAdd === 'resource' ? null : 'resource'"><i class="bi bi-link-45deg me-1" aria-hidden="true"></i>{{ de.addResource }}</button>
        <button class="btn btn-sm btn-outline-secondary" type="button" @click="activeAdd = activeAdd === 'material' ? null : 'material'"><i class="bi bi-box-seam me-1" aria-hidden="true"></i>{{ de.addMaterial }}</button>
    </div>
    <form v-if="activeAdd === 'file'" class="border rounded p-3 mb-3" enctype="multipart/form-data" @submit.prevent="uploadFile"><label class="form-label" for="resource-list-file">{{ de.chooseFile }}</label><input id="resource-list-file" class="form-control form-control-sm" type="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.txt,.md,.wscdoc" @change="resourceUpload.resource = $event.target.files?.[0] ?? null"><div v-if="resourceUpload.errors.resource" class="invalid-feedback d-block">{{ resourceUpload.errors.resource }}</div><button class="btn btn-sm btn-primary mt-2" type="submit" :disabled="!resourceUpload.resource || resourceUpload.processing">{{ de.uploadAttachment }}</button></form>
    <form v-if="activeAdd === 'resource'" class="border rounded p-3 mb-3" @submit.prevent="addResourceLink"><div class="row g-2"><div class="col-md-4"><label class="form-label" for="resource-list-title">{{ de.resourceTitle }}</label><input id="resource-list-title" v-model="resourceLinkForm.title" class="form-control form-control-sm" required></div><div class="col-md-5"><label class="form-label" for="resource-list-url">{{ de.resourceUrl }}</label><input id="resource-list-url" v-model="resourceLinkForm.url" class="form-control form-control-sm" type="url" required></div><div class="col-md-3"><label class="form-label" for="resource-list-description">{{ de.description }}</label><input id="resource-list-description" v-model="resourceLinkForm.description" class="form-control form-control-sm"></div></div><button class="btn btn-sm btn-primary mt-2" type="submit">{{ de.addResource }}</button></form>
    <form v-if="activeAdd === 'material'" class="border rounded p-3 mb-3" @submit.prevent="addMaterialItem"><div class="row g-2"><div class="col-md-5"><label class="form-label" for="resource-list-material">{{ de.materialItem }}</label><input id="resource-list-material" v-model="materialItemForm.name" class="form-control form-control-sm" required></div><div class="col-md-7"><label class="form-label" for="resource-list-material-description">{{ de.description }}</label><input id="resource-list-material-description" v-model="materialItemForm.description" class="form-control form-control-sm"></div></div><button class="btn btn-sm btn-primary mt-2" type="submit">{{ de.addMaterial }}</button></form>
    <div v-if="props.resources.length" class="attachment-list">
        <article v-for="resource in props.resources" :key="resource.id" class="attachment-item">
            <i class="bi fs-4 text-primary" :class="iconFor(resource)" aria-hidden="true"></i>
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex flex-wrap align-items-baseline gap-2">
                    <strong class="text-break">{{ resource.display_name || resource.original_name }}</strong>
                    <span class="small text-muted">{{ fileTypeFor(resource) }} · {{ sizeFor(resource.size) }}<span v-if="isWscDoc(resource) && resource.page_count"> ({{ resource.page_count }} {{ pageLabel(resource) }})</span></span>
                </div>
                <div class="input-group input-group-sm mt-2">
                    <input :value="descriptionFor(resource)" class="form-control" type="text" placeholder="Beschreibung" @input="descriptions[resource.id] = $event.target.value" @keydown.enter.prevent="saveDescription(resource)">
                    <button class="btn btn-outline-secondary" type="button" title="Beschreibung speichern" @click="saveDescription(resource)"><i class="bi bi-check2" aria-hidden="true"></i></button>
                </div>
            </div>
            <div class="d-flex gap-1 align-self-start">
                <button v-if="isPreviewable(resource)" class="btn btn-sm btn-outline-primary" type="button" title="Vorschau öffnen" :aria-label="`Vorschau: ${resource.original_name}`" @click="previewResource = resource"><i class="bi bi-eye" aria-hidden="true"></i></button>
                <a class="btn btn-sm btn-outline-secondary" :href="`${props.downloadBaseUrl}/${resource.id}/download`" title="Herunterladen" :aria-label="`Herunterladen: ${resource.original_name}`"><i class="bi bi-download" aria-hidden="true"></i></a>
                <button class="btn btn-sm btn-outline-danger" type="button" title="Löschen" :aria-label="`Löschen: ${resource.original_name}`" @click="emit('delete', resource)"><i class="bi bi-trash" aria-hidden="true"></i></button>
            </div>
        </article>
    </div>
    <div v-if="props.resourceLinks.length || props.materialItems.length || materialTextItems().length" class="list-group mb-3">
        <div v-for="item in materialTextItems()" :key="`text-${item}`" class="list-group-item small"><i class="bi bi-box-seam me-2" aria-hidden="true"></i>{{ item }}</div>
        <div v-for="link in props.resourceLinks" :key="link.id || link.local_key" class="list-group-item small"><i class="bi bi-link-45deg me-2" aria-hidden="true"></i><strong>{{ link.title }}</strong><a class="d-block ms-4 text-break" :href="link.url" target="_blank" rel="noreferrer">{{ link.url }}</a></div>
        <div v-for="item in props.materialItems" :key="item.id || item.local_key" class="list-group-item small"><i class="bi bi-box-seam me-2" aria-hidden="true"></i><strong>{{ item.name }}</strong><span v-if="item.description" class="d-block ms-4 text-muted">{{ item.description }}</span></div>
    </div>
    <p v-else class="small text-muted">Noch keine Anhänge vorhanden.</p>
    <div v-if="previewResource" class="roo-modal-backdrop" role="presentation" @click.self="previewResource = null"><section class="roo-modal attachment-preview-modal" role="dialog" aria-modal="true" :aria-label="`Vorschau: ${previewResource.original_name}`"><div class="card border-0"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h6 mb-0 text-break">{{ previewResource.original_name }}</h2><button class="btn-close" type="button" aria-label="Schließen" @click="previewResource = null"></button></div><img v-if="previewKind(previewResource) === 'image'" class="attachment-preview-image" :src="`${props.downloadBaseUrl}/${previewResource.id}/preview`" :alt="previewResource.original_name"><video v-else-if="previewKind(previewResource) === 'video'" class="attachment-preview-media" controls :src="`${props.downloadBaseUrl}/${previewResource.id}/preview`"></video><audio v-else class="w-100" controls :src="`${props.downloadBaseUrl}/${previewResource.id}/preview`"></audio></div></div></section></div>
</template>
