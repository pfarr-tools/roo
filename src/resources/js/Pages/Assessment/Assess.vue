<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import de from '../../i18n/de'

defineProps({
    group: { type: Object, required: true },
    assessment: { type: Object, required: true },
    scan: { type: Object, required: true },
})
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
        </template>
        <div class="container-full px-3 py-4">
            <h1 class="h2 mb-1">{{ de.assessmentScanTitle }}</h1>
            <p class="text-muted mb-4">{{ assessment.title }}</p>

            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-lg-3">
                    <div class="card card-body h-100">
                        <div class="small text-muted">{{ de.assessmentScanBooklets }}</div>
                        <div class="fs-3">{{ scan.booklets?.length ?? 0 }}</div>
                    </div>
                </div>
            </div>

            <div v-if="scan.warnings?.length" class="alert alert-warning" role="alert">
                <h2 class="h6">{{ de.assessmentScanWarnings }}</h2>
                <ul class="mb-0">
                    <li v-for="warning in scan.warnings" :key="warning">{{ warning }}</li>
                </ul>
            </div>

            <section v-for="booklet in scan.booklets" :key="booklet.number" class="card mb-3">
                <div class="card-header">
                    <strong>{{ de.assessmentScanBooklet }} {{ booklet.number }}</strong>
                    <span class="text-muted ms-2">{{ de.assessmentScanStartPage }} {{ booklet.start_page }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>{{ de.assessmentScanMarker }}</th>
                                <th>{{ de.assessmentScanTask }}</th>
                                <th>{{ de.assessmentScanPage }}</th>
                                <th>{{ de.assessmentScanPosition }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(marker, index) in booklet.markers" :key="`${booklet.number}-${index}`">
                                <td>{{ marker.kind }}</td>
                                <td>{{ marker.task_id ?? '–' }}</td>
                                <td>{{ marker.page }}</td>
                                <td>{{ marker.y_cm }} cm</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <p v-if="!scan.booklets?.length" class="text-muted">{{ de.assessmentScanNoBooklets }}</p>
        </div>
    </AppShell>
</template>
