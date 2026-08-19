<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import AttachmentList from '../../Components/Ui/AttachmentList.vue'
import LessonEditorModal from '../../Components/Planning/LessonEditorModal.vue'
import LessonPhasesTab from '../../Components/Planning/LessonPhasesTab.vue'
import de from '../../i18n/de'
import { onMounted, onUnmounted, ref } from 'vue'
import { requestConfirmation } from '../../utils/confirmation'
import { router, useForm } from '@inertiajs/vue3'

const props = defineProps({ slot: Object, group: Object, lesson: Object, unit: Object, phaseTemplates: Array, socialForms: Array, materialItems: { type: Array, default: () => [] }, resourceLinks: { type: Array, default: () => [] }, competencyOptions: Array, lessonTemplates: Array, targetCompetencies: { type: Object, default: () => ({ process: [], content: [] }) } })
const activeView = ref('planning')
const editorOpen = ref(false)
const phaseDraft = ref((props.lesson.phases ?? []).map(phase => ({ ...phase })))
const resourceLinks = ref((props.resourceLinks ?? []).map(link => ({ ...link })))
const resourceMaterialItems = ref((props.materialItems ?? []).map(item => ({ ...item })))
const deletedResourceLinkIds = ref([])
const deletedMaterialItemIds = ref([])
const saveProcessing = ref(false)
const now = ref(new Date())
let clockTimer
const toastMessages = ref([])
let toastId = 0
const executionForm = useForm({ status: props.slot.scheduled_lesson.status, actual_on: props.slot.scheduled_lesson.actual_on ?? '', execution_notes: props.slot.scheduled_lesson.execution_notes ?? '' })
onMounted(() => { clockTimer = window.setInterval(() => { now.value = new Date() }, 1000) })
onUnmounted(() => window.clearInterval(clockTimer))
const competencyText = competency => competency.competency_presentation?.label || competency.competency_presentation?.text || competency.text || competency.display || de.noCompetencyText
const targetCompetencyText = competency => competency.label || competency.text || de.noCompetencyText
const formatDate = value => new Date(`${String(value).slice(0, 10)}T12:00:00`).toLocaleDateString('de-DE', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' })
function savePlanning() {
    if (saveProcessing.value) return
    saveProcessing.value = true
    try {
        const phases = (Array.isArray(phaseDraft.value) ? phaseDraft.value : []).map(phase => ({
            ...phase,
            social_form: typeof phase.social_form === 'object' ? phase.social_form?.name ?? '' : (phase.social_form ?? phase.socialForm?.name ?? ''),
        }))
        router.put(`/jahresplanung/${props.group.id}/lessons/${props.lesson.id}`, { title: props.lesson.title, duration: props.lesson.duration, learning_goals: props.lesson.learning_goals, materials: props.lesson.materials, homework: props.lesson.homework, assessment_note: props.lesson.assessment_note, notes: props.lesson.notes, phases, resource_links: resourceLinks.value, material_items: resourceMaterialItems.value, deleted_resource_link_ids: deletedResourceLinkIds.value, deleted_material_item_ids: deletedMaterialItemIds.value }, {
            preserveScroll: true,
            onSuccess: () => addToast('success', 'Stunde wurde gespeichert.'),
            onError: errors => addToast('error', Object.values(errors)[0] || 'Die Stunde konnte nicht gespeichert werden.'),
            onFinish: () => { saveProcessing.value = false },
        })
    } catch (error) {
        saveProcessing.value = false
        addToast('error', error instanceof Error ? error.message : 'Die Stunde konnte nicht gespeichert werden.')
    }
}
function saveExecution() { executionForm.put(`/unterricht/${props.slot.id}/durchfuehrung`, { preserveScroll: true }) }
function refreshResources(page) {
    if (page?.props?.resourceLinks) resourceLinks.value = page.props.resourceLinks.map(link => ({ ...link }))
    if (page?.props?.materialItems) resourceMaterialItems.value = page.props.materialItems.map(item => ({ ...item }))
    if (page?.props?.lesson?.resources) props.lesson.resources = page.props.lesson.resources
}
function markConducted() { executionForm.status = 'conducted'; if (!executionForm.actual_on) executionForm.actual_on = String(props.slot.date).slice(0, 10); saveExecution() }
function addToast(type, message) { const id = ++toastId; toastMessages.value.push({ id, type, message }); window.setTimeout(() => { toastMessages.value = toastMessages.value.filter(toast => toast.id !== id) }, 5000) }
function updateResourceDescription(resource, description) { useForm({ description }).put(`/jahresplanung/${props.group.id}/eigene-einheiten/${props.unit.id}/anhaenge/${resource.id}`, { preserveScroll: true, onSuccess: () => { resource.description = description } }) }
async function deleteResource(resource) { if (await requestConfirmation({ message: de.deleteAttachmentConfirm })) router.delete(`/jahresplanung/${props.group.id}/eigene-einheiten/${props.unit.id}/anhaenge/${resource.id}`, { preserveScroll: true, onSuccess: () => { props.lesson.resources = (props.lesson.resources ?? []).filter(item => item.id !== resource.id) } }) }
const statusLabel = status => ({ assigned: de.lessonStatusAssigned, planned: de.lessonStatusPlanned, ready: de.lessonStatusReady, conducted: de.lessonStatusConducted, cancelled: de.cancelled, postponed: de.postponed }[status] ?? status)
const phaseMinutes = phase => Number(phase.duration_minutes || 0)
const plannedMinutes = () => (props.lesson.phases ?? []).reduce((sum, phase) => sum + phaseMinutes(phase), 0) || Number(props.lesson.duration || 1) * 45
const lessonStart = () => { const date = String(props.slot.date).slice(0, 10); const time = String(props.slot.starts_at || '08:00').slice(0, 5); return new Date(`${date}T${time}:00`) }
const elapsedSeconds = () => Math.floor((now.value.getTime() - lessonStart().getTime()) / 1000)
const formatTimer = seconds => { const sign = seconds < 0 ? '-' : ''; const absolute = Math.abs(Math.round(seconds)); return `${sign}${String(Math.floor(absolute / 60)).padStart(2, '0')}:${String(absolute % 60).padStart(2, '0')}` }
const currentPhaseIndex = () => { let elapsed = Math.max(0, elapsedSeconds() / 60); return Math.min(Math.max((props.lesson.phases ?? []).findIndex(phase => { elapsed -= phaseMinutes(phase); return elapsed < 0 }) || 0, 0), Math.max((props.lesson.phases ?? []).length - 1, 0)) }
const currentPhaseTimer = () => { const phases = props.lesson.phases ?? []; const index = currentPhaseIndex(); const before = phases.slice(0, index).reduce((sum, phase) => sum + phaseMinutes(phase), 0); return phaseMinutes(phases[index]) * 60 - (elapsedSeconds() - before * 60) }
const fileDownloadUrl = resource => `/jahresplanung/${props.group.id}/eigene-einheiten/${props.unit.id}/anhaenge/${resource.id}/download`
const filePreviewUrl = resource => `/jahresplanung/${props.group.id}/eigene-einheiten/${props.unit.id}/anhaenge/${resource.id}/preview`
</script>

<template>
    <AppShell>
        <div class="planning-toast-container" aria-live="polite" aria-atomic="true"><div v-for="toast in toastMessages" :key="toast.id" class="planning-toast" :class="`planning-toast-${toast.type}`" role="alert"><span>{{ toast.message }}</span><button class="btn-close btn-close-white ms-3" type="button" :aria-label="de.close" @click="toastMessages = toastMessages.filter(item => item.id !== toast.id)"></button></div></div>
        <template #toolbar>
            <a href="/dashboard" class="btn btn-sm btn-light" :title="de.close" :aria-label="de.close"><i class="bi bi-x-lg" aria-hidden="true"></i></a>
            <button v-if="activeView === 'planning'" class="btn btn-sm btn-primary ms-2" type="button" @click="savePlanning"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ de.saveChanges }}</button>
            <template v-else-if="activeView === 'execution'"><button class="btn btn-sm btn-success ms-2" type="button" :disabled="executionForm.processing" @click="markConducted"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>{{ de.markConducted }}</button><button class="btn btn-sm btn-primary ms-2" type="button" :disabled="executionForm.processing" @click="saveExecution"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ de.saveChanges }}</button></template>
            <div class="btn-group btn-group-sm" role="tablist" :aria-label="de.lessonViews">
                <button v-for="view in [{ id: 'planning', label: de.lessonPlanning }, { id: 'execution', label: de.lessonExecution }, { id: 'observation', label: de.lessonObservation }]" :key="view.id" class="btn" :class="activeView === view.id ? 'btn-primary' : 'btn-outline-secondary'" type="button" role="tab" :aria-selected="activeView === view.id" @click="activeView = view.id">{{ view.label }}</button>
            </div>
        </template>
        <div class="container-full px-3 py-4">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-4"><div><h1 class="h2 mb-1">{{ lesson.title }}</h1><div class="text-muted">{{ formatDate(slot.date) }} · {{ slot.period_number }}. {{ de.period }} · {{ group.name }}</div></div><span class="badge text-bg-primary align-self-start">{{ statusLabel(slot.scheduled_lesson.status) }}</span></div>

            <section v-if="activeView === 'planning'" aria-labelledby="planning-heading">
                <h2 id="planning-heading" class="visually-hidden">{{ de.lessonPlanning }}</h2>
                <div class="row g-4 mb-4">
                    <div class="col-lg-6"><article class="card h-100"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><h2 class="h5">{{ de.lessonMetadata }}</h2><button class="btn btn-sm btn-outline-secondary" type="button" @click="editorOpen = true"><i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ de.editLesson }}</button></div><dl class="row mb-0 small"><dt class="col-sm-5">{{ de.unit }}</dt><dd class="col-sm-7">{{ unit.title }}</dd><dt class="col-sm-5">{{ de.lessonDuration }}</dt><dd class="col-sm-7">{{ lesson.duration }} {{ de.hours.toLowerCase() }}</dd><dt class="col-sm-5">{{ de.learningGoals }}</dt><dd class="col-sm-7 text-pre-wrap">{{ lesson.learning_goals || '–' }}</dd></dl><div class="row g-3 mt-2"><div class="col-md-6"><h3 class="h6">{{ de.processCompetencies }}</h3><ul v-if="targetCompetencies.process.length" class="small mb-0 ps-3"><li v-for="competency in targetCompetencies.process" :key="competency.id">{{ targetCompetencyText(competency) }}</li></ul><p v-else class="small text-muted mb-0">{{ de.noCompetencies }}</p></div><div class="col-md-6"><h3 class="h6">{{ de.contentCompetencies }}</h3><ul v-if="targetCompetencies.content.length" class="small mb-0 ps-3"><li v-for="competency in targetCompetencies.content" :key="competency.id">{{ targetCompetencyText(competency) }}</li></ul><p v-else class="small text-muted mb-0">{{ de.noCompetencies }}</p></div></div></div></article></div>
                    <div class="col-lg-6"><article class="card h-100"><div class="card-body"><h2 class="h5">{{ de.materials }}</h2><AttachmentList :resources="lesson.resources ?? []" :resource-links="resourceLinks" :material-items="resourceMaterialItems" :material-text="lesson.materials" :manage="true" :library-attach-url="'/jahresplanung/' + group.id + '/ressourcen'" :library-target-type="'lesson'" :library-target-id="lesson.id" :upload-url="`/jahresplanung/${group.id}/eigene-einheiten/${unit.id}/anhaenge`" :upload-lesson-id="lesson.id" :download-base-url="`/jahresplanung/${group.id}/eigene-einheiten/${unit.id}/anhaenge`" @update="updateResourceDescription" @delete="deleteResource" @uploaded="refreshResources" @update:resource-links="resourceLinks = $event" @update:material-items="resourceMaterialItems = $event" @delete:resource-link="deletedResourceLinkIds.push($event.id)" @delete:material-item="deletedMaterialItemIds.push($event.id)" @error="addToast('error', $event)" /></div></article></div>
                </div>
                <article class="card planning-phases-workspace"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h4 mb-1">{{ de.phases }}</h2><p class="text-muted mb-0">{{ de.lessonPhasesWorkspaceIntro }}</p></div><span class="badge text-bg-light">{{ phaseDraft.length }} {{ de.phases.toLowerCase() }}</span></div><LessonPhasesTab :lesson="lesson" :phases="phaseDraft" :group-id="group.id" :phase-templates="phaseTemplates" :social-forms="socialForms" :resources="lesson.resources ?? []" :resource-links="resourceLinks" :material-items="resourceMaterialItems" @update:phases="phaseDraft = $event" /></div></article>
            </section>
            <section v-else-if="activeView === 'execution'" aria-labelledby="execution-heading">
                <div class="row g-4">
                    <div class="col-lg-4"><article class="card mb-4"><div class="card-body"><h2 id="execution-heading" class="h5">{{ de.lessonExecution }}</h2><div class="display-5 font-monospace mb-3">{{ now.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) }}</div><dl class="row small mb-0"><dt class="col-7">{{ de.lessonElapsed }}</dt><dd class="col-5 text-end font-monospace">{{ formatTimer(elapsedSeconds()) }}</dd><dt class="col-7">{{ de.lessonRemaining }}</dt><dd class="col-5 text-end font-monospace">{{ formatTimer(plannedMinutes() * 60 - elapsedSeconds()) }}</dd><dt class="col-7">{{ de.phaseRemaining }}</dt><dd class="col-5 text-end font-monospace">{{ formatTimer(currentPhaseTimer()) }}</dd><dt class="col-7">{{ de.plannedDuration }}</dt><dd class="col-5 text-end">{{ plannedMinutes() }} {{ de.minutes }}</dd></dl><label class="form-label mt-3" for="execution-date">{{ de.actualDate }}</label><input id="execution-date" v-model="executionForm.actual_on" class="form-control" type="date"><label class="form-label mt-3" for="execution-notes">{{ de.executionNotes }}</label><textarea id="execution-notes" v-model="executionForm.execution_notes" class="form-control" rows="8"></textarea></div></article></div>
                    <div class="col-lg-8"><article class="card"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">{{ de.lessonPhasesExecution }}</h2><span class="small text-muted">{{ currentPhaseIndex() + 1 }} / {{ lesson.phases.length || 0 }}</span></div><div v-if="lesson.phases.length" class="accordion" :id="`lesson-phases-${lesson.id}`"><div v-for="(phase, index) in lesson.phases" :key="phase.id" class="accordion-item"><h3 class="accordion-header"><button class="accordion-button" :class="{ collapsed: currentPhaseIndex() !== index }" type="button" data-bs-toggle="collapse" :data-bs-target="`#lesson-phase-${phase.id}`">{{ index + 1 }}. {{ phase.title }} <span v-if="phase.duration_minutes" class="ms-auto me-3 small">{{ phase.duration_minutes }} {{ de.minutes }}</span></button></h3><div :id="`lesson-phase-${phase.id}`" class="accordion-collapse collapse" :class="{ show: currentPhaseIndex() === index }"><div class="accordion-body small"><dl class="row mb-3"><dt v-if="phase.social_form" class="col-sm-4">{{ de.socialForm }}</dt><dd v-if="phase.social_form" class="col-sm-8">{{ phase.social_form.name || phase.social_form }}</dd><dt v-if="phase.teacher_interaction" class="col-sm-4">{{ de.teacherInteraction }}</dt><dd v-if="phase.teacher_interaction" class="col-sm-8 text-pre-wrap">{{ phase.teacher_interaction }}</dd><dt v-if="phase.learner_activity" class="col-sm-4">{{ de.learnerActivity }}</dt><dd v-if="phase.learner_activity" class="col-sm-8 text-pre-wrap">{{ phase.learner_activity }}</dd><dt v-if="phase.differentiation" class="col-sm-4">{{ de.differentiation }}</dt><dd v-if="phase.differentiation" class="col-sm-8 text-pre-wrap">{{ phase.differentiation }}</dd><dt v-if="phase.didactic_comment" class="col-sm-4">{{ de.didacticComment }}</dt><dd v-if="phase.didactic_comment" class="col-sm-8 text-pre-wrap">{{ phase.didactic_comment }}</dd><dt v-if="phase.materials" class="col-sm-4">{{ de.materials }}</dt><dd v-if="phase.materials" class="col-sm-8 text-pre-wrap">{{ phase.materials }}</dd><dt v-if="phase.media" class="col-sm-4">{{ de.media }}</dt><dd v-if="phase.media" class="col-sm-8 text-pre-wrap">{{ phase.media }}</dd></dl><div v-if="phase.resources?.length || phase.resource_links?.length || phase.material_items?.length"><h4 class="h6">{{ de.resources }}</h4><div class="list-group"> <div v-for="resource in phase.resources" :key="`file-${resource.id}`" class="list-group-item d-flex justify-content-between align-items-center"><span>{{ resource.display_name || resource.original_name }}</span><span class="d-flex gap-1"><a class="btn btn-sm btn-outline-secondary" :href="filePreviewUrl(resource)" target="_blank" :title="de.preview"><i class="bi bi-eye" aria-hidden="true"></i></a><a class="btn btn-sm btn-outline-secondary" :href="fileDownloadUrl(resource)" :title="de.download"><i class="bi bi-download" aria-hidden="true"></i></a></span></div><a v-for="link in phase.resource_links" :key="`link-${link.id}`" class="list-group-item" :href="link.url" target="_blank" rel="noopener">{{ link.title }}</a><div v-for="item in phase.material_items" :key="`material-${item.id}`" class="list-group-item">{{ item.name }}</div></div></div></div></div></div></div><p v-else class="text-muted mb-0">{{ de.noPhases }}</p></div></article></div>
                </div>
            </section>
            <section v-else class="card"><div class="card-body"><h2 class="h4">{{ de.lessonObservation }}</h2><p class="text-muted">{{ de.lessonObservationComingSoon }}</p></div></section>
        </div>
        <LessonEditorModal v-if="editorOpen" :lesson="lesson" :unit="unit" :group-id="group.id" :competency-options="competencyOptions" :competency-text="competencyText" :phase-templates="phaseTemplates" :social-forms="socialForms" :scheduled-lesson="slot.scheduled_lesson" :execution-url="`/unterricht/${slot.id}/durchfuehrung`" :show-phases="false" :show-resources="false" @close="editorOpen = false" />
    </AppShell>
</template>
