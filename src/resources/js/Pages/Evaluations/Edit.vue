<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import { useForm } from '@inertiajs/vue3'
import axios from 'axios'
import de from '../../i18n/de'
import { computed, onMounted, ref, watch } from 'vue'
import { observationScaleLabels } from '../Schools/schoolObservationScale'

const props = defineProps({ group: Object, evaluation: Object, lses: { type: Array, default: () => [] }, gradeComponents: { type: Array, default: () => [] }, periodLevel: { type: String, default: null }, customProcessCompetences: { type: Array, default: () => [] }, competencies: { type: Array, default: () => [] }, customProcessCompetenceScaleIntervalCount: { type: Number, default: 4 }, competenceAverages: { type: Array, default: () => [] }, previousEvaluation: { type: Object, default: null }, nextEvaluation: { type: Object, default: null } })
function competenceKey(competence) { return competence.education_plan_competency_id ?? competence.id }
function evaluationFormData(evaluation) { return { draft_text: evaluation.draft_text ?? '', teacher_note: evaluation.teacher_note ?? '', level: evaluation.level ?? props.periodLevel ?? '', status: evaluation.status ?? 'draft', observation_scales: (evaluation.observation_scales ?? []).map(scale => ({ custom_process_competence_id: scale.custom_process_competence_id, custom_scale_level: scale.custom_scale_level, custom_scale_status: scale.custom_scale_status })), competence_ratings: (evaluation.competence_ratings ?? []).map(rating => ({ education_plan_competency_id: rating.education_plan_competency_id, rating: rating.rating, include_in_text: rating.include_in_text ?? true, include_in_grade: rating.include_in_grade ?? true })) } }
const form = useForm(evaluationFormData(props.evaluation))
const draftGenerationProcessing = ref(false)
const gradeComponentResults = ref(props.gradeComponents)
watch(() => props.evaluation, evaluation => Object.assign(form, evaluationFormData(evaluation)))
const scaleLabels = computed(() => observationScaleLabels(props.customProcessCompetenceScaleIntervalCount))
const standardScaleLabels = [0, 1, 2, 3, 4, 5]
function selectedScale(competence) { return form.observation_scales.find(scale => scale.custom_process_competence_id === competence.id) }
function averageFor(competence) { return props.competenceAverages.find(average => average.custom_process_competence_id === competence.id) }
function standardAverageFor(competence) { return props.competenceAverages.find(average => average.education_plan_competency_id === competence.id) }
function gradeForPercentage(percentage) { if (percentage === null || percentage === undefined) return null; const grade = ['6', '6+', '5,5', '5-', '5', '5+', '4,5', '4-', '4', '4+', '3,5', '3-', '3', '3+', '2,5', '2-', '2', '2+', '1,5', '1-', '1'][Math.round(Math.max(0, Math.min(100, percentage)) / 5)]; return props.evaluation.period?.whole_grades ? String(Math.round(Number(grade.replace(',', '.').replace(/[+-]$/, '')))) : grade }
function finalGradePercentage() { const active = gradeComponentResults.value.filter(component => component.percentage !== null && component.percentage !== undefined); const weight = active.reduce((sum, component) => sum + component.weight, 0); return weight ? Math.round(active.reduce((sum, component) => sum + component.percentage * component.weight, 0) / weight) : null }
function selectedCompetenceSetting(competence) { return form.competence_ratings.find(rating => rating.education_plan_competency_id === competenceKey(competence)) }
function selectedStandardRating(competence) { const setting = selectedCompetenceSetting(competence); return setting?.rating === null || setting?.rating === undefined ? undefined : setting }
function ensureCompetenceSetting(competence) { const existing = selectedCompetenceSetting(competence); if (existing) return existing; const setting = { education_plan_competency_id: competenceKey(competence), rating: null, include_in_text: true, include_in_grade: true }; form.competence_ratings.push(setting); return setting }
function competenceText(competence) {
    const text = String(competence.level_texts?.[form.level] || competence.text || competence.label || '')
        .replace(/^Du kannst\s+/iu, '')
        .replace(/\s+/gu, ' ')
        .trim()
        .replace(/[.!?]+$/u, '')

    return `${props.evaluation.student.first_name} kann ${text}.`
}
function setScale(competence, label) { const scale = selectedScale(competence) ?? { custom_process_competence_id: competence.id, custom_scale_level: null, custom_scale_status: null }; scale.custom_scale_level = label === 'ne' ? null : scaleLabels.value.indexOf(label) + 1; scale.custom_scale_status = label === 'ne' ? 'ne' : null; if (!selectedScale(competence)) form.observation_scales.push(scale) }
function setStandardRating(competence, rating) {
    const selected = selectedCompetenceSetting(competence)
    if (selected?.rating === rating) {
        form.competence_ratings.splice(form.competence_ratings.indexOf(selected), 1)
        regenerateDraft()
        return
    }

    if (selected) {
        selected.rating = rating
        regenerateDraft()
        return
    }

    form.competence_ratings.push({ education_plan_competency_id: competenceKey(competence), rating })
    regenerateDraft()
}
function clearStandardRating(competence) {
    const selected = selectedCompetenceSetting(competence)
    if (selected) selected.rating = null
    regenerateDraft()
}
function toggleCompetenceSetting(competence, property) { const setting = ensureCompetenceSetting(competence); setting[property] = !setting[property]; regenerateDraft() }
function setAllRatingsToAverage() {
    if (props.group.grading_model === 'observation_scales') {
        props.customProcessCompetences.forEach(competence => {
            const average = averageFor(competence)?.rounded_level
            if (average === null || average === undefined) return
            setScale(competence, scaleLabels.value[average - 1])
        })
        return
    }

    props.competencies.forEach(competence => {
        const average = standardAverageFor(competence)?.rounded_level
        if (average === null || average === undefined) return
        ensureCompetenceSetting(competence).rating = average
    })
    regenerateDraft()
}
async function regenerateDraft() {
    if (props.group.grading_model !== 'competency_texts_and_grades') return
    if (props.evaluation.status === 'confirmed') return
    draftGenerationProcessing.value = true
    try {
        const response = await axios.post(`/unterrichtsgruppen/${props.group.id}/bewertungen/${props.evaluation.id}/entwurf`, { level: form.level || null, competence_ratings: form.competence_ratings }, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        form.draft_text = response.data.draft_text
        gradeComponentResults.value = response.data.grade_components ?? gradeComponentResults.value
    } finally {
        draftGenerationProcessing.value = false
    }
}
onMounted(() => { if (!form.draft_text) regenerateDraft() })
function save() { form.put(`/unterrichtsgruppen/${props.group.id}/bewertungen/${props.evaluation.id}`) }
function saveAndConfirm() { form.status = 'confirmed'; save() }
function formatDate(value) { const parts = String(value ?? '').slice(0, 10).split('-'); return parts.length === 3 && parts.every(Boolean) ? `${parts[2]}.${parts[1]}.${parts[0]}` : value }
</script>
<template>
    <AppShell>
        <template #toolbar>
            <a :href="`/unterrichtsgruppen/${group.id}/bewertungen`" class="btn btn-sm btn-light" :title="de.close" :aria-label="de.close"><i class="bi bi-x-lg" aria-hidden="true"></i></a>
            <button v-if="evaluation.status !== 'confirmed' || form.status !== 'confirmed'" class="btn btn-sm btn-primary ms-2" type="submit" form="evaluation-form" :disabled="form.processing || draftGenerationProcessing">{{ de.save }}</button>
            <button v-if="evaluation.status !== 'confirmed'" class="btn btn-sm btn-success ms-2" type="button" :disabled="form.processing || draftGenerationProcessing" @click="saveAndConfirm">{{ de.saveAndConfirm }}</button>
            <button v-if="competencies.length || customProcessCompetences.length" class="btn btn-sm btn-secondary ms-2" type="button" :disabled="evaluation.status === 'confirmed' || draftGenerationProcessing" @click="setAllRatingsToAverage">{{ de.evaluationSetAllRatingsToAverage }}</button>
            <a v-if="previousEvaluation" class="btn btn-sm btn-light ms-2" :href="`/unterrichtsgruppen/${group.id}/bewertungen/${previousEvaluation.id}/bearbeiten`" :title="de.previousStudent" :aria-label="de.previousStudent">← {{ de.previousStudent }}</a>
            <a v-if="nextEvaluation" class="btn btn-sm btn-light" :href="`/unterrichtsgruppen/${group.id}/bewertungen/${nextEvaluation.id}/bearbeiten`" :title="de.nextStudent" :aria-label="de.nextStudent">{{ de.nextStudent }} →</a>
        </template>
        <div class="container-full px-3 py-4">
            <h1 class="h2">{{ de.editEvaluation }}</h1>
            <p class="text-muted"><a :href="`/schueler:innen/${evaluation.student.id}`">{{ evaluation.student.last_name }}, {{ evaluation.student.first_name }}</a> · {{ evaluation.period.label }}</p>
            <label v-if="['competency_texts_and_grades', 'grades_only'].includes(group.grading_model)" class="form-label" for="evaluation-level">{{ de.evaluationPeriodLevel }}</label>
            <select v-if="['competency_texts_and_grades', 'grades_only'].includes(group.grading_model)" id="evaluation-level" v-model="form.level" class="form-select mb-3" :disabled="evaluation.status === 'confirmed'" @change="regenerateDraft"><option value="">{{ de.notSet }}</option><option v-for="level in ['G', 'M', 'E']" :key="level" :value="level">{{ level }}</option></select>
            <hr v-if="['competency_texts_and_grades', 'grades_only'].includes(group.grading_model)">
            <form id="evaluation-form" @submit.prevent="save">
                <div v-if="group.grading_model === 'competency_texts_and_grades' && competencies.length" class="mt-4"><h2 class="h5">{{ de.competenceDescriptions }}</h2><div class="table-responsive"><table id="evaluation-competencies-table" class="table table-sm align-top"><colgroup><col style="width: 42%"><col style="width: 23%"><col style="width: 18%"><col style="width: 9%"><col style="width: 8%"></colgroup><thead><tr><th>{{ de.competence }}</th><th>{{ de.evaluationCompetenceSources }}</th><th>{{ de.evaluationCompetenceAverage }}</th><th>{{ de.evaluationCompetenceText }}</th><th>{{ de.evaluationCompetenceGrade }}</th></tr></thead><tbody><tr v-for="competence in competencies" :key="competence.id"><td>{{ competenceText(competence) }}</td><td><div v-for="source in standardAverageFor(competence)?.sources" :key="source.text" class="small">{{ source.text }}</div><span v-if="!standardAverageFor(competence)?.sources?.length">–</span></td><td><div class="d-flex gap-2" :aria-label="de.competenceRatingScale"><button v-for="level in standardScaleLabels" :key="level" class="btn btn-sm" :class="selectedStandardRating(competence)?.rating === level ? 'btn-primary' : (!selectedStandardRating(competence) && standardAverageFor(competence)?.rounded_level === level ? 'bg-primary-subtle text-primary-emphasis border-primary' : 'btn-outline-secondary')" type="button" :disabled="evaluation.status === 'confirmed' || draftGenerationProcessing" :aria-pressed="selectedStandardRating(competence)?.rating === level" :aria-label="de.competenceRatingLevel(level)" @click="setStandardRating(competence, level)"><span v-if="level === 0">0</span><i v-else class="bi bi-star-fill" aria-hidden="true"></i></button><button class="btn btn-sm" :class="selectedStandardRating(competence) ? 'btn-outline-secondary' : 'btn-primary'" type="button" :disabled="evaluation.status === 'confirmed' || draftGenerationProcessing" :aria-pressed="!selectedStandardRating(competence)" :aria-label="de.notSet" @click="clearStandardRating(competence)">–</button></div><span v-if="standardAverageFor(competence)?.percentage !== null && standardAverageFor(competence)?.percentage !== undefined" class="small text-muted">{{ standardAverageFor(competence).percentage }}%</span></td><td><input class="form-check-input" type="checkbox" :checked="selectedCompetenceSetting(competence)?.include_in_text ?? true" :disabled="evaluation.status === 'confirmed' || draftGenerationProcessing" :aria-label="de.evaluationCompetenceText" @change="toggleCompetenceSetting(competence, 'include_in_text')"></td><td><input class="form-check-input" type="checkbox" :checked="selectedCompetenceSetting(competence)?.include_in_grade ?? true" :disabled="evaluation.status === 'confirmed' || draftGenerationProcessing" :aria-label="de.evaluationCompetenceGrade" @change="toggleCompetenceSetting(competence, 'include_in_grade')"></td></tr></tbody></table></div></div>
                <div v-if="customProcessCompetences.length" class="mt-4"><h2 class="h5">{{ de.schoolObservationScale }}</h2><div v-for="competence in customProcessCompetences" :key="competence.id" class="border-top py-3"><div class="mb-2">{{ competence.text }}</div><div class="d-flex flex-wrap gap-2"><button v-for="label in scaleLabels" :key="label" class="btn btn-sm" :class="selectedScale(competence)?.custom_scale_status === label || (label !== 'ne' && selectedScale(competence)?.custom_scale_level === scaleLabels.indexOf(label) + 1) ? 'btn-primary' : (averageFor(competence)?.rounded_level === scaleLabels.indexOf(label) + 1 ? 'bg-primary-subtle text-primary-emphasis border-primary' : 'btn-outline-secondary')" type="button" :disabled="evaluation.status === 'confirmed'" :aria-pressed="selectedScale(competence)?.custom_scale_status === label || (label !== 'ne' && selectedScale(competence)?.custom_scale_level === scaleLabels.indexOf(label) + 1)" @click="setScale(competence, label)">{{ label }}</button></div></div></div>
                <template v-if="group.grading_model === 'competency_texts_and_grades'"><label class="form-label mt-3" for="draft-text">{{ de.evaluationDraft }}</label><textarea id="draft-text" v-model="form.draft_text" class="form-control" rows="12" :disabled="evaluation.status === 'confirmed'"></textarea></template>
                <hr v-if="group.grading_model === 'competency_texts_and_grades'">
                <section v-if="['competency_texts_and_grades', 'grades_only'].includes(group.grading_model) && gradeComponentResults.length" class="mb-4" aria-labelledby="evaluation-grade-components-heading"><h2 id="evaluation-grade-components-heading" class="h5">{{ de.evaluationCompetenceGrade }}</h2><div v-if="!evaluation.student.receives_grades" class="alert alert-info" role="alert">{{ de.evaluationNoGradeAlert }}</div><table class="table table-sm align-top"><thead><tr><th>{{ de.evaluationGradeComponentHeader }}</th><th>{{ de.evaluationWeight }}</th><th>{{ de.percentage }}</th><th>{{ de.evaluationCompetenceGrade }}</th><th>{{ de.evaluationCompetenceSources }}</th></tr></thead><tbody><tr v-for="component in gradeComponentResults" :key="component.type"><td>{{ component.label }}</td><td>{{ component.weight }}%</td><td>{{ component.percentage === null ? '–' : `${component.percentage}%` }}</td><td>{{ component.grade || '–' }}</td><td><div v-for="source in component.sources" :key="source" class="small">{{ source }}</div><span v-if="!component.sources?.length">–</span></td></tr><tr><th>{{ de.evaluationFinalGrade }}</th><th colspan="2">{{ finalGradePercentage() === null ? '–' : `${finalGradePercentage()}%` }}</th><th>{{ gradeForPercentage(finalGradePercentage()) || '–' }}</th><td></td></tr></tbody></table></section>
                <hr v-if="group.grading_model !== 'observation_scales'">
                <label class="form-label mt-3" for="teacher-note">{{ de.internalNote }}</label><textarea id="teacher-note" v-model="form.teacher_note" class="form-control" rows="4" :disabled="evaluation.status === 'confirmed'"></textarea>
                <hr>
                <div class="form-check mt-3"><input id="evaluation-confirmed" v-model="form.status" class="form-check-input" type="checkbox" true-value="confirmed" false-value="draft"><label class="form-check-label" for="evaluation-confirmed">{{ de.confirmEvaluation }}</label></div>
            </form>
        </div>
    </AppShell>
</template>
