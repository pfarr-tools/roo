<script setup>
import { useForm, router } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import AppShell from "../../Components/Ui/AppShell.vue";
import CompetencyPickerModal from "../../Components/Planning/CompetencyPickerModal.vue";
import ImageLibraryUploadModal from "../../Components/Ui/ImageLibraryUploadModal.vue";
import de from "../../i18n/de";
import {
    competencyNumber,
    competencyNumberAndText,
    competencyText,
} from "../../utils/competencies";

const props = defineProps({
    backUrl: { type: String, required: true },
    submitUrl: { type: String, required: true },
    method: { type: String, default: "post" },
    competencyId: { type: [String, Number], default: "" },
    initialEducationPlanId: { type: [String, Number], default: "" },
    initialCompetency: { type: Object, default: null },
    educationPlans: { type: Array, default: () => [] },
    task: { type: Object, default: null },
    imageLibrary: { type: Array, default: () => [] },
    imageUploadUrl: { type: String, default: "" },
    libraryMode: { type: Boolean, default: false },
});

const taskTypes = Object.entries(de.assessmentTaskTypeLabels).map(
    ([value, label]) => ({ value, label }),
);
const editorTab = ref("details");
const competencyPickerOpen = ref(false);
const imageUploadOpen = ref(false);
const imageLibraryOpen = ref(false);
const imageLibrarySearch = ref("");
const activeImageLabel = ref(null);
const draggedImageIndex = ref(null);
const saveError = ref("");
const selectedCompetencyText = ref("");
const selectedCompetencyNumber = ref("");
const selectedCompetencyWording = ref("");
const selectedCompetencyDifferentiated = ref(false);
const emptyExpectation = () => ({ text: "", points: 1, repetitions: 1 });
const newOption = () => ({
    id: `option-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    text: "",
    correct: false,
});
const newSubtask = () => ({
    key: `subtask-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    label: "",
    image_identifier: "",
    solution: "",
    lines: 3,
    points: 1,
    expectations: [],
});
const emptyContent = () => ({
    prompt: "",
    lines: 5,
    lineated: false,
    reading_text: "",
    options: [newOption()],
    points_per_correct_answer: 1,
    checkbox_scoring_mode: "correct_states",
    image_width_cm: 3,
    image_label_width_cm: 6,
    image_label_layout: "center",
    points_per_correct_answer: 1,
    show_solutions: false,
    columns: [""],
    rows: [{ label: "", answer: "" }],
    subtasks: [newSubtask()],
    questions: [{ label: "", lines: 3 }],
    words: "",
});
const form = useForm({
    title: "",
    task_type: "free_text",
    content: emptyContent(),
    images: [],
    image_labels: [],
    expectations: [emptyExpectation()],
    solution: "",
    education_plan_id: "",
    education_plan_competency_id: "",
    levels: [],
});

function resetForm() {
    const content = { ...emptyContent(), ...(props.task?.content ?? {}) };
    if (
        props.task?.task_type === "checkbox" &&
        !props.task?.content?.checkbox_scoring_mode
    )
        content.checkbox_scoring_mode = "correct_selections";
    content.options = (content.options ?? []).map((option, index) => ({
        id: option.id ?? `option-${index + 1}`,
        ...option,
    }));
    content.subtasks = (content.subtasks ?? []).map((subtask, index) => ({
        ...newSubtask(),
        ...subtask,
        key: subtask.key ?? `subtask-${index + 1}`,
        expectations: (props.task?.expectations ?? [])
            .filter((expectation) => expectation.subtask_key === (subtask.key ?? `subtask-${index + 1}`))
            .map((expectation) => ({ text: expectation.text ?? "", points: expectation.points ?? 1, repetitions: expectation.repetitions ?? 1 })),
    }));
    form.defaults({
        title: props.task?.title ?? "",
        task_type: props.task?.task_type ?? "free_text",
        content,
        images: (props.task?.images ?? []).map((image) => ({
            identifier: image.identifier,
            resource_id: image.resource_reference_id,
            name: image.resource?.original_name ?? "",
            preview_url: image.preview_url ?? image.resource?.preview_url ?? "",
            label: image.label ?? "",
            answer: image.answer ?? "",
        })),
        image_labels: (props.task?.images?.[0]?.labels ?? []).map((label, index) => ({
            id: label.id,
            position: label.position ?? index,
            x_percent: Number(label.x_percent),
            y_percent: Number(label.y_percent),
            solution: label.solution ?? "",
            lines: label.lines ?? 1,
        })),
        expectations: props.task?.expectations?.length
            ? props.task.expectations.map((expectation) => ({
                  text: expectation.text ?? "",
                  points: expectation.points ?? 1,
                  repetitions: expectation.repetitions ?? 1,
              }))
            : [emptyExpectation()],
        solution: props.task?.solution ?? "",
        education_plan_id:
            props.task?.education_plan_id ?? props.initialEducationPlanId ?? "",
        education_plan_competency_id:
            props.task?.education_plan_competency_id ??
            props.initialCompetency?.id ??
            "",
        levels: props.task?.levels?.map((level) => level.level ?? level) ?? [],
    });
    form.reset();
    if (props.task?.education_plan_competency)
        setSelectedCompetency(props.task.education_plan_competency);
    else if (!props.task && props.initialCompetency)
        setSelectedCompetency(props.initialCompetency);
    else {
        selectedCompetencyNumber.value = "";
        selectedCompetencyWording.value = props.task?.competency ?? "";
        selectedCompetencyText.value = props.task?.competency ?? "";
    }
    selectedCompetencyDifferentiated.value =
        props.task?.has_differentiation ||
        competencyIsDifferentiated(props.task?.education_plan_competency) ||
        competencyIsDifferentiated(
            props.task?.competency?.education_plan_competency,
        ) ||
        false;
}
watch(() => props.task?.id, resetForm, { immediate: true });

