// @vitest-environment happy-dom

import { createApp, nextTick, reactive } from 'vue'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
    useForm: vi.fn(initial => reactive({ ...initial, processing: false, put: vi.fn() })),
}))
vi.mock('../../resources/js/Components/Ui/AppShell.vue', () => ({
    default: { template: '<div><slot name="toolbar" /><slot /></div>' },
}))

import EvaluationEdit from '../../resources/js/Pages/Evaluations/Edit.vue'

function mount() {
    const root = document.createElement('div')
    document.body.append(root)
    const app = createApp(EvaluationEdit, {
        group: { id: 1 },
        evaluation: { id: 2, status: 'draft', draft_text: '', teacher_note: '', student: { first_name: 'Mia', last_name: 'Muster' }, period: { label: '1. Halbjahr' }, observation_scales: [] },
        customProcessCompetences: [{ id: 7, text: 'Wahrnehmen', position: 1 }],
        customProcessCompetenceScaleIntervalCount: 4,
        competenceAverages: [{ custom_process_competence_id: 7, average: 2.5, rounded_level: 3 }],
    })
    app.mount(root)

    return { root, unmount: () => { app.unmount(); root.remove() } }
}

describe('evaluation edit competence averages', () => {
    it('highlights the scale level matching the rounded competence average', async () => {
        const { root, unmount } = mount()
        await nextTick()

        const buttons = [...root.querySelectorAll('.border-top .btn-sm')]
        expect(buttons[2].textContent).toBe('+++')
        expect(buttons[2].classList).toContain('bg-primary-subtle')
        expect(buttons[0].classList).not.toContain('bg-primary-subtle')

        unmount()
    })

    it('keeps the active scale level primary when it matches the average', async () => {
        const { root, unmount } = mount()
        await nextTick()

        root.querySelectorAll('.border-top .btn-sm')[2].click()
        await nextTick()

        const activeButton = root.querySelectorAll('.border-top .btn-sm')[2]
        expect(activeButton.classList).toContain('btn-primary')
        expect(activeButton.classList).not.toContain('bg-primary-subtle')

        unmount()
    })
})
