<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import de from '../../i18n/de'

defineProps({ query: String, results: Object })
const groups = [
    ['schools', de.schools, '/schulen/', 'name'],
    ['groups', de.teachingGroups, '/unterrichtsgruppen/', 'name'],
    ['curricula', de.curricula, '/curricula/', 'title'],
    ['educationPlans', de.educationPlans, '/bildungsplaene/', 'title'],
    ['students', de.students, '/schueler:innen', 'last_name'],
]
</script>

<template>
    <AppShell>
        <div class="container-full px-3 py-4">
            <h1 class="h2 mb-4">{{ de.search }}</h1>
            <form class="input-group mb-4" method="get" action="/suche" role="search"><input name="q" :value="query" class="form-control" :placeholder="de.globalSearch" autofocus><button class="btn btn-primary" type="submit"><i class="bi bi-search me-1" aria-hidden="true"></i>{{ de.search }}</button></form>
            <div v-if="!query" class="alert alert-info">{{ de.globalSearchHint }}</div>
            <div v-else-if="!Object.values(results).some(items => items.length)" class="alert alert-info">{{ de.noSearchResults }}</div>
            <section v-for="[key, label, path, titleKey] in groups" v-if="query && results[key]?.length" :key="key" class="mb-4">
                <h2 class="h5">{{ label }}</h2>
                <div class="list-group">
                    <a v-for="item in results[key]" :key="item.id" class="list-group-item list-group-item-action" :href="key === 'students' ? path : `${path}${item.slug ?? item.id}`">
                        <strong>{{ key === 'students' ? `${item.last_name}, ${item.first_name}` : item[titleKey] }}</strong>
                        <span class="d-block small text-muted">{{ key === 'students' ? `${item.class_name} · ${item.school?.name}` : (item.external_identifier || item.city || item.school?.name) }}</span>
                    </a>
                </div>
            </section>
        </div>
    </AppShell>
</template>
