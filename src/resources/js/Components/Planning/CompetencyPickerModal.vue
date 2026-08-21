<script setup>
import de from '../../i18n/de'
import { computed, ref, watch } from 'vue'

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    competencies: { type: Array, default: () => [] },
    selectedIds: { type: Array, default: () => [] },
    competencyText: { type: Function, required: true },
    lessons: { type: Array, default: () => [] },
    coveredHours: { type: Object, default: () => ({}) },
    endpoint: { type: String, required: true },
    currentLessonId: { type: [String, Number], default: null },
})
const emit = defineEmits(['update:modelValue', 'apply'])

const activeTab = ref('process')
const search = ref('')
const debouncedSearch = ref('')
const draftSelectedIds = ref(new Set())
const pickerCompetencies = ref([])
const pickerCoveredHours = ref({})
const loading = ref(false)
let searchTimer

watch(() => props.modelValue, async open => {
    if (!open) return
    activeTab.value = 'process'
    search.value = ''
    debouncedSearch.value = ''
    draftSelectedIds.value = new Set(props.selectedIds)
    pickerCompetencies.value = [...props.competencies]
    pickerCoveredHours.value = { ...props.coveredHours }
    loading.value = true
    try {
        const response = await fetch(props.endpoint, { headers: { Accept: 'application/json' } })
        if (!response.ok) throw new Error('Kompetenzen konnten nicht geladen werden.')
        const data = await response.json()
        pickerCompetencies.value = data.competencies ?? []
        pickerCoveredHours.value = Object.fromEntries(Object.entries(data.covered_hours ?? {}).map(([id, hours]) => [String(id), Number(hours ?? 0)]))
    } finally {
        loading.value = false
    }
}, { immediate: true })

watch(search, value => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => { debouncedSearch.value = value.trim().toLowerCase() }, 250)
})

const competencyKind = competency => {
    const kind = competency.competency_kind || competency.area?.kind || competency.competency_area?.kind || competency.competency_presentation?.kind || 'content'
    return String(kind).includes('process') ? 'process' : 'content'
}
const areaFor = competency => competency.competency_area || competency.area || competency.education_plan_competency?.area || null
const matchesSearch = competency => !debouncedSearch.value || props.competencyText(competency).toLowerCase().includes(debouncedSearch.value)
const grouped = competencies => {
    const groups = new Map()
    const seen = new Set()
    competencies.filter(matchesSearch).forEach(competency => {
        const competencyKey = competency.external_identifier || competency.number || competency.id
        if (seen.has(competencyKey)) return
        seen.add(competencyKey)
        const area = areaFor(competency)
        const key = area?.identifier || 'other'
        if (!groups.has(key)) groups.set(key, { key, area, competencies: [] })
        groups.get(key).competencies.push(competency)
    })
    return [...groups.values()]
}
const processGroups = computed(() => grouped(pickerCompetencies.value.filter(competency => competencyKind(competency) === 'process')))
const contentGroups = computed(() => grouped(pickerCompetencies.value.filter(competency => competencyKind(competency) !== 'process')))
const activeGroups = computed(() => activeTab.value === 'process' ? processGroups.value : contentGroups.value)
const selectedCompetencies = computed(() => pickerCompetencies.value
    .filter(competency => draftSelectedIds.value.has(competency.id))
    .sort((left, right) => String(left.external_identifier || left.number || left.id).localeCompare(String(right.external_identifier || right.number || right.id), 'de', { numeric: true })))

const competencyHours = competency => props.lessons.reduce((total, lesson) => {
    const optionIds = new Set([competency.id, competency.education_plan_competency_id].filter(value => value !== null && value !== undefined).map(String))
    const represented = (lesson.competencies ?? []).some(item => [item.curriculum_topic_competency_id, item.education_plan_competency_id, item.curriculum_competency?.id, item.education_plan_competency?.id].filter(value => value !== null && value !== undefined).some(value => optionIds.has(String(value))))
    return total + (represented ? Number(lesson.duration ?? 0) : 0)
}, 0)
const coveredHoursFor = competency => Object.prototype.hasOwnProperty.call(pickerCoveredHours.value, String(competency.id))
    ? Number(pickerCoveredHours.value[String(competency.id)] ?? 0)
    : competencyHours(competency)
