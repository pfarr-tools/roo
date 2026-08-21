<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import de from '../../i18n/de'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
    user: { type: Object, required: true },
    integrations: { type: Object, default: () => ({}) },
})

const form = useForm({ name: props.user.name, email: props.user.email, openai_api_key: '', flux_api_key: '' })
</script>

<template>
    <AppShell>
        <div class="container-fluid px-3 py-4">
            <h1 class="h2 mb-4">{{ de.profile }}</h1>
            <form class="row g-4" @submit.prevent="form.put('/profil')">
                <section class="col-12 col-xl-7" aria-labelledby="profile-data-heading">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h2 id="profile-data-heading" class="h5">{{ de.profileData }}</h2>
                            <p class="text-muted">{{ de.profileDataIntro }}</p>
                            <div class="mb-3"><label class="form-label" for="profile-name">{{ de.name }}</label><input id="profile-name" v-model="form.name" class="form-control" :class="{ 'is-invalid': form.errors.name }" autocomplete="name"><div v-if="form.errors.name" class="invalid-feedback">{{ form.errors.name }}</div></div>
                            <div class="mb-3"><label class="form-label" for="profile-email">E-Mail-Adresse</label><input id="profile-email" v-model="form.email" class="form-control" :class="{ 'is-invalid': form.errors.email }" type="email" autocomplete="email"><div v-if="form.errors.email" class="invalid-feedback">{{ form.errors.email }}</div></div>
                        </div>
                    </div>
                </section>
                <section class="col-12 col-xl-7" aria-labelledby="integrations-heading">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h2 id="integrations-heading" class="h5">{{ de.integrations }}</h2>
                            <p class="text-muted">{{ de.integrationsIntro }}</p>
                            <div class="mb-3"><label class="form-label" for="openai-api-key">{{ de.openaiApiKey }}</label><div class="input-group"><input id="openai-api-key" v-model="form.openai_api_key" class="form-control" :class="{ 'is-invalid': form.errors.openai_api_key }" type="password" autocomplete="new-password" :placeholder="integrations.openai ? de.apiKeyStored : 'sk-…'"><span v-if="integrations.openai" class="input-group-text text-success" :title="de.apiKeySaved"><i class="bi bi-check-circle" aria-hidden="true"></i></span></div><div v-if="form.errors.openai_api_key" class="text-danger small mt-1">{{ form.errors.openai_api_key }}</div></div>
                            <div class="mb-3"><label class="form-label" for="flux-api-key">{{ de.fluxApiKey }}</label><div class="input-group"><input id="flux-api-key" v-model="form.flux_api_key" class="form-control" :class="{ 'is-invalid': form.errors.flux_api_key }" type="password" autocomplete="new-password" :placeholder="integrations.flux ? de.apiKeyStored : de.apiKeyPlaceholder"><span v-if="integrations.flux" class="input-group-text text-success" :title="de.apiKeySaved"><i class="bi bi-check-circle" aria-hidden="true"></i></span></div><div v-if="form.errors.flux_api_key" class="text-danger small mt-1">{{ form.errors.flux_api_key }}</div></div>
                            <button class="btn btn-primary" type="submit" :disabled="form.processing"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ de.saveChanges }}</button>
                        </div>
                    </div>
                </section>
            </form>
        </div>
    </AppShell>
</template>
