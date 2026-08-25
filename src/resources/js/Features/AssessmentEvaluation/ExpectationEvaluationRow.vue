<script setup>
import de from '../../i18n/de'

defineProps({
    item: { type: Object, required: true },
    fragmentId: { type: [Number, String], required: true },
    processing: { type: Boolean, default: false },
})

const emit = defineEmits(['toggle-full-points', 'update:points', 'update:note'])

function formatPoints(points) {
    return new Intl.NumberFormat('de-DE', { maximumFractionDigits: 2 }).format(Number(points ?? 0))
}
</script>

<template>
    <div data-testid="expectation-row">
        <div class="row g-2 align-items-start">
            <div class="col-12 col-lg-4">
                <div class="fw-semibold">
                    {{ item.text }} ({{ formatPoints(item.points) }} VP)
                </div>
                <img
                    v-if="item.thumbnail"
                    :src="item.thumbnail"
                    :alt="item.thumbnail_alt || item.text"
                    class="mt-2"
                    data-testid="expectation-thumbnail"
                    style="width: 5rem; height: 5rem; object-fit: contain"
                />
            </div>
            <div class="col-auto">
                <button
                    :data-testid="`full-points-${fragmentId}-${item.expectation_id}-${item.occurrence}`"
                    class="btn btn-sm px-2"
                    :class="item.full_points_selected ? 'btn-outline-success' : 'btn-outline-danger'"
                    type="button"
                    :title="item.full_points_selected ? de.assessmentEvaluationZeroPoints : de.assessmentEvaluationFullPoints"
                    :aria-label="item.full_points_selected ? de.assessmentEvaluationZeroPoints : de.assessmentEvaluationFullPoints"
                    :disabled="processing"
                    @click="emit('toggle-full-points')"
                >
                    <i
                        :class="item.full_points_selected ? 'bi bi-check-lg' : 'bi bi-x-lg'"
                        aria-hidden="true"
                    ></i>
                </button>
            </div>
            <div class="col-12 col-sm-3 col-lg-2">
                <label
                    class="visually-hidden"
                    :for="`points-${fragmentId}-${item.expectation_id}-${item.occurrence}`"
                >{{ de.assessmentEvaluationAwardedPoints }}</label>
                <input
                    :id="`points-${fragmentId}-${item.expectation_id}-${item.occurrence}`"
                    :data-testid="`points-${fragmentId}-${item.expectation_id}-${item.occurrence}`"
                    :value="item.awarded_points"
                    class="form-control form-control-sm"
                    type="number"
                    min="0"
                    :max="item.points"
                    step="1"
                    :disabled="processing"
                    @input="emit('update:points', $event.target.value)"
                />
            </div>
            <div class="col-12 col-lg">
                <label
                    class="visually-hidden"
                    :for="`note-${fragmentId}-${item.expectation_id}-${item.occurrence}`"
                >{{ de.assessmentEvaluationExplanationOptional }}</label>
                <input
                    :id="`note-${fragmentId}-${item.expectation_id}-${item.occurrence}`"
                    :data-testid="`note-${fragmentId}-${item.expectation_id}-${item.occurrence}`"
                    :value="item.note"
                    class="form-control form-control-sm"
                    type="text"
                    :placeholder="de.assessmentEvaluationExplanationOptional"
                    :disabled="processing"
                    @input="emit('update:note', $event.target.value)"
                />
            </div>
        </div>
    </div>
</template>
