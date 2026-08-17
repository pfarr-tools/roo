<script setup>
import de from '../../i18n/de'

const props = defineProps({ educationPlans: Array, search: String })

function formatDate(value) {
    if (!value) return de.noVersionDate
    const parts = String(value).slice(0, 10).split('-')
    return parts.length === 3 ? `${parts[2]}.${parts[1]}.${parts[0]}` : value
}
</script>

<template>
    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="/dashboard" class="text-decoration-none">{{ de.dashboard }}</a>
                <h1 class="h2 mt-2 mb-1">{{ de.educationPlans }}</h1>
                <p class="text-muted mb-0">{{ de.educationPlansIntro }}</p>
            </div>
            <a href="/schulen" class="btn btn-outline-secondary">{{ de.schools }}</a>
        </div>

        <form method="get" action="/bildungsplaene" class="input-group mb-4" role="search">
            <label for="education-plan-search" class="visually-hidden">{{ de.search }}</label>
            <input id="education-plan-search" name="q" :value="props.search" class="form-control" :placeholder="de.searchEducationPlans">
            <button class="btn btn-outline-primary" type="submit">{{ de.search }}</button>
        </form>

        <div v-if="!props.educationPlans.length" class="alert alert-info" role="status">
            {{ de.noEducationPlans }}
        </div>

        <div v-for="plan in props.educationPlans" :key="plan.id" class="card mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <h2 class="h5 mb-1"><a :href="`/bildungsplaene/${plan.id}`" class="text-decoration-none">{{ plan.title }}</a></h2>
                        <p class="text-muted mb-0">{{ plan.subject }} · {{ plan.external_identifier }}</p>
                    </div>
                    <span class="badge text-bg-light align-self-start">{{ [plan.country, plan.state].filter(Boolean).join(' · ') }}</span>
                </div>

                <div class="table-responsive mt-3">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ de.version }}</th>
                                <th>{{ de.versionDate }}</th>
                                <th>{{ de.status }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="version in plan.versions" :key="version.id">
                                <td>{{ version.external_identifier || version.title }}</td>
                                <td>{{ formatDate(version.version_date) }}</td>
                                <td>
                                    <span :class="['badge', version.is_complete ? 'text-bg-success' : 'text-bg-warning']">
                                        {{ version.is_complete ? de.complete : de.incomplete }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</template>
