// @vitest-environment happy-dom

import { createApp, nextTick } from 'vue'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
    router: { post: vi.fn(), put: vi.fn(), delete: vi.fn() },
    useForm: vi.fn(initial => ({ ...initial, processing: false, put: vi.fn(), post: vi.fn() })),
}))
vi.mock('../../resources/js/Components/Ui/AppShell.vue', () => ({
    default: { template: '<div><slot name="toolbar" /><slot /></div>' },
}))
vi.mock('../../resources/js/utils/confirmation', () => ({ requestConfirmation: vi.fn() }))

import CurriculumShow from '../../resources/js/Pages/Curricula/Show.vue'

function mount() {
    const root = document.createElement('div')
    document.body.append(root)
    const app = createApp(CurriculumShow, {
        curriculum: { id: 1, title: 'Curriculum', grades: [1], denominations: [] },
        version: { is_editable: true, bindings: [], topics: [{ id: 2, title: 'Thema', year: 1, competencies: [] }] },
        educationPlans: [],
        schoolTypes: [],
        canToggleEditing: false,
    })
    app.mount(root)

    return { app, root, unmount: () => { app.unmount(); root.remove() } }
}

describe('curriculum competency dialog', () => {
    it('does not access processing on a missing competency form', async () => {
        const { app, root, unmount } = mount()
        await nextTick()

        root.querySelector('[title="Inhaltsbezogene Kompetenzen"]').click()
        await nextTick()
        app._instance.setupState.competencyForm = null

        await expect(nextTick()).resolves.toBeUndefined()
        expect(root.querySelector('button.btn-primary')).not.toBeNull()

        unmount()
    })
})
