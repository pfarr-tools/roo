<script setup>
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import de from '../../i18n/de'
import { requestConfirmation } from '../../utils/confirmation'

const props = defineProps({
    groupId: { type: [Number, String], required: true },
    assessmentId: { type: [Number, String], required: true },
    title: { type: String, required: true },
    differentiated: { type: Boolean, default: false },
})

const downloadOpen = ref(false)
const downloadLevel = ref('M')
const downloadFormat = ref('odt')
const downloadTemplate = ref('primary-school-lower-secondary')
const downloadUrl = computed(() => {
    const params = new URLSearchParams()
    if (props.differentiated) params.set('level', downloadLevel.value)
    params.set('format', downloadFormat.value)
    params.set('template', downloadTemplate.value)

    return `/unterrichtsgruppen/${props.groupId}/lernstandserhebungen/${props.assessmentId}/download?${params.toString()}`
})

async function remove() {
    if (!await requestConfirmation({ message: de.deleteAssessmentConfirm.replace('{title}', props.title) })) return

    useForm({}).delete(`/unterrichtsgruppen/${props.groupId}/lernstandserhebungen/${props.assessmentId}`)
}

function openDownload() {
    downloadOpen.value = true
}
</script>

<template>
    <div class="d-flex gap-1">
        <a class="btn btn-sm btn-outline-secondary" :href="`/unterrichtsgruppen/${groupId}/lernstandserhebungen/${assessmentId}/bearbeiten?return_tab=assessments`" :title="de.editAssessment" :aria-label="de.editAssessment"><i class="bi bi-pencil" aria-hidden="true"></i></a>
        <button class="btn btn-sm btn-outline-secondary" type="button" :title="de.printAssessment" :aria-label="de.printAssessment" @click="openDownload"><i class="bi bi-printer" aria-hidden="true"></i></button>
        <a class="btn btn-sm btn-outline-primary" :href="`/unterrichtsgruppen/${groupId}/lernstandserhebungen/${assessmentId}/auswertung`" :title="de.assessAssessment" :aria-label="de.assessAssessment"><i class="bi bi-clipboard-check" aria-hidden="true"></i></a>
        <button class="btn btn-sm btn-outline-danger" type="button" :title="de.deleteAssessment" :aria-label="de.deleteAssessment" @click="remove"><i class="bi bi-trash" aria-hidden="true"></i></button>
    </div>
    <div v-if="downloadOpen" class="roo-modal-backdrop" role="presentation" @click.self="downloadOpen = false">
        <section class="roo-modal" role="dialog" aria-modal="true" :aria-label="de.assessmentDownloadTitle">
            <div class="card border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">{{ de.assessmentDownloadTitle }}</h2>
                        <button class="btn-close" type="button" :aria-label="de.close" @click="downloadOpen = false"></button>
                    </div>
                    <div v-if="differentiated" class="mb-3">
                        <label class="form-label" for="assessment-download-level">{{ de.assessmentDownloadLevel }}</label>
                        <select id="assessment-download-level" v-model="downloadLevel" class="form-select">
                            <option value="G">G</option>
                            <option value="M">M</option>
                            <option value="E">E</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="assessment-download-format">{{ de.assessmentDownloadFormat }}</label>
                        <select id="assessment-download-format" v-model="downloadFormat" class="form-select">
                            <option value="odt">ODT</option>
                            <option value="docx">DOCX</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="assessment-download-template">{{ de.assessmentDownloadTemplate }}</label>
                        <select id="assessment-download-template" v-model="downloadTemplate" class="form-select">
                            <option value="primary-school-lower-secondary">{{ de.assessmentTemplatePrimarySchoolLowerSecondary }}</option>
                        </select>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button class="btn btn-outline-secondary" type="button" @click="downloadOpen = false">{{ de.cancel }}</button>
                        <a data-testid="assessment-download" class="btn btn-primary" :href="downloadUrl" @click="downloadOpen = false">{{ de.assessmentDownloadStart }}</a>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
