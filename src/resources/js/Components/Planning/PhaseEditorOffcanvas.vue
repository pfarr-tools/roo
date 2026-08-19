<script setup>
import de from '../../i18n/de'
import { reactive, watch } from 'vue'

const props = defineProps({ phase: Object })
const emit = defineEmits(['close', 'save'])
const form = reactive({ title: '', duration_minutes: null, description: '', materials: '' })
watch(() => props.phase, phase => Object.assign(form, { title: phase?.title ?? '', duration_minutes: phase?.duration_minutes ?? null, description: phase?.description ?? '', materials: phase?.materials ?? '' }), { immediate: true })
</script>

<template>
    <div class="offcanvas-backdrop" role="presentation" @click.self="emit('close')">
        <aside class="offcanvas offcanvas-end show" tabindex="-1" aria-modal="true" role="dialog" :aria-label="de.editPhase">
            <div class="offcanvas-header"><h2 class="h5 mb-0">{{ de.editPhase }}</h2><button type="button" class="btn-close" :aria-label="de.close" @click="emit('close')"></button></div>
            <form class="offcanvas-body" @submit.prevent="emit('save', { ...form })">
                <label class="form-label" for="phase-title">{{ de.phaseTitle }}</label><input id="phase-title" v-model="form.title" class="form-control" required>
                <label class="form-label mt-3" for="phase-duration">{{ de.phaseDuration }}</label><input id="phase-duration" v-model="form.duration_minutes" class="form-control" type="number" min="1" max="999">
                <label class="form-label mt-3" for="phase-description">{{ de.description }}</label><textarea id="phase-description" v-model="form.description" class="form-control" rows="5"></textarea>
                <label class="form-label mt-3" for="phase-materials">{{ de.materials }}</label><textarea id="phase-materials" v-model="form.materials" class="form-control" rows="3"></textarea>
                <div class="d-flex justify-content-end gap-2 mt-4"><button class="btn btn-outline-secondary" type="button" @click="emit('close')">{{ de.cancel }}</button><button class="btn btn-primary" type="submit">{{ de.saveChanges }}</button></div>
            </form>
        </aside>
    </div>
</template>
