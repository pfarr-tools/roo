<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import AttachmentList from '../../Components/Ui/AttachmentList.vue'
import LessonEditorModal from '../../Components/Planning/LessonEditorModal.vue'
import LessonPhasesTab from '../../Components/Planning/LessonPhasesTab.vue'
import Tab from '../../Components/Ui/Tabs/Tab.vue'
import TabHeader from '../../Components/Ui/Tabs/TabHeader.vue'
import TabHeaders from '../../Components/Ui/Tabs/TabHeaders.vue'
import Tabs from '../../Components/Ui/Tabs/Tabs.vue'
import de from '../../i18n/de'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { requestConfirmation } from '../../utils/confirmation'
import { router, useForm } from '@inertiajs/vue3'

const props = defineProps({ slot: Object, group: Object, lesson: Object, unit: Object, groupLessons: { type: Array, default: () => [] }, groupCompetencyHours: { type: Object, default: () => ({}) }, phaseTemplates: Array, socialForms: Array, materialItems: { type: Array, default: () => [] }, songs: { type: Array, default: () => [] }, resourceLinks: { type: Array, default: () => [] }, competencyOptions: Array, lessonTemplates: Array, targetCompetencies: { type: Object, default: () => ({ process: [], content: [] }) }, observationStudents: { type: Array, default: () => [] }, observationTypes: { type: Array, default: () => [] }, attendanceRecords: { type: Array, default: () => [] }, observations: { type: Array, default: () => [] }, competenceEvidences: { type: Array, default: () => [] } })
const activeTab = ref('planning')
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
const previewSong = ref(null)
const showLessonSongPrintModal = ref(false)
const lessonSongPrintFormat = ref('a4')
const lessonSongPrintInstrument = ref('')
const lessonSongExporting = ref(false)
const executionForm = useForm({ status: props.slot.scheduled_lesson.status, actual_on: props.slot.scheduled_lesson.actual_on ?? '', execution_notes: props.slot.scheduled_lesson.execution_notes ?? '' })
const observationForm = useForm({ students: props.observationStudents.map(student => { const attendance = props.attendanceRecords.find(item => item.student_id === student.id); return { student_id: student.id, attendance: attendance?.status ?? 'present', note: attendance?.note ?? '', observation_type_ids: props.observations.filter(item => item.student_id === student.id).map(item => item.observation_type_id), evidences: props.competenceEvidences.filter(item => item.student_id === student.id).map(item => ({ competency_id: item.teaching_unit_competency_id, scale: item.scale ?? '', note: item.note ?? '' })) } }) })
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
    if (page?.props?.lesson?.songs) props.lesson.songs = page.props.lesson.songs
    if (page?.props?.lesson?.songbooks) props.lesson.songbooks = page.props.lesson.songbooks
    if (page?.props?.lesson?.phases) {
        props.lesson.phases = page.props.lesson.phases
        phaseDraft.value = page.props.lesson.phases.map(phase => ({ ...phase }))
    }
}
function markConducted() { executionForm.status = 'conducted'; if (!executionForm.actual_on) executionForm.actual_on = String(props.slot.date).slice(0, 10); saveExecution() }
function observationRow(student) { return observationForm.students.find(row => row.student_id === student.id) }
function toggleObservation(student, typeId) { const row = observationRow(student); row.observation_type_ids = row.observation_type_ids.includes(typeId) ? row.observation_type_ids.filter(id => id !== typeId) : [...row.observation_type_ids, typeId] }
function saveObservations() { observationForm.put(`/unterricht/${props.slot.id}/beobachtungen`, { preserveScroll: true, onSuccess: () => addToast('success', 'Beobachtungen wurden gespeichert.'), onError: errors => addToast('error', Object.values(errors)[0] || 'Die Beobachtungen konnten nicht gespeichert werden.') }) }
function addToast(type, message) { const id = ++toastId; toastMessages.value.push({ id, type, message }); window.setTimeout(() => { toastMessages.value = toastMessages.value.filter(toast => toast.id !== id) }, 5000) }
function updateResourceDescription(resource, description, copyrights) { useForm({ description, copyrights }).put(`/jahresplanung/${props.group.id}/eigene-einheiten/${props.unit.id}/anhaenge/${resource.id}`, { preserveScroll: true, onSuccess: () => { resource.description = description; resource.copyrights = copyrights } }) }
async function deleteResource(resource) { if (await requestConfirmation({ message: de.deleteAttachmentConfirm })) router.delete(`/jahresplanung/${props.group.id}/eigene-einheiten/${props.unit.id}/anhaenge/${resource.id}`, { preserveScroll: true, onSuccess: () => { props.lesson.resources = (props.lesson.resources ?? []).filter(item => item.id !== resource.id) } }) }
const statusLabel = status => ({ assigned: de.lessonStatusAssigned, planned: de.lessonStatusPlanned, ready: de.lessonStatusReady, conducted: de.lessonStatusConducted, cancelled: de.cancelled, postponed: de.postponed }[status] ?? status)
const phaseMinutes = phase => Number(phase.duration_minutes || 0)
const plannedMinutes = () => (props.lesson.phases ?? []).reduce((sum, phase) => sum + phaseMinutes(phase), 0) || Number(props.lesson.duration || 1) * 45
const lessonStart = () => { const date = String(props.slot.date).slice(0, 10); const time = String(props.slot.starts_at || '08:00').slice(0, 5); return new Date(`${date}T${time}:00`) }
const elapsedSeconds = () => Math.floor((now.value.getTime() - lessonStart().getTime()) / 1000)
const formatTimer = seconds => { const sign = seconds < 0 ? '-' : ''; const absolute = Math.abs(Math.round(seconds)); const days = Math.floor(absolute / 86400); const hours = Math.floor((absolute % 86400) / 3600); const minutes = Math.floor((absolute % 3600) / 60); const formattedHours = `${String(hours).padStart(2, '0')}:`; return `${sign}${days ? `${days}:` : ''}${days || hours ? formattedHours : ''}${String(minutes).padStart(2, '0')}:${String(absolute % 60).padStart(2, '0')}` }
const currentPhaseIndex = () => { let elapsed = Math.max(0, elapsedSeconds() / 60); return Math.min(Math.max((props.lesson.phases ?? []).findIndex(phase => { elapsed -= phaseMinutes(phase); return elapsed < 0 }) || 0, 0), Math.max((props.lesson.phases ?? []).length - 1, 0)) }
const currentPhaseTimer = () => { const phases = props.lesson.phases ?? []; const index = currentPhaseIndex(); const before = phases.slice(0, index).reduce((sum, phase) => sum + phaseMinutes(phase), 0); return phaseMinutes(phases[index]) * 60 - (elapsedSeconds() - before * 60) }
const fileDownloadUrl = resource => `/jahresplanung/${props.group.id}/eigene-einheiten/${props.unit.id}/anhaenge/${resource.id}/download`
const filePreviewUrl = resource => `/jahresplanung/${props.group.id}/eigene-einheiten/${props.unit.id}/anhaenge/${resource.id}/preview`
const songTitle = song => song.song?.title || song.title || song.name
const songCredits = song => {
    const author = song.song?.author?.trim()
    const composer = song.song?.composer?.trim()
    if (author && composer && author.toLowerCase() === composer.toLowerCase()) return `Text & Musik: ${author}`
    return [author && `Text: ${author}`, composer && `Musik: ${composer}`].filter(Boolean).join(' / ') || 'Keine Credits'
}
const songParts = song => song.parts?.length ? song.parts : (song.lyrics ? [{ title: '', content: song.lyrics, is_refrain: false }] : [])
const assignedLessonSongs = computed(() => {
    const initialIds = new Set((props.group.songbook?.entries ?? []).map(entry => entry.song_version_id))
    const songs = [...(props.lesson.songs ?? []), ...(props.lesson.phases ?? []).flatMap(phase => phase.songs ?? [])]
    return songs.filter((song, index, all) => !initialIds.has(song.id) && all.findIndex(item => item.id === song.id) === index)
})
async function printLessonSongs() {
    lessonSongExporting.value = true
    try {
        const instrument = lessonSongPrintInstrument.value.trim()
        const response = await fetch(`/unterricht/${props.slot.id}/lieder/export?format=${lessonSongPrintFormat.value}${lessonSongPrintFormat.value === 'chord-sheet' ? `&instrument=${encodeURIComponent(instrument)}` : ''}`, { headers: { Accept: 'application/pdf' } })
        if (!response.ok) throw new Error('Die neuen Lieder konnten nicht exportiert werden.')
        const url = URL.createObjectURL(await response.blob())
        const link = document.createElement('a')
        link.href = url
        link.download = `Neue-Lieder-Stunde-${String(props.slot.date).slice(0, 10)}-${lessonSongPrintFormat.value}.pdf`
        document.body.appendChild(link)
        link.click()
        link.remove()
        URL.revokeObjectURL(url)
        showLessonSongPrintModal.value = false
        addToast('success', 'Die neuen Lieder wurden als PDF heruntergeladen.')
    } catch (error) {
        addToast('error', error.message)
    } finally {
        lessonSongExporting.value = false
    }
}
</script>

