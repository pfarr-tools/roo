<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({ group: Object, songVersions: { type: Array, default: () => [] }, songbookVersions: { type: Array, default: () => [] } })

const showEditModal = ref(false)
const showPrintModal = ref(false)
const editTab = ref('title-page')
const searchTerm = ref('')
const searchResults = ref([])
const searching = ref(false)
const exporting = ref(false)
const toastMessage = ref('')
const printForm = ref({ format: 'a4', instrument: '', scope: 'whole', date: '' })
const titlePageForm = useForm({ title_page: null })
function songbookEntries() {
    return props.group.songbook?.entries ?? []
}

function songVersionIds(entries = songbookEntries()) {
    return entries.map(entry => entry.song_version_id ?? entry.song_version?.id).filter(Boolean)
}

const selectedSongVersions = ref(songbookEntries().map(entry => entry.song_version ?? entry))
const songbookForm = useForm({ song_version_ids: songVersionIds() })
let searchTimer

const initialSongs = computed(() => selectedSongVersions.value.filter(version => songbookForm.song_version_ids.includes(version.id)))
const availableDates = computed(() => [...new Set((props.group.songbook?.entries ?? []).map(entry => entry.added_at?.slice(0, 10)).filter(Boolean))].sort())
const availableInstruments = computed(() => [...new Set(props.songbookVersions.flatMap(version => (version.chord_sets ?? []).map(set => set.instrument).filter(Boolean)))].sort((a, b) => a.localeCompare(b, 'de')))

watch(() => props.group.songbook?.entries, (entries) => {
    const nextEntries = entries ?? []
    selectedSongVersions.value = nextEntries.map(entry => entry.song_version ?? entry)
    songbookForm.song_version_ids = songVersionIds(nextEntries)
}, { deep: true })

function songLabel(version) {
    return `${version.song?.title ?? 'Unbenannt'}${version.name ? ` · ${version.name}` : ''}`
}

function openEdit() {
    editTab.value = 'title-page'
    showEditModal.value = true
}

function uploadTitlePage() {
    if (!titlePageForm.title_page) return
    titlePageForm.post(`/unterrichtsgruppen/${props.group.id}/liederbuch/titelseite`, {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            titlePageForm.reset('title_page')
            showToast('Die Titelseite wurde gespeichert.')
        },
    })
}

function searchSongs() {
    clearTimeout(searchTimer)
    if (searchTerm.value.trim().length < 2) {
        searchResults.value = []
        return
    }
    searchTimer = setTimeout(async () => {
        searching.value = true
        try {
            const response = await fetch(`/unterrichtsgruppen/${props.group.id}/liederbuch/songs?q=${encodeURIComponent(searchTerm.value.trim())}`, { headers: { Accept: 'application/json' } })
            searchResults.value = response.ok ? await response.json() : []
        } finally {
            searching.value = false
        }
    }, 300)
}

function addSong(version) {
    if (!songbookForm.song_version_ids.includes(version.id)) {
        songbookForm.song_version_ids = [...songbookForm.song_version_ids, version.id]
        selectedSongVersions.value = [...selectedSongVersions.value, version]
    }
    searchTerm.value = ''
    searchResults.value = []
}

function removeSong(versionId) {
    songbookForm.song_version_ids = songbookForm.song_version_ids.filter(id => id !== versionId)
    selectedSongVersions.value = selectedSongVersions.value.filter(version => version.id !== versionId)
}

function saveSongbook() {
    songbookForm.put(`/unterrichtsgruppen/${props.group.id}/liederbuch/lieder`, { preserveScroll: true, onSuccess: () => showToast('Die Ausgangslieder wurden gespeichert.') })
}

async function printSongbook() {
    const query = new URLSearchParams({ format: printForm.value.format })
    if (printForm.value.format === 'chord-sheet') query.set('instrument', printForm.value.instrument.trim())
    if (printForm.value.scope === 'through') query.set('through_date', printForm.value.date)
    if (printForm.value.scope === 'from') query.set('from_date', printForm.value.date)
    exporting.value = true
    try {
        const response = await fetch(`/unterrichtsgruppen/${props.group.id}/liederbuch/export?${query.toString()}`, { headers: { Accept: 'application/pdf' } })
        if (!response.ok) throw new Error('Der PDF-Export konnte nicht erstellt werden.')
        const blob = await response.blob()
        const url = URL.createObjectURL(blob)
        const link = document.createElement('a')
        link.href = url
        link.download = `Gruppenliederbuch-${printForm.value.format}.pdf`
        document.body.appendChild(link)
        link.click()
        link.remove()
        URL.revokeObjectURL(url)
        showPrintModal.value = false
        showToast('Das Gruppenliederbuch wurde als PDF heruntergeladen.')
    } catch (error) {
        showToast(error.message)
    } finally {
        exporting.value = false
    }
}

