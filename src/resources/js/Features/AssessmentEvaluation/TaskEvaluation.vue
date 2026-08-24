<script setup>
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import CheckboxTaskEvaluation from './CheckboxTaskEvaluation.vue'
import de from '../../i18n/de'

const props = defineProps({
    group: { type: Object, required: true },
    assessment: { type: Object, required: true },
    task: { type: Object, required: true },
    fragments: { type: Array, default: () => [] },
    openKey: { type: Number, required: true },
})

const reviewCases = ref([])

const maximumPoints = computed(() => Number(props.task.max_points ?? props.task.expectations.reduce(
    (sum, expectation) => sum + Number(expectation.points ?? 0) * Math.max(1, Number(expectation.repetitions ?? 1)),
    0,
)))

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

    const existingOptions = new Map((fragment.review?.options ?? []).map((option) => [option.option_id, option.selected]))
    const options = (props.task.content?.options ?? []).map((option, index) => ({
        id: option.id ?? `option-${index + 1}`,
        text: option.text,
        correct: option.correct,
        selected: existingOptions.get(option.id) ?? false,
    }))

    return {
        fragment,
        options,
        items: occurrences.value.map((occurrence) => {
            const existing = existingItems.get(`${occurrence.expectation_id}:${occurrence.occurrence}`)

            return {
                ...occurrence,
                awarded_points: existing?.awarded_points ?? 0,
                note: existing?.note ?? null,
                full_points_selected: existing !== undefined && Number(existing.awarded_points) === Number(occurrence.points),
            }
        }),
        extra_points: fragment.review?.extra_points ?? 0,
        extra_note: fragment.review?.extra_note ?? null,
        errors: {},
        processing: false,
        saved: fragment.review !== null,
    }
}

function assignedPoints(reviewCase) {
    const automaticPoints = reviewCase.options
        .filter(option => option.selected && option.correct)
        .length * Number(props.task.content?.points_per_correct_answer ?? 0)
    const manualPoints = reviewCase.items.reduce((sum, item) => sum + Number(item.awarded_points ?? 0), 0)

    return automaticPoints + manualPoints + Number(reviewCase.extra_points ?? 0)
}

function formatPoints(points) {
    return new Intl.NumberFormat('de-DE', { maximumFractionDigits: 2 }).format(points)
}

function specializedCheckbox(reviewCase) {
    return props.task.task_type === 'checkbox'
}

function updateOptions(reviewCase, options) {
    reviewCase.options = options
    markDirty(reviewCase)
}

function resetCases() {
    reviewCases.value = shuffled(props.fragments).map(buildReviewCase)
}

function reviewUrl(fragment) {
    return `/unterrichtsgruppen/${props.group.id}/lernstandserhebungen/${props.assessment.id}/auswertung/booklets/${fragment.booklet_id}/tasks/${props.task.id}/review`
}

function toggleFullPoints(reviewCase, item) {
    item.full_points_selected = !item.full_points_selected
    item.awarded_points = item.full_points_selected ? item.points : 0
    markDirty(reviewCase)
}

function markDirty(reviewCase) {
    reviewCase.saved = false
}

function normalizeAwardedPoints(reviewCase, item) {
    const points = Number(item.awarded_points)
    const maximum = Number(item.points)

    if (Number.isFinite(points) && Number.isFinite(maximum)) {
        item.awarded_points = Math.min(Math.max(points, 0), maximum)
    }

    item.full_points_selected = false
    markDirty(reviewCase)
}

