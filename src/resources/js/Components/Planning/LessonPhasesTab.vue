<script setup>
import de from '../../i18n/de'
import { router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({ lesson: Object, groupId: [String, Number], phaseTemplates: { type: Array, default: () => [] } })
const phases = ref([])
const editing = ref(null)
const selectedTemplate = ref('')
const newPhaseTitle = ref('')
const phaseForm = ref({ title: '', duration_minutes: null, description: '', materials: '' })

watch(() => props.lesson, lesson => { phases.value = [...(lesson?.phases ?? [])] }, { immediate: true })
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
    const payload = selectedTemplate.value ? { phase_template_id: selectedTemplate.value } : { title: newPhaseTitle.value }
    router.post(`/jahresplanung/${props.groupId}/lessons/${props.lesson.id}/phasen`, payload, { preserveScroll: true, onSuccess: () => { selectedTemplate.value = ''; newPhaseTitle.value = '' } })
}

function editPhase(phase) {
    editing.value = editing.value === phase.id ? null : phase.id
    phaseForm.value = { title: phase.title ?? '', duration_minutes: phase.duration_minutes ?? null, description: phase.description ?? '', materials: phase.materials ?? '' }
}

function savePhase(phase) {
    router.put(`/jahresplanung/${props.groupId}/phasen/${phase.id}`, phaseForm.value, { preserveScroll: true, onSuccess: () => { editing.value = null } })
}

function removePhase(phase) {
    if (window.confirm(de.deletePhaseConfirm)) router.delete(`/jahresplanung/${props.groupId}/phasen/${phase.id}`, { preserveScroll: true })
}

function movePhase(index, direction) {
    const target = index + direction
    if (target < 0 || target >= phases.value.length) return
    const next = [...phases.value]
    ;[next[index], next[target]] = [next[target], next[index]]
    phases.value = next
    router.put(`/jahresplanung/${props.groupId}/lessons/${props.lesson.id}/phasen/reihenfolge`, { phase_ids: next.map(phase => phase.id) }, { preserveScroll: true })
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
            <div v-for="(phase, index) in phases" :key="phase.id" class="list-group-item d-flex flex-wrap align-items-center gap-2">
                <span class="text-muted small" aria-hidden="true">{{ index + 1 }}</span>
                <button class="btn btn-sm btn-link text-start flex-grow-1 p-0" type="button" :aria-expanded="editing === phase.id" @click="editPhase(phase)"><strong>{{ phase.title }}</strong><span v-if="phase.duration_minutes" class="small text-muted ms-2">{{ phase.duration_minutes }} {{ de.minutes }}</span><span class="small text-muted d-block text-truncate">{{ phase.description || de.noDescription }}</span></button>
                <button class="btn btn-sm btn-outline-secondary" type="button" :disabled="index === 0" :aria-label="de.moveUp" @click="movePhase(index, -1)"><i class="bi bi-chevron-up" aria-hidden="true"></i></button>
                <button class="btn btn-sm btn-outline-secondary" type="button" :disabled="index === phases.length - 1" :aria-label="de.moveDown" @click="movePhase(index, 1)"><i class="bi bi-chevron-down" aria-hidden="true"></i></button>
                <button class="btn btn-sm btn-outline-danger" type="button" :aria-label="de.deletePhase" @click="removePhase(phase)"><i class="bi bi-trash" aria-hidden="true"></i></button>
                <form v-if="editing === phase.id" class="w-100 border-top pt-3 mt-2" @submit.prevent="savePhase(phase)">
                    <label class="form-label" :for="`phase-title-${phase.id}`">{{ de.phaseTitle }}</label><input :id="`phase-title-${phase.id}`" v-model="phaseForm.title" class="form-control" required>
                    <label class="form-label mt-2" :for="`phase-duration-${phase.id}`">{{ de.phaseDuration }}</label><input :id="`phase-duration-${phase.id}`" v-model="phaseForm.duration_minutes" class="form-control" type="number" min="1" max="999">
                    <label class="form-label mt-2" :for="`phase-description-${phase.id}`">{{ de.description }}</label><textarea :id="`phase-description-${phase.id}`" v-model="phaseForm.description" class="form-control" rows="3"></textarea>
                    <label class="form-label mt-2" :for="`phase-materials-${phase.id}`">{{ de.materials }}</label><textarea :id="`phase-materials-${phase.id}`" v-model="phaseForm.materials" class="form-control" rows="2"></textarea>
                    <div class="d-flex justify-content-end gap-2 mt-3"><button class="btn btn-outline-secondary" type="button" @click="editing = null">{{ de.cancel }}</button><button class="btn btn-primary" type="submit">{{ de.saveChanges }}</button></div>
                </form>
            </div>
        </div>
        <p v-else class="small text-muted">{{ de.noPhases }}</p>
    </div>
</template>
