<script setup>
import de from '../../i18n/de'
import { reactive, ref } from 'vue'

const props = defineProps({ modelValue: Array, curricula: Array })
const emit = defineEmits(['update:modelValue'])
const showAddModal = ref(false)
const draft = reactive({ curriculum_id: '', valid_from: '', valid_until: '', school_type: '', grades: [], notes: '' })
function assignments() { return props.modelValue ?? [] }
function update(index, field, value) { emit('update:modelValue', assignments().map((item, itemIndex) => itemIndex === index ? { ...item, [field]: value } : item)) }
function add() {
    emit('update:modelValue', [...assignments(), { ...draft }])
    Object.assign(draft, { curriculum_id: '', valid_from: '', valid_until: '', school_type: '', grades: [], notes: '' })
    showAddModal.value = false
}
function remove(index) { emit('update:modelValue', assignments().filter((_, itemIndex) => itemIndex !== index)) }
function curriculumLabel(curriculum) { return [curriculum.title, curriculum.school_type].filter(Boolean).join(' · ') }
</script>

<template>
    <fieldset class="mb-4">
        <div class="d-flex justify-content-between align-items-center"><legend class="h5 mb-0">{{ de.curriculumAssignments }}</legend><button class="btn btn-sm btn-outline-primary" type="button" @click="showAddModal = true"><i class="bi bi-plus-lg" aria-hidden="true"></i><span class="visually-hidden">{{ de.addCurriculumAssignment }}</span></button></div>
        <p class="text-muted small mt-2">{{ de.curriculumAssignmentsIntro }}</p>
        <div v-if="!assignments().length" class="text-muted small">{{ de.noCurriculumAssignments }}</div>
        <div v-for="(assignment, index) in assignments()" :key="index" class="row g-2 mb-2 align-items-end">
            <div class="col-md-4"><label class="form-label small">{{ de.curriculum }}</label><select :value="assignment.curriculum_id" class="form-select form-select-sm" @change="update(index, 'curriculum_id', Number($event.target.value) || '')"><option value="">{{ de.choose }}</option><option v-for="curriculum in curricula" :key="curriculum.id" :value="curriculum.id">{{ curriculumLabel(curriculum) }}</option></select></div>
            <div class="col-md-2"><label class="form-label small">{{ de.from }}</label><input :value="assignment.valid_from" type="date" class="form-control form-control-sm" @input="update(index, 'valid_from', $event.target.value)"></div>
            <div class="col-md-2"><label class="form-label small">{{ de.to }}</label><input :value="assignment.valid_until" type="date" class="form-control form-control-sm" @input="update(index, 'valid_until', $event.target.value)"></div>
            <div class="col-md-3"><label class="form-label small">{{ de.notes }}</label><textarea :value="assignment.notes" class="form-control form-control-sm" rows="1" @input="update(index, 'notes', $event.target.value)"></textarea></div>
            <div class="col-md-1"><button class="btn btn-sm btn-outline-danger" type="button" :title="de.removeCurriculumAssignment" :aria-label="de.removeCurriculumAssignment" @click="remove(index)"><i class="bi bi-trash" aria-hidden="true"></i></button></div>
        </div>
    </fieldset>
    <div v-if="showAddModal" class="roo-modal-backdrop" role="presentation" @click.self="showAddModal = false"><section class="roo-modal" role="dialog" aria-modal="true" :aria-label="de.addCurriculumAssignment"><div class="card border-0"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">{{ de.addCurriculumAssignment }}</h2><button class="btn-close" type="button" :aria-label="de.close" @click="showAddModal = false"></button></div><div class="row g-3"><div class="col-12"><label class="form-label">{{ de.curriculum }}</label><select v-model="draft.curriculum_id" class="form-select" required><option value="">{{ de.choose }}</option><option v-for="curriculum in curricula" :key="curriculum.id" :value="curriculum.id">{{ curriculumLabel(curriculum) }}</option></select></div><div class="col-md-6"><label class="form-label">{{ de.from }}</label><input v-model="draft.valid_from" type="date" class="form-control"></div><div class="col-md-6"><label class="form-label">{{ de.to }}</label><input v-model="draft.valid_until" type="date" class="form-control"></div><div class="col-12"><label class="form-label">{{ de.notes }}</label><textarea v-model="draft.notes" class="form-control" rows="4"></textarea></div></div><div class="d-flex justify-content-end gap-2 mt-4"><button class="btn btn-outline-secondary" type="button" @click="showAddModal = false">{{ de.cancel }}</button><button class="btn btn-primary" type="button" :disabled="!draft.curriculum_id" @click="add">{{ de.add }}</button></div></div></div></section></div>
</template>
