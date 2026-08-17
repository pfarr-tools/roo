<script setup>
defineProps({
    laravelVersion: {
        type: String,
        required: true,
    },
})
</script>

<template>
    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-xl-8">
                <div class="p-5 bg-body-tertiary rounded-4 border">
                    <span class="badge text-bg-primary mb-3">Roo</span>
                    <h1 class="display-5 fw-semibold">
                        Religionsunterricht planen
                    </h1>
                    <p class="lead">
                        Die technische Grundlage läuft. Als Nächstes folgen
                        Anmeldung, Schulen und Schuljahre.
                    </p>
                    <p class="text-body-secondary mb-0">
                        Laravel {{ laravelVersion }}
                    </p>
                </div>
            </div>
        </div>
    </main>
</template>
