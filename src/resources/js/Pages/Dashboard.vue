<script setup>
import de from '../i18n/de'

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
</script>

<template>
    <main class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2 mb-0">{{ de.dashboard }}</h1>
            <form method="post" action="/logout">
                <input type="hidden" name="_token" :value="csrfToken">
                <button class="btn btn-outline-secondary" type="submit">{{ de.logout }}</button>
            </form>
        </div>
        <div class="alert alert-primary" role="status">{{ de.dashboardIntro }}</div>
        <a href="/schulen" class="btn btn-primary">{{ de.schools }}</a>
    </main>
</template>
