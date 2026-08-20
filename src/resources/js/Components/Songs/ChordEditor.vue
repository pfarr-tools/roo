<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    parts: { type: Array, default: () => [] },
});
const keySignatures = [
    { value: "C-Dur", label: "C-Dur" },
    { value: "G-Dur", label: "G-Dur" },
    { value: "D-Dur", label: "D-Dur" },
    { value: "A-Dur", label: "A-Dur" },
    { value: "E-Dur", label: "E-Dur" },
    { value: "B-Dur", label: "B♮-Dur" },
    { value: "F#-Dur", label: "F#-Dur" },
    { value: "C#-Dur", label: "C#-Dur" },
    { value: "Db-Dur", label: "Db-Dur" },
    { value: "Ab-Dur", label: "Ab-Dur" },
    { value: "Eb-Dur", label: "Eb-Dur" },
    { value: "Bb-Dur", label: "Bb-Dur" },
    { value: "F-Dur", label: "F-Dur" },
    { value: "A-Moll", label: "A-Moll" },
    { value: "E-Moll", label: "E-Moll" },
    { value: "B-Moll", label: "B♮-Moll" },
    { value: "F#-Moll", label: "F#-Moll" },
    { value: "C#-Moll", label: "C#-Moll" },
    { value: "G#-Moll", label: "G#-Moll" },
    { value: "D#-Moll", label: "D#-Moll" },
    { value: "A#-Moll", label: "A#-Moll" },
    { value: "F-Moll", label: "F-Moll" },
    { value: "C-Moll", label: "C-Moll" },
    { value: "G-Moll", label: "G-Moll" },
    { value: "D-Moll", label: "D-Moll" },
];
const emit = defineEmits(["update:modelValue"]);
const activeSetIndex = ref(0),
    selected = ref(null),
    hovered = ref(null),
    hoveredSource = ref(null),
    eraserActive = ref(false),
    quickChord = ref(null),
    root = ref("C"),
    accidental = ref(""),
    minor = ref(false),
    sus = ref(""),
    extension = ref("");
