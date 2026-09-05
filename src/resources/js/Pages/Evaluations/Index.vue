<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import de from '../../i18n/de'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    groups: { type: Array, default: () => [] },
    group: { type: Object, default: null },
    reportPeriods: { type: Array, default: () => [] },
})

function selectGroup(event) {
    router.get('/bewertungen', { group: event.target.value }, { preserveState: true, preserveScroll: true })
}
</script>

<template>
    <AppShell>
        <template #toolbar>
            <label class="visually-hidden" for="evaluations-group">{{ de.teachingGroup }}</label>
            <select id="evaluations-group" class="form-select form-select-sm" :value="group?.id ?? ''" :disabled="!groups.length" @change="selectGroup">
                <option v-for="option in groups" :key="option.id" :value="option.id">{{ option.name }}</option>
            </select>
            <a v-if="group" class="btn btn-sm btn-primary d-inline-flex align-items-center" :href="`/unterrichtsgruppen/${group.id}/bewertungen/neu`"><i class="bi bi-plus-lg d-inline me-1" aria-hidden="true"></i>Zeitraum</a>
        </template>
        <div class="container-full px-3 py-4">
            <h1 class="h2">{{ de.groupEvaluations }}</h1>
            <p v-if="group" class="text-muted">Bewertungszeiträume und bearbeitbare Entwürfe.</p>
            <div v-if="!group" class="text-muted">Noch keine Unterrichtsgruppe angelegt.</div>
            <template v-else>
                <div v-if="!reportPeriods.length" class="text-muted">Noch kein Bewertungszeitraum angelegt.</div>
                <div v-for="period in reportPeriods" :key="period.id" class="border-top py-3">
                    <strong>{{ period.label }}</strong>
                    <div v-if="!period.evaluations?.length" class="small text-muted mt-1">Keine Schüler:innen in diesem Zeitraum.</div>
                    <div v-for="evaluation in period.evaluations" :key="evaluation.id" class="d-flex justify-content-between align-items-start mt-2">
                        <div>
                            <span>{{ evaluation.student.last_name }}, {{ evaluation.student.first_name }}</span>
                            <span class="badge ms-2" :class="evaluation.status === 'confirmed' ? 'text-bg-success' : 'text-bg-light'">{{ evaluation.status === 'confirmed' ? 'bestätigt' : 'Entwurf' }}</span>
                            <p class="mb-0 mt-1 small text-muted text-pre-wrap">{{ evaluation.draft_text || 'Noch kein Bewertungsentwurf.' }}</p>
                        </div>
                        <a class="btn btn-sm btn-outline-primary" :href="`/unterrichtsgruppen/${group.id}/bewertungen/${evaluation.id}/bearbeiten`">Bearbeiten</a>
                    </div>
                </div>
            </template>
        </div>
    </AppShell>
</template>
