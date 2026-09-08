// @vitest-environment happy-dom

import { createApp, h, nextTick, reactive } from 'vue'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
    useForm: vi.fn(initial => reactive({ ...initial, processing: false, put: vi.fn() })),
}))
vi.mock('axios', () => ({
    default: { post: vi.fn(() => Promise.resolve({ data: { draft_text: 'Mia kann sehr sicher beschreiben.' } })) },
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

function mountCompetencyTextEvaluation() {
    const root = document.createElement('div')
    document.body.append(root)
    const app = createApp(EvaluationEdit, {
        group: { id: 1, grading_model: 'competency_texts_and_grades' },
        evaluation: { id: 2, status: 'draft', draft_text: 'Bewertungsentwurf', teacher_note: '', student: { first_name: 'Mia', last_name: 'Muster' }, period: { label: '1. Halbjahr' }, observation_scales: [] },
        lses: [
            { id: 11, title: 'Erste LSE', date: '2026-09-08', student_levels: ['G'], percentage: 80, grade: '2', receives_grades: false },
            { id: 12, title: 'Zweite LSE', date: '2026-10-02', student_levels: ['M', 'E'], percentage: 95, grade: '1-', receives_grades: true },
        ],
        gradeComponents: [{ type: 'written_assessments', label: 'Schriftliche Leistungen', weight: 50, percentage: 88, grade: '2-', sources: ['Erste LSE (G, 08.09.2026): 80% / 2'] }],
        periodLevel: 'G',
        competencies: [{ id: 8, identifier: '3.1.1.1', label: 'Menschliche Erfahrungen auf G-Niveau beschreiben', text: 'Menschliche Erfahrungen beschreiben', level_texts: { G: 'Menschliche Erfahrungen auf G-Niveau beschreiben', M: 'Menschliche Erfahrungen auf M-Niveau erklären', E: 'Menschliche Erfahrungen auf E-Niveau beurteilen' } }],
        competenceAverages: [{ teaching_unit_competency_id: 8, average: 3.5, rounded_level: 4, percentage: 70, sources: [{ type: 'written_assessments', title: 'Erste LSE', percentage: 80, text: 'Erste LSE (G, 08.09.2026): 80% / 2' }, { type: 'observations', title: 'Stunde · 08.09.2026', percentage: 40, text: 'Stunde: 40%' }] }],
    })
    app.mount(root)

    return { root, unmount: () => { app.unmount(); root.remove() } }
}

describe('evaluation edit competence averages', () => {
    it('hides competence descriptions and draft text for grades-only evaluations', async () => {
        const root = document.createElement('div')
        document.body.append(root)
        const app = createApp(EvaluationEdit, {
            group: { id: 1, grading_model: 'grades_only' },
            evaluation: { id: 2, status: 'draft', draft_text: 'Nicht anzeigen', teacher_note: '', student: { first_name: 'Mia', last_name: 'Muster' }, period: { label: '1. Halbjahr' }, observation_scales: [] },
            competencies: [{ id: 8, text: 'Kompetenz' }],
            gradeComponents: [{ type: 'written_assessments', label: 'Schriftliche Leistungen', weight: 100, percentage: 80, grade: '2', sources: [] }],
        })
        app.mount(root)
        await nextTick()

        expect(root.querySelector('#evaluation-competencies-table')).toBeNull()
        expect(root.querySelector('#draft-text')).toBeNull()
        expect(root.textContent).not.toContain('Nicht anzeigen')

        app.unmount()
        root.remove()
    })

    it('offers the average action for observation scales', async () => {
        const { root, unmount } = mount()
        await nextTick()

        expect([...root.querySelectorAll('[data-testid="toolbar"] button')].map(button => button.textContent.trim())).toContain('Alle Bewertungen auf Durchschnitt setzen')

        unmount()
    })

    it('shows the draft and a colored rating scale for competency text grading', async () => {
        const { root, unmount } = mountCompetencyTextEvaluation()
        await nextTick()

        expect(root.querySelector('#draft-text')?.value).toBe('Bewertungsentwurf')
        expect(root.querySelector('#draft-text').compareDocumentPosition(root.querySelector('#teacher-note')) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
        const buttons = [...root.querySelectorAll('#evaluation-competencies-table .btn-sm')]
        expect(buttons).toHaveLength(7)
        expect(buttons[4].classList).toContain('bg-primary-subtle')
        expect(buttons[0].classList).not.toContain('bg-primary-subtle')
        expect(buttons[6].classList).toContain('btn-primary')
        expect(root.textContent).toContain('Erste LSE (G, 08.09.2026): 80% / 2')
        expect(root.textContent).not.toContain('Lernstandserhebungen')
        expect(root.querySelector('#evaluation-competencies-table')).not.toBeNull()
        expect(root.textContent).toContain('Erste LSE (G, 08.09.2026): 80% / 2')
        expect(root.textContent).toContain('Stunde: 40%')
        expect(root.querySelector('#evaluation-level')?.value).toBe('G')
        expect(root.querySelector('#evaluation-competencies-table tbody td')?.textContent).toBe('Mia kann Menschliche Erfahrungen auf G-Niveau beschreiben.')

        root.querySelector('#evaluation-level').value = 'M'
        root.querySelector('#evaluation-level').dispatchEvent(new Event('change'))
        await nextTick()
        expect(root.querySelector('#evaluation-competencies-table tbody td')?.textContent).toBe('Mia kann Menschliche Erfahrungen auf M-Niveau erklären.')

        unmount()
    })

    it('allows selecting a final rating for a standard competence', async () => {
        const { root, unmount } = mountCompetencyTextEvaluation()
        await nextTick()

        const buttons = [...root.querySelectorAll('#evaluation-competencies-table .btn-sm')]
        expect(buttons[3].disabled).toBe(false)

        buttons[1].click()
        await nextTick()
        await Promise.resolve()
        await nextTick()

        expect(buttons[1].classList).toContain('btn-primary')
        expect(buttons[1].getAttribute('aria-pressed')).toBe('true')
        expect(buttons[3].classList).not.toContain('bg-primary-subtle')

        buttons[1].click()
        await nextTick()
        await Promise.resolve()
        await nextTick()

        expect(buttons[1].classList).not.toContain('btn-primary')
        expect(buttons[4].classList).toContain('bg-primary-subtle')

        unmount()
    })

    it('reformulates the draft from the selected competence rating', async () => {
        const { root, unmount } = mountCompetencyTextEvaluation()
        await nextTick()

        root.querySelectorAll('#evaluation-competencies-table .btn-sm')[5].click()
        await nextTick()
        await Promise.resolve()
        await nextTick()

        expect(root.querySelector('#draft-text')?.value).toBe('Mia kann sehr sicher beschreiben.')

        unmount()
    })

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
        expect([...root.querySelectorAll('[data-testid="toolbar"] a, [data-testid="toolbar"] button')].map(element => element.textContent.trim())).toEqual(['', 'Speichern', 'Speichern und bestätigen', 'Alle Bewertungen auf Durchschnitt setzen', '← Vorherige:r', 'Nächste:r →'])
        expect(root.querySelector('[data-testid="toolbar"] a[aria-label="Schließen"]')?.getAttribute('href')).toBe('/unterrichtsgruppen/1/bewertungen')
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
