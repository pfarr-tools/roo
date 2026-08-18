<script setup>
import AppShell from '../Components/Ui/AppShell.vue'
import de from '../i18n/de'
import { computed, ref } from 'vue'

const props = defineProps({
    week: { type: String, default: '' },
    weekOptions: { type: Array, default: () => [] },
    previousWeek: { type: String, default: '' },
    nextWeek: { type: String, default: '' },
    days: { type: Array, default: () => [] },
    periodNumbers: { type: Array, default: () => [] },
    hasSchoolYear: { type: Boolean, default: false },
})

const weekOptions = computed(() => (Array.isArray(props.weekOptions) ? props.weekOptions : []))
const days = computed(() => (Array.isArray(props.days) ? props.days : []))
const periodNumbers = computed(() => (Array.isArray(props.periodNumbers) ? props.periodNumbers : []))
const selectedWeek = ref(props.week || weekOptions.value[0]?.value || '')

function formatDate(value) {
    if (!value) {
        return ''
    }

    const [, month, day] = value.split('-')

    return `${day}.${month}.`
}

function entriesFor(day, periodNumber) {
    return (day?.entries ?? []).filter((entry) => entry.period_number === periodNumber)
}

function navigateToWeek() {
    if (selectedWeek.value) {
        window.location.href = `/dashboard?week=${selectedWeek.value}`
    }
}

function weekUrl(value) {
    return value ? `/dashboard?week=${value}` : '/dashboard'
}
</script>

<template>
    <AppShell>
        <template #toolbar><span class="fw-semibold">{{ de.timetable }}</span></template>
        <div class="container-full px-3 py-4">
            <div class="d-flex justify-content-between align-items-center gap-3 mb-4"><a :href="weekUrl(props.previousWeek)" class="btn btn-outline-primary" :aria-label="de.previousWeek"><i class="bi bi-chevron-left" aria-hidden="true"></i><span class="d-none d-sm-inline ms-1">{{ de.previousWeek }}</span></a><div class="d-flex align-items-center gap-2"><h1 class="h2 mb-0 d-none d-md-block">{{ de.timetable }}</h1><label class="visually-hidden" for="dashboard-week">{{ de.chooseWeek }}</label><select id="dashboard-week" v-model="selectedWeek" class="form-select" @change="navigateToWeek"><option v-for="option in weekOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select></div><a :href="weekUrl(props.nextWeek)" class="btn btn-outline-primary" :aria-label="de.nextWeek"><span class="d-none d-sm-inline me-1">{{ de.nextWeek }}</span><i class="bi bi-chevron-right" aria-hidden="true"></i></a></div>
            <div v-if="!hasSchoolYear" class="alert alert-info">{{ de.noSchoolYearWeek }}</div>
            <div v-else-if="!periodNumbers.length" class="alert alert-info">{{ de.noPeriods }}</div>
            <div v-else class="table-responsive"><table class="table table-bordered align-middle timetable-grid"><thead><tr><th scope="col">{{ de.period }}</th><th v-for="day in days" :key="day.date" scope="col"><span class="text-capitalize">{{ day.label }}</span><small class="d-block text-muted">{{ formatDate(day.date) }}</small></th></tr></thead><tbody><tr v-for="periodNumber in periodNumbers" :key="periodNumber"><th scope="row" class="text-center">{{ periodNumber }}</th><td v-for="day in days" :key="day.date"><div v-for="entry in entriesFor(day, periodNumber)" :key="`${entry.group_id}-${entry.period_number}`" class="mb-1"><div class="small text-muted">{{ entry.starts_at }}–{{ entry.ends_at }}</div><a :href="`/unterrichtsgruppen/${entry.group_id}`" class="fw-semibold text-decoration-none">{{ entry.group_name }}</a><div class="small text-muted">{{ entry.school_name }}</div></div><span v-if="!entriesFor(day, periodNumber).length" class="text-muted">–</span></td></tr></tbody></table></div>
        </div>
    </AppShell>
</template>
