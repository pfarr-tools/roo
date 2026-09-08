<script setup>
import logo from '../../../images/branding/roo-logo.png'
import icon from '../../../images/branding/roo-icon.png'
import ConfirmationModal from './ConfirmationModal.vue'
import de from '../../i18n/de'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
const sidebarPinStorageKey = 'roo.sidebar.pinned'
const sidebarPinned = ref(false)
const sidebarHovered = ref(false)
const mobileSidebarOpen = ref(false)
const labels = de
const page = usePage()
const currentUser = computed(() => page.props.auth?.user ?? null)
const userInitials = computed(() => {
    const parts = (currentUser.value?.name ?? '').trim().split(/\s+/).filter(Boolean)
    if (!parts.length) return '?'
    return (parts.length === 1 ? parts[0].slice(0, 2) : `${parts[0][0]}${parts[parts.length - 1][0]}`).toUpperCase()
})
const flashToasts = ref([])
let flashToastId = 0
const sidebarExpanded = computed(() => sidebarPinned.value || sidebarHovered.value || mobileSidebarOpen.value)

onMounted(() => {
    sidebarPinned.value = window.localStorage.getItem(sidebarPinStorageKey) === 'true'
})

watch(sidebarPinned, pinned => {
    window.localStorage.setItem(sidebarPinStorageKey, String(pinned))
})
const globalSearchQuery = ref('')
const globalSearchResults = ref({})
const globalSearchOpen = ref(false)
let globalSearchTimer = null
let globalSearchRequest = 0
const globalSearchGroups = [
    { key: 'schools', label: labels.schools, title: item => item.name, href: item => `/schulen/${item.slug}` },
    { key: 'groups', label: labels.teachingGroups, title: item => item.name, href: item => `/unterrichtsgruppen/${item.id}` },
    { key: 'curricula', label: labels.curricula, title: item => item.title, href: item => `/curricula/${item.id}` },
    { key: 'educationPlans', label: labels.educationPlans, title: item => item.title, href: item => `/bildungsplaene/${item.id}` },
    { key: 'students', label: labels.students, title: item => `${item.last_name}, ${item.first_name}`, href: item => `/schueler:innen/${item.id}` },
    { key: 'teachingUnits', label: labels.teachingUnits, title: item => item.title, href: () => '/unterrichtseinheiten' },
    { key: 'unitTemplates', label: labels.unitTemplates, title: item => item.title, href: () => '/unterrichtseinheiten' },
    { key: 'lessonTemplates', label: labels.lessonTemplates, title: item => item.title, href: () => '/unterrichtseinheiten' },
    { key: 'phaseTemplates', label: labels.phaseTemplates, title: item => item.title, href: () => '/unterrichtseinheiten' },
    { key: 'songs', label: labels.songs, title: item => item.title, href: () => '/bibliothek' },
    { key: 'assessmentTasks', label: labels.assessmentTasks, title: item => item.title, href: () => '/bibliothek' },
    { key: 'schoolYears', label: labels.schoolYears, title: item => item.name, href: item => `/schulen/${item.school?.slug}/${item.slug}` },
    { key: 'assessments', label: labels.learningAssessments, title: item => item.title, href: item => `/unterrichtsgruppen/${item.teaching_group_id}/lernstandserhebungen/${item.id}/bearbeiten` },
    { key: 'files', label: labels.files, title: item => item.original_name, href: () => '/bibliothek' },
    { key: 'links', label: labels.links, title: item => item.title, href: item => item.url },
    { key: 'materials', label: labels.materials, title: item => item.name, href: () => '/bibliothek' },
    { key: 'songVersions', label: labels.songVersions, title: item => item.song?.title || item.name, href: () => '/bibliothek' },
]
const presentGlobalSearchGroups = computed(() => globalSearchGroups.filter(group => globalSearchResults.value[group.key]?.length))

