<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import de from '../../i18n/de'
import { router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({ units: Array, educationPlans: Array, filters: Object })
const search = ref(props.filters?.q ?? '')
const editing = ref(null)
const form = useForm({ title: '', notes: '', education_plan_id: null })

function filter() { router.get('/unterrichtseinheiten', { q: search.value }, { preserveState: true, replace: true }) }
function edit(unit) { editing.value = unit; form.defaults({ title: unit.title, notes: unit.notes ?? '', education_plan_id: unit.education_plan_id }); form.reset(); form.clearErrors() }
function save() { form.put(`/unterrichtseinheiten/${editing.value.id}`, { onSuccess: () => { editing.value = null } }) }
function remove(unit) { if (window.confirm(de.deleteUnitConfirm)) router.delete(`/unterrichtseinheiten/${unit.id}`) }
</script>

<template>
    <AppShell>
        <div class="container-full px-3 py-4">
            <h1 class="h2">{{ de.teachingUnits }}</h1>
            <p class="text-muted">{{ de.teachingUnitsIntro }}</p>
            <form class="row g-2 mb-3" role="search" @submit.prevent="filter">
                <div class="col-sm-8 col-lg-5"><label class="visually-hidden" for="teaching-unit-search">{{ de.searchUnits }}</label><input id="teaching-unit-search" v-model="search" class="form-control" type="search" :placeholder="de.searchUnits"></div>
                <div class="col-auto"><button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search me-1" aria-hidden="true"></i>{{ de.filter }}</button></div>
            </form>
            <div v-if="$page.props.flash?.success" class="alert alert-success">{{ $page.props.flash.success }}</div>
            <div v-if="!units.length" class="alert alert-info">{{ de.noTeachingUnits }}</div>
            <div v-for="unit in units" :key="unit.id" class="card mb-2 planning-card">
                <div class="card-body d-flex justify-content-between gap-3">
                    <div><h2 class="h5 mb-1">{{ unit.title }}</h2><div class="small text-muted">{{ unit.group?.name }} · {{ unit.group?.school_year?.name }} · {{ unit.lessons_count }} {{ de.lessons }}<span v-if="unit.source_curriculum_topic"> · {{ de.sourceCurriculum }}: {{ unit.source_curriculum_topic.title }}</span><span v-if="unit.education_plan"> · {{ unit.education_plan.title }}</span></div></div>
                    <div class="d-flex gap-2 align-items-start"><button class="btn btn-sm btn-outline-primary" type="button" @click="edit(unit)"><i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ de.editUnit }}</button><button class="btn btn-sm btn-outline-danger" type="button" @click="remove(unit)"><i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden">{{ de.deleteUnit }}</span></button></div>
                </div>
            </div>
        </div>
        <div v-if="editing" class="roo-modal-backdrop" role="presentation" @click.self="editing = null"><section class="roo-modal" role="dialog" aria-modal="true" :aria-label="de.editUnit"><div class="card border-0"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">{{ de.editUnit }}</h2><button class="btn-close" type="button" :aria-label="de.close" @click="editing = null"></button></div><form @submit.prevent="save"><label class="form-label" for="unit-title">{{ de.unitTitle }}</label><input id="unit-title" v-model="form.title" class="form-control" required><label class="form-label mt-3" for="unit-plan">{{ de.educationPlan }}</label><select id="unit-plan" v-model="form.education_plan_id" class="form-select"><option :value="null">{{ de.choose }}</option><option v-for="plan in educationPlans" :key="plan.id" :value="plan.id">{{ plan.title }}{{ plan.external_identifier ? ' (' + plan.external_identifier + ')' : '' }}</option></select><label class="form-label mt-3" for="unit-notes">{{ de.notes }}</label><textarea id="unit-notes" v-model="form.notes" class="form-control" rows="5"></textarea><div class="d-flex justify-content-end gap-2 mt-4"><button class="btn btn-outline-secondary" type="button" @click="editing = null">{{ de.cancel }}</button><button class="btn btn-primary" type="submit" :disabled="form.processing">{{ de.saveChanges }}</button></div></form></div></div></section></div>
    </AppShell>
</template>
