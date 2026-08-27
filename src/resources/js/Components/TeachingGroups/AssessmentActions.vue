<script setup>
import { useForm } from '@inertiajs/vue3'
import de from '../../i18n/de'
import { requestConfirmation } from '../../utils/confirmation'

const props = defineProps({
    groupId: { type: [Number, String], required: true },
    assessmentId: { type: [Number, String], required: true },
    title: { type: String, required: true },
})

async function remove() {
    if (!await requestConfirmation({ message: de.deleteAssessmentConfirm.replace('{title}', props.title) })) return

    useForm({}).delete(`/unterrichtsgruppen/${props.groupId}/lernstandserhebungen/${props.assessmentId}`)
}
</script>

<template>
    <div class="d-flex gap-1">
        <a class="btn btn-sm btn-outline-secondary" :href="`/unterrichtsgruppen/${groupId}/lernstandserhebungen/${assessmentId}/bearbeiten?return_tab=assessments`" :title="de.editAssessment" :aria-label="de.editAssessment"><i class="bi bi-pencil" aria-hidden="true"></i></a>
        <a class="btn btn-sm btn-outline-secondary" :href="`/unterrichtsgruppen/${groupId}/lernstandserhebungen/${assessmentId}/download`" :title="de.printAssessment" :aria-label="de.printAssessment"><i class="bi bi-printer" aria-hidden="true"></i></a>
        <a class="btn btn-sm btn-outline-primary" :href="`/unterrichtsgruppen/${groupId}/lernstandserhebungen/${assessmentId}/auswertung`" :title="de.assessAssessment" :aria-label="de.assessAssessment"><i class="bi bi-clipboard-check" aria-hidden="true"></i></a>
        <button class="btn btn-sm btn-outline-danger" type="button" :title="de.deleteAssessment" :aria-label="de.deleteAssessment" @click="remove"><i class="bi bi-trash" aria-hidden="true"></i></button>
    </div>
</template>
