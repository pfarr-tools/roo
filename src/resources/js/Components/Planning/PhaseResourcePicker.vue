<script setup>
import de from '../../i18n/de'

const props = defineProps({
    resources: { type: Array, default: () => [] },
    resourceLinks: { type: Array, default: () => [] },
    materialItems: { type: Array, default: () => [] },
    selectedResourceIds: { type: Array, default: () => [] },
    selectedResourceLinkIds: { type: Array, default: () => [] },
    selectedMaterialItemIds: { type: Array, default: () => [] },
})
const emit = defineEmits(['update:resource-ids', 'update:resource-link-ids', 'update:material-item-ids'])

const toggle = (values, value, event) => {
    const next = [...values]
    const index = next.findIndex(item => String(item) === String(value))
    if (event.target.checked && index === -1) next.push(value)
    if (!event.target.checked && index !== -1) next.splice(index, 1)
    return next
}
</script>

<template>
    <fieldset class="border rounded p-3 mt-3">
        <legend class="float-none w-auto px-2 fs-6 mb-0">{{ de.phaseResources }}</legend>
        <p class="small text-muted">{{ de.phaseResourcesHint }}</p>
        <div v-if="resources.length" class="mb-3">
            <div class="small fw-semibold mb-1">{{ de.attachments }}</div>
            <label v-for="resource in resources" :key="resource.id" class="d-flex gap-2 align-items-start small mb-2">
                <input class="form-check-input mt-1" type="checkbox" :checked="selectedResourceIds.some(id => String(id) === String(resource.id))" @change="emit('update:resource-ids', toggle(selectedResourceIds, resource.id, $event))">
                <span class="text-break">{{ resource.display_name || resource.original_name }}<span class="d-block text-muted">{{ resource.size ? `${Math.round(resource.size / 1024)} KB` : '' }}<span v-if="resource.page_count"> · {{ resource.page_count }} {{ resource.page_count === 1 ? de.page : de.pages }}</span></span></span>
            </label>
        </div>
        <div v-if="resourceLinks.length" class="mb-3">
            <div class="small fw-semibold mb-1">{{ de.resources }}</div>
            <label v-for="link in resourceLinks" :key="link.id || link.local_key" class="d-flex gap-2 align-items-start small mb-2">
                <input class="form-check-input mt-1" type="checkbox" :checked="selectedResourceLinkIds.some(id => String(id) === String(link.id || link.local_key))" @change="emit('update:resource-link-ids', toggle(selectedResourceLinkIds, link.id || link.local_key, $event))">
                <span class="text-break"><span class="fw-semibold">{{ link.title }}</span><a class="d-block" :href="link.url" target="_blank" rel="noreferrer">{{ link.url }}</a></span>
            </label>
        </div>
        <div v-if="materialItems.length" class="mb-3">
            <div class="small fw-semibold mb-1">{{ de.materialItems }}</div>
            <label v-for="item in materialItems" :key="item.id" class="d-flex gap-2 align-items-start small mb-2">
                <input class="form-check-input mt-1" type="checkbox" :checked="selectedMaterialItemIds.some(id => String(id) === String(item.id))" @change="emit('update:material-item-ids', toggle(selectedMaterialItemIds, item.id, $event))">
                <span>{{ item.name }}<span v-if="item.description" class="d-block text-muted">{{ item.description }}</span></span>
            </label>
        </div>
    </fieldset>
</template>
