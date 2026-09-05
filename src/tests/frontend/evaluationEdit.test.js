// @vitest-environment happy-dom

import { createApp, h, nextTick, reactive } from 'vue'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
    useForm: vi.fn(initial => reactive({ ...initial, processing: false, put: vi.fn() })),
}))
vi.mock('../../resources/js/Components/Ui/AppShell.vue', () => ({
    default: { template: '<div><div data-testid="toolbar"><slot name="toolbar" /></div><slot /></div>' },
}))

import EvaluationEdit from '../../resources/js/Pages/Evaluations/Edit.vue'

function mount() {
    const root = document.createElement('div')
    document.body.append(root)
    const props = {
        group: { id: 1, grading_model: 'observation_scales' },
        evaluation: { id: 2, status: 'draft', draft_text: '', teacher_note: '', student: { first_name: 'Mia', last_name: 'Muster' }, period: { label: '1. Halbjahr' }, observation_scales: [] },
        customProcessCompetences: [{ id: 7, text: 'Wahrnehmen', position: 1 }],
        customProcessCompetenceScaleIntervalCount: 4,
        competenceAverages: [{ custom_process_competence_id: 7, average: 2.5, rounded_level: 3 }],
        previousEvaluation: { id: 1, student_name: 'Albrecht, Anna' },
        nextEvaluation: { id: 3, student_name: 'Zimmer, Zoe' },
    }
    const state = reactive({ evaluation: props.evaluation })
    const app = createApp({
        setup: () => () => h(EvaluationEdit, { ...props, evaluation: state.evaluation }),
    })
    app.mount(root)

    return { root, state, unmount: () => { app.unmount(); root.remove() } }
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

    it('places previous and next students after save and hides the draft text for observation scales', async () => {
        const { root, unmount } = mount()
        await nextTick()

        expect(root.querySelector('#draft-text')).toBeNull()
        expect([...root.querySelectorAll('[data-testid="toolbar"] a, [data-testid="toolbar"] button')].map(element => element.textContent.trim())).toEqual(['', 'Speichern', '← Vorherige:r', 'Nächste:r →'])
        expect(root.querySelector('[data-testid="toolbar"] a[aria-label="Schließen"]')?.getAttribute('href')).toBe('/bewertungen?group=1')
        expect(root.querySelector('[data-testid="toolbar"] a[href="/unterrichtsgruppen/1/bewertungen/1/bearbeiten"]')).not.toBeNull()
        expect(root.querySelector('[data-testid="toolbar"] a[href="/unterrichtsgruppen/1/bewertungen/3/bearbeiten"]')).not.toBeNull()

        unmount()
    })

    it('refreshes the scale selection when Inertia replaces the evaluation props', async () => {
        const { root, state, unmount } = mount()
        await nextTick()

        state.evaluation = { id: 3, status: 'draft', draft_text: '', teacher_note: '', student: { first_name: 'Zoe', last_name: 'Zimmer' }, period: { label: '1. Halbjahr' }, observation_scales: [{ custom_process_competence_id: 7, custom_scale_level: 1, custom_scale_status: null }] }
        await nextTick()

        const buttons = [...root.querySelectorAll('.border-top .btn-sm')]
        expect(buttons[0].classList).toContain('btn-primary')
        expect(buttons[2].classList).not.toContain('btn-primary')

        unmount()
    })
})
