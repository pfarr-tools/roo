<script setup>
import de from '../i18n/de'
</script>

<template>
    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-xl-8">
                <div class="p-5 bg-body-tertiary rounded-4 border">
                    <span class="badge text-bg-primary mb-3">{{ de.appName }}</span>
                    <h1 class="display-5 fw-semibold">Religionsunterricht planen</h1>
                    <p class="lead">{{ de.dashboardIntro }}</p>
                    <div class="d-flex gap-2">
                        <a class="btn btn-primary" href="/login">{{ de.login }}</a>
                        <a class="btn btn-outline-secondary" href="/register">{{ de.register }}</a>
                    </div>
                </div>
            </div>
        </div>
    </main>
</template>
