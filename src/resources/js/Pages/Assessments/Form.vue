<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({ group: Object, assessment: { type: Object, default: null }, slot: { type: Object, default: null }, returnTab: { type: String, default: 'assessments' }, returnTo: { type: String, default: 'group' } })
const form = useForm({ title: props.assessment?.title ?? '', report_period_id: props.assessment?.report_period_id ?? '', return_tab: props.returnTab, return_to: props.returnTo, notes: props.assessment?.notes ?? '' })
function formatDate(value) { const parts = String(value ?? '').slice(0, 10).split('-'); return parts.length === 3 && parts.every(Boolean) ? `${parts[2]}.${parts[1]}.${parts[0]}` : value }
function save() { const url = props.assessment ? `/unterrichtsgruppen/${props.group.id}/lernstandserhebungen/${props.assessment.id}` : `/unterrichtsgruppen/${props.group.id}/lernstandserhebungen`; form[props.assessment ? 'put' : 'post'](url) }
</script>
<template>
    <AppShell><template #toolbar><a :href="returnTo === 'year-plan' ? `/jahresplanung/${group.id}` : `/unterrichtsgruppen/${group.id}?tab=${returnTab}`" class="btn btn-sm btn-light" title="Schließen" aria-label="Schließen"><i class="bi bi-x-lg" aria-hidden="true"></i></a><button class="btn btn-sm btn-primary ms-2" type="submit" form="assessment-form" :disabled="form.processing">Speichern</button></template><div class="container-full px-3 py-4"><h1 class="h2 mb-1">{{ assessment ? 'Lernstandserhebung bearbeiten' : 'Neue Lernstandserhebung' }}</h1><p v-if="slot?.date" class="text-muted mb-4">{{ formatDate(slot.date) }}</p><form id="assessment-form" class="card card-body" @submit.prevent="save"><label class="form-label" for="assessment-title">Titel</label><input id="assessment-title" v-model="form.title" class="form-control" required><label class="form-label mt-3" for="assessment-notes">Notizen</label><textarea id="assessment-notes" v-model="form.notes" class="form-control" rows="3"></textarea></form></div></AppShell>
</template>
