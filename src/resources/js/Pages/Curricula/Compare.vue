<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import de from '../../i18n/de'

const props = defineProps({ curricula: Array, left: Object, right: Object, selected: Object })
function compareUrl(side, value) {
    const params = new URLSearchParams()
    const left = side === 'left' ? value : props.selected.left
    const right = side === 'right' ? value : props.selected.right
    if (left) params.set('left', left)
    if (right) params.set('right', right)
    return `/curricula/vergleichen?${params.toString()}`
}
function changeSide(side, value) { window.location.href = compareUrl(side, value) }
function topicLabel(topic) { return [topic.number ? `${topic.number}.` : null, topic.title].filter(Boolean).join(' ') }
</script>

<template>
    <AppShell>
        <div class="container-full px-3 py-4">
            <h1 class="h2 mb-4">{{ de.compareCurricula }}</h1>
            <div class="row g-3 mb-4"><div class="col-md-6"><label class="form-label" for="compare-left">{{ de.firstCurriculum }}</label><select id="compare-left" class="form-select" :value="selected.left ?? ''" @change="changeSide('left', $event.target.value)"><option value="">{{ de.choose }}</option><option v-for="curriculum in curricula" :key="curriculum.id" :value="curriculum.id">{{ curriculum.title }}</option></select></div><div class="col-md-6"><label class="form-label" for="compare-right">{{ de.secondCurriculum }}</label><select id="compare-right" class="form-select" :value="selected.right ?? ''" @change="changeSide('right', $event.target.value)"><option value="">{{ de.choose }}</option><option v-for="curriculum in curricula" :key="curriculum.id" :value="curriculum.id">{{ curriculum.title }}</option></select></div></div>
            <div v-if="!left || !right" class="alert alert-info">{{ de.chooseTwoCurricula }}</div>
            <div v-else class="row g-3"><section v-for="curriculum in [left, right]" :key="curriculum.id" class="col-lg-6"><div class="card h-100"><div class="card-header"><h2 class="h5 mb-0">{{ curriculum.title }}</h2><small class="text-muted">{{ [curriculum.school_type, curriculum.grades?.join(', ')].filter(Boolean).join(' · ') }}</small></div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>{{ de.unit }}</th><th>{{ de.assignYear }}</th><th>{{ de.hours }}</th><th>{{ de.competencies }}</th></tr></thead><tbody><tr v-for="topic in curriculum.topics" :key="`${curriculum.id}-${topic.number}-${topic.title}`"><td>{{ topicLabel(topic) }}</td><td>{{ topic.year ? `${de.classLabel} ${topic.year}` : de.unassigned }}</td><td>{{ topic.hours ?? '–' }}</td><td>{{ topic.competencies_count }}</td></tr><tr v-if="!curriculum.topics.length"><td colspan="4" class="text-muted">{{ de.noUnits }}</td></tr></tbody></table></div></div></section></div>
        </div>
    </AppShell>
</template>
