<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({ group: Object, assessment: { type: Object, default: null } })
const form = useForm({ title: props.assessment?.title ?? '', report_period_id: props.assessment?.report_period_id ?? '', assessed_on: props.assessment?.assessed_on ?? '', notes: props.assessment?.notes ?? '' })
function save() { const url = props.assessment ? `/unterrichtsgruppen/${props.group.id}/lernstandserhebungen/${props.assessment.id}` : `/unterrichtsgruppen/${props.group.id}/lernstandserhebungen`; form[props.assessment ? 'put' : 'post'](url) }
</script>
<template>
    <AppShell><template #toolbar><a :href="`/unterrichtsgruppen/${group.id}`" class="btn btn-sm btn-light" title="Schließen" aria-label="Schließen"><i class="bi bi-x-lg" aria-hidden="true"></i></a><button class="btn btn-sm btn-primary ms-2" type="submit" form="assessment-form" :disabled="form.processing">Speichern</button></template><div class="container-full px-3 py-4"><h1 class="h2">{{ assessment ? 'Lernstandserhebung bearbeiten' : 'Neue Lernstandserhebung' }}</h1><form id="assessment-form" class="card card-body" @submit.prevent="save"><label class="form-label" for="assessment-title">Titel</label><input id="assessment-title" v-model="form.title" class="form-control" required><label class="form-label mt-3" for="assessment-date">Zeitraum / Datum</label><input id="assessment-date" v-model="form.assessed_on" type="date" class="form-control"><label class="form-label mt-3" for="assessment-notes">Notizen</label><textarea id="assessment-notes" v-model="form.notes" class="form-control" rows="3"></textarea></form></div></AppShell>
</template>
