<script setup>
import { computed, ref } from 'vue'
import AppShell from '../../Components/Ui/AppShell.vue'
import AssessmentScanUploadModal from '../../Features/AssessmentScan/AssessmentScanUploadModal.vue'
import de from '../../i18n/de'

const props = defineProps({
    group: { type: Object, required: true },
    assessment: { type: Object, required: true },
    scan: { type: Object, required: true },
    fragments: { type: Array, default: () => [] },
})

const fragmentGroups = computed(() => {
    const groups = new Map()

    for (const fragment of props.fragments) {
        if (!groups.has(fragment.booklet)) groups.set(fragment.booklet, [])
        groups.get(fragment.booklet).push(fragment)
    }

    return [...groups.entries()].map(([booklet, fragments]) => ({ booklet, fragments }))
})
const scanOpen = ref(false)
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
            <button class="btn btn-sm btn-primary ms-2" type="button" @click="scanOpen = true">{{ de.assessmentScanSubmit }}</button>
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

            <section v-if="fragments.length" class="mt-5">
                <h2 class="h4 mb-3">{{ de.assessmentScanFragments }}</h2>
                <div v-for="group in fragmentGroups" :key="group.booklet" class="mb-4">
                    <h3 class="h5 mb-3">{{ de.assessmentScanBooklet }} {{ group.booklet }}</h3>
                    <div class="row g-3">
                        <div v-for="fragment in group.fragments" :key="fragment.fragment_id" class="col-md-6 col-xl-4">
                            <article class="card h-100">
                                <img :src="fragment.url" class="card-img-top assessment-scan-fragment" :alt="`${de.assessmentScanBooklet} ${fragment.booklet}, ${de.assessmentScanTask} ${fragment.task_id}`">
                                <div class="card-body py-2">
                                    <strong>{{ de.assessmentScanTask }} {{ fragment.task_id }}</strong>
                                    <div class="small text-muted">{{ de.assessmentScanPage }} {{ fragment.page }}</div>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <AssessmentScanUploadModal v-if="scanOpen" :group="group" :assessment="assessment" @close="scanOpen = false" />
    </AppShell>
</template>
