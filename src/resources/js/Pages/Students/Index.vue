<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import AppShell from '../../Components/Ui/AppShell.vue'
import { router, useForm } from '@inertiajs/vue3'
import { requestConfirmation } from '../../utils/confirmation'
import de from '../../i18n/de'

const props = defineProps({ students: Object, schools: Array, classes: Array, gradeLevels: Array, groups: Array, schoolYears: Array, pronounSets: Array, filters: Object })
const showExportModal = ref(false)
const selectedStudent = ref(null)
const search = ref(props.filters.q ?? '')
let searchTimeout
const editStudentForm = useForm({ first_name: '', last_name: '', class_name: '', notes: '', receives_grades: false, pronoun_set: 'er' })

function queryUrl(changes = {}) {
    const values = { ...props.filters, ...changes }
    const params = new URLSearchParams()

    Object.entries(values).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') params.set(key, value)
    })

    return `/schueler:innen?${params.toString()}`
}

function sortUrl(column) {
    const direction = props.filters.sort === column && props.filters.direction === 'asc' ? 'desc' : 'asc'

    return queryUrl({ sort: column, direction })
}

function sortLabel(column) {
    return props.filters.sort === column && props.filters.direction === 'desc' ? de.sortAscending : de.sortDescending
}

function applyFilter(key, value) {
    clearTimeout(searchTimeout)
    const changes = { [key]: value }

    if (key !== 'q') changes.q = search.value

    router.get(queryUrl(changes), {}, { preserveState: true, preserveScroll: true })
}

watch(search, value => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => applyFilter('q', value), 300)
})
onBeforeUnmount(() => clearTimeout(searchTimeout))

function editStudent(student) {
    selectedStudent.value = student
    editStudentForm.defaults({ first_name: student.first_name, last_name: student.last_name, class_name: student.class_name, notes: student.notes ?? '', receives_grades: Boolean(student.receives_grades), pronoun_set: student.pronoun_set ?? 'er' })
    editStudentForm.reset()
}

function saveStudent() { editStudentForm.put(`/schuelerinnen/${selectedStudent.value.id}`, { onSuccess: () => { selectedStudent.value = null } }) }
async function deleteStudent(student) { if (await requestConfirmation({ message: de.deleteStudentConfirm })) useForm({}).delete(`/schuelerinnen/${student.id}`) }
</script>

