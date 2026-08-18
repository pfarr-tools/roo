<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import de from '../../i18n/de'
defineProps({ groups: Array })
</script>
<template>
    <AppShell>
        <div class="container-full px-3 py-4"><h1 class="h2">{{ de.yearPlans }}</h1><p class="text-muted">{{ de.yearPlansIntro }}</p>
            <div v-if="!groups.length" class="alert alert-info">{{ de.noYearPlanGroups }}</div>
            <div v-for="group in groups" :key="group.id" class="card mb-3"><div class="card-body d-flex justify-content-between align-items-center gap-3"><div><h2 class="h5 mb-1">{{ group.name }}</h2><div class="text-muted">{{ group.school.name }} · {{ group.school_year.name }}</div></div><a class="btn btn-sm btn-outline-primary" :href="`/jahresplanung/${group.id}`">{{ de.planForGroup }}</a></div></div>
        </div>
    </AppShell>
</template>
