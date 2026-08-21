<script setup>
import de from '../../i18n/de'
import AttachmentList from '../Ui/AttachmentList.vue'
import CompetencyPickerModal from './CompetencyPickerModal.vue'
import LessonPhasesTab from './LessonPhasesTab.vue'
import { router, useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({ lesson: Object, unit: Object, groupLessons: { type: Array, default: () => [] }, coveredHours: { type: Object, default: () => ({}) }, groupId: [String, Number], competencyOptions: Array, competencyText: Function, phaseTemplates: Array, socialForms: Array, scheduledLesson: { type: Object, default: null }, executionUrl: { type: String, default: '' }, materialItems: { type: Array, default: () => [] }, songs: { type: Array, default: () => [] }, resourceLinks: { type: Array, default: () => [] }, libraryResources: { type: Array, default: () => [] }, libraryResourceLinks: { type: Array, default: () => [] }, showPhases: { type: Boolean, default: true }, showResources: { type: Boolean, default: true } })
const emit = defineEmits(['close'])
const activeTab = ref('metadata')
const unitCompetencies = ref([])
const competencyPickerOpen = ref(false)
const competencyPickerApplying = ref(false)
const form = useForm({
    title: '',
    duration: 1,
    learning_goals: '',
    materials: '',
    homework: '',
    assessment_note: '',
    notes: '',
})
const competencyForm = useForm({ competency_ids: [] })
const phaseDraft = ref([])
const resourceLinksDraft = ref([])
const materialItemsDraft = ref([])
const deletedResourceLinkIds = ref([])
const deletedMaterialItemIds = ref([])
const preparationStatus = ref('')

function syncLesson(lesson) {
    if (!lesson) return
    form.defaults({
        title: lesson.title ?? '',
        duration: lesson.duration ?? 1,
        learning_goals: lesson.learning_goals ?? '',
        materials: lesson.materials ?? '',
        homework: lesson.homework ?? '',
        assessment_note: lesson.assessment_note ?? '',
        notes: lesson.notes ?? '',
    })
    form.reset()
    competencyForm.competency_ids = lesson.competencies?.map(competency => competency.id) ?? []
    phaseDraft.value = (lesson.phases ?? []).map(phase => ({ ...phase }))
    resourceLinksDraft.value = [...(lesson.resource_links ?? props.resourceLinks ?? props.unit?.resource_links ?? [])].map(link => ({ ...link }))
    materialItemsDraft.value = [...(lesson.material_items ?? props.materialItems ?? [])].map(item => ({ ...item }))
    unitCompetencies.value = [...(props.unit?.competencies ?? [])]
    preparationStatus.value = props.scheduledLesson?.status ?? ''
}

watch(() => props.lesson, syncLesson, { immediate: true })
watch(() => props.unit, unit => { unitCompetencies.value = [...(unit?.competencies ?? [])] })

const competencyKind = competency => competency.competency_presentation?.kind || competency.education_plan_competency?.area?.kind || competency.curriculum_competency?.competency_kind || 'content'
const processCompetencies = computed(() => unitCompetencies.value.filter(competency => competencyKind(competency) === 'process'))
const contentCompetencies = computed(() => unitCompetencies.value.filter(competency => competencyKind(competency) !== 'process'))
const competencyAreaGroups = competencies => { const groups = new Map(); for (const competency of competencies) { const key = competency.competency_area?.identifier || competency.education_plan_competency?.area?.external_identifier || 'other'; if (!groups.has(key)) groups.set(key, { key, area: competency.competency_area ?? (competency.education_plan_competency?.area ? { identifier: competency.education_plan_competency.area.external_identifier, title: competency.education_plan_competency.area.title } : null), competencies: [] }); groups.get(key).competencies.push(competency) } return [...groups.values()] }
const processCompetencyGroups = computed(() => competencyAreaGroups(processCompetencies.value))
const contentCompetencyGroups = computed(() => competencyAreaGroups(contentCompetencies.value))
const lessonSelectedEducationPlanIds = computed(() => unitCompetencies.value.filter(competency => competencyForm.competency_ids.includes(competency.id)).map(competency => competency.curriculum_topic_competency_id || competency.education_plan_competency_id).filter(Boolean))
const competencyHours = competency => (props.unit?.lessons ?? []).reduce((total, lesson) => {
    const represented = lesson.id === props.lesson?.id
        ? competencyForm.competency_ids.includes(competency.id)
        : (lesson.competencies ?? []).some(item => item.id === competency.id)
    return total + (represented ? Number(lesson.duration ?? 0) : 0)
}, 0)
const competencyCardStyle = competency => {
    const totalHours = (props.unit?.lessons ?? []).reduce((total, lesson) => total + Number(lesson.duration ?? 0), 0)
    const hours = competencyHours(competency)
    const intensity = Math.min(0.78, 0.18 + hours * 0.16)
    return { backgroundColor: hours ? `rgba(var(--bs-success-rgb), ${intensity})` : 'rgba(var(--bs-secondary-rgb), 0.04)' }
}

function applyCompetencies(educationPlanCompetencyIds) {
    const selectedIds = [...new Set(educationPlanCompetencyIds)]
    const selectedUnitCompetencyIds = []
    const pending = []
    selectedIds.forEach(educationPlanCompetencyId => {
        const existing = unitCompetencies.value.find(competency => (competency.curriculum_topic_competency_id || competency.education_plan_competency_id) === educationPlanCompetencyId)
        existing ? selectedUnitCompetencyIds.push(existing.id) : pending.push(educationPlanCompetencyId)
    })
    competencyPickerApplying.value = pending.length > 0
    const addNext = index => {
        if (index >= pending.length) {
            competencyForm.competency_ids = selectedUnitCompetencyIds
            competencyPickerApplying.value = false
            return
        }
        const curriculumTopicCompetencyId = pending[index]
        router.post(`/jahresplanung/${props.groupId}/lessons/${props.lesson.id}/kompetenzen`, { curriculum_topic_competency_id: curriculumTopicCompetencyId }, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: response => {
                const updatedUnit = response.props.workspace?.units?.find(unit => unit.id === props.unit.id)
                if (updatedUnit) unitCompetencies.value = [...(updatedUnit.competencies ?? [])]
                const added = unitCompetencies.value.find(competency => competency.curriculum_topic_competency_id === curriculumTopicCompetencyId)
                if (added) selectedUnitCompetencyIds.push(added.id)
                addNext(index + 1)
            },
            onError: () => { competencyPickerApplying.value = false },
        })
    }
    addNext(0)
}