const competencyCardStyle = competency => {
    const hours = coveredHoursFor(competency)
    const intensity = Math.min(0.78, 0.18 + hours * 0.16)
    return { backgroundColor: hours ? `rgba(var(--bs-success-rgb), ${intensity})` : 'rgba(var(--bs-secondary-rgb), 0.04)' }
}

function toggle(competency) {
    const selected = new Set(draftSelectedIds.value)
    selected.has(competency.id) ? selected.delete(competency.id) : selected.add(competency.id)
    draftSelectedIds.value = selected
}
function close() { emit('update:modelValue', false) }
function apply() {
    emit('apply', [...draftSelectedIds.value], selectedCompetencies.value)
    emit('update:modelValue', false)
}
</script>

<template>
    <div v-if="modelValue" class="roo-modal-backdrop" role="presentation" @click.self="close">
        <section class="roo-modal roo-modal-wide" role="dialog" aria-modal="true" :aria-label="de.addCompetency" style="width: 80vw; max-width: 80vw; height: 80vh; max-height: 80vh">
            <div class="card border-0 h-100">
                <div class="card-body d-flex flex-column" style="min-height: 0">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">{{ de.addCompetency }}</h2>
                        <button class="btn-close" type="button" :aria-label="de.close" @click="close"></button>
                    </div>
                    <input v-model="search" class="form-control mb-3" :placeholder="de.searchCompetencies" type="search">
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item"><button class="nav-link" :class="{ active: activeTab === 'process' }" type="button" @click="activeTab = 'process'">{{ de.editProcessCompetencies }}</button></li>
                        <li class="nav-item"><button class="nav-link" :class="{ active: activeTab === 'content' }" type="button" @click="activeTab = 'content'">{{ de.editContentCompetencies }}</button></li>
                    </ul>
                    <div class="competency-picker-list flex-grow-1 overflow-auto pe-2" style="min-height: 0">
                        <div class="row g-2">
                        <template v-for="group in activeGroups" :key="group.key">
                            <h3 v-if="group.area" class="col-12 h6 border-bottom pb-1 mt-3 mb-1">{{ group.area.identifier }} {{ group.area.title }}</h3>
                            <label v-for="competency in group.competencies" :key="competency.id" class="col-md-6 col-xl-4 form-check border rounded p-2 ps-5" :style="competencyCardStyle(competency)">
                                <input class="form-check-input" type="checkbox" :checked="draftSelectedIds.has(competency.id)" @change="toggle(competency)">
                                <span class="form-check-label small">{{ competencyText(competency) }}</span>
                            </label>
                        </template>
                        </div>
                        <p v-if="loading" class="small text-muted">Kompetenzen werden geladen …</p>
                        <p v-else-if="!activeGroups.length" class="small text-muted">{{ de.noCompetencyOptions }}</p>
                    </div>
                    <div v-if="selectedCompetencies.length" class="border-top pt-2 mt-2">
                        <span v-for="competency in selectedCompetencies" :key="`selected-${competency.id}`" class="badge text-bg-primary me-1 mb-1">
                            {{ competency.external_identifier || competency.number || competency.id }}
                            <button class="btn-close btn-close-white ms-1 align-middle" type="button" :aria-label="de.removeCompetency" @click="toggle(competency)"></button>
                        </span>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button class="btn btn-outline-secondary" type="button" @click="close">{{ de.cancel }}</button>
                        <button class="btn btn-primary" type="button" @click="apply">{{ de.saveChanges }}</button>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
