<script setup>
import AppShell from "../../Components/Ui/AppShell.vue";
import { computed, ref } from "vue";
import { router, useForm } from "@inertiajs/vue3";
import { requestConfirmation } from "../../utils/confirmation";
import FluxImageGeneratorModal from "../../Components/Ui/FluxImageGeneratorModal.vue";
import ChordEditor from "../../Components/Songs/ChordEditor.vue";

const props = defineProps({
    songVersion: Object,
    isCreating: Boolean,
    songStyles: Object,
    libraryImages: Array,
    flux: { type: Object, default: () => ({}) },
});
const editorTab = ref("metadata"),
    editorVersion = ref(null),
    generatedSheetUrl = ref(null),
    activeImageId = ref(null),
    draggedPartIndex = ref(null),
    interaction = ref(null),
    libraryModal = ref(false),
    libraryQuery = ref(""),
    fluxModal = ref(false),
    toastMessage = ref("");
const canvas = { width: 420, height: 595.28 };
const form = useForm({
    title: "",
    composer: "",
    author: "",
    copyright_notice: "",
    age_group: "",
    topics: "",
    notes: "",
    version_name: "",
    lyrics: "",
    notation: "",
    chords: "",
    text_export_allowed: false,
    metadata_export_allowed: true,
    sheet: null,
});
const editor = useForm({
        song: {},
        name: "",
        language: "de",
        parts: [],
        chord_sets: [],
        layout_data: { images: [] },
    }),
    imageForm = useForm({ images: [], copyrights: "" }),
    libraryImageForm = useForm({
        resource: null,
        description: "",
        copyrights: "",
    });
