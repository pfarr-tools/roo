<script setup>
defineProps({
    competencies: Array,
    planId: Number,
    csrfToken: String,
    labels: Object,
})
</script>

<template>
    <ol class="list-group list-group-numbered list-group-flush">
        <li v-for="competency in competencies" :key="competency.id" class="list-group-item px-0">
            <div class="d-flex gap-2">
                <span class="text-muted">{{ competency.external_identifier }}</span>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <p v-if="competency.text" class="mb-2">{{ competency.text }}</p>
                        <span :class="['badge', competency.is_active ? 'text-bg-success' : 'text-bg-secondary']">
                            {{ competency.is_active ? labels.active : labels.inactive }}
                        </span>
                    </div>
                    <div v-for="variant in competency.variants" :key="variant.id" class="small mb-1">
                        <span class="badge text-bg-light me-2">{{ variant.level?.external_identifier ?? 'Standard' }}</span>{{ variant.text }}
                    </div>
                    <div class="d-flex align-items-center gap-2 mt-2">
                        <form method="post" :action="`/bildungsplaene/${planId}/kompetenzen/${competency.id}/status`">
                            <input type="hidden" name="_token" :value="csrfToken">
                            <input type="hidden" name="is_active" :value="competency.is_active ? 0 : 1">
                            <button class="btn btn-sm btn-link p-0" type="submit">{{ competency.is_active ? labels.deactivate : labels.activate }}</button>
                        </form>
                        <details v-if="competency.relations?.length" class="small text-muted">
                            <summary>Quellverweise ({{ competency.relations.length }})</summary>
                            <div v-for="relation in competency.relations" :key="relation.id">{{ relation.raw_reference }}</div>
                        </details>
                    </div>
                </div>
            </div>
        </li>
    </ol>
</template>