function showToast(message) {
    toastMessage.value = message
    window.setTimeout(() => { toastMessage.value = '' }, 4500)
}

function formatDate(value) {
    return new Intl.DateTimeFormat('de-DE').format(new Date(`${value}T12:00:00`))
}

onBeforeUnmount(() => clearTimeout(searchTimer))
</script>

<template>
    <section class="card card-body mb-4">
        <div class="d-flex justify-content-between align-items-start gap-3">
            <div>
                <h2 class="h5 mb-1">Gruppenliederbuch</h2>
                <p class="text-muted mb-0">{{ group.songbook?.entries?.length ?? 0 }} Lieder · automatische Nummerierung</p>
                <span v-if="group.songbook?.title_page_path" class="badge text-bg-success mt-2"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>Titelseite vorhanden</span>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-sm btn-outline-primary" type="button" @click="openEdit"><i class="bi bi-pencil me-1" aria-hidden="true"></i>Bearbeiten</button>
                <button class="btn btn-sm btn-primary" type="button" @click="showPrintModal = true"><i class="bi bi-printer me-1" aria-hidden="true"></i>Drucken</button>
            </div>
        </div>
        <div v-if="group.songbook?.entries?.length" class="d-flex flex-wrap gap-2 mt-3">
            <span v-for="entry in group.songbook.entries" :key="entry.id" class="badge text-bg-light">{{ entry.song_number }} · {{ entry.song_version?.song?.title }}</span>
        </div>
    </section>

    <div v-if="showEditModal" class="roo-modal-backdrop" role="presentation" @click.self="showEditModal = false">
        <section class="roo-modal roo-modal-wide" role="dialog" aria-modal="true" aria-labelledby="songbook-edit-title">
            <div class="card border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 id="songbook-edit-title" class="h5 mb-0">Gruppenliederbuch bearbeiten</h2>
                        <button class="btn-close" type="button" aria-label="Schließen" @click="showEditModal = false"></button>
                    </div>
                    <ul class="nav nav-tabs mb-4" role="tablist">
                        <li class="nav-item"><button class="nav-link" :class="{ active: editTab === 'title-page' }" type="button" @click="editTab = 'title-page'">Titelseite</button></li>
                        <li class="nav-item"><button class="nav-link" :class="{ active: editTab === 'initial-songs' }" type="button" @click="editTab = 'initial-songs'">Ausgangslieder</button></li>
                    </ul>

                    <div v-if="editTab === 'title-page'">
                        <p class="text-muted">Lade eine PDF- oder Bilddatei als Titelseite hoch. Der Upload bleibt in diesem Dialog und aktualisiert die Anzeige sofort.</p>
                        <form class="row g-2 align-items-end" @submit.prevent="uploadTitlePage">
                            <div class="col-md-8"><label class="form-label" for="songbook-title-page">Titelseite</label><input id="songbook-title-page" class="form-control" type="file" accept="application/pdf,image/jpeg,image/png" @change="titlePageForm.title_page = $event.target.files?.[0] ?? null"></div>
                            <div class="col-auto"><button class="btn btn-primary" type="submit" :disabled="titlePageForm.processing">Titelseite speichern</button></div>
                        </form>
                        <div class="d-flex gap-2 mt-3">
                            <span v-if="group.songbook?.title_page_path" class="small text-success align-self-center"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>Titelseite hochgeladen</span>
                            <a v-if="group.songbook?.title_page_path" class="btn btn-sm btn-outline-secondary" :href="`/unterrichtsgruppen/${group.id}/liederbuch/titelseite`" target="_blank" rel="noopener">Vorschau</a>
                            <span v-else class="small text-muted align-self-center">Noch keine Titelseite hinterlegt.</span>
                        </div>
                    </div>

                    <div v-else>
                        <p class="text-muted">Diese Lieder werden zum Schuljahresbeginn in das Gruppenliederbuch übernommen.</p>
                        <div class="position-relative mb-3">
                            <label class="form-label" for="songbook-song-search">Lied suchen</label>
                            <input id="songbook-song-search" v-model="searchTerm" class="form-control" type="search" placeholder="Mindestens zwei Zeichen eingeben …" autocomplete="off" @input="searchSongs">
                            <div v-if="searchTerm.length >= 2" class="dropdown-menu show w-100 p-0">
                                <div v-if="searching" class="p-2 small text-muted">Suche läuft …</div>
                                <button v-for="version in searchResults" :key="version.id" class="dropdown-item py-2" type="button" @click="addSong(version)">{{ songLabel(version) }}</button>
                                <div v-if="!searching && !searchResults.length" class="p-2 small text-muted">Keine Lieder gefunden.</div>
                            </div>
                        </div>
                        <div class="list-group mb-3">
                            <div v-for="version in initialSongs" :key="version.id" class="list-group-item d-flex justify-content-between align-items-center">
                                <span>{{ songLabel(version) }}</span><button class="btn btn-sm btn-outline-danger" type="button" aria-label="Lied entfernen" @click="removeSong(version.id)"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                            </div>
                            <div v-if="!initialSongs.length" class="list-group-item text-muted">Noch keine Ausgangslieder ausgewählt.</div>
                        </div>
                        <div class="d-flex justify-content-end gap-2"><button class="btn btn-outline-secondary" type="button" @click="showEditModal = false">Abbrechen</button><button class="btn btn-primary" type="button" :disabled="songbookForm.processing" @click="saveSongbook">Ausgangslieder speichern</button></div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div v-if="showPrintModal" class="roo-modal-backdrop" role="presentation" @click.self="showPrintModal = false">
        <section class="roo-modal" role="dialog" aria-modal="true" aria-labelledby="songbook-print-title">
            <div class="card border-0"><div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3"><h2 id="songbook-print-title" class="h5 mb-0">Gruppenliederbuch drucken</h2><button class="btn-close" type="button" aria-label="Schließen" @click="showPrintModal = false"></button></div>
                <fieldset class="mb-3"><legend class="h6">Format</legend><label class="form-check"><input v-model="printForm.format" class="form-check-input" type="radio" value="a4"><span class="form-check-label">A4 quer (zwei A5-Kopien je Seite)</span></label><label class="form-check"><input v-model="printForm.format" class="form-check-input" type="radio" value="a5"><span class="form-check-label">A5</span></label><label class="form-check"><input v-model="printForm.format" class="form-check-input" type="radio" value="chord-sheet"><span class="form-check-label">Akkordblatt (A4 hoch)</span></label></fieldset>
                <div v-if="printForm.format === 'chord-sheet'" class="mb-3"><label class="form-label" for="songbook-print-instrument">Instrument</label><select id="songbook-print-instrument" v-model="printForm.instrument" class="form-select" required><option value="">Bitte auswählen</option><option v-for="instrument in availableInstruments" :key="instrument" :value="instrument">{{ instrument }}</option></select><div v-if="!availableInstruments.length" class="form-text">Für die Lieder im Liederbuch sind keine Akkordsätze hinterlegt.</div></div>
                <fieldset class="mb-3"><legend class="h6">Umfang</legend><label class="form-check"><input v-model="printForm.scope" class="form-check-input" type="radio" value="whole"><span class="form-check-label">Gesamtes Liederbuch</span></label><label class="form-check"><input v-model="printForm.scope" class="form-check-input" type="radio" value="through"><span class="form-check-label">Bis einschließlich einer Stunde mit neuen Liedern</span></label><label class="form-check"><input v-model="printForm.scope" class="form-check-input" type="radio" value="from"><span class="form-check-label">Neue Lieder ab einer Stunde</span></label></fieldset>
                <div v-if="printForm.scope !== 'whole'" class="mb-3"><label class="form-label" for="songbook-print-date">Stunde mit neuen Liedern</label><select id="songbook-print-date" v-model="printForm.date" class="form-select"><option value="">Bitte auswählen</option><option v-for="date in availableDates" :key="date" :value="date">{{ formatDate(date) }}</option></select></div>
                <div class="d-flex justify-content-end gap-2"><button class="btn btn-outline-secondary" type="button" @click="showPrintModal = false">Abbrechen</button><button class="btn btn-primary" type="button" :disabled="exporting || (printForm.scope !== 'whole' && !printForm.date) || (printForm.format === 'chord-sheet' && !printForm.instrument.trim())" @click="printSongbook"><span v-if="exporting" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>{{ exporting ? 'PDF wird erstellt …' : 'Druck starten' }}</button></div>
            </div></div>
        </section>
    </div>

    <div v-if="toastMessage" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1200" role="status" aria-live="polite">
        <div class="toast show text-bg-success"><div class="d-flex"><div class="toast-body">{{ toastMessage }}</div><button class="btn-close btn-close-white me-2 m-auto" type="button" aria-label="Schließen" @click="toastMessage = ''"></button></div></div>
    </div>
</template>
