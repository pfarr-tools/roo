<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
const props = defineProps({ songs: Array, filters: Object })
const query = ref(props.filters?.q ?? '')
const modal = ref(false)
const form = useForm({ title: '', composer: '', author: '', copyright_notice: '', age_group: '', topics: '', notes: '', version_name: 'Standardfassung', lyrics: '', notation: '', chords: '', rights_status: 'unknown', rights_note: '', text_export_allowed: false, metadata_export_allowed: true, sheet: null })
function search() { router.get('/lieder', { q: query.value }, { preserveState: true, replace: true }) }
function save() { form.post('/lieder', { forceFormData: true, onSuccess: () => { modal.value = false; form.reset() } }) }
</script>
<template>
    <AppShell><template #toolbar><button class="btn btn-sm btn-primary" type="button" @click="modal = true"><i class="bi bi-plus-lg me-1"></i>Lied anlegen</button></template>
        <div class="container-full px-3 py-4"><h1 class="h2 mb-4">Liedersammlung</h1><form class="d-flex gap-2 mb-3" @submit.prevent="search"><input v-model="query" class="form-control" placeholder="Lieder durchsuchen"><button class="btn btn-outline-secondary">Suchen</button></form><div class="list-group"><div v-for="song in songs" :key="song.id" class="list-group-item"><strong>{{ song.title }}</strong><span class="small text-muted ms-2">{{ [song.author, song.composer].filter(Boolean).join(' · ') }}</span><div v-for="version in song.versions" :key="version.id" class="small mt-1">{{ version.name }} · Rechte: {{ version.rights_status }}<span v-if="version.sheet" class="ms-2 text-success"><i class="bi bi-file-earmark-pdf"></i> Liedblatt vorhanden</span></div></div><p v-if="!songs.length" class="text-muted">Noch keine Lieder vorhanden.</p></div></div>
        <div v-if="modal" class="roo-modal-backdrop" @click.self="modal = false"><form class="roo-modal card" @submit.prevent="save"><div class="card-body"><div class="d-flex justify-content-between"><h2 class="h5">Lied anlegen</h2><button type="button" class="btn-close" @click="modal = false"></button></div><label class="form-label">Titel</label><input v-model="form.title" class="form-control" required><div class="row g-2"><div class="col-md-6"><label class="form-label mt-2">Text:in</label><input v-model="form.author" class="form-control"></div><div class="col-md-6"><label class="form-label mt-2">Komponist:in</label><input v-model="form.composer" class="form-control"></div></div><label class="form-label mt-2">Fassung</label><input v-model="form.version_name" class="form-control" required><label class="form-label mt-2">Rechtestatus</label><select v-model="form.rights_status" class="form-select"><option value="unknown">Ungeklärt</option><option value="cleared">Geklärt</option><option value="restricted">Eingeschränkt</option><option value="licensed">Lizenziert</option></select><label class="form-label mt-2">Vorhandenes A5-Liedblatt (PDF)</label><input class="form-control" type="file" accept="application/pdf" @change="form.sheet = $event.target.files?.[0] ?? null"><button class="btn btn-primary mt-3" :disabled="form.processing">Speichern</button></div></form></div>
    </AppShell>
</template>