<template>
    <AppShell>
        <div class="planning-toast-container" aria-live="polite" aria-atomic="true"><div v-for="toast in toastMessages" :key="toast.id" class="planning-toast" :class="`planning-toast-${toast.type}`" role="alert"><span>{{ toast.message }}</span><button class="btn-close btn-close-white ms-3" type="button" :aria-label="de.close" @click="toastMessages = toastMessages.filter(item => item.id !== toast.id)"></button></div></div>
        <template #toolbar>
            <a href="/dashboard" class="btn btn-sm btn-light" :title="de.close" :aria-label="de.close"><i class="bi bi-x-lg" aria-hidden="true"></i></a>
            <button v-if="activeTab === 'planning'" class="btn btn-sm btn-primary ms-2" type="button" @click="savePlanning"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ de.saveChanges }}</button>
            <template v-else-if="activeTab === 'execution'"><button class="btn btn-sm btn-success ms-2" type="button" :disabled="executionForm.processing" @click="markConducted"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>{{ de.markConducted }}</button><button class="btn btn-sm btn-primary ms-2" type="button" :disabled="executionForm.processing" @click="saveExecution"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ de.saveChanges }}</button></template>
            <button v-if="assignedLessonSongs.length" class="btn btn-sm btn-outline-primary ms-2" type="button" @click="showLessonSongPrintModal = true"><i class="bi bi-printer me-1" aria-hidden="true"></i>Neue Lieder drucken</button>
        </template>
        <div class="container-full px-3 py-4">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-4"><div><h1 class="h2 mb-1">{{ lesson.title }}</h1><div class="text-muted">{{ formatDate(slot.date) }} · {{ slot.period_number }}. {{ de.period }} · {{ group.name }}</div></div><span class="badge text-bg-primary align-self-start">{{ statusLabel(slot.scheduled_lesson.status) }}</span></div>

            <TabHeaders :aria-label="de.lessonViews">
                <TabHeader id="planning" :title="de.lessonPlanning" :active-tab="activeTab" icon="clipboard-check" @select="activeTab = $event" />
                <TabHeader id="execution" :title="de.lessonExecution" :active-tab="activeTab" icon="play-circle" @select="activeTab = $event" />
                <TabHeader id="observation" :title="de.lessonObservation" :active-tab="activeTab" icon="person-lines-fill" @select="activeTab = $event" />
            </TabHeaders>
            <Tabs :active-tab="activeTab">
            <Tab id="planning" :active-tab="activeTab">
                <h2 id="planning-heading" class="visually-hidden">{{ de.lessonPlanning }}</h2>
                <div class="row g-4 mb-4">
                    <div class="col-lg-6"><article class="card h-100"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><h2 class="h5">{{ de.lessonMetadata }}</h2><button class="btn btn-sm btn-outline-secondary" type="button" @click="editorOpen = true"><i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ de.editLesson }}</button></div><dl class="row mb-0 small"><dt class="col-sm-5">{{ de.unit }}</dt><dd class="col-sm-7">{{ unit.title }}</dd><dt class="col-sm-5">{{ de.lessonDuration }}</dt><dd class="col-sm-7">{{ lesson.duration }} {{ de.hours.toLowerCase() }}</dd><dt class="col-sm-5">{{ de.learningGoals }}</dt><dd class="col-sm-7 text-pre-wrap">{{ lesson.learning_goals || '–' }}</dd></dl><div class="row g-3 mt-2"><div class="col-md-6"><h3 class="h6">{{ de.processCompetencies }}</h3><ul v-if="targetCompetencies.process.length" class="small mb-0 ps-3"><li v-for="competency in targetCompetencies.process" :key="competency.id">{{ targetCompetencyText(competency) }}</li></ul><p v-else class="small text-muted mb-0">{{ de.noCompetencies }}</p></div><div class="col-md-6"><h3 class="h6">{{ de.contentCompetencies }}</h3><ul v-if="targetCompetencies.content.length" class="small mb-0 ps-3"><li v-for="competency in targetCompetencies.content" :key="competency.id">{{ targetCompetencyText(competency) }}</li></ul><p v-else class="small text-muted mb-0">{{ de.noCompetencies }}</p></div></div></div></article></div>
                    <div class="col-lg-6"><article class="card h-100"><div class="card-body"><h2 class="h5">{{ de.materials }}</h2><AttachmentList :resources="lesson.resources ?? []" :resource-links="resourceLinks" :material-items="resourceMaterialItems" :songs="lesson.songs ?? []" :songbooks="lesson.songbooks ?? []" :material-text="lesson.materials" :manage="true" :library-attach-url="'/jahresplanung/' + group.id + '/ressourcen'" :library-target-type="'lesson'" :library-target-id="lesson.id" :upload-url="`/jahresplanung/${group.id}/eigene-einheiten/${unit.id}/anhaenge`" :upload-lesson-id="lesson.id" :download-base-url="`/jahresplanung/${group.id}/eigene-einheiten/${unit.id}/anhaenge`" @update="updateResourceDescription" @delete="deleteResource" @uploaded="refreshResources" @update:resource-links="resourceLinks = $event" @update:material-items="resourceMaterialItems = $event" @delete:resource-link="deletedResourceLinkIds.push($event.id)" @delete:material-item="deletedMaterialItemIds.push($event.id)" @error="addToast('error', $event)" /></div></article></div>
                </div>
                <article class="card planning-phases-workspace"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h4 mb-1">{{ de.phases }}</h2><p class="text-muted mb-0">{{ de.lessonPhasesWorkspaceIntro }}</p></div><span class="badge text-bg-light">{{ phaseDraft.length }} {{ de.phases.toLowerCase() }}</span></div><div class="planning-phases-scroll"><LessonPhasesTab :lesson="lesson" :phases="phaseDraft" :group-id="group.id" :phase-templates="phaseTemplates" :social-forms="socialForms" :resources="lesson.resources ?? []" :resource-links="resourceLinks" :material-items="resourceMaterialItems" :songs="songs" @update:phases="phaseDraft = $event" /></div></div></article>
            </Tab>
            <Tab id="execution" :active-tab="activeTab">
                <div class="row g-4">
                    <div class="col-lg-4"><article class="card mb-4"><div class="card-body"><h2 id="execution-heading" class="h5">{{ de.lessonExecution }}</h2><div class="display-5 font-monospace mb-3">{{ now.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) }}</div><dl class="row small mb-0"><dt class="col-7">{{ de.lessonElapsed }}</dt><dd class="col-5 text-end font-monospace">{{ formatTimer(elapsedSeconds()) }}</dd><dt class="col-7">{{ de.lessonRemaining }}</dt><dd class="col-5 text-end font-monospace">{{ formatTimer(plannedMinutes() * 60 - elapsedSeconds()) }}</dd><dt class="col-7">{{ de.phaseRemaining }}</dt><dd class="col-5 text-end font-monospace">{{ formatTimer(currentPhaseTimer()) }}</dd><dt class="col-7">{{ de.plannedDuration }}</dt><dd class="col-5 text-end">{{ plannedMinutes() }} {{ de.minutes }}</dd></dl><label class="form-label mt-3" for="execution-notes">{{ de.executionNotes }}</label><textarea id="execution-notes" v-model="executionForm.execution_notes" class="form-control" rows="8"></textarea></div></article></div>
                    <div class="col-lg-8"><article class="card"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">{{ de.lessonPhasesExecution }}</h2><span class="small text-muted">{{ currentPhaseIndex() + 1 }} / {{ lesson.phases.length || 0 }}</span></div><div v-if="lesson.phases.length" class="accordion" :id="`lesson-phases-${lesson.id}`"><div v-for="(phase, index) in lesson.phases" :key="phase.id" class="accordion-item"><h3 class="accordion-header"><button class="accordion-button" :class="{ collapsed: currentPhaseIndex() !== index }" type="button" data-bs-toggle="collapse" :data-bs-target="`#lesson-phase-${phase.id}`">{{ index + 1 }}. {{ phase.title }} <span v-if="phase.duration_minutes" class="ms-auto me-3 small">{{ phase.duration_minutes }} {{ de.minutes }}</span></button></h3><div :id="`lesson-phase-${phase.id}`" class="accordion-collapse collapse" :class="{ show: currentPhaseIndex() === index }"><div class="accordion-body small"><dl class="row mb-3"><dt v-if="phase.social_form" class="col-sm-4">{{ de.socialForm }}</dt><dd v-if="phase.social_form" class="col-sm-8">{{ phase.social_form.name || phase.social_form }}</dd><dt v-if="phase.teacher_interaction" class="col-sm-4">{{ de.teacherInteraction }}</dt><dd v-if="phase.teacher_interaction" class="col-sm-8 text-pre-wrap">{{ phase.teacher_interaction }}</dd><dt v-if="phase.learner_activity" class="col-sm-4">{{ de.learnerActivity }}</dt><dd v-if="phase.learner_activity" class="col-sm-8 text-pre-wrap">{{ phase.learner_activity }}</dd><dt v-if="phase.differentiation" class="col-sm-4">{{ de.differentiation }}</dt><dd v-if="phase.differentiation" class="col-sm-8 text-pre-wrap">{{ phase.differentiation }}</dd><dt v-if="phase.didactic_comment" class="col-sm-4">{{ de.didacticComment }}</dt><dd v-if="phase.didactic_comment" class="col-sm-8 text-pre-wrap">{{ phase.didactic_comment }}</dd><dt v-if="phase.materials" class="col-sm-4">{{ de.materials }}</dt><dd v-if="phase.materials" class="col-sm-8 text-pre-wrap">{{ phase.materials }}</dd><dt v-if="phase.media" class="col-sm-4">{{ de.media }}</dt><dd v-if="phase.media" class="col-sm-8 text-pre-wrap">{{ phase.media }}</dd></dl><div v-if="phase.resources?.length || phase.resource_links?.length || phase.material_items?.length || phase.songs?.length"><h4 class="h6">{{ de.resources }}</h4><div class="list-group"> <div v-for="resource in phase.resources" :key="`file-${resource.id}`" class="list-group-item d-flex justify-content-between align-items-center"><span>{{ resource.display_name || resource.original_name }}</span><span class="d-flex gap-1"><a class="btn btn-sm btn-outline-secondary" :href="filePreviewUrl(resource)" target="_blank" :title="de.preview"><i class="bi bi-eye" aria-hidden="true"></i></a><a class="btn btn-sm btn-outline-secondary" :href="fileDownloadUrl(resource)" :title="de.download"><i class="bi bi-download" aria-hidden="true"></i></a></span></div><a v-for="link in phase.resource_links" :key="`link-${link.id}`" class="list-group-item" :href="link.url" target="_blank" rel="noopener">{{ link.title }}</a><div v-for="item in phase.material_items" :key="`material-${item.id}`" class="list-group-item">{{ item.name }}</div><div v-for="song in phase.songs" :key="`song-${song.id}`" class="list-group-item d-flex justify-content-between align-items-center"><span><i class="bi bi-music-note-beamed me-2" aria-hidden="true"></i><strong>{{ songTitle(song) }}</strong><span class="d-block small text-muted ms-4">{{ songCredits(song) }}</span></span><button class="btn btn-sm btn-outline-primary" type="button" title="Liedvorschau öffnen" aria-label="Liedvorschau öffnen" @click="previewSong = song"><i class="bi bi-eye" aria-hidden="true"></i></button></div></div></div></div></div></div></div><p v-else class="text-muted mb-0">{{ de.noPhases }}</p></div></article></div>
                </div>
            </Tab>
            <Tab id="observation" :active-tab="activeTab">
                <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 id="observation-heading" class="h4 mb-1">{{ de.lessonObservation }}</h2><p class="text-muted mb-0">Schnelle Nachweise für diese Stunde</p></div><button class="btn btn-primary" type="button" :disabled="observationForm.processing" @click="saveObservations"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>Beobachtungen speichern</button></div>
                <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0 observation-table"><thead><tr><th scope="col">Schüler:in</th><th scope="col">Anwesenheit</th><th v-for="type in observationTypes" :key="type.id" scope="col" class="text-center" :title="type.label">{{ type.symbol || type.label }}</th><th scope="col">Notiz</th></tr></thead><tbody><tr v-for="student in observationStudents" :key="student.id"><th scope="row"><span>{{ student.last_name }}, {{ student.first_name }}</span><small v-if="student.class_name" class="d-block text-muted">{{ student.class_name }}</small></th><td><select v-model="observationRow(student).attendance" class="form-select form-select-sm"><option value="present">anwesend</option><option value="late">verspätet</option><option value="absent">abwesend</option></select></td><td v-for="type in observationTypes" :key="type.id" class="text-center"><button type="button" class="btn btn-sm" :class="observationRow(student).observation_type_ids.includes(type.id) ? 'btn-primary' : 'btn-outline-secondary'" :aria-pressed="observationRow(student).observation_type_ids.includes(type.id)" :title="type.label" @click="toggleObservation(student, type.id)">{{ type.symbol || '✓' }}</button></td><td><input v-model="observationRow(student).note" class="form-control form-control-sm" maxlength="2000"></td></tr><tr v-if="!observationStudents.length"><td colspan="99" class="text-muted">Für diese Gruppe sind keine Schüler:innen erfasst.</td></tr></tbody></table></div></div>
            </Tab>
            </Tabs>
        </div>
        <div v-if="previewSong" class="roo-modal-backdrop" role="presentation" @click.self="previewSong = null"><section class="roo-modal song-execution-preview-modal" role="dialog" aria-modal="true" aria-labelledby="song-execution-preview-title"><div class="card border-0"><div class="card-body"><div class="d-flex justify-content-between align-items-start gap-3 mb-4"><div><h2 id="song-execution-preview-title" class="h3 mb-1">{{ songTitle(previewSong) }}</h2><div class="text-muted">{{ songCredits(previewSong) }}<span v-if="previewSong.name"> · {{ previewSong.name }}</span></div></div><button class="btn-close" type="button" aria-label="Schließen" @click="previewSong = null"></button></div><div v-if="songParts(previewSong).length" class="song-execution-text-preview"><section v-for="(part, index) in songParts(previewSong)" :key="part.id || index" class="mb-4"><h3 v-if="part.title" class="h5">{{ part.title }}</h3><p class="mb-0" :class="{ 'fw-semibold': part.is_refrain }">{{ part.content }}</p></section></div><p v-else class="text-muted mb-0">Für diese Liedfassung ist noch kein Liedtext hinterlegt.</p></div></div></section></div>
        <div v-if="showLessonSongPrintModal" class="roo-modal-backdrop" role="presentation" @click.self="showLessonSongPrintModal = false"><section class="roo-modal" role="dialog" aria-modal="true" aria-labelledby="lesson-song-print-title"><div class="card border-0"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 id="lesson-song-print-title" class="h5 mb-0">Neue Lieder dieser Stunde drucken</h2><button class="btn-close" type="button" aria-label="Schließen" @click="showLessonSongPrintModal = false"></button></div><p class="text-muted">Es werden nur neue Lieder dieser Stunde gedruckt.</p><fieldset class="mb-3"><legend class="h6">Format</legend><label class="form-check"><input v-model="lessonSongPrintFormat" class="form-check-input" type="radio" value="a4"><span class="form-check-label">A4 quer</span></label><label class="form-check"><input v-model="lessonSongPrintFormat" class="form-check-input" type="radio" value="a5"><span class="form-check-label">A5</span></label><label class="form-check"><input v-model="lessonSongPrintFormat" class="form-check-input" type="radio" value="chord-sheet"><span class="form-check-label">Akkordblatt (A4 hoch)</span></label></fieldset><div v-if="lessonSongPrintFormat === 'chord-sheet'" class="mb-3"><label class="form-label" for="lesson-song-print-instrument">Instrument</label><input id="lesson-song-print-instrument" v-model="lessonSongPrintInstrument" class="form-control" placeholder="z. B. Gitarre" required></div><div class="d-flex justify-content-end gap-2"><button class="btn btn-outline-secondary" type="button" @click="showLessonSongPrintModal = false">Abbrechen</button><button class="btn btn-primary" type="button" :disabled="lessonSongExporting || (lessonSongPrintFormat === 'chord-sheet' && !lessonSongPrintInstrument.trim())" @click="printLessonSongs"><span v-if="lessonSongExporting" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>{{ lessonSongExporting ? 'PDF wird erstellt …' : 'Druck starten' }}</button></div></div></div></section></div>
        <LessonEditorModal v-if="editorOpen" :lesson="lesson" :unit="unit" :group-lessons="groupLessons" :covered-hours="groupCompetencyHours" :group-id="group.id" :competency-options="competencyOptions" :competency-text="competencyText" :phase-templates="phaseTemplates" :social-forms="socialForms" :scheduled-lesson="slot.scheduled_lesson" :execution-url="`/unterricht/${slot.id}/durchfuehrung`" :show-phases="false" :show-resources="false" @close="editorOpen = false" />
    </AppShell>
</template>
