<script setup>
import de from '../../i18n/de'

const props = defineProps({ educationPlan: Object, versions: Array, selectedVersion: Object })

function formatDate(value) {
    if (!value) return de.noVersionDate
    const parts = String(value).slice(0, 10).split('-')
    return parts.length === 3 ? `${parts[2]}.${parts[1]}.${parts[0]}` : value
}

function versionUrl(id) { return `/bildungsplaene/${props.educationPlan.id}?version=${id}` }
</script>

<template>
    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
            <div>
                <a href="/bildungsplaene" class="text-decoration-none">{{ de.educationPlans }}</a>
                <h1 class="h2 mt-2 mb-1">{{ educationPlan.title }}</h1>
                <p class="text-muted mb-0">{{ educationPlan.subject }} · {{ educationPlan.external_identifier }}</p>
            </div>
            <a href="/dashboard" class="btn btn-outline-secondary">{{ de.dashboard }}</a>
        </div>

        <div class="row g-4">
            <aside class="col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <h2 class="h6">{{ de.version }}</h2>
                        <div class="list-group list-group-flush">
                            <a v-for="version in versions" :key="version.id" :href="versionUrl(version.id)" :class="['list-group-item list-group-item-action px-0', version.id === selectedVersion.id ? 'active' : '']">
                                <div class="d-flex justify-content-between gap-2">
                                    <span>{{ version.external_identifier || version.title }}</span>
                                    <span :class="['badge', version.is_complete ? 'text-bg-success' : 'text-bg-warning']">{{ version.is_complete ? de.complete : de.incomplete }}</span>
                                </div>
                                <small :class="version.id === selectedVersion.id ? 'text-white-50' : 'text-muted'">{{ formatDate(version.version_date) }}</small>
                            </a>
                        </div>
                    </div>
                </div>
            </aside>

            <section class="col-lg-9">
                <div v-if="!selectedVersion.is_complete" class="alert alert-warning" role="status">
                    {{ de.incomplete }}: Diese Fassung enthält nur den verifizierten Umfang der Quelle.
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <h2 class="h4 mb-1">{{ selectedVersion.title }}</h2>
                                <p class="text-muted mb-0">{{ de.version }} {{ selectedVersion.external_identifier }} · {{ formatDate(selectedVersion.version_date) }}</p>
                            </div>
                            <a v-if="selectedVersion.source_url" :href="selectedVersion.source_url" target="_blank" rel="noreferrer" class="btn btn-sm btn-outline-secondary">{{ de.source }}</a>
                        </div>
                    </div>
                </div>

                <section v-if="selectedVersion.competence_areas?.length" class="mb-4">
                    <h2 class="h4">{{ de.processCompetencies }}</h2>
                    <article v-for="area in selectedVersion.competence_areas" :key="area.id" class="card mb-3">
                        <div class="card-body">
                            <h3 class="h5">{{ area.external_identifier }} {{ area.title }}</h3>
                            <p v-if="area.introduction" class="text-muted">{{ area.introduction }}</p>
                            <competence-list :competencies="area.competencies" />
                        </div>
                    </article>
                </section>

                <section v-for="stage in selectedVersion.stages" :key="stage.id" class="mb-5">
                    <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-3">
                        <h2 class="h4 mb-0">{{ stage.external_identifier }} {{ stage.label }}</h2>
                        <span class="text-muted">{{ stage.grade_levels?.map(grade => grade.label).join(', ') }}<span v-if="stage.course_label"> · {{ stage.course_label }}</span></span>
                    </div>
                    <article v-for="area in stage.competence_areas" :key="area.id" class="card mb-3">
                        <div class="card-body">
                            <h3 class="h5">{{ area.external_identifier }} {{ area.title }}</h3>
                            <p v-if="area.introduction" class="text-muted">{{ area.introduction }}</p>
                            <competence-list :competencies="area.competencies" />
                        </div>
                    </article>
                </section>
            </section>
        </div>
    </main>
</template>

<script>
export default {
    components: {
        CompetenceList: {
            props: { competencies: Array },
            template: `<ol class="list-group list-group-numbered list-group-flush"><li v-for="competency in competencies" :key="competency.id" class="list-group-item px-0"><div class="d-flex gap-2"><span class="text-muted">{{ competency.external_identifier }}</span><div class="flex-grow-1"><p v-if="competency.text" class="mb-2">{{ competency.text }}</p><div v-for="variant in competency.variants" :key="variant.id" class="small mb-1"><span class="badge text-bg-light me-2">{{ variant.level?.external_identifier ?? 'Standard' }}</span>{{ variant.text }}</div><details v-if="competency.relations?.length" class="small text-muted mt-2"><summary>Quellverweise ({{ competency.relations.length }})</summary><div v-for="relation in competency.relations" :key="relation.id">{{ relation.raw_reference }}</div></details></div></div></li></ol>`,
        },
    },
}
</script>
