<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'

const props = defineProps({ group: Object, assessments: Array, students: Array, competencies: Array })
</script>

<template>
    <AppShell>
        <template #toolbar><a :href="`/unterrichtsgruppen/${group.id}`" class="btn btn-sm btn-light" title="Schließen" aria-label="Schließen"><i class="bi bi-x-lg" aria-hidden="true"></i></a><a class="btn btn-sm btn-primary ms-2" :href="`/unterrichtsgruppen/${group.id}/lernstandserhebungen/neu`"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Neue Lernstandserhebung</a></template>
        <div class="container-full px-3 py-4"><h1 class="h2">Lernstandserhebungen · {{ group.name }}</h1><div v-if="$page.props.flash?.success" class="alert alert-success">{{ $page.props.flash.success }}</div><div v-if="!assessments.length" class="card"><div class="card-body text-muted">Noch keine Lernstandserhebung angelegt.</div></div><div v-for="assessment in assessments" :key="assessment.id" class="card mb-3"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><h2 class="h5">{{ assessment.title }}</h2><p v-if="assessment.assessed_on" class="text-muted">{{ assessment.assessed_on }}</p></div><a class="btn btn-sm btn-outline-primary" :href="`/unterrichtsgruppen/${group.id}/lernstandserhebungen/${assessment.id}/bearbeiten`">Bearbeiten</a></div><ol class="mb-0"><li v-for="task in assessment.tasks" :key="task.id">{{ task.title }}<span v-if="task.level" class="badge text-bg-light ms-2">{{ task.level }}</span><span v-if="task.max_points" class="text-muted ms-2">{{ task.max_points }} Punkte</span></li></ol></div></div></div>
    </AppShell>
</template>
