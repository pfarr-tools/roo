<script setup>
import { computed, ref } from 'vue'
import AppShell from '../../Components/Ui/AppShell.vue'
import AssessmentScanUploadModal from '../../Features/AssessmentEvaluation/AssessmentScanUploadModal.vue'
import BookletAssignment from '../../Features/AssessmentEvaluation/BookletAssignment.vue'
import BookletList from '../../Features/AssessmentEvaluation/BookletList.vue'
import { evaluationSections } from '../../Features/AssessmentEvaluation/presentation'
import de from '../../i18n/de'

const props = defineProps({
    group: { type: Object, required: true },
    assessment: { type: Object, required: true },
    scan: { type: Object, default: () => ({ warnings: [] }) },
    students: { type: Array, default: () => [] },
    tasks: { type: Array, default: () => [] },
    booklets: { type: Array, default: () => [] },
    progress: { type: Object, default: () => ({}) },
})

const activeSection = ref('scans')
const scanOpen = ref(false)
const openBooklets = computed(() => props.booklets.filter((booklet) => booklet.status === 'open'))
</script>

<template>
    <AppShell>
        <template #toolbar>
            <a
                :href="`/unterrichtsgruppen/${group.id}/lernstandserhebungen/${assessment.id}/bearbeiten`"
                class="btn btn-sm btn-light"
                :title="de.close"
                :aria-label="de.close"
            ><i class="bi bi-x-lg" aria-hidden="true"></i></a>
            <button class="btn btn-sm btn-primary ms-2" type="button" @click="scanOpen = true">
                <i class="bi bi-upload me-1" aria-hidden="true"></i>{{ de.assessmentScanSubmit }}
            </button>
        </template>

        <div class="container-full px-3 py-4">
            <h1 class="h2 mb-1">{{ de.assessmentEvaluationTitle }}</h1>
            <p class="text-muted mb-4">{{ assessment.title }}</p>

            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-lg-3"><div class="card card-body h-100"><div class="small text-muted">{{ de.assessmentEvaluationBooklets }}</div><div class="fs-3">{{ progress.total_booklets ?? booklets.length }}</div></div></div>
                <div class="col-sm-6 col-lg-3"><div class="card card-body h-100"><div class="small text-muted">{{ de.assessmentEvaluationAssignStudent }}</div><div class="fs-3">{{ progress.assigned_booklets ?? 0 }} / {{ progress.open_booklets ?? openBooklets.length }}</div></div></div>
                <div class="col-sm-6 col-lg-3"><div class="card card-body h-100"><div class="small text-muted">{{ de.assessmentEvaluationOpen }}</div><div class="fs-3">{{ progress.unassigned_booklets ?? 0 }}</div></div></div>
                <div class="col-sm-6 col-lg-3"><div class="card card-body h-100"><div class="small text-muted">{{ de.assessmentEvaluationDiscarded }}</div><div class="fs-3">{{ progress.discarded_booklets ?? 0 }}</div></div></div>
            </div>

            <nav class="nav nav-pills gap-2 mb-4" :aria-label="de.assessmentEvaluationTitle">
                <button v-for="section in evaluationSections" :key="section.id" class="nav-link" :class="{ active: activeSection === section.id }" type="button" :aria-pressed="activeSection === section.id" @click="activeSection = section.id">{{ de[section.label] }}</button>
            </nav>

            <section v-if="activeSection === 'scans'" aria-labelledby="assessment-scans-heading">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div><h2 id="assessment-scans-heading" class="h4 mb-1">{{ de.assessmentEvaluationScans }}</h2><p class="text-muted mb-0">{{ de.assessmentEvaluationScansIntro }}</p></div>
                    <button class="btn btn-outline-primary" type="button" @click="scanOpen = true"><i class="bi bi-upload me-1" aria-hidden="true"></i>{{ de.assessmentScanSubmit }}</button>
                </div>
                <BookletList :group="group" :assessment="assessment" :booklets="booklets" />
            </section>

            <section v-else-if="activeSection === 'booklets'" aria-labelledby="assessment-booklets-heading">
                <h2 id="assessment-booklets-heading" class="h4 mb-1">{{ de.assessmentEvaluationAssignments }}</h2>
                <p class="text-muted mb-4">{{ de.assessmentEvaluationAssignmentsIntro }}</p>
                <div v-if="openBooklets.length" class="row g-3">
                    <div v-for="booklet in openBooklets" :key="booklet.id" class="col-md-6 col-xl-4">
                        <BookletAssignment :group="group" :assessment="assessment" :booklet="booklet" :booklets="booklets" :students="students" />
                    </div>
                </div>
                <p v-else class="text-muted mb-0">{{ de.assessmentEvaluationNoOpenBooklets }}</p>
            </section>

            <section v-else aria-labelledby="assessment-tasks-heading">
                <h2 id="assessment-tasks-heading" class="h4 mb-1">{{ de.assessmentEvaluationTasks }}</h2>
                <p class="text-muted mb-4">{{ de.assessmentEvaluationTasksIntro }}</p>
                <div v-if="tasks.length" class="list-group">
                    <div v-for="task in tasks" :key="task.id" class="list-group-item d-flex justify-content-between align-items-center"><span>{{ task.title }}</span><span class="badge text-bg-light">{{ task.expectations?.length ?? 0 }}</span></div>
                </div>
                <p v-else class="text-muted mb-0">{{ de.assessmentEvaluationNoTasks }}</p>
            </section>

            <div v-if="scan.warnings?.length" class="alert alert-warning mt-4" role="alert">
                <h2 class="h6">{{ de.assessmentScanWarnings }}</h2>
                <ul class="mb-0"><li v-for="warning in scan.warnings" :key="warning">{{ warning }}</li></ul>
            </div>
        </div>

        <AssessmentScanUploadModal v-if="scanOpen" :group="group" :assessment="assessment" @close="scanOpen = false" />
    </AppShell>
</template>
