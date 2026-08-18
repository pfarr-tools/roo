<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import de from '../../i18n/de'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({ sources: Array, schoolTypes: Array })
const denominations = ['evangelical', 'catholic', 'old_catholic', 'syriac_orthodox']
const form = useForm({ title: '', school_type: '', grades: [], denominations: [], source_version_ids: [] })

function toggle(id) {
    form.source_version_ids = form.source_version_ids.includes(id) ? form.source_version_ids.filter(value => value !== id) : [...form.source_version_ids, id]
}
function denominationLabel(value) { return de.denominationLabels[value] ?? value }
function schoolTypeLabel(value) { return de.schoolTypeLabels[value] ?? value }
function submit() { form.post('/curricula') }
</script>

<template>
    <AppShell>
        <template #toolbar><a href="/curricula" class="btn btn-sm btn-light" :title="de.close" :aria-label="de.close"><i class="bi bi-x-lg" aria-hidden="true"></i></a><button class="btn btn-sm btn-primary" type="button" :disabled="form.processing" @click="submit">{{ de.createCurriculum }}</button></template>
        <div class="container-full px-3 py-4">
            <a href="/curricula">{{ de.curricula }}</a>
            <h1 class="h2 mt-2">{{ de.createCurriculum }}</h1>
            <p class="text-muted">Wähle optional Vorlagen. Ohne Auswahl wird ein leeres Curriculum angelegt.</p>
            <form class="card" @submit.prevent="submit">
                <div class="card-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6"><label class="form-label">{{ de.curriculumTitle }}</label><input v-model="form.title" class="form-control" required><div v-if="form.errors.title" class="text-danger small">{{ form.errors.title }}</div></div>
                        <div class="col-md-3"><label class="form-label">{{ de.schoolType }}</label><select v-model="form.school_type" class="form-select"><option value="">{{ de.choose }}</option><option v-for="schoolType in props.schoolTypes" :key="schoolType" :value="schoolType">{{ schoolTypeLabel(schoolType) }}</option></select></div>
                        <div class="col-md-3"><label class="form-label">{{ de.grades }}</label><input :value="form.grades.join(', ')" class="form-control" placeholder="1,2" @change="form.grades = $event.target.value.split(',').map(value => Number(value.trim())).filter(Boolean)"></div>
                    </div>
                    <fieldset class="mb-4"><legend class="h5">{{ de.denominations }}</legend><div class="d-flex gap-4 flex-wrap"><label v-for="denomination in denominations" :key="denomination" class="form-check"><input v-model="form.denominations" class="form-check-input" type="checkbox" :value="denomination"><span class="form-check-label">{{ denominationLabel(denomination) }}</span></label></div></fieldset>
                    <h2 class="h5">{{ de.sourceCurricula }}</h2>
                    <p class="text-muted small">{{ de.noSourceCurriculum }}: Du kannst Bildungspläne und Kompetenzen später im Curriculum hinterlegen.</p>
                    <div class="list-group mb-3"><label v-for="source in props.sources" :key="source.id" class="list-group-item d-flex gap-3 align-items-center"><input class="form-check-input" type="checkbox" :checked="form.source_version_ids.includes(source.id)" @change="toggle(source.id)"><span><strong>{{ source.curriculum.title }}</strong><br><small class="text-muted">{{ [source.curriculum.school_type, source.curriculum.grades?.join(', ')].filter(Boolean).join(' · ') }} · {{ source.topics_count }} {{ de.units }}</small></span></label></div>
                    <div v-if="form.errors.source_version_ids" class="text-danger small mb-3">{{ form.errors.source_version_ids }}</div>
                </div>
            </form>
        </div>
    </AppShell>
</template>
