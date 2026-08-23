<script setup>
import { useForm } from '@inertiajs/vue3'
import de from '../../i18n/de'
import { bookletActionUrl } from './presentation'

const props = defineProps({
    group: { type: Object, required: true },
    assessment: { type: Object, required: true },
    booklet: { type: Object, required: true },
})

const form = useForm({ status: props.booklet.status })

function updateStatus(status) {
    form.status = status
    form.patch(bookletActionUrl(props.group.id, props.assessment.id, props.booklet.id, 'status'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <div class="card-footer bg-transparent d-flex justify-content-end">
        <button
            v-if="booklet.status === 'open'"
            class="btn btn-sm btn-outline-danger"
            type="button"
            :disabled="form.processing"
            @click="updateStatus('discarded')"
        >{{ de.assessmentEvaluationDiscard }}</button>
        <button
            v-else
            class="btn btn-sm btn-outline-secondary"
            type="button"
            :disabled="form.processing"
            @click="updateStatus('open')"
        >{{ de.assessmentEvaluationRestore }}</button>
    </div>
    <div v-if="form.errors.status" class="card-footer bg-transparent pt-0 text-danger small">{{ form.errors.status }}</div>
</template>
