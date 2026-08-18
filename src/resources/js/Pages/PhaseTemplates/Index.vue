<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import de from '../../i18n/de'

defineProps({ templates: Array, lessonTemplates: Array })
const open = ref(false)
const editing = ref(null)
const form = useForm({ lesson_template_id: '', title: '', duration_minutes: '', social_form: '', description: '', material: '', position: '' })

function resetForm() { form.reset(); form.clearErrors(); editing.value = null }
function openCreate() { resetForm(); open.value = true }
function openEdit(template) {
    editing.value = template
    form.defaults({ lesson_template_id: template.lesson_template_id, title: template.title, duration_minutes: template.duration_minutes ?? '', social_form: template.social_form ?? '', description: template.description ?? '', material: template.material ?? '', position: template.position })
    form.reset(); form.clearErrors(); open.value = true
}
function save() {
    const options = { onSuccess: () => { form.reset(); open.value = false } }
    if (editing.value) form.put(`/phasen-vorlagen/${editing.value.id}`, options)
    else form.post('/phasen-vorlagen', options)
}
function remove(template) {
    if (window.confirm(de.deletePhaseTemplateConfirm)) router.delete(`/phasen-vorlagen/${template.id}`)
}
</script>

<template>
    <AppShell>
        <template #toolbar><button class="btn btn-sm btn-primary" type="button" @click="openCreate"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ de.addPhaseTemplate }}</button></template>
        <div class="container-full px-3 py-4">
            <h1 class="h2">{{ de.phaseTemplates }}</h1>
            <div v-if="$page.props.flash?.success" class="alert alert-success">{{ $page.props.flash.success }}</div>
            <div v-if="!templates.length" class="alert alert-info">{{ de.noPhaseTemplates }}</div>
            <div v-for="template in templates" :key="template.id" class="card mb-3"><div class="card-body"><div class="d-flex justify-content-between gap-3"><div><h2 class="h5 mb-1">{{ template.title }}</h2><div class="text-muted small mb-2">{{ template.lesson_template.title }} · {{ template.duration_minutes ? `${template.duration_minutes} ${de.minutes}` : de.noDuration }}<span v-if="template.social_form"> · {{ template.social_form }}</span> · {{ de.version }} {{ template.version }}</div><p v-if="template.description" class="mb-2">{{ template.description }}</p><div v-if="template.material" class="small"><strong>{{ de.material }}:</strong> {{ template.material }}</div></div><div class="d-flex align-items-start gap-2"><span class="badge text-bg-light">{{ de.phaseTemplate }}</span><button class="btn btn-sm btn-outline-primary" type="button" :aria-label="`${de.editPhaseTemplate}: ${template.title}`" @click="openEdit(template)"><i class="bi bi-pencil" aria-hidden="true"></i></button><button class="btn btn-sm btn-outline-danger" type="button" :aria-label="`${de.deletePhaseTemplate}: ${template.title}`" @click="remove(template)"><i class="bi bi-trash" aria-hidden="true"></i></button></div></div></div></div>
        </div>
        <div v-if="open" class="roo-modal-backdrop" role="presentation" @click.self="open = false"><section class="roo-modal" role="dialog" aria-modal="true" :aria-label="editing ? de.editPhaseTemplate : de.addPhaseTemplate"><div class="card border-0"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">{{ editing ? de.editPhaseTemplate : de.addPhaseTemplate }}</h2><button class="btn-close" type="button" :aria-label="de.close" @click="open = false"></button></div><form @submit.prevent="save">
            <label class="form-label" for="phase-template-lesson">{{ de.assignedLessonTemplate }}</label><select id="phase-template-lesson" v-model="form.lesson_template_id" class="form-select" required><option value="">{{ de.choose }}</option><option v-for="lessonTemplate in lessonTemplates" :key="lessonTemplate.id" :value="lessonTemplate.id">{{ lessonTemplate.title }}</option></select>
            <label class="form-label mt-3" for="phase-template-title">{{ de.phaseTitle }}</label><input id="phase-template-title" v-model="form.title" class="form-control" required>
            <label class="form-label mt-3" for="phase-template-duration">{{ de.durationMinutes }}</label><input id="phase-template-duration" v-model="form.duration_minutes" class="form-control" type="number" min="1">
            <label class="form-label mt-3" for="phase-template-social-form">{{ de.socialForm }}</label><input id="phase-template-social-form" v-model="form.social_form" class="form-control" placeholder="z. B. Plenum">
            <label class="form-label mt-3" for="phase-template-description">{{ de.description }}</label><textarea id="phase-template-description" v-model="form.description" class="form-control" rows="3"></textarea>
            <label class="form-label mt-3" for="phase-template-material">{{ de.material }}</label><textarea id="phase-template-material" v-model="form.material" class="form-control" rows="2"></textarea>
            <label class="form-label mt-3" for="phase-template-position">{{ de.position }}</label><input id="phase-template-position" v-model="form.position" class="form-control" type="number" min="0">
            <div class="d-flex justify-content-end gap-2 mt-4"><button class="btn btn-outline-secondary" type="button" @click="open = false">{{ de.cancel }}</button><button class="btn btn-primary" type="submit" :disabled="form.processing">{{ de.save }}</button></div>
        </form></div></div></section></div>
    </AppShell>
</template>
