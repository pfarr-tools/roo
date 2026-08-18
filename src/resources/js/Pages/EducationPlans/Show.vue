<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import CompetenceList from '../../Components/EducationPlans/CompetenceList.vue'
import de from '../../i18n/de'

const props = defineProps({
    educationPlan: Object,
    versions: Array,
    selectedVersion: Object,
    comparisonVersion: Object,
    comparisonRows: Array,
    importRuns: Array,
})
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''

function formatDate(value) {
    if (!value) return de.noVersionDate
    const parts = String(value).slice(0, 10).split('-')
    return parts.length === 3 ? `${parts[2]}.${parts[1]}.${parts[0]}` : value
}

function versionUrl(id) { return `/bildungsplaene/${props.educationPlan.id}?version=${id}` }
function comparisonUrl(id) { return `/bildungsplaene/${props.educationPlan.id}?version=${props.selectedVersion.id}&compare=${id}` }
function statusClass(status) { return { added: 'text-bg-success', removed: 'text-bg-danger', changed: 'text-bg-warning', unchanged: 'text-bg-secondary' }[status] }
function goToComparison(event) { window.location.href = event.target.value ? comparisonUrl(event.target.value) : versionUrl(props.selectedVersion.id) }
</script>

<template>
    <AppShell>
    <template #toolbar><a href="/bildungsplaene" class="btn btn-sm btn-light" :title="de.close" :aria-label="de.close"><i class="bi bi-x-lg" aria-hidden="true"></i></a></template>
    <div class="container-full px-3 py-4">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h1 class="h2 mb-4">{{ educationPlan.title }}</h1>
            </div>
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
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                            <div>
                                <h2 class="h4 mb-1">{{ selectedVersion.title }}</h2>
                                <p class="text-muted mb-0">{{ de.version }} {{ selectedVersion.external_identifier }} · {{ formatDate(selectedVersion.version_date) }}</p>
                            </div>
                            <a v-if="selectedVersion.source_url" :href="selectedVersion.source_url" target="_blank" rel="noreferrer" class="btn btn-sm btn-outline-secondary">{{ de.source }}</a>
                        </div>
                        <div v-if="versions.length > 1" class="row align-items-center mt-3 g-2">
                            <label for="compare-version" class="col-sm-auto col-form-label">{{ de.compareWith }}</label>
                            <div class="col-sm-6">
                                <select id="compare-version" class="form-select form-select-sm" :value="comparisonVersion?.id ?? ''" @change="goToComparison">
                                    <option value="">{{ de.noComparison }}</option>
                                    <option v-for="version in versions" :key="version.id" :value="version.id" :disabled="version.id === selectedVersion.id">{{ version.external_identifier || version.title }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <section v-if="comparisonVersion" class="card mb-4">
                    <div class="card-body">
                        <h2 class="h5">{{ de.comparison }}: {{ selectedVersion.external_identifier }} ↔ {{ comparisonVersion.external_identifier }}</h2>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead><tr><th>Kennung</th><th>Bereich</th><th>{{ selectedVersion.external_identifier }}</th><th>{{ comparisonVersion.external_identifier }}</th><th>Status</th></tr></thead>
                                <tbody>
                                    <tr v-for="row in comparisonRows" :key="row.external_identifier">
                                        <td>{{ row.external_identifier }}</td><td>{{ row.title }}</td><td class="small text-break">{{ row.current }}</td><td class="small text-break">{{ row.other }}</td>
                                        <td><span :class="['badge', statusClass(row.status)]">{{ de.comparisonStatus[row.status] }}</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section v-if="selectedVersion.competence_areas?.length" class="mb-4">
                    <h2 class="h4">{{ de.processCompetencies }}</h2>
                    <article v-for="area in selectedVersion.competence_areas" :key="area.id" class="card mb-3">
                        <div class="card-body">
                            <h3 class="h5">{{ area.external_identifier }} {{ area.title }}</h3>
                            <p v-if="area.introduction" class="text-muted">{{ area.introduction }}</p>
                            <CompetenceList :competencies="area.competencies" :plan-id="educationPlan.id" :csrf-token="csrfToken" :labels="de" />
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
                            <CompetenceList :competencies="area.competencies" :plan-id="educationPlan.id" :csrf-token="csrfToken" :labels="de" />
                        </div>
                    </article>
                </section>

                <section v-if="importRuns.length" class="card mb-4">
                    <div class="card-body">
                        <h2 class="h5">{{ de.importHistory }}</h2>
                        <div class="table-responsive"><table class="table table-sm"><thead><tr><th>{{ de.version }}</th><th>{{ de.importSource }}</th><th>{{ de.importStatus }}</th><th>{{ de.date }}</th></tr></thead><tbody><tr v-for="run in importRuns" :key="run.id"><td>{{ run.version?.external_identifier }}</td><td class="text-break">{{ run.source_path }}</td><td>{{ run.status }}</td><td>{{ formatDate(run.finished_at) }}</td></tr></tbody></table></div>
                    </div>
                </section>
            </section>
        </div>
    </div>
    </AppShell>
</template>
