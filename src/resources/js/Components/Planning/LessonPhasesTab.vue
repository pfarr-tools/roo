<script setup>
import de from '../../i18n/de'
import PhaseResourcePicker from './PhaseResourcePicker.vue'
import { router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({ lesson: Object, phases: { type: Array, default: () => [] }, groupId: [String, Number], phaseTemplates: { type: Array, default: () => [] }, socialForms: { type: Array, default: () => [] }, resources: { type: Array, default: () => [] }, resourceLinks: { type: Array, default: () => [] }, materialItems: { type: Array, default: () => [] }, compact: { type: Boolean, default: false } })
const emit = defineEmits(['update:phases'])
const phases = ref([])
const resourceLinks = computed(() => props.resourceLinks)
const editing = ref(null)
const selectedTemplate = ref('')
const newPhaseTitle = ref('')
const phaseForm = ref({ title: '', duration_minutes: null, social_form: '', teacher_interaction: '', learner_activity: '', differentiation: '', didactic_comment: '', resource_ids: [], resource_link_ids: [], material_item_ids: [] })

watch(() => props.phases, value => { phases.value = value.map(phase => ({ ...phase })) }, { immediate: true })
watch(phaseForm, value => {
    const phase = phases.value.find(item => (item.id ?? item.local_key) === editing.value)
    if (phase) { Object.assign(phase, value); emit('update:phases', phases.value) }
}, { deep: true })
const scheduledLesson = computed(() => props.lesson?.scheduled_lessons?.[0] ?? null)
const statusOptions = [
    { value: 'assigned', label: de.lessonStatusAssigned },
    { value: 'planned', label: de.lessonStatusPlanned },
    { value: 'ready', label: de.lessonStatusReady },
    { value: 'conducted', label: de.lessonStatusConducted },
    { value: 'cancelled', label: de.cancelled },
    { value: 'postponed', label: de.postponed },
]
const totalMinutes = computed(() => phases.value.reduce((total, phase) => total + Number(phase.duration_minutes ?? 0), 0))
const expectedMinutes = computed(() => Number(props.lesson?.duration ?? 1) * 45)
const phaseKey = phase => phase.id ?? phase.local_key

function addPhase() {
    const template = props.phaseTemplates.find(item => String(item.id) === String(selectedTemplate.value))
    phases.value.push({
        local_key: `new-${Date.now()}-${phases.value.length}`,
        phase_template_id: template?.id ?? null,
        title: template?.title ?? newPhaseTitle.value,
        duration_minutes: template?.duration_minutes ?? null,
        social_form: template?.social_form?.name ?? '',
        teacher_interaction: template?.teacher_interaction ?? '',
        learner_activity: template?.learner_activity ?? '',
        differentiation: template?.differentiation ?? '',
        didactic_comment: template?.didactic_comment ?? '',
        resource_ids: [], resource_link_ids: [], material_item_ids: [],
    })
    emit('update:phases', phases.value)
    selectedTemplate.value = ''; newPhaseTitle.value = ''
}

function editPhase(phase) {
    const key = phase.id ?? phase.local_key
    editing.value = editing.value === key ? null : key
    phaseForm.value = { title: phase.title ?? '', duration_minutes: phase.duration_minutes ?? null, social_form: phase.social_form?.name ?? phase.social_form ?? '', teacher_interaction: phase.teacher_interaction ?? '', learner_activity: phase.learner_activity ?? '', differentiation: phase.differentiation ?? '', didactic_comment: phase.didactic_comment ?? '', resource_ids: [...(phase.resource_ids ?? [])], resource_link_ids: [...(phase.resource_link_ids ?? [])], material_item_ids: [...(phase.material_item_ids ?? [])] }
}

function phaseMaterialMedia(phase) {
    const files = (phase.resource_ids ?? []).map(id => props.resources.find(resource => String(resource.id) === String(id))?.display_name || props.resources.find(resource => String(resource.id) === String(id))?.original_name).filter(Boolean)
    const links = (phase.resource_link_ids ?? []).map(id => props.resourceLinks.find(link => String(link.id || link.local_key) === String(id))?.title).filter(Boolean)
    const items = (phase.material_item_ids ?? []).map(id => props.materialItems.find(item => String(item.id) === String(id))?.name).filter(Boolean)
    return [...files, ...links, ...items].filter(Boolean).join('\n') || '–'
}

function removePhase(phase) {
    if (window.confirm(de.deletePhaseConfirm)) { phases.value = phases.value.filter(item => item !== phase); emit('update:phases', phases.value) }
}

function movePhase(index, direction) {
    const target = index + direction
    if (target < 0 || target >= phases.value.length) return
    const next = [...phases.value]
    ;[next[index], next[target]] = [next[target], next[index]]
    phases.value = next
    emit('update:phases', next)
}

function updateStatus(status) {
    if (!scheduledLesson.value) return
    router.put(`/jahresplanung/${props.groupId}/geplante-stunden/${scheduledLesson.value.id}/status`, { status }, { preserveScroll: true })
}

function savePhaseAsTemplate(phase) {
    if (phase.id) router.post(`/jahresplanung/${props.groupId}/phasen/${phase.id}/als-vorlage`, {}, { preserveScroll: true })
}
</script>

<template>
    <div>
        <div v-if="scheduledLesson" class="mb-4">
            <label class="form-label" :for="`lesson-status-${lesson.id}`">{{ de.lessonStatus }}</label>
            <select :id="`lesson-status-${lesson.id}`" class="form-select" :value="scheduledLesson.status" @change="updateStatus($event.target.value)">
                <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <div class="form-text">{{ de.lessonStatusHint }}</div>
        </div>

        <div class="input-group mb-3">
            <input v-model="newPhaseTitle" class="form-control" :placeholder="de.phaseTitle" :disabled="Boolean(selectedTemplate)">
            <select v-model="selectedTemplate" class="form-select" :aria-label="de.insertPhaseTemplate">
                <option value="">{{ de.insertPhaseTemplate }}</option>
                <option v-for="template in phaseTemplates" :key="template.id" :value="template.id">{{ template.title }}</option>
            </select>
            <button class="btn btn-primary" type="button" :disabled="!newPhaseTitle && !selectedTemplate" @click="addPhase">{{ de.addPhase }}</button>
        </div>
        <div v-if="totalMinutes" class="alert mb-3" :class="totalMinutes === expectedMinutes ? 'alert-success' : 'alert-warning'" role="status">
            {{ de.phaseTimeSummary(totalMinutes, expectedMinutes) }}
        </div>

        <div v-if="phases.length" class="list-group">
            <div v-if="!compact" class="row g-2 px-3 py-2 small text-muted fw-semibold d-none d-md-flex" aria-hidden="true"><div class="col-md-1">{{ de.phaseNumber }}</div><div class="col-md-1">{{ de.phaseDuration }}</div><div class="col-md-2">{{ de.phaseTitle }}</div><div class="col-md-2">{{ de.teacherInteraction }}</div><div class="col-md-2">{{ de.learnerActivityDifferentiation }}</div><div class="col-md-2">{{ de.socialFormDidacticComment }}</div><div class="col-md-1">{{ de.materialsMedia }}</div><div class="col-md-1"></div></div>
            <div v-for="(phase, index) in phases" :key="phase.id ?? phase.local_key" class="list-group-item">
                <div class="row g-2 align-items-start">
                    <div class="col-md-1"><button class="btn btn-sm btn-link text-decoration-none text-start p-0" type="button" :aria-expanded="editing === (phase.id ?? phase.local_key)" :aria-label="`${phase.title}: ${editing === (phase.id ?? phase.local_key) ? de.close : de.edit}`" @click="editPhase(phase)"><span class="text-muted me-1" aria-hidden="true">{{ index + 1 }}</span><i class="bi" :class="editing === (phase.id ?? phase.local_key) ? 'bi-chevron-down' : 'bi-chevron-right'" aria-hidden="true"></i></button></div>
                    <div :class="compact ? 'col-md-2' : 'col-md-1'" class="text-nowrap"><span class="d-md-none small text-muted fw-semibold">{{ de.phaseDuration }}: </span>{{ phase.duration_minutes ? `${phase.duration_minutes} ${de.minutes}` : '–' }}</div>
                    <div class="col-md-2"><strong>{{ phase.title }}</strong></div>
                    <div v-if="!compact" class="col-md-2 text-pre-wrap">{{ phase.teacher_interaction || '–' }}</div>
                    <div v-if="!compact" class="col-md-2 text-pre-wrap">{{ [phase.learner_activity, phase.differentiation].filter(Boolean).join(' / ') || '–' }}</div>
                    <div v-if="!compact" class="col-md-2 text-pre-wrap"><div>{{ phase.social_form?.name || phase.social_form || phase.socialForm?.name || '–' }}</div><div v-if="phase.didactic_comment" class="mt-1">{{ phase.didactic_comment }}</div></div>
                    <div v-if="!compact" class="col-md-1 text-pre-wrap">{{ phaseMaterialMedia(phase) }}</div>
                    <div :class="compact ? 'col-md-7' : 'col-md-1'" class="d-flex justify-content-md-end gap-1"><button class="btn btn-sm btn-outline-secondary" type="button" :disabled="index === 0" :aria-label="de.moveUp" @click="movePhase(index, -1)"><i class="bi bi-chevron-up" aria-hidden="true"></i></button><button class="btn btn-sm btn-outline-secondary" type="button" :disabled="index === phases.length - 1" :aria-label="de.moveDown" @click="movePhase(index, 1)"><i class="bi bi-chevron-down" aria-hidden="true"></i></button><button class="btn btn-sm btn-outline-primary" type="button" :disabled="!phase.id" :title="de.savePhaseAsTemplate" :aria-label="de.savePhaseAsTemplate" @click="savePhaseAsTemplate(phase)"><i class="bi bi-bookmark-plus" aria-hidden="true"></i></button><button class="btn btn-sm btn-outline-danger" type="button" :aria-label="de.deletePhase" @click="removePhase(phase)"><i class="bi bi-trash" aria-hidden="true"></i></button></div>
                </div>
                <div v-if="editing === (phase.id ?? phase.local_key)" class="border-top pt-3 mt-3">
                    <label class="form-label" :for="`phase-title-${phaseKey(phase)}`">{{ de.phaseTitle }}</label><input :id="`phase-title-${phaseKey(phase)}`" v-model="phaseForm.title" class="form-control" required>
                    <div class="row g-2"><div class="col-md-6"><label class="form-label mt-2" :for="`phase-duration-${phaseKey(phase)}`">{{ de.phaseDuration }}</label><input :id="`phase-duration-${phaseKey(phase)}`" v-model="phaseForm.duration_minutes" class="form-control" type="number" min="1" max="999"></div><div class="col-md-6"><label class="form-label mt-2" :for="`phase-social-form-${phaseKey(phase)}`">{{ de.socialForm }}</label><input :id="`phase-social-form-${phaseKey(phase)}`" v-model="phaseForm.social_form" class="form-control" list="lesson-social-forms" :placeholder="de.socialFormPlaceholder"><datalist id="lesson-social-forms"><option v-for="socialForm in socialForms" :key="socialForm.id" :value="socialForm.name"></option></datalist></div></div>
                    <div class="row g-2"><div class="col-lg-6"><label class="form-label mt-2" :for="`phase-teacher-interaction-${phaseKey(phase)}`">{{ de.teacherInteraction }}</label><textarea :id="`phase-teacher-interaction-${phaseKey(phase)}`" v-model="phaseForm.teacher_interaction" class="form-control" rows="3"></textarea></div><div class="col-lg-6"><label class="form-label mt-2" :for="`phase-learner-activity-${phaseKey(phase)}`">{{ de.learnerActivity }}</label><textarea :id="`phase-learner-activity-${phaseKey(phase)}`" v-model="phaseForm.learner_activity" class="form-control" rows="3"></textarea></div><div class="col-lg-6"><label class="form-label mt-2" :for="`phase-differentiation-${phaseKey(phase)}`">{{ de.differentiation }}</label><textarea :id="`phase-differentiation-${phaseKey(phase)}`" v-model="phaseForm.differentiation" class="form-control" rows="3"></textarea></div><div class="col-lg-6"><label class="form-label mt-2" :for="`phase-didactic-comment-${phaseKey(phase)}`">{{ de.didacticComment }}</label><textarea :id="`phase-didactic-comment-${phaseKey(phase)}`" v-model="phaseForm.didactic_comment" class="form-control" rows="3"></textarea></div></div>
                    <PhaseResourcePicker :resources="resources" :resource-links="resourceLinks" :material-items="materialItems" :selected-resource-ids="phaseForm.resource_ids" :selected-resource-link-ids="phaseForm.resource_link_ids" :selected-material-item-ids="phaseForm.material_item_ids" @update:resource-ids="phaseForm.resource_ids = $event" @update:resource-link-ids="phaseForm.resource_link_ids = $event" @update:material-item-ids="phaseForm.material_item_ids = $event" />
                    <div class="small text-muted mt-3">{{ de.phaseChangesSavedWithLesson }}</div>
                </div>
            </div>
        </div>
        <p v-else class="small text-muted">{{ de.noPhases }}</p>
    </div>
</template>