function save(reviewCase) {
    reviewCase.processing = true
    reviewCase.errors = {}

    router.put(reviewUrl(reviewCase.fragment), {
        options: specializedCheckbox(reviewCase) ? reviewCase.options.map(option => ({ id: option.id, selected: option.selected })) : undefined,
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
        <article v-for="reviewCase in reviewCases" :key="reviewCase.fragment.id" class="col-12">
            <form class="card h-100" @submit.prevent="save(reviewCase)">
                <img :src="reviewCase.fragment.image_url" class="card-img-top" :alt="de.assessmentEvaluationTaskFragment">
                <div class="card-body">
                    <span class="visually-hidden" data-testid="task-fragment-id">{{ reviewCase.fragment.id }}</span>
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                        <h3 class="h5 mb-0">{{ task.title }}</h3>
                        <div class="text-end">
                            <div :data-testid="`points-summary-${reviewCase.fragment.id}`" class="h4 mb-1">{{ formatPoints(assignedPoints(reviewCase)) }} / {{ formatPoints(maximumPoints) }} {{ de.assessmentEvaluationPoints }}</div>
                            <span v-if="reviewCase.saved" class="badge text-bg-success">{{ de.assessmentEvaluationReviewed }}</span>
                        </div>
                    </div>

                    <CheckboxTaskEvaluation v-if="specializedCheckbox(reviewCase)" :options="reviewCase.options" :processing="reviewCase.processing" @update:selection="updateOptions(reviewCase, $event)" />

                    <div v-if="reviewCase.items.length" class="vstack gap-2">
                        <div v-for="item in reviewCase.items" :key="`${item.expectation_id}:${item.occurrence}`" class="border rounded p-2" data-testid="expectation-row">
                            <div class="row g-2 align-items-start">
                                <div class="col-12 col-lg-4">
                                    <div class="fw-semibold">{{ item.text }}</div>
                                    <div class="small text-muted">{{ de.assessmentEvaluationOccurrence }} {{ item.occurrence }} · {{ de.assessmentEvaluationMaximumPoints }} {{ item.points }}</div>
                                </div>
                                <div class="col-auto">
                                    <button :data-testid="`full-points-${reviewCase.fragment.id}-${item.expectation_id}-${item.occurrence}`" class="btn btn-sm px-2" :class="item.full_points_selected ? 'btn-outline-success' : 'btn-outline-danger'" type="button" :title="item.full_points_selected ? de.assessmentEvaluationZeroPoints : de.assessmentEvaluationFullPoints" :aria-label="item.full_points_selected ? de.assessmentEvaluationZeroPoints : de.assessmentEvaluationFullPoints" :disabled="reviewCase.processing" @click="toggleFullPoints(reviewCase, item)"><i :class="item.full_points_selected ? 'bi bi-check-lg' : 'bi bi-x-lg'" aria-hidden="true"></i></button>
                                </div>
                                <div class="col-12 col-sm-3 col-lg-2">
                                    <label class="visually-hidden" :for="`points-${reviewCase.fragment.id}-${item.expectation_id}-${item.occurrence}`">{{ de.assessmentEvaluationAwardedPoints }}</label>
                                    <input :id="`points-${reviewCase.fragment.id}-${item.expectation_id}-${item.occurrence}`" :data-testid="`points-${reviewCase.fragment.id}-${item.expectation_id}-${item.occurrence}`" v-model="item.awarded_points" class="form-control form-control-sm" type="number" min="0" :max="item.points" step="0.01" :disabled="reviewCase.processing" @input="normalizeAwardedPoints(reviewCase, item)">
                                </div>
                                <div class="col-12 col-lg">
                                    <label class="visually-hidden" :for="`note-${reviewCase.fragment.id}-${item.expectation_id}-${item.occurrence}`">{{ de.assessmentEvaluationExplanationOptional }}</label>
                                    <input :id="`note-${reviewCase.fragment.id}-${item.expectation_id}-${item.occurrence}`" :data-testid="`note-${reviewCase.fragment.id}-${item.expectation_id}-${item.occurrence}`" v-model="item.note" class="form-control form-control-sm" type="text" :placeholder="de.assessmentEvaluationExplanationOptional" :disabled="reviewCase.processing" @input="markDirty(reviewCase)">
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-muted">{{ de.assessmentEvaluationNoExpectations }}</p>

                    <div class="border-top mt-4 pt-3" data-testid="extra-points-row">
                        <div class="row g-2 align-items-start">
                            <div class="col-12 col-sm-auto fw-semibold pt-1">{{ de.assessmentEvaluationExtraPoints }}</div>
                            <div class="col-12 col-sm-3 col-lg-2">
                                <label class="visually-hidden" :for="`extra-points-${reviewCase.fragment.id}`">{{ de.assessmentEvaluationExtraPoints }}</label>
                                <input :id="`extra-points-${reviewCase.fragment.id}`" :data-testid="`extra-points-${reviewCase.fragment.id}`" v-model="reviewCase.extra_points" class="form-control form-control-sm" type="number" step="0.01" :disabled="reviewCase.processing" @input="markDirty(reviewCase)">
                            </div>
                            <div class="col-12 col-lg">
                                <label class="visually-hidden" :for="`extra-note-${reviewCase.fragment.id}`">{{ de.assessmentEvaluationExtraExplanationOptional }}</label>
                                <input :id="`extra-note-${reviewCase.fragment.id}`" v-model="reviewCase.extra_note" class="form-control form-control-sm" type="text" :placeholder="de.assessmentEvaluationExtraExplanationOptional" :disabled="reviewCase.processing" @input="markDirty(reviewCase)">
                            </div>
                        </div>
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