const typeLabel = (value) => de.assessmentTaskTypeLabels[value] || value;
const usesOptions = (value) => ["checkbox", "matching_table"].includes(value);
const usesTable = (value) =>
    [
        "fill_table",
        "matching_table",
        "subtask_table",
        "image_answer_table",
        "heading_table",
    ].includes(value);
const usesImages = (value) =>
    [
        "free_text_images",
        "image_matching",
        "image_labeling",
        "image_answer_table",
    ].includes(value);
const usesQuestions = (value) =>
    ["labeled_fields", "reading_text", "sorting"].includes(value);
function selectType(value) {
    form.task_type = value;
    editorTab.value = "content";
}
function addOption() {
    form.content.options.push(newOption());
}
function addRow() {
    form.content.rows.push({ label: "", answer: "" });
}
function addSubtask() {
    form.content.subtasks.push(newSubtask());
}
function addSubtaskExpectation(subtask) {
    subtask.expectations.push(emptyExpectation());
}
function addImage() {
    const image = {
        identifier:
            "pair-" + Date.now() + "-" + Math.random().toString(36).slice(2, 8),
        resource_id: "",
        name: "",
        preview_url: "",
        label: "",
        answer: "",
    };
    form.images.push(image);
    if (form.task_type === "image_answer_table") {
        const placeholder = form.content.subtasks.find(
            (subtask) => !subtask.image_identifier && !subtask.label && !subtask.solution,
        );
        if (placeholder) placeholder.image_identifier = image.identifier;
        else form.content.subtasks.push({ ...newSubtask(), image_identifier: image.identifier });
    }
}
function removeImage(index) {
    const image = form.images[index];
    form.images.splice(index, 1);
    if (form.task_type === "image_answer_table" && image?.identifier) {
        const subtaskIndex = form.content.subtasks.findIndex(
            (subtask) => subtask.image_identifier === image.identifier,
        );
        if (subtaskIndex >= 0) form.content.subtasks.splice(subtaskIndex, 1);
    }
}
function moveImage(index, offset) {
    const target = index + offset;
    if (target < 0 || target >= form.images.length) return;
    const [image] = form.images.splice(index, 1);
    form.images.splice(target, 0, image);
}
function startImageDrag(index) {
    draggedImageIndex.value = index;
}
function dropImage(index) {
    if (draggedImageIndex.value === null || draggedImageIndex.value === index) {
        draggedImageIndex.value = null;
        return;
    }
    const [image] = form.images.splice(draggedImageIndex.value, 1);
    form.images.splice(index, 0, image);
    draggedImageIndex.value = null;
}
function shuffleImages() {
    for (let index = form.images.length - 1; index > 0; index -= 1) {
        const replacement = Math.floor(Math.random() * (index + 1));
        [form.images[index], form.images[replacement]] = [
            form.images[replacement],
            form.images[index],
        ];
    }
}
function openImageUpload() {
    imageUploadOpen.value = true;
}
function openImageLibrary() {
    imageLibrarySearch.value = "";
    imageLibraryOpen.value = true;
}
function addLibraryImage(libraryImage) {
    const image = {
        identifier:
            "pair-" + Date.now() + "-" + Math.random().toString(36).slice(2, 8),
        resource_id: libraryImage.id,
        name: libraryImage.name,
        preview_url: libraryImage.preview_url,
        answer: "",
    };
    if (form.task_type === "image_labeling") form.images = [image];
    else {
        form.images.push(image);
        if (form.task_type === "image_answer_table") {
            const placeholder = form.content.subtasks.find(
                (subtask) => !subtask.image_identifier && !subtask.label && !subtask.solution,
            );
            if (placeholder) placeholder.image_identifier = image.identifier;
            else form.content.subtasks.push({ ...newSubtask(), image_identifier: image.identifier });
        }
    }
    imageLibraryOpen.value = false;
}
function addImageLabel(event) {
    if (form.task_type !== "image_labeling" || !form.images[0]?.preview_url) return;
    const rect = event.currentTarget.getBoundingClientRect();
    form.image_labels.push({
        position: form.image_labels.length,
        x_percent: Math.max(0, Math.min(100, ((event.clientX - rect.left) / rect.width) * 100)),
        y_percent: Math.max(0, Math.min(100, ((event.clientY - rect.top) / rect.height) * 100)),
        solution: "",
        lines: 1,
    });
    activeImageLabel.value = form.image_labels.length - 1;
}
function removeImageLabel(index) {
    form.image_labels.splice(index, 1);
    form.image_labels.forEach((label, position) => { label.position = position; });
    activeImageLabel.value = null;
}
const filteredImageLibrary = computed(() => {
    const query = imageLibrarySearch.value.trim().toLocaleLowerCase("de");
    return props.imageLibrary.filter(
        (image) => !query || image.name.toLocaleLowerCase("de").includes(query),
    );
});
function addQuestion() {
    form.content.questions.push({ label: "", lines: 3 });
}
function addExpectation() {
    form.expectations.push(emptyExpectation());
}
function totalPoints() {
    return form.expectations.reduce(
        (total, expectation) =>
            total +
            (Number(expectation.points) || 0) *
                (Number(expectation.repetitions) || 0),
        0,
    );
}
function expectationCount() {
    return form.expectations.filter(
        (expectation) => String(expectation.text || "").trim() !== "",
    ).length;
}
function setSelectedCompetency(competency) {
    const presentation = competency.competency_presentation || {};
    selectedCompetencyNumber.value = competencyNumber(competency);
    selectedCompetencyWording.value = competencyText(competency);
    selectedCompetencyText.value = competencyNumberAndText(competency);
}
function competencyIsDifferentiated(competency) {
    return (
        competency?.has_differentiation ||
        (competency?.variants || []).some(
            (variant) => variant.education_plan_level_id,
        )
    );
}
const competenceSummary = computed(
    () =>
        `Du kannst ${selectedCompetencyWording.value || "…"}${selectedCompetencyNumber.value ? ` (${selectedCompetencyNumber.value})` : ""} [${totalPoints()} VP]`,
);
function removeAt(collection, index) {
    if (collection.length > 1) collection.splice(index, 1);
}
function pickerEndpoint() {
    return form.education_plan_id
        ? "/ressourcen/bibliothek/bildungsplaene/" +
              form.education_plan_id +
              "/kompetenzen"
        : "/ressourcen/bibliothek/bildungsplaene/0/kompetenzen";
}
function applyCompetency(ids, selected = []) {
    form.education_plan_competency_id = ids[0] ?? "";
    if (selected[0]) setSelectedCompetency(selected[0]);
    else {
        selectedCompetencyText.value = "";
        selectedCompetencyNumber.value = "";
        selectedCompetencyWording.value = "";
    }
    selectedCompetencyDifferentiated.value =
        selected[0]?.has_differentiation ?? false;
}
function choosePlan() {
    form.education_plan_competency_id = "";
    selectedCompetencyText.value = "";
    selectedCompetencyNumber.value = "";
    selectedCompetencyWording.value = "";
    selectedCompetencyDifferentiated.value = false;
}
function save() {
    saveError.value = "";
    const payload = form.data();
    const content = { ...payload.content };
    if (["subtask_table", "image_answer_table"].includes(form.task_type)) {
        content.subtasks = content.subtasks.map((subtask) => {
            const { expectations, ...data } = subtask;
            return data;
        });
        payload.expectations = form.content.subtasks.flatMap((subtask) =>
            String(subtask.solution || "").trim()
                ? []
                : (subtask.expectations || [])
                      .filter((expectation) => String(expectation.text || "").trim() !== "")
                      .map((expectation) => ({ ...expectation, subtask_key: subtask.key })),
        );
    }
    if (!usesOptions(form.task_type)) delete content.options;
    if (!["image_matching", "image_answer_table"].includes(form.task_type)) delete content.image_width_cm;
    if (!['image_labeling', 'subtask_table', 'image_answer_table'].includes(form.task_type)) {
        delete content.image_label_width_cm;
        delete content.image_label_layout;
        delete content.show_solutions;
    }
    if (!["checkbox", "image_matching", "image_labeling"].includes(form.task_type)) {
        delete content.points_per_correct_answer;
        delete content.checkbox_scoring_mode;
    }
    if (!usesTable(form.task_type)) {
        delete content.columns;
        delete content.rows;
    }
    if (["subtask_table", "image_answer_table"].includes(form.task_type)) {
        delete content.columns;
        delete content.rows;
    } else {
        delete content.subtasks;
    }
    if (!usesImages(form.task_type)) payload.images = [];
    else
        payload.images = payload.images
            .filter((image) => image.resource_id)
            .map((image, position) => ({ ...image, position }));
    if (form.task_type === "image_labeling") {
        payload.images = payload.images.slice(0, 1);
        payload.image_labels = form.image_labels.map((label, position) => ({ ...label, position, lines: Number(label.lines) || 1 }));
    } else {
        delete payload.image_labels;
    }
    if (!usesQuestions(form.task_type)) delete content.questions;
    if (form.task_type !== "reading_text") delete content.reading_text;
    if (form.task_type !== "sentence_builder") delete content.words;
    if (
        !["free_text", "free_text_images", "reading_text", "subtask_table", "image_answer_table"].includes(
            form.task_type,
        )
    ) {
        delete content.lines;
        delete content.lineated;
    }
    payload.content = content;
    payload.expectations = payload.expectations.filter(
        (expectation) => String(expectation.text || "").trim() !== "",
    );
    delete payload.competency_id;
    form.transform(() => payload)[props.method](props.submitUrl, {
        onSuccess: () => router.visit(props.backUrl),
        onError: (errors) => {
            const firstError = Object.values(errors ?? {})[0];
            saveError.value = Array.isArray(firstError)
                ? firstError[0]
                : firstError || de.assessmentTaskSaveError;
        },
    });
}
</script>

