<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import de from '../../i18n/de'

const props = defineProps({ groups: Array, schools: Array })
const open = ref(false)
const form = useForm({ school_id: '', school_year_id: '', name: '', aktenzeichen: '', notes: '', grade_levels: [''] })
const selectedSchool = ref(null)
function yearsForSchool() { return props.schools.find(school => String(school.id) === String(form.school_id))?.school_years ?? [] }
function addGrade() { form.grade_levels.push('') }
function removeGrade(index) { if (form.grade_levels.length > 1) form.grade_levels.splice(index, 1) }
function create() { form.grade_levels = form.grade_levels.map(value => value.trim()).filter(Boolean); form.post('/unterrichtsgruppen', { onSuccess: () => { form.reset(); form.grade_levels = ['']; open.value = false } }) }
</script>

<template>
    <AppShell>
        <template #toolbar><button class="btn btn-sm btn-primary" type="button" @click="open = true"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ de.addTeachingGroup }}</button></template>
        <div class="container-full px-3 py-4">
            <h1 class="h2">{{ de.teachingGroups }}</h1>
            <div v-if="$page.props.flash?.success" class="alert alert-success">{{ $page.props.flash.success }}</div>
            <div v-if="!groups.length" class="alert alert-info">{{ de.noTeachingGroups }}</div>
            <div v-for="group in groups" :key="group.id" class="card mb-3"><div class="card-body d-flex justify-content-between align-items-center gap-3"><div><h2 class="h5 mb-1"><a :href="`/unterrichtsgruppen/${group.id}`" class="text-decoration-none">{{ group.name }}</a></h2><div class="text-muted">{{ group.school.name }} · {{ group.school_year.name }} · {{ group.grade_levels.map(level => level.grade_level).join(', ') }}</div></div><span class="badge text-bg-light">{{ group.students_count }} {{ de.members.toLowerCase() }}</span></div></div>
        </div>
        <div v-if="open" class="roo-modal-backdrop" role="presentation" @click.self="open = false"><section class="roo-modal" role="dialog" aria-modal="true" :aria-label="de.addTeachingGroup"><div class="card border-0"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">{{ de.addTeachingGroup }}</h2><button class="btn-close" type="button" :aria-label="de.close" @click="open = false"></button></div><form @submit.prevent="create">
            <label class="form-label">{{ de.school }}</label><select v-model="form.school_id" class="form-select" required><option value="">{{ de.choose }}</option><option v-for="school in schools" :key="school.id" :value="school.id">{{ school.name }}</option></select>
            <label class="form-label mt-3">{{ de.schoolYear }}</label><select v-model="form.school_year_id" class="form-select" required><option value="">{{ de.choose }}</option><option v-for="year in yearsForSchool()" :key="year.id" :value="year.id">{{ year.name }}</option></select>
            <label class="form-label mt-3">{{ de.groupName }}</label><input v-model="form.name" class="form-control" required>
            <label class="form-label mt-3">{{ de.aktenzeichen }}</label><input v-model="form.aktenzeichen" class="form-control" maxlength="30"><div class="form-text">{{ de.aktenzeichenHint }}</div>
            <label class="form-label mt-3">{{ de.groupGrades }}</label><div v-for="(_, index) in form.grade_levels" :key="index" class="input-group mb-2"><input v-model="form.grade_levels[index]" class="form-control" placeholder="z. B. 2" required><button class="btn btn-outline-secondary" type="button" :disabled="form.grade_levels.length === 1" @click="removeGrade(index)">×</button></div><button class="btn btn-sm btn-outline-secondary" type="button" @click="addGrade">{{ de.add }} {{ de.groupGrades }}</button><div class="form-text">{{ de.groupGradesHint }}</div>
            <div class="d-flex justify-content-end gap-2 mt-4"><button class="btn btn-outline-secondary" type="button" @click="open = false">{{ de.cancel }}</button><button class="btn btn-primary" type="submit" :disabled="form.processing">{{ de.save }}</button></div>
        </form></div></div></section></div>
    </AppShell>
</template>
