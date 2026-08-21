<script setup>
import de from '../../i18n/de'
import { computed, ref, watch } from 'vue'

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    competencies: { type: Array, default: () => [] },
    selectedIds: { type: Array, default: () => [] },
    competencyText: { type: Function, required: true },
    lessons: { type: Array, default: () => [] },
    currentLessonId: { type: [String, Number], default: null },
})
const emit = defineEmits(['update:modelValue', 'apply'])

const activeTab = ref('process')
const search = ref('')
const debouncedSearch = ref('')
const draftSelectedIds = ref(new Set())
let searchTimer

watch(() => props.modelValue, open => {
    if (!open) return
    activeTab.value = 'process'
    search.value = ''
    debouncedSearch.value = ''
    draftSelectedIds.value = new Set(props.selectedIds)
})

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
const processGroups = computed(() => grouped(props.competencies.filter(competency => competencyKind(competency) === 'process')))
const contentGroups = computed(() => grouped(props.competencies.filter(competency => competencyKind(competency) !== 'process')))
const activeGroups = computed(() => activeTab.value === 'process' ? processGroups.value : contentGroups.value)

const competencyHours = competency => props.lessons.reduce((total, lesson) => {
    const represented = lesson.id === props.currentLessonId
        ? draftSelectedIds.value.has(competency.id)
        : (lesson.competencies ?? []).some(item => item.curriculum_topic_competency_id === competency.id || item.education_plan_competency_id === competency.id)
    return total + (represented ? Number(lesson.duration ?? 0) : 0)
}, 0)
const competencyCardStyle = competency => {
    const hours = competencyHours(competency)
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
    emit('apply', [...draftSelectedIds.value])
    emit('update:modelValue', false)
}
</script>

<template>
    <div v-if="modelValue" class="roo-modal-backdrop" role="presentation" @click.self="close">
        <section class="roo-modal roo-modal-wide" role="dialog" aria-modal="true" :aria-label="de.addCompetency">
            <div class="card border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">{{ de.addCompetency }}</h2>
                        <button class="btn-close" type="button" :aria-label="de.close" @click="close"></button>
                    </div>
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item"><button class="nav-link" :class="{ active: activeTab === 'process' }" type="button" @click="activeTab = 'process'">{{ de.editProcessCompetencies }}</button></li>
                        <li class="nav-item"><button class="nav-link" :class="{ active: activeTab === 'content' }" type="button" @click="activeTab = 'content'">{{ de.editContentCompetencies }}</button></li>
                    </ul>
                    <input v-model="search" class="form-control mb-3" :placeholder="de.searchCompetencies" type="search">
                    <div class="competency-picker-list">
                        <template v-for="group in activeGroups" :key="group.key">
                            <h3 v-if="group.area" class="h6 border-bottom pb-1 mt-3 mb-2">{{ group.area.identifier }} {{ group.area.title }}</h3>
                            <label v-for="competency in group.competencies" :key="competency.id" class="form-check border rounded p-2 ps-5 mb-2" :style="competencyCardStyle(competency)">
                                <input class="form-check-input" type="checkbox" :checked="draftSelectedIds.has(competency.id)" @change="toggle(competency)">
                                <span class="form-check-label small">{{ competencyText(competency) }}</span>
                            </label>
                        </template>
                        <p v-if="!activeGroups.length" class="small text-muted">{{ de.noCompetencyOptions }}</p>
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
