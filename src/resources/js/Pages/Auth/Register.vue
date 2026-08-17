<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import { useForm } from '@inertiajs/vue3'
import de from '../../i18n/de'

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' })

function submit() {
    form.post('/register')
}
</script>

<template>
    <AppShell :authenticated="false">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <h1 class="h2 mb-2">{{ de.register }}</h1>
                <p class="text-body-secondary">{{ de.registerIntro }}</p>
                <form class="card card-body shadow-sm" @submit.prevent="submit">
                    <label class="form-label" for="name">{{ de.name }}</label>
                    <input id="name" v-model="form.name" class="form-control mb-3" type="text" required autofocus autocomplete="name">
                    <label class="form-label" for="email">{{ de.email }}</label>
                    <input id="email" v-model="form.email" class="form-control mb-3" type="email" required autocomplete="username">
                    <label class="form-label" for="password">{{ de.password }}</label>
                    <input id="password" v-model="form.password" class="form-control mb-3" type="password" required autocomplete="new-password">
                    <label class="form-label" for="password_confirmation">{{ de.passwordConfirmation }}</label>
                    <input id="password_confirmation" v-model="form.password_confirmation" class="form-control mb-3" type="password" required autocomplete="new-password">
                    <button class="btn btn-primary" type="submit" :disabled="form.processing">{{ de.register }}</button>
                </form>
                <p class="mt-3">{{ de.existingAccount }} <a href="/login">{{ de.login }}</a></p>
            </div>
        </div>
    </div>
    </AppShell>
</template>
