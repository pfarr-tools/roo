<script setup>
import { reactive, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import de from '../../i18n/de'

const props = defineProps({ schools: Array })
const showSchoolForm = ref(false)
const showYearForm = ref(false)
const schoolForm = useForm({ name: '', short_name: '', school_type: '', city: '', notes: '' })
const yearForm = useForm({ school_id: '', name: '', starts_on: '', ends_on: '', timezone: 'Europe/Berlin' })

function createSchool() { schoolForm.post('/schulen', { onSuccess: () => { schoolForm.reset(); showSchoolForm.value = false } }) }
function createYear() { yearForm.post('/schuljahre', { onSuccess: () => { yearForm.reset(); showYearForm.value = false } }) }
</script>

<template>
    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div><a href="/dashboard" class="text-decoration-none">{{ de.dashboard }}</a><h1 class="h2 mt-2">{{ de.schools }}</h1></div>
            <div class="d-flex gap-2"><button class="btn btn-outline-primary" @click="showYearForm = !showYearForm">{{ de.addSchoolYear }}</button><button class="btn btn-primary" @click="showSchoolForm = !showSchoolForm">{{ de.addSchool }}</button></div>
        </div>
        <div v-if="$page.props.flash?.success" class="alert alert-success">{{ $page.props.flash.success }}</div>
        <form v-if="showSchoolForm" class="card card-body mb-4" @submit.prevent="createSchool">
            <h2 class="h5">{{ de.addSchool }}</h2><div class="row g-3"><div class="col-md-6"><label class="form-label">{{ de.schoolName }}</label><input v-model="schoolForm.name" class="form-control" required></div><div class="col-md-3"><label class="form-label">{{ de.shortName }}</label><input v-model="schoolForm.short_name" class="form-control"></div><div class="col-md-3"><label class="form-label">{{ de.city }}</label><input v-model="schoolForm.city" class="form-control"></div></div><button class="btn btn-primary mt-3" :disabled="schoolForm.processing">{{ de.save }}</button>
        </form>
        <form v-if="showYearForm" class="card card-body mb-4" @submit.prevent="createYear">
            <h2 class="h5">{{ de.addSchoolYear }}</h2><div class="row g-3"><div class="col-md-4"><label class="form-label">{{ de.school }}</label><select v-model="yearForm.school_id" class="form-select" required><option value="" disabled>{{ de.choose }}</option><option v-for="school in props.schools" :key="school.id" :value="school.id">{{ school.name }}</option></select></div><div class="col-md-3"><label class="form-label">{{ de.schoolYearName }}</label><input v-model="yearForm.name" class="form-control" placeholder="2026/27" required></div><div class="col-md-2"><label class="form-label">{{ de.from }}</label><input v-model="yearForm.starts_on" type="date" class="form-control" required></div><div class="col-md-2"><label class="form-label">{{ de.to }}</label><input v-model="yearForm.ends_on" type="date" class="form-control" required></div></div><button class="btn btn-primary mt-3" :disabled="yearForm.processing">{{ de.save }}</button>
        </form>
        <div v-if="!props.schools.length" class="alert alert-info">{{ de.noSchools }}</div>
        <div v-for="school in props.schools" :key="school.id" class="card mb-3"><div class="card-body"><h2 class="h5 mb-1">{{ school.name }}</h2><span class="text-muted">{{ [school.short_name, school.city].filter(Boolean).join(' · ') }}</span><div class="mt-3"><span v-if="!school.school_years.length" class="text-muted">{{ de.noSchoolYears }}</span><a v-for="year in school.school_years" :key="year.id" class="btn btn-sm btn-outline-secondary me-2" :href="`/schuljahre/${year.slug}`">{{ year.name }}</a></div></div></div>
    </main>
</template>
