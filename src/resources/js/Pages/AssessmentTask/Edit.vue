<script setup>
import { useForm, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import AppShell from '../../Components/Ui/AppShell.vue'
import CompetencyPickerModal from '../../Components/Planning/CompetencyPickerModal.vue'
import de from '../../i18n/de'

const props = defineProps({
    backUrl: { type: String, required: true },
    submitUrl: { type: String, required: true },
    method: { type: String, default: 'post' },
    competencyId: { type: [String, Number], default: '' },
    educationPlans: { type: Array, default: () => [] },
    task: { type: Object, default: null },
    libraryMode: { type: Boolean, default: false },
    competencies: { type: Array, default: () => [] },
    competencyField: { type: String, default: 'teaching_unit_competency_id' },
})

const form = useForm({ title: '', solution: '', max_points: '', competency_id: '', education_plan_id: '', education_plan_competency_id: '', levels: [] })
const competencyPickerOpen = ref(false)
const selectedCompetencyText = ref('')
const selectedCompetencyDifferentiated = ref(false)

function resetForm() {
    form.defaults({
        title: props.task?.title ?? '', solution: props.task?.solution ?? '', max_points: props.task?.max_points ?? '',
        competency_id: props.task?.teaching_unit_competency_id ?? props.competencyId ?? '',
        education_plan_id: props.task?.education_plan_id ?? '',
        education_plan_competency_id: props.task?.education_plan_competency_id ?? '',
        levels: props.task?.levels?.map(level => level.level ?? level) ?? [],
    })
    form.reset()
    selectedCompetencyText.value = props.task?.education_plan_competency?.external_identifier ?? props.task?.competency_identifier ?? props.task?.competency ?? ''
    selectedCompetencyDifferentiated.value = props.task?.has_differentiation ?? false
}
watch(() => props.task?.id, resetForm, { immediate: true })

function competencyText(competency) {
    const presentation = competency.competency_presentation || {}
    const number = presentation.identifier || competency.external_identifier || competency.number
    const variantText = (competency.variants || []).map(variant => variant.text).filter(Boolean).join(' / ')
    const text = presentation.text || competency.text || competency.display || competency.local_wording || variantText
    if (text) return [number, text].filter(Boolean).join(' – ')
    return presentation.label || ('Kompetenz ' + competency.id)
}
function pickerEndpoint() {
    return form.education_plan_id ? '/ressourcen/bibliothek/bildungsplaene/' + form.education_plan_id + '/kompetenzen' : '/ressourcen/bibliothek/bildungsplaene/0/kompetenzen'
}
function applyCompetency(ids, selected = []) {
    form.education_plan_competency_id = ids[0] ?? ''
    selectedCompetencyText.value = selected[0] ? competencyText(selected[0]) : ''
    selectedCompetencyDifferentiated.value = selected[0]?.has_differentiation ?? false
}
function choosePlan() {
    form.education_plan_competency_id = ''
    selectedCompetencyText.value = ''
    selectedCompetencyDifferentiated.value = false
}
function save() {
    const payload = form.data()
    payload[props.competencyField] = payload.competency_id
    if (props.competencyField !== 'competency_id') delete payload.competency_id
    form.transform(() => payload)[props.method](props.submitUrl, {
        onSuccess: () => router.visit(props.backUrl),
    })
}
</script>

<template>
    <AppShell>
        <template #toolbar>
            <a :href="backUrl" class="btn btn-sm btn-light" :title="de.close" :aria-label="de.close"><i class="bi bi-x-lg" aria-hidden="true"></i></a>
            <button class="btn btn-sm btn-primary ms-2" type="submit" form="assessment-task-form" :disabled="form.processing"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ de.saveChanges }}</button>
        </template>
        <div class="container-full px-3 py-4">
            <h1 class="h2">{{ task ? de.editAssessmentTask : de.newAssessmentTask }}</h1>
            <form id="assessment-task-form" class="card card-body" @submit.prevent="save">
                <label class="form-label" for="assessment-task-title">{{ de.assessmentTaskTitle }}</label>
                <input id="assessment-task-title" v-model="form.title" class="form-control" required>
                <label class="form-label mt-3" for="assessment-task-solution">{{ de.assessmentSolution }}</label>
                <textarea id="assessment-task-solution" v-model="form.solution" class="form-control" rows="5"></textarea>
                <template v-if="!libraryMode || task || form.education_plan_id">
                <label class="form-label mt-3" for="assessment-task-plan">{{ de.educationPlan }}</label>
                <select id="assessment-task-plan" v-model="form.education_plan_id" class="form-select" required @change="choosePlan">
                    <option value="">{{ de.choose }}</option>
                    <option v-for="plan in educationPlans" :key="plan.id" :value="plan.id">{{ plan.title }}</option>
                </select>
                <label class="form-label mt-3">{{ de.competencies }}</label>
                <div class="input-group"><input class="form-control" :value="selectedCompetencyText" :placeholder="de.choose" readonly required><button class="btn btn-outline-primary" type="button" :disabled="!form.education_plan_id" @click="competencyPickerOpen = true">{{ de.addCompetency }}</button></div>
                </template>
                <template v-else><label class="form-label mt-3" for="assessment-task-library-competency">{{ de.competencies }}</label><select id="assessment-task-library-competency" v-model="form.competency_id" class="form-select" required><option value="">{{ de.choose }}</option><option v-for="competency in competencies" :key="competency.id" :value="competency.id">{{ competency.label }}{{ competency.unit ? ` · ${competency.unit}` : '' }}</option></select></template>
                <div class="small text-muted mt-2" v-if="!form.education_plan_id && form.competency_id">{{ de.competencies }}: {{ form.competency_id }}</div>
                <label class="form-label mt-3" for="assessment-task-points">{{ de.maxPoints }}</label>
                <input id="assessment-task-points" v-model="form.max_points" class="form-control" type="number" min="1">
                <template v-if="selectedCompetencyDifferentiated"><label class="form-label mt-3">{{ de.assessmentLevels }}</label><div class="d-flex gap-3"><label v-for="level in ['G', 'M', 'E']" :key="level" class="form-check"><input v-model="form.levels" class="form-check-input" type="checkbox" :value="level"><span class="form-check-label">{{ level }}</span></label></div></template>
            </form>
        </div>
        <CompetencyPickerModal v-model="competencyPickerOpen" :selected-ids="form.education_plan_competency_id ? [form.education_plan_competency_id] : []" :competency-text="competencyText" :endpoint="pickerEndpoint()" :exclude-process-competencies="true" :single="true" @apply="applyCompetency" />
    </AppShell>
</template>
