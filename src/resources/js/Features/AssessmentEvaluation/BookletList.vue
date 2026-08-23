<script setup>
import { useForm } from '@inertiajs/vue3'
import de from '../../i18n/de'
import { bookletActionUrl } from './presentation'

const props = defineProps({
    group: { type: Object, required: true },
    assessment: { type: Object, required: true },
    booklets: { type: Array, default: () => [] },
})

const statusForm = useForm({ status: 'open' })

function updateStatus(booklet, status) {
    statusForm.status = status
    statusForm.patch(bookletActionUrl(props.group.id, props.assessment.id, booklet.id, 'status'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <div v-if="booklets.length" class="row g-3">
        <article v-for="booklet in booklets" :key="booklet.id" class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <h3 class="h5 mb-1">{{ de.assessmentEvaluationBooklet }} {{ booklet.number }}</h3>
                        <span class="badge" :class="booklet.status === 'discarded' ? 'text-bg-secondary' : 'text-bg-primary'">
                            {{ booklet.status === 'discarded' ? de.assessmentEvaluationDiscarded : de.assessmentEvaluationOpen }}
                        </span>
                    </div>
                    <p class="small text-muted mb-0">
                        {{ booklet.fragment_count }} {{ de.assessmentEvaluationFragments }} ·
                        {{ booklet.reviewed_fragment_count }} {{ de.assessmentEvaluationReviewed }}
                    </p>
                </div>
                <div class="card-footer bg-transparent d-flex justify-content-end">
                    <button
                        v-if="booklet.status === 'open'"
                        class="btn btn-sm btn-outline-danger"
                        type="button"
                        :disabled="statusForm.processing"
                        @click="updateStatus(booklet, 'discarded')"
                    >{{ de.assessmentEvaluationDiscard }}</button>
                    <button
                        v-else
                        class="btn btn-sm btn-outline-secondary"
                        type="button"
                        :disabled="statusForm.processing"
                        @click="updateStatus(booklet, 'open')"
                    >{{ de.assessmentEvaluationRestore }}</button>
                </div>
                <div v-if="statusForm.errors.status" class="card-footer bg-transparent pt-0 text-danger small">{{ statusForm.errors.status }}</div>
            </div>
        </article>
    </div>
    <p v-else class="text-muted mb-0">{{ de.assessmentEvaluationNoBooklets }}</p>
</template>
