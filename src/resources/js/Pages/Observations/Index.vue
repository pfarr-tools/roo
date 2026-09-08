<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import AppShell from '../../Components/Ui/AppShell.vue'
import { router } from '@inertiajs/vue3'
import de from '../../i18n/de'
import { formatObservationDate } from './formatters'

const props = defineProps({ observations: Object, groups: Array, schoolYears: Array, observationTypes: Array, filters: Object })
const search = ref(props.filters?.q ?? '')
let searchTimeout

function queryUrl(changes = {}) {
    const values = { ...props.filters, ...changes }
    const params = new URLSearchParams()
    Object.entries(values).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') params.set(key, value)
    })
    return `/beobachtungen?${params.toString()}`
}

function applyFilter(key, value) {
    const changes = { [key]: value }
    if (key !== 'q') changes.q = search.value
    router.get(queryUrl(changes), {}, { preserveState: true, preserveScroll: true, replace: true })
}

function sortUrl(column) {
    const direction = props.filters.sort === column && props.filters.direction === 'asc' ? 'desc' : 'asc'
    return queryUrl({ sort: column, direction })
}

function sortLabel(column) {
    return props.filters.sort === column && props.filters.direction === 'desc' ? de.sortAscending : de.sortDescending
}

function observationUrl(observation) {
    return `/unterricht/${observation.scheduled_lesson?.slot?.id}?tab=observation`
}

watch(search, value => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => applyFilter('q', value), 300)
})
onBeforeUnmount(() => clearTimeout(searchTimeout))
</script>

<template>
    <AppShell>
        <div class="container-full px-3 py-4">
            <div class="d-flex justify-content-between align-items-end gap-3 mb-4">
                <div><h1 class="h2 mb-1">{{ de.observations }}</h1><p class="text-muted mb-0">{{ de.observationOverviewIntro }}</p></div>
                <span class="badge text-bg-light">{{ observations.total }} {{ de.observationCount }}</span>
            </div>

            <form class="card card-body mb-4" role="search" @submit.prevent>
                <div class="row g-2 align-items-end">
                    <div class="col-lg-4"><label class="form-label" for="observation-search">{{ de.filter }}</label><input id="observation-search" v-model="search" class="form-control" type="search" :placeholder="de.searchObservations" autocomplete="off"></div>
                    <div class="col-lg-3"><label class="form-label" for="observation-group">{{ de.teachingGroup }}</label><select id="observation-group" class="form-select" :value="filters.group ?? ''" @change="applyFilter('group', $event.target.value)"><option value="">{{ de.allTeachingGroups }}</option><option v-for="group in groups" :key="group.id" :value="group.id">{{ group.name }}<template v-if="group.school_year"> ({{ group.school_year.name }})</template></option></select></div>
                    <div class="col-lg-2"><label class="form-label" for="observation-school-year">{{ de.schoolYear }}</label><select id="observation-school-year" class="form-select" :value="filters.school_year ?? ''" @change="applyFilter('school_year', $event.target.value)"><option value="">{{ de.allSchoolYears }}</option><option v-for="year in schoolYears" :key="year.id" :value="year.id">{{ year.name }}</option></select></div>
                    <div class="col-lg-2"><label class="form-label" for="observation-type">{{ de.observation }}</label><select id="observation-type" class="form-select" :value="filters.type ?? ''" @change="applyFilter('type', $event.target.value)"><option value="">{{ de.allObservationTypes }}</option><option v-for="type in observationTypes" :key="type.id" :value="type.id">{{ type.label }}</option></select></div>
                    <div class="col-lg-1 d-flex justify-content-end"><a href="/beobachtungen" class="btn btn-outline-secondary" :title="de.clearFilters" :aria-label="de.clearFilters"><i class="bi bi-x-lg" aria-hidden="true"></i></a></div>
                </div>
            </form>

            <div v-if="!observations.data.length" class="alert alert-info">{{ de.noSearchResults }}</div>
            <div v-else class="table-responsive"><table class="table table-hover align-middle"><thead><tr>
                <th><a :href="sortUrl('date')" class="text-decoration-none" :title="sortLabel('date')">{{ de.date }} <i v-if="filters.sort === 'date'" :class="filters.direction === 'asc' ? 'bi bi-arrow-up' : 'bi bi-arrow-down'" aria-hidden="true"></i></a></th>
                <th><a :href="sortUrl('student')" class="text-decoration-none" :title="sortLabel('student')">{{ de.student }} <i v-if="filters.sort === 'student'" :class="filters.direction === 'asc' ? 'bi bi-arrow-up' : 'bi bi-arrow-down'" aria-hidden="true"></i></a></th>
                <th><a :href="sortUrl('group')" class="text-decoration-none" :title="sortLabel('group')">{{ de.teachingGroup }} <i v-if="filters.sort === 'group'" :class="filters.direction === 'asc' ? 'bi bi-arrow-up' : 'bi bi-arrow-down'" aria-hidden="true"></i></a></th>
                <th>{{ de.lesson }}</th>
                <th><a :href="sortUrl('type')" class="text-decoration-none" :title="sortLabel('type')">{{ de.observation }} <i v-if="filters.sort === 'type'" :class="filters.direction === 'asc' ? 'bi bi-arrow-up' : 'bi bi-arrow-down'" aria-hidden="true"></i></a></th>
                <th>{{ de.notes }}</th>
            </tr></thead><tbody><tr v-for="observation in observations.data" :key="observation.id">
                <td class="text-nowrap"><a :href="observationUrl(observation)" class="text-decoration-none">{{ formatObservationDate(observation.scheduled_lesson?.slot?.date) }}</a><div class="small text-muted">{{ observation.scheduled_lesson?.slot?.period_number ? `${de.period} ${observation.scheduled_lesson.slot.period_number}` : '' }}</div></td>
                <td><a :href="'/schueler:innen/' + observation.student?.id" class="text-decoration-none"><strong>{{ observation.student?.last_name }}, {{ observation.student?.first_name }}</strong></a><div class="small text-muted">{{ observation.student?.class_name }}</div></td>
                <td>{{ observation.scheduled_lesson?.slot?.group?.name }}</td>
                <td><a :href="observationUrl(observation)" class="text-decoration-none">{{ observation.scheduled_lesson?.lesson?.title }}</a></td>
                <td><span class="badge text-bg-light">{{ observation.type?.label }}</span><span v-if="observation.type?.symbol" class="small text-muted ms-1">{{ observation.type.symbol }}</span></td>
                <td class="text-pre-wrap">{{ observation.note || '–' }}</td>
            </tr></tbody></table></div>
            <nav v-if="observations.links.length > 3" class="mt-3" :aria-label="de.pagination"><ul class="pagination"><li v-for="link in observations.links" :key="link.label" class="page-item" :class="{ active: link.active, disabled: !link.url }"><a v-if="link.url" class="page-link" :href="link.url" v-html="link.label"></a><span v-else class="page-link" v-html="link.label"></span></li></ul></nav>
        </div>
    </AppShell>
</template>