<template>
    <AppShell>
        <template #toolbar
            ><a
                :href="backUrl"
                class="btn btn-sm btn-light"
                :title="de.close"
                :aria-label="de.close"
                ><i class="bi bi-x-lg" aria-hidden="true"></i></a
            ><button
                class="btn btn-sm btn-primary ms-2"
                type="submit"
                form="assessment-task-form"
                :disabled="form.processing"
            >
                <i class="bi bi-check-lg me-1" aria-hidden="true"></i
                >{{ de.saveChanges }}
            </button></template
        >
        <div class="container-full px-3 py-4">
            <div
                v-if="saveError"
                class="roo-toast-container"
                aria-live="assertive"
                aria-atomic="true"
            >
                <div class="roo-toast roo-toast-error" role="alert">
                    <span>{{ saveError }}</span>
                    <button
                        class="btn-close btn-close-white ms-3"
                        type="button"
                        :aria-label="de.close"
                        @click="saveError = ''"
                    ></button>
                </div>
            </div>
            <h1 class="h2">
                {{ task ? de.editAssessmentTask : de.newAssessmentTask }}
            </h1>
            <form id="assessment-task-form" novalidate @submit.prevent="save">
                <ul
                    class="nav nav-tabs mb-4"
                    role="tablist"
                    :aria-label="de.assessmentTaskType"
                >
                    <li class="nav-item" role="presentation">
                        <button
                            class="nav-link"
                            :class="{ active: editorTab === 'details' }"
                            type="button"
                            role="tab"
                            :aria-selected="editorTab === 'details'"
                            @click="editorTab = 'details'"
                        >
                            {{ de.assessmentTaskTabTask }}
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button
                            class="nav-link"
                            :class="{ active: editorTab === 'content' }"
                            type="button"
                            role="tab"
                            :aria-selected="editorTab === 'content'"
                            @click="editorTab = 'content'"
                        >
                            {{ de.assessmentTaskTabContent }}
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button
                            class="nav-link"
                            :class="{ active: editorTab === 'expectations' }"
                            type="button"
                            role="tab"
                            :aria-selected="editorTab === 'expectations'"
                            @click="editorTab = 'expectations'"
                        >
                            {{ de.assessmentTaskExpectations }}
                            <span
                                class="badge rounded-pill text-bg-secondary"
                                >{{ expectationCount() }}</span
                            >
                        </button>
                    </li>
                </ul>
                <section
                    v-show="editorTab === 'content'"
                    class="row g-4"
                    role="tabpanel"
                >
                    <div class="col-3">
                        <article class="card card-body h-100">
                            <h2 class="h5">{{ de.assessmentTaskType }}</h2>
                            <p class="text-muted small">
                                {{ de.assessmentTaskTypeHint }}
                            </p>
                            <div class="row g-2">
                                <div
                                    v-for="item in taskTypes"
                                    :key="item.value"
                                    class="col-12"
                                >
                                    <button
                                        type="button"
                                        class="btn w-100 text-start h-100 p-2"
                                        :class="
                                            form.task_type === item.value
                                                ? 'btn-primary'
                                                : 'btn-outline-secondary'
                                        "
                                        @click="selectType(item.value)"
                                    >
                                        <span
                                            class="fw-semibold d-block small"
                                            >{{ item.label }}</span
                                        >
                                    </button>
                                </div>
                            </div>
                        </article>
                    </div>
                    <div class="col-9">
                        <article class="card card-body h-100">
                            <p class="mb-3 fw-semibold">
                                {{ competenceSummary }}
                            </p>
                            <h2 class="h5">{{ typeLabel(form.task_type) }}</h2>
                            <label
                                class="form-label"
                                for="assessment-task-prompt"
                                >{{ de.assessmentTaskPrompt }}</label
                            ><textarea
                                id="assessment-task-prompt"
                                v-model="form.content.prompt"
                                class="form-control"
                                rows="4"
                                required
                            ></textarea>
                            <div
                                v-if="form.task_type === 'reading_text'"
                                class="mt-3"
                            >
                                <label class="form-label">Lesetext</label
                                ><textarea
                                    v-model="form.content.reading_text"
                                    class="form-control"
                                    rows="10"
                                    required
                                ></textarea>
                            </div>
                            <div
                                v-if="
                                    [
                                        'free_text',
                                        'free_text_images',
                                        'reading_text',
                                    ].includes(form.task_type)
                                "
                                class="mt-3"
                            >
                                <label class="form-label">{{
                                    de.assessmentTaskLines
                                }}</label>
                                <div class="d-flex align-items-center gap-3">
                                    <input
                                        v-model="form.content.lines"
                                        type="number"
                                        min="0"
                                        class="form-control"
                                        style="max-width: 12rem"
                                    /><label class="form-check mb-0"
                                        ><input
                                            v-model="form.content.lineated"
                                            type="checkbox"
                                            class="form-check-input"
                                        /><span class="form-check-label">{{
                                            de.assessmentTaskLineation
                                        }}</span></label
                                    >
                                </div>
                            </div>
                            <div
                                v-if="
                                    usesOptions(form.task_type) ||
                                    ['image_matching', 'image_labeling', 'image_answer_table'].includes(form.task_type)
                                "
                                class="mt-4"
                            >
                                <h3
                                    v-if="usesOptions(form.task_type)"
                                    class="h6"
                                >
                                    {{ de.assessmentTaskOptions }}
                                </h3>
                                <div
                                    v-if="
                                        ['checkbox', 'image_matching', 'image_labeling'].includes(
                                            form.task_type,
                                        )
                                    "
                                    class="mb-3"
                                >
                                    <label
                                        class="form-label"
                                        for="assessment-task-points-per-correct-answer"
                                        >{{
                                            de.assessmentTaskPointsPerCorrectAnswer
                                        }}</label
                                    ><input
                                        id="assessment-task-points-per-correct-answer"
                                        v-model="
                                            form.content
                                                .points_per_correct_answer
                                        "
                                        class="form-control"
                                        type="number"
                                        min="0"
                                        step="1"
                                        required
                                    /><template
                                        v-if="form.task_type === 'checkbox'"
                                        ><label
                                            class="form-label mt-3"
                                            for="assessment-task-checkbox-scoring-mode"
                                            >{{
                                                de.assessmentTaskCheckboxScoringMode
                                            }}</label
                                        ><select
                                            id="assessment-task-checkbox-scoring-mode"
                                            v-model="
                                                form.content
                                                    .checkbox_scoring_mode
                                            "
                                            class="form-select"
                                        >
                                            <option value="correct_states">
                                                {{
                                                    de.assessmentTaskCheckboxScoringCorrectStates
                                                }}
                                            </option>
                                            <option value="correct_selections">
                                                {{
                                                    de.assessmentTaskCheckboxScoringCorrectSelections
                                                }}
                                            </option>
                                        </select></template
                                    >
                                </div>
                                <div v-if="form.task_type === 'image_labeling'" class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label" for="assessment-task-label-image-width">{{ de.assessmentTaskImageWidth }}: {{ Number(form.content.image_label_width_cm).toFixed(1).replace('.', ',') }} cm</label>
                                        <input id="assessment-task-label-image-width" v-model.number="form.content.image_label_width_cm" class="form-range" type="range" min="4" max="8" step="0.1" />
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label" for="assessment-task-label-layout">{{ de.assessmentTaskImageLabelLayout }}</label>
                                        <select id="assessment-task-label-layout" v-model="form.content.image_label_layout" class="form-select">
                                            <option value="center">{{ de.assessmentTaskImageLabelLayoutCenter }}</option>
                                            <option value="left">{{ de.assessmentTaskImageLabelLayoutLeft }}</option>
                                            <option value="right">{{ de.assessmentTaskImageLabelLayoutRight }}</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <label class="form-check mb-2"><input v-model="form.content.show_solutions" class="form-check-input" type="checkbox" /><span class="form-check-label">{{ de.assessmentTaskShowSolutions }}</span></label>
                                    </div>
                                </div>
                                <div
                                    v-if="form.task_type === 'checkbox'"
                                    v-for="(option, index) in form.content
                                        .options"
                                    :key="option.id"
                                    class="input-group mb-2"
                                >
                                    <input
                                        v-model="option.text"
                                        class="form-control"
                                        :placeholder="de.assessmentTaskOption"
                                        required
                                    /><span class="input-group-text"
                                        ><input
                                            v-model="option.correct"
                                            type="checkbox"
                                            class="form-check-input me-2"
                                        />{{ de.assessmentTaskCorrect }}</span
                                    ><button
                                        type="button"
                                        class="btn btn-outline-danger"
                                        @click="
                                            removeAt(
                                                form.content.options,
                                                index,
                                            )
                                        "
                                    >
                                        ×
                                    </button>
                                </div>
                                <button
                                    v-if="form.task_type === 'checkbox'"
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    @click="addOption"
                                >
                                    {{ de.assessmentTaskAddOption }}
                                </button>
                            </div>
                            <div v-if="form.task_type === 'subtask_table'" class="mt-4">
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-check"><input v-model="form.content.show_solutions" type="checkbox" class="form-check-input" /><span class="form-check-label">{{ de.assessmentTaskShowSolutions }}</span></label>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-check"><input v-model="form.content.lineated" type="checkbox" class="form-check-input" /><span class="form-check-label">{{ de.assessmentTaskLineation }}</span></label>
                                    </div>
                                </div>
                                <div v-for="(subtask, index) in form.content.subtasks" :key="subtask.key" class="border rounded p-3 mb-3">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-4"><label class="form-label">{{ de.assessmentTaskSubtask }}</label><input v-model="subtask.label" class="form-control" required /></div>
                                        <div class="col-md-4"><label class="form-label">{{ de.assessmentTaskSubtaskSolution }}</label><input v-model="subtask.solution" class="form-control" /></div>
                                        <div class="col-md-2"><label class="form-label">{{ de.assessmentTaskSubtaskLines }}</label><input v-model.number="subtask.lines" type="number" min="0" class="form-control" required /></div>
                                        <div class="col-md-1" v-if="String(subtask.solution || '').trim()"><label class="form-label">{{ de.assessmentTaskSubtaskPoints }}</label><input v-model.number="subtask.points" type="number" min="1" class="form-control" required /></div>
                                        <div class="col-md-1"><button type="button" class="btn btn-outline-danger" @click="removeAt(form.content.subtasks, index)">×</button></div>
                                    </div>
                                    <div v-if="!String(subtask.solution || '').trim()" class="mt-3 ps-3 border-start">
                                        <h4 class="h6">{{ de.assessmentTaskSubtaskExpectations }}</h4>
                                        <div v-for="(expectation, expectationIndex) in subtask.expectations" :key="expectationIndex" class="row g-2 mb-2">
                                            <div class="col"><input v-model="expectation.text" class="form-control" :placeholder="de.assessmentTaskSubtaskExpectation" required /></div>
                                            <div class="col-auto"><input v-model.number="expectation.points" type="number" min="1" class="form-control" :placeholder="de.assessmentTaskSubtaskPoints" required /></div>
                                            <div class="col-auto"><button type="button" class="btn btn-outline-danger" @click="removeAt(subtask.expectations, expectationIndex)">×</button></div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" @click="addSubtaskExpectation(subtask)">{{ de.assessmentTaskSubtaskAddExpectation }}</button>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" @click="addSubtask">{{ de.assessmentTaskAddSubtask }}</button>
                            </div>
                            <div v-else-if="usesTable(form.task_type) && form.task_type !== 'image_answer_table'" class="mt-4">
                                <h3 class="h6">
                                    {{ de.assessmentTaskColumns }}
                                </h3>
                                <div
                                    v-for="(column, index) in form.content
                                        .columns"
                                    :key="index"
                                    class="input-group mb-2"
                                >
                                    <input
                                        v-model="form.content.columns[index]"
                                        class="form-control"
                                        placeholder="Spaltenüberschrift"
                                        required
                                    /><button
                                        type="button"
                                        class="btn btn-outline-danger"
                                        @click="
                                            removeAt(
                                                form.content.columns,
                                                index,
                                            )
                                        "
                                    >
                                        ×
                                    </button>
                                </div>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary mb-3"
                                    @click="form.content.columns.push('')"
                                >
                                    Spalte hinzufügen
                                </button>
                                <h3 class="h6">{{ de.assessmentTaskRows }}</h3>
                                <div
                                    v-for="(row, index) in form.content.rows"
                                    :key="index"
                                    class="row g-2 mb-2"
                                >
                                    <div class="col-md-5">
                                        <input
                                            v-model="row.label"
                                            class="form-control"
                                            placeholder="Zeile / Teilaufgabe"
                                            required
                                        />
                                    </div>
                                    <div class="col-md-6">
                                        <input
                                            v-model="row.answer"
                                            class="form-control"
                                            :placeholder="
                                                de.assessmentTaskAnswer
                                            "
                                        />
                                    </div>
                                    <div class="col-md-1">
                                        <button
                                            type="button"
                                            class="btn btn-outline-danger"
                                            @click="
                                                removeAt(
                                                    form.content.rows,
                                                    index,
                                                )
                                            "
                                        >
                                            ×
                                        </button>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    @click="addRow"
                                >
                                    {{ de.assessmentTaskAddRow }}
                                </button>
                            </div>
                            <div v-if="usesImages(form.task_type)" class="mt-4">
                                <h3 class="h6">
                                    {{ de.assessmentTaskImages }}
                                </h3>
                                <div
                                    v-if="['image_matching', 'image_answer_table'].includes(form.task_type)"
                                    class="mb-3"
                                >
                                    <label
                                        class="form-label"
                                        for="assessment-task-image-width"
                                        >{{ de.assessmentTaskImageWidth }}:
                                        {{
                                            Number(form.content.image_width_cm)
                                                .toFixed(1)
                                                .replace(".", ",")
                                        }}
                                        cm</label
                                    ><input
                                        id="assessment-task-image-width"
                                        v-model.number="
                                            form.content.image_width_cm
                                        "
                                        class="form-range"
                                        type="range"
                                        min="1.5"
                                        max="4"
                                        step="0.1"
                                    />
                                </div>
                                <div v-if="form.task_type === 'image_answer_table'" class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-check"><input v-model="form.content.show_solutions" type="checkbox" class="form-check-input" /><span class="form-check-label">{{ de.assessmentTaskShowSolutions }}</span></label>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-check"><input v-model="form.content.lineated" type="checkbox" class="form-check-input" /><span class="form-check-label">{{ de.assessmentTaskLineation }}</span></label>
                                    </div>
                                </div>
                                <div
                                    v-for="(subtask, index) in form.content.subtasks"
                                    v-if="form.task_type === 'image_answer_table'"
                                    :key="subtask.key || index"
                                    class="border rounded p-2 mb-2"
                                >
                                    <div class="row g-2 align-items-center">
                                        <div class="col-auto">
                                            <img
                                                :src="form.images.find((image) => image.identifier === subtask.image_identifier)?.preview_url"
                                                :alt="form.images.find((image) => image.identifier === subtask.image_identifier)?.name || de.assessmentTaskImages"
                                                class="rounded object-fit-contain"
                                                style="width: 6rem; height: 4rem"
                                            />
                                        </div>
                                        <div class="col">
                                            <label class="form-label mb-1">{{ de.assessmentTaskSubtaskSolution }}</label>
                                            <input v-model="subtask.solution" class="form-control" :placeholder="de.assessmentTaskSubtaskSolution" />
                                        </div>
                                        <div class="col-auto" style="max-width: 7rem">
                                            <label class="form-label mb-1">{{ de.assessmentTaskSubtaskLines }}</label>
                                            <input v-model.number="subtask.lines" type="number" min="0" class="form-control" required />
                                        </div>
                                        <div v-if="String(subtask.solution || '').trim()" class="col-auto" style="max-width: 7rem">
                                            <label class="form-label mb-1">{{ de.assessmentTaskSubtaskPoints }}</label>
                                            <input v-model.number="subtask.points" type="number" min="1" class="form-control" required />
                                        </div>
                                        <div class="col-auto">
                                            <button type="button" class="btn btn-outline-danger" @click="removeImage(form.images.findIndex((image) => image.identifier === subtask.image_identifier))">×</button>
                                        </div>
                                    </div>
                                    <div v-if="!String(subtask.solution || '').trim()" class="mt-3 ps-3 border-start">
                                        <h4 class="h6">{{ de.assessmentTaskSubtaskExpectations }}</h4>
                                        <div v-for="(expectation, expectationIndex) in subtask.expectations" :key="expectationIndex" class="row g-2 mb-2">
                                            <div class="col"><input v-model="expectation.text" class="form-control" :placeholder="de.assessmentTaskSubtaskExpectation" required /></div>
                                            <div class="col-auto"><input v-model.number="expectation.points" type="number" min="1" class="form-control" :placeholder="de.assessmentTaskSubtaskPoints" required /></div>
                                            <div class="col-auto"><button type="button" class="btn btn-outline-danger" @click="removeAt(subtask.expectations, expectationIndex)">×</button></div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" @click="addSubtaskExpectation(subtask)">{{ de.assessmentTaskSubtaskAddExpectation }}</button>
                                    </div>
                                </div>
                                <div v-if="form.task_type === 'image_labeling'" class="mb-3">
                                    <div v-if="form.images[0]?.preview_url" class="image-labeling-stage mx-auto" @click="addImageLabel">
                                        <img :src="form.images[0].preview_url" :alt="form.images[0].name || de.assessmentTaskImages" class="img-fluid d-block" />
                                        <button v-for="(label, index) in form.image_labels" :key="label.id || index" type="button" class="image-labeling-point" :class="{ active: activeImageLabel === index }" :style="{ left: `${label.x_percent}%`, top: `${label.y_percent}%` }" :aria-label="`${de.assessmentTaskLabelPoint} ${index + 1}`" @click.stop="activeImageLabel = index" @mouseenter="activeImageLabel = index"><span>{{ index + 1 }}</span></button>
                                    </div>
                                    <p v-else class="text-muted">{{ de.assessmentTaskChooseImage }}</p>
                                    <div v-for="(label, index) in form.image_labels" :key="label.id || `label-${index}`" class="input-group input-group-sm mt-2" @mouseenter="activeImageLabel = index" @mouseleave="activeImageLabel = null">
                                        <span class="input-group-text">{{ index + 1 }}</span>
                                        <input v-model="label.solution" class="form-control" :class="{ 'border-primary': activeImageLabel === index }" :placeholder="de.assessmentTaskLabelSolution" @focus="activeImageLabel = index" />
                                        <label class="input-group-text" :for="`assessment-task-label-lines-${index}`">{{ de.assessmentTaskLabelLines }}</label>
                                        <input :id="`assessment-task-label-lines-${index}`" v-model.number="label.lines" class="form-control w-auto" style="max-width: 3.25rem" type="number" min="1" max="9" inputmode="numeric" />
                                        <button type="button" class="btn btn-outline-danger" :aria-label="de.remove" @mouseenter="activeImageLabel = index" @focus="activeImageLabel = index" @click="removeImageLabel(index)"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                    </div>
                                </div>
                                <div
                                    v-for="(image, index) in form.images"
                                    v-if="form.task_type === 'image_matching'"
                                    :key="image.identifier || index"
                                    class="border rounded p-2 mb-2"
                                    draggable="true"
                                    @dragstart="startImageDrag(index)"
                                    @dragover.prevent
                                    @drop="dropImage(index)"
                                >
                                    <div class="row g-2 align-items-center">
                                        <div class="col-auto">
                                            <span
                                                class="btn btn-sm btn-light"
                                                role="button"
                                                tabindex="0"
                                                :aria-label="
                                                    de.assessmentTaskMoveImage
                                                "
                                                :title="
                                                    de.assessmentTaskMoveImage
                                                "
                                                @keydown.up.prevent="
                                                    moveImage(index, -1)
                                                "
                                                @keydown.down.prevent="
                                                    moveImage(index, 1)
                                                "
                                            >
                                                <i
                                                    class="bi bi-grip-vertical"
                                                    aria-hidden="true"
                                                ></i>
                                            </span>
                                        </div>
                                        <div class="col-auto">
                                            <img
                                                :src="image.preview_url"
                                                :alt="
                                                    image.name ||
                                                    de.assessmentTaskImages
                                                "
                                                class="rounded object-fit-contain"
                                                style="
                                                    width: 6rem;
                                                    height: 4rem;
                                                "
                                            />
                                        </div>
                                        <div class="col">
                                            <label
                                                class="visually-hidden"
                                                :for="
                                                    'assessment-task-image-answer-' +
                                                    index
                                                "
                                                >{{
                                                    de.assessmentTaskAnswer
                                                }}</label
                                            ><input
                                                :id="
                                                    'assessment-task-image-answer-' +
                                                    index
                                                "
                                                v-model="image.answer"
                                                class="form-control"
                                                :placeholder="
                                                    de.assessmentTaskAnswer
                                                "
                                            />
                                        </div>
                                        <div class="col-auto">
                                            <button
                                                type="button"
                                                class="btn btn-outline-danger"
                                                :aria-label="de.remove"
                                                @click="removeImage(index)"
                                            >
                                                <i
                                                    class="bi bi-trash"
                                                    aria-hidden="true"
                                                ></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        @click="openImageUpload"
                                    >
                                        <i
                                            class="bi bi-upload me-1"
                                            aria-hidden="true"
                                        ></i
                                        >{{ de.assessmentTaskUploadImage }}
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        @click="openImageLibrary"
                                    >
                                        <i
                                            class="bi bi-collection me-1"
                                            aria-hidden="true"
                                        ></i
                                        >{{ de.assessmentTaskImageLibrary }}
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        v-if="form.task_type === 'image_matching'"
                                        :disabled="form.images.length < 2"
                                        @click="shuffleImages"
                                    >
                                        <i
                                            class="bi bi-shuffle me-1"
                                            aria-hidden="true"
                                        ></i
                                        >{{ de.assessmentTaskShuffleImages }}
                                    </button>
                                </div>
                            </div>
                            <div
                                v-if="form.task_type === 'sentence_builder'"
                                class="mt-4"
                            >
                                <label class="form-label">{{
                                    de.assessmentTaskWords
                                }}</label
                                ><input
                                    v-model="form.content.words"
                                    class="form-control"
                                    placeholder="Wort 1, Wort 2, Wort 3"
                                    required
                                />
                            </div>
                            <div
                                v-if="usesQuestions(form.task_type)"
                                class="mt-4"
                            >
                                <h3 class="h6">
                                    {{ de.assessmentTaskQuestions }}
                                </h3>
                                <div
                                    v-for="(question, index) in form.content
                                        .questions"
                                    :key="index"
                                    class="input-group mb-2"
                                >
                                    <input
                                        v-model="question.label"
                                        class="form-control"
                                        :placeholder="
                                            de.assessmentTaskFieldLabel
                                        "
                                        required
                                    /><input
                                        v-model="question.lines"
                                        class="form-control"
                                        type="number"
                                        min="0"
                                        placeholder="Linien"
                                    /><button
                                        type="button"
                                        class="btn btn-outline-danger"
                                        @click="
                                            removeAt(
                                                form.content.questions,
                                                index,
                                            )
                                        "
                                    >
                                        ×
                                    </button>
                                </div>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    @click="addQuestion"
                                >
                                    {{ de.assessmentTaskAddQuestion }}
                                </button>
                            </div>
                        </article>
                    </div>
                </section>
                <section
                    v-show="editorTab === 'details'"
                    class="card card-body"
                    role="tabpanel"
                >
                    <label class="form-label" for="assessment-task-title">{{
                        de.assessmentTaskTitle
                    }}</label
                    ><input
                        id="assessment-task-title"
                        v-model="form.title"
                        class="form-control"
                        required
                    />
                    <label class="form-label mt-3" for="assessment-task-plan">{{
                        de.educationPlan
                    }}</label
                    ><select
                        id="assessment-task-plan"
                        v-model="form.education_plan_id"
                        class="form-select"
                        required
                        @change="choosePlan"
                    >
                        <option value="">{{ de.choose }}</option>
                        <option
                            v-for="plan in educationPlans"
                            :key="plan.id"
                            :value="plan.id"
                        >
                            {{ plan.title }}
                        </option></select
                    ><label class="form-label mt-3">{{ de.competency }}</label>
                    <div class="row g-2 align-items-center">
                        <div class="col-11">
                            <div class="form-control-plaintext py-2">
                                {{ selectedCompetencyText || de.choose }}
                            </div>
                        </div>
                        <div class="col-1">
                            <button
                                class="btn btn-outline-primary w-100"
                                type="button"
                                :disabled="!form.education_plan_id"
                                @click="competencyPickerOpen = true"
                            >
                                {{ de.assessmentTaskSelectCompetency }}
                            </button>
                        </div>
                    </div>
                    <template
                        v-if="
                            selectedCompetencyDifferentiated ||
                            form.education_plan_competency_id
                        "
                        ><label class="form-label mt-3">{{
                            de.assessmentLevels
                        }}</label>
                        <div class="d-flex gap-3">
                            <label
                                v-for="level in ['G', 'M', 'E']"
                                :key="level"
                                class="form-check"
                                ><input
                                    v-model="form.levels"
                                    class="form-check-input"
                                    type="checkbox"
                                    :value="level"
                                /><span class="form-check-label">{{
                                    level
                                }}</span></label
                            >
                        </div></template
                    >
                </section>
                <section
                    v-show="editorTab === 'expectations'"
                    class="card card-body"
                    role="tabpanel"
                >
                    <div
                        class="d-flex justify-content-between align-items-center mb-3"
                    >
                        <div>
                            <h2 class="h5 mb-1">
                                {{ de.assessmentTaskExpectations }}
                            </h2>
                            <p class="text-muted mb-0">
                                {{ totalPoints() }}
                                {{ de.assessmentTaskPoints }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-primary"
                            @click="addExpectation"
                        >
                            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i
                            >{{ de.assessmentTaskAddExpectation }}
                        </button>
                    </div>
                    <div
                        v-for="(expectation, index) in form.expectations"
                        :key="index"
                        class="row g-2 align-items-end mb-3"
                    >
                        <div class="col-md-7">
                            <label class="form-label">{{
                                de.assessmentTaskExpectation
                            }}</label
                            ><textarea
                                v-model="expectation.text"
                                class="form-control"
                                rows="2"
                                required
                            ></textarea>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{
                                de.assessmentTaskPoints
                            }}</label
                            ><input
                                v-model="expectation.points"
                                class="form-control"
                                type="number"
                                min="1"
                                required
                            />
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{
                                de.assessmentTaskRepetitions
                            }}</label
                            ><input
                                v-model="expectation.repetitions"
                                class="form-control"
                                type="number"
                                min="1"
                                required
                            />
                        </div>
                        <div class="col-md-1">
                            <button
                                type="button"
                                class="btn btn-outline-danger"
                                :disabled="form.expectations.length === 1"
                                :aria-label="de.remove"
                                @click="removeAt(form.expectations, index)"
                            >
                                <i class="bi bi-trash" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </section>
            </form>
        </div>
        <CompetencyPickerModal
            v-model="competencyPickerOpen"
            :selected-ids="
                form.education_plan_competency_id
                    ? [form.education_plan_competency_id]
                    : []
            "
            :competency-text="competencyText"
            :endpoint="pickerEndpoint()"
            :exclude-process-competencies="true"
            :single="true"
            @apply="applyCompetency"
        />
        <ImageLibraryUploadModal
            :show="imageUploadOpen"
            :upload-url="props.imageUploadUrl"
            @close="imageUploadOpen = false"
            @uploaded="addLibraryImage"
        />
        <div
            v-if="imageLibraryOpen"
            class="roo-modal-backdrop"
            role="presentation"
            @click.self="imageLibraryOpen = false"
        >
            <section
                class="roo-modal"
                role="dialog"
                aria-modal="true"
                :aria-label="de.assessmentTaskImageLibrary"
            >
                <div class="card border-0">
                    <div class="card-body">
                        <div
                            class="d-flex justify-content-between align-items-center mb-3"
                        >
                            <h2 class="h5 mb-0">
                                {{ de.assessmentTaskImageLibrary }}
                            </h2>
                            <button
                                class="btn-close"
                                type="button"
                                :aria-label="de.close"
                                @click="imageLibraryOpen = false"
                            ></button>
                        </div>
                        <input
                            v-model="imageLibrarySearch"
                            class="form-control mb-3"
                            type="search"
                            :placeholder="de.searchLibrary"
                        />
                        <div class="list-group">
                            <button
                                v-for="image in filteredImageLibrary"
                                :key="image.id"
                                class="list-group-item list-group-item-action d-flex align-items-center gap-3 text-start"
                                type="button"
                                @click="addLibraryImage(image)"
                            >
                                <img
                                    :src="image.preview_url"
                                    :alt="image.name"
                                    style="
                                        width: 4rem;
                                        height: 3rem;
                                        object-fit: contain;
                                    "
                                />
                                <span>{{ image.name }}</span>
                            </button>
                            <p
                                v-if="!filteredImageLibrary.length"
                                class="small text-muted mb-0"
                            >
                                {{ de.noImagesInLibrary }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppShell>
</template>

<style scoped>
.image-labeling-stage {
    position: relative;
    width: min(100%, 52rem);
    cursor: crosshair;
}

.image-labeling-stage img {
    width: 100%;
    height: auto;
}

.image-labeling-point {
    position: absolute;
    width: 1.75rem;
    height: 1.75rem;
    padding: 0;
    transform: translate(-50%, -50%);
    border: 2px solid #fff;
    border-radius: 50%;
    background: #dc3545;
    color: #fff;
    box-shadow: 0 0 0 1px #212529;
}

.image-labeling-point.active {
    background: #0d6efd;
}
</style>
