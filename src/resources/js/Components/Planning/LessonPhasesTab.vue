<script setup>
import PhaseEditorOffcanvas from './PhaseEditorOffcanvas.vue'
import de from '../../i18n/de'
import { router, useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({ lesson: Object, groupId: [String, Number], phaseTemplates: { type: Array, default: () => [] } })
const phases = ref([])
const editing = ref(null)
const selectedTemplate = ref('')
const newPhaseTitle = ref('')
const phaseForm = useForm({ title: '', description: '', materials: '' })

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
    editing.value = phase
    phaseForm.defaults({ title: phase.title ?? '', duration_minutes: phase.duration_minutes ?? null, description: phase.description ?? '', materials: phase.materials ?? '' })
    phaseForm.reset()
}

function savePhase(data) {
    phaseForm.transform(() => data).put(`/jahresplanung/${props.groupId}/phasen/${editing.value.id}`, { preserveScroll: true, onSuccess: () => { editing.value = null } })
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
            <div v-for="(phase, index) in phases" :key="phase.id" class="list-group-item d-flex align-items-center gap-2">
                <span class="text-muted small" aria-hidden="true">{{ index + 1 }}</span>
                <div class="flex-grow-1"><strong>{{ phase.title }}</strong><span v-if="phase.duration_minutes" class="small text-muted ms-2">{{ phase.duration_minutes }} {{ de.minutes }}</span><div class="small text-muted text-truncate">{{ phase.description || de.noDescription }}</div></div>
                <button class="btn btn-sm btn-outline-secondary" type="button" :disabled="index === 0" :aria-label="de.moveUp" @click="movePhase(index, -1)"><i class="bi bi-chevron-up" aria-hidden="true"></i></button>
                <button class="btn btn-sm btn-outline-secondary" type="button" :disabled="index === phases.length - 1" :aria-label="de.moveDown" @click="movePhase(index, 1)"><i class="bi bi-chevron-down" aria-hidden="true"></i></button>
                <button class="btn btn-sm btn-outline-secondary" type="button" :aria-label="de.editPhase" @click="editPhase(phase)"><i class="bi bi-pencil-square" aria-hidden="true"></i></button>
                <button class="btn btn-sm btn-outline-danger" type="button" :aria-label="de.deletePhase" @click="removePhase(phase)"><i class="bi bi-trash" aria-hidden="true"></i></button>
            </div>
        </div>
        <p v-else class="small text-muted">{{ de.noPhases }}</p>

        <PhaseEditorOffcanvas v-if="editing" :phase="editing" @close="editing = null" @save="savePhase" />
    </div>
</template>
