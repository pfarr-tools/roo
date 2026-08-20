<script setup>
import { confirmationState, closeConfirmation } from '../../utils/confirmation'
</script>

<template>
    <div v-if="confirmationState.open" class="roo-modal-backdrop roo-confirmation-modal-backdrop" role="presentation" @click.self="closeConfirmation(false)">
        <section class="roo-modal card border-0" role="alertdialog" aria-modal="true" :aria-label="confirmationState.title">
            <div class="card-body">
                <h2 class="h5">{{ confirmationState.title }}</h2>
                <p class="mb-4 text-pre-wrap">{{ confirmationState.message }}</p>
                <div class="d-flex justify-content-end gap-2">
                    <button v-for="action in confirmationState.actions" :key="String(action.value)" class="btn" :class="`btn-${action.variant ?? 'secondary'}`" type="button" @click="closeConfirmation(action.value)">{{ action.label }}</button>
                </div>
            </div>
        </section>
    </div>
</template>
