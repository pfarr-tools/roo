<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import { useForm } from '@inertiajs/vue3'
const props = defineProps({ group: Object })
const form = useForm({ label: '', starts_on: '', ends_on: '' })
function save() { form.post(`/unterrichtsgruppen/${props.group.id}/bewertungen/zeiträume`) }
</script>
<template><AppShell><template #toolbar><a :href="`/unterrichtsgruppen/${group.id}`" class="btn btn-sm btn-light" title="Schließen" aria-label="Schließen"><i class="bi bi-x-lg" aria-hidden="true"></i></a><button class="btn btn-sm btn-primary ms-2" type="submit" form="period-form" :disabled="form.processing">Speichern</button></template><div class="container-full px-3 py-4"><h1 class="h2">Bewertungszeitraum anlegen</h1><form id="period-form" class="card card-body" @submit.prevent="save"><label class="form-label">Bezeichnung</label><input v-model="form.label" class="form-control" placeholder="z. B. 1. Halbjahr" required><div class="row g-3 mt-1"><div class="col-md-6"><label class="form-label">Beginn</label><input v-model="form.starts_on" type="date" class="form-control" required></div><div class="col-md-6"><label class="form-label">Ende</label><input v-model="form.ends_on" type="date" class="form-control" required></div></div></form></div></AppShell></template>