function addFlashToast(type, message) {
    if (!message) return
    const id = ++flashToastId
    flashToasts.value.push({ id, type, message })
    window.setTimeout(() => { flashToasts.value = flashToasts.value.filter(toast => toast.id !== id) }, 5000)
}

watch(() => [page.props.flash?.success, page.props.flash?.warning, page.props.flash?.error], ([success, warning, error]) => {
    addFlashToast('success', success)
    addFlashToast('warning', warning)
    addFlashToast('error', error)
}, { immediate: true })

async function searchGlobally() {
    const query = globalSearchQuery.value.trim()
    if (!query) {
        globalSearchResults.value = {}
        globalSearchOpen.value = false
        return
    }

    const requestId = ++globalSearchRequest
    try {
        const response = await fetch(`/suche?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        if (!response.ok || requestId !== globalSearchRequest) return
        const payload = await response.json()
        globalSearchResults.value = payload.results ?? {}
        globalSearchOpen.value = true
    } catch {
        if (requestId === globalSearchRequest) globalSearchOpen.value = false
    }
}

watch(globalSearchQuery, () => {
    if (globalSearchTimer) window.clearTimeout(globalSearchTimer)
    globalSearchTimer = window.setTimeout(searchGlobally, 300)
})

onBeforeUnmount(() => {
    if (globalSearchTimer) window.clearTimeout(globalSearchTimer)
    globalSearchRequest++
})
const moduleGroups = [
    { title: labels.teaching, items: [
        { label: labels.timetable, icon: 'bi-grid-1x2', url: '/dashboard', enabled: true },
        { label: labels.planningModule, icon: 'bi-calendar-range', url: '/jahresplanung', enabled: true },
    ] },
    { title: labels.organization, items: [
        { label: labels.schools, icon: 'bi-building', url: '/schulen', enabled: true },
        { label: labels.teachingGroups, icon: 'bi-people', url: '/unterrichtsgruppen', enabled: true },
        { label: labels.students, icon: 'bi-person-vcard', url: '/schueler:innen', enabled: true },
    ] },
    { title: labels.content, items: [
        { label: labels.educationPlans, icon: 'bi-journal-text', url: '/bildungsplaene', enabled: true },
        { label: labels.curricula, icon: 'bi-diagram-3', url: '/curricula', enabled: true },
        { label: labels.teachingUnits, icon: 'bi-collection', url: '/unterrichtseinheiten', enabled: true },
        { label: labels.library, icon: 'bi-folder2-open', url: '/bibliothek', enabled: true },
    ] },
    { title: labels.assessment, items: [
        { label: labels.observations, icon: 'bi-eye', url: '/beobachtungen', enabled: true },
        { label: labels.assessments, icon: 'bi-bar-chart', url: '/bewertungen', enabled: true },
    ] },
]

defineProps({
    authenticated: { type: Boolean, default: true },
    showBrand: { type: Boolean, default: true },
    showHeader: { type: Boolean, default: true },
})
</script>

<template>
    <div :class="['roo-app', { 'roo-sidebar-expanded': sidebarExpanded }]">
        <ConfirmationModal />
        <div class="roo-toast-container" aria-live="polite" aria-atomic="true"><div v-for="toast in flashToasts" :key="toast.id" class="roo-toast" :class="`roo-toast-${toast.type}`" role="status"><span>{{ toast.message }}</span><button class="btn-close btn-close-white ms-3" type="button" :aria-label="labels.close" @click="flashToasts = flashToasts.filter(item => item.id !== toast.id)"></button></div></div>
        <aside v-if="authenticated && showHeader" class="roo-sidebar" aria-label="Module" @mouseenter="sidebarHovered = true" @mouseleave="sidebarHovered = false">
            <div class="roo-sidebar-brand"><a class="roo-brand" :href="authenticated ? '/dashboard' : '/'"><img class="roo-sidebar-icon" :src="icon" alt="Roo – Religionsunterricht organisieren"><span v-if="sidebarExpanded" class="roo-sidebar-name">Roo</span></a><button class="btn btn-sm btn-link roo-sidebar-toggle" type="button" :aria-label="sidebarPinned ? 'Navigation lösen' : 'Navigation anheften'" :title="sidebarPinned ? 'Navigation lösen' : 'Navigation anheften'" @click="sidebarPinned = !sidebarPinned"><i :class="sidebarPinned ? 'bi bi-pin-angle-fill' : 'bi bi-pin-angle'" aria-hidden="true"></i></button></div>
            <nav class="roo-module-nav" :aria-label="'Hauptnavigation – ' + labels.modules">
                <template v-for="group in moduleGroups" :key="group.title">
                    <div class="roo-nav-heading"><span>{{ group.title }}</span></div>
                    <template v-for="item in group.items" :key="item.label">
                        <a v-if="item.enabled" class="roo-nav-link" :href="item.url"><i :class="['bi', item.icon]" aria-hidden="true"></i><span>{{ item.label }}</span></a>
                        <span v-else class="roo-nav-link roo-nav-link-disabled" aria-disabled="true"><i :class="['bi', item.icon]" aria-hidden="true"></i><span>{{ item.label }}</span></span>
                    </template>
                </template>
            </nav>
        </aside>
        <div class="roo-workspace">
            <header v-if="showHeader" class="roo-app-header sticky-top">
                <nav class="roo-topbar" aria-label="Werkzeugleiste">
                    <button v-if="authenticated" class="btn btn-sm btn-outline-secondary d-lg-none" type="button" aria-label="Navigation einblenden" @click="mobileSidebarOpen = !mobileSidebarOpen"><i class="bi bi-list" aria-hidden="true"></i></button>
                    <a v-if="!authenticated && showBrand" class="roo-brand" href="/"><img :src="logo" alt="Roo – Religionsunterricht organisieren"></a>
                    <div class="roo-topbar-left d-flex align-items-center gap-2"><slot name="toolbar"></slot></div>
                    <div class="ms-auto d-flex align-items-center gap-2">
                        <form v-if="authenticated" class="roo-global-search" method="get" action="/suche" role="search" @submit="globalSearchOpen = false">
                            <label class="visually-hidden" for="roo-global-search-input">{{ labels.globalSearch }}</label>
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <input id="roo-global-search-input" v-model="globalSearchQuery" name="q" type="search" :placeholder="labels.globalSearch" autocomplete="off" @focus="globalSearchOpen = Boolean(globalSearchQuery.trim())">
                            <div v-if="globalSearchOpen && presentGlobalSearchGroups.length" class="roo-global-search-results" role="listbox">
                                <template v-for="group in presentGlobalSearchGroups" :key="group.key">
                                    <div class="small text-muted px-3 pt-2 pb-1">{{ group.label }}</div>
                                    <a v-for="item in globalSearchResults[group.key]" :key="item.id" :href="group.href(item)" role="option" @click="globalSearchOpen = false">{{ group.title(item) }}</a>
                                </template>
                                <button type="submit" class="btn btn-link btn-sm w-100 text-start px-3">{{ labels.search }} …</button>
                            </div>
                        </form>
                        <details v-if="authenticated" class="roo-profile-menu">
                            <summary class="btn btn-sm btn-light d-flex align-items-center gap-2"><span class="roo-avatar">{{ userInitials }}</span><span class="d-none d-sm-inline">{{ currentUser?.name }}</span><i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
                            <div class="roo-profile-dropdown"><a href="/profil"><i class="bi bi-gear" aria-hidden="true"></i>Einstellungen</a><form method="post" action="/logout"><input type="hidden" name="_token" :value="csrfToken"><button type="submit"><i class="bi bi-box-arrow-right" aria-hidden="true"></i>Abmelden</button></form></div>
                        </details>
                        <img v-else-if="showBrand" class="roo-brand-mark" :src="icon" alt="">
                    </div>
                </nav>
            </header>
            <main class="roo-page"><slot /></main>
        </div>
    </div>
</template>
