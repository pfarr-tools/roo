<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import de from '../../i18n/de'

const props = defineProps({ templates: Array, unitTemplates: Array, filters: Object })
const open = ref(false)
const search = ref(props.filters?.q ?? '')
const editing = ref(null)
const form = useForm({ unit_template_id: '', title: '', duration_minutes: '', objective: '', notes: '' })

function resetForm() {
    form.reset()
    form.clearErrors()
    editing.value = null
}

function openCreate() {
    resetForm()
    open.value = true
}

function openEdit(template) {
    editing.value = template
    form.defaults({ unit_template_id: template.unit_template_id, title: template.title, duration_minutes: template.duration_minutes ?? '', objective: template.objective ?? '', notes: template.notes ?? '' })
    form.reset()
    form.clearErrors()
    open.value = true
}

function save() {
    const options = { onSuccess: () => { form.reset(); open.value = false } }
    if (editing.value) form.put(`/stunden-vorlagen/${editing.value.id}`, options)
    else form.post('/stunden-vorlagen', options)
}

function remove(template) {
    if (window.confirm(de.deleteLessonTemplateConfirm)) router.delete(`/stunden-vorlagen/${template.id}`)
}

function filter() {
    router.get('/stunden-vorlagen', { q: search.value }, { preserveState: true, replace: true })
}
</script>

<template>
    <AppShell>
        <template #toolbar><button class="btn btn-sm btn-primary" type="button" @click="openCreate"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ de.addLessonTemplate }}</button></template>
        <div class="container-full px-3 py-4">
            <h1 class="h2">{{ de.lessonTemplates }}</h1>
            <form class="row g-2 mb-3" role="search" @submit.prevent="filter"><div class="col-sm-8 col-lg-5"><label class="visually-hidden" for="lesson-template-search">{{ de.searchTemplates }}</label><input id="lesson-template-search" v-model="search" class="form-control" type="search" :placeholder="de.searchTemplates"></div><div class="col-auto"><button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search me-1" aria-hidden="true"></i>{{ de.filter }}</button></div></form>
            <div v-if="$page.props.flash?.success" class="alert alert-success">{{ $page.props.flash.success }}</div>
            <div v-if="!templates.length" class="alert alert-info">{{ de.noLessonTemplates }}</div>
            <div v-for="template in templates" :key="template.id" class="card mb-3">
                <div class="card-body"><div class="d-flex justify-content-between gap-3"><div><h2 class="h5 mb-1">{{ template.title }}</h2><div class="text-muted small mb-2">{{ template.unit_template.title }} · {{ template.duration_minutes ? `${template.duration_minutes} ${de.minutes}` : de.noDuration }} · {{ de.version }} {{ template.version }}</div><p v-if="template.objective" class="mb-0">{{ template.objective }}</p></div><div class="d-flex align-items-start gap-2"><span class="badge text-bg-light">{{ de.lessonTemplate }}</span><button class="btn btn-sm btn-outline-primary" type="button" :aria-label="`${de.editLessonTemplate}: ${template.title}`" @click="openEdit(template)"><i class="bi bi-pencil" aria-hidden="true"></i></button><button class="btn btn-sm btn-outline-danger" type="button" :aria-label="`${de.deleteLessonTemplate}: ${template.title}`" @click="remove(template)"><i class="bi bi-trash" aria-hidden="true"></i></button></div></div></div>
            </div>
        </div>
        <div v-if="open" class="roo-modal-backdrop" role="presentation" @click.self="open = false"><section class="roo-modal" role="dialog" aria-modal="true" :aria-label="editing ? de.editLessonTemplate : de.addLessonTemplate"><div class="card border-0"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">{{ editing ? de.editLessonTemplate : de.addLessonTemplate }}</h2><button class="btn-close" type="button" :aria-label="de.close" @click="open = false"></button></div><form @submit.prevent="save">
            <label class="form-label" for="lesson-template-unit">{{ de.assignedUnitTemplate }}</label><select id="lesson-template-unit" v-model="form.unit_template_id" class="form-select" required><option value="">{{ de.choose }}</option><option v-for="unitTemplate in unitTemplates" :key="unitTemplate.id" :value="unitTemplate.id">{{ unitTemplate.title }}</option></select>
            <label class="form-label mt-3" for="lesson-template-title">{{ de.lessonTitle }}</label><input id="lesson-template-title" v-model="form.title" class="form-control" required>
            <label class="form-label mt-3" for="lesson-template-duration">{{ de.durationMinutes }}</label><input id="lesson-template-duration" v-model="form.duration_minutes" class="form-control" type="number" min="1">
            <label class="form-label mt-3" for="lesson-template-objective">{{ de.objective }}</label><textarea id="lesson-template-objective" v-model="form.objective" class="form-control" rows="3"></textarea>
            <label class="form-label mt-3" for="lesson-template-notes">{{ de.notes }}</label><textarea id="lesson-template-notes" v-model="form.notes" class="form-control" rows="3"></textarea>
            <div class="d-flex justify-content-end gap-2 mt-4"><button class="btn btn-outline-secondary" type="button" @click="open = false">{{ de.cancel }}</button><button class="btn btn-primary" type="submit" :disabled="form.processing">{{ de.save }}</button></div>
        </form></div></div></section></div>
    </AppShell>
</template>
