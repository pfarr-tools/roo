<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import de from '../../i18n/de'
import { router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({ school: Object })
const showSchedule = ref(false)
const form = useForm({ name: props.school.name, short_name: props.school.short_name ?? '', city: props.school.city ?? '', notes: props.school.notes ?? '' })
const periods = useForm({ periods: Array.from({ length: 12 }, (_, index) => { const existing = (props.school.periods ?? []).find(period => period.period_number === index + 1); return { id: existing?.id ?? null, period_number: index + 1, starts_at: existing?.starts_at?.slice(0, 5) ?? '' } }) })
function endAt(start) { if (!start) return ''; const [hours, minutes] = start.split(':').map(Number); const total = hours * 60 + minutes + 45; return `${String(Math.floor(total / 60) % 24).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}` }
function save() { form.put(`/schulen/${props.school.slug}`) }
function savePeriods() { periods.transform(data => ({ periods: data.periods.filter(period => period.starts_at) })).put(`/schulen/${props.school.slug}/stundenraster`, { onSuccess: () => { showSchedule.value = false } }) }
function removeSchool() { if (window.confirm(de.deleteSchoolConfirm)) router.delete(`/schulen/${props.school.slug}`) }
</script>

<template>
    <AppShell>
        <template #toolbar><a href="/schulen" class="btn btn-sm btn-light" :title="de.close" :aria-label="de.close"><i class="bi bi-x-lg" aria-hidden="true"></i></a><button class="btn btn-sm btn-primary" type="button" :disabled="form.processing" @click="save"><i class="bi bi-check-lg" aria-hidden="true"></i><span class="d-none d-md-inline ms-1">{{ de.saveChanges }}</span></button><button class="btn btn-sm btn-outline-danger" type="button" @click="removeSchool"><i class="bi bi-trash" aria-hidden="true"></i><span class="d-none d-md-inline ms-1">{{ de.deleteSchool }}</span></button></template>
        <div class="container-full px-3 py-4"><div class="d-flex justify-content-between align-items-center mb-4"><h1 class="h2 mb-0">{{ de.editSchool }}: {{ school.name }}</h1></div>
            <form class="card card-body" @submit.prevent="save"><div class="row g-3"><div class="col-md-6"><label class="form-label">{{ de.schoolName }}</label><input v-model="form.name" class="form-control" required></div><div class="col-md-3"><label class="form-label">{{ de.shortName }}</label><input v-model="form.short_name" class="form-control"></div><div class="col-md-3"><label class="form-label">{{ de.city }}</label><input v-model="form.city" class="form-control"></div><div class="col-12"><label class="form-label">{{ de.notes }}</label><textarea v-model="form.notes" class="form-control" rows="3"></textarea></div></div></form>
            <section class="card card-body mt-4"><div class="d-flex justify-content-between align-items-center gap-3"><div><h2 class="h5 mb-1">{{ de.schoolSchedule }}</h2><p class="text-muted mb-0">{{ de.schoolScheduleIntro }}</p></div><button class="btn btn-outline-primary" type="button" @click="showSchedule = true">{{ de.editSchedule }}</button></div></section>
        </div>
        <div v-if="showSchedule" class="roo-modal-backdrop" role="presentation" @click.self="showSchedule = false"><section class="roo-modal" role="dialog" aria-modal="true" :aria-label="de.schoolSchedule"><div class="card border-0"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h5 mb-1">{{ de.schoolSchedule }}</h2><p class="text-muted mb-0">{{ de.schoolScheduleIntro }}</p></div><button class="btn-close" type="button" :aria-label="de.close" @click="showSchedule = false"></button></div><form @submit.prevent="savePeriods"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>{{ de.period }}</th><th>{{ de.from }}</th><th>{{ de.to }}</th></tr></thead><tbody><tr v-for="period in periods.periods" :key="period.period_number"><th>{{ period.period_number }}</th><td><input v-model="period.starts_at" type="time" class="form-control"></td><td class="text-muted">{{ endAt(period.starts_at) || '–' }}</td></tr></tbody></table></div><div class="d-flex justify-content-end gap-2 mt-3"><button class="btn btn-outline-secondary" type="button" @click="showSchedule = false">{{ de.cancel }}</button><button class="btn btn-primary" type="submit" :disabled="periods.processing">{{ de.saveSchedule }}</button></div></form></div></div></section></div>
    </AppShell>
</template>
