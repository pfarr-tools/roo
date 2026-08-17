<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import CurriculumAssignments from '../../Components/Schools/CurriculumAssignments.vue'
import de from '../../i18n/de'
import { router, useForm } from '@inertiajs/vue3'

const props = defineProps({ school: Object, curricula: Array })
const form = useForm({
    name: props.school.name,
    short_name: props.school.short_name ?? '',
    city: props.school.city ?? '',
    notes: props.school.notes ?? '',
    curriculum_assignments: (props.school.curriculum_assignments ?? []).map(assignment => ({ curriculum_id: assignment.curriculum_id, valid_from: assignment.valid_from ?? '', valid_until: assignment.valid_until ?? '', school_type: assignment.school_type ?? '', grades: assignment.grades ?? [], notes: assignment.notes ?? '' })),
})
function save() { form.put(`/schulen/${props.school.id}`) }
function removeSchool() { if (window.confirm(de.deleteSchoolConfirm)) router.delete(`/schulen/${props.school.id}`) }
</script>

<template>
    <AppShell>
        <template #toolbar>
            <a href="/schulen" class="btn btn-sm btn-light" :title="de.close" :aria-label="de.close"><i class="bi bi-x-lg" aria-hidden="true"></i></a>
            <button class="btn btn-sm btn-primary" type="button" :disabled="form.processing" @click="save"><i class="bi bi-check-lg" aria-hidden="true"></i><span class="d-none d-md-inline ms-1">{{ de.saveChanges }}</span></button>
            <button class="btn btn-sm btn-outline-danger" type="button" @click="removeSchool"><i class="bi bi-trash" aria-hidden="true"></i><span class="d-none d-md-inline ms-1">{{ de.deleteSchool }}</span></button>
        </template>
        <div class="container-full px-3 py-4">
            <a href="/schulen" class="text-decoration-none">{{ de.schools }}</a>
            <div class="d-flex justify-content-between align-items-center mt-2 mb-4"><h1 class="h2 mb-0">{{ de.editSchool }}: {{ school.name }}</h1></div>
            <form class="card card-body" @submit.prevent="save">
                <div class="row g-3 mb-4"><div class="col-md-6"><label class="form-label">{{ de.schoolName }}</label><input v-model="form.name" class="form-control" required></div><div class="col-md-3"><label class="form-label">{{ de.shortName }}</label><input v-model="form.short_name" class="form-control"></div><div class="col-md-3"><label class="form-label">{{ de.city }}</label><input v-model="form.city" class="form-control"></div><div class="col-12"><label class="form-label">{{ de.notes }}</label><textarea v-model="form.notes" class="form-control" rows="3"></textarea></div></div>
                <CurriculumAssignments v-model="form.curriculum_assignments" :curricula="curricula" />
            </form>
        </div>
    </AppShell>
</template>
