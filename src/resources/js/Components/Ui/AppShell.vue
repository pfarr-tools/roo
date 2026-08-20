<script setup>
import logo from '../../../images/branding/roo-logo.png'
import icon from '../../../images/branding/roo-icon.png'
import ConfirmationModal from './ConfirmationModal.vue'
import de from '../../i18n/de'
import { computed, ref } from 'vue'

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
const sidebarPinned = ref(false)
const sidebarHovered = ref(false)
const mobileSidebarOpen = ref(false)
const labels = de
const sidebarExpanded = computed(() => sidebarPinned.value || sidebarHovered.value || mobileSidebarOpen.value)
const moduleGroups = [
    { title: labels.teaching, items: [
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
        { label: labels.observations, icon: 'bi-eye', enabled: false },
        { label: labels.assessments, icon: 'bi-bar-chart', enabled: false },
    ] },
    { title: labels.documentsAndAi, items: [
        { label: labels.documents, icon: 'bi-file-earmark-text', enabled: false },
        { label: labels.generation, icon: 'bi-file-earmark-arrow-down', enabled: false },
        { label: labels.aiAssistance, icon: 'bi-stars', enabled: false },
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
        <aside v-if="authenticated && showHeader" class="roo-sidebar" aria-label="Module" @mouseenter="sidebarHovered = true" @mouseleave="sidebarHovered = false">
            <div class="roo-sidebar-brand"><a class="roo-brand" :href="authenticated ? '/dashboard' : '/'"><img class="roo-sidebar-icon" :src="icon" alt="Roo – Religionsunterricht organisieren"><span v-if="sidebarExpanded" class="roo-sidebar-name">Roo</span></a><button class="btn btn-sm btn-link roo-sidebar-toggle" type="button" :aria-label="sidebarPinned ? 'Navigation lösen' : 'Navigation anheften'" :title="sidebarPinned ? 'Navigation lösen' : 'Navigation anheften'" @click="sidebarPinned = !sidebarPinned"><i :class="sidebarPinned ? 'bi bi-pin-angle-fill' : 'bi bi-pin-angle'" aria-hidden="true"></i></button></div>
            <nav class="roo-module-nav" :aria-label="'Hauptnavigation – ' + labels.modules">
                <a class="roo-nav-link" href="/dashboard"><i class="bi bi-grid-1x2" aria-hidden="true"></i><span>{{ labels.dashboard }}</span></a>
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
                        <form v-if="authenticated" class="roo-global-search" method="get" action="/suche" role="search">
                            <label class="visually-hidden" for="roo-global-search-input">{{ labels.globalSearch }}</label>
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <input id="roo-global-search-input" name="q" type="search" :placeholder="labels.globalSearch" autocomplete="off">
                        </form>
                        <details v-if="authenticated" class="roo-profile-menu">
                            <summary class="btn btn-sm btn-light d-flex align-items-center gap-2"><span class="roo-avatar">R</span><span class="d-none d-sm-inline">Profil</span><i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
                            <div class="roo-profile-dropdown"><a href="/profil"><i class="bi bi-person" aria-hidden="true"></i>Profil</a><a href="/profil"><i class="bi bi-gear" aria-hidden="true"></i>Einstellungen</a><form method="post" action="/logout"><input type="hidden" name="_token" :value="csrfToken"><button type="submit"><i class="bi bi-box-arrow-right" aria-hidden="true"></i>Abmelden</button></form></div>
                        </details>
                        <img v-else-if="showBrand" class="roo-brand-mark" :src="icon" alt="">
                    </div>
                </nav>
            </header>
            <main class="roo-page"><slot /></main>
        </div>
    </div>
</template>
