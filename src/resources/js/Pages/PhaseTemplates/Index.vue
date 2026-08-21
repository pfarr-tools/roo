<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import { ref } from 'vue'
import { requestConfirmation } from '../../utils/confirmation'
import { router, useForm } from '@inertiajs/vue3'
import de from '../../i18n/de'

const props = defineProps({ templates: Array, lessonTemplates: Array, filters: Object })
const open = ref(false)
const search = ref(props.filters?.q ?? '')
const materialItemsText = ref('')
const editing = ref(null)
const form = useForm({ lesson_template_id: '', title: '', duration_minutes: '', social_form: '', teacher_interaction: '', learner_activity: '', differentiation: '', didactic_comment: '', material: '', media: '', position: '' })
const resourceForm = useForm({ resource: null })

function resetForm() { form.reset(); form.clearErrors(); editing.value = null; materialItemsText.value = '' }
function openCreate() { resetForm(); open.value = true }
function openEdit(template) {
    editing.value = template
    form.defaults({ lesson_template_id: template.lesson_template_id, title: template.title, duration_minutes: template.duration_minutes ?? '', social_form: template.social_form?.name ?? '', teacher_interaction: template.teacher_interaction ?? '', learner_activity: template.learner_activity ?? '', differentiation: template.differentiation ?? '', didactic_comment: template.didactic_comment ?? '', material: template.material ?? '', media: template.media ?? '', position: template.position, material_items: template.material_items?.map(item => item.name) ?? [] })
    form.reset(); form.clearErrors(); materialItemsText.value = template.material_items?.map(item => item.name).join(', ') ?? ''; open.value = true
}
function save() {
    form.material_items = materialItemsText.value.split(',').map(item => item.trim()).filter(Boolean)
    const options = { onSuccess: () => { form.reset(); open.value = false } }
    if (editing.value) form.put(`/phasen-vorlagen/${editing.value.id}`, options)
    else form.post('/phasen-vorlagen', options)
}
async function remove(template) {
    if (await requestConfirmation({ message: de.deletePhaseTemplateConfirm })) router.delete(`/phasen-vorlagen/${template.id}`)
}

function filter() {
    router.get('/phasen-vorlagen', { q: search.value }, { preserveState: true, replace: true })
}

function copyTemplate(template) {
    router.post(`/phasen-vorlagen/${template.id}/kopieren`)
}

function uploadResource(template) {
    resourceForm.post(`/phasen-vorlagen/${template.id}/anhaenge`, { forceFormData: true, onSuccess: () => resourceForm.reset() })
}

async function removeResource(template, resource) {
    if (await requestConfirmation({ message: `${de.attachments}: ${resource.original_name} wirklich löschen?` })) router.delete(`/phasen-vorlagen/${template.id}/anhaenge/${resource.id}`)
}
</script>

