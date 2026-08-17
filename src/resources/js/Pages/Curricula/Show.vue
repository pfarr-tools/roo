<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import de from '../../i18n/de'
import { router, useForm } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'

const props = defineProps({ curriculum: Object, version: Object })
const years = computed(() => {
    const metadataYears = [...new Set((props.curriculum.grades ?? []).map(Number))]
        .filter(year => year >= 1 && year <= 13)
        .sort((first, second) => first - second)

    return metadataYears.length ? metadataYears : Array.from({ length: 10 }, (_, index) => index + 1)
})
const editingTopic = ref(null)
const showCurriculumForm = ref(false)
const showAddForm = ref(false)
const curriculumForm = useForm({ title: props.curriculum.title, school_type: props.curriculum.school_type ?? '', grades: props.curriculum.grades ?? [] })
const addForm = useForm({ title: '', year: '', hours: '', notes: '', preparation_questions: '' })
const topicForms = reactive(Object.fromEntries(props.version.topics.map(topic => [topic.id, { title: topic.title, hours: topic.hours ?? '', notes: topic.notes ?? '', preparation_questions: (topic.preparation_questions ?? []).join('\n') }])))

function topics(year) { return props.version.topics.filter(topic => (topic.year ?? null) === year) }
function yearLabel(year) { return `${de.classLabel} ${year}` }
function startDrag(event, topic) { event.dataTransfer.setData('topic', topic.id) }
function unassignedGroups() {
    const groups = props.version.topics.filter(topic => topic.year === null).reduce((groups, topic) => {
        const key = topic.source_version?.curriculum?.title ?? de.ownUnit
        groups[key] ??= []
        groups[key].push(topic)
        return groups
    }, {})
    return Object.fromEntries(Object.entries(groups).sort(([first], [second]) => first.localeCompare(second, 'de')))
}
function move(topic, year) { router.post(`/curricula/${props.curriculum.id}/themen/${topic.id}/jahr`, { year }, { preserveScroll: true }) }
function drop(event, year) { const id = event.dataTransfer.getData('topic'); const topic = props.version.topics.find(item => String(item.id) === id); if (topic) move(topic, year) }
function saveCurriculum() { curriculumForm.put(`/curricula/${props.curriculum.id}`, { preserveScroll: true, onSuccess: () => { showCurriculumForm.value = false } }) }
function saveTopic(topic) { router.put(`/curricula/${props.curriculum.id}/themen/${topic.id}`, topicForms[topic.id], { preserveScroll: true, onSuccess: () => { editingTopic.value = null } }) }
function addTopic() { addForm.post(`/curricula/${props.curriculum.id}/themen`, { preserveScroll: true, onSuccess: () => { addForm.reset(); showAddForm.value = false } }) }
function deleteCurriculum() { if (window.confirm(de.deleteCurriculumConfirm)) router.delete(`/curricula/${props.curriculum.id}`) }
</script>

