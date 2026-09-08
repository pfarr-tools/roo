<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import { useForm } from '@inertiajs/vue3'
const props = defineProps({ group: Object, period: { type: Object, default: null } })
const numericGrades = props.group.grading_model === 'grades_only' || (props.group.grading_model === 'competency_texts_and_grades' && props.group.numeric_grades_enabled)
function dateInputValue(value) { return value ? String(value).slice(0, 10) : '' }
const form = useForm({ label: props.period?.label ?? '', starts_on: dateInputValue(props.period?.starts_on), ends_on: dateInputValue(props.period?.ends_on), whole_grades: Boolean(props.period?.whole_grades), include_full_school_year: Boolean(props.period?.include_full_school_year) })
function save() {
    const url = props.period
        ? `/unterrichtsgruppen/${props.group.id}/bewertungen/zeiträume/${props.period.id}`
        : `/unterrichtsgruppen/${props.group.id}/bewertungen/zeiträume`
    props.period ? form.put(url) : form.post(url)
}
</script>
<template><AppShell><template #toolbar><a :href="`/unterrichtsgruppen/${group.id}?tab=evaluations`" class="btn btn-sm btn-light" title="Schließen" aria-label="Schließen"><i class="bi bi-x-lg" aria-hidden="true"></i></a><button class="btn btn-sm btn-primary ms-2" type="submit" form="period-form" :disabled="form.processing">Speichern</button></template><div class="container-full px-3 py-4"><h1 class="h2">{{ period ? 'Bewertungszeitraum bearbeiten' : 'Bewertungszeitraum anlegen' }}</h1><form id="period-form" class="card card-body" @submit.prevent="save"><label class="form-label">Bezeichnung</label><input v-model="form.label" class="form-control" placeholder="z. B. 1. Halbjahr" required><div class="row g-3 mt-1"><div class="col-md-6"><label class="form-label">Beginn</label><input v-model="form.starts_on" type="date" class="form-control" required></div><div class="col-md-6"><label class="form-label">Ende</label><input v-model="form.ends_on" type="date" class="form-control" required></div></div><template v-if="numericGrades"><div class="form-check mt-3"><input id="period-whole-grades" v-model="form.whole_grades" class="form-check-input" type="checkbox"><label class="form-check-label" for="period-whole-grades">Nur ganze Noten</label></div><div class="form-check mt-2"><input id="period-full-school-year" v-model="form.include_full_school_year" class="form-check-input" type="checkbox"><label class="form-check-label" for="period-full-school-year">Ganzes Schuljahr mit einbeziehen</label></div></template></form></div></AppShell></template>