<template>
    <AppShell>
        <template #toolbar><button class="btn btn-sm btn-primary" type="button" @click="openCreate"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ de.addPhaseTemplate }}</button></template>
        <div class="container-full px-3 py-4">
            <h1 class="h2">{{ de.phaseTemplates }}</h1>
            <form class="row g-2 mb-3" role="search" @submit.prevent="filter"><div class="col-sm-8 col-lg-5"><label class="visually-hidden" for="phase-template-search">{{ de.searchTemplates }}</label><input id="phase-template-search" v-model="search" class="form-control" type="search" :placeholder="de.searchTemplates"></div><div class="col-auto"><button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search me-1" aria-hidden="true"></i>{{ de.filter }}</button></div></form>
            <div v-if="!templates.length" class="alert alert-info">{{ de.noPhaseTemplates }}</div>
            <div v-for="template in templates" :key="template.id" class="card mb-3"><div class="card-body"><div class="d-flex justify-content-between gap-3"><div><h2 class="h5 mb-1">{{ template.title }}</h2><div class="text-muted small mb-2">{{ template.lesson_template.title }} · {{ template.duration_minutes ? `${template.duration_minutes} ${de.minutes}` : de.noDuration }}<span v-if="template.social_form"> · {{ template.social_form.name }}</span> · {{ de.version }} {{ template.version }}</div><div v-if="template.material" class="small"><strong>{{ de.material }}:</strong> {{ template.material }}</div><div v-if="template.material_items?.length" class="small mt-2"><strong>{{ de.materialItems }}:</strong> <span v-for="item in template.material_items" :key="item.id" class="badge text-bg-light me-1">{{ item.name }}</span></div><div v-if="template.resources?.length" class="small mt-2"><strong>{{ de.attachments }}:</strong> <span v-for="resource in template.resources" :key="resource.id" class="badge text-bg-light me-1">{{ resource.original_name }} <button class="btn btn-link btn-sm p-0 text-danger" type="button" @click="removeResource(template, resource)">×</button></span></div><form class="mt-2" @submit.prevent="uploadResource(template)"><label class="visually-hidden" :for="`phase-resource-${template.id}`">{{ de.chooseFile }}</label><input :id="`phase-resource-${template.id}`" class="form-control form-control-sm" type="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.txt,.md" @change="resourceForm.resource = $event.target.files[0]"><button class="btn btn-sm btn-outline-secondary mt-1" type="submit" :disabled="!resourceForm.resource || resourceForm.processing">{{ de.uploadAttachment }}</button></form></div><div class="d-flex align-items-start gap-2"><span class="badge text-bg-light">{{ de.phaseTemplate }}</span><button class="btn btn-sm btn-outline-secondary" type="button" :aria-label="`${de.copyTemplate}: ${template.title}`" @click="copyTemplate(template)"><i class="bi bi-copy" aria-hidden="true"></i></button><button class="btn btn-sm btn-outline-primary" type="button" :aria-label="`${de.editPhaseTemplate}: ${template.title}`" @click="openEdit(template)"><i class="bi bi-pencil" aria-hidden="true"></i></button><button class="btn btn-sm btn-outline-danger" type="button" :aria-label="`${de.deletePhaseTemplate}: ${template.title}`" @click="remove(template)"><i class="bi bi-trash" aria-hidden="true"></i></button></div></div></div></div>
        </div>
        <div v-if="open" class="roo-modal-backdrop" role="presentation" @click.self="open = false"><section class="roo-modal" role="dialog" aria-modal="true" :aria-label="editing ? de.editPhaseTemplate : de.addPhaseTemplate"><div class="card border-0"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">{{ editing ? de.editPhaseTemplate : de.addPhaseTemplate }}</h2><button class="btn-close" type="button" :aria-label="de.close" @click="open = false"></button></div><form @submit.prevent="save">
            <label class="form-label" for="phase-template-lesson">{{ de.assignedLessonTemplate }}</label><select id="phase-template-lesson" v-model="form.lesson_template_id" class="form-select" required><option value="">{{ de.choose }}</option><option v-for="lessonTemplate in lessonTemplates" :key="lessonTemplate.id" :value="lessonTemplate.id">{{ lessonTemplate.title }}</option></select>
            <label class="form-label mt-3" for="phase-template-title">{{ de.phaseTitle }}</label><input id="phase-template-title" v-model="form.title" class="form-control" required>
            <label class="form-label mt-3" for="phase-template-duration">{{ de.durationMinutes }}</label><input id="phase-template-duration" v-model="form.duration_minutes" class="form-control" type="number" min="1">
            <label class="form-label mt-3" for="phase-template-social-form">{{ de.socialForm }}</label><input id="phase-template-social-form" v-model="form.social_form" class="form-control" placeholder="z. B. Plenum">
            <div class="row g-2"><div class="col-lg-6"><label class="form-label mt-3" for="phase-template-teacher-interaction">{{ de.teacherInteraction }}</label><textarea id="phase-template-teacher-interaction" v-model="form.teacher_interaction" class="form-control" rows="3"></textarea></div><div class="col-lg-6"><label class="form-label mt-3" for="phase-template-learner-activity">{{ de.learnerActivity }}</label><textarea id="phase-template-learner-activity" v-model="form.learner_activity" class="form-control" rows="3"></textarea></div><div class="col-lg-6"><label class="form-label mt-3" for="phase-template-differentiation">{{ de.differentiation }}</label><textarea id="phase-template-differentiation" v-model="form.differentiation" class="form-control" rows="3"></textarea></div><div class="col-lg-6"><label class="form-label mt-3" for="phase-template-didactic-comment">{{ de.didacticComment }}</label><textarea id="phase-template-didactic-comment" v-model="form.didactic_comment" class="form-control" rows="3"></textarea></div></div>
            <label class="form-label mt-3" for="phase-template-material">{{ de.material }}</label><textarea id="phase-template-material" v-model="form.material" class="form-control" rows="2"></textarea>
            <label class="form-label mt-3" for="phase-template-media">{{ de.media }}</label><textarea id="phase-template-media" v-model="form.media" class="form-control" rows="2"></textarea>
            <label class="form-label mt-3" for="phase-template-material-items">{{ de.materialItems }}</label><input id="phase-template-material-items" v-model="materialItemsText" class="form-control" :placeholder="de.materialItemsHint">
            <label class="form-label mt-3" for="phase-template-position">{{ de.position }}</label><input id="phase-template-position" v-model="form.position" class="form-control" type="number" min="0">
            <div class="d-flex justify-content-end gap-2 mt-4"><button class="btn btn-outline-secondary" type="button" @click="open = false">{{ de.cancel }}</button><button class="btn btn-primary" type="submit" :disabled="form.processing">{{ de.save }}</button></div>
        </form></div></div></section></div>
    </AppShell>
</template>
