<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import de from '../../i18n/de'
import { requestConfirmation } from '../../utils/confirmation'
import { router, useForm } from '@inertiajs/vue3'

const props = defineProps({ student: Object, availableSchoolYears: Array, selectedSchoolYear: Object, groups: Array, observations: Array, assessmentResults: Array, evaluations: Array, ratings: Array })
const editForm = useForm({ first_name: props.student.first_name, last_name: props.student.last_name, class_name: props.student.class_name, notes: props.student.notes ?? '', receives_grades: Boolean(props.student.receives_grades), pronoun_set: props.student.pronoun_set ?? 'er' })

function yearUrl(yearId) { return `/schueler:innen/${props.student.id}?school_year=${yearId}` }
function formatDate(value) { const [year, month, day] = String(value ?? '').slice(0, 10).split('-'); return year && month && day ? `${day}.${month}.${year}` : '–' }
function save() { editForm.put(`/schuelerinnen/${props.student.id}`) }
async function remove() { if (await requestConfirmation({ message: de.deleteStudentConfirm })) router.delete(`/schuelerinnen/${props.student.id}`) }
function ratingText(rating) { return rating.competence?.text || '–' }
function ratingStars(rating) { return Math.max(0, Math.min(5, Number(rating.rating))) }
</script>

<template>
    <AppShell>
        <template #toolbar><button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#student-edit-modal"><i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ de.editStudent }}</button><button class="btn btn-sm btn-outline-danger" type="button" @click="remove"><i class="bi bi-trash me-1" aria-hidden="true"></i>{{ de.deleteStudent }}</button></template>
        <div class="container-full px-3 py-4">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-4"><div><h1 class="h2 mb-1">{{ student.last_name }}, {{ student.first_name }}</h1><p class="text-muted mb-0">{{ [student.class_name, student.school?.name].filter(Boolean).join(' · ') }}</p><p class="text-muted mt-2 mb-0">{{ de.studentDetailsIntro }}</p></div><div v-if="availableSchoolYears.length" class="col-sm-4 col-lg-3"><label class="form-label" for="student-school-year-detail">{{ de.schoolYear }}</label><select id="student-school-year-detail" class="form-select" :value="selectedSchoolYear?.id ?? ''" @change="router.get(yearUrl($event.target.value))"><option v-for="year in availableSchoolYears" :key="year.id" :value="year.id">{{ year.name }}</option></select></div></div>
            <div v-if="!availableSchoolYears.length" class="alert alert-info">{{ de.noStudentSchoolYears }}</div>
            <template v-else>
                <div class="row g-4 mb-4"><div class="col-lg-5"><section class="card h-100"><div class="card-body"><h2 class="h5">{{ de.studentGroups }}</h2><div v-if="groups.length" class="list-group list-group-flush"><a v-for="group in groups" :key="group.id" class="list-group-item list-group-item-action px-0" :href="`/unterrichtsgruppen/${group.id}`">{{ group.name }}<span class="small text-muted d-block">{{ group.school?.name }}</span></a></div><p v-else class="text-muted mb-0">{{ de.noStudentData }}</p></div></section></div></div>
                <section class="card mb-4"><div class="card-body"><h2 class="h5">{{ de.studentObservations }}</h2><div v-if="observations.length" class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>{{ de.date }}</th><th>{{ de.teachingGroup }}</th><th>{{ de.lesson }}</th><th>{{ de.observation }}</th><th>{{ de.notes }}</th></tr></thead><tbody><tr v-for="observation in observations" :key="observation.id"><td>{{ formatDate(observation.scheduled_lesson?.slot?.date) }}</td><td>{{ observation.scheduled_lesson?.slot?.group?.name }}</td><td>{{ observation.scheduled_lesson?.lesson?.title }}</td><td><span class="badge text-bg-light">{{ observation.type?.label }}</span></td><td>{{ observation.note || '–' }}</td></tr></tbody></table></div><p v-else class="text-muted mb-0">{{ de.noStudentData }}</p></div></section>
                <section class="card"><div class="card-body"><h2 class="h5">{{ de.studentEvaluations }}</h2><div v-if="evaluations.length" class="row g-3"><article v-for="evaluation in evaluations" :key="evaluation.id" class="col-md-6"><div class="border rounded p-3 h-100"><strong>{{ evaluation.period?.label }}</strong><span class="small text-muted"> · {{ evaluation.period?.group?.name }}</span><p v-if="evaluation.draft_text" class="mb-1 mt-2 text-pre-wrap">{{ evaluation.draft_text }}</p><p v-if="evaluation.level" class="mb-0">{{ de.level }}: {{ evaluation.level }}</p><p v-if="!evaluation.draft_text && !evaluation.level" class="text-muted mb-0 mt-2">{{ de.noStudentData }}</p></div></article></div><p v-else class="text-muted mb-0">{{ de.noStudentData }}</p></div></section>
            </template>
        </div>
        <div class="modal fade" id="student-edit-modal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><form @submit.prevent="save"><div class="modal-header"><h2 class="modal-title h5">{{ de.editStudent }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" :aria-label="de.close"></button></div><div class="modal-body"><div class="row g-2"><div class="col-6"><label class="form-label">{{ de.firstName }}</label><input v-model="editForm.first_name" class="form-control" required></div><div class="col-6"><label class="form-label">{{ de.lastName }}</label><input v-model="editForm.last_name" class="form-control" required></div></div><label class="form-label mt-2">{{ de.actualClass }}</label><input v-model="editForm.class_name" class="form-control" required><label class="form-label mt-2">{{ de.notes }}</label><textarea v-model="editForm.notes" class="form-control" rows="3"></textarea></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ de.cancel }}</button><button class="btn btn-primary" type="submit" :disabled="editForm.processing">{{ de.saveChanges }}</button></div></form></div></div></div>
    </AppShell>
</template>
