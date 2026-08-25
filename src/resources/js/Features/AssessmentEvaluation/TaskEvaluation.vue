<script setup>
import { computed, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import CheckboxTaskEvaluation from "./CheckboxTaskEvaluation.vue";
import ExpectationEvaluationRow from "./ExpectationEvaluationRow.vue";
import de from "../../i18n/de";

const props = defineProps({
    group: { type: Object, required: true },
    assessment: { type: Object, required: true },
    task: { type: Object, required: true },
    fragments: { type: Array, default: () => [] },
    openKey: { type: Number, required: true },
});

const reviewCases = ref([]);

const maximumPoints = computed(() =>
    Number(
        props.task.max_points ??
            props.task.expectations.reduce(
                (sum, expectation) =>
                    sum +
                    Number(expectation.points ?? 0) *
                        Math.max(1, Number(expectation.repetitions ?? 1)),
                0,
            ),
    ),
);

const occurrences = computed(() => {
    if (["image_labeling", "image_matching"].includes(props.task.task_type)) {
        const points = props.task.content?.points_per_correct_answer ?? 0;

        const options =
            props.task.task_type === "image_labeling"
                ? props.task.label_options ?? []
                : props.task.images ?? [];

        return [
            ...options.map((option) => ({
                expectation_id: option.id,
                option_id: option.id,
                subtask_key: null,
                occurrence: 1,
                text:
                    props.task.task_type === "image_labeling"
                        ? `Korrekt beschriftet: ${option.text}`
                        : `Du hast ${option.answer} dem korrekten Bild zugeordnet.`,
                points,
                thumbnail:
                    props.task.task_type === "image_matching"
                        ? option.image_url
                        : null,
                thumbnail_alt: option.label,
            })),
            ...(props.task.expectations ?? []).flatMap((expectation) =>
                Array.from(
                    { length: Math.max(1, Number(expectation.repetitions ?? 1)) },
                    (_, index) => ({
                        expectation_id: expectation.id,
                        subtask_key: expectation.subtask_key ?? null,
                        occurrence: index + 1,
                        text: expectation.text,
                        points: expectation.points,
                    }),
                ),
            ),
        ];
    }

    return props.task.expectations.flatMap((expectation) =>
        Array.from(
            { length: Math.max(1, Number(expectation.repetitions ?? 1)) },
            (_, index) => ({
                expectation_id: expectation.id,
                subtask_key: expectation.subtask_key ?? null,
                occurrence: index + 1,
                text: expectation.text,
                points: expectation.points,
            }),
        ),
    );
});

function shuffled(fragments) {
    const result = [...fragments];

    for (let index = result.length - 1; index > 0; index -= 1) {
        const replacementIndex = Math.floor(Math.random() * (index + 1));
        [result[index], result[replacementIndex]] = [
            result[replacementIndex],
            result[index],
        ];
    }

    return result;
}

function buildReviewCase(fragment) {
    const existingItems = new Map(
        (fragment.review?.items ?? []).map((item) => [
            `${item.expectation_id}:${item.occurrence}`,
            item,
        ]),
    );

    const existingOptions = new Map(
        (fragment.review?.options ?? []).map((option) => [
            option.option_id,
            option.selected,
        ]),
    );
    const definitions =
        props.task.task_type === "image_matching"
            ? (props.task.images ?? [])
            : props.task.task_type === "image_labeling"
              ? (props.task.label_options ?? [])
            : (props.task.content?.options ?? []);
    const options = definitions.map((option, index) => ({
        id: option.id ?? `option-${index + 1}`,
        text: option.text,
        label: option.label,
        answer: option.answer,
        image_url: option.image_url,
        correct: option.correct,
        selected: existingOptions.get(option.id) ?? false,
    }));

    return {
        fragment,
        options,
        items: occurrences.value.map((occurrence) => {
            const existing = existingItems.get(
                `${occurrence.expectation_id}:${occurrence.occurrence}`,
            );

            return {
                ...occurrence,
                awarded_points:
                    occurrence.option_id !== undefined
                        ? existingOptions.get(occurrence.option_id)
                            ? Number(occurrence.points)
                            : 0
                        : Number(existing?.awarded_points ?? 0),
                note: existing?.note ?? null,
                full_points_selected:
                    existing !== undefined &&
                    Number(existing.awarded_points) ===
                        Number(occurrence.points),
            };
        }),
        extra_points: fragment.review?.extra_points ?? 0,
        extra_note: fragment.review?.extra_note ?? null,
        errors: {},
        processing: false,
        saved: fragment.review !== null,
    };
}

function assignedPoints(reviewCase) {
    const automaticPoints =
        reviewCase.options.filter((option) =>
            props.task.task_type === "checkbox"
                ? props.task.checkbox_scoring_mode === "correct_states"
                    ? option.selected === option.correct
                    : option.selected && option.correct
                : false,
        ).length * Number(props.task.content?.points_per_correct_answer ?? 0);
    const manualPoints = reviewCase.items.reduce(
        (sum, item) => sum + Number(item.awarded_points ?? 0),
        0,
    );

    return (
        automaticPoints + manualPoints + Number(reviewCase.extra_points ?? 0)
    );
}

function formatPoints(points) {
    return new Intl.NumberFormat("de-DE", { maximumFractionDigits: 2 }).format(
        points,
    );
}

function specializedCheckbox(reviewCase) {
    return props.task.task_type === "checkbox";
}

function subtaskHeading(reviewCase, index) {
    if (props.task.task_type !== "subtask_table") {
        return null;
    }

    const item = reviewCase.items[index];
    if (index > 0 && reviewCase.items[index - 1].subtask_key === item.subtask_key) {
        return null;
    }

    return (props.task.content?.subtasks ?? []).find(
        (subtask) => subtask.key === item.subtask_key,
    )?.label ?? null;
}

function updateOptions(reviewCase, options) {
    reviewCase.options = options;
    markDirty(reviewCase);
}

function resetCases() {
    reviewCases.value = shuffled(props.fragments).map(buildReviewCase);
}

function reviewUrl(fragment) {
    return `/unterrichtsgruppen/${props.group.id}/lernstandserhebungen/${props.assessment.id}/auswertung/booklets/${fragment.booklet_id}/tasks/${props.task.id}/review`;
}

function toggleFullPoints(reviewCase, item) {
    item.full_points_selected = !item.full_points_selected;
    item.awarded_points = item.full_points_selected ? item.points : 0;
    syncImageLabelOption(reviewCase, item);
    markDirty(reviewCase);
}

function updateAwardedPoints(reviewCase, item, value) {
    item.awarded_points = value;
    normalizeAwardedPoints(reviewCase, item);
    syncImageLabelOption(reviewCase, item);
}

function syncImageLabelOption(reviewCase, item) {
    if (!["image_labeling", "image_matching"].includes(props.task.task_type)) {
        return;
    }

    const option = reviewCase.options.find(
        (candidate) => candidate.id === item.option_id,
    );
    if (option) {
        option.selected = Number(item.awarded_points) > 0;
    }
}

function updateNote(reviewCase, item, value) {
    item.note = value;
    markDirty(reviewCase);
}

function markDirty(reviewCase) {
    reviewCase.saved = false;
}

function normalizeAwardedPoints(reviewCase, item) {
    const points = Number(item.awarded_points);
    const maximum = Number(item.points);

    if (Number.isFinite(points) && Number.isFinite(maximum)) {
        item.awarded_points = Math.min(Math.max(points, 0), maximum);
    }

    item.full_points_selected = false;
    markDirty(reviewCase);
}

function save(reviewCase) {
    reviewCase.processing = true;
    reviewCase.errors = {};

    router.put(
        reviewUrl(reviewCase.fragment),
        {
            options:
                specializedCheckbox(reviewCase) ||
                ["image_matching", "image_labeling"].includes(props.task.task_type)
                    ? reviewCase.options.map((option) => ({
                          id: option.id,
                          selected: option.selected,
                      }))
                    : undefined,
            items: reviewCase.items
                .filter((item) => item.option_id === undefined)
                .map((item) => ({
                    expectation_id: item.expectation_id,
                    occurrence: item.occurrence,
                    awarded_points: item.awarded_points,
                    note: item.note || null,
                })),
            extra_points: reviewCase.extra_points,
            extra_note: reviewCase.extra_note || null,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => {
                reviewCase.errors = errors;
            },
            onSuccess: () => {
                reviewCase.saved = true;
            },
            onFinish: () => {
                reviewCase.processing = false;
            },
        },
    );
}

watch(() => props.openKey, resetCases, { immediate: true });
</script>

<template>
    <div v-if="reviewCases.length" class="row g-4">
        <article
            v-for="reviewCase in reviewCases"
            :key="reviewCase.fragment.id"
            class="col-12"
        >
            <form class="card h-100" @submit.prevent="save(reviewCase)">
                <img
                    :src="reviewCase.fragment.image_url"
                    class="card-img-top"
                    :alt="de.assessmentEvaluationTaskFragment"
                />
                <div class="card-body">
                    <span
                        class="visually-hidden"
                        data-testid="task-fragment-id"
                        >{{ reviewCase.fragment.id }}</span
                    >
                    <div
                        class="d-flex justify-content-between align-items-start gap-2 mb-3"
                    >
                        <h3 class="h5 mb-0">
                            {{ task.title }} ({{ formatPoints(maximumPoints) }} VP)
                        </h3>
                        <div class="text-end">
                            <div
                                :data-testid="`points-summary-${reviewCase.fragment.id}`"
                                class="h4 mb-1"
                            >
                                {{ formatPoints(assignedPoints(reviewCase)) }} /
                                {{ formatPoints(maximumPoints) }}
                                {{ de.assessmentEvaluationPoints }}
                            </div>
                            <span
                                v-if="reviewCase.saved"
                                class="badge text-bg-success"
                                >{{ de.assessmentEvaluationReviewed }}</span
                            >
                        </div>
                    </div>

                    <CheckboxTaskEvaluation
                        v-if="specializedCheckbox(reviewCase)"
                        :options="reviewCase.options"
                        :processing="reviewCase.processing"
                        @update:selection="updateOptions(reviewCase, $event)"
                    />
                    <div v-if="reviewCase.items.length" class="vstack gap-2">
                        <template v-for="(item, index) in reviewCase.items" :key="`${item.expectation_id}:${item.occurrence}`">
                            <h4 v-if="subtaskHeading(reviewCase, index)" class="h6 mb-2" data-testid="subtask-heading">{{ subtaskHeading(reviewCase, index) }}</h4>
                            <ExpectationEvaluationRow
                                :item="item"
                                :fragment-id="reviewCase.fragment.id"
                                :processing="reviewCase.processing"
                                @toggle-full-points="toggleFullPoints(reviewCase, item)"
                                @update:points="updateAwardedPoints(reviewCase, item, $event)"
                                @update:note="updateNote(reviewCase, item, $event)"
                            />
                        </template>
                    </div>
                    <p v-else class="text-muted">
                        {{ de.assessmentEvaluationNoExpectations }}
                    </p>

                    <div
                        class="border-top mt-4 pt-3"
                        data-testid="extra-points-row"
                    >
                        <div class="row g-2 align-items-start">
                            <div class="col-12 col-lg-4 fw-semibold pt-1">
                                {{ de.assessmentEvaluationExtraPoints }}
                            </div>
                            <div class="col-auto" aria-hidden="true"></div>
                            <div class="col-12 col-sm-3 col-lg-2">
                                <label
                                    class="visually-hidden"
                                    :for="`extra-points-${reviewCase.fragment.id}`"
                                    >{{
                                        de.assessmentEvaluationExtraPoints
                                    }}</label
                                >
                                <input
                                    :id="`extra-points-${reviewCase.fragment.id}`"
                                    :data-testid="`extra-points-${reviewCase.fragment.id}`"
                                    v-model="reviewCase.extra_points"
                                    class="form-control form-control-sm"
                                    type="number"
                                    step="1"
                                    :disabled="reviewCase.processing"
                                    @input="markDirty(reviewCase)"
                                />
                            </div>
                            <div class="col-12 col-lg">
                                <label
                                    class="visually-hidden"
                                    :for="`extra-note-${reviewCase.fragment.id}`"
                                    >{{
                                        de.assessmentEvaluationExtraExplanationOptional
                                    }}</label
                                >
                                <input
                                    :id="`extra-note-${reviewCase.fragment.id}`"
                                    v-model="reviewCase.extra_note"
                                    class="form-control form-control-sm"
                                    type="text"
                                    :placeholder="
                                        de.assessmentEvaluationExtraExplanationOptional
                                    "
                                    :disabled="reviewCase.processing"
                                    @input="markDirty(reviewCase)"
                                />
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="
                            reviewCase.errors.items ||
                            reviewCase.errors.extra_points
                        "
                        class="invalid-feedback d-block mt-3"
                    >
                        {{
                            reviewCase.errors.items ||
                            reviewCase.errors.extra_points
                        }}
                    </div>
                </div>
                <div
                    class="card-footer bg-transparent d-flex justify-content-end"
                >
                    <button
                        class="btn"
                        :class="reviewCase.saved ? 'btn-success' : 'btn-warning'"
                        type="submit"
                        :disabled="reviewCase.processing"
                    >
                        {{ de.saveChanges }}
                    </button>
                </div>
            </form>
        </article>
    </div>
    <p v-else class="text-muted mb-0">
        {{ de.assessmentEvaluationNoTaskFragments }}
    </p>
</template>