<template>
    <AppShell>
        <template #toolbar>
            <div v-if="version.is_editable" class="d-flex align-items-center gap-2"><button class="btn btn-sm btn-primary" type="button" @click="showCurriculumForm = true"><i class="bi bi-pencil me-1" aria-hidden="true"></i><span class="d-none d-md-inline">{{ de.editCurriculum }}</span></button><button class="btn btn-sm btn-outline-primary" type="button" @click="showAddForm = true"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i><span class="d-none d-md-inline">{{ de.addUnit }}</span></button><button class="btn btn-sm btn-outline-danger" type="button" @click="deleteCurriculum"><i class="bi bi-trash me-1" aria-hidden="true"></i><span class="d-none d-md-inline">{{ de.deleteCurriculum }}</span></button></div>
        </template>
        <div class="container-fluid py-4">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div><a href="/curricula">{{ de.curricula }}</a><h1 class="h2 mt-1 mb-1">{{ curriculum.title }}</h1><p class="text-muted mb-0">{{ [curriculum.school_type, curriculum.grades?.join(', ')].filter(Boolean).join(' · ') }} · {{ version.topics.length }} {{ de.units }}</p></div>
                <span v-if="version.is_editable" class="badge text-bg-success">{{ de.editableCurriculum }}</span>
            </div>
            <div v-if="showCurriculumForm" class="roo-modal-backdrop" role="presentation" @click.self="showCurriculumForm = false"><section class="roo-modal" role="dialog" aria-modal="true" :aria-label="de.editCurriculum"><div class="card border-0"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">{{ de.editCurriculum }}</h2><button class="btn-close" type="button" :aria-label="de.close" @click="showCurriculumForm = false"></button></div><form class="row g-3 align-items-end" @submit.prevent="saveCurriculum"><div class="col-md-6"><label class="form-label">{{ de.curriculumTitle }}</label><input v-model="curriculumForm.title" class="form-control" required></div><div class="col-md-3"><label class="form-label">{{ de.schoolType }}</label><input v-model="curriculumForm.school_type" class="form-control"></div><div class="col-md-3"><label class="form-label">{{ de.grades }}</label><input :value="curriculumForm.grades.join(', ')" class="form-control" @change="curriculumForm.grades = $event.target.value.split(',').map(value => Number(value.trim())).filter(Boolean)"></div><div class="col-12 d-flex justify-content-end gap-2"><button class="btn btn-outline-secondary" type="button" @click="showCurriculumForm = false">{{ de.cancel }}</button><button class="btn btn-primary" :disabled="curriculumForm.processing">{{ de.saveChanges }}</button></div></form></div></div></section></div>
            <div v-if="showAddForm" class="roo-modal-backdrop" role="presentation" @click.self="showAddForm = false"><section class="roo-modal" role="dialog" aria-modal="true" :aria-label="de.addUnit"><div class="card border-0"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">{{ de.addUnit }}</h2><button class="btn-close" type="button" :aria-label="de.close" @click="showAddForm = false"></button></div><form class="row g-3" @submit.prevent="addTopic"><div class="col-md-8"><label class="form-label">{{ de.unitTitle }}</label><input v-model="addForm.title" class="form-control" required></div><div class="col-md-4"><label class="form-label">{{ de.assignYear }}</label><select v-model="addForm.year" class="form-select"><option value="">{{ de.unassigned }}</option><option v-for="year in years" :key="year" :value="year">{{ yearLabel(year) }}</option></select></div><div class="col-md-4"><label class="form-label">{{ de.hours }}</label><input v-model="addForm.hours" type="number" min="0" class="form-control"></div><div class="col-12"><label class="form-label">{{ de.notes }}</label><textarea v-model="addForm.notes" class="form-control" rows="3"></textarea></div><div class="col-12 d-flex justify-content-end gap-2"><button class="btn btn-outline-secondary" type="button" @click="showAddForm = false">{{ de.cancel }}</button><button class="btn btn-primary" :disabled="addForm.processing">{{ de.addUnit }}</button></div></form></div></div></section></div>
            <div class="row g-3 flex-nowrap overflow-auto pb-3 mt-1">
                <section class="col-12 col-md-4 col-xl-2"><div class="card h-100"><div class="card-header">{{ de.unassigned }}</div><div class="card-body" @dragover.prevent @drop="drop($event, null)"><div v-for="(group, source) in unassignedGroups()" :key="source" class="mb-3"><h3 class="h6 border-bottom pb-1">{{ source }}</h3><article v-for="topic in group" :key="topic.id" class="card mb-2 shadow-sm" draggable="true" @dragstart="startDrag($event, topic)"><div class="card-body p-2"><strong class="small">{{ topic.number }}. {{ topic.title }}</strong><select class="form-select form-select-sm mt-2" :value="topic.year ?? ''" @change="move(topic, $event.target.value ? Number($event.target.value) : null)" :aria-label="de.assignYear"><option value="">{{ de.unassigned }}</option><option v-for="year in years" :key="year" :value="year">{{ yearLabel(year) }}</option></select><button v-if="version.is_editable" class="btn btn-link btn-sm px-0" @click="editingTopic = topic.id">{{ de.editUnit }}</button></div></article></div></div></div></section>
                <section v-for="year in years" :key="year" class="col-12 col-md-4 col-xl-2"><div class="card h-100" @dragover.prevent @drop="drop($event, year)"><div class="card-header">{{ yearLabel(year) }} <span class="badge text-bg-secondary">{{ topics(year).length }}</span></div><div class="card-body"><article v-for="topic in topics(year)" :key="topic.id" class="card mb-2 shadow-sm" draggable="true" @dragstart="startDrag($event, topic)"><div class="card-body p-2"><strong class="small">{{ topic.number }}. {{ topic.title }}</strong><select class="form-select form-select-sm mt-2" :value="topic.year" @change="move(topic, $event.target.value ? Number($event.target.value) : null)" :aria-label="de.assignYear"><option value="">{{ de.unassigned }}</option><option v-for="target in years" :key="target" :value="target">{{ yearLabel(target) }}</option></select><button v-if="version.is_editable" class="btn btn-link btn-sm px-0" @click="editingTopic = topic.id">{{ de.editUnit }}</button></div></article></div></div></section>
            </div>
            <div v-if="version.is_editable && editingTopic" class="roo-modal-backdrop" role="presentation" @click.self="editingTopic = null"><section class="roo-modal" role="dialog" aria-modal="true" :aria-label="de.editUnit"><div class="card border-0"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">{{ de.editUnit }}</h2><button class="btn-close" type="button" :aria-label="de.close" @click="editingTopic = null"></button></div><form @submit.prevent="saveTopic(version.topics.find(topic => topic.id === editingTopic))"><div class="row g-3"><div class="col-md-8"><label class="form-label">{{ de.unitTitle }}</label><input v-model="topicForms[editingTopic].title" class="form-control" required></div><div class="col-md-4"><label class="form-label">{{ de.hours }}</label><input v-model="topicForms[editingTopic].hours" type="number" min="0" class="form-control"></div><div class="col-12"><label class="form-label">{{ de.preparationQuestions }}</label><textarea v-model="topicForms[editingTopic].preparation_questions" class="form-control" rows="4"></textarea></div><div class="col-12"><label class="form-label">{{ de.notes }}</label><textarea v-model="topicForms[editingTopic].notes" class="form-control" rows="3"></textarea></div></div><div class="mt-3 d-flex justify-content-end gap-2"><button class="btn btn-outline-secondary" type="button" @click="editingTopic = null">{{ de.cancel }}</button><button class="btn btn-primary">{{ de.saveChanges }}</button></div></form></div></div></section></div>
        </div>
    </AppShell>
</template>
