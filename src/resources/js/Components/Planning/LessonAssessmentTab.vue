<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { requestConfirmation } from '../../utils/confirmation'
import de from '../../i18n/de'

const props = defineProps({
    scheduleSlotId: { type: [String, Number], required: true },
    groupId: { type: [String, Number], required: true },
    lessonId: { type: [String, Number], required: true },
    competencies: { type: Array, default: () => [] },
    assessmentTasks: { type: Array, default: () => [] },
})
const emit = defineEmits(['refresh'])
const modal = ref(null)
const librarySearch = ref('')
const libraryItems = ref([])
const libraryLoading = ref(false)

const competencyText = competency => competency.label || competency.text || ('Kompetenz ' + competency.id)
const tasksFor = competency => props.assessmentTasks.filter(task => competency.education_plan_competency_id && String(task.education_plan_competency_id) === String(competency.education_plan_competency_id))
function newUrl() { return '/unterricht/' + props.scheduleSlotId + '/pruefungsaufgaben/neu' }
function editUrl(task) { return '/unterricht/' + props.scheduleSlotId + '/pruefungsaufgaben/' + task.id + '/bearbeiten' }
async function remove(task) {
    if (!await requestConfirmation({ message: de.removeAssessmentTaskConfirm })) return
    router.delete('/unterricht/' + props.scheduleSlotId + '/pruefungsaufgaben/' + task.id, { preserveScroll: true, onSuccess: page => emit('refresh', page) })
}
async function searchLibrary() {
    libraryLoading.value = true
    try {
        const response = await fetch('/ressourcen/bibliothek?q=' + encodeURIComponent(librarySearch.value) + '&type=assessment-task', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        if (response.ok) libraryItems.value = await response.json()
    } finally { libraryLoading.value = false }
}
function close() { modal.value = null }
function openLibrary() { librarySearch.value = ''; modal.value = 'library'; searchLibrary() }
function assign(task) {
    router.post('/jahresplanung/' + props.groupId + '/ressourcen/assessment-task/' + task.id + '/zuordnen', { target_type: 'lesson', target_id: props.lessonId }, { preserveScroll: true, onSuccess: page => { close(); emit('refresh', page) } })
}
</script>

<template>
    <article class="card"><div class="card-body">
        <h2 id="assessment-heading" class="h5 mb-1">{{ de.lessonAssessment }}</h2><p class="text-muted mb-3">{{ de.lessonAssessmentIntro }}</p>
        <div class="table-responsive"><table class="table"><colgroup><col style="width: 40%"><col style="width: 40%"><col style="width: 20%"></colgroup><thead><tr><th class="align-top">{{ de.assessmentCompetency }}</th><th class="align-top">{{ de.assessmentTasks }}</th><th class="text-end align-top">{{ de.assessmentActions }}</th></tr></thead><tbody>
            <tr v-for="competency in competencies" :key="competency.id" class="align-top"><th class="fw-normal align-top">{{ competencyText(competency) }}</th><td class="align-top">
                <div v-if="tasksFor(competency).length" class="list-group list-group-flush"><div v-for="task in tasksFor(competency)" :key="task.id" class="list-group-item px-0 d-flex align-items-center gap-2"><span class="flex-grow-1">{{ task.title }}<small v-if="task.max_points" class="text-muted ms-2">{{ task.max_points }} Punkte</small></span><a class="btn btn-sm btn-outline-secondary" :href="editUrl(task)" title="Bearbeiten" aria-label="Prüfungsaufgabe bearbeiten"><i class="bi bi-pencil" aria-hidden="true"></i></a><button class="btn btn-sm btn-outline-danger" type="button" title="Entfernen" aria-label="Prüfungsaufgabe entfernen" @click="remove(task)"><i class="bi bi-trash" aria-hidden="true"></i></button></div></div>
                <span v-else class="text-muted small">{{ de.noAssessmentTask }}</span>
            </td><td class="text-end text-nowrap align-top"><a class="btn btn-sm btn-outline-primary me-1" :href="newUrl(competency)" :title="de.newAssessmentTask" :aria-label="de.newAssessmentTask"><i class="bi bi-plus-lg" aria-hidden="true"></i></a><button class="btn btn-sm btn-outline-secondary" type="button" @click="openLibrary(competency)"><i class="bi bi-collection me-1" aria-hidden="true"></i>Aus Bibliothek</button></td></tr>
            <tr v-if="!competencies.length"><td colspan="3" class="text-muted">{{ de.noContentCompetenciesForLesson }}</td></tr>
        </tbody></table></div>
    </div></article>
    <div v-if="modal === 'library'" class="roo-modal-backdrop" role="presentation" @click.self="close">
        <section class="roo-modal card border-0" role="dialog" aria-modal="true"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h6 mb-0">{{ de.assessmentTaskFromLibrary }}</h2><button class="btn-close" type="button" :aria-label="de.close" @click="close"></button></div><input v-model="librarySearch" class="form-control mb-3" type="search" :placeholder="de.searchLibrary" @input="searchLibrary"><div class="list-group library-picker-list"><button v-for="item in libraryItems" :key="item.id" class="list-group-item list-group-item-action text-start" type="button" @click="assign(item)"><strong>{{ item.title }}</strong><span class="d-block small text-muted">{{ item.description || item.competency || de.assessmentTaskFallback }}</span></button><p v-if="!libraryItems.length" class="small text-muted mb-0">{{ libraryLoading ? de.searching : de.noAssessmentTasksFound }}</p></div></div></section>
    </div>
</template>
