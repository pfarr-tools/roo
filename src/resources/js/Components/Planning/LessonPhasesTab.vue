<script setup>
import de from '../../i18n/de'
import { router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({ lesson: Object, phases: { type: Array, default: () => [] }, groupId: [String, Number], phaseTemplates: { type: Array, default: () => [] }, socialForms: { type: Array, default: () => [] } })
const emit = defineEmits(['update:phases'])
const phases = ref([])
const editing = ref(null)
const selectedTemplate = ref('')
const newPhaseTitle = ref('')
const phaseForm = ref({ title: '', duration_minutes: null, description: '', materials: '' })

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

function addPhase() {
    const template = props.phaseTemplates.find(item => String(item.id) === String(selectedTemplate.value))
    phases.value.push({
        local_key: `new-${Date.now()}-${phases.value.length}`,
        phase_template_id: template?.id ?? null,
        title: template?.title ?? newPhaseTitle.value,
        duration_minutes: template?.duration_minutes ?? null,
        social_form_id: template?.social_form_id ?? null,
        description: template?.description ?? '',
        materials: template?.material ?? '',
    })
    emit('update:phases', phases.value)
    selectedTemplate.value = ''; newPhaseTitle.value = ''
}

function editPhase(phase) {
    const key = phase.id ?? phase.local_key
    editing.value = editing.value === key ? null : key
    phaseForm.value = { title: phase.title ?? '', duration_minutes: phase.duration_minutes ?? null, social_form_id: phase.social_form_id ?? null, description: phase.description ?? '', materials: phase.materials ?? '' }
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
            <div v-for="(phase, index) in phases" :key="phase.id ?? phase.local_key" class="list-group-item d-flex flex-wrap align-items-center gap-2">
                <span class="text-muted small" aria-hidden="true">{{ index + 1 }}</span>
                <button class="btn btn-sm btn-link text-start flex-grow-1 p-0" type="button" :aria-expanded="editing === (phase.id ?? phase.local_key)" @click="editPhase(phase)"><strong>{{ phase.title }}</strong><span v-if="phase.duration_minutes" class="small text-muted ms-2">{{ phase.duration_minutes }} {{ de.minutes }}</span><span v-if="phase.social_form_id" class="small text-muted ms-2">· {{ socialForms.find(item => item.id === Number(phase.social_form_id))?.name }}</span><span class="small text-muted d-block text-truncate">{{ phase.description || de.noDescription }}</span></button>
                <button class="btn btn-sm btn-outline-secondary" type="button" :disabled="index === 0" :aria-label="de.moveUp" @click="movePhase(index, -1)"><i class="bi bi-chevron-up" aria-hidden="true"></i></button>
                <button class="btn btn-sm btn-outline-secondary" type="button" :disabled="index === phases.length - 1" :aria-label="de.moveDown" @click="movePhase(index, 1)"><i class="bi bi-chevron-down" aria-hidden="true"></i></button>
                <button class="btn btn-sm btn-outline-danger" type="button" :aria-label="de.deletePhase" @click="removePhase(phase)"><i class="bi bi-trash" aria-hidden="true"></i></button>
                <div v-if="editing === (phase.id ?? phase.local_key)" class="w-100 border-top pt-3 mt-2">
                    <label class="form-label" :for="`phase-title-${phaseKey(phase)}`">{{ de.phaseTitle }}</label><input :id="`phase-title-${phaseKey(phase)}`" v-model="phaseForm.title" class="form-control" required>
                    <div class="row g-2"><div class="col-md-6"><label class="form-label mt-2" :for="`phase-duration-${phaseKey(phase)}`">{{ de.phaseDuration }}</label><input :id="`phase-duration-${phaseKey(phase)}`" v-model="phaseForm.duration_minutes" class="form-control" type="number" min="1" max="999"></div><div class="col-md-6"><label class="form-label mt-2" :for="`phase-social-form-${phaseKey(phase)}`">{{ de.socialForm }}</label><select :id="`phase-social-form-${phaseKey(phase)}`" v-model="phaseForm.social_form_id" class="form-select"><option :value="null">{{ de.choose }}</option><option v-for="socialForm in socialForms" :key="socialForm.id" :value="socialForm.id">{{ socialForm.name }}</option></select></div></div>
                    <label class="form-label mt-2" :for="`phase-description-${phaseKey(phase)}`">{{ de.description }}</label><textarea :id="`phase-description-${phaseKey(phase)}`" v-model="phaseForm.description" class="form-control" rows="3"></textarea>
                    <label class="form-label mt-2" :for="`phase-materials-${phaseKey(phase)}`">{{ de.materials }}</label><textarea :id="`phase-materials-${phaseKey(phase)}`" v-model="phaseForm.materials" class="form-control" rows="2"></textarea>
                    <div class="small text-muted mt-3">{{ de.phaseChangesSavedWithLesson }}</div>
                </div>
            </div>
        </div>
        <p v-else class="small text-muted">{{ de.noPhases }}</p>
    </div>
</template>
