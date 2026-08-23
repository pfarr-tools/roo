<script setup>
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import de from '../../i18n/de'
import { createScanClient } from '../AssessmentScan/scanClient'
import { uploadErrorMessage, uploadProgressDetails } from './presentation'

const props = defineProps({
    group: { type: Object, required: true },
    assessment: { type: Object, required: true },
})

const emit = defineEmits(['close'])
const scanForm = useForm({ pdf: null })
const browserScanning = ref(false)
const scanProgress = ref(null)
const scanResult = ref(null)
const scanError = ref(null)
const scanPreviewUrl = ref(null)
const scanPreviewPage = ref(null)
const scanClient = ref(null)
const isScanProcessing = computed(() => scanForm.processing || browserScanning.value)
const progressDetails = computed(() => scanProgress.value ? uploadProgressDetails(scanProgress.value) : null)

function selectScanFile(event) {
    scanForm.pdf = event.target.files?.[0] ?? null
}

function close() {
    if (!isScanProcessing.value) {
        if (scanPreviewUrl.value) URL.revokeObjectURL(scanPreviewUrl.value)
        emit('close')
    }
}

async function submit() {
    if (!scanForm.pdf || isScanProcessing.value) return

    browserScanning.value = true
    scanError.value = null
    const sessionUrl = `/unterrichtsgruppen/${props.group.id}/lernstandserhebungen/${props.assessment.id}/auswertung/session`
    scanClient.value = createScanClient({
        pdf: scanForm.pdf,
        sessionUrl,
        pageUrl: (sessionId) => `${sessionUrl}/${sessionId}/pages`,
        completeUrl: (sessionId) => `${sessionUrl}/${sessionId}/complete`,
        onProgress: (progress) => { scanProgress.value = progress },
        onPagePreview: (url, page) => {
            if (scanPreviewUrl.value) URL.revokeObjectURL(scanPreviewUrl.value)
            scanPreviewUrl.value = url
            scanPreviewPage.value = page
        },
        onComplete: (redirectUrl) => window.location.assign(redirectUrl),
    })

    try {
        scanResult.value = await scanClient.value.start()
    } catch (error) {
        scanError.value = uploadErrorMessage(error)
    } finally {
        browserScanning.value = false
    }
}
</script>

<template>
    <div class="roo-modal-backdrop" role="presentation" @click.self="close">
        <section class="roo-modal card border-0 assessment-scan-modal" role="dialog" aria-modal="true" :aria-label="de.assessmentScanTitle">
            <form class="card-body d-flex flex-column assessment-scan-form" @submit.prevent="submit">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 mb-0">{{ de.assessmentScanTitle }}</h2>
                    <button class="btn-close" type="button" :aria-label="de.close" :disabled="isScanProcessing" @click="close"></button>
                </div>
                <div class="row g-3 flex-grow-1 assessment-scan-layout">
                    <div class="col-lg-5 assessment-scan-controls">
                        <p class="small text-muted">{{ de.assessmentScanUploadHint }}</p>
                        <div v-if="isScanProcessing" class="alert alert-info d-flex align-items-start gap-2" role="status" aria-live="polite">
                            <span class="spinner-border spinner-border-sm mt-1 flex-shrink-0" aria-hidden="true"></span>
                            <span>{{ de.assessmentScanProcessingHint }}</span>
                        </div>
                        <label class="form-label" for="assessment-scan-pdf">{{ de.assessmentScanPdf }}</label>
                        <input id="assessment-scan-pdf" class="form-control" type="file" accept="application/pdf,.pdf" required :disabled="isScanProcessing" @change="selectScanFile">
                        <div v-if="scanForm.errors.pdf" class="invalid-feedback d-block">{{ scanForm.errors.pdf }}</div>
                        <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                            <button class="btn btn-secondary" type="button" :disabled="isScanProcessing" @click="close">{{ de.cancel }}</button>
                            <button v-if="scanError" class="btn btn-outline-primary" type="button" :disabled="isScanProcessing || !scanForm.pdf" @click="submit">{{ de.assessmentScanRetry }}</button>
                            <button class="btn btn-primary" type="submit" :disabled="isScanProcessing || !scanForm.pdf">
                                <span v-if="isScanProcessing" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                {{ isScanProcessing ? de.assessmentScanProcessing : de.assessmentScanSubmit }}
                            </button>
                        </div>
                        <div v-if="scanProgress && progressDetails" class="mt-3" role="status" aria-live="polite">
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span>{{ de.assessmentScanPhase }}: {{ scanProgress.phase }}</span>
                                <span v-if="progressDetails.page">{{ progressDetails.page }}</span>
                            </div>
                            <div class="progress mb-2" role="progressbar" :aria-valuenow="scanProgress.percent ?? 0" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar" :style="{ width: `${scanProgress.percent ?? 0}%` }"></div>
                            </div>
                            <div class="small text-muted">
                                {{ de.assessmentScanMarkers }}: {{ progressDetails.markers }} ·
                                {{ de.assessmentScanDetectedFragments }}: {{ progressDetails.fragments }} ·
                                {{ de.assessmentScanUploadedPages }}: {{ progressDetails.uploadedPages }}
                            </div>
                        </div>
                        <div v-if="scanResult" class="alert alert-success mt-3 mb-0">{{ de.assessmentScanCompleted }}</div>
                        <div v-if="scanError" class="alert alert-danger mt-3 mb-0" role="alert">{{ scanError }}</div>
                    </div>
                    <div class="col-lg-7 d-flex assessment-scan-preview-pane">
                        <figure v-if="scanPreviewUrl" class="border rounded bg-light p-2 mb-0 w-100 d-flex flex-column assessment-scan-preview">
                            <figcaption class="small text-muted mb-2">{{ de.assessmentScanPreview }} {{ scanPreviewPage }}</figcaption>
                            <img :src="scanPreviewUrl" class="d-block mx-auto assessment-scan-preview-image" :alt="`${de.assessmentScanPreview} ${scanPreviewPage}`">
                        </figure>
                    </div>
                </div>
            </form>
        </section>
    </div>
</template>
