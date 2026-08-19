<script setup>
import { ref } from 'vue'

const props = defineProps({ resources: { type: Array, default: () => [] }, downloadBaseUrl: { type: String, required: true } })
const emit = defineEmits(['update', 'delete'])
const descriptions = ref({})
const previewResource = ref(null)

const iconFor = resource => {
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
const isPreviewable = resource => resource.mime_type?.startsWith('image/') || resource.mime_type?.startsWith('video/') || resource.mime_type?.startsWith('audio/')
const previewKind = resource => resource.mime_type?.startsWith('image/') ? 'image' : resource.mime_type?.startsWith('video/') ? 'video' : 'audio'
function saveDescription(resource) { emit('update', resource, descriptionFor(resource)) }
</script>

<template>
    <div v-if="props.resources.length" class="attachment-list">
        <article v-for="resource in props.resources" :key="resource.id" class="attachment-item">
            <i class="bi fs-4 text-primary" :class="iconFor(resource)" aria-hidden="true"></i>
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex flex-wrap align-items-baseline gap-2">
                    <strong class="text-break">{{ resource.original_name }}</strong>
                    <span class="small text-muted">{{ resource.mime_type || 'Datei' }} · {{ sizeFor(resource.size) }}</span>
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
    <p v-else class="small text-muted">Noch keine Anhänge vorhanden.</p>
    <div v-if="previewResource" class="roo-modal-backdrop" role="presentation" @click.self="previewResource = null"><section class="roo-modal attachment-preview-modal" role="dialog" aria-modal="true" :aria-label="`Vorschau: ${previewResource.original_name}`"><div class="card border-0"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h6 mb-0 text-break">{{ previewResource.original_name }}</h2><button class="btn-close" type="button" aria-label="Schließen" @click="previewResource = null"></button></div><img v-if="previewKind(previewResource) === 'image'" class="attachment-preview-image" :src="`${props.downloadBaseUrl}/${previewResource.id}/preview`" :alt="previewResource.original_name"><video v-else-if="previewKind(previewResource) === 'video'" class="attachment-preview-media" controls :src="`${props.downloadBaseUrl}/${previewResource.id}/preview`"></video><audio v-else class="w-100" controls :src="`${props.downloadBaseUrl}/${previewResource.id}/preview`"></audio></div></div></section></div>
</template>
