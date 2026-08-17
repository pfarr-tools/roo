<script setup>
import logo from '../../../images/branding/roo-logo.png'
import icon from '../../../images/branding/roo-icon.png'

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''

defineProps({
    authenticated: { type: Boolean, default: true },
    showBrand: { type: Boolean, default: true },
    showHeader: { type: Boolean, default: true },
})
</script>

<template>
    <header v-if="showHeader" :class="['roo-app-header sticky-top', { 'roo-app-header-compact': !showBrand }]">
        <nav class="container d-flex align-items-center justify-content-between py-3" aria-label="Hauptnavigation">
            <a v-if="showBrand" class="roo-brand" href="/">
                <img :src="logo" alt="Roo – Religionsunterricht organisieren">
            </a>
            <div v-if="authenticated" class="d-flex align-items-center gap-2">
                <a class="btn btn-sm btn-link text-decoration-none" href="/dashboard">Übersicht</a>
                <a class="btn btn-sm btn-outline-primary" href="/schulen">Schulen</a>
                <form method="post" action="/logout" class="d-inline">
                    <input type="hidden" name="_token" :value="csrfToken">
                    <button class="btn btn-sm btn-outline-secondary" type="submit">Abmelden</button>
                </form>
            </div>
            <img v-else-if="showBrand" class="roo-brand-mark" :src="icon" alt="">
        </nav>
    </header>
    <main class="roo-page">
        <slot />
    </main>
</template>