function save() {
    const phases = phaseDraft.value.map(phase => ({
        ...phase,
        social_form: typeof phase.social_form === 'object' ? phase.social_form?.name ?? '' : (phase.social_form ?? phase.socialForm?.name ?? ''),
    }))
    form.transform(data => ({ ...data, competency_ids: competencyForm.competency_ids, phases, resource_links: resourceLinksDraft.value, material_items: materialItemsDraft.value, deleted_resource_link_ids: deletedResourceLinkIds.value, deleted_material_item_ids: deletedMaterialItemIds.value })).put(`/jahresplanung/${props.groupId}/lessons/${props.lesson.id}`, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => emit('close'),
    })
}
function updateResourceDescription(resource, description, copyrights) { useForm({ description, copyrights }).put(`/jahresplanung/${props.groupId}/eigene-einheiten/${props.unit.id}/anhaenge/${resource.id}`, { preserveScroll: true, onSuccess: () => { resource.description = description; resource.copyrights = copyrights } }) }
function deleteResource(resource) { router.delete(`/jahresplanung/${props.groupId}/eigene-einheiten/${props.unit.id}/anhaenge/${resource.id}`, { preserveScroll: true, onSuccess: () => { props.lesson.resources = (props.lesson.resources ?? []).filter(item => item.id !== resource.id) } }) }
function updatePreparationStatus() {
    if (!props.executionUrl || !preparationStatus.value) return
    router.put(props.executionUrl, { status: preparationStatus.value, actual_on: props.scheduledLesson?.actual_on ?? null, execution_notes: props.scheduledLesson?.execution_notes ?? null }, { preserveState: true, preserveScroll: true, onSuccess: () => { if (props.scheduledLesson) props.scheduledLesson.status = preparationStatus.value } })
}

