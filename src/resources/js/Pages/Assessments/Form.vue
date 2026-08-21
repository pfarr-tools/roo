<script setup>
import AppShell from "../../Components/Ui/AppShell.vue";
import { useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import de from "../../i18n/de";

const props = defineProps({
    group: Object,
    assessment: { type: Object, default: null },
    slot: { type: Object, default: null },
    assessmentTasks: { type: Array, default: () => [] },
    returnTab: { type: String, default: "assessments" },
    returnTo: { type: String, default: "group" },
});
const taskList = ref(props.assessmentTasks.map((task) => ({ ...task })));
const form = useForm({
    title: props.assessment?.title ?? "",
    report_period_id: props.assessment?.report_period_id ?? "",
    return_tab: props.returnTab,
    return_to: props.returnTo,
    notes: props.assessment?.notes ?? "",
    tasks: [],
});
function syncTasks() {
    form.tasks = taskList.value
        .filter((task) => task.checked)
        .map((task) => ({ task_id: task.id }));
}
function toggleTask(task) {
    task.checked = !task.checked;
    syncTasks();
}
function removeTask(task) {
    taskList.value = taskList.value.filter((item) => item.id !== task.id);
    syncTasks();
}
const libraryOpen = ref(false);
const librarySearch = ref("");
const libraryItems = ref([]);
const libraryLoading = ref(false);
async function searchLibrary() {
    libraryLoading.value = true;
    try {
        const response = await fetch(
            "/ressourcen/bibliothek?q=" +
                encodeURIComponent(librarySearch.value) +
                "&type=assessment-task",
            {
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
            },
        );
        if (response.ok) libraryItems.value = await response.json();
    } finally {
        libraryLoading.value = false;
    }
}
function openLibrary() {
    libraryOpen.value = true;
    librarySearch.value = "";
    searchLibrary();
}
function addFromLibrary(item) {
    const existing = taskList.value.find(
        (task) => String(task.id) === String(item.id),
    );
    if (existing) existing.checked = true;
    else
        taskList.value.push({
            id: item.id,
            title: item.title,
            max_points: item.max_points,
            competency: item.competency,
            competency_id: item.competency_id,
            education_plan_competency_id: item.education_plan_competency_id,
            levels: item.levels ?? [],
            edit_url: `/bibliothek/pruefungsaufgaben/${item.id}/bearbeiten`,
            checked: true,
            source: "manual",
        });
    syncTasks();
    libraryOpen.value = false;
}
function formatDate(value) {
    const parts = String(value ?? "")
        .slice(0, 10)
        .split("-");
    return parts.length === 3 && parts.every(Boolean)
        ? `${parts[2]}.${parts[1]}.${parts[0]}`
        : value;
}
function editTaskUrl(task) {
    if (!props.assessment) return task.edit_url
    const returnUrl = `/unterrichtsgruppen/${props.group.id}/lernstandserhebungen/${props.assessment.id}/bearbeiten?return_to=${encodeURIComponent(props.returnTo)}`
    return `${task.edit_url}?return_to=${encodeURIComponent(returnUrl)}`
}
const assessmentDate = computed(() => props.slot?.date || props.assessment?.assessed_on)
function save() {
    const url = props.assessment
        ? `/unterrichtsgruppen/${props.group.id}/lernstandserhebungen/${props.assessment.id}`
        : `/unterrichtsgruppen/${props.group.id}/lernstandserhebungen`;
    form[props.assessment ? "put" : "post"](url);
}
const activeTasks = computed(() =>
    taskList.value.filter((task) => task.checked),
);
const taskGroups = computed(() => {
    const groups = new Map();
    taskList.value.forEach((task) => {
        const key = task.competency_key ?? task.competency_id ?? task.education_plan_competency_id ?? (task.competency ? `text-${task.competency}` : "none");
        if (!groups.has(key))
            groups.set(key, {
                key,
                title: task.competency || de.noCompetency,
                tasks: [],
            });
        groups.get(key).tasks.push(task);
    });
    return [...groups.values()].map((group) => ({
        ...group,
        tasks: group.tasks.sort(
            (left, right) =>
                (left.date || "9999-12-31").localeCompare(
                    right.date || "9999-12-31",
                ) || left.title.localeCompare(right.title, "de"),
        ),
    }));
});
function taskHasLevel(task, level) {
    return !task.levels?.length || task.levels.includes(level);
}
const isDifferentiated = computed(() =>
    activeTasks.value.some((task) => task.levels?.length),
);
const pointsForLevel = (level) =>
    activeTasks.value.reduce((total, task) => {
        const levels = task.levels ?? [];
        return (
            total +
            (!levels.length || levels.includes(level)
                ? Number(task.max_points ?? 0)
                : 0)
        );
    }, 0);
const coveredCompetencies = computed(
    () =>
        new Set(
            activeTasks.value
                .map(
                    (task) =>
                        task.competency_key ?? task.competency_id ?? task.education_plan_competency_id,
                )
                .filter(Boolean),
        ).size,
);
const assessmentStats = computed(() => ({
    tasks: activeTasks.value.length,
    points: isDifferentiated.value
        ? {
              G: pointsForLevel("G"),
              M: pointsForLevel("M"),
              E: pointsForLevel("E"),
          }
        : pointsForLevel(),
    competencies: coveredCompetencies.value,
}));
syncTasks();
</script>
<template>
    <AppShell
        ><template #toolbar
            ><a
                :href="
                    returnTo === 'year-plan'
                        ? `/jahresplanung/${group.id}`
                        : `/unterrichtsgruppen/${group.id}?tab=${returnTab}`
                "
                class="btn btn-sm btn-light"
                title="Schließen"
                aria-label="Schließen"
                ><i class="bi bi-x-lg" aria-hidden="true"></i></a
            ><button
                class="btn btn-sm btn-primary ms-2"
                type="submit"
                form="assessment-form"
                :disabled="form.processing"
            >
                Speichern
            </button></template
        >
        <div class="container-full px-3 py-4">
            <h1 class="h2 mb-1">
                {{ assessment ? de.editAssessment : de.newAssessment }}
            </h1>
            <div v-if="assessmentDate" class="text-muted mb-4">
                {{ formatDate(assessmentDate)
                }}<span class="ms-3"
                    >{{ assessmentStats.tasks }} {{ de.assessmentStatsTasks }} ·
                    <template v-if="isDifferentiated"
                        >{{ de.assessmentStatsPoints }} G
                        {{ assessmentStats.points.G }}, M
                        {{ assessmentStats.points.M }}, E
                        {{ assessmentStats.points.E }}</template
                    ><template v-else
                        >{{ assessmentStats.points }}
                        {{ de.assessmentStatsPoints }}</template
                    >
                    · {{ assessmentStats.competencies }}
                    {{ de.assessmentStatsCompetencies }}</span
                >
            </div>
            <form
                id="assessment-form"
                class="card card-body"
                @submit.prevent="save"
            >
                <label class="form-label" for="assessment-title">{{
                    de.title
                }}</label
                ><input
                    id="assessment-title"
                    v-model="form.title"
                    class="form-control"
                    required
                /><label class="form-label mt-3" for="assessment-notes">{{
                    de.notes
                }}</label
                ><textarea
                    id="assessment-notes"
                    v-model="form.notes"
                    class="form-control"
                    rows="3"
                ></textarea>
                <section
                    class="mt-4"
                    aria-labelledby="assessment-tasks-heading"
                >
                    <div
                        class="d-flex justify-content-between align-items-center mb-2"
                    >
                        <h2 id="assessment-tasks-heading" class="h5 mb-0">
                            {{ de.assessmentTasks }}
                        </h2>
                        <button
                            class="btn btn-sm btn-outline-primary"
                            type="button"
                            @click="openLibrary"
                        >
                            <i
                                class="bi bi-collection me-1"
                                aria-hidden="true"
                            ></i
                            >{{ de.addFromLibrary }}
                        </button>
                    </div>
                    <p class="small text-muted">
                        {{ de.assessmentTasksWindowHint }}
                    </p>
                    <div v-if="taskList.length" class="list-group">
                        <template v-for="group in taskGroups" :key="group.key">
                            <h3 class="h6 mt-3 mb-0 px-3 py-2 bg-light">{{ group.title }}</h3>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0 align-middle assessment-task-table">
                                    <colgroup>
                                        <col>
                                        <col v-if="isDifferentiated" class="assessment-task-level-column">
                                        <col v-if="isDifferentiated" class="assessment-task-level-column">
                                        <col v-if="isDifferentiated" class="assessment-task-level-column">
                                        <col class="assessment-task-actions-column">
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th>{{ de.assessmentTasks }}</th>
                                            <th v-if="isDifferentiated" class="text-center">G</th>
                                            <th v-if="isDifferentiated" class="text-center">M</th>
                                            <th v-if="isDifferentiated" class="text-center">E</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="task in group.tasks" :key="task.id">
                                            <td>
                                                <div class="d-flex align-items-start gap-2">
                                                    <input :id="`assessment-task-${task.id}`" class="form-check-input mt-1" type="checkbox" :checked="task.checked" @change="toggleTask(task)">
                                                    <label :for="`assessment-task-${task.id}`" class="mb-0">
                                                        <strong>{{ task.title }}</strong>
                                                        <span class="d-block small text-muted">{{ task.max_points ?? 0 }} {{ de.assessmentTaskPoints }}<template v-if="task.date"> · {{ formatDate(task.date) }}</template></span>
                                                    </label>
                                                </div>
                                            </td>
                                            <td v-if="isDifferentiated" class="text-center"><i v-if="taskHasLevel(task, 'G')" class="bi bi-check-lg" aria-label="G"></i></td>
                                            <td v-if="isDifferentiated" class="text-center"><i v-if="taskHasLevel(task, 'M')" class="bi bi-check-lg" aria-label="M"></i></td>
                                            <td v-if="isDifferentiated" class="text-center"><i v-if="taskHasLevel(task, 'E')" class="bi bi-check-lg" aria-label="E"></i></td>
                                            <td class="text-end text-nowrap">
                                                <a class="btn btn-sm btn-outline-secondary" :href="editTaskUrl(task)" :title="de.edit" :aria-label="de.editAssessmentTask"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                                                <button v-if="task.source === 'manual'" class="btn btn-sm btn-outline-danger ms-1" type="button" :title="de.remove" :aria-label="de.removeAssessmentTask" @click="removeTask(task)"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </template>
                    </div>
                    <p v-else class="small text-muted mb-0">
                        {{ de.noAssessmentTasksInWindow }}
                    </p>
                </section>
            </form>
        </div>
        <div
            v-if="libraryOpen"
            class="roo-modal-backdrop"
            role="presentation"
            @click.self="libraryOpen = false"
        >
            <section
                class="roo-modal card border-0"
                role="dialog"
                aria-modal="true"
                :aria-label="de.addFromLibrary"
            >
                <div class="card-body">
                    <div
                        class="d-flex justify-content-between align-items-center mb-3"
                    >
                        <h2 class="h6 mb-0">{{ de.addFromLibrary }}</h2>
                        <button
                            class="btn-close"
                            type="button"
                            :aria-label="de.close"
                            @click="libraryOpen = false"
                        ></button>
                    </div>
                    <input
                        v-model="librarySearch"
                        class="form-control mb-3"
                        type="search"
                        :placeholder="de.searchAssessmentTasks"
                        @input="searchLibrary"
                    />
                    <div class="list-group library-picker-list">
                        <button
                            v-for="item in libraryItems"
                            :key="item.id"
                            class="list-group-item list-group-item-action text-start"
                            type="button"
                            @click="addFromLibrary(item)"
                        >
                            <strong>{{ item.title }}</strong
                            ><span class="d-block small text-muted">{{
                                item.description ||
                                item.competency ||
                                de.assessmentTaskFallback
                            }}</span>
                        </button>
                        <p
                            v-if="!libraryItems.length"
                            class="small text-muted mb-0"
                        >
                            {{
                                libraryLoading
                                    ? de.searching
                                    : de.noAssessmentTasksFound
                            }}
                        </p>
                    </div>
                </div>
            </section>
        </div></AppShell
    >
</template>
