<script setup>
import de from '../../i18n/de'

const props = defineProps({
    options: { type: Array, default: () => [] },
    processing: { type: Boolean, default: false },
})
const emit = defineEmits(['update:selection'])

function toggle(option) {
    emit('update:selection', props.options.map(item => item.id === option.id ? { ...item, selected: !item.selected } : { ...item }))
}
</script>

<template>
    <fieldset class="border rounded p-3 mb-4" data-testid="checkbox-task-evaluation">
        <legend class="h6 px-1 mb-2">{{ de.assessmentEvaluationCheckboxOptions }}</legend>
        <div v-for="option in options" :key="option.id" class="form-check mb-2">
            <input :id="`checkbox-option-${option.id}`" class="form-check-input" type="checkbox" :checked="option.selected" :disabled="processing" @change="toggle(option)">
            <label class="form-check-label" :for="`checkbox-option-${option.id}`">{{ option.text }}</label>
        </div>
        <p class="small text-muted mb-0">{{ de.assessmentEvaluationCheckboxHint }}</p>
    </fieldset>
</template>
