<script setup>
import de from '../../i18n/de'

const props = defineProps({
    options: { type: Array, default: () => [] },
    processing: { type: Boolean, default: false },
})
const emit = defineEmits(['update:selection'])

function toggle(index) {
    emit('update:selection', props.options.map((item, itemIndex) => itemIndex === index ? { ...item, selected: !item.selected } : { ...item }))
}
</script>

<template>
    <fieldset class="border rounded p-3 mb-4" data-testid="checkbox-task-evaluation">
        <legend class="h6 px-1 mb-2">{{ de.assessmentEvaluationCheckboxOptions }}</legend>
        <div v-for="(option, index) in options" :key="option.id ?? index" class="form-check mb-2">
            <input :id="`checkbox-option-${option.id ?? index}`" class="form-check-input" type="checkbox" :checked="option.selected" :disabled="processing" @change="toggle(index)">
            <label class="form-check-label" :for="`checkbox-option-${option.id ?? index}`"><i :class="option.selected === option.correct ? 'bi-check-lg text-success' : 'bi-x-lg text-danger'" class="bi me-1" aria-hidden="true"></i>{{ option.text }}</label>
        </div>
        <p class="small text-muted mb-0">{{ de.assessmentEvaluationCheckboxHint }}</p>
    </fieldset>
</template>
