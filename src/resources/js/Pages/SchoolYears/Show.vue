<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import { useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import de from '../../i18n/de'

const props = defineProps({ schoolYear: Object, days: Array })
const editingDay = ref(null)
const dayForm = useForm({ kind: '', label: '', notes: '' })

const baseUrl = `/schulen/${props.schoolYear.school.slug}/${props.schoolYear.slug}`
function importHolidays() { useForm({}).post(`${baseUrl}/ferien/importieren`) }
function editDay(day) {
    editingDay.value = day
    dayForm.kind = day.kind
    dayForm.label = day.label ?? ''
    dayForm.notes = day.notes ?? ''
    dayForm.clearErrors()
}
function closeDayForm() { editingDay.value = null; dayForm.reset(); dayForm.clearErrors() }
function saveDay() { dayForm.put(`${baseUrl}/tage/${editingDay.value.id}`, { onSuccess: closeDayForm }) }
function updateDayStatus(day, kind) {
    if (day.kind === kind) return
    useForm({ kind, label: day.label ?? '', notes: day.notes ?? '' }).put(`${baseUrl}/tage/${day.id}`)
}
function statusLabel(kind) { return de.statuses[kind] ?? kind }
function formatDate(value) { const date = String(value).slice(0, 10).split('-'); return date.length === 3 ? `${date[2]}.${date[1]}.${date[0]}` : value }
</script>

<template>
    <AppShell>
        <template #toolbar><a href="/schulen" class="btn btn-sm btn-light" :title="de.close" :aria-label="de.close"><i class="bi bi-x-lg" aria-hidden="true"></i></a><button class="btn btn-sm btn-primary" type="button" @click="importHolidays"><i class="bi bi-cloud-download me-1" aria-hidden="true"></i>{{ de.importHolidays }}</button></template>
        <div class="container-full px-3 py-4">
            <a href="/schulen" class="text-decoration-none">{{ de.schools }}</a>
            <h1 class="h2 mt-2">{{ schoolYear.school.name }} – {{ schoolYear.name }}</h1>
            <p class="text-muted">{{ formatDate(schoolYear.starts_on) }} bis {{ formatDate(schoolYear.ends_on) }}</p>
            <div v-if="$page.props.flash?.success" class="alert alert-success">{{ $page.props.flash.success }}</div>
            <h2 class="h5 mt-5">{{ de.calendar }}</h2>
            <div class="table-responsive"><table class="table table-sm align-middle">
                <thead><tr><th>{{ de.date }}</th><th>{{ de.status }}</th><th>{{ de.label }}</th><th>{{ de.dayNotes }}</th><th></th></tr></thead>
                <tbody><tr v-for="day in days" :key="day.id"><td>{{ formatDate(day.date) }}</td><td><div v-if="day.kind !== 'weekend'" class="d-flex align-items-center justify-content-between gap-2"><span>{{ statusLabel(day.kind) }}</span><span class="d-inline-flex gap-1"><button class="btn btn-sm btn-outline-secondary p-1 lh-1" type="button" :disabled="day.kind === 'instruction'" :title="de.setInstructionDay" @click="updateDayStatus(day, 'instruction')"><i class="bi bi-book" aria-hidden="true"></i><span class="visually-hidden">{{ de.setInstructionDay }}</span></button><button class="btn btn-sm btn-outline-secondary p-1 lh-1" type="button" :disabled="day.kind === 'holiday'" :title="de.setHolidayDay" @click="updateDayStatus(day, 'holiday')"><i class="bi bi-brightness-high" aria-hidden="true"></i><span class="visually-hidden">{{ de.setHolidayDay }}</span></button><button class="btn btn-sm btn-outline-secondary p-1 lh-1" type="button" :disabled="day.kind === 'no_instruction'" :title="de.setNoInstructionDay" @click="updateDayStatus(day, 'no_instruction')"><i class="bi bi-slash-circle" aria-hidden="true"></i><span class="visually-hidden">{{ de.setNoInstructionDay }}</span></button></span></div></td><td>{{ day.label }}</td><td class="text-muted">{{ day.notes }}</td><td class="text-end"><button v-if="day.kind !== 'weekend'" class="btn btn-sm btn-outline-secondary" type="button" @click="editDay(day)"><i class="bi bi-pencil" aria-hidden="true"></i><span class="visually-hidden">{{ de.editDay }}</span></button></td></tr></tbody>
            </table></div>
        </div>
        <div v-if="editingDay" class="roo-modal-backdrop" role="presentation" @click.self="closeDayForm"><section class="roo-modal" role="dialog" aria-modal="true" :aria-label="de.editDay"><div class="card border-0"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">{{ de.editDay }} – {{ formatDate(editingDay.date) }}</h2><button class="btn-close" type="button" :aria-label="de.close" @click="closeDayForm"></button></div>
            <form @submit.prevent="saveDay">
                <label class="form-label" for="day-kind">{{ de.status }}</label><select id="day-kind" v-model="dayForm.kind" class="form-select" :class="{ 'is-invalid': dayForm.errors.kind }"><option value="no_instruction">{{ de.noInstruction }}</option><option value="instruction">{{ de.instruction }}</option><option value="holiday">{{ de.holiday }}</option></select><div v-if="dayForm.errors.kind" class="invalid-feedback">{{ dayForm.errors.kind }}</div>
                <label class="form-label mt-3" for="day-label">{{ de.label }}</label><input id="day-label" v-model="dayForm.label" class="form-control" :class="{ 'is-invalid': dayForm.errors.label }"><div v-if="dayForm.errors.label" class="invalid-feedback">{{ dayForm.errors.label }}</div>
                <label class="form-label mt-3" for="day-notes">{{ de.dayNotes }}</label><textarea id="day-notes" v-model="dayForm.notes" class="form-control" rows="4" :class="{ 'is-invalid': dayForm.errors.notes }"></textarea><div v-if="dayForm.errors.notes" class="invalid-feedback">{{ dayForm.errors.notes }}</div>
                <div class="d-flex justify-content-end gap-2 mt-4"><button class="btn btn-outline-secondary" type="button" @click="closeDayForm">{{ de.cancel }}</button><button class="btn btn-primary" type="submit" :disabled="dayForm.processing">{{ de.save }}</button></div>
            </form>
        </div></div></section></div>
    </AppShell>
</template>
