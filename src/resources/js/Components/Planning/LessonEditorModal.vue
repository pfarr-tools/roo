<script setup>
import de from '../../i18n/de'
import { useForm } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

const props = defineProps({ lesson: Object, unit: Object, groupId: [String, Number], competencyText: Function })
const emit = defineEmits(['close'])
const activeTab = ref('metadata')
const phaseEditor = ref(null)
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
const phaseForm = useForm({ title: '', description: '', materials: '' })

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
}

watch(() => props.lesson, syncLesson, { immediate: true })

function save() {
    form.put(`/jahresplanung/${props.groupId}/lessons/${props.lesson.id}`, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => competencyForm.put(`/jahresplanung/${props.groupId}/lessons/${props.lesson.id}/kompetenzen`, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => emit('close'),
        }),
    })
}

function openPhase(phase) {
    phaseEditor.value = phase
    phaseForm.defaults({ title: phase.title ?? '', description: phase.description ?? '', materials: phase.materials ?? '' })
    phaseForm.reset()
}

function savePhase() {
    phaseForm.put(`/jahresplanung/${props.groupId}/phasen/${phaseEditor.value.id}`, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => { phaseEditor.value = null },
    })
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
                        <li class="nav-item"><button class="nav-link" :class="{ active: activeTab === 'phases' }" type="button" @click="activeTab = 'phases'">{{ de.phases }}</button></li>
                    </ul>

                    <form @submit.prevent="save">
                        <div v-if="activeTab === 'metadata'" class="row g-3">
                            <div class="col-md-8"><label class="form-label">{{ de.lessonTitle }}</label><input v-model="form.title" class="form-control" required></div>
                            <div class="col-md-4"><label class="form-label">{{ de.lessonDuration }}</label><input v-model="form.duration" class="form-control" type="number" min="1" max="12" required></div>
                            <div class="col-12"><label class="form-label">{{ de.learningGoals }}</label><textarea v-model="form.learning_goals" class="form-control" rows="3"></textarea></div>
                            <div class="col-md-6"><label class="form-label">{{ de.materials }}</label><textarea v-model="form.materials" class="form-control" rows="4"></textarea></div>
                            <div class="col-md-6"><label class="form-label">{{ de.homework }}</label><textarea v-model="form.homework" class="form-control" rows="4"></textarea></div>
                            <div class="col-md-6"><label class="form-label">{{ de.assessmentNote }}</label><textarea v-model="form.assessment_note" class="form-control" rows="3"></textarea></div>
                            <div class="col-md-6"><label class="form-label">{{ de.notes }}</label><textarea v-model="form.notes" class="form-control" rows="3"></textarea></div>
                        </div>

                        <div v-else-if="activeTab === 'competencies'">
                            <p class="small text-muted">{{ de.lessonCompetenciesHint }}</p>
                            <div v-if="unit?.competencies?.length" class="row g-2">
                                <div v-for="competency in unit.competencies" :key="competency.id" class="col-md-6">
                                    <label class="form-check border rounded p-2 ps-5 h-100">
                                        <input v-model="competencyForm.competency_ids" class="form-check-input" type="checkbox" :value="competency.id">
                                        <span class="form-check-label small">{{ competencyText(competency) }}</span>
                                    </label>
                                </div>
                            </div>
                            <p v-else class="small text-muted">{{ de.noCompetencies }}</p>
                        </div>

                        <div v-else>
                            <div v-if="lesson.phases?.length" class="list-group">
                                <div v-for="(phase, index) in lesson.phases" :key="phase.id" class="list-group-item d-flex align-items-center gap-3">
                                    <span class="text-muted small">{{ index + 1 }}</span>
                                    <div class="flex-grow-1"><strong>{{ phase.title }}</strong><div class="small text-muted text-truncate">{{ phase.description || de.noDescription }}</div></div>
                                    <button class="btn btn-sm btn-outline-secondary" type="button" :aria-label="de.editPhase" @click="openPhase(phase)"><i class="bi bi-pencil-square" aria-hidden="true"></i></button>
                                </div>
                            </div>
                            <p v-else class="small text-muted">{{ de.noPhases }}</p>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <button class="btn btn-outline-secondary" type="button" @click="emit('close')">{{ de.cancel }}</button>
                            <button class="btn btn-primary" type="submit" :disabled="form.processing || competencyForm.processing">{{ de.saveChanges }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <div v-if="phaseEditor" class="roo-modal-backdrop nested-modal" role="presentation" @click.self="phaseEditor = null">
            <section class="roo-modal" role="dialog" aria-modal="true" :aria-label="de.editPhase">
                <div class="card border-0"><div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">{{ de.editPhase }}</h2><button class="btn-close" type="button" :aria-label="de.close" @click="phaseEditor = null"></button></div>
                    <form @submit.prevent="savePhase">
                        <label class="form-label">{{ de.phaseTitle }}</label><input v-model="phaseForm.title" class="form-control" required>
                        <label class="form-label mt-3">{{ de.description }}</label><textarea v-model="phaseForm.description" class="form-control" rows="5"></textarea>
                        <label class="form-label mt-3">{{ de.materials }}</label><textarea v-model="phaseForm.materials" class="form-control" rows="3"></textarea>
                        <div class="d-flex justify-content-end gap-2 mt-4"><button class="btn btn-outline-secondary" type="button" @click="phaseEditor = null">{{ de.cancel }}</button><button class="btn btn-primary" type="submit" :disabled="phaseForm.processing">{{ de.saveChanges }}</button></div>
                    </form>
                </div></div>
            </section>
        </div>
    </div>
</template>
