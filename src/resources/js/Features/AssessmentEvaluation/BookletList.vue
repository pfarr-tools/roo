<script setup>
import de from '../../i18n/de'
import BookletStatusControl from './BookletStatusControl.vue'

const props = defineProps({
    group: { type: Object, required: true },
    assessment: { type: Object, required: true },
    booklets: { type: Array, default: () => [] },
})

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
                <BookletStatusControl :group="group" :assessment="assessment" :booklet="booklet" />
            </div>
        </article>
    </div>
    <p v-else class="text-muted mb-0">{{ de.assessmentEvaluationNoBooklets }}</p>
</template>