const selectedImage = computed(
    () =>
        editor.layout_data.images?.find(
            (image) => image.id === activeImageId.value,
        ) ?? null,
);
const canvasImages = computed(() =>
    (editor.layout_data.images ?? []).filter((image) =>
        editorVersion.value?.images?.some((item) => item.id === image.id),
    ),
);
const filteredLibraryImages = computed(() =>
    (props.libraryImages ?? []).filter((image) =>
        image.original_name
            .toLowerCase()
            .includes(libraryQuery.value.toLowerCase()),
    ),
);
const songCredits = computed(() => {
    const author = (editor.song.author ?? "").trim(),
        composer = (editor.song.composer ?? "").trim(),
        copyright = (editor.song.copyright_notice ?? "").trim();
    let credit =
        author && composer && author.toLowerCase() === composer.toLowerCase()
            ? `Text & Musik: ${author}`
            : [author && `Text: ${author}`, composer && `Musik: ${composer}`]
                  .filter(Boolean)
                  .join(" / ");
    credit = copyright ? `${credit}${credit ? ". " : ""}${copyright}` : credit;
    const images = (editor.layout_data.images ?? [])
        .map((image) => (image.credits ?? "").trim())
        .filter(Boolean);
    return [
        credit,
        images.length
            ? `${images.length === 1 ? "Bild:" : "Bilder:"} ${images.join(" · ")}`
            : "",
    ]
        .filter(Boolean)
        .join("\n");
});
const imageCredits = computed(() =>
    (editor.layout_data.images ?? [])
        .map((image) => (image.credits ?? "").trim())
        .filter(Boolean),
);
const previewCredits = computed(() =>
    [
        songCredits.value,
        imageCredits.value.length
            ? `${imageCredits.value.length === 1 ? "Bild:" : "Bilder:"} ${imageCredits.value.join(" · ")}`
            : "",
    ]
        .filter(Boolean)
        .join("\n"),
);
const numberedParts = computed(() => {
    let previousNumber = 0;
    return editor.parts.map((part) => {
        if (!part.is_numbered) return null;
        const number = part.number || previousNumber + 1;
        previousNumber = number;
        return number;
    });
});
const fluxPrompt = computed(
    () =>
        `Erzeuge eine einfache, freundliche Schwarz-Weiß-Strichzeichnung auf rein weißem Hintergrund als Illustration zum folgenden Liedtext. Verwende ausschließlich schwarze Linien, keine Schattierungen und keine Farben. Verwende keinen Text, keine Buchstaben, keine Zahlen und keine Noten im Bild. Liedtext:\n\n${editor.parts.map((part) => part.content).join("\n\n")}`,
);
const previewStyles = computed(() => ({
    title: {
        fontFamily: props.songStyles?.title_font_family,
        fontSize: `${props.songStyles?.title_font_size ?? 24}px`,
        fontWeight: props.songStyles?.title_font_weight,
    },
    text: {
        fontFamily: props.songStyles?.text_font_family,
        fontSize: `${props.songStyles?.text_font_size ?? 14}px`,
        fontWeight: props.songStyles?.text_font_weight,
    },
    refrain: {
        fontFamily: props.songStyles?.refrain_font_family,
        fontSize: `${props.songStyles?.refrain_font_size ?? 14}px`,
        fontWeight: props.songStyles?.refrain_font_weight,
    },
}));
function generatedSheetUrlFor(
    version,
    format = "a5",
    cacheKey = (format === "a4"
        ? (version.generated_sheet_a4_path ?? version.generated_sheet_a4_at)
        : (version.generated_sheet_path ?? version.generated_sheet_at)) ??
        Date.now(),
) {
    const path =
        format === "a4"
            ? version.generated_sheet_a4_path
            : version.generated_sheet_path;
    return path
        ? `/lieder/fassungen/${version.id}/liedblatt/erzeugt${format === "a4" ? "/a4" : ""}?v=${encodeURIComponent(cacheKey)}`
        : null;
}
function imageUrl(imageId) {
    const image = editorVersion.value?.images?.find(
        (item) => item.id === imageId,
    );
    const cacheKey = image?.updated_at ?? image?.id ?? Date.now();
    return `/lieder/fassungen/${editorVersion.value.id}/bilder/${imageId}?v=${encodeURIComponent(cacheKey)}`;
}
function layoutForExistingImages(layoutData, images) {
    const imageIds = new Set((images ?? []).map((image) => image.id));
    return {
        ...(layoutData ?? {}),
        images: (layoutData?.images ?? []).filter((image) =>
            imageIds.has(image.id),
        ),
    };
}
function cleanLayoutData() {
    editor.layout_data = layoutForExistingImages(
        editor.layout_data,
        editorVersion.value?.images,
    );
    return editor.layout_data;
}
function openEditor(version) {
    editorVersion.value = version;
    editorTab.value = "metadata";
    activeImageId.value = null;
    generatedSheetUrl.value = generatedSheetUrlFor(version);
    editor.defaults({
        song: {
            title: version.song?.title ?? "",
            composer: version.song?.composer ?? "",
            author: version.song?.author ?? "",
            copyright_notice: version.song?.copyright_notice ?? "",
            age_group: version.song?.age_group ?? "",
            topics: version.song?.topics ?? "",
            notes: version.song?.notes ?? "",
        },
        name: version.name,
        language: version.language ?? "de",
        parts: (version.parts ?? []).map((part) => ({
            id: part.id,
            content: part.content,
            is_refrain: part.is_refrain,
            is_repeated: part.is_repeated ?? false,
            repeat_count: part.repeat_count ?? 2,
            is_numbered: part.is_numbered ?? false,
            number: part.number ?? null,
        })),
        chord_sets: (version.chord_sets ?? []).map((set) => ({
            id: set.id,
            instrument: set.instrument,
            name: set.name ?? "",
            key_signature: set.key_signature ?? "",
            chords: (set.chords ?? []).map((chord) => ({
                song_part_id: chord.song_part_id,
                line_number: chord.line_number,
                repetition: chord.repetition ?? 0,
                character_offset: chord.character_offset,
                chord: chord.chord,
            })),
        })),
        layout_data: layoutForExistingImages(
            version.layout_data,
            version.images,
        ),
    });
    editor.reset();
    imageForm.reset();
}
function refreshEditorVersion() {
    if (props.songVersion) {
        editorVersion.value = props.songVersion;
        cleanLayoutData();
    }
}
function saveEditor() {
    cleanLayoutData();
    if (props.isCreating) {
        form.transform(() => ({
            title: editor.song.title,
            composer: editor.song.composer,
            author: editor.song.author,
            copyright_notice: editor.song.copyright_notice,
            age_group: editor.song.age_group,
            topics: editor.song.topics,
            notes: editor.song.notes,
            version_name: editor.name,
            lyrics: editor.parts.map((part) => part.content).join("\\n\\n"),
        }));
        return form.post("/lieder", {
            preserveScroll: true,
            onSuccess: closeEditor,
        });
    }
    const chordSets = editor.chord_sets.map((set) => ({
        id: set.id ?? null,
        instrument: set.instrument ?? "",
        name: set.name ?? "",
        key_signature: set.key_signature ?? "",
        chords: (set.chords ?? []).map((chord) => ({
            song_part_id: chord.song_part_id,
            line_number: chord.line_number,
            repetition: chord.repetition ?? 0,
            character_offset: chord.character_offset,
            chord: chord.chord,
        })),
    }));
    editor.transform((data) => ({ ...data, chord_sets: chordSets }));
    editor.put(`/lieder/fassungen/${editorVersion.value.id}`, {
        preserveScroll: true,
        onSuccess: closeEditor,
    });
}
function uploadImages() {
    imageForm.post(`/lieder/fassungen/${editorVersion.value.id}/bilder`, {
        forceFormData: true,
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            refreshEditorVersion();
            imageForm.reset();
        },
        onError: (errors) =>
            window.alert(
                Object.values(errors)[0] ??
                    "Die Bilder konnten nicht hochgeladen werden.",
            ),
    });
}
function importLibraryImage(image) {
    router.post(
        `/lieder/fassungen/${editorVersion.value.id}/bilder/bibliothek`,
        { resource_id: image.id },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                refreshEditorVersion();
            },
        },
    );
}
function openFluxGenerator() {
    fluxModal.value = true;
}
function showToast(message) {
    toastMessage.value = message;
    window.setTimeout(() => {
        toastMessage.value = "";
    }, 5000);
}
function selectFluxImage(payload) {
    fluxModal.value = false;
    uploadGeneratedImage(payload);
}
function uploadGeneratedImage(payload) {
    const file = new File([payload.blob], payload.filename, {
        type: "image/png",
    });
    imageForm.images = [file];
    imageForm.copyrights = payload.credits;
    imageForm.post(`/lieder/fassungen/${editorVersion.value.id}/bilder`, {
        forceFormData: true,
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            refreshEditorVersion();
            const image = editorVersion.value.images.find(
                (item) => item.original_name === payload.filename,
            );
            if (image) {
                addImage(image);
                setImageCredits(image.id, payload.credits);
            }
            imageForm.reset();
        },
        onError: (errors) =>
            showToast(
                Object.values(errors)[0] ??
                    "Das Bild konnte nicht gespeichert werden.",
            ),
    });
}
function saveFluxImageToLibrary(payload) {
    libraryImageForm.resource = new File([payload.blob], payload.filename, {
        type: "image/png",
    });
    libraryImageForm.description = payload.description;
    libraryImageForm.copyrights = payload.copyrights;
    libraryImageForm.post("/ressourcen/bibliothek/dateien", {
        forceFormData: true,
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            libraryImageForm.reset();
            showToast("Bild wurde zur Bibliothek hinzugefügt.");
        },
        onError: (errors) =>
            showToast(
                Object.values(errors)[0] ??
                    "Das Bild konnte nicht zur Bibliothek hinzugefügt werden.",
            ),
    });
}
async function deleteImage(image) {
    if (
        await requestConfirmation({
            message: `„${image.original_name}“ wirklich löschen?`,
        })
    )
        router.delete(
            `/lieder/fassungen/${editorVersion.value.id}/bilder/${image.id}`,
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    editorVersion.value.images =
                        editorVersion.value.images.filter(
                            (item) => item.id !== image.id,
                        );
                    editor.layout_data.images =
                        editor.layout_data.images.filter(
                            (item) => item.id !== image.id,
                        );
                    if (activeImageId.value === image.id)
                        activeImageId.value = null;
                },
                onError: (errors) =>
                    window.alert(
                        Object.values(errors)[0] ??
                            "Das Bild konnte nicht gelöscht werden.",
                    ),
            },
        );
}
function layoutFor(imageId) {
    return editor.layout_data.images?.find((image) => image.id === imageId);
}
function addImage(image) {
    if (!layoutFor(image.id))
        editor.layout_data.images.push({
            id: image.id,
            x: 20,
            y: 20,
            width: 100,
            height: 100,
            rotation: 0,
            flipX: false,
            flipY: false,
            credits: "",
        });
    activeImageId.value = image.id;
}
function setImageCredits(imageId, value) {
    const layout = layoutFor(imageId);
    if (layout) layout.credits = value;
}
function flipImage(imageId, axis) {
    const layout = layoutFor(imageId);
    if (layout)
        layout[axis === "x" ? "flipX" : "flipY"] =
            !layout[axis === "x" ? "flipX" : "flipY"];
}
function generateSheet() {
    cleanLayoutData();
    editor.put(`/lieder/fassungen/${editorVersion.value.id}`, {
        preserveScroll: true,
        onSuccess: () =>
            router.post(
                `/lieder/fassungen/${editorVersion.value.id}/liedblatt/erzeugen`,
                {},
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        refreshEditorVersion();
                        generatedSheetUrl.value = generatedSheetUrlFor(
                            editorVersion.value,
                            Date.now(),
                        );
                    },
                    onError: (errors) =>
                        window.alert(
                            Object.values(errors)[0] ??
                                "Das A5-Liedblatt konnte nicht erzeugt werden.",
                        ),
                },
            ),
        onError: (errors) =>
            window.alert(
                Object.values(errors)[0] ??
                    "Das Lied konnte nicht gespeichert werden.",
            ),
    });
}
function addPart() {
    editor.parts.push({
        content: "",
        is_refrain: false,
        is_repeated: false,
        repeat_count: 2,
        is_numbered: false,
        number: null,
    });
}
function movePart(index, direction) {
    const target = index + direction;
    if (target < 0 || target >= editor.parts.length) return;
    const parts = [...editor.parts];
    [parts[index], parts[target]] = [parts[target], parts[index]];
    editor.parts = parts;
}
function startPartDrag(index, event) {
    draggedPartIndex.value = index;
    event.dataTransfer.effectAllowed = "move";
}
function dropPart(index) {
    if (draggedPartIndex.value === null || draggedPartIndex.value === index)
        return;
    const parts = [...editor.parts],
        [part] = parts.splice(draggedPartIndex.value, 1);
    parts.splice(index, 0, part);
    editor.parts = parts;
    draggedPartIndex.value = null;
}
function removePart(index) {
    editor.parts.splice(index, 1);
}
function imageStyle(image) {
    return {
        left: 0,
        top: 0,
        width: `${image.width}px`,
        height: `${image.height}px`,
        transform: `rotate(${image.rotation}deg) scale(${image.flipX ? -1 : 1}, ${image.flipY ? -1 : 1})`,
    };
}
function beginInteraction(type, event, image, corner = null) {
    event.preventDefault();
    activeImageId.value = image.id;

    const selection = event.currentTarget.closest(
        ".song-image-selection",
    );
    const selectionRect = selection?.getBoundingClientRect();
    const centerX = selectionRect
        ? selectionRect.left + selectionRect.width / 2
        : image.x + image.width / 2;
    const centerY = selectionRect
        ? selectionRect.top + selectionRect.height / 2
        : image.y + image.height / 2;

    interaction.value = {
        type,
        corner,
        image,
        startX: event.clientX,
        startY: event.clientY,
        x: image.x,
        y: image.y,
        width: image.width,
        height: image.height,
        rotation: image.rotation ?? 0,
        centerX,
        centerY,
        startAngle: Math.atan2(
            event.clientY - centerY,
            event.clientX - centerX,
        ),
    };
    window.addEventListener("pointermove", continueInteraction);
    window.addEventListener("pointerup", endInteraction, { once: true });
    window.addEventListener("pointercancel", endInteraction, { once: true });
}
function continueInteraction(event) {
    const state = interaction.value;
    if (!state) return;

    const dx = event.clientX - state.startX;
    const dy = event.clientY - state.startY;

    if (state.type === "move") {
        state.image.x = Math.max(
            0,
            Math.min(canvas.width - state.width, state.x + dx),
        );
        state.image.y = Math.max(
            0,
            Math.min(canvas.height - state.height, state.y + dy),
        );
        return;
    }

    if (state.type === "resize") {
        const west = state.corner.includes("w");
        const north = state.corner.includes("n");
        const width = Math.max(20, state.width + (west ? -dx : dx));
        const height = Math.max(20, state.height + (north ? -dy : dy));

        state.image.width = width;
        state.image.height = height;
        state.image.x = Math.max(
            0,
            Math.min(
                canvas.width - width,
                west ? state.x + state.width - width : state.x,
            ),
        );
        state.image.y = Math.max(
            0,
            Math.min(
                canvas.height - height,
                north ? state.y + state.height - height : state.y,
            ),
        );
        return;
    }

    const angle = Math.atan2(
        event.clientY - state.centerY,
        event.clientX - state.centerX,
    );
    state.image.rotation =
        state.rotation + ((angle - state.startAngle) * 180) / Math.PI;
}
function endInteraction() {
    interaction.value = null;
    window.removeEventListener("pointermove", continueInteraction);
    window.removeEventListener("pointerup", endInteraction);
    window.removeEventListener("pointercancel", endInteraction);
}
function removeSelectedImage() {
    if (selectedImage.value) {
        editor.layout_data = {
            ...editor.layout_data,
            images: editor.layout_data.images.filter(
                (image) => image.id !== selectedImage.value.id,
            ),
        };
        activeImageId.value = null;
    }
}
function handleImageKeydown(event) {
    if (
        (event.key === "Delete" || event.key === "Backspace") &&
        selectedImage.value
    ) {
        event.preventDefault();
        removeSelectedImage();
    }
}
window.addEventListener("keydown", handleImageKeydown);
if (props.songVersion) openEditor(props.songVersion);
else {
    editorVersion.value = { id: null, images: [] };
    editor.defaults({
        song: {
            title: "",
            composer: "",
            author: "",
            copyright_notice: "",
            age_group: "",
            topics: "",
            notes: "",
        },
        name: "Standardfassung",
        language: "de",
        parts: [],
        chord_sets: [],
        layout_data: { images: [] },
    });
    editor.reset();
}
function closeEditor() {
    router.visit("/bibliothek");
}
</script>

