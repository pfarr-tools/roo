<script setup>
import { useForm } from '@inertiajs/vue3'
import de from '../../i18n/de'

const form = useForm({ email: '', password: '', remember: false })

function submit() {
    form.post('/login')
}
</script>

<template>
    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <h1 class="h2 mb-2">{{ de.login }}</h1>
                <p class="text-body-secondary">{{ de.loginIntro }}</p>
                <form class="card card-body shadow-sm" @submit.prevent="submit">
                    <label class="form-label" for="email">{{ de.email }}</label>
                    <input id="email" v-model="form.email" class="form-control mb-3" type="email" required autofocus autocomplete="username">
                    <div v-if="form.errors.email" class="invalid-feedback d-block">{{ form.errors.email }}</div>
                    <label class="form-label" for="password">{{ de.password }}</label>
                    <input id="password" v-model="form.password" class="form-control mb-3" type="password" required autocomplete="current-password">
                    <div v-if="form.errors.password" class="invalid-feedback d-block">{{ form.errors.password }}</div>
                    <div class="form-check mb-3">
                        <input id="remember" v-model="form.remember" class="form-check-input" type="checkbox">
                        <label class="form-check-label" for="remember">{{ de.rememberMe }}</label>
                    </div>
                    <button class="btn btn-primary" type="submit" :disabled="form.processing">{{ de.login }}</button>
                    <a class="mt-3" href="/forgot-password">{{ de.forgotPassword }}</a>
                </form>
                <p class="mt-3">{{ de.noAccount }} <a href="/register">{{ de.register }}</a></p>
            </div>
        </div>
    </main>
</template>
