<script setup>
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppShell from '../../Components/Ui/AppShell.vue'
import Tab from '../../Components/Ui/Tabs/Tab.vue'
import TabHeader from '../../Components/Ui/Tabs/TabHeader.vue'
import TabHeaders from '../../Components/Ui/Tabs/TabHeaders.vue'
import Tabs from '../../Components/Ui/Tabs/Tabs.vue'
import AssessmentScanUploadModal from '../../Features/AssessmentEvaluation/AssessmentScanUploadModal.vue'
import BookletAssignment from '../../Features/AssessmentEvaluation/BookletAssignment.vue'
import TaskEvaluation from '../../Features/AssessmentEvaluation/TaskEvaluation.vue'
import de from '../../i18n/de'

const props = defineProps({
    group: { type: Object, required: true },
    assessment: { type: Object, required: true },
    scan: { type: Object, default: () => ({ warnings: [] }) },
    students: { type: Array, default: () => [] },
    tasks: { type: Array, default: () => [] },
    booklets: { type: Array, default: () => [] },
    taskFragments: { type: Array, default: () => [] },
    progress: { type: Object, default: () => ({}) },
})

const activeSection = ref('booklets')
const scanOpen = ref(false)
const manualBookletOpen = ref(false)
const manualBookletForm = useForm({ student_id: '' })
const activeTaskId = ref(null)
const taskOpenKey = ref(0)
const openBooklets = computed(() => props.booklets.filter((booklet) => booklet.status === 'open'))
const unassignedBooklets = computed(() => openBooklets.value.filter((booklet) => !booklet.student_id))
const activeTask = computed(() => props.tasks.find((task) => task.id === activeTaskId.value) ?? null)
const activeTaskFragments = computed(() => props.taskFragments.filter((fragment) => fragment.assessment_task_id === activeTaskId.value))

function taskProgress(task) {
    const fragments = props.taskFragments.filter((fragment) => fragment.assessment_task_id === task.id)
    const completed = fragments.filter((fragment) => fragment.review !== null).length

    return { total: fragments.length, completed, open: fragments.length - completed }
}

function openTask(taskId) {
    activeTaskId.value = taskId
    taskOpenKey.value += 1
}

