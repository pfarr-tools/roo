<template><div class="tab-content"><slot /></div></template>

<script setup>
defineProps({ activeTab: { type: String, required: true } })
</script>
