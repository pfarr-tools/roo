<template>
    <li class="nav-item" role="presentation">
        <button :id="`${id}-tab`" class="nav-link" :class="{ active: activeTab === id }" type="button" role="tab" :aria-selected="activeTab === id" :aria-controls="`${id}-panel`" @click="$emit('select', id)">
            <i v-if="icon" class="me-1" :class="`bi bi-${icon}`" aria-hidden="true"></i>{{ title }}
            <span v-if="count !== undefined" class="badge rounded-pill text-bg-secondary ms-1">{{ count }}</span>
        </button>
    </li>
</template>

<script setup>
defineProps({ id: { type: String, required: true }, title: { type: String, required: true }, activeTab: { type: String, required: true }, count: { type: [Number, String], default: undefined }, icon: { type: String, default: '' } })
defineEmits(['select'])
</script>
