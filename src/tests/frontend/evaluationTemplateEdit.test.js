// @vitest-environment happy-dom

import { createApp, nextTick, reactive } from 'vue'
import { describe, expect, it, vi } from 'vitest'

const { post } = vi.hoisted(() => ({
    post: vi.fn((url, data, options) => options.onSuccess({ props: { period: { evaluation_templates: [{ id: 4, original_text: 'Veralteter Vorschlag.', text: 'Neu erzeugter Vorschlag.' }] } } })),
}))

vi.mock('@inertiajs/vue3', () => ({
    useForm: vi.fn(initial => reactive({ ...initial, processing: false, put: vi.fn() })),
    router: { post },
}))
vi.mock('../../resources/js/Components/Ui/AppShell.vue', () => ({
    default: { template: '<div><slot name="toolbar" /><slot /></div>' },
}))

import EvaluationTemplateEdit from '../../resources/js/Pages/Evaluations/TemplateEdit.vue'

it('regenerates an edited proposal instead of restoring its saved text', async () => {
    const root = document.createElement('div')
    document.body.append(root)
    const app = createApp(EvaluationTemplateEdit, {
        group: { id: 1 },
        period: { id: 2, label: '1. Halbjahr', evaluation_templates: [{ id: 4, level: 'G', original_text: 'Original.', text: 'Bearbeitet.' }] },
    })
    app.mount(root)
    await nextTick()

    const textarea = root.querySelector('textarea')
    expect(textarea.value).toBe('Bearbeitet.')
    root.querySelector('button[type="button"]').click()
    await nextTick()
    expect(post).toHaveBeenCalledWith(
        '/unterrichtsgruppen/1/bewertungen/zeiträume/2/vorlage/4/zurücksetzen',
        {},
        expect.objectContaining({ preserveState: true }),
    )
    expect(textarea.value).toBe('Neu erzeugter Vorschlag.')

    app.unmount()
    root.remove()
})
