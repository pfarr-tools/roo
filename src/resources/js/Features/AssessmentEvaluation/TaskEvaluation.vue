<script setup>
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import de from '../../i18n/de'

const props = defineProps({
    group: { type: Object, required: true },
    assessment: { type: Object, required: true },
    task: { type: Object, required: true },
    fragments: { type: Array, default: () => [] },
    openKey: { type: Number, required: true },
})

const reviewCases = ref([])

const occurrences = computed(() => props.task.expectations.flatMap((expectation) => Array.from(
    { length: Math.max(1, Number(expectation.repetitions ?? 1)) },
    (_, index) => ({
        expectation_id: expectation.id,
        occurrence: index + 1,
        text: expectation.text,
        points: expectation.points,
    }),
)))

function shuffled(fragments) {
    const result = [...fragments]

    for (let index = result.length - 1; index > 0; index -= 1) {
        const replacementIndex = Math.floor(Math.random() * (index + 1))
        ;[result[index], result[replacementIndex]] = [result[replacementIndex], result[index]]
    }

    return result
}

function buildReviewCase(fragment) {
    const existingItems = new Map((fragment.review?.items ?? []).map((item) => [
        `${item.expectation_id}:${item.occurrence}`,
        item,
    ]))

    return {
        fragment,
        items: occurrences.value.map((occurrence) => {
            const existing = existingItems.get(`${occurrence.expectation_id}:${occurrence.occurrence}`)

            return {
                ...occurrence,
                awarded_points: existing?.awarded_points ?? 0,
                note: existing?.note ?? null,
            }
        }),
        extra_points: fragment.review?.extra_points ?? 0,
        extra_note: fragment.review?.extra_note ?? null,
        errors: {},
        processing: false,
        saved: fragment.review !== null,
    }
}

function resetCases() {
    reviewCases.value = shuffled(props.fragments).map(buildReviewCase)
}

function reviewUrl(fragment) {
    return `/unterrichtsgruppen/${props.group.id}/lernstandserhebungen/${props.assessment.id}/auswertung/booklets/${fragment.booklet_id}/tasks/${props.task.id}/review`
}

function awardFullPoints(reviewCase, item) {
    item.awarded_points = item.points
    reviewCase.saved = false
}

function save(reviewCase) {
    reviewCase.processing = true
    reviewCase.errors = {}

    router.put(reviewUrl(reviewCase.fragment), {
        items: reviewCase.items.map((item) => ({
            expectation_id: item.expectation_id,
            occurrence: item.occurrence,
            awarded_points: item.awarded_points,
            note: item.note || null,
        })),
        extra_points: reviewCase.extra_points,
        extra_note: reviewCase.extra_note || null,
    }, {
        preserveScroll: true,
        preserveState: true,
        onError: (errors) => {
            reviewCase.errors = errors
        },
        onSuccess: () => {
            reviewCase.saved = true
        },
        onFinish: () => {
            reviewCase.processing = false
        },
    })
}

watch(() => props.openKey, resetCases, { immediate: true })
</script>

<template>
    <div v-if="reviewCases.length" class="row g-4">
        <article v-for="reviewCase in reviewCases" :key="reviewCase.fragment.id" class="col-12 col-xxl-6">
            <form class="card h-100" @submit.prevent="save(reviewCase)">
                <img :src="reviewCase.fragment.image_url" class="card-img-top" :alt="de.assessmentEvaluationTaskFragment">
                <div class="card-body">
                    <span class="visually-hidden" data-testid="task-fragment-id">{{ reviewCase.fragment.id }}</span>
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                        <h3 class="h5 mb-0">{{ task.title }}</h3>
                        <span v-if="reviewCase.saved" class="badge text-bg-success">{{ de.assessmentEvaluationReviewed }}</span>
                    </div>

                    <div v-if="reviewCase.items.length" class="vstack gap-3">
                        <div v-for="item in reviewCase.items" :key="`${item.expectation_id}:${item.occurrence}`" class="border rounded p-3" data-testid="expectation-row">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                                <div>
                                    <div class="fw-semibold">{{ item.text }}</div>
                                    <div class="small text-muted">{{ de.assessmentEvaluationOccurrence }} {{ item.occurrence }} · {{ de.assessmentEvaluationMaximumPoints }} {{ item.points }}</div>
                                </div>
                                <button :data-testid="`full-points-${reviewCase.fragment.id}-${item.expectation_id}-${item.occurrence}`" class="btn btn-sm btn-outline-primary" type="button" :disabled="reviewCase.processing" @click="awardFullPoints(reviewCase, item)">{{ de.assessmentEvaluationFullPoints }}</button>
                            </div>
                            <label class="form-label" :for="`points-${reviewCase.fragment.id}-${item.expectation_id}-${item.occurrence}`">{{ de.assessmentEvaluationAwardedPoints }}</label>
                            <input :id="`points-${reviewCase.fragment.id}-${item.expectation_id}-${item.occurrence}`" :data-testid="`points-${reviewCase.fragment.id}-${item.expectation_id}-${item.occurrence}`" v-model="item.awarded_points" class="form-control" type="number" step="0.01" :disabled="reviewCase.processing">
                            <label class="form-label mt-2" :for="`note-${reviewCase.fragment.id}-${item.expectation_id}-${item.occurrence}`">{{ de.assessmentEvaluationExplanationOptional }}</label>
                            <textarea :id="`note-${reviewCase.fragment.id}-${item.expectation_id}-${item.occurrence}`" :data-testid="`note-${reviewCase.fragment.id}-${item.expectation_id}-${item.occurrence}`" v-model="item.note" class="form-control" rows="2" :disabled="reviewCase.processing"></textarea>
                        </div>
                    </div>
                    <p v-else class="text-muted">{{ de.assessmentEvaluationNoExpectations }}</p>

                    <div class="border-top mt-4 pt-3">
                        <label class="form-label" :for="`extra-points-${reviewCase.fragment.id}`">{{ de.assessmentEvaluationExtraPoints }}</label>
                        <input :id="`extra-points-${reviewCase.fragment.id}`" :data-testid="`extra-points-${reviewCase.fragment.id}`" v-model="reviewCase.extra_points" class="form-control" type="number" step="0.01" :disabled="reviewCase.processing">
                        <label class="form-label mt-2" :for="`extra-note-${reviewCase.fragment.id}`">{{ de.assessmentEvaluationExtraExplanationOptional }}</label>
                        <textarea :id="`extra-note-${reviewCase.fragment.id}`" v-model="reviewCase.extra_note" class="form-control" rows="2" :disabled="reviewCase.processing"></textarea>
                    </div>

                    <div v-if="reviewCase.errors.items || reviewCase.errors.extra_points" class="invalid-feedback d-block mt-3">{{ reviewCase.errors.items || reviewCase.errors.extra_points }}</div>
                </div>
                <div class="card-footer bg-transparent d-flex justify-content-end">
                    <button class="btn btn-primary" type="submit" :disabled="reviewCase.processing">{{ de.saveChanges }}</button>
                </div>
            </form>
        </article>
    </div>
    <p v-else class="text-muted mb-0">{{ de.assessmentEvaluationNoTaskFragments }}</p>
</template>
