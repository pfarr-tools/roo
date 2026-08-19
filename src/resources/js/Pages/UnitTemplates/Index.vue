<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import { ref } from 'vue'
import { requestConfirmation } from '../../utils/confirmation'
import { router, useForm } from '@inertiajs/vue3'
import de from '../../i18n/de'

const props = defineProps({ templates: Array, filters: Object })
const open = ref(false)
const search = ref(props.filters?.q ?? '')
const editing = ref(null)
const tagsText = ref('')
const form = useForm({ title: '', description: '', expected_hours: '', notes: '', tags: [] })
const resourceForm = useForm({ resource: null })

function resetForm() {
    form.reset()
    form.clearErrors()
    tagsText.value = ''
    editing.value = null
}

function openCreate() {
    resetForm()
    open.value = true
}

function openEdit(template) {
    editing.value = template
    form.defaults({ title: template.title, description: template.description ?? '', expected_hours: template.expected_hours ?? '', notes: template.notes ?? '', tags: template.tags?.map(tag => tag.name) ?? [] })
    form.reset()
    form.clearErrors()
    tagsText.value = template.tags?.map(tag => tag.name).join(', ') ?? ''
    open.value = true
}

function save() {
    form.tags = tagsText.value.split(',').map(tag => tag.trim()).filter(Boolean)
    const options = {
        onSuccess: () => { form.reset(); open.value = false },
    }
    if (editing.value) {
        form.put(`/unterrichtseinheiten-vorlagen/${editing.value.id}`, options)
    } else {
        form.post('/unterrichtseinheiten-vorlagen', options)
    }
}

async function remove(template) {
    if (await requestConfirmation({ message: de.deleteUnitTemplateConfirm })) router.delete(`/unterrichtseinheiten-vorlagen/${template.id}`)
}

function filter() {
    router.get('/unterrichtseinheiten-vorlagen', { q: search.value }, { preserveState: true, replace: true })
}

function copyTemplate(template) {
    router.post(`/unterrichtseinheiten-vorlagen/${template.id}/kopieren`)
}

function uploadResource(template) {
    resourceForm.post(`/unterrichtseinheiten-vorlagen/${template.id}/anhaenge`, { forceFormData: true, onSuccess: () => resourceForm.reset() })
}

async function removeResource(template, resource) {
    if (await requestConfirmation({ message: `${de.attachments}: ${resource.original_name} wirklich löschen?` })) router.delete(`/unterrichtseinheiten-vorlagen/${template.id}/anhaenge/${resource.id}`)
}
</script>

<template>
    <AppShell>
        <template #toolbar><button class="btn btn-sm btn-primary" type="button" @click="openCreate"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ de.addUnitTemplate }}</button></template>
        <div class="container-full px-3 py-4">
            <h1 class="h2">{{ de.unitTemplates }}</h1>
            <form class="row g-2 mb-3" role="search" @submit.prevent="filter"><div class="col-sm-8 col-lg-5"><label class="visually-hidden" for="unit-template-search">{{ de.searchTemplates }}</label><input id="unit-template-search" v-model="search" class="form-control" type="search" :placeholder="de.searchTemplates"></div><div class="col-auto"><button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search me-1" aria-hidden="true"></i>{{ de.filter }}</button></div></form>
            <div v-if="$page.props.flash?.success" class="alert alert-success">{{ $page.props.flash.success }}</div>
            <div v-if="!templates.length" class="alert alert-info">{{ de.noUnitTemplates }}</div>
            <div v-for="template in templates" :key="template.id" class="card mb-3">
                <div class="card-body"><div class="d-flex justify-content-between gap-3"><div><h2 class="h5 mb-1">{{ template.title }}</h2><p v-if="template.description" class="mb-2">{{ template.description }}</p><div class="text-muted small">{{ template.expected_hours ? `${template.expected_hours} ${de.hours.toLowerCase()}` : de.noExpectedHours }} · {{ de.version }} {{ template.version }}</div><div v-if="template.tags?.length" class="mt-2"><span v-for="tag in template.tags" :key="tag.id" class="badge text-bg-light me-1">{{ tag.name }}</span></div><div v-if="template.resources?.length" class="mt-2"><strong class="small">{{ de.attachments }}:</strong> <span v-for="resource in template.resources" :key="resource.id" class="badge text-bg-light me-1">{{ resource.original_name }} <button class="btn btn-link btn-sm p-0 text-danger" type="button" :aria-label="`${de.attachments}: ${resource.original_name}`" @click="removeResource(template, resource)">×</button></span></div><form class="mt-2" @submit.prevent="uploadResource(template)"><label class="visually-hidden" :for="`resource-${template.id}`">{{ de.chooseFile }}</label><input :id="`resource-${template.id}`" class="form-control form-control-sm" type="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.txt,.md" @change="resourceForm.resource = $event.target.files[0]"><button class="btn btn-sm btn-outline-secondary mt-1" type="submit" :disabled="!resourceForm.resource || resourceForm.processing">{{ de.uploadAttachment }}</button></form></div><div class="d-flex align-items-start gap-2"><span class="badge text-bg-light">{{ de.unitTemplate }}</span><button class="btn btn-sm btn-outline-secondary" type="button" :aria-label="`${de.copyTemplate}: ${template.title}`" @click="copyTemplate(template)"><i class="bi bi-copy" aria-hidden="true"></i></button><button class="btn btn-sm btn-outline-primary" type="button" :aria-label="`${de.editUnitTemplate}: ${template.title}`" @click="openEdit(template)"><i class="bi bi-pencil" aria-hidden="true"></i></button><button class="btn btn-sm btn-outline-danger" type="button" :aria-label="`${de.deleteUnitTemplate}: ${template.title}`" @click="remove(template)"><i class="bi bi-trash" aria-hidden="true"></i></button></div></div></div>
            </div>
        </div>
        <div v-if="open" class="roo-modal-backdrop" role="presentation" @click.self="open = false"><section class="roo-modal" role="dialog" aria-modal="true" :aria-label="editing ? de.editUnitTemplate : de.addUnitTemplate"><div class="card border-0"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">{{ editing ? de.editUnitTemplate : de.addUnitTemplate }}</h2><button class="btn-close" type="button" :aria-label="de.close" @click="open = false"></button></div><form @submit.prevent="save">
            <label class="form-label" for="unit-template-title">{{ de.unitTitle }}</label><input id="unit-template-title" v-model="form.title" class="form-control" required>
            <label class="form-label mt-3" for="unit-template-description">{{ de.description }}</label><textarea id="unit-template-description" v-model="form.description" class="form-control" rows="3"></textarea>
            <label class="form-label mt-3" for="unit-template-hours">{{ de.hours }}</label><input id="unit-template-hours" v-model="form.expected_hours" class="form-control" type="number" min="1">
            <label class="form-label mt-3" for="unit-template-notes">{{ de.notes }}</label><textarea id="unit-template-notes" v-model="form.notes" class="form-control" rows="3"></textarea>
            <label class="form-label mt-3" for="unit-template-tags">{{ de.tags }}</label><input id="unit-template-tags" v-model="tagsText" class="form-control" :placeholder="de.tagsHint">
            <div class="d-flex justify-content-end gap-2 mt-4"><button class="btn btn-outline-secondary" type="button" @click="open = false">{{ de.cancel }}</button><button class="btn btn-primary" type="submit" :disabled="form.processing">{{ de.save }}</button></div>
        </form></div></div></section></div>
    </AppShell>
</template>
