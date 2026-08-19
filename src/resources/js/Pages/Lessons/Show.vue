<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import AttachmentList from '../../Components/Ui/AttachmentList.vue'
import LessonEditorModal from '../../Components/Planning/LessonEditorModal.vue'
import LessonPhasesTab from '../../Components/Planning/LessonPhasesTab.vue'
import de from '../../i18n/de'
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({ slot: Object, group: Object, lesson: Object, unit: Object, phaseTemplates: Array, socialForms: Array, competencyOptions: Array, lessonTemplates: Array, targetCompetencies: { type: Object, default: () => ({ process: [], content: [] }) } })
const activeView = ref('planning')
const editorOpen = ref(false)
const phaseDraft = ref((props.lesson.phases ?? []).map(phase => ({ ...phase })))
const saveForm = useForm({})
const executionMode = ref('teacher')
const currentPhase = ref(0)
const checkedMaterials = ref([])
const executionForm = useForm({ status: props.slot.scheduled_lesson.status, actual_on: props.slot.scheduled_lesson.actual_on ?? '', execution_notes: props.slot.scheduled_lesson.execution_notes ?? '' })
const competencyText = competency => competency.text ?? competency.display ?? de.noCompetencyText
const targetCompetencyText = competency => competency.text || de.noCompetencyText
const formatDate = value => new Date(`${String(value).slice(0, 10)}T12:00:00`).toLocaleDateString('de-DE', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' })
function savePlanning() {
    saveForm.transform(() => ({ title: props.lesson.title, duration: props.lesson.duration, learning_goals: props.lesson.learning_goals, materials: props.lesson.materials, homework: props.lesson.homework, assessment_note: props.lesson.assessment_note, notes: props.lesson.notes, phases: phaseDraft.value })).put(`/jahresplanung/${props.group.id}/lessons/${props.lesson.id}`, { preserveScroll: true })
}
function saveExecution() { executionForm.put(`/unterricht/${props.slot.id}/durchfuehrung`, { preserveScroll: true }) }
function markConducted() { executionForm.status = 'conducted'; if (!executionForm.actual_on) executionForm.actual_on = String(props.slot.date).slice(0, 10); saveExecution() }
const statusLabel = status => ({ assigned: de.lessonStatusAssigned, planned: de.lessonStatusPlanned, ready: de.lessonStatusReady, conducted: de.lessonStatusConducted, cancelled: de.cancelled, postponed: de.postponed }[status] ?? status)
const materialItems = computed(() => [...String(props.lesson.materials ?? '').split('\n'), ...(props.lesson.phases ?? []).flatMap(phase => String(phase.materials ?? '').split('\n'))].map(item => item.trim()).filter(Boolean).filter((item, index, all) => all.indexOf(item) === index))
</script>

<template>
    <AppShell>
        <template #toolbar>
            <div class="btn-group btn-group-sm" role="tablist" :aria-label="de.lessonViews">
                <button v-for="view in [{ id: 'planning', label: de.lessonPlanning }, { id: 'execution', label: de.lessonExecution }, { id: 'observation', label: de.lessonObservation }]" :key="view.id" class="btn" :class="activeView === view.id ? 'btn-primary' : 'btn-outline-secondary'" type="button" role="tab" :aria-selected="activeView === view.id" @click="activeView = view.id">{{ view.label }}</button>
            </div>
            <button v-if="activeView === 'planning'" class="btn btn-sm btn-primary ms-2" type="button" :disabled="saveForm.processing" @click="savePlanning"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ de.saveChanges }}</button>
            <button v-else-if="activeView === 'execution'" class="btn btn-sm btn-primary ms-2" type="button" :disabled="executionForm.processing" @click="saveExecution"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ de.saveChanges }}</button>
        </template>
        <div class="container-full px-3 py-4">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-4"><div><h1 class="h2 mb-1">{{ lesson.title }}</h1><div class="text-muted">{{ formatDate(slot.date) }} · {{ slot.period_number }}. {{ de.period }} · {{ group.name }}</div></div><span class="badge text-bg-primary align-self-start">{{ statusLabel(slot.scheduled_lesson.status) }}</span></div>

            <section v-if="activeView === 'planning'" aria-labelledby="planning-heading">
                <h2 id="planning-heading" class="visually-hidden">{{ de.lessonPlanning }}</h2>
                <div class="row g-4 mb-4">
                    <div class="col-lg-5"><article class="card h-100"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><h2 class="h5">{{ de.lessonMetadata }}</h2><button class="btn btn-sm btn-outline-secondary" type="button" @click="editorOpen = true"><i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ de.editLesson }}</button></div><dl class="row mb-0 small"><dt class="col-sm-5">{{ de.unit }}</dt><dd class="col-sm-7">{{ unit.title }}</dd><dt class="col-sm-5">{{ de.lessonDuration }}</dt><dd class="col-sm-7">{{ lesson.duration }} {{ de.hours.toLowerCase() }}</dd><dt class="col-sm-5">{{ de.learningGoals }}</dt><dd class="col-sm-7 text-pre-wrap">{{ lesson.learning_goals || '–' }}</dd></dl><div class="row g-3 mt-2"><div class="col-md-6"><h3 class="h6">{{ de.processCompetencies }}</h3><ul v-if="targetCompetencies.process.length" class="small mb-0 ps-3"><li v-for="competency in targetCompetencies.process" :key="competency.id">{{ targetCompetencyText(competency) }}</li></ul><p v-else class="small text-muted mb-0">{{ de.noCompetencies }}</p></div><div class="col-md-6"><h3 class="h6">{{ de.contentCompetencies }}</h3><ul v-if="targetCompetencies.content.length" class="small mb-0 ps-3"><li v-for="competency in targetCompetencies.content" :key="competency.id">{{ targetCompetencyText(competency) }}</li></ul><p v-else class="small text-muted mb-0">{{ de.noCompetencies }}</p></div></div></div></article></div>
                    <div class="col-lg-7"><article class="card h-100"><div class="card-body"><div class="row g-4"><div class="col-lg-7"><h2 class="h5">{{ de.materials }}</h2><p v-if="lesson.materials" class="text-pre-wrap">{{ lesson.materials }}</p><p v-else class="small text-muted">{{ de.noMaterials }}</p></div><div class="col-lg-5"><h3 class="h6">{{ de.attachments }}</h3><AttachmentList :resources="unit.resources ?? []" :download-base-url="`/jahresplanung/${group.id}/eigene-einheiten/${unit.id}/anhaenge`" /></div></div></div></article></div>
                </div>
                <article class="card planning-phases-workspace"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h4 mb-1">{{ de.phases }}</h2><p class="text-muted mb-0">{{ de.lessonPhasesWorkspaceIntro }}</p></div><span class="badge text-bg-light">{{ phaseDraft.length }} {{ de.phases.toLowerCase() }}</span></div><LessonPhasesTab :lesson="lesson" :phases="phaseDraft" :group-id="group.id" :phase-templates="phaseTemplates" :social-forms="socialForms" @update:phases="phaseDraft = $event" /></div></article>
            </section>
            <section v-else-if="activeView === 'execution'" aria-labelledby="execution-heading">
                <div class="row g-4">
                    <div class="col-lg-4"><article class="card"><div class="card-body"><h2 id="execution-heading" class="h5">{{ de.lessonExecution }}</h2><label class="form-label" for="execution-status">{{ de.lessonStatus }}</label><select id="execution-status" v-model="executionForm.status" class="form-select"><option v-for="status in ['assigned', 'planned', 'ready', 'conducted', 'cancelled', 'postponed']" :key="status" :value="status">{{ statusLabel(status) }}</option></select><label class="form-label mt-3" for="execution-date">{{ de.actualDate }}</label><input id="execution-date" v-model="executionForm.actual_on" class="form-control" type="date"><label class="form-label mt-3" for="execution-notes">{{ de.executionNotes }}</label><textarea id="execution-notes" v-model="executionForm.execution_notes" class="form-control" rows="8"></textarea><button class="btn btn-success w-100 mt-3" type="button" :disabled="executionForm.processing" @click="markConducted"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>{{ de.markConducted }}</button></div></article></div>
                    <div class="col-lg-8"><article class="card mb-4"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">{{ de.lessonPhasesExecution }}</h2><div class="btn-group btn-group-sm"><button class="btn" :class="executionMode === 'teacher' ? 'btn-primary' : 'btn-outline-secondary'" type="button" @click="executionMode = 'teacher'">{{ de.teacherView }}</button><button class="btn" :class="executionMode === 'presentation' ? 'btn-primary' : 'btn-outline-secondary'" type="button" @click="executionMode = 'presentation'">{{ de.presentationView }}</button></div></div><div v-if="executionMode === 'teacher'" class="list-group"><button v-for="(phase, index) in lesson.phases" :key="phase.id" class="list-group-item list-group-item-action text-start" :class="{ active: currentPhase === index }" type="button" @click="currentPhase = index"><div class="d-flex justify-content-between"><strong>{{ index + 1 }}. {{ phase.title }}</strong><span v-if="phase.duration_minutes">{{ phase.duration_minutes }} {{ de.minutes }}</span></div><div class="small mt-1">{{ phase.description || de.noDescription }}</div><div v-if="phase.materials" class="small mt-1"><i class="bi bi-box-seam me-1" aria-hidden="true"></i>{{ phase.materials }}</div></button></div><div v-else class="presentation-view"><div v-if="lesson.phases[currentPhase]" class="text-center py-5"><div class="display-6 mb-3">{{ currentPhase + 1 }} / {{ lesson.phases.length }}</div><h3 class="display-5">{{ lesson.phases[currentPhase].title }}</h3><p class="lead text-pre-wrap mt-4">{{ lesson.phases[currentPhase].description || de.noDescription }}</p><div class="d-flex justify-content-center gap-2 mt-4"><button class="btn btn-outline-secondary" type="button" :disabled="currentPhase === 0" @click="currentPhase--">{{ de.previousPhase }}</button><button class="btn btn-primary" type="button" :disabled="currentPhase >= lesson.phases.length - 1" @click="currentPhase++">{{ de.nextPhase }}</button></div></div><p v-else class="text-muted">{{ de.noPhases }}</p></div></div></article><article class="card"><div class="card-body"><h2 class="h5">{{ de.materialChecklist }}</h2><div v-if="materialItems.length" class="list-group list-group-flush"><label v-for="item in materialItems" :key="item" class="list-group-item form-check"><input v-model="checkedMaterials" class="form-check-input me-2" type="checkbox" :value="item">{{ item }}</label></div><p v-else class="small text-muted mb-0">{{ de.noMaterials }}</p></div></article></div>
                </div>
            </section>
            <section v-else class="card"><div class="card-body"><h2 class="h4">{{ de.lessonObservation }}</h2><p class="text-muted">{{ de.lessonObservationComingSoon }}</p></div></section>
        </div>
        <LessonEditorModal v-if="editorOpen" :lesson="lesson" :unit="unit" :group-id="group.id" :competency-options="competencyOptions" :competency-text="competencyText" :phase-templates="phaseTemplates" :social-forms="socialForms" :show-phases="false" @close="editorOpen = false" />
    </AppShell>
</template>