function selectTask(event) {
    openTask(Number(event.target.value))
}
function createManualBooklet() {
    manualBookletForm.post(`/unterrichtsgruppen/${props.group.id}/lernstandserhebungen/${props.assessment.id}/auswertung/booklets/manuell`, {
        preserveScroll: true,
        onSuccess: () => {
            manualBookletOpen.value = false
            manualBookletForm.reset()
        },
    })
}
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
            <button class="btn btn-sm btn-outline-light ms-2" type="button" @click="manualBookletOpen = true">
                <i class="bi bi-person-plus me-1" aria-hidden="true"></i>{{ de.assessmentManualBookletAdd }}
            </button>
        </template>

        <div class="container-full px-3 py-4">
            <h1 class="h2 mb-1">{{ assessment.title }} auswerten</h1>
            <p class="text-muted mb-4">
                <span class="assessment-evaluation-stat">{{ de.assessmentEvaluationBooklets }}: {{ progress.total_booklets ?? booklets.length }}</span> ·
                <span class="assessment-evaluation-stat">{{ de.assessmentEvaluationAssigned }}: {{ progress.assigned_booklets ?? 0 }}</span> ·
                <span class="assessment-evaluation-stat">{{ de.assessmentEvaluationOpen }}: {{ progress.unassigned_booklets ?? unassignedBooklets.length }}</span> ·
                <span class="assessment-evaluation-stat">{{ de.assessmentEvaluationDiscarded }}: {{ progress.discarded_booklets ?? 0 }}</span>
            </p>

            <TabHeaders :aria-label="de.assessmentEvaluationTitle">
                <TabHeader id="booklets" :title="de.assessmentEvaluationAssignments" :active-tab="activeSection" icon="person-check" @select="activeSection = $event" />
                <TabHeader id="tasks" :title="de.assessmentEvaluationTasks" :active-tab="activeSection" icon="clipboard-check" @select="activeSection = $event" />
            </TabHeaders>

            <Tabs :active-tab="activeSection">
            <Tab id="booklets" :active-tab="activeSection">
                <h2 id="assessment-booklets-heading" class="h4 mb-1">{{ de.assessmentEvaluationAssignments }}</h2>
                <p class="text-muted mb-4">{{ de.assessmentEvaluationAssignmentsIntro }}</p>
                <div v-if="openBooklets.length" class="row g-3">
                    <div v-for="booklet in openBooklets" :key="booklet.id" class="col-md-6 col-xl-4">
                        <BookletAssignment :group="group" :assessment="assessment" :booklet="booklet" :booklets="booklets" :students="students" />
                    </div>
                </div>
                <p v-else class="text-muted mb-0">{{ de.assessmentEvaluationNoOpenBooklets }}</p>
            </Tab>

            <Tab id="tasks" :active-tab="activeSection">
                <h2 id="assessment-tasks-heading" class="h4 mb-1">{{ de.assessmentEvaluationTasks }}</h2>
                <p class="text-muted mb-4">{{ de.assessmentEvaluationTasksIntro }}</p>
                <div v-if="tasks.length">
                    <label class="form-label" for="assessment-task-select">{{ de.assessmentEvaluationChooseTask }}</label>
                    <select id="assessment-task-select" class="form-select" :value="activeTaskId ?? ''" @change="selectTask">
                        <option value="">{{ de.assessmentEvaluationChooseTask }}</option>
                        <option v-for="task in tasks" :key="task.id" :value="task.id">
                            {{ task.title }} · {{ taskProgress(task).open }} {{ de.assessmentEvaluationOpen }} · {{ taskProgress(task).completed }} {{ de.assessmentEvaluationCompleted }} · {{ taskProgress(task).total }} {{ de.assessmentEvaluationTotal }}
                        </option>
                    </select>
                    <div class="mt-4">
                        <TaskEvaluation v-if="activeTask" :group="group" :assessment="assessment" :task="activeTask" :fragments="activeTaskFragments" :open-key="taskOpenKey" />
                        <p v-else class="text-muted mb-0">{{ de.assessmentEvaluationChooseTask }}</p>
                    </div>
                </div>
                <p v-else class="text-muted mb-0">{{ de.assessmentEvaluationNoTasks }}</p>
            </Tab>
            </Tabs>

            <div v-if="scan.warnings?.length" class="alert alert-warning mt-4" role="alert">
                <h2 class="h6">{{ de.assessmentScanWarnings }}</h2>
                <ul class="mb-0"><li v-for="warning in scan.warnings" :key="warning">{{ warning }}</li></ul>
            </div>
        </div>

        <AssessmentScanUploadModal v-if="scanOpen" :group="group" :assessment="assessment" @close="scanOpen = false" />
        <div v-if="manualBookletOpen" class="roo-modal-backdrop" role="presentation" @click.self="manualBookletOpen = false">
            <section class="roo-modal card border-0" role="dialog" aria-modal="true" :aria-label="de.assessmentManualBookletAdd">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">{{ de.assessmentManualBookletAdd }}</h2><button class="btn-close" type="button" :aria-label="de.close" @click="manualBookletOpen = false"></button></div>
                    <p class="text-muted small">{{ de.assessmentManualBookletHint }}</p>
                    <label class="form-label" for="manual-booklet-student">{{ de.student }}</label>
                    <select id="manual-booklet-student" v-model="manualBookletForm.student_id" class="form-select" :class="{ 'is-invalid': manualBookletForm.errors.student_id }">
                        <option value="">{{ de.choose }}</option>
                        <option v-for="student in students" :key="student.id" :value="student.id">{{ student.last_name }}, {{ student.first_name }}<template v-if="student.class_name"> ({{ student.class_name }})</template></option>
                    </select>
                    <div v-if="manualBookletForm.errors.student_id" class="invalid-feedback d-block">{{ manualBookletForm.errors.student_id }}</div>
                    <div class="d-flex justify-content-end gap-2 mt-4"><button class="btn btn-outline-secondary" type="button" @click="manualBookletOpen = false">{{ de.cancel }}</button><button class="btn btn-primary" type="button" :disabled="manualBookletForm.processing || !manualBookletForm.student_id" @click="createManualBooklet">{{ de.create }}</button></div>
                </div>
            </section>
        </div>
    </AppShell>
</template>
