<template>
    <ul class="nav nav-tabs roo-tab-headers" role="tablist" :aria-label="ariaLabel">
        <slot />
    </ul>
</template>

<script setup>
defineProps({ ariaLabel: { type: String, required: true } })
</script>
