<script setup>
const props = defineProps({
    options: { type: Array, default: () => [] },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(["update:selection"]);

function toggle(option) {
    emit(
        "update:selection",
        props.options.map((item) =>
            item.id === option.id
                ? { ...item, selected: !item.selected }
                : item,
        ),
    );
}
</script>

<template>
    <div class="vstack gap-2" data-testid="image-matching-task-evaluation">
        <label
            v-for="option in options"
            :key="option.id"
            class="border rounded p-2 d-flex align-items-center gap-3"
            :for="'image-match-' + option.id"
        >
            <input
                :id="'image-match-' + option.id"
                class="form-check-input flex-shrink-0"
                type="checkbox"
                :checked="option.selected"
                :disabled="processing"
                @change="toggle(option)"
            />
            <img
                :src="option.image_url"
                :alt="option.label || 'Bild'"
                style="width: 6rem; max-height: 6rem; object-fit: contain"
            />
            <span>{{ option.label }} – {{ option.answer }}</span>
        </label>
    </div>
</template>
