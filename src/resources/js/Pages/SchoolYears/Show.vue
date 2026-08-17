<script setup>
import { useForm } from '@inertiajs/vue3'
import de from '../../i18n/de'
const props = defineProps({ schoolYear: Object, days: Array })
const holidayForm = useForm({ name: '', starts_on: '', ends_on: '', change_reason: '' })
const exceptionForm = useForm({ date: '', kind: 'no_instruction', label: '', change_reason: '' })
function addHoliday() { holidayForm.post(`/schuljahre/${props.schoolYear.slug}/ferien`, { onSuccess: () => holidayForm.reset() }) }
function addException() { exceptionForm.post(`/schuljahre/${props.schoolYear.slug}/ausnahmen`, { onSuccess: () => exceptionForm.reset('date', 'label', 'change_reason') }) }
function importHolidays() { useForm({}).post(`/schuljahre/${props.schoolYear.slug}/ferien/importieren`) }
function statusLabel(kind) { return de.statuses[kind] ?? kind }
function formatDate(value) { const date = String(value).slice(0, 10).split('-'); return date.length === 3 ? `${date[2]}.${date[1]}.${date[0]}` : value }
</script>

<template>
    <main class="container py-4"><a href="/schulen" class="text-decoration-none">{{ de.schools }}</a><h1 class="h2 mt-2">{{ schoolYear.school.name }} – {{ schoolYear.name }}</h1><p class="text-muted">{{ formatDate(schoolYear.starts_on) }} bis {{ formatDate(schoolYear.ends_on) }}</p>
        <div v-if="$page.props.flash?.success" class="alert alert-success">{{ $page.props.flash.success }}</div>
        <div class="d-flex gap-2 mb-4"><button class="btn btn-primary" @click="importHolidays">{{ de.importHolidays }}</button></div>
        <div class="row g-4"><div class="col-lg-6"><form class="card card-body" @submit.prevent="addHoliday"><h2 class="h5">{{ de.localHoliday }}</h2><input v-model="holidayForm.name" class="form-control mb-2" :placeholder="de.holidayName" required><div class="row g-2"><div class="col"><input v-model="holidayForm.starts_on" type="date" class="form-control" required></div><div class="col"><input v-model="holidayForm.ends_on" type="date" class="form-control" required></div></div><input v-model="holidayForm.change_reason" class="form-control mt-2" :placeholder="de.changeReason"><button class="btn btn-outline-primary mt-3">{{ de.add }}</button></form></div><div class="col-lg-6"><form class="card card-body" @submit.prevent="addException"><h2 class="h5">{{ de.calendarException }}</h2><div class="row g-2"><div class="col"><input v-model="exceptionForm.date" type="date" class="form-control" required></div><div class="col"><select v-model="exceptionForm.kind" class="form-select"><option value="no_instruction">{{ de.noInstruction }}</option><option value="instruction">{{ de.instruction }}</option><option value="holiday">{{ de.holiday }}</option></select></div></div><input v-model="exceptionForm.label" class="form-control mt-2" :placeholder="de.label" required><input v-model="exceptionForm.change_reason" class="form-control mt-2" :placeholder="de.changeReason"><button class="btn btn-outline-primary mt-3">{{ de.add }}</button></form></div></div>
        <h2 class="h5 mt-5">{{ de.calendar }}</h2><div class="table-responsive"><table class="table table-sm"><thead><tr><th>{{ de.date }}</th><th>{{ de.status }}</th><th>{{ de.label }}</th></tr></thead><tbody><tr v-for="day in days" :key="day.id"><td>{{ formatDate(day.date) }}</td><td>{{ statusLabel(day.kind) }}</td><td>{{ day.label }}</td></tr></tbody></table></div>
    </main>
</template>
