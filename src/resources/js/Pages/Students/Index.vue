<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import de from '../../i18n/de'

const props = defineProps({ students: Object, schools: Array, classes: Array, filters: Object })

function queryUrl(changes = {}) {
    const values = { ...props.filters, ...changes }
    const params = new URLSearchParams()

    Object.entries(values).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') params.set(key, value)
    })

    return `/schüler:innen?${params.toString()}`
}

function sortUrl(column) {
    const direction = props.filters.sort === column && props.filters.direction === 'asc' ? 'desc' : 'asc'

    return queryUrl({ sort: column, direction })
}

function sortLabel(column) {
    return props.filters.sort === column && props.filters.direction === 'desc' ? de.sortAscending : de.sortDescending
}
</script>

<template>
    <AppShell>
        <template #toolbar><span class="fw-semibold">{{ de.students }}</span></template>
        <div class="container-full px-3 py-4">
            <a href="/dashboard" class="text-decoration-none">{{ de.dashboard }}</a>
            <div class="d-flex justify-content-between align-items-end gap-3 mt-2 mb-4"><div><h1 class="h2 mb-1">{{ de.students }}</h1><p class="text-muted mb-0">{{ de.studentsIntro }}</p></div><span class="badge text-bg-light">{{ students.total }} {{ de.studentCount }}</span></div>
            <form method="get" action="/schüler:innen" class="card card-body mb-4" role="search"><div class="row g-2 align-items-end"><div class="col-lg-5"><label class="form-label" for="student-search">{{ de.search }}</label><input id="student-search" name="q" :value="filters.q" class="form-control" :placeholder="de.searchStudents"></div><div class="col-lg-3"><label class="form-label" for="student-school">{{ de.school }}</label><select id="student-school" name="school_id" class="form-select"><option value="">{{ de.allSchools }}</option><option v-for="school in schools" :key="school.id" :value="school.id" :selected="String(filters.school_id) === String(school.id)">{{ school.name }}</option></select></div><div class="col-lg-2"><label class="form-label" for="student-class">{{ de.studentClass }}</label><select id="student-class" name="class_name" class="form-select"><option value="">{{ de.allClasses }}</option><option v-for="className in classes" :key="className" :value="className" :selected="filters.class_name === className">{{ className }}</option></select></div><div class="col-lg-2 d-flex gap-2"><button class="btn btn-primary flex-grow-1" type="submit">{{ de.search }}</button><a href="/schüler:innen" class="btn btn-outline-secondary" :title="de.clearFilters" :aria-label="de.clearFilters"><i class="bi bi-x-lg" aria-hidden="true"></i></a></div></div></form>
            <div v-if="!students.data.length" class="alert alert-info">{{ de.noStudents }}</div>
            <div v-else class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th><a :href="sortUrl('last_name')" class="text-decoration-none" :title="sortLabel('last_name')">{{ de.lastName }} <i v-if="filters.sort === 'last_name'" :class="filters.direction === 'asc' ? 'bi bi-arrow-up' : 'bi bi-arrow-down'" aria-hidden="true"></i></a></th><th><a :href="sortUrl('first_name')" class="text-decoration-none" :title="sortLabel('first_name')">{{ de.firstName }} <i v-if="filters.sort === 'first_name'" :class="filters.direction === 'asc' ? 'bi bi-arrow-up' : 'bi bi-arrow-down'" aria-hidden="true"></i></a></th><th><a :href="sortUrl('class_name')" class="text-decoration-none" :title="sortLabel('class_name')">{{ de.studentClass }} <i v-if="filters.sort === 'class_name'" :class="filters.direction === 'asc' ? 'bi bi-arrow-up' : 'bi bi-arrow-down'" aria-hidden="true"></i></a></th><th><a :href="sortUrl('school')" class="text-decoration-none" :title="sortLabel('school')">{{ de.studentSchool }} <i v-if="filters.sort === 'school'" :class="filters.direction === 'asc' ? 'bi bi-arrow-up' : 'bi bi-arrow-down'" aria-hidden="true"></i></a></th><th>{{ de.schoolYear }}</th></tr></thead><tbody><tr v-for="student in students.data" :key="student.id"><td>{{ student.last_name }}</td><td>{{ student.first_name }}</td><td>{{ student.class_name }}</td><td>{{ student.school.name }}</td><td>{{ [...new Set(student.teaching_groups.map(group => group.school_year?.name).filter(Boolean))].join(', ') || '–' }}</td></tr></tbody></table></div>
            <nav v-if="students.links.length > 3" class="mt-3" :aria-label="de.pagination"><ul class="pagination"><li v-for="link in students.links" :key="link.label" class="page-item" :class="{ active: link.active, disabled: !link.url }"><a v-if="link.url" class="page-link" :href="link.url" v-html="link.label"></a><span v-else class="page-link" v-html="link.label"></span></li></ul></nav>
        </div>
    </AppShell>
</template>