<template>
    <AppShell>
        <template #toolbar><button class="btn btn-sm btn-outline-primary" type="button" @click="showExportModal = true">{{ de.exportStudents }}</button></template>
        <div class="container-full px-3 py-4">
            <div class="d-flex justify-content-between align-items-end gap-3 mb-4"><div><h1 class="h2 mb-1">{{ de.students }}</h1></div><span class="badge text-bg-light">{{ students.total }} {{ de.studentCount }}</span></div>
            <form class="card card-body mb-4" role="search" @submit.prevent><div class="row g-2 align-items-end"><div class="col-lg-3"><label class="form-label" for="student-search">{{ de.filter }}</label><input id="student-search" v-model="search" name="q" class="form-control" :placeholder="de.searchStudents" autocomplete="off"></div><div class="col-lg-2"><label class="form-label" for="student-school">{{ de.school }}</label><select id="student-school" class="form-select" :value="filters.school_id ?? ''" @change="applyFilter('school_id', $event.target.value)"><option value="">{{ de.allSchools }}</option><option v-for="school in schools" :key="school.id" :value="school.id">{{ school.name }}</option></select></div><div class="col-lg-2"><label class="form-label" for="student-grade-level">{{ de.gradeLevel }}</label><select id="student-grade-level" class="form-select" :value="filters.grade_level ?? ''" @change="applyFilter('grade_level', $event.target.value)"><option value="">{{ de.allGradeLevels }}</option><option v-for="gradeLevel in gradeLevels" :key="gradeLevel" :value="gradeLevel">{{ gradeLevel }}</option></select></div><div class="col-lg-2"><label class="form-label" for="student-class">{{ de.studentClass }}</label><select id="student-class" class="form-select" :value="filters.class_name ?? ''" @change="applyFilter('class_name', $event.target.value)"><option value="">{{ de.allClasses }}</option><option v-for="className in classes" :key="className" :value="className">{{ className }}</option></select></div><div class="col-lg-2"><label class="form-label" for="student-school-year">{{ de.schoolYear }}</label><select id="student-school-year" class="form-select" :value="filters.school_year_id ?? ''" @change="applyFilter('school_year_id', $event.target.value)"><option value="">{{ de.allSchoolYears }}</option><option v-for="schoolYear in schoolYears" :key="schoolYear.id" :value="schoolYear.id">{{ schoolYear.name }}</option></select></div><div class="col-lg-1 d-flex justify-content-end"><a href="/schueler:innen" class="btn btn-outline-secondary" :title="de.clearFilters" :aria-label="de.clearFilters"><i class="bi bi-x-lg" aria-hidden="true"></i></a></div></div></form>
            <div v-if="!students.data.length" class="alert alert-info">{{ de.noStudents }}</div>
            <div v-else class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th><a :href="sortUrl('last_name')" class="text-decoration-none" :title="sortLabel('last_name')">{{ de.lastName }} <i v-if="filters.sort === 'last_name'" :class="filters.direction === 'asc' ? 'bi bi-arrow-up' : 'bi bi-arrow-down'" aria-hidden="true"></i></a></th><th><a :href="sortUrl('first_name')" class="text-decoration-none" :title="sortLabel('first_name')">{{ de.firstName }} <i v-if="filters.sort === 'first_name'" :class="filters.direction === 'asc' ? 'bi bi-arrow-up' : 'bi bi-arrow-down'" aria-hidden="true"></i></a></th><th><a :href="sortUrl('class_name')" class="text-decoration-none" :title="sortLabel('class_name')">{{ de.studentClass }} <i v-if="filters.sort === 'class_name'" :class="filters.direction === 'asc' ? 'bi bi-arrow-up' : 'bi bi-arrow-down'" aria-hidden="true"></i></a></th><th><a :href="sortUrl('school')" class="text-decoration-none" :title="sortLabel('school')">{{ de.studentSchool }} <i v-if="filters.sort === 'school'" :class="filters.direction === 'asc' ? 'bi bi-arrow-up' : 'bi bi-arrow-down'" aria-hidden="true"></i></a></th><th>{{ de.schoolYear }}</th><th><span class="visually-hidden">{{ de.actions }}</span></th></tr></thead><tbody><tr v-for="student in students.data" :key="student.id"><td>{{ student.last_name }}</td><td>{{ student.first_name }}</td><td>{{ student.class_name }}</td><td>{{ student.school.name }}</td><td>{{ [...new Set(student.teaching_groups.map(group => group.school_year?.name).filter(Boolean))].join(', ') || '–' }}</td><td class="text-end"><button class="btn btn-sm btn-outline-secondary me-1" type="button" :title="de.editStudent" :aria-label="de.editStudent" @click="editStudent(student)"><i class="bi bi-pencil" aria-hidden="true"></i></button><button class="btn btn-sm btn-outline-danger" type="button" :title="de.deleteStudent" :aria-label="de.deleteStudent" @click="deleteStudent(student)"><i class="bi bi-trash" aria-hidden="true"></i></button></td></tr></tbody></table></div>
            <nav v-if="students.links.length > 3" class="mt-3" :aria-label="de.pagination"><ul class="pagination"><li v-for="link in students.links" :key="link.label" class="page-item" :class="{ active: link.active, disabled: !link.url }"><a v-if="link.url" class="page-link" :href="link.url" v-html="link.label"></a><span v-else class="page-link" v-html="link.label"></span></li></ul></nav>
        </div>
        <div v-if="selectedStudent" class="roo-modal-backdrop" role="presentation" @click.self="selectedStudent = null"><section class="roo-modal" role="dialog" aria-modal="true" :aria-label="de.editStudent"><div class="card border-0"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">{{ de.editStudent }}</h2><button class="btn-close" type="button" :aria-label="de.close" @click="selectedStudent = null"></button></div><form @submit.prevent="saveStudent"><div class="row g-2"><div class="col-6"><label class="form-label">{{ de.firstName }}</label><input v-model="editStudentForm.first_name" class="form-control" required></div><div class="col-6"><label class="form-label">{{ de.lastName }}</label><input v-model="editStudentForm.last_name" class="form-control" required></div></div><label class="form-label mt-2">{{ de.actualClass }}</label><input v-model="editStudentForm.class_name" class="form-control" required><div class="row g-2 mt-1"><div class="col-md-6"><label class="form-label">{{ de.pronouns }}</label><select v-model="editStudentForm.pronoun_set" class="form-select"><option v-for="pronounSet in pronounSets" :key="pronounSet.key" :value="pronounSet.key">{{ pronounSet.label }}</option></select></div><div class="col-md-6 d-flex align-items-end"><div class="form-check mb-2"><input id="global-edit-student-receives-grades" v-model="editStudentForm.receives_grades" class="form-check-input" type="checkbox"><label class="form-check-label" for="global-edit-student-receives-grades">{{ de.receivesGrades }}</label></div></div></div><label class="form-label mt-2">{{ de.notes }}</label><textarea v-model="editStudentForm.notes" class="form-control" rows="2"></textarea><div class="d-flex justify-content-end gap-2 mt-4"><button class="btn btn-outline-secondary" type="button" @click="selectedStudent = null">{{ de.cancel }}</button><button class="btn btn-primary" type="submit" :disabled="editStudentForm.processing">{{ de.saveChanges }}</button></div></form></div></div></section></div>
        <div v-if="showExportModal" class="roo-modal-backdrop" role="presentation" @click.self="showExportModal = false">
            <section class="roo-modal" role="dialog" aria-modal="true" :aria-label="de.exportStudentsTitle">
                <div class="card border-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div><h2 class="h5 mb-1">{{ de.exportStudentsTitle }}</h2><p class="text-muted mb-0">{{ de.exportStudentsIntro }}</p></div>
                            <button class="btn-close" type="button" :aria-label="de.close" @click="showExportModal = false"></button>
                        </div>
            <form method="get" action="/schueler:innen/export">
                            <div class="row g-3">
                                <div class="col-12"><label class="form-label" for="export-student-search">{{ de.filter }}</label><input id="export-student-search" name="q" :value="filters.q" class="form-control" :placeholder="de.searchStudents"></div>
                                <div class="col-md-6"><label class="form-label" for="export-student-school">{{ de.school }}</label><select id="export-student-school" name="school_id" class="form-select"><option value="">{{ de.allSchools }}</option><option v-for="school in schools" :key="school.id" :value="school.id" :selected="String(filters.school_id) === String(school.id)">{{ school.name }}</option></select></div>
                                <div class="col-md-6"><label class="form-label" for="export-student-class">{{ de.studentClass }}</label><select id="export-student-class" name="class_name" class="form-select"><option value="">{{ de.allClasses }}</option><option v-for="className in classes" :key="className" :value="className" :selected="filters.class_name === className">{{ className }}</option></select></div>
                                <div class="col-md-6"><label class="form-label" for="export-student-group">{{ de.teachingGroup }}</label><select id="export-student-group" name="teaching_group_id" class="form-select"><option value="">{{ de.allTeachingGroups }}</option><option v-for="group in groups" :key="group.id" :value="group.id" :selected="String(filters.teaching_group_id) === String(group.id)">{{ group.name }} ({{ group.school_year?.name }})</option></select></div>
                                <div class="col-md-6"><label class="form-label" for="export-student-school-year">{{ de.schoolYear }}</label><select id="export-student-school-year" name="school_year_id" class="form-select"><option value="">{{ de.allSchoolYears }}</option><option v-for="schoolYear in schoolYears" :key="schoolYear.id" :value="schoolYear.id" :selected="String(filters.school_year_id) === String(schoolYear.id)">{{ schoolYear.name }}</option></select></div>
                            </div>
                            <div class="d-flex justify-content-end gap-2 mt-4"><button class="btn btn-outline-secondary" type="button" @click="showExportModal = false">{{ de.cancel }}</button><button class="btn btn-primary" type="submit">{{ de.exportStudents }}</button></div>
                        </form>
                    </div>
                </div>
            </section>
        </div>
    </AppShell>
</template>
