<script setup>
import AppShell from '../Components/Ui/AppShell.vue'
import de from '../i18n/de'
import logo from '../../images/branding/roo-logo.png'
</script>

<template>
    <AppShell :authenticated="false" :show-brand="false" :show-header="false">
        <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-xl-8">
                <div class="roo-hero p-4 p-md-5 rounded-4">
                    <img class="roo-splash-logo mb-4" :src="logo" alt="Roo – Religionsunterricht organisieren">
                    <span class="roo-kicker d-block mb-3">{{ de.appName }}</span>
                    <h1 class="display-5 fw-semibold">Religionsunterricht planen</h1>
                    <p class="lead">{{ de.dashboardIntro }}</p>
                    <div class="d-flex gap-2">
                        <a class="btn btn-primary" href="/login">{{ de.login }}</a>
                        <a class="btn btn-outline-secondary" href="/register">{{ de.register }}</a>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </AppShell>
</template>
