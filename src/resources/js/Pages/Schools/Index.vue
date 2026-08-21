<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import de from '../../i18n/de'

const props = defineProps({ schools: Array })
const showSchoolForm = ref(false)
const showYearForm = ref(false)
const schoolForm = useForm({ name: '', short_name: '', school_type: '', city: '', notes: '' })
const yearForm = useForm({ school_id: '', starts_on: '', ends_on: '', timezone: 'Europe/Berlin' })

const schoolYearName = computed(() => {
    const year = Number.parseInt(String(yearForm.starts_on).slice(0, 4), 10)

    return Number.isInteger(year) ? `${year}/${String((year + 1) % 100).padStart(2, '0')}` : ''
})

function createSchool() { schoolForm.post('/schulen', { onSuccess: () => { schoolForm.reset(); showSchoolForm.value = false } }) }
function createYear() {
    yearForm.transform(data => ({ ...data, name: schoolYearName.value })).post('/schuljahre', { onSuccess: () => { yearForm.reset(); showYearForm.value = false } })
}
function openYearForm(schoolId) { yearForm.school_id = schoolId; showYearForm.value = true }
</script>

<style scoped>
.school-edit-button {
    min-height: 31px;
}
</style>

<template>
    <AppShell>
    <template #toolbar>
        <button class="btn btn-sm btn-primary" type="button" @click="showSchoolForm = true">{{ de.addSchool }}</button>
    </template>
    <div class="container-full px-3 py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div><h1 class="h2 mb-0">{{ de.schools }}</h1></div>
        </div>
        <div v-if="showSchoolForm" class="roo-modal-backdrop" role="presentation" @click.self="showSchoolForm = false"><section class="roo-modal" role="dialog" aria-modal="true" :aria-label="de.addSchool"><div class="card border-0"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">{{ de.addSchool }}</h2><button class="btn-close" type="button" :aria-label="de.close" @click="showSchoolForm = false"></button></div><form @submit.prevent="createSchool"><div class="row g-3"><div class="col-md-6"><label class="form-label">{{ de.schoolName }}</label><input v-model="schoolForm.name" class="form-control" required></div><div class="col-md-3"><label class="form-label">{{ de.shortName }}</label><input v-model="schoolForm.short_name" class="form-control"></div><div class="col-md-3"><label class="form-label">{{ de.city }}</label><input v-model="schoolForm.city" class="form-control"></div></div><div class="d-flex justify-content-end gap-2 mt-4"><button class="btn btn-outline-secondary" type="button" @click="showSchoolForm = false">{{ de.cancel }}</button><button class="btn btn-primary" type="submit" :disabled="schoolForm.processing">{{ de.save }}</button></div></form></div></div></section></div>
        <div v-if="showYearForm" class="roo-modal-backdrop" role="presentation" @click.self="showYearForm = false"><section class="roo-modal" role="dialog" aria-modal="true" :aria-label="de.addSchoolYear"><div class="card border-0"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">{{ de.addSchoolYear }}</h2><button class="btn-close" type="button" :aria-label="de.close" @click="showYearForm = false"></button></div><form @submit.prevent="createYear"><div class="row g-3"><div class="col-md-4"><label class="form-label">{{ de.from }}</label><input v-model="yearForm.starts_on" type="date" class="form-control" required></div><div class="col-md-4"><label class="form-label">{{ de.to }}</label><input v-model="yearForm.ends_on" type="date" class="form-control" required></div><div class="col-md-4"><span class="form-label d-block">{{ de.schoolYearName }}</span><div class="form-control-plaintext" aria-live="polite">{{ schoolYearName || de.notAvailable }}</div></div></div><div class="d-flex justify-content-end gap-2 mt-4"><button class="btn btn-outline-secondary" type="button" @click="showYearForm = false">{{ de.cancel }}</button><button class="btn btn-primary" :disabled="yearForm.processing">{{ de.save }}</button></div></form></div></div></section></div>
        <div v-if="!props.schools.length" class="alert alert-info">{{ de.noSchools }}</div>
        <div v-for="school in props.schools" :key="school.id" class="card mb-3"><div class="card-body"><div class="d-flex justify-content-between"><div><h2 class="h5 mb-1">{{ school.name }}</h2><span class="text-muted">{{ [school.short_name, school.city].filter(Boolean).join(' · ') }}</span></div><a :href="`/schulen/${school.slug}`" class="btn btn-sm btn-outline-primary school-edit-button">{{ de.editSchool }}</a></div><div class="mt-3 d-flex flex-wrap align-items-center gap-2"><span v-if="!school.school_years.length" class="text-muted">{{ de.noSchoolYears }}</span><a v-for="year in school.school_years" :key="year.id" class="btn btn-sm btn-outline-secondary" :href="`/schulen/${school.slug}/${year.slug}`">{{ year.name }}</a><button class="btn btn-sm btn-outline-primary" type="button" @click="openYearForm(school.id)"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ de.addSchoolYear }}</button></div></div></div>
    </div>
    </AppShell>
</template>
