<script setup>
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import de from '../../i18n/de'
import BookletStatusControl from './BookletStatusControl.vue'
import { bookletActionUrl, bookletStudentOptions } from './presentation'

const props = defineProps({
    group: { type: Object, required: true },
    assessment: { type: Object, required: true },
    booklet: { type: Object, required: true },
    students: { type: Array, default: () => [] },
    booklets: { type: Array, default: () => [] },
})

const form = useForm({ student_id: props.booklet.student_id ?? '' })
const selectedStudentId = ref(props.booklet.student_id ?? '')
const studentOptions = computed(() => bookletStudentOptions(props.booklet, props.students, props.booklets))

watch(() => props.booklet.student_id, (studentId) => {
    selectedStudentId.value = studentId ?? ''
    form.student_id = studentId ?? ''
})

function save() {
    form.student_id = selectedStudentId.value || null
    form.put(bookletActionUrl(props.group.id, props.assessment.id, props.booklet.id, 'assignment'), {
        preserveScroll: true,
    })
}

function unassign() {
    selectedStudentId.value = ''
    form.student_id = null
    form.put(bookletActionUrl(props.group.id, props.assessment.id, props.booklet.id, 'assignment'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <article class="card h-100">
        <img
            v-if="booklet.name_fragment_url"
            :src="booklet.name_fragment_url"
            class="card-img-top"
            :alt="`${de.assessmentEvaluationNameFragment} ${de.assessmentEvaluationBooklet} ${booklet.number}`"
        >
        <div class="card-body">
            <h3 class="h5">{{ de.assessmentEvaluationBooklet }} {{ booklet.number }}</h3>
            <p v-if="!booklet.name_fragment_url" class="small text-muted">{{ de.assessmentEvaluationNoNameFragment }}</p>
            <form @submit.prevent="save">
                <label class="form-label" :for="`booklet-student-${booklet.id}`">{{ de.assessmentEvaluationAssignStudent }}</label>
                <select
                    :id="`booklet-student-${booklet.id}`"
                    v-model="selectedStudentId"
                    class="form-select"
                    :disabled="form.processing"
                >
                    <option value="">{{ de.assessmentEvaluationChooseStudent }}</option>
                    <option v-for="student in studentOptions" :key="student.id" :value="student.id" :disabled="student.disabled">
                        {{ student.label }}
                    </option>
                </select>
                <div v-if="form.errors.student_id" class="invalid-feedback d-block">{{ form.errors.student_id }}</div>
                <div class="d-flex flex-wrap justify-content-end gap-2 mt-3">
                    <button
                        v-if="booklet.student_id"
                        class="btn btn-sm btn-outline-secondary"
                        type="button"
                        :disabled="form.processing"
                        @click="unassign"
                    >{{ de.assessmentEvaluationUnassign }}</button>
                    <button class="btn btn-sm btn-primary" type="submit" :disabled="form.processing || !selectedStudentId">
                        {{ de.saveChanges }}
                    </button>
                </div>
            </form>
        </div>
        <BookletStatusControl
            v-if="!booklet.student_id"
            :group="group"
            :assessment="assessment"
            :booklet="booklet"
        />
    </article>
</template>
