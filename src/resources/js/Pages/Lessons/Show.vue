<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import AttachmentList from '../../Components/Ui/AttachmentList.vue'
import LessonEditorModal from '../../Components/Planning/LessonEditorModal.vue'
import LessonPhasesTab from '../../Components/Planning/LessonPhasesTab.vue'
import de from '../../i18n/de'
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({ slot: Object, group: Object, lesson: Object, unit: Object, phaseTemplates: Array, socialForms: Array, competencyOptions: Array, lessonTemplates: Array })
const activeView = ref('planning')
const editorOpen = ref(false)
const phaseDraft = ref((props.lesson.phases ?? []).map(phase => ({ ...phase })))
const saveForm = useForm({})
const competencyText = competency => competency.text ?? competency.display ?? de.noCompetencyText
const formatDate = value => new Date(`${String(value).slice(0, 10)}T12:00:00`).toLocaleDateString('de-DE', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' })
function savePlanning() {
    saveForm.transform(() => ({ title: props.lesson.title, duration: props.lesson.duration, learning_goals: props.lesson.learning_goals, materials: props.lesson.materials, homework: props.lesson.homework, assessment_note: props.lesson.assessment_note, notes: props.lesson.notes, phases: phaseDraft.value })).put(`/jahresplanung/${props.group.id}/lessons/${props.lesson.id}`, { preserveScroll: true })
}
</script>

<template>
    <AppShell>
        <template #toolbar>
            <div class="btn-group btn-group-sm" role="tablist" :aria-label="de.lessonViews">
                <button v-for="view in [{ id: 'planning', label: de.lessonPlanning }, { id: 'execution', label: de.lessonExecution }, { id: 'observation', label: de.lessonObservation }]" :key="view.id" class="btn" :class="activeView === view.id ? 'btn-primary' : 'btn-outline-secondary'" type="button" role="tab" :aria-selected="activeView === view.id" @click="activeView = view.id">{{ view.label }}</button>
            </div>
            <button v-if="activeView === 'planning'" class="btn btn-sm btn-primary ms-2" type="button" :disabled="saveForm.processing" @click="savePlanning"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ de.saveChanges }}</button>
        </template>
        <div class="container-full px-3 py-4">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-4"><div><h1 class="h2 mb-1">{{ lesson.title }}</h1><div class="text-muted">{{ formatDate(slot.date) }} · {{ slot.period_number }}. {{ de.period }} · {{ group.name }}</div></div><span class="badge text-bg-primary align-self-start">{{ slot.scheduled_lesson.status }}</span></div>

            <section v-if="activeView === 'planning'" aria-labelledby="planning-heading">
                <h2 id="planning-heading" class="visually-hidden">{{ de.lessonPlanning }}</h2>
                <div class="row g-4 mb-4">
                    <div class="col-lg-5"><article class="card h-100"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><h2 class="h5">{{ de.lessonMetadata }}</h2><button class="btn btn-sm btn-outline-secondary" type="button" @click="editorOpen = true"><i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ de.editLesson }}</button></div><dl class="row mb-0 small"><dt class="col-sm-5">{{ de.unit }}</dt><dd class="col-sm-7">{{ unit.title }}</dd><dt class="col-sm-5">{{ de.lessonDuration }}</dt><dd class="col-sm-7">{{ lesson.duration }} {{ de.hours.toLowerCase() }}</dd><dt class="col-sm-5">{{ de.learningGoals }}</dt><dd class="col-sm-7 text-pre-wrap">{{ lesson.learning_goals || '–' }}</dd></dl></div></article></div>
                    <div class="col-lg-7"><article class="card h-100"><div class="card-body"><h2 class="h5">{{ de.materials }}</h2><p v-if="lesson.materials" class="text-pre-wrap">{{ lesson.materials }}</p><p v-else class="small text-muted">{{ de.noMaterials }}</p><AttachmentList :resources="unit.resources ?? []" :download-base-url="`/jahresplanung/${group.id}/eigene-einheiten/${unit.id}/anhaenge`" /></div></article></div>
                </div>
                <article class="card planning-phases-workspace"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h4 mb-1">{{ de.phases }}</h2><p class="text-muted mb-0">{{ de.lessonPhasesWorkspaceIntro }}</p></div><span class="badge text-bg-light">{{ phaseDraft.length }} {{ de.phases.toLowerCase() }}</span></div><LessonPhasesTab :lesson="lesson" :phases="phaseDraft" :group-id="group.id" :phase-templates="phaseTemplates" :social-forms="socialForms" @update:phases="phaseDraft = $event" /></div></article>
            </section>
            <section v-else-if="activeView === 'execution'" class="card"><div class="card-body"><h2 class="h4">{{ de.lessonExecution }}</h2><p class="text-muted">{{ de.lessonExecutionComingSoon }}</p></div></section>
            <section v-else class="card"><div class="card-body"><h2 class="h4">{{ de.lessonObservation }}</h2><p class="text-muted">{{ de.lessonObservationComingSoon }}</p></div></section>
        </div>
        <LessonEditorModal v-if="editorOpen" :lesson="lesson" :unit="unit" :group-id="group.id" :competency-options="competencyOptions" :competency-text="competencyText" :phase-templates="phaseTemplates" :social-forms="socialForms" :show-phases="false" @close="editorOpen = false" />
    </AppShell>
</template>
