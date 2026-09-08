<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import de from '../../i18n/de'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    groups: { type: Array, default: () => [] },
    group: { type: Object, default: null },
    reportPeriods: { type: Array, default: () => [] },
})

function selectGroup(event) {
    router.get(`/unterrichtsgruppen/${event.target.value}/bewertungen`, {}, { preserveState: true, preserveScroll: true })
}

function observationScaleResult(scale) {
    if (scale.custom_scale_status === 'ne') {
        return 'ne'
    }

    if (scale.custom_scale_level === null || scale.custom_scale_level === undefined) {
        return '-'
    }

    return '+'.repeat(Number(scale.custom_scale_level))
}

function observationScaleText(evaluation, scale) {
    return `${evaluation.student.first_name} kann ${String(scale.competence_text_snapshot ?? '').replace(/:\s*$/u, '')}`
}
</script>

<template>
    <AppShell>
        <template #toolbar>
            <label class="visually-hidden" for="evaluations-group">{{ de.teachingGroup }}</label>
            <select id="evaluations-group" class="form-select form-select-sm" :value="group?.id ?? ''" :disabled="!groups.length" @change="selectGroup">
                <option v-for="option in groups" :key="option.id" :value="option.id">{{ option.name }}</option>
            </select>
            <a v-if="group && group.grading_model !== 'competency_texts_and_grades'" class="btn btn-sm btn-primary d-inline-flex align-items-center" :href="`/unterrichtsgruppen/${group.id}/bewertungen/neu`"><i class="bi bi-plus-lg d-inline me-1" aria-hidden="true"></i>Zeitraum</a>
        </template>
        <div class="container-full px-3 py-4">
            <h1 class="h2">{{ de.groupEvaluations }}</h1>
            <p v-if="group" class="text-muted">Bewertungszeiträume und bearbeitbare Entwürfe.</p>
            <div v-if="!group" class="text-muted">Noch keine Unterrichtsgruppe angelegt.</div>
            <template v-else>
                <div v-if="!reportPeriods.length" class="text-muted">Noch kein Bewertungszeitraum angelegt.</div>
                <div v-for="period in reportPeriods" :key="period.id" class="border-top py-3">
                    <strong>{{ period.label }}</strong>
                    <div v-if="!period.evaluations?.length" class="small text-muted mt-1">Keine Schüler:innen in diesem Zeitraum.</div>
                    <div v-for="evaluation in period.evaluations" :key="evaluation.id" class="d-flex justify-content-between align-items-start mt-2">
                        <div>
                            <span>{{ evaluation.student.last_name }}, {{ evaluation.student.first_name }}</span>
                            <span class="badge ms-2" :class="evaluation.status === 'confirmed' ? 'text-bg-success' : 'text-bg-light'">{{ evaluation.status === 'confirmed' ? 'bestätigt' : 'Entwurf' }}</span>
                            <p v-if="group.grading_model === 'competency_texts_and_grades'" class="mb-0 mt-1 small text-muted text-pre-wrap">{{ evaluation.draft_text || 'Noch kein Bewertungsentwurf.' }}</p>
                            <table v-else-if="evaluation.observation_scales?.length" class="evaluation-observation-scales table table-sm table-borderless w-100 mb-0 mt-1 small text-muted" style="table-layout: fixed;">
                                <colgroup><col style="width: 90%"><col style="width: 10%"></colgroup>
                                <tbody><tr v-for="scale in evaluation.observation_scales" :key="scale.id" class="evaluation-observation-scale"><td class="py-0">{{ observationScaleText(evaluation, scale) }}</td><td class="py-0 text-end">{{ observationScaleResult(scale) }}</td></tr></tbody>
                            </table>
                            <p v-if="['competency_texts_and_grades', 'grades_only'].includes(group.grading_model) && evaluation.status === 'confirmed' && evaluation.result_percentage !== null" class="mb-0 small text-muted">
                                {{ evaluation.result_percentage }}%
                                <span v-if="evaluation.result_grade">{{ evaluation.student.receives_grades ? evaluation.result_grade : `(${evaluation.result_grade})` }}</span>
                            </p>
                        </div>
                        <a class="btn btn-sm btn-outline-primary" :href="`/unterrichtsgruppen/${group.id}/bewertungen/${evaluation.id}/bearbeiten`">Bearbeiten</a>
                    </div>
                </div>
            </template>
        </div>
    </AppShell>
</template>
