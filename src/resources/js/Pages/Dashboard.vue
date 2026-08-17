<script setup>
import AppShell from '../Components/Ui/AppShell.vue'
import de from '../i18n/de'
</script>

<template>
    <AppShell>
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2 mb-0">{{ de.dashboard }}</h1>
            <span class="roo-kicker">{{ de.appName }}</span>
        </div>
        <div class="alert alert-primary" role="status">{{ de.dashboardIntro }}</div>
        <div class="d-flex gap-2">
            <a href="/schulen" class="btn btn-primary">{{ de.schools }}</a>
            <a href="/bildungsplaene" class="btn btn-outline-primary">{{ de.educationPlans }}</a>
        </div>
    </div>
    </AppShell>
</template>
