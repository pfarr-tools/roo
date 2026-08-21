<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import GroupSongbookPanel from '../../Components/TeachingGroups/GroupSongbookPanel.vue'
import { ref, computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import de from '../../i18n/de'
import { requestConfirmation } from '../../utils/confirmation'
import Tab from '../../Components/Ui/Tabs/Tab.vue'
import TabHeader from '../../Components/Ui/Tabs/TabHeader.vue'
import TabHeaders from '../../Components/Ui/Tabs/TabHeaders.vue'
import Tabs from '../../Components/Ui/Tabs/Tabs.vue'

const props = defineProps({ group: Object, students: Array, curricula: Array, schoolPeriods: Array, ritualPhaseTemplates: Array, competencies: { type: Array, default: () => [] }, denominationOptions: { type: Array, default: () => [] }, songVersions: { type: Array, default: () => [] }, songbookVersions: { type: Array, default: () => [] }, assessments: { type: Array, default: () => [] }, reportPeriods: { type: Array, default: () => [] } })
const activeTab = ref('general')
const selectedStudent = ref(null)
const showStudentModal = ref(false)
const showImportModal = ref(false)
const showMemberModal = ref(false)
const showCurriculumModal = ref(false)
const editForm = useForm({ school_id: props.group.school_id, school_year_id: props.group.school_year_id, name: props.group.name, aktenzeichen: props.group.aktenzeichen ?? '', denomination: props.group.denomination ?? '', notes: props.group.notes ?? '', grade_levels: props.group.grade_levels.map(level => level.grade_level), periods: (props.group.school_periods ?? []).map(period => ({ school_period_id: period.id, weekday: period.pivot.weekday })), phase_template_ids: (props.group.rituals ?? []).map(ritual => ritual.phase_template_id) })
const studentForm = useForm({ school_id: props.group.school_id, first_name: '', last_name: '', class_name: '', notes: '' })
const importForm = useForm({ school_id: props.group.school_id, students: null })
const editStudentForm = useForm({ first_name: '', last_name: '', class_name: '', notes: '' })
const memberForm = useForm({ student_ids: [], starts_on: '', ends_on: '' })
const curriculumForm = useForm({ curriculum_assignments: (props.group.curricula ?? []).map(curriculum => ({ curriculum_id: curriculum.id, role: curriculum.pivot?.role ?? 'additional' })) })
const weekdays = de.weekdays.map((label, index) => ({ label, value: index + 1 }))
const availableDenominations = ['evangelical', 'catholic', 'old_catholic', 'syriac_orthodox']
const denominationLabel = value => de.denominationLabels[value] ?? value
const competenciesByKind = computed(() => ({ process: props.competencies.filter(item => item.kind === 'process'), content: props.competencies.filter(item => item.kind !== 'process') }))
const competencyText = competency => competency.presentation?.label || [competency.presentation?.identifier, competency.presentation?.text].filter(Boolean).join(' – ') || de.noCompetencyText
const competencyCardStyle = competency => ({ backgroundColor: competency.covered_hours ? `rgba(var(--bs-success-rgb), ${Math.min(0.78, 0.18 + competency.covered_hours * 0.16)})` : 'rgba(var(--bs-secondary-rgb), 0.04)' })

function save() {
    editForm.grade_levels = String(editForm.grade_levels).split(',').map(value => value.trim()).filter(Boolean)
    editForm.put(`/unterrichtsgruppen/${props.group.id}`, { onSuccess: () => router.visit('/unterrichtsgruppen') })
}

function createStudent(saveAndNew = false) {
    studentForm.post(`/unterrichtsgruppen/${props.group.id}/schuelerinnen`, {
        onSuccess: () => {
            studentForm.reset('first_name', 'last_name', 'class_name', 'notes')
            if (!saveAndNew) showStudentModal.value = false
        },
    })
}

function importStudents() {
    importForm.post('/schuelerinnen/importieren', {
        forceFormData: true,
        onSuccess: () => { importForm.reset('students'); showImportModal.value = false },
    })
}

function assign() {
    memberForm.post(`/unterrichtsgruppen/${props.group.id}/mitglieder`, {
        onSuccess: () => { memberForm.reset(); showMemberModal.value = false },
    })
}

function curriculumSelected(curriculumId) {
    return curriculumForm.curriculum_assignments.some(assignment => assignment.curriculum_id === curriculumId)
}

function toggleCurriculum(curriculumId) {
    curriculumForm.curriculum_assignments = curriculumSelected(curriculumId)
        ? curriculumForm.curriculum_assignments.filter(assignment => assignment.curriculum_id !== curriculumId)
        : [...curriculumForm.curriculum_assignments, { curriculum_id: curriculumId, role: 'additional' }]
}

function setCurriculumRole(curriculumId, role) {
    curriculumForm.curriculum_assignments = curriculumForm.curriculum_assignments.map(assignment => assignment.curriculum_id === curriculumId ? { ...assignment, role } : assignment)
}

function saveCurricula() {
    curriculumForm.put(`/unterrichtsgruppen/${props.group.id}/curricula`, { onSuccess: () => { showCurriculumModal.value = false } })
}
function periodFor(number) { return props.schoolPeriods.find(period => period.period_number === number) }
function periodSelected(period, weekday) { return editForm.periods.some(item => item.school_period_id === period?.id && item.weekday === weekday) }
function togglePeriod(period, weekday) {
    if (!period) return
    editForm.periods = periodSelected(period, weekday) ? editForm.periods.filter(item => !(item.school_period_id === period.id && item.weekday === weekday)) : [...editForm.periods, { school_period_id: period.id, weekday }]
}
function editStudent(student) {
    selectedStudent.value = student
    editStudentForm.defaults({ first_name: student.first_name, last_name: student.last_name, class_name: student.class_name, notes: student.notes ?? '' })
    editStudentForm.reset()
}
function saveStudent() { editStudentForm.put(`/schuelerinnen/${selectedStudent.value.id}`, { onSuccess: () => { selectedStudent.value = null } }) }
async function deleteStudent(student) { if (await requestConfirmation({ message: de.deleteStudentConfirm })) useForm({}).delete(`/schuelerinnen/${student.id}`) }
async function remove(student) { if (await requestConfirmation({ message: `${student.first_name} ${student.last_name} aus der Gruppe entfernen?` })) useForm({}).delete(`/unterrichtsgruppen/${props.group.id}/mitglieder/${student.id}`) }
</script>

<template>
    <AppShell>
        <template #toolbar><a href="/unterrichtsgruppen" class="btn btn-sm btn-light" :title="de.close" :aria-label="de.close"><i class="bi bi-x-lg" aria-hidden="true"></i></a><button class="btn btn-sm btn-primary" type="button" @click="save">{{ de.saveChanges }}</button></template>
        <div class="container-full px-3 py-4">
            <h1 class="h2">{{ group.name }}</h1>
            <TabHeaders :aria-label="`${group.name} Bereiche`">
                <TabHeader id="general" :title="de.groupGeneral" :active-tab="activeTab" icon="gear" @select="activeTab = $event" />
                <TabHeader id="timetable" :title="de.groupTimetable" :active-tab="activeTab" icon="calendar-week" @select="activeTab = $event" />
                <TabHeader id="students" :title="de.students" :active-tab="activeTab" icon="people" :count="group.students.length" @select="activeTab = $event" />
                <TabHeader id="contents" :title="de.groupContents" :active-tab="activeTab" icon="collection" @select="activeTab = $event" />
                <TabHeader id="competencies" :title="de.competencies" :active-tab="activeTab" icon="journal-check" :count="competencies.length" @select="activeTab = $event" />
                <TabHeader id="assessments" :title="de.groupAssessments" :active-tab="activeTab" icon="clipboard-data" :count="assessments.length" @select="activeTab = $event" />
                <TabHeader id="evaluations" :title="de.groupEvaluations" :active-tab="activeTab" icon="bar-chart" :count="reportPeriods.length" @select="activeTab = $event" />
            </TabHeaders>
            <Tabs :active-tab="activeTab">
            <Tab id="general" :active-tab="activeTab">
            <form class="card card-body mb-4" @submit.prevent="save"><h2 class="h5">{{ de.teachingGroup }}</h2><div class="row g-3"><div class="col-md-6"><label class="form-label">{{ de.groupName }}</label><input v-model="editForm.name" class="form-control" required></div><div class="col-md-6"><label class="form-label">{{ de.groupGrades }}</label><input v-model="editForm.grade_levels" class="form-control" required></div><div class="col-md-6"><label class="form-label">{{ de.denomination }}</label><select v-model="editForm.denomination" class="form-select"><option value="">{{ de.choose }}</option><option v-for="option in availableDenominations" :key="option" :value="option">{{ denominationLabel(option) }}</option></select><div class="form-text">{{ de.groupDenominationHint }}</div></div><div class="col-md-6"><label class="form-label">{{ de.aktenzeichen }}</label><select v-model="editForm.aktenzeichen" class="form-select"><option value="">{{ de.noAktenzeichen }}</option><option v-for="option in de.aktenzeichenOptions" :key="option.value" :value="option.value">{{ option.value }} – {{ option.label }}</option></select><div class="form-text">{{ de.aktenzeichenHint }}</div></div><div class="col-12"><label class="form-label">{{ de.notes }}</label><textarea v-model="editForm.notes" class="form-control" rows="2"></textarea></div></div></form>
            </Tab>
            <Tab id="competencies" :active-tab="activeTab">
                <section class="card card-body"><h2 class="h5 mb-1">{{ de.competencies }}</h2><p class="text-muted">{{ editForm.denomination ? denominationLabel(editForm.denomination) : de.allDenominations }}</p><div v-if="!competencies.length" class="text-muted">{{ de.noCompetencies }}</div><div v-else class="row g-4"><div v-for="kind in ['process', 'content']" :key="kind" class="col-lg-6"><h3 class="h6">{{ kind === 'process' ? de.processCompetencies : de.contentCompetencies }}</h3><div v-for="competency in competenciesByKind[kind]" :key="competency.id" class="border-bottom py-2 px-2 mb-1" :style="competencyCardStyle(competency)"><div class="small">{{ competencyText(competency) }}</div><div class="small text-muted">{{ competency.topic_title }} · {{ competency.grade }}. {{ de.classLabel }}<span v-if="competency.covered_hours"> · {{ competency.covered_hours }} {{ de.hours.toLowerCase() }} im Plan</span></div></div><p v-if="!competenciesByKind[kind].length" class="small text-muted">{{ de.noCompetencies }}</p></div></div></section>
            </Tab>
            <Tab id="timetable" :active-tab="activeTab">
                <section class="card card-body"><div class="mb-3"><h2 class="h5 mb-1">{{ de.regularPeriods }}</h2><p class="text-muted mb-0">{{ de.regularPeriodsIntro }} {{ de.saveChanges }}.</p></div><div v-if="!schoolPeriods.length" class="text-muted">{{ de.noPeriods }}</div><div v-else class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>{{ de.period }}</th><th v-for="weekday in weekdays" :key="weekday.value">{{ weekday.label }}</th></tr></thead><tbody><tr v-for="number in 12" :key="number"><th>{{ number }}</th><td v-for="weekday in weekdays" :key="weekday.value"><template v-if="periodFor(number)"><button class="btn btn-sm w-100" :class="periodSelected(periodFor(number), weekday.value) ? 'btn-primary' : 'btn-outline-secondary'" type="button" @click="togglePeriod(periodFor(number), weekday.value)">{{ periodFor(number).starts_at.slice(0, 5) }}–{{ periodFor(number).ends_at.slice(0, 5) }}</button></template><span v-else class="text-muted">–</span></td></tr></tbody></table></div><div class="text-end mt-3"><button class="btn btn-primary" type="button" @click="save">{{ de.saveChanges }}</button></div></section>
            </Tab>
            <Tab id="students" :active-tab="activeTab">
                <section class="card card-body"><div class="d-flex justify-content-between align-items-center gap-2 mb-3"><h2 class="h5 mb-0">{{ de.members }} ({{ group.students.length }})</h2><div class="d-flex gap-1"><button class="btn btn-sm btn-outline-primary" type="button" @click="showMemberModal = true">{{ de.assign }}</button><button class="btn btn-sm btn-outline-primary" type="button" @click="showStudentModal = true">{{ de.create }}</button><button class="btn btn-sm btn-outline-primary" type="button" @click="showImportModal = true">{{ de.import }}</button></div></div><div v-if="!group.students.length" class="text-muted mb-3">{{ de.noMembers }}</div><div v-for="student in group.students" :key="student.id" class="border-bottom py-2"><div class="d-flex justify-content-between align-items-center"><span>{{ student.last_name }}, {{ student.first_name }} <small class="text-muted">({{ student.class_name }})</small></span><div class="d-flex gap-1"><button class="btn btn-sm btn-outline-secondary" type="button" :title="de.editStudent" @click="editStudent(student)"><i class="bi bi-pencil" aria-hidden="true"></i></button><button class="btn btn-sm btn-outline-danger" type="button" :title="de.removeAssignment" @click="remove(student)"><i class="bi bi-x-lg" aria-hidden="true"></i></button><button class="btn btn-sm btn-outline-danger" type="button" :title="de.deleteStudent" @click="deleteStudent(student)"><i class="bi bi-trash" aria-hidden="true"></i></button></div></div><small v-if="student.pivot?.starts_on || student.pivot?.ends_on" class="text-muted">{{ student.pivot?.starts_on ?? '–' }} – {{ student.pivot?.ends_on ?? '–' }}</small></div></section>
            </Tab>
            <Tab id="contents" :active-tab="activeTab">
                <div class="row g-4 mb-4"><div class="col-md-6"><section class="card card-body h-100"><div class="d-flex justify-content-between align-items-start gap-3"><div><h2 class="h5 mb-1">{{ de.groupCurricula }}</h2><p class="text-muted mb-0">{{ de.groupCurriculaIntro }}</p></div><button class="btn btn-sm btn-outline-primary" type="button" @click="showCurriculumModal = true">{{ de.curriculumAssignments }}</button></div><div v-if="!group.curricula?.length" class="text-muted mt-3">{{ de.noGroupCurricula }}</div><div v-else class="d-flex flex-wrap gap-2 mt-3"><span v-for="curriculum in group.curricula" :key="curriculum.id" class="badge text-bg-light">{{ curriculum.title }} · {{ curriculum.pivot.role === 'primary' ? de.primary : de.additional }}</span></div></section></div><div class="col-md-6"><section class="card card-body h-100"><div class="d-flex justify-content-between align-items-start gap-3"><div><h2 class="h5 mb-1">{{ de.groupRituals }}</h2><p class="text-muted mb-0">{{ de.groupRitualsIntro }}</p></div></div><div v-if="!ritualPhaseTemplates?.length" class="text-muted mt-3">{{ de.noPhaseTemplates }}</div><div v-else class="mt-3"><label class="form-label">{{ de.phaseTemplates }}</label><select v-model="editForm.phase_template_ids" class="form-select" multiple size="5"><option v-for="template in ritualPhaseTemplates" :key="template.id" :value="template.id">{{ template.title }}<template v-if="template.duration_minutes"> · {{ template.duration_minutes }} Min.</template></option></select><div class="form-text">{{ de.groupRitualsSelectionHint }} {{ de.saveChanges }}.</div></div></section></div></div>
                <GroupSongbookPanel :group="group" :song-versions="songVersions" :songbook-versions="songbookVersions" />
            </Tab>
            <Tab id="assessments" :active-tab="activeTab">
                <div class="card card-body"><div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h4">{{ de.groupAssessments }}</h2><p class="text-muted mb-0">Lernstandserhebungen und Aufgaben dieser Gruppe.</p></div><a class="btn btn-primary" :href="`/unterrichtsgruppen/${group.id}/lernstandserhebungen/neu`"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Neue Lernstandserhebung</a></div><div v-if="!assessments.length" class="text-muted">Noch keine Lernstandserhebung angelegt.</div><div v-for="assessment in assessments" :key="assessment.id" class="border-top py-3 d-flex justify-content-between align-items-start"><div><strong>{{ assessment.title }}</strong><span v-if="assessment.assessed_on" class="text-muted ms-2">{{ assessment.assessed_on }}</span><span class="d-block small text-muted">{{ assessment.tasks.length }} Aufgaben</span></div><a class="btn btn-sm btn-outline-primary" :href="`/unterrichtsgruppen/${group.id}/lernstandserhebungen/${assessment.id}/bearbeiten`">Bearbeiten</a></div></div>
            </Tab>
            <Tab id="evaluations" :active-tab="activeTab">
                <div class="card card-body"><div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h4">{{ de.groupEvaluations }}</h2><p class="text-muted mb-0">Bewertungszeiträume und bearbeitbare Entwürfe.</p></div><a class="btn btn-primary" :href="`/unterrichtsgruppen/${group.id}/bewertungen/neu`"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Bewertungszeitraum anlegen</a></div><div v-if="!reportPeriods.length" class="text-muted">Noch kein Bewertungszeitraum angelegt.</div><div v-for="period in reportPeriods" :key="period.id" class="border-top py-3"><strong>{{ period.label }}</strong><div v-if="!period.evaluations?.length" class="small text-muted mt-1">Keine Schüler:innen in diesem Zeitraum.</div><div v-for="evaluation in period.evaluations" :key="evaluation.id" class="d-flex justify-content-between align-items-start mt-2"><div><span>{{ evaluation.student.last_name }}, {{ evaluation.student.first_name }}</span><span class="badge ms-2" :class="evaluation.status === 'confirmed' ? 'text-bg-success' : 'text-bg-light'">{{ evaluation.status === 'confirmed' ? 'bestätigt' : 'Entwurf' }}</span><p class="mb-0 mt-1 small text-muted text-pre-wrap">{{ evaluation.draft_text || 'Noch kein Bewertungsentwurf.' }}</p></div><a class="btn btn-sm btn-outline-primary" :href="`/unterrichtsgruppen/${group.id}/bewertungen/${evaluation.id}/bearbeiten`">Bearbeiten</a></div></div></div>
            </Tab>
            </Tabs>
        </div>
        <div v-if="showCurriculumModal" class="roo-modal-backdrop" role="presentation" @click.self="showCurriculumModal = false"><section class="roo-modal" role="dialog" aria-modal="true" :aria-label="de.curriculumAssignments"><div class="card border-0"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h5 mb-1">{{ de.curriculumAssignments }}</h2><p class="text-muted mb-0">{{ de.groupCurriculaIntro }}</p></div><button class="btn-close" type="button" :aria-label="de.close" @click="showCurriculumModal = false"></button></div><div v-if="!curricula.length" class="text-muted">{{ de.noCurricula }}</div><div v-for="curriculum in curricula" :key="curriculum.id" class="row g-2 align-items-center border-bottom py-2"><div class="col-1"><input :id="`group-curriculum-${curriculum.id}`" class="form-check-input" type="checkbox" :checked="curriculumSelected(curriculum.id)" @change="toggleCurriculum(curriculum.id)"></div><div class="col"><label class="form-check-label" :for="`group-curriculum-${curriculum.id}`">{{ curriculum.title }}</label></div><div v-if="curriculumSelected(curriculum.id)" class="col-sm-4"><select :value="curriculumForm.curriculum_assignments.find(assignment => assignment.curriculum_id === curriculum.id).role" class="form-select form-select-sm" @change="setCurriculumRole(curriculum.id, $event.target.value)"><option value="primary">{{ de.primary }}</option><option value="additional">{{ de.additional }}</option></select></div></div><div class="d-flex justify-content-end gap-2 mt-4"><button class="btn btn-outline-secondary" type="button" @click="showCurriculumModal = false">{{ de.cancel }}</button><button class="btn btn-primary" type="button" :disabled="curriculumForm.processing" @click="saveCurricula">{{ de.saveChanges }}</button></div></div></div></section></div>
        <div v-if="showMemberModal" class="roo-modal-backdrop" role="presentation" @click.self="showMemberModal = false"><section class="roo-modal" role="dialog" aria-modal="true" :aria-label="de.assignStudent"><div class="card border-0"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">{{ de.assignStudent }}</h2><button class="btn-close" type="button" :aria-label="de.close" @click="showMemberModal = false"></button></div><form @submit.prevent="assign"><label class="form-label">{{ de.assignStudent }}</label><select v-model="memberForm.student_ids" class="form-select" multiple size="8" required><option v-for="student in students.filter(item => !group.students.some(member => member.id === item.id))" :key="student.id" :value="student.id">{{ student.last_name }}, {{ student.first_name }} ({{ student.class_name }})</option></select><div class="form-text">{{ de.multiSelectHint }}</div><div class="row g-2 mt-1"><div class="col-sm-6"><label class="form-label small">{{ de.membershipFrom }}</label><input v-model="memberForm.starts_on" class="form-control" type="date"></div><div class="col-sm-6"><label class="form-label small">{{ de.membershipTo }}</label><input v-model="memberForm.ends_on" class="form-control" type="date"></div></div><div class="form-text">{{ de.membershipDateHint }}</div><div class="d-flex justify-content-end gap-2 mt-4"><button class="btn btn-outline-secondary" type="button" @click="showMemberModal = false">{{ de.cancel }}</button><button class="btn btn-primary" type="submit">{{ de.add }}</button></div></form></div></div></section></div>
        <div v-if="showStudentModal" class="roo-modal-backdrop" role="presentation" @click.self="showStudentModal = false"><section class="roo-modal" role="dialog" aria-modal="true" :aria-label="de.addStudent"><div class="card border-0"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">{{ de.addStudent }}</h2><button class="btn-close" type="button" :aria-label="de.close" @click="showStudentModal = false"></button></div><form @submit.prevent="createStudent(false)"><div class="row g-2"><div class="col-6"><label class="form-label">{{ de.firstName }}</label><input v-model="studentForm.first_name" class="form-control" required></div><div class="col-6"><label class="form-label">{{ de.lastName }}</label><input v-model="studentForm.last_name" class="form-control" required></div></div><label class="form-label mt-2">{{ de.actualClass }}</label><input v-model="studentForm.class_name" class="form-control" placeholder="z. B. 2a" required><label class="form-label mt-2">{{ de.notes }}</label><textarea v-model="studentForm.notes" class="form-control" rows="2"></textarea><div class="form-text">{{ de.actualClassHint }}</div><div class="d-flex justify-content-end gap-2 mt-4"><button class="btn btn-outline-secondary" type="button" @click="showStudentModal = false">{{ de.cancel }}</button><button class="btn btn-outline-primary" type="button" :disabled="studentForm.processing" @click="createStudent(true)">{{ de.saveAndNew }}</button><button class="btn btn-primary" type="submit" :disabled="studentForm.processing">{{ de.save }}</button></div></form></div></div></section></div>
        <div v-if="showImportModal" class="roo-modal-backdrop" role="presentation" @click.self="showImportModal = false"><section class="roo-modal" role="dialog" aria-modal="true" :aria-label="de.importStudents"><div class="card border-0"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">{{ de.importStudents }}</h2><button class="btn-close" type="button" :aria-label="de.close" @click="showImportModal = false"></button></div><p class="small text-muted">{{ de.importStudentsHint }}</p><form @submit.prevent="importStudents"><input class="form-control" type="file" accept=".csv,.txt,text/csv,text/plain" required @change="importForm.students = $event.target.files[0] ?? null"><div class="d-flex justify-content-end gap-2 mt-4"><button class="btn btn-outline-secondary" type="button" @click="showImportModal = false">{{ de.cancel }}</button><button class="btn btn-primary" type="submit" :disabled="importForm.processing">{{ de.importStudents }}</button></div></form></div></div></section></div>
        <div v-if="selectedStudent" class="roo-modal-backdrop" role="presentation" @click.self="selectedStudent = null"><section class="roo-modal" role="dialog" aria-modal="true" :aria-label="de.editStudent"><div class="card border-0"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">{{ de.editStudent }}</h2><button class="btn-close" type="button" :aria-label="de.close" @click="selectedStudent = null"></button></div><form @submit.prevent="saveStudent"><div class="row g-2"><div class="col-6"><label class="form-label">{{ de.firstName }}</label><input v-model="editStudentForm.first_name" class="form-control" required></div><div class="col-6"><label class="form-label">{{ de.lastName }}</label><input v-model="editStudentForm.last_name" class="form-control" required></div></div><label class="form-label mt-2">{{ de.actualClass }}</label><input v-model="editStudentForm.class_name" class="form-control" required><label class="form-label mt-2">{{ de.notes }}</label><textarea v-model="editStudentForm.notes" class="form-control" rows="2"></textarea><div class="d-flex justify-content-end gap-2 mt-4"><button class="btn btn-outline-secondary" type="button" @click="selectedStudent = null">{{ de.cancel }}</button><button class="btn btn-primary" type="submit" :disabled="editStudentForm.processing">{{ de.saveChanges }}</button></div></form></div></div></section></div>
    </AppShell>
</template>