const currentChord = computed(
    () =>
        quickChord.value ??
        `${root.value === "B" && !["#", "b"].includes(accidental.value) ? "B♮" : root.value}${accidental.value}${minor.value ? "m" : ""}${sus.value ? `sus${sus.value}` : ""}${extension.value}`,
);
const quickChordMap = {
    "C-Dur": ["C", "F", "G", "G7", "Am", "Dm", "Em"],
    "G-Dur": ["G", "C", "D", "D7", "Em", "Am", "Bm"],
    "D-Dur": ["D", "G", "A", "A7", "Bm", "Em", "F#m"],
    "A-Dur": ["A", "D", "E", "E7", "F#m", "Bm", "C#m"],
    "E-Dur": ["E", "A", "B", "B7", "C#m", "F#m", "G#m"],
    "B-Dur": ["B", "E", "F#", "F#7", "G#m", "C#m", "D#m"],
    "F#-Dur": ["F#", "B", "C#", "C#7", "D#m", "G#m", "A#m"],
    "C#-Dur": ["C#", "F#", "G#", "G#7", "A#m", "D#m", "E#m"],
    "Db-Dur": ["Db", "Gb", "Ab", "Ab7", "Bbm", "Ebm", "Fm"],
    "Ab-Dur": ["Ab", "Db", "Eb", "Eb7", "Fm", "Bbm", "Cm"],
    "Eb-Dur": ["Eb", "Ab", "Bb", "Bb7", "Cm", "Fm", "Gm"],
    "Bb-Dur": ["Bb", "Eb", "F", "F7", "Gm", "Cm", "Dm"],
    "F-Dur": ["F", "Bb", "C", "C7", "Dm", "Gm", "Am"],
    "A-Moll": ["Am", "Dm", "E", "E7", "C", "F", "G"],
    "E-Moll": ["Em", "Am", "B", "B7", "G", "C", "D"],
    "B-Moll": ["Bm", "Em", "F#", "F#7", "D", "G", "A"],
    "F#-Moll": ["F#m", "Bm", "C#", "C#7", "A", "D", "E"],
    "C#-Moll": ["C#m", "F#m", "G#", "G#7", "E", "A", "B"],
    "G#-Moll": ["G#m", "C#m", "D#", "D#7", "B", "E", "F#"],
    "D#-Moll": ["D#m", "G#m", "A#", "A#7", "F#", "B", "C#"],
    "A#-Moll": ["A#m", "D#m", "E#", "E#7", "C#", "F#", "G#"],
    "F-Moll": ["Fm", "Bbm", "C", "C7", "Ab", "Db", "Eb"],
    "C-Moll": ["Cm", "Fm", "G", "G7", "Eb", "Ab", "Bb"],
    "G-Moll": ["Gm", "Cm", "D", "D7", "Bb", "Eb", "F"],
    "D-Moll": ["Dm", "Gm", "A", "A7", "F", "Bb", "C"],
};
const quickChords = computed(() =>
    activeSet.value?.key_signature
        ? (quickChordMap[activeSet.value.key_signature] ?? []).map((chord) => ({
              chord: normalizeChord(chord.replace(" ", "")),
          }))
        : [],
);
function normalizeChord(chord) {
    return String(chord ?? "")
        .replace(/^H(?=[A-Ga-g#b♮0-9]|$)/, "B♮")
        .replace(/^B(?!b|♮)/, "B♮");
}
const activeSet = computed(
    () => props.modelValue[activeSetIndex.value] ?? null,
);
const lines = computed(() =>
    (props.parts ?? []).flatMap((part, partIndex) => {
        const repetitions = part.is_repeated
            ? Math.max(2, part.repeat_count || 2)
            : 1;
        return Array.from({ length: repetitions }, (_, repetition) =>
            String(part.content ?? "")
                .split("\n")
                .map((text, lineNumber) => ({
                    part,
                    partIndex,
                    lineNumber,
                    repetition,
                    text,
                })),
        ).flat();
    }),
);
function updateSets(value) {
    emit("update:modelValue", value);
}
function addSet() {
    updateSets([
        ...props.modelValue,
        { instrument: "Gitarre", name: "", chords: [] },
    ]);
    activeSetIndex.value = props.modelValue.length;
}
function removeSet() {
    if (!activeSet.value) return;
    updateSets(
        props.modelValue.filter((_, index) => index !== activeSetIndex.value),
    );
    activeSetIndex.value = Math.max(0, activeSetIndex.value - 1);
}
function selectPosition(line, characterOffset) {
    selected.value = {
        partId: line.part.id,
        lineNumber: line.lineNumber,
        repetition: line.repetition,
        characterOffset,
    };
    if (eraserActive.value) {
        removeChordAtSelectedPosition();
    } else {
        saveChord();
    }
}
function hoverPosition(line, characterOffset) {
    hovered.value = {
        partId: line.part.id,
        lineNumber: line.lineNumber,
        repetition: line.repetition,
        characterOffset,
    };
    hoveredSource.value = "text";
}
function hoverSavedChord(chord) {
    hovered.value = {
        partId: chord.song_part_id,
        lineNumber: chord.line_number,
        repetition: chord.repetition ?? 0,
        characterOffset: chord.character_offset,
    };
    hoveredSource.value = "saved";
}
function clearHover() {
    hovered.value = null;
    hoveredSource.value = null;
}
function isHovered(line, characterOffset) {
    return (
        hovered.value?.partId === line.part.id &&
        hovered.value?.lineNumber === line.lineNumber &&
        hovered.value?.repetition === line.repetition &&
        hovered.value?.characterOffset === characterOffset
    );
}
function displayedChord(line, item) {
    if (eraserActive.value && isHovered(line, item.index)) {
        return normalizeChord(chordAt(line, item.index)?.chord ?? "");
    }

    return isHovered(line, item.index) && hoveredSource.value === "text"
        ? currentChord.value
        : normalizeChord(item.chord);
}
function chordAt(line, characterOffset) {
    return chordsAt(line).find(
        (chord) => chord.character_offset === characterOffset,
    );
}
function isEraserPreview(line, characterOffset) {
    return (
        eraserActive.value &&
        isHovered(line, characterOffset) &&
        Boolean(chordAt(line, characterOffset))
    );
}
function saveChord() {
    if (!activeSet.value || !selected.value || !currentChord.value) return;
    const chords = (activeSet.value.chords ?? []).filter(
        (chord) =>
            !(
                chord.song_part_id === selected.value.partId &&
                chord.line_number === selected.value.lineNumber &&
                (chord.repetition ?? 0) === selected.value.repetition &&
                chord.character_offset === selected.value.characterOffset
            ),
    );
    chords.push({
        song_part_id: selected.value.partId,
        line_number: selected.value.lineNumber,
        repetition: selected.value.repetition,
        character_offset: selected.value.characterOffset,
        chord: currentChord.value,
    });
    updateSets(
        props.modelValue.map((set, index) =>
            index === activeSetIndex.value ? { ...set, chords } : set,
        ),
    );
}
function chooseRoot(value) {
    eraserActive.value = false;
    quickChord.value = null;
    root.value = value;
    accidental.value = "";
    minor.value = false;
    sus.value = "";
    extension.value = "";
}
function chooseMinor() {
    eraserActive.value = false;
    quickChord.value = null;
    minor.value = !minor.value;
}
function chooseAccidental(value) {
    eraserActive.value = false;
    quickChord.value = null;
    accidental.value = accidental.value === value ? "" : value;
}
function chooseSus(value) {
    eraserActive.value = false;
    quickChord.value = null;
    sus.value = sus.value === value ? "" : value;
}
function chooseExtension(value) {
    eraserActive.value = false;
    quickChord.value = null;
    extension.value = extension.value === value ? "" : value;
}
function chooseQuickChord(chord) {
    eraserActive.value = false;
    quickChord.value = chord;
}
function toggleEraser() {
    eraserActive.value = !eraserActive.value;
}
function handleKeyboardShortcut(event) {
    const target = event.target;
    if (
        target instanceof HTMLElement &&
        target.matches('input, textarea, select, [contenteditable="true"]')
    ) {
        return;
    }

    const plain = !event.altKey && !event.ctrlKey && !event.metaKey;
    if (plain && event.key === "Delete") {
        toggleEraser();
        event.preventDefault();
        return;
    }
    const rootKeys = ["A", "B", "C", "D", "E", "F", "G"];
    if (
        plain &&
        !event.shiftKey &&
        rootKeys.includes(event.key.toUpperCase())
    ) {
        chooseRoot(event.key.toUpperCase());
    } else if (plain && event.key.toLowerCase() === "m") {
        chooseMinor();
    } else if (plain && event.key.toLowerCase() === "n") {
        chooseAccidental("♮");
    } else if (plain && event.key === "#") {
        chooseAccidental("#");
    } else if (plain && event.shiftKey && event.code === "KeyB") {
        chooseAccidental("b");
    } else if (plain && event.key === "2") {
        chooseSus("2");
    } else if (plain && event.key === "4") {
        chooseSus("4");
    } else if (plain && event.key === "0") {
        chooseExtension("");
    } else if (plain && ["6", "7", "9"].includes(event.key)) {
        chooseExtension(event.key);
    } else if (plain && event.shiftKey && event.code === "Digit1") {
        chooseExtension("11");
    } else if (plain && event.shiftKey && event.code === "Digit3") {
        chooseExtension("13");
    } else {
        return;
    }
    event.preventDefault();
}
onMounted(() => window.addEventListener("keydown", handleKeyboardShortcut));
onUnmounted(() =>
    window.removeEventListener("keydown", handleKeyboardShortcut),
);
function removeChord(chord) {
    updateSets(
        props.modelValue.map((set, index) =>
            index === activeSetIndex.value
                ? {
                      ...set,
                      chords: set.chords.filter((item) => item !== chord),
                  }
                : set,
        ),
    );
}
function removeChordAtSelectedPosition() {
    if (!activeSet.value || !selected.value) return;

    const chords = (activeSet.value.chords ?? []).filter(
        (chord) =>
            !(
                chord.song_part_id === selected.value.partId &&
                chord.line_number === selected.value.lineNumber &&
                (chord.repetition ?? 0) === selected.value.repetition &&
                chord.character_offset === selected.value.characterOffset
            ),
    );
    updateSets(
        props.modelValue.map((set, index) =>
            index === activeSetIndex.value ? { ...set, chords } : set,
        ),
    );
}
function chordsAt(line) {
    return (activeSet.value?.chords ?? []).filter(
        (chord) =>
            chord.song_part_id === line.part.id &&
            chord.line_number === line.lineNumber &&
            (chord.repetition ?? 0) === line.repetition,
    );
}
function textParts(line) {
    const marks = chordsAt(line);
    return Array.from(line.text).map((character, index) => ({
        character,
        index,
        chord:
            marks.find((mark) => mark.character_offset === index)?.chord ??
            null,
    }));
}
</script>

<template>
    <div class="row g-4 chord-editor">
        <div class="col-lg-3">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="h6 mb-0">Instrumente</h3>
                <button
                    class="btn btn-sm btn-outline-primary"
                    type="button"
                    @click="addSet"
                >
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>
            <div class="list-group mt-2">
                <button
                    v-for="(set, index) in modelValue"
                    :key="index"
                    class="list-group-item list-group-item-action text-start"
                    :class="{ active: index === activeSetIndex }"
                    type="button"
                    @click="activeSetIndex = index"
                >
                    {{ set.instrument || "Ohne Instrument"
                    }}<span v-if="set.name" class="small d-block opacity-75">{{
                        set.name
                    }}</span
                    ><span class="small d-block opacity-75"
                        >{{ set.chords?.length ?? 0 }} Akkorde</span
                    >
                </button>
            </div>
            <p v-if="!modelValue.length" class="small text-muted mt-3">
                Lege einen Satz für Gitarre, Klavier oder ein anderes Instrument
                an.
            </p>
        </div>
        <div v-if="activeSet" class="col-lg-9">
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Instrument</label
                    ><input
                        v-model="activeSet.instrument"
                        class="form-control"
                        placeholder="z. B. Gitarre"
                    />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tonart</label>
                    <select
                        v-model="activeSet.key_signature"
                        class="form-select"
                    >
                        <option value="">Keine Tonart</option>
                        <option
                            v-for="key in keySignatures"
                            :key="key.value"
                            :value="key.value"
                        >
                            {{ key.label }}
                        </option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label"
                        >Bezeichnung
                        <span class="text-muted">(optional)</span></label
                    ><input
                        v-model="activeSet.name"
                        class="form-control"
                        placeholder="z. B. Capo 2"
                    />
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button
                        class="btn btn-outline-danger"
                        type="button"
                        title="Akkordsatz löschen"
                        aria-label="Akkordsatz löschen"
                        @click="removeSet"
                    >
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
            <p class="small text-muted">
                Klicke im Liedtext auf den Buchstaben, über dem der Akkord
                beginnen soll. Der ausgewählte Akkord bleibt aktiv und wird bei
                jedem weiteren Klick verwendet.
            </p>
            <div
                class="chord-toolbar border rounded p-2 mb-3"
                aria-label="Akkord auswählen"
            >
                <div class="d-flex flex-wrap align-items-center gap-1">
                    <span class="small fw-semibold me-1">{{
                        eraserActive ? "Radierer" : currentChord
                    }}</span>
                    <button
                        class="btn btn-sm"
                        :class="
                            eraserActive ? 'btn-danger' : 'btn-outline-danger'
                        "
                        type="button"
                        title="Radierer (Entf)"
                        :aria-pressed="eraserActive"
                        @click="toggleEraser"
                    >
                        <i class="bi bi-eraser me-1"></i>Radierer
                    </button>
                    <button
                        v-for="note in ['A', 'B', 'C', 'D', 'E', 'F', 'G']"
                        :key="note"
                        class="btn btn-sm"
                        :class="
                            root === note
                                ? 'btn-primary'
                                : 'btn-outline-secondary'
                        "
                        type="button"
                        @click="chooseRoot(note)"
                    >
                        {{ note === "B" ? "B♮" : note }}
                    </button>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-1">
                    <span class="small text-muted me-1">Zusätze</span>
                    <button
                        class="btn btn-sm"
                        :class="minor ? 'btn-primary' : 'btn-outline-secondary'"
                        type="button"
                        @click="chooseMinor"
                    >
                        m
                    </button>
                    <button
                        v-for="value in ['♮', '#', 'b']"
                        :key="value"
                        class="btn btn-sm"
                        :class="
                            accidental === value
                                ? 'btn-primary'
                                : 'btn-outline-secondary'
                        "
                        type="button"
                        @click="chooseAccidental(value)"
                    >
                        {{ value === "♮" ? "Auflösung ♮" : value }}
                    </button>
                    <button
                        v-for="value in ['2', '4']"
                        :key="`sus${value}`"
                        class="btn btn-sm"
                        :class="
                            sus === value
                                ? 'btn-primary'
                                : 'btn-outline-secondary'
                        "
                        type="button"
                        @click="chooseSus(value)"
                    >
                        sus{{ value }}
                    </button>
                    <button
                        v-for="value in ['', '6', '7', '9', '11', '13']"
                        :key="value || 'no-extension'"
                        class="btn btn-sm"
                        :class="
                            extension === value
                                ? 'btn-primary'
                                : 'btn-outline-secondary'
                        "
                        type="button"
                        @click="chooseExtension(value)"
                    >
                        {{ value || "—" }}
                    </button>
                </div>
                <div
                    v-if="quickChords.length"
                    class="d-flex flex-wrap align-items-center gap-1 mt-2 pt-2 border-top"
                >
                    <span class="small text-muted me-1">Tonart</span>
                    <button
                        v-for="item in quickChords"
                        :key="item.chord"
                        class="btn btn-sm"
                        :class="
                            quickChord === item.chord
                                ? 'btn-primary'
                                : 'btn-outline-secondary'
                        "
                        type="button"
                        :title="`Schnellwahl ${item.chord}`"
                        @click="chooseQuickChord(item.chord)"
                    >
                        {{ item.chord }}
                    </button>
                </div>
                <div class="small text-muted mt-2" aria-label="Tastaturkürzel">
                    Tastenkürzel: A–G Grundton (B = B♮) · M Moll · N ♮ · # ♯ ·
                    Umschalt+B ♭ · 2/4 sus · 0 ohne Zahl · 6/7/9 · Umschalt+1 11
                    · Umschalt+3 13 · Entf Radierer
                </div>
            </div>
            <div class="chord-line-editor border rounded p-3">
                <div
                    v-for="line in lines"
                    :key="`${line.partIndex}-${line.repetition}-${line.lineNumber}`"
                    class="chord-line mb-3"
                >
                    <span
                        v-if="line.part.is_repeated"
                        class="small text-muted me-2"
                        >Wiederholung {{ line.repetition + 1 }}</span
                    >
                    <div class="chord-character-row">
                        <button
                            v-for="item in textParts(line)"
                            :key="item.index"
                            class="chord-character"
                            :class="{
                                selected:
                                    selected?.partId === line.part.id &&
                                    selected?.lineNumber === line.lineNumber &&
                                    selected?.repetition === line.repetition &&
                                    selected?.characterOffset === item.index,
                                'chord-character-hovered': isHovered(
                                    line,
                                    item.index,
                                ),
                                'chord-character-saved-hovered':
                                    hoveredSource === 'saved' &&
                                    isHovered(line, item.index),
                            }"
                            type="button"
                            @mouseenter="hoverPosition(line, item.index)"
                            @mouseleave="clearHover"
                            @click="selectPosition(line, item.index)"
                        >
                            <span
                                class="chord-above"
                                :class="{
                                    'chord-above-preview': isHovered(
                                        line,
                                        item.index,
                                    ),
                                    'chord-above-eraser-preview':
                                        isEraserPreview(line, item.index),
                                }"
                                >{{ displayedChord(line, item) }}</span
                            ><span>{{
                                item.character === " " ? "·" : item.character
                            }}</span>
                        </button>
                    </div>
                </div>
                <p v-if="!lines.length" class="small text-muted mb-0">
                    Füge im Tab „Liedblatt“ zuerst Liedtext hinzu.
                </p>
            </div>
            <div class="small text-muted mt-3">
                Aktiver Akkord:
                <strong class="text-dark">{{ currentChord }}</strong
                >. Klicke im Text auf eine Position, um ihn dort zu setzen.
            </div>
            <div v-if="activeSet.chords?.length" class="mt-4">
                <h4 class="h6">Gespeicherte Akkorde</h4>
                <div class="d-flex flex-wrap gap-2">
                    <span
                        v-for="chord in activeSet.chords"
                        :key="`${chord.song_part_id}-${chord.line_number}-${chord.repetition ?? 0}-${chord.character_offset}`"
                        class="badge rounded-pill text-bg-light border"
                        @mouseenter="hoverSavedChord(chord)"
                        @mouseleave="clearHover"
                        >{{ normalizeChord(chord.chord) }}
                        <button
                            class="btn btn-sm p-0 ms-1 text-danger"
                            type="button"
                            aria-label="Akkord löschen"
                            @click="removeChord(chord)"
                        >
                            <i class="bi bi-x"></i></button
                    ></span>
                </div>
            </div>
        </div>
    </div>
</template>
