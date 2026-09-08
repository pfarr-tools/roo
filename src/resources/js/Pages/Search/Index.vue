<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import de from '../../i18n/de'
import { computed } from 'vue'

const props = defineProps({ query: String, results: Object })
const groups = [
    { key: 'schools', label: de.schools, title: item => item.name, subtitle: item => item.city, href: item => `/schulen/${item.slug}` },
    { key: 'groups', label: de.teachingGroups, title: item => item.name, subtitle: item => item.school?.name, href: item => `/unterrichtsgruppen/${item.id}` },
    { key: 'curricula', label: de.curricula, title: item => item.title, subtitle: item => item.external_identifier, href: item => `/curricula/${item.id}` },
    { key: 'educationPlans', label: de.educationPlans, title: item => item.title, subtitle: item => item.external_identifier, href: item => `/bildungsplaene/${item.id}` },
    { key: 'students', label: de.students, title: item => `${item.last_name}, ${item.first_name}`, subtitle: item => `${item.class_name} · ${item.school?.name}`, href: item => `/schueler:innen/${item.id}` },
    { key: 'teachingUnits', label: de.teachingUnits, title: item => item.title, subtitle: item => item.group?.name, href: () => '/unterrichtseinheiten' },
    { key: 'unitTemplates', label: de.unitTemplates, title: item => item.title, subtitle: item => item.description, href: () => '/unterrichtseinheiten' },
    { key: 'lessonTemplates', label: de.lessonTemplates, title: item => item.title, subtitle: item => item.objective, href: () => '/unterrichtseinheiten' },
    { key: 'phaseTemplates', label: de.phaseTemplates, title: item => item.title, subtitle: item => item.material, href: () => '/unterrichtseinheiten' },
    { key: 'songs', label: de.songs, title: item => item.title, subtitle: item => [item.composer, item.author].filter(Boolean).join(' · '), href: () => '/bibliothek' },
    { key: 'assessmentTasks', label: de.assessmentTasks, title: item => item.title, subtitle: item => [item.task_type, item.level].filter(Boolean).join(' · '), href: () => '/bibliothek' },
    { key: 'schoolYears', label: de.schoolYears, title: item => item.name, subtitle: item => item.school?.name, href: item => `/schulen/${item.school?.slug}/${item.slug}` },
    { key: 'assessments', label: de.learningAssessments, title: item => item.title, subtitle: item => item.group?.name, href: item => `/unterrichtsgruppen/${item.teaching_group_id}/lernstandserhebungen/${item.id}/bearbeiten` },
    { key: 'files', label: de.files, title: item => item.original_name, subtitle: item => item.description || item.mime_type, href: () => '/bibliothek' },
    { key: 'links', label: de.links, title: item => item.title, subtitle: item => item.url, href: item => item.url },
    { key: 'materials', label: de.materials, title: item => item.name, subtitle: item => [item.material_number, item.storage_location].filter(Boolean).join(' · '), href: () => '/bibliothek' },
    { key: 'songVersions', label: de.songVersions, title: item => item.song?.title || item.name, subtitle: item => [item.song?.composer, item.song?.author].filter(Boolean).join(' · '), href: () => '/bibliothek' },
]
const presentGroups = computed(() => groups.filter(group => props.query && props.results?.[group.key]?.length))
</script>

<template>
    <AppShell>
        <div class="container-full px-3 py-4">
            <h1 class="h2 mb-4">{{ de.search }}</h1>
            <form class="input-group mb-4" method="get" action="/suche" role="search"><input name="q" :value="query" class="form-control" :placeholder="de.globalSearch" autofocus><button class="btn btn-primary" type="submit"><i class="bi bi-search me-1" aria-hidden="true"></i>{{ de.search }}</button></form>
            <nav v-if="presentGroups.length" class="d-flex flex-wrap gap-2 mb-4" :aria-label="de.searchResultTypes"><a v-for="group in presentGroups" :key="group.key" class="badge rounded-pill text-bg-light text-decoration-none" :href="`#search-results-${group.key}`">{{ group.label }} <span class="badge rounded-pill text-bg-secondary">{{ results[group.key].length }}</span></a></nav>
            <div v-if="!query" class="alert alert-info">{{ de.globalSearchHint }}</div>
            <div v-else-if="!Object.values(results).some(items => items.length)" class="alert alert-info">{{ de.noSearchResults }}</div>
            <template v-for="group in groups" :key="group.key">
                <section v-if="query && results[group.key]?.length" :id="`search-results-${group.key}`" class="mb-4">
                    <h2 class="h5">{{ group.label }}</h2>
                    <div class="list-group">
                        <a v-for="item in results[group.key]" :key="item.id" class="list-group-item list-group-item-action" :href="group.href(item)">
                            <strong>{{ group.title(item) }}</strong>
                            <span class="d-block small text-muted">{{ group.subtitle(item) }}</span>
                        </a>
                    </div>
                </section>
            </template>
        </div>
    </AppShell>
</template>
