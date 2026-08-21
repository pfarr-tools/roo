<script setup>
import { useForm, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import AppShell from '../../Components/Ui/AppShell.vue'
import CompetencyPickerModal from '../../Components/Planning/CompetencyPickerModal.vue'
import de from '../../i18n/de'
import { requestConfirmation } from '../../utils/confirmation'
import { formatCompetencyIdentifier } from '../../utils/competencies'

const props = defineProps({
    backUrl: { type: String, required: true }, submitUrl: { type: String, required: true }, method: { type: String, default: 'post' },
    competencyId: { type: [String, Number], default: '' }, educationPlans: { type: Array, default: () => [] }, task: { type: Object, default: null },
    libraryMode: { type: Boolean, default: false }, competencies: { type: Array, default: () => [] }, competencyField: { type: String, default: 'teaching_unit_competency_id' }, assignedCompetency: { type: Object, default: null },
})

const taskTypes = Object.entries(de.assessmentTaskTypeLabels).filter(([value]) => value !== 'multiple_choice').map(([value, label]) => ({ value, label }))
const editorTab = ref('details')
const competencyPickerOpen = ref(false)
const selectedCompetencyText = ref('')
const selectedCompetencyNumber = ref('')
const selectedCompetencyWording = ref('')
const selectedCompetencyDifferentiated = ref(false)
const automaticExpectationCount = ref(0)
const automaticExpectationsEnabled = ref(true)
const emptyExpectation = () => ({ text: '', points: 1, repetitions: 1 })
const emptyContent = () => ({ prompt: '', lines: 5, lineated: false, reading_text: '', options: [{ text: '', correct: false }], columns: [''], rows: [{ label: '', answer: '' }], images: [{ url: '', label: '', answer: '' }], questions: [{ label: '', lines: 3 }], words: '', automatic_expectations: true })
const form = useForm({ title: '', task_type: 'free_text', content: emptyContent(), expectations: [emptyExpectation()], solution: '', competency_id: '', education_plan_id: '', education_plan_competency_id: '', levels: [] })

function resetForm() {
    const content = { ...emptyContent(), ...(props.task?.content ?? {}) }
    form.defaults({ title: props.task?.title ?? '', task_type: props.task?.task_type ?? 'free_text', content, expectations: props.task?.expectations?.length ? props.task.expectations.map(expectation => ({ text: expectation.text ?? '', points: expectation.points ?? 1, repetitions: expectation.repetitions ?? 1 })) : [emptyExpectation()], solution: props.task?.solution ?? '', competency_id: props.task?.teaching_unit_competency_id ?? props.competencyId ?? '', education_plan_id: props.task?.education_plan_id ?? '', education_plan_competency_id: props.task?.education_plan_competency_id ?? '', levels: props.task?.levels?.map(level => level.level ?? level) ?? [] })
    form.reset()
    if (props.task?.education_plan_competency) setSelectedCompetency(props.task.education_plan_competency)
    else if (props.assignedCompetency) setSelectedCompetency(props.assignedCompetency.education_plan_competency || props.assignedCompetency)
    else {
        selectedCompetencyNumber.value = ''
        selectedCompetencyWording.value = props.task?.competency ?? ''
        selectedCompetencyText.value = props.task?.competency ?? ''
    }
    selectedCompetencyDifferentiated.value = props.task?.has_differentiation ?? false
    automaticExpectationsEnabled.value = form.content.automatic_expectations !== false
    automaticExpectationCount.value = form.task_type === 'checkbox' && automaticExpectationsEnabled.value ? Math.min(form.expectations.length, form.content.options.length) : 0
    syncCheckboxExpectations()
}
watch(() => props.task?.id, resetForm, { immediate: true })

const typeLabel = value => de.assessmentTaskTypeLabels[value] || value
const usesOptions = value => ['checkbox', 'multiple_choice', 'matching_table'].includes(value)
const usesTable = value => ['fill_table', 'matching_table', 'subtask_table', 'image_answer_table', 'heading_table'].includes(value)
const usesImages = value => ['free_text_images', 'image_matching', 'image_labeling', 'image_answer_table'].includes(value)
const usesQuestions = value => ['labeled_fields', 'reading_text', 'sorting'].includes(value)
function selectType(value) {
    form.task_type = value
    if (supportsAutomaticExpectations(value)) {
        automaticExpectationsEnabled.value = true
        automaticExpectationCount.value = Math.min(form.expectations.length, form.content.options.length)
        syncCheckboxExpectations()
    }
    editorTab.value = 'content'
}
function addOption() { form.content.options.push({ text: '', correct: false }) }
function addRow() { form.content.rows.push({ label: '', answer: '' }) }
function addImage() { form.content.images.push({ url: '', label: '', answer: '' }) }
function addQuestion() { form.content.questions.push({ label: '', lines: 3 }) }
function addExpectation() { form.expectations.push(emptyExpectation()) }
function supportsAutomaticExpectations(value) { return value === 'checkbox' }
function checkboxExpectationText(option) { return `Du hast den folgenden Satz ${option.correct ? '' : 'nicht '}angekreuzt: ${option.text || ''} Das war richtig.` }
function syncCheckboxExpectations() {
    if (!supportsAutomaticExpectations(form.task_type) || !automaticExpectationsEnabled.value) return
    const currentExpectations = form.expectations
    const generatedExpectations = currentExpectations.slice(0, automaticExpectationCount.value)
    const additionalExpectations = currentExpectations.slice(automaticExpectationCount.value)
    form.expectations = [
        ...form.content.options.map((option, index) => ({ text: checkboxExpectationText(option), points: generatedExpectations[index]?.points ?? 1, repetitions: 1 })),
        ...additionalExpectations,
    ]
    automaticExpectationCount.value = form.content.options.length
}
watch(() => form.content.options, syncCheckboxExpectations, { deep: true })
function toggleAutomaticExpectations() {
    form.content.automatic_expectations = automaticExpectationsEnabled.value
    if (automaticExpectationsEnabled.value) {
        syncCheckboxExpectations()
    } else {
        form.expectations = form.expectations.slice(automaticExpectationCount.value)
        automaticExpectationCount.value = 0
    }
}
function isGeneratedCheckboxExpectation(index) { return supportsAutomaticExpectations(form.task_type) && automaticExpectationsEnabled.value && index < automaticExpectationCount.value }
async function handleAutomaticPointsChange(index, points) {
    if (!isGeneratedCheckboxExpectation(index)) return
    const value = Number(points)
    if (!Number.isFinite(value) || value < 1) return
    if (!await requestConfirmation({
        title: de.assessmentTaskApplyPointsTitle,
        message: de.assessmentTaskApplyPointsMessage,
        actions: [
            { value: true, label: de.assessmentTaskApplyPointsAll, variant: 'primary' },
            { value: false, label: de.assessmentTaskApplyPointsOnlyThis, variant: 'secondary' },
        ],
    })) return
    form.expectations.slice(0, automaticExpectationCount.value).forEach(expectation => { expectation.points = value })
}
function totalPoints() { return form.expectations.reduce((total, expectation) => total + (Number(expectation.points) || 0) * (Number(expectation.repetitions) || 0), 0) }
function expectationCount() { return form.expectations.filter(expectation => String(expectation.text || '').trim() !== '').length }
function setSelectedCompetency(competency) {
    const presentation = competency.competency_presentation || {}
    selectedCompetencyNumber.value = formatCompetencyIdentifier(presentation.identifier || competency.external_identifier || competency.number || '')
    selectedCompetencyWording.value = presentation.text || competency.text || competency.local_wording || (competency.variants || []).map(variant => variant.text).filter(Boolean).join(' / ') || ''
    selectedCompetencyText.value = competencyText(competency)
}
const competenceSummary = computed(() => `Du kannst ${selectedCompetencyWording.value || '…'}${selectedCompetencyNumber.value ? ` (${selectedCompetencyNumber.value})` : ''} [${totalPoints()} VP]`)
function removeAt(collection, index) { if (collection.length > 1) collection.splice(index, 1) }
function competencyText(competency) {
    const presentation = competency.competency_presentation || {}
    const number = presentation.identifier || competency.external_identifier || competency.number
    const variants = (competency.variants || []).map(variant => variant.text).filter(Boolean).join(' / ')
    const text = presentation.text || competency.text || competency.display || competency.local_wording || variants
    return text ? [number, text].filter(Boolean).join(' – ') : (presentation.label || ('Kompetenz ' + competency.id))
}
function pickerEndpoint() { return form.education_plan_id ? '/ressourcen/bibliothek/bildungsplaene/' + form.education_plan_id + '/kompetenzen' : '/ressourcen/bibliothek/bildungsplaene/0/kompetenzen' }
function applyCompetency(ids, selected = []) { form.education_plan_competency_id = ids[0] ?? ''; if (selected[0]) setSelectedCompetency(selected[0]); else { selectedCompetencyText.value = ''; selectedCompetencyNumber.value = ''; selectedCompetencyWording.value = '' }; selectedCompetencyDifferentiated.value = selected[0]?.has_differentiation ?? false }
function choosePlan() { form.education_plan_competency_id = ''; selectedCompetencyText.value = ''; selectedCompetencyNumber.value = ''; selectedCompetencyWording.value = ''; selectedCompetencyDifferentiated.value = false }
function save() {
    syncCheckboxExpectations()
    const payload = form.data()
    const content = { ...payload.content }
    if (!usesOptions(form.task_type)) delete content.options
    if (!usesTable(form.task_type)) {
        delete content.columns
        delete content.rows
    }
    if (!usesImages(form.task_type)) delete content.images
    if (!usesQuestions(form.task_type)) delete content.questions
    if (form.task_type !== 'reading_text') delete content.reading_text
    if (form.task_type !== 'sentence_builder') delete content.words
    if (!['free_text', 'free_text_images', 'reading_text'].includes(form.task_type)) {
        delete content.lines
        delete content.lineated
    }
    payload.content = content
    payload.expectations = payload.expectations.filter(expectation => String(expectation.text || '').trim() !== '')
    payload[props.competencyField] = payload.competency_id
    if (props.competencyField !== 'competency_id') delete payload.competency_id
    form.transform(() => payload)[props.method](props.submitUrl, { onSuccess: () => router.visit(props.backUrl) })
}
</script>

<template>
    <AppShell>
        <template #toolbar><a :href="backUrl" class="btn btn-sm btn-light" :title="de.close" :aria-label="de.close"><i class="bi bi-x-lg" aria-hidden="true"></i></a><button class="btn btn-sm btn-primary ms-2" type="submit" form="assessment-task-form" :disabled="form.processing"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ de.saveChanges }}</button></template>
        <div class="container-full px-3 py-4">
            <h1 class="h2">{{ task ? de.editAssessmentTask : de.newAssessmentTask }}</h1>
            <form id="assessment-task-form" novalidate @submit.prevent="save">
                <ul class="nav nav-tabs mb-4" role="tablist" :aria-label="de.assessmentTaskType">
                    <li class="nav-item" role="presentation"><button class="nav-link" :class="{ active: editorTab === 'details' }" type="button" role="tab" :aria-selected="editorTab === 'details'" @click="editorTab = 'details'">{{ de.assessmentTaskTabTask }}</button></li>
                    <li class="nav-item" role="presentation"><button class="nav-link" :class="{ active: editorTab === 'content' }" type="button" role="tab" :aria-selected="editorTab === 'content'" @click="editorTab = 'content'">{{ de.assessmentTaskTabContent }}</button></li>
                    <li class="nav-item" role="presentation"><button class="nav-link" :class="{ active: editorTab === 'expectations' }" type="button" role="tab" :aria-selected="editorTab === 'expectations'" @click="editorTab = 'expectations'">{{ de.assessmentTaskExpectations }} <span class="badge rounded-pill text-bg-secondary">{{ expectationCount() }}</span></button></li>
                </ul>
                <section v-show="editorTab === 'content'" class="row g-4" role="tabpanel">
                    <div class="col-3"><article class="card card-body h-100"><h2 class="h5">{{ de.assessmentTaskType }}</h2><p class="text-muted small">{{ de.assessmentTaskTypeHint }}</p><div class="row g-2"><div v-for="item in taskTypes" :key="item.value" class="col-12"><button type="button" class="btn w-100 text-start h-100 p-2" :class="form.task_type === item.value ? 'btn-primary' : 'btn-outline-secondary'" @click="selectType(item.value)"><span class="fw-semibold d-block small">{{ item.label }}</span></button></div></div></article></div>
                    <div class="col-9"><article class="card card-body h-100"><p class="mb-3 fw-semibold">{{ competenceSummary }}</p><h2 class="h5">{{ typeLabel(form.task_type) }}</h2>
                    <label class="form-label" for="assessment-task-prompt">{{ de.assessmentTaskPrompt }}</label><textarea id="assessment-task-prompt" v-model="form.content.prompt" class="form-control" rows="4" required></textarea>
                    <div v-if="form.task_type === 'reading_text'" class="mt-3"><label class="form-label">Lesetext</label><textarea v-model="form.content.reading_text" class="form-control" rows="10" required></textarea></div>
                    <div v-if="['free_text', 'free_text_images', 'reading_text'].includes(form.task_type)" class="mt-3"><label class="form-label">{{ de.assessmentTaskLines }}</label><div class="d-flex align-items-center gap-3"><input v-model="form.content.lines" type="number" min="0" class="form-control" style="max-width: 12rem"><label class="form-check mb-0"><input v-model="form.content.lineated" type="checkbox" class="form-check-input"><span class="form-check-label">{{ de.assessmentTaskLineation }}</span></label></div></div>
                    <div v-if="usesOptions(form.task_type)" class="mt-4"><h3 class="h6">{{ de.assessmentTaskOptions }}</h3><div v-for="(option, index) in form.content.options" :key="index" class="input-group mb-2"><input v-model="option.text" class="form-control" :placeholder="de.assessmentTaskOption" required><span class="input-group-text"><input v-model="option.correct" type="checkbox" class="form-check-input me-2">{{ de.assessmentTaskCorrect }}</span><button type="button" class="btn btn-outline-danger" @click="removeAt(form.content.options, index)">×</button></div><button type="button" class="btn btn-sm btn-outline-secondary" @click="addOption">{{ de.assessmentTaskAddOption }}</button></div>
                    <div v-if="usesTable(form.task_type)" class="mt-4"><h3 class="h6">{{ de.assessmentTaskColumns }}</h3><div v-for="(column, index) in form.content.columns" :key="index" class="input-group mb-2"><input v-model="form.content.columns[index]" class="form-control" placeholder="Spaltenüberschrift" required><button type="button" class="btn btn-outline-danger" @click="removeAt(form.content.columns, index)">×</button></div><button type="button" class="btn btn-sm btn-outline-secondary mb-3" @click="form.content.columns.push('')">Spalte hinzufügen</button><h3 class="h6">{{ de.assessmentTaskRows }}</h3><div v-for="(row, index) in form.content.rows" :key="index" class="row g-2 mb-2"><div class="col-md-5"><input v-model="row.label" class="form-control" placeholder="Zeile / Teilaufgabe" required></div><div class="col-md-6"><input v-model="row.answer" class="form-control" :placeholder="de.assessmentTaskAnswer"></div><div class="col-md-1"><button type="button" class="btn btn-outline-danger" @click="removeAt(form.content.rows, index)">×</button></div></div><button type="button" class="btn btn-sm btn-outline-secondary" @click="addRow">{{ de.assessmentTaskAddRow }}</button></div>
                    <div v-if="usesImages(form.task_type)" class="mt-4"><h3 class="h6">Bilder</h3><div v-for="(image, index) in form.content.images" :key="index" class="border rounded p-3 mb-2"><div class="row g-2"><div class="col-md-5"><label class="form-label">{{ de.assessmentTaskImageUrl }}</label><input v-model="image.url" class="form-control" type="url" required></div><div class="col-md-3"><label class="form-label">{{ de.assessmentTaskFieldLabel }}</label><input v-model="image.label" class="form-control"></div><div class="col-md-3"><label class="form-label">{{ de.assessmentTaskAnswer }}</label><input v-model="image.answer" class="form-control"></div><div class="col-md-1 d-flex align-items-end"><button type="button" class="btn btn-outline-danger" @click="removeAt(form.content.images, index)">×</button></div></div></div><button type="button" class="btn btn-sm btn-outline-secondary" @click="addImage">Bild hinzufügen</button></div>
                    <div v-if="form.task_type === 'sentence_builder'" class="mt-4"><label class="form-label">{{ de.assessmentTaskWords }}</label><input v-model="form.content.words" class="form-control" placeholder="Wort 1, Wort 2, Wort 3" required></div>
                    <div v-if="usesQuestions(form.task_type)" class="mt-4"><h3 class="h6">{{ de.assessmentTaskQuestions }}</h3><div v-for="(question, index) in form.content.questions" :key="index" class="input-group mb-2"><input v-model="question.label" class="form-control" :placeholder="de.assessmentTaskFieldLabel" required><input v-model="question.lines" class="form-control" type="number" min="0" placeholder="Linien"><button type="button" class="btn btn-outline-danger" @click="removeAt(form.content.questions, index)">×</button></div><button type="button" class="btn btn-sm btn-outline-secondary" @click="addQuestion">{{ de.assessmentTaskAddQuestion }}</button></div>
                    </article></div>
                </section>
                <section v-show="editorTab === 'details'" class="card card-body" role="tabpanel">
                    <label class="form-label" for="assessment-task-title">{{ de.assessmentTaskTitle }}</label><input id="assessment-task-title" v-model="form.title" class="form-control" required>
                    <template v-if="!libraryMode || task || form.education_plan_id"><label class="form-label mt-3" for="assessment-task-plan">{{ de.educationPlan }}</label><select id="assessment-task-plan" v-model="form.education_plan_id" class="form-select" required @change="choosePlan"><option value="">{{ de.choose }}</option><option v-for="plan in educationPlans" :key="plan.id" :value="plan.id">{{ plan.title }}</option></select><label class="form-label mt-3">{{ de.competency }}</label><div class="row g-2 align-items-center"><div class="col-11"><div class="form-control-plaintext py-2">{{ selectedCompetencyText || de.choose }}</div></div><div class="col-1"><button class="btn btn-outline-primary w-100" type="button" :disabled="!form.education_plan_id" @click="competencyPickerOpen = true">{{ de.assessmentTaskSelectCompetency }}</button></div></div></template>
                    <template v-else><label class="form-label mt-3" for="assessment-task-library-competency">{{ de.competencies }}</label><select id="assessment-task-library-competency" v-model="form.competency_id" class="form-select" required><option value="">{{ de.choose }}</option><option v-for="competency in competencies" :key="competency.id" :value="competency.id">{{ competency.label }}{{ competency.unit ? ` · ${competency.unit}` : '' }}</option></select></template>
                    <template v-if="selectedCompetencyDifferentiated"><label class="form-label mt-3">{{ de.assessmentLevels }}</label><div class="d-flex gap-3"><label v-for="level in ['G', 'M', 'E']" :key="level" class="form-check"><input v-model="form.levels" class="form-check-input" type="checkbox" :value="level"><span class="form-check-label">{{ level }}</span></label></div></template>
                </section>
                <section v-show="editorTab === 'expectations'" class="card card-body" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h5 mb-1">{{ de.assessmentTaskExpectations }}</h2><p class="text-muted mb-0">{{ totalPoints() }} {{ de.assessmentTaskPoints }}</p></div><button type="button" class="btn btn-sm btn-outline-primary" @click="addExpectation"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ de.assessmentTaskAddExpectation }}</button></div>
                    <label v-if="supportsAutomaticExpectations(form.task_type)" class="form-check mb-3"><input v-model="automaticExpectationsEnabled" class="form-check-input" type="checkbox" @change="toggleAutomaticExpectations"><span class="form-check-label">{{ de.assessmentTaskAutomaticExpectations }}</span></label>
                    <div v-for="(expectation, index) in form.expectations" :key="index" class="row g-2 align-items-end mb-3"><div class="col-md-7"><label class="form-label">{{ de.assessmentTaskExpectation }}</label><textarea v-model="expectation.text" class="form-control" rows="2" :disabled="isGeneratedCheckboxExpectation(index)" required></textarea></div><div class="col-md-2"><label class="form-label">{{ de.assessmentTaskPoints }}</label><input v-model="expectation.points" class="form-control" type="number" min="1" required @change="handleAutomaticPointsChange(index, expectation.points)"></div><div class="col-md-2"><label class="form-label">{{ de.assessmentTaskRepetitions }}</label><input v-model="expectation.repetitions" class="form-control" type="number" min="1" :disabled="isGeneratedCheckboxExpectation(index)" required></div><div class="col-md-1"><button v-if="!isGeneratedCheckboxExpectation(index)" type="button" class="btn btn-outline-danger" :disabled="form.expectations.length === 1" :aria-label="de.remove" @click="removeAt(form.expectations, index)"><i class="bi bi-trash" aria-hidden="true"></i></button></div></div>
                </section>
            </form>
        </div>
        <CompetencyPickerModal v-model="competencyPickerOpen" :selected-ids="form.education_plan_competency_id ? [form.education_plan_competency_id] : []" :competency-text="competencyText" :endpoint="pickerEndpoint()" :exclude-process-competencies="true" :single="true" @apply="applyCompetency" />
    </AppShell>
</template>
