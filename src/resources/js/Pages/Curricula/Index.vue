<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import de from '../../i18n/de'
const props = defineProps({ curricula: Array, search: String })
</script>
<template><AppShell><div class="container-full px-3 py-4">
    <div class="d-flex justify-content-between align-items-start mb-4"><div><a href="/dashboard">{{ de.dashboard }}</a><h1 class="h2 mt-2">{{ de.curricula }}</h1><p class="text-muted">{{ de.curriculaIntro }}</p></div><div class="d-flex gap-2"><a href="/curricula/vergleichen" class="btn btn-outline-primary">{{ de.compareCurricula }}</a><a href="/curricula/neu" class="btn btn-primary">{{ de.createCurriculum }}</a></div></div>
    <form method="get" action="/curricula" class="input-group mb-4"><input name="q" :value="search" class="form-control" placeholder="Titel oder Kennung suchen"><button class="btn btn-outline-primary">{{ de.search }}</button></form>
    <div v-if="!curricula.length" class="alert alert-info">{{ de.noCurricula }}</div>
    <div v-for="item in curricula" :key="item.id" class="card mb-3"><div class="card-body"><div class="d-flex justify-content-between"><div><h2 class="h5"><a :href="`/curricula/${item.id}`">{{ item.title }}</a></h2><div class="text-muted">{{ [item.school_type, item.grades?.join(', ')].filter(Boolean).join(' · ') }}<span v-if="item.derived_from_id"> · eigenes Curriculum</span></div></div><span class="badge text-bg-light">{{ item.versions?.[0]?.topics_count ?? 0 }} {{ de.units }}</span></div></div></div>
</div></AppShell></template>