<template>
    <AppShell>
        <template #toolbar
            ><div class="d-flex gap-2">
                <button
                    class="btn btn-sm btn-light"
                    type="button"
                    title="Schließen"
                    aria-label="Schließen"
                    @click="closeEditor"
                >
                    <i class="bi bi-x-lg" aria-hidden="true"></i></button
                ><button
                    class="btn btn-sm btn-primary"
                    type="button"
                    :disabled="editor.processing || form.processing"
                    @click="saveEditor"
                >
                    <i class="bi bi-check-lg me-1" aria-hidden="true"></i
                    >Speichern
                </button>
            </div></template
        >
        <div v-if="editorVersion" class="container-full px-3 py-4">
            <section class="song-editor-page card border-0">
                <div class="card-body d-flex flex-column">
                    <div
                        class="d-flex justify-content-between align-items-center"
                    >
                        <h2 class="h5 mb-0">
                            {{
                                props.isCreating
                                    ? "Lied anlegen"
                                    : "Lied bearbeiten"
                            }}
                        </h2>
                    </div>
                    <ul class="nav nav-tabs mt-3 mb-3">
                        <li class="nav-item">
                            <button
                                class="nav-link"
                                :class="{ active: editorTab === 'metadata' }"
                                type="button"
                                @click="editorTab = 'metadata'"
                            >
                                Metadaten
                            </button>
                        </li>
                        <li class="nav-item">
                            <button
                                class="nav-link"
                                :class="{ active: editorTab === 'liedblatt' }"
                                type="button"
                                @click="editorTab = 'liedblatt'"
                            >
                                Liedblatt
                            </button>
                        </li>
                        <li class="nav-item">
                            <button
                                class="nav-link"
                                :class="{ active: editorTab === 'akkorde' }"
                                type="button"
                                @click="editorTab = 'akkorde'"
                            >
                                Akkorde
                            </button>
                        </li>
                    </ul>
                    <div
                        v-if="editorTab === 'metadata'"
                        class="row g-3 editor-tab-content"
                    >
                        <div class="col-md-8">
                            <label class="form-label">Titel</label
                            ><input
                                v-model="editor.song.title"
                                class="form-control"
                                required
                            />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Sprache</label
                            ><input
                                v-model="editor.language"
                                class="form-control"
                            />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Text</label
                            ><input
                                v-model="editor.song.author"
                                class="form-control"
                            />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Musik</label
                            ><input
                                v-model="editor.song.composer"
                                class="form-control"
                            />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fassungsname</label
                            ><input
                                v-model="editor.name"
                                class="form-control"
                                required
                            />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Altersgruppe</label
                            ><input
                                v-model="editor.song.age_group"
                                class="form-control"
                            />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Themen</label
                            ><input
                                v-model="editor.song.topics"
                                class="form-control"
                            />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Rechtehinweis</label
                            ><input
                                v-model="editor.song.copyright_notice"
                                class="form-control"
                            />
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notizen</label
                            ><textarea
                                v-model="editor.song.notes"
                                class="form-control"
                                rows="5"
                            ></textarea>
                        </div>
                    </div>
                    <div
                        v-else-if="editorTab === 'akkorde'"
                        class="editor-tab-content"
                    >
                        <ChordEditor
                            v-model="editor.chord_sets"
                            :parts="editor.parts"
                        />
                    </div>
                    <div
                        v-else
                        class="songbook-editor-grid row g-3 editor-tab-content overflow-hidden"
                    >
                        <div
                            class="col-lg-4 songbook-editor-column song-parts-scroll"
                        >
                            <div
                                class="d-flex justify-content-between align-items-center"
                            >
                                <h3 class="h6 mb-0">Liedteile</h3>
                                <button
                                    class="btn btn-sm btn-outline-primary"
                                    type="button"
                                    @click="addPart"
                                >
                                    Teil hinzufügen
                                </button>
                            </div>
                            <div
                                v-for="(part, index) in editor.parts"
                                :key="index"
                                class="border rounded p-2 mt-2"
                                draggable="true"
                                @dragstart="startPartDrag(index, $event)"
                                @dragover.prevent
                                @drop="dropPart(index)"
                            >
                                <div class="d-flex align-items-center gap-1">
                                    <i
                                        class="bi bi-grip-vertical text-muted"
                                    ></i
                                    ><label
                                        class="form-check small flex-grow-1 mb-0"
                                        ><input
                                            v-model="part.is_refrain"
                                            class="form-check-input"
                                            type="checkbox"
                                        />
                                        Kehrvers</label
                                    ><label class="form-check small mb-0"
                                        ><input
                                            v-model="part.is_numbered"
                                            class="form-check-input"
                                            type="checkbox"
                                        />
                                        Nummerieren</label
                                    ><input
                                        v-if="part.is_numbered"
                                        v-model.number="part.number"
                                        class="form-control form-control-sm song-part-number"
                                        type="number"
                                        min="1"
                                        placeholder="Nr."
                                    /><label class="form-check small mb-0"
                                        ><input
                                            v-model="part.is_repeated"
                                            class="form-check-input"
                                            type="checkbox"
                                        />
                                        Wiederholen</label
                                    ><input
                                        v-if="part.is_repeated"
                                        v-model.number="part.repeat_count"
                                        class="form-control form-control-sm song-part-number"
                                        type="number"
                                        min="2"
                                        placeholder="2x"
                                    /><button
                                        class="btn btn-sm btn-link p-0"
                                        type="button"
                                        :disabled="index === 0"
                                        @click="movePart(index, -1)"
                                    >
                                        <i class="bi bi-chevron-up"></i></button
                                    ><button
                                        class="btn btn-sm btn-link p-0"
                                        type="button"
                                        :disabled="
                                            index === editor.parts.length - 1
                                        "
                                        @click="movePart(index, 1)"
                                    >
                                        <i
                                            class="bi bi-chevron-down"
                                        ></i></button
                                    ><button
                                        class="btn btn-sm btn-link text-danger p-0"
                                        type="button"
                                        title="Teil löschen"
                                        @click="removePart(index)"
                                    >
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                                <textarea
                                    v-model="part.content"
                                    class="form-control mt-2"
                                    rows="6"
                                    placeholder="Text"
                                ></textarea>
                            </div>
                        </div>
                        <div
                            class="col-lg-5 songbook-editor-column song-preview-pane"
                        >
                            <h3 class="h6">A5-Vorschau</h3>
                            <div
                                class="song-canvas border bg-white position-relative"
                                @click="activeImageId = null"
                            >
                                <div class="p-3">
                                    <h4
                                        class="song-preview-title"
                                        :style="previewStyles.title"
                                    >
                                        {{ editor.song.title }}
                                    </h4>
                                    <p
                                        v-if="songCredits"
                                        class="song-preview-credits"
                                    >
                                        {{ songCredits }}
                                    </p>
                                    <section
                                        v-for="(part, index) in editor.parts"
                                        :key="index"
                                        class="song-preview-part"
                                        :style="
                                            part.is_refrain
                                                ? previewStyles.refrain
                                                : previewStyles.text
                                        "
                                    >
                                        {{
                                            numberedParts[index]
                                                ? `${numberedParts[index]}. `
                                                : ""
                                        }}{{ part.content
                                        }}{{
                                            part.is_repeated
                                                ? ` (${part.repeat_count || 2}x)`
                                                : ""
                                        }}
                                    </section>
                                </div>
                                <div class="song-hole-hints" aria-hidden="true">
                                    <span class="song-hole-hint"></span
                                    ><span class="song-hole-hint"></span>
                                </div>
                                <div
                                    v-for="image in canvasImages"
                                    :key="image.id"
                                    class="song-image-selection"
                                    :class="{
                                        selected: activeImageId === image.id,
                                    }"
                                    :style="{
                                        left: `${image.x}px`,
                                        top: `${image.y}px`,
                                        width: `${image.width}px`,
                                        height: `${image.height}px`,
                                    }"
                                    @pointerdown.stop="
                                        beginInteraction('move', $event, image)
                                    "
                                >
                                    <img
                                        :src="imageUrl(image.id)"
                                        :style="imageStyle(image)"
                                        alt=""
                                    /><button
                                        v-for="corner in [
                                            'nw',
                                            'ne',
                                            'sw',
                                            'se',
                                        ]"
                                        :key="corner"
                                        class="image-handle resize-handle"
                                        :class="corner"
                                        type="button"
                                        @pointerdown.stop="
                                            beginInteraction(
                                                'resize',
                                                $event,
                                                image,
                                                corner,
                                            )
                                        "
                                    ></button
                                    ><button
                                        class="image-handle rotate-handle"
                                        type="button"
                                        @pointerdown.stop="
                                            beginInteraction(
                                                'rotate',
                                                $event,
                                                image,
                                            )
                                        "
                                    ></button>
                                </div>
                                <div
                                    v-if="imageCredits.length"
                                    class="song-preview-image-credits"
                                >
                                    <strong>{{
                                        imageCredits.length === 1
                                            ? "Bild:"
                                            : "Bilder:"
                                    }}</strong>
                                    {{ imageCredits.join(" · ") }}
                                </div>
                            </div>
                        </div>
                        <div
                            class="col-lg-3 songbook-editor-column song-image-column"
                        >
                            <h3 class="h6">Bilder</h3>
                            <label class="form-label">Hochladen</label
                            ><input
                                class="form-control"
                                type="file"
                                accept="image/*"
                                multiple
                                @change="
                                    imageForm.images = [...$event.target.files]
                                "
                            />
                            <div class="d-flex gap-2 mt-2">
                                <button
                                    class="btn btn-sm btn-outline-secondary"
                                    type="button"
                                    :disabled="
                                        imageForm.processing ||
                                        !imageForm.images.length
                                    "
                                    @click="uploadImages"
                                >
                                    Bilder hochladen</button
                                ><button
                                    class="btn btn-sm btn-outline-primary"
                                    type="button"
                                    @click="libraryModal = true"
                                >
                                    <i class="bi bi-images me-1"></i>Bild aus
                                    Bibliothek wählen</button
                                ><button
                                    v-if="flux.enabled"
                                    class="btn btn-sm btn-outline-primary"
                                    type="button"
                                    @click="openFluxGenerator"
                                >
                                    <i class="bi bi-stars me-1"></i>Bild
                                    erzeugen
                                </button>
                            </div>
                            <h4 class="h6 mt-4">Hochgeladene Bilder</h4>
                            <div
                                v-for="image in editorVersion.images"
                                :key="image.id"
                                class="small mt-2"
                            >
                                <div class="d-flex gap-1">
                                    <button
                                        class="btn btn-sm btn-outline-secondary flex-grow-1 text-start"
                                        type="button"
                                        @click="addImage(image)"
                                    >
                                        {{ image.original_name }}</button
                                    ><button
                                        class="btn btn-sm btn-outline-danger"
                                        type="button"
                                        title="Bild löschen"
                                        @click="deleteImage(image)"
                                    >
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                                <template v-if="layoutFor(image.id)"
                                    ><input
                                        class="form-control form-control-sm mt-1"
                                        :value="layoutFor(image.id).credits"
                                        placeholder="Bild-Credits"
                                        @input="
                                            setImageCredits(
                                                image.id,
                                                $event.target.value,
                                            )
                                        "
                                    />
                                    <div class="d-flex gap-1 mt-1">
                                        <button
                                            class="btn btn-sm btn-outline-secondary"
                                            type="button"
                                            @click="flipImage(image.id, 'x')"
                                        >
                                            ↔ Spiegeln</button
                                        ><button
                                            class="btn btn-sm btn-outline-secondary"
                                            type="button"
                                            @click="flipImage(image.id, 'y')"
                                        >
                                            ↕ Spiegeln
                                        </button>
                                    </div></template
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <button
            v-if="editorVersion && editorTab === 'liedblatt' && selectedImage"
            class="song-image-remove-launcher btn btn-danger btn-sm"
            type="button"
            @click="removeSelectedImage"
        >
            <i class="bi bi-trash me-1"></i>Bild aus Vorschau entfernen
        </button>
        <div
            v-if="libraryModal"
            class="roo-modal-backdrop roo-library-modal-backdrop"
            @click.self="libraryModal = false"
        >
            <section class="roo-modal card roo-library-image-modal">
                <div class="card-body">
                    <div
                        class="d-flex justify-content-between align-items-center"
                    >
                        <h2 class="h5 mb-0">Bild aus Bibliothek wählen</h2>
                        <button
                            class="btn-close"
                            type="button"
                            @click="libraryModal = false"
                        ></button>
                    </div>
                    <input
                        v-model="libraryQuery"
                        class="form-control mt-3"
                        placeholder="Bilder suchen"
                    />
                    <div class="list-group mt-3">
                        <div
                            v-for="image in filteredLibraryImages"
                            :key="image.id"
                            class="list-group-item d-flex align-items-center gap-2"
                        >
                            <span class="flex-grow-1 text-truncate">{{
                                image.original_name
                            }}</span
                            ><button
                                class="btn btn-sm btn-primary"
                                type="button"
                                @click="importLibraryImage(image)"
                            >
                                Übernehmen
                            </button>
                        </div>
                        <p
                            v-if="!filteredLibraryImages.length"
                            class="small text-muted mt-2"
                        >
                            Keine passenden Bilder gefunden.
                        </p>
                    </div>
                </div>
            </section>
        </div>
        <FluxImageGeneratorModal
            :open="fluxModal"
            :default-prompt="fluxPrompt"
            :default-remove-white="true"
            :output-locked="true"
            :user-name="flux.userName"
            :models="flux.models"
            @close="fluxModal = false"
            @selected="selectFluxImage"
            @library="saveFluxImageToLibrary"
            @toast="showToast"
        />
        <div
            v-if="toastMessage"
            class="toast-container position-fixed bottom-0 end-0 p-3"
            style="z-index: 1300"
            role="status"
        >
            <div class="toast show text-bg-success">
                <div class="toast-body">{{ toastMessage }}</div>
            </div>
        </div>
    </AppShell>
</template>