</script>

<template>
    <div class="roo-modal-backdrop" role="presentation" @click.self="emit('close')">
        <section class="roo-modal roo-modal-wide" role="dialog" aria-modal="true" :aria-label="de.editLesson">
            <div class="card border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">{{ de.editLesson }}</h2>
                        <button class="btn-close" type="button" :aria-label="de.close" @click="emit('close')"></button>
                    </div>

                    <ul class="nav nav-tabs unit-editor-tabs mb-4" role="tablist">
                        <li class="nav-item"><button class="nav-link" :class="{ active: activeTab === 'metadata' }" type="button" @click="activeTab = 'metadata'">{{ de.unitEditorMetadata }}</button></li>
                        <li class="nav-item"><button class="nav-link" :class="{ active: activeTab === 'competencies' }" type="button" @click="activeTab = 'competencies'">{{ de.unitEditorCompetencies }}</button></li>
                        <li v-if="showPhases" class="nav-item"><button class="nav-link" :class="{ active: activeTab === 'phases' }" type="button" @click="activeTab = 'phases'">{{ de.phases }}</button></li>
                        <li v-if="showResources" class="nav-item"><button class="nav-link" :class="{ active: activeTab === 'resources' }" type="button" @click="activeTab = 'resources'">{{ de.attachmentsAndMaterials }}</button></li>
                    </ul>

                    <form @submit.prevent="save">
                        <div v-if="activeTab === 'metadata'" class="row g-3">
                            <div class="col-md-8"><label class="form-label">{{ de.lessonTitle }}</label><input v-model="form.title" class="form-control" required></div>
                            <div class="col-md-4"><label class="form-label">{{ de.lessonDuration }}</label><input v-model="form.duration" class="form-control" type="number" min="1" max="12" required></div>
                            <div v-if="scheduledLesson && executionUrl" class="col-md-4"><label class="form-label" for="lesson-preparation-status">{{ de.lessonStatus }}</label><select id="lesson-preparation-status" v-model="preparationStatus" class="form-select" @change="updatePreparationStatus"><option v-for="status in ['assigned', 'planned', 'ready', 'conducted', 'cancelled', 'postponed']" :key="status" :value="status">{{ ({ assigned: de.lessonStatusAssigned, planned: de.lessonStatusPlanned, ready: de.lessonStatusReady, conducted: de.lessonStatusConducted, cancelled: de.cancelled, postponed: de.postponed })[status] }}</option></select><div class="form-text">{{ de.lessonStatusHint }}</div></div>
                            <div class="col-12"><label class="form-label">{{ de.learningGoals }}</label><textarea v-model="form.learning_goals" class="form-control" rows="3"></textarea></div>
                            <div class="col-md-6"><label class="form-label">{{ de.materials }}</label><textarea v-model="form.materials" class="form-control" rows="4"></textarea></div>
                            <div class="col-md-6"><label class="form-label">{{ de.homework }}</label><textarea v-model="form.homework" class="form-control" rows="4"></textarea></div>
                            <div class="col-md-6"><label class="form-label">{{ de.assessmentNote }}</label><textarea v-model="form.assessment_note" class="form-control" rows="3"></textarea></div>
                            <div class="col-md-6"><label class="form-label">{{ de.notes }}</label><textarea v-model="form.notes" class="form-control" rows="3"></textarea></div>
                        </div>

                        <div v-else-if="activeTab === 'competencies'">
                            <p class="small text-muted">{{ de.lessonCompetenciesHint }}</p>
                            <div v-if="unitCompetencies.length" class="row g-4">
                                <div class="col-md-6"><h3 class="h6">{{ de.editProcessCompetencies }}</h3><div v-if="processCompetencies.length" class="row g-2"><template v-for="group in processCompetencyGroups" :key="group.key"><div v-if="group.area" class="col-12"><h4 class="h6 border-bottom pb-1 mt-2 mb-1">{{ group.area.identifier }} {{ group.area.title }}</h4></div><div v-for="competency in group.competencies" :key="competency.id" class="col-12"><label class="form-check border rounded p-2 ps-5 h-100" :style="competencyCardStyle(competency)"><input v-model="competencyForm.competency_ids" class="form-check-input" type="checkbox" :value="competency.id"><span class="form-check-label small">{{ competencyText(competency) }} <span v-if="competency.is_secondary" class="badge text-bg-light">{{ de.fromLesson }}</span></span></label></div></template></div><p v-else class="small text-muted">{{ de.noCompetencies }}</p></div>
                                <div class="col-md-6"><h3 class="h6">{{ de.editContentCompetencies }}</h3><div v-if="contentCompetencies.length" class="row g-2"><template v-for="group in contentCompetencyGroups" :key="group.key"><div v-if="group.area" class="col-12"><h4 class="h6 border-bottom pb-1 mt-2 mb-1">{{ group.area.identifier }} {{ group.area.title }}</h4></div><div v-for="competency in group.competencies" :key="competency.id" class="col-12"><label class="form-check border rounded p-2 ps-5 h-100" :style="competencyCardStyle(competency)"><input v-model="competencyForm.competency_ids" class="form-check-input" type="checkbox" :value="competency.id"><span class="form-check-label small">{{ competencyText(competency) }} <span v-if="competency.is_secondary" class="badge text-bg-light">{{ de.fromLesson }}</span></span></label></div></template></div><p v-else class="small text-muted">{{ de.noCompetencies }}</p></div>
                            </div>
                            <p v-else class="small text-muted">{{ de.noCompetencies }}</p>
                            <div class="position-relative mt-4">
                                <button class="btn btn-outline-primary" type="button" :disabled="competencyPickerApplying" @click="competencyPickerOpen = true"><i class="bi bi-list-check me-1"></i>{{ de.addCompetency }}</button>
                            </div>
                        </div>

                        <LessonPhasesTab v-else-if="activeTab === 'phases'" :lesson="lesson" :phases="phaseDraft" :group-id="groupId" :phase-templates="phaseTemplates" :social-forms="socialForms" :resources="lesson.resources ?? []" :resource-links="resourceLinksDraft" :material-items="materialItemsDraft" :songs="songs" compact @update:phases="phaseDraft = $event" />
                        <AttachmentList v-else :resources="lesson.resources ?? []" :resource-links="resourceLinksDraft" :material-items="materialItemsDraft" :songs="lesson.songs ?? []" :songbooks="lesson.songbooks ?? []" :material-text="lesson.materials" :manage="true" :library-attach-url="'/jahresplanung/' + groupId + '/ressourcen'" :library-target-type="'lesson'" :library-target-id="lesson.id" :upload-url="`/jahresplanung/${groupId}/eigene-einheiten/${unit.id}/anhaenge`" :upload-lesson-id="lesson.id" :download-base-url="`/jahresplanung/${groupId}/eigene-einheiten/${unit.id}/anhaenge`" @update="updateResourceDescription" @delete="deleteResource" @uploaded="router.reload({ preserveScroll: true })" @update:resource-links="resourceLinksDraft = $event" @update:material-items="materialItemsDraft = $event" />

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <button class="btn btn-outline-secondary" type="button" @click="emit('close')">{{ de.cancel }}</button>
                            <button class="btn btn-primary" type="submit" :disabled="form.processing || competencyForm.processing">{{ de.saveChanges }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>

    </div>
    <CompetencyPickerModal v-model="competencyPickerOpen" :competencies="competencyOptions" :selected-ids="lessonSelectedEducationPlanIds" :competency-text="competencyText" :lessons="groupLessons.length ? groupLessons : (unit?.lessons ?? [])" :covered-hours="coveredHours" :current-lesson-id="lesson?.id" @apply="applyCompetencies" />
</template>
