<template>
    <section :id="`${id}-panel`" class="tab-pane fade" :class="{ show: activeTab === id, active: activeTab === id }" role="tabpanel" :aria-labelledby="`${id}-tab`" :tabindex="activeTab === id ? 0 : -1" v-show="activeTab === id">
        <slot />
    </section>
</template>

<script setup>
defineProps({ id: { type: String, required: true }, activeTab: { type: String, required: true } })
</script>
