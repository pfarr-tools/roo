<script setup>
import AppShell from '../../Components/Ui/AppShell.vue'
import { router, useForm } from '@inertiajs/vue3'
import de from '../../i18n/de'

const props = defineProps({ group: Object, period: Object })
const form = useForm({ templates: (props.period.evaluation_templates ?? []).map(template => ({ id: template.id, level: template.level, original_text: template.original_text, text: template.text })) })

function reset(template) {
    router.post(`/unterrichtsgruppen/${props.group.id}/bewertungen/zeiträume/${props.period.id}/vorlage/${template.id}/zurücksetzen`, {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: page => {
            const refreshedTemplate = page.props.period?.evaluation_templates?.find(item => item.id === template.id)
            if (refreshedTemplate) {
                template.text = refreshedTemplate.text
                template.original_text = refreshedTemplate.original_text
            }
        },
    })
}

function save() {
    form.put(`/unterrichtsgruppen/${props.group.id}/bewertungen/zeiträume/${props.period.id}/vorlage`)
}
</script>

<template>
    <AppShell>
        <template #toolbar>
            <a :href="`/unterrichtsgruppen/${group.id}?tab=evaluations`" class="btn btn-sm btn-light" :title="de.close" :aria-label="de.close"><i class="bi bi-x-lg" aria-hidden="true"></i></a>
            <button class="btn btn-sm btn-primary ms-2" type="submit" form="evaluation-template-form" :disabled="form.processing">{{ de.saveChanges }}</button>
        </template>
        <div class="container-full px-3 py-4">
            <h1 class="h2">{{ de.editEvaluationTemplates }}</h1>
            <p class="text-muted">{{ period.label }}</p>
            <form id="evaluation-template-form" @submit.prevent="save">
                <div v-if="!form.templates.length" class="text-muted">{{ de.noEvaluationTemplate }}</div>
                <section v-for="template in form.templates" :key="template.id" class="card card-body mb-3">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                        <label class="form-label mb-0" :for="`evaluation-template-${template.id}`">{{ template.level ?? de.proposedEvaluationText }}<template v-if="template.level">-Niveau</template></label>
                        <button class="btn btn-sm btn-outline-secondary" type="button" @click="reset(template)"><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>{{ de.resetProposal }}</button>
                    </div>
                    <textarea :id="`evaluation-template-${template.id}`" v-model="template.text" class="form-control" rows="8" required></textarea>
                </section>
            </form>
        </div>
    </AppShell>
</template>
