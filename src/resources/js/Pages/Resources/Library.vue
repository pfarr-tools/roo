<script setup>
import AppShell from "../../Components/Ui/AppShell.vue";
import CompetencyPickerModal from "../../Components/Planning/CompetencyPickerModal.vue";
import de from "../../i18n/de";
import { router, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { requestConfirmation } from "../../utils/confirmation";

const props = defineProps({
    items: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    counts: { type: Object, default: () => ({}) },
    competencies: { type: Array, default: () => [] },
    educationPlans: { type: Array, default: () => [] },
});
const search = ref(props.filters.q ?? "");
const type = ref(props.filters.type ?? "all");
const sort = ref(props.filters.sort ?? "name");
const direction = ref(props.filters.direction ?? "asc");
const modal = ref(null);
const newMenuOpen = ref(false);
const editing = ref(null);
const competencyPickerOpen = ref(false);
const selectedTaskCompetencyText = ref("");
const selectedTaskCompetencyDifferentiated = ref(false);
const preview = ref(null);
const fileForm = useForm({ resource: null, description: "", copyrights: "" });
const resourceForm = useForm({ title: "", url: "", description: "" });
const materialForm = useForm({
    name: "",
    material_number: "",
    storage_location: "",
    description: "",
    image: null,
});
const assessmentTaskForm = useForm({
    title: "",
    solution: "",
    max_points: "",
    competency_id: "",
    levels: [],
});
const editForm = useForm({
    description: "",
    copyrights: "",
    title: "",
    url: "",
    name: "",
    material_number: "",
    storage_location: "",
    image: null,
    solution: "",
    max_points: "",
    competency_id: "",
    education_plan_id: "",
    education_plan_competency_id: "",
    levels: [],
});
const options = [
    { value: "all", label: "Alle" },
    { value: "file", label: "Dateien" },
    { value: "resource", label: "Ressourcen" },
    { value: "material", label: "Material" },
    { value: "song", label: "Lieder" },
    { value: "assessment-task", label: "Prüfungsaufgaben" },
];
const visibleItems = computed(() => props.items);
function load() {
    router.get(
        "/bibliothek",
        {
            q: search.value,
            type: type.value,
            sort: sort.value,
            direction: direction.value,
        },
        { preserveState: true, replace: true },
    );
}
function changeSort(value) {
    if (sort.value === value)
        direction.value = direction.value === "asc" ? "desc" : "asc";
    else {
        sort.value = value;
        direction.value = "asc";
    }
    load();
}
function label(item) {
    return item.name || item.title || item.original_name;
}
function kindLabel(kind) {
    return kind === "file"
        ? "Datei"
        : kind === "resource"
          ? "Ressource"
          : kind === "song"
            ? "Lied"
            : kind === "assessment-task"
              ? "Prüfungsaufgabe"
              : "Material";
}
function icon(item) {
    return item.kind === "file"
        ? "bi-file-earmark"
        : item.kind === "resource"
          ? "bi-link-45deg"
          : item.kind === "song"
            ? "bi-music-note-beamed"
            : item.kind === "assessment-task"
              ? "bi-clipboard-check"
              : "bi-box-seam";
}
function size(bytes) {
    return !bytes
        ? ""
        : bytes < 1048576
          ? `${Math.max(1, Math.round(bytes / 1024))} KB`
          : `${(bytes / 1048576).toFixed(1)} MB`;
}
function openAdd(kind) {
    newMenuOpen.value = false;
    if (kind === "song") return window.location.assign("/bibliothek/lied/neu");
    modal.value = kind;
}
function submitAdd() {
    const form =
        modal.value === "file"
            ? fileForm
            : modal.value === "resource"
              ? resourceForm
              : modal.value === "material"
                ? materialForm
                : assessmentTaskForm;
    const url =
        modal.value === "file"
            ? "/ressourcen/bibliothek/dateien"
            : modal.value === "resource"
              ? "/ressourcen/bibliothek/ressourcen"
              : modal.value === "material"
                ? "/ressourcen/bibliothek/materialien"
                : "/ressourcen/bibliothek/pruefungsaufgaben";
    form.post(url, {
        forceFormData: modal.value === "file" || modal.value === "material",
        onSuccess: () => {
            modal.value = null;
            form.reset();
        },
    });
}
function songUrl(item, format) {
    return `/lieder/fassungen/${item.id}/liedblatt/erzeugt${format === "a4" ? "/a4" : ""}`;
}
function chordSongUrl(item, instrument) {
    return `/lieder/fassungen/${item.id}/liedblatt/erzeugt/akkord/${encodeURIComponent(instrument)}`;
}
function openEdit(item) {
    editing.value = item;
    competencyPickerOpen.value = false;
    selectedTaskCompetencyText.value = item.competency ?? "";
    selectedTaskCompetencyDifferentiated.value = item.has_differentiation ?? false;
    editForm.defaults({
        description: item.description ?? "",
        copyrights: item.copyrights ?? "",
        title: item.title ?? "",
        url: item.url ?? "",
        name: item.name ?? "",
        material_number: item.material_number ?? "",
        storage_location: item.storage_location ?? "",
        image: null,
        solution: item.solution ?? "",
        max_points: item.max_points ?? "",
        competency_id: item.competency_id ?? "",
        education_plan_id: item.education_plan_id ?? "",
        education_plan_competency_id: item.education_plan_competency_id ?? "",
        levels: item.levels ?? [],
    });
    editForm.reset();
    editForm.clearErrors();
}
function competencyText(competency) {
    if (competency.competency_presentation?.label) {
        return competency.competency_presentation.label;
    }

    const number = competency.external_identifier || competency.number;
    const text = competency.text || competency.display || competency.local_wording;

    return [number, text].filter(Boolean).join(" – ") || `Kompetenz ${competency.id}`;
}
function applyTaskCompetency(ids, selectedCompetencies = []) {
    editForm.education_plan_competency_id = ids[0] ?? "";
    selectedTaskCompetencyDifferentiated.value = selectedCompetencies[0]?.has_differentiation ?? false;
    selectedTaskCompetencyText.value = selectedCompetencies[0]
        ? competencyText(selectedCompetencies[0])
        : "";
}
function taskPickerEndpoint() {
    return editForm.education_plan_id
        ? `/ressourcen/bibliothek/bildungsplaene/${editForm.education_plan_id}/kompetenzen`
        : "/ressourcen/bibliothek/bildungsplaene/0/kompetenzen";
}
function uploadMaterialImage(item, file) {
    if (!file) return;
    const form = useForm({ image: file });
    form.post(`/ressourcen/bibliothek/materialien/${item.id}/bild`, {
        forceFormData: true,
        onSuccess: () => {
            item.image_url = `/ressourcen/bibliothek/materialien/${item.id}/bild`;
        },
    });
}
function saveEdit() {
    editForm.put(
        `/ressourcen/bibliothek/${editing.value.kind}/${editing.value.id}`,
        {
            onSuccess: () => {
                editing.value = null;
            },
        },
    );
}
async function remove(item) {
    if (
        await requestConfirmation({
            message: "Diesen Bibliothekseintrag wirklich löschen?",
        })
    )
        router.delete(
            item.kind === "song"
                ? `/lieder/${item.song_id}`
                : `/ressourcen/bibliothek/${item.kind}/${item.id}`,
            {
                onError: (errors) =>
                    window.alert(
                        Object.values(errors)[0] ??
                            "Der Eintrag konnte nicht gelöscht werden.",
                    ),
            },
        );
}
function previewable(item) {
    return (
        (item.kind === "file" &&
            (item.mime_type?.startsWith("image/") ||
                item.mime_type?.startsWith("video/") ||
                item.mime_type?.startsWith("audio/"))) ||
        (item.kind === "material" && item.image_url)
    );
}
</script>

<template>
    <AppShell>
        <template #toolbar>
            <div class="dropdown">
                <button
                    class="btn btn-sm btn-primary dropdown-toggle"
                    type="button"
                    :aria-expanded="newMenuOpen"
                    @click="newMenuOpen = !newMenuOpen"
                >
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Neues
                    Element
                </button>
                <div v-if="newMenuOpen" class="dropdown-menu show">
                    <button
                        class="dropdown-item"
                        type="button"
                        @click="openAdd('file')"
                    >
                        <i class="bi bi-upload me-2" aria-hidden="true"></i
                        >{{ de.addFile }}
                    </button>
                    <button
                        class="dropdown-item"
                        type="button"
                        @click="openAdd('resource')"
                    >
                        <i class="bi bi-link-45deg me-2" aria-hidden="true"></i
                        >{{ de.addResource }}
                    </button>
                    <button
                        class="dropdown-item"
                        type="button"
                        @click="openAdd('material')"
                    >
                        <i class="bi bi-box-seam me-2" aria-hidden="true"></i
                        >{{ de.addMaterial }}
                    </button>
                    <button
                        class="dropdown-item"
                        type="button"
                        @click="openAdd('assessment-task')"
                    >
                        <i
                            class="bi bi-clipboard-check me-2"
                            aria-hidden="true"
                        ></i
                        >Prüfungsaufgabe
                    </button>
                    <button
                        class="dropdown-item"
                        type="button"
                        @click="openAdd('song')"
                    >
                        <i
                            class="bi bi-music-note-beamed me-2"
                            aria-hidden="true"
                        ></i
                        >Lied anlegen
                    </button>
                </div>
            </div>
        </template>
        <div class="container-full px-3 py-4">
            <div
                class="d-flex justify-content-between align-items-start gap-3 mb-4"
            >
                <div>
                    <h1 class="h2 mb-1">Bibliothek</h1>
                    <p class="text-muted mb-0">
                        Dateien, Ressourcen und Material an einem Ort verwalten.
                    </p>
                </div>
                <span class="badge text-bg-light"
                    >{{ visibleItems.length }} Einträge</span
                >
            </div>
            <form class="row g-2 mb-3" role="search" @submit.prevent="load">
                <div class="col-md-6 col-xl-4">
                    <label class="visually-hidden" for="library-search"
                        >Bibliothek durchsuchen</label
                    ><input
                        id="library-search"
                        v-model="search"
                        class="form-control"
                        type="search"
                        placeholder="Bibliothek durchsuchen"
                    />
                </div>
                <div class="col-md-4 col-xl-3">
                    <select
                        v-model="type"
                        class="form-select"
                        aria-label="Typ filtern"
                        @change="load"
                    >
                        <option
                            v-for="option in options"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-secondary" type="submit">
                        <i class="bi bi-search me-1" aria-hidden="true"></i
                        >{{ de.filter }}
                    </button>
                </div>
            </form>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <button
                    v-for="option in options"
                    :key="option.value"
                    class="btn btn-sm"
                    :class="
                        type === option.value
                            ? 'btn-primary'
                            : 'btn-outline-secondary'
                    "
                    type="button"
                    @click="
                        type = option.value;
                        load();
                    "
                >
                    {{ option.label }}
                    <span class="badge rounded-pill text-bg-light">{{
                        option.value === "all"
                            ? (counts.total ?? 0)
                            : (counts[option.value] ?? 0)
                    }}</span>
                </button>
            </div>
            <div class="table-responsive bg-white rounded shadow-sm">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>
                                <button
                                    class="btn btn-link p-0 text-decoration-none"
                                    type="button"
                                    @click="changeSort('name')"
                                >
                                    Name
                                    <i
                                        v-if="sort === 'name'"
                                        :class="
                                            direction === 'asc'
                                                ? 'bi bi-chevron-up'
                                                : 'bi bi-chevron-down'
                                        "
                                        aria-hidden="true"
                                    ></i>
                                </button>
                            </th>
                            <th>Typ</th>
                            <th>Beziehungen</th>
                            <th>Details</th>
                            <th>
                                <button
                                    class="btn btn-link p-0 text-decoration-none"
                                    type="button"
                                    @click="changeSort('created_at')"
                                >
                                    Hinzugefügt
                                    <i
                                        v-if="sort === 'created_at'"
                                        :class="
                                            direction === 'asc'
                                                ? 'bi bi-chevron-up'
                                                : 'bi bi-chevron-down'
                                        "
                                        aria-hidden="true"
                                    ></i>
                                </button>
                            </th>
                            <th class="text-end">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in visibleItems"
                            :key="`${item.kind}-${item.id}`"
                        >
                            <td>
                                <div class="d-flex gap-2">
                                    <i
                                        :class="[
                                            'bi',
                                            icon(item),
                                            'text-primary',
                                            'fs-5',
                                        ]"
                                        aria-hidden="true"
                                    ></i>
                                    <div>
                                        <strong class="text-break">{{
                                            label(item)
                                        }}</strong>
                                        <div
                                            v-if="item.description"
                                            class="small text-muted text-pre-wrap"
                                        >
                                            {{ item.description }}
                                        </div>
                                        <a
                                            v-if="item.kind === 'resource'"
                                            class="small d-block text-break"
                                            :href="item.url"
                                            target="_blank"
                                            rel="noreferrer"
                                            >{{ item.url }}</a
                                        >
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge text-bg-light">{{
                                    kindLabel(item.kind)
                                }}</span>
                            </td>
                            <td>
                                <span
                                    v-if="item.relationships?.length"
                                    class="small"
                                    >{{ item.relationships.join(" · ") }}</span
                                ><span v-else class="small text-muted"
                                    >Nicht zugeordnet</span
                                >
                            </td>
                            <td class="small text-muted">
                                {{
                                    item.kind === "file"
                                        ? `${item.mime_type || "Datei"}${size(item.size) ? " · " + size(item.size) : ""}`
                                        : item.kind === "material"
                                          ? [
                                                item.material_number,
                                                item.storage_location,
                                            ]
                                                .filter(Boolean)
                                                .join(" · ")
                                          : item.kind === "assessment-task"
                                            ? `Niveaus: ${(item.levels || []).join(", ") || "keines"}`
                                            : ""
                                }}
                            </td>
                            <td class="small text-muted">
                                {{
                                    item.created_at
                                        ? new Date(
                                              item.created_at,
                                          ).toLocaleDateString("de-DE")
                                        : ""
                                }}
                            </td>
                            <td>
                                <div class="d-flex justify-content-end gap-1">
                                    <button
                                        v-if="previewable(item)"
                                        class="btn btn-sm btn-outline-primary"
                                        type="button"
                                        title="Vorschau"
                                        @click="preview = item"
                                    >
                                        <i
                                            class="bi bi-eye"
                                            aria-hidden="true"
                                        ></i></button
                                    ><a
                                        v-if="
                                            item.kind === 'song' &&
                                            item.generated_sheet_path
                                        "
                                        class="btn btn-sm btn-outline-success"
                                        :href="songUrl(item, 'a5')"
                                        download
                                        title="A5-Lied herunterladen"
                                        >A5</a
                                    ><a
                                        v-if="
                                            item.kind === 'song' &&
                                            item.generated_sheet_a4_path
                                        "
                                        class="btn btn-sm btn-outline-success"
                                        :href="songUrl(item, 'a4')"
                                        download
                                        title="A4-Lied herunterladen"
                                        >A4</a
                                    ><a
                                        v-for="instrument in item.kind ===
                                        'song'
                                            ? Object.keys(
                                                  item.generated_chord_sheet_paths ||
                                                      {},
                                              )
                                            : []"
                                        :key="instrument"
                                        class="btn btn-sm btn-outline-success"
                                        :href="chordSongUrl(item, instrument)"
                                        download
                                        :title="`Akkordblatt ${instrument}`"
                                        >{{ instrument }}</a
                                    ><a
                                        v-if="item.kind === 'file'"
                                        class="btn btn-sm btn-outline-secondary"
                                        :href="`/ressourcen/bibliothek/dateien/${item.id}/download`"
                                        title="Herunterladen"
                                        ><i
                                            class="bi bi-download"
                                            aria-hidden="true"
                                        ></i></a
                                    ><a
                                        v-if="item.kind === 'song'"
                                        class="btn btn-sm btn-outline-secondary"
                                        :href="`/bibliothek/lied/${item.id}`"
                                        title="Lied bearbeiten"
                                        ><i
                                            class="bi bi-pencil"
                                            aria-hidden="true"
                                        ></i></a
                                    ><button
                                        v-if="item.kind !== 'song'"
                                        class="btn btn-sm btn-outline-secondary"
                                        type="button"
                                        title="Bearbeiten"
                                        @click="openEdit(item)"
                                    >
                                        <i
                                            class="bi bi-pencil"
                                            aria-hidden="true"
                                        ></i></button
                                    ><button
                                        v-if="
                                            item.kind !== 'song' ||
                                            item.can_delete
                                        "
                                        class="btn btn-sm btn-outline-danger"
                                        type="button"
                                        title="Löschen"
                                        @click="remove(item)"
                                    >
                                        <i
                                            class="bi bi-trash"
                                            aria-hidden="true"
                                        ></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!visibleItems.length">
                            <td colspan="6" class="text-center text-muted py-5">
                                Keine passenden Einträge gefunden.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div
            v-if="modal"
            class="roo-modal-backdrop"
            role="presentation"
            @click.self="modal = null"
        >
            <form class="roo-modal card border-0" @submit.prevent="submitAdd">
                <div class="card-body">
                    <div
                        class="d-flex justify-content-between align-items-center mb-3"
                    >
                        <h2 class="h5 mb-0">
                            {{
                                modal === "file"
                                    ? de.addFile
                                    : modal === "resource"
                                      ? de.addResource
                                      : modal === "material"
                                        ? de.addMaterial
                                        : "Prüfungsaufgabe"
                            }}
                        </h2>
                        <button
                            class="btn-close"
                            type="button"
                            :aria-label="de.close"
                            @click="modal = null"
                        ></button>
                    </div>
                    <template v-if="modal === 'file'"
                        ><label class="form-label">{{ de.chooseFile }}</label
                        ><input
                            class="form-control"
                            type="file"
                            required
                            @change="
                                fileForm.resource =
                                    $event.target.files?.[0] ?? null
                            "
                        /><label class="form-label mt-3">{{
                            de.description
                        }}</label
                        ><textarea
                            v-model="fileForm.description"
                            class="form-control"
                            rows="3"
                        ></textarea
                        ><label class="form-label mt-3">Copyrights</label
                        ><textarea
                            v-model="fileForm.copyrights"
                            class="form-control"
                            rows="2"
                        ></textarea></template
                    ><template v-else-if="modal === 'resource'"
                        ><label class="form-label">{{ de.resourceTitle }}</label
                        ><input
                            v-model="resourceForm.title"
                            class="form-control"
                            required
                        /><label class="form-label mt-3">{{
                            de.resourceUrl
                        }}</label
                        ><input
                            v-model="resourceForm.url"
                            class="form-control"
                            type="url"
                            required
                        /><label class="form-label mt-3">{{
                            de.description
                        }}</label
                        ><textarea
                            v-model="resourceForm.description"
                            class="form-control"
                            rows="3"
                        ></textarea></template
                    ><template v-else-if="modal === 'material'"
                        ><label class="form-label">{{ de.materialItem }}</label
                        ><input
                            v-model="materialForm.name"
                            class="form-control"
                            required
                        />
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label mt-3"
                                    >Materialnummer</label
                                ><input
                                    v-model="materialForm.material_number"
                                    class="form-control"
                                />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label mt-3">Lagerort</label
                                ><input
                                    v-model="materialForm.storage_location"
                                    class="form-control"
                                />
                            </div>
                        </div>
                        <label class="form-label mt-3">{{
                            de.description
                        }}</label
                        ><textarea
                            v-model="materialForm.description"
                            class="form-control"
                            rows="3"
                        ></textarea></template
                    ><template v-else
                        ><label class="form-label">Aufgabe</label
                        ><input
                            v-model="assessmentTaskForm.title"
                            class="form-control"
                            required /><label class="form-label mt-3"
                            >Lösung / Erwartungshorizont</label
                        ><textarea
                            v-model="assessmentTaskForm.solution"
                            class="form-control"
                            rows="3"
                        ></textarea
                        ><label class="form-label mt-3">Kompetenz</label
                        ><select
                            v-model="assessmentTaskForm.competency_id"
                            class="form-select"
                            required
                        >
                            <option value="">Bitte wählen</option>
                            <option
                                v-for="competency in competencies"
                                :key="competency.id"
                                :value="competency.id"
                            >
                                {{ competency.label
                                }}{{
                                    competency.unit
                                        ? ` · ${competency.unit}`
                                        : ""
                                }}
                            </option></select
                        ><label class="form-label mt-3"
                            >G/M/E-Niveaus (optional)</label
                        >
                        <div class="d-flex gap-3">
                            <label
                                v-for="level in ['G', 'M', 'E']"
                                :key="level"
                                class="form-check"
                                ><input
                                    v-model="assessmentTaskForm.levels"
                                    class="form-check-input"
                                    type="checkbox"
                                    :value="level"
                                /><span class="form-check-label">{{
                                    level
                                }}</span></label
                            >
                        </div>
                        <label class="form-label mt-3">Max. Punkte</label
                        ><input
                            v-model="assessmentTaskForm.max_points"
                            class="form-control"
                            type="number"
                            min="1" /></template
                    ><button class="btn btn-primary mt-3" type="submit">
                        {{ de.saveChanges }}
                    </button>
                </div>
            </form>
        </div>
        <div
            v-if="editing"
            class="roo-modal-backdrop"
            role="presentation"
            @click.self="editing = null"
        >
            <form class="roo-modal card border-0" @submit.prevent="saveEdit">
                <div class="card-body">
                    <h2 class="h5">Bearbeiten</h2>
                    <template v-if="editing.kind === 'file'"
                        ><label class="form-label">{{ de.description }}</label
                        ><textarea
                            v-model="editForm.description"
                            class="form-control"
                            rows="4"
                        ></textarea
                        ><label class="form-label mt-3">Copyrights</label
                        ><textarea
                            v-model="editForm.copyrights"
                            class="form-control"
                            rows="2"
                        ></textarea></template
                    ><template v-else-if="editing.kind === 'resource'"
                        ><label class="form-label">{{ de.resourceTitle }}</label
                        ><input
                            v-model="editForm.title"
                            class="form-control"
                            required
                        /><label class="form-label mt-3">{{
                            de.resourceUrl
                        }}</label
                        ><input
                            v-model="editForm.url"
                            class="form-control"
                            type="url"
                            required
                        /><label class="form-label mt-3">{{
                            de.description
                        }}</label
                        ><textarea
                            v-model="editForm.description"
                            class="form-control"
                            rows="3"
                        ></textarea></template
                    ><template v-else-if="editing.kind === 'assessment-task'"
                        ><label class="form-label">Aufgabe</label
                        ><input
                            v-model="editForm.title"
                            class="form-control"
                            required
                        /><label class="form-label mt-3">Bildungsplan</label>
                        ><select v-model="editForm.education_plan_id" class="form-select" required @change="editForm.education_plan_competency_id = ''; selectedTaskCompetencyText = ''; selectedTaskCompetencyDifferentiated = false"><option value="">Bitte wählen</option><option v-for="plan in educationPlans" :key="plan.id" :value="plan.id">{{ plan.title }}</option></select
                        ><label class="form-label mt-3">Kompetenz</label
                        ><div class="input-group"><input class="form-control" :value="selectedTaskCompetencyText" placeholder="Bitte auswählen" readonly required><button class="btn btn-outline-primary" type="button" :disabled="!editForm.education_plan_id" @click="competencyPickerOpen = true">Kompetenz auswählen</button></div
                        ><label class="form-label mt-3"
                            >Lösung / Erwartungshorizont</label
                        ><textarea
                            v-model="editForm.solution"
                            class="form-control"
                            rows="3"
                        ></textarea
                        ><label class="form-label mt-3">Max. Punkte</label
                        ><input
                            v-model="editForm.max_points"
                            class="form-control"
                            type="number"
                            min="1"
                        /><template v-if="selectedTaskCompetencyDifferentiated"><label class="form-label mt-3">G/M/E-Niveaus</label>
                        <div class="d-flex gap-3">
                            <label
                                v-for="level in ['G', 'M', 'E']"
                                :key="level"
                                class="form-check"
                                ><input
                                    v-model="editForm.levels"
                                    class="form-check-input"
                                    type="checkbox"
                                    :value="level"
                                /><span class="form-check-label">{{
                                    level
                                }}</span></label
                            >
                        </div></template></template
                    ><template v-else
                        ><label class="form-label">{{ de.materialItem }}</label
                        ><input
                            v-model="editForm.name"
                            class="form-control"
                            required
                        /><label class="form-label mt-3">Materialnummer</label
                        ><input
                            v-model="editForm.material_number"
                            class="form-control"
                        /><label class="form-label mt-3">Lagerort</label
                        ><input
                            v-model="editForm.storage_location"
                            class="form-control"
                        /><label class="form-label mt-3">{{
                            de.description
                        }}</label
                        ><textarea
                            v-model="editForm.description"
                            class="form-control"
                            rows="3"
                        ></textarea></template
                    ><button class="btn btn-primary mt-3" type="submit">
                        {{ de.saveChanges }}
                    </button>
                </div>
            </form>
        </div>
        <CompetencyPickerModal
            v-if="editing?.kind === 'assessment-task'"
            v-model="competencyPickerOpen"
            :competencies="[]"
            :selected-ids="
                editForm.education_plan_competency_id
                    ? [editForm.education_plan_competency_id]
                    : []
            "
            :competency-text="competencyText"
            :endpoint="taskPickerEndpoint()"
            :exclude-process-competencies="true"
            :single="true"
            @apply="applyTaskCompetency"
        />
        <div
            v-if="preview"
            class="roo-modal-backdrop"
            role="presentation"
            @click.self="preview = null"
        >
            <section class="roo-modal card border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <h2 class="h5">{{ label(preview) }}</h2>
                        <button
                            class="btn-close"
                            type="button"
                            @click="preview = null"
                        ></button>
                    </div>
                    <img
                        v-if="preview.mime_type?.startsWith('image/')"
                        class="attachment-preview-image"
                        :src="`/ressourcen/bibliothek/dateien/${preview.id}/preview`"
                        :alt="label(preview)"
                    /><video
                        v-else-if="preview.mime_type?.startsWith('video/')"
                        class="attachment-preview-media"
                        controls
                        :src="`/ressourcen/bibliothek/dateien/${preview.id}/preview`"
                    ></video
                    ><audio
                        v-else
                        class="w-100"
                        controls
                        :src="`/ressourcen/bibliothek/dateien/${preview.id}/preview`"
                    ></audio>
                </div>
            </section>
        </div>
    </AppShell>
</template>
