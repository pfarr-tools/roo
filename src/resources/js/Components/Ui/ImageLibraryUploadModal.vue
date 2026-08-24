<script setup>
import { ref, watch } from "vue";
import de from "../../i18n/de";

const props = defineProps({
    show: { type: Boolean, default: false },
    uploadUrl: { type: String, required: true },
});

const emit = defineEmits(["close", "uploaded"]);
const file = ref(null);
const description = ref("");
const copyrights = ref("");
const processing = ref(false);
const error = ref("");

watch(
    () => props.show,
    (show) => {
        if (show) {
            file.value = null;
            description.value = "";
            copyrights.value = "";
            error.value = "";
        }
    },
);

async function upload() {
    if (!file.value || processing.value) return;

    processing.value = true;
    error.value = "";
    const body = new FormData();
    body.append("image", file.value);
    body.append("description", description.value);
    body.append("copyrights", copyrights.value);
    const token = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content");

    try {
        const response = await fetch(props.uploadUrl, {
            method: "POST",
            body,
            headers: token
                ? { "X-CSRF-TOKEN": token, Accept: "application/json" }
                : { Accept: "application/json" },
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || "Upload fehlgeschlagen");
        emit("uploaded", data.image);
        emit("close");
    } catch {
        error.value = "Das Bild konnte nicht hochgeladen werden.";
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <div
        v-if="show"
        class="roo-modal-backdrop"
        role="presentation"
        @click.self="emit('close')"
    >
        <section
            class="roo-modal"
            role="dialog"
            aria-modal="true"
            :aria-label="de.assessmentTaskUploadImage"
        >
            <form class="card border-0" @submit.prevent="upload">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">{{ de.assessmentTaskUploadImage }}</h2>
                        <button
                            class="btn-close"
                            type="button"
                            :aria-label="de.close"
                            @click="emit('close')"
                        ></button>
                    </div>
                    <label class="form-label" for="image-library-upload-file">{{ de.chooseFile }}</label>
                    <input
                        id="image-library-upload-file"
                        class="form-control"
                        type="file"
                        accept="image/*"
                        required
                        @change="file = $event.target.files?.[0] ?? null"
                    />
                    <label class="form-label mt-3" for="image-library-upload-description">{{ de.description }}</label>
                    <textarea id="image-library-upload-description" v-model="description" class="form-control" rows="3"></textarea>
                    <label class="form-label mt-3" for="image-library-upload-copyrights">{{ de.copyrights }}</label>
                    <textarea id="image-library-upload-copyrights" v-model="copyrights" class="form-control" rows="3"></textarea>
                    <div v-if="error" class="invalid-feedback d-block">{{ error }}</div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button class="btn btn-outline-secondary" type="button" @click="emit('close')">{{ de.cancel }}</button>
                        <button class="btn btn-primary" type="submit" :disabled="!file || processing">
                            {{ processing ? de.uploading : de.assessmentTaskUploadImage }}
                        </button>
                    </div>
                </div>
            </form>
        </section>
    </div>
</template>
