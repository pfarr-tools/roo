<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import de from '../../i18n/de'
const props = defineProps({ curricula: Array, search: String })
</script>
<template><AppShell>
    <template #toolbar><div class="d-flex align-items-center gap-2"><a href="/curricula/vergleichen" class="btn btn-sm btn-outline-primary"><i class="bi bi-columns-gap me-1" aria-hidden="true"></i>{{ de.compareCurricula }}</a><a href="/curricula/neu" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ de.createCurriculum }}</a></div></template>
    <div class="container-full px-3 py-4">
    <div class="mb-4"><h1 class="h2">{{ de.curricula }}</h1></div>
    <form method="get" action="/curricula" class="input-group mb-4"><input name="q" :value="search" class="form-control" placeholder="Titel oder Kennung suchen"><button class="btn btn-outline-primary">{{ de.search }}</button></form>
    <div v-if="!curricula.length" class="alert alert-info">{{ de.noCurricula }}</div>
    <div v-for="item in curricula" :key="item.id" class="card mb-3"><div class="card-body"><div class="d-flex justify-content-between"><div><h2 class="h5"><a :href="`/curricula/${item.id}`">{{ item.title }}</a></h2><div class="text-muted">{{ [item.school_type, item.grades?.join(', ')].filter(Boolean).join(' · ') }}<span v-if="item.derived_from_id"> · eigenes Curriculum</span></div></div><span class="badge text-bg-light">{{ item.versions?.[0]?.topics_count ?? 0 }} {{ de.units }}</span></div></div></div>
</div></AppShell></template>
