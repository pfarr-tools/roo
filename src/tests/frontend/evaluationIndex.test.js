// @vitest-environment happy-dom

import { createApp, nextTick } from 'vue'
import { describe, expect, it, vi } from 'vitest'

const { get } = vi.hoisted(() => ({ get: vi.fn() }))
vi.mock('../../resources/js/Components/Ui/AppShell.vue', () => ({
    default: { template: '<div><div data-testid="toolbar"><slot name="toolbar" /></div><slot /></div>' },
}))
vi.mock('@inertiajs/vue3', () => ({ router: { get } }))

import EvaluationIndex from '../../resources/js/Pages/Evaluations/Index.vue'

function mount() {
    const root = document.createElement('div')
    document.body.append(root)
    const app = createApp(EvaluationIndex, {
        groups: [{ id: 1, name: '4a' }, { id: 2, name: '5a' }],
        group: { id: 2, name: '5a' },
        reportPeriods: [{ id: 3, label: '1. Halbjahr', evaluations: [] }],
    })
    app.mount(root)

    return { root, unmount: () => { app.unmount(); root.remove() } }
}

function mountWithEvaluation({ grading_model = 'competency_texts_and_grades', result_percentage = 83, result_grade = '2', draft_text = 'Du kannst dich ausdrücken.', observation_scales = [] } = {}) {
    const root = document.createElement('div')
    document.body.append(root)
    const app = createApp(EvaluationIndex, {
        groups: [{ id: 1, name: '4a', grading_model }],
        group: { id: 1, name: '4a', grading_model },
        reportPeriods: [{
            id: 3,
            label: '1. Halbjahr',
            evaluations: [{
                id: 4,
                status: 'confirmed',
                draft_text,
                result_percentage,
                result_grade,
                observation_scales,
                student: { first_name: 'Ada', last_name: 'Lovelace', receives_grades: false },
            }],
        }],
    })
    app.mount(root)

    return { root, unmount: () => { app.unmount(); root.remove() } }
}

describe('evaluation index', () => {
    it('shows group selection and period creation in the topbar without a content card', async () => {
        const { root, unmount } = mount()
        await nextTick()

        expect(root.querySelector('#evaluations-group').value).toBe('2')
        expect(root.querySelector('#evaluations-group').options).toHaveLength(2)
        expect(root.querySelector('a[href="/unterrichtsgruppen/2/bewertungen/neu"]')).not.toBeNull()
        const createPeriodLink = root.querySelector('a[href="/unterrichtsgruppen/2/bewertungen/neu"]')
        expect(createPeriodLink.textContent.trim()).toBe('Zeitraum')
        expect(createPeriodLink.classList).toContain('d-inline-flex')
        expect(createPeriodLink.classList).toContain('align-items-center')
        expect(createPeriodLink.querySelector('i.bi-plus-lg')).not.toBeNull()
        expect(createPeriodLink.querySelector('i.bi-plus-lg').classList).toContain('d-inline')
        expect(root.querySelector('.roo-page > .container-full .card')).toBeNull()
        expect(root.textContent).toContain('1. Halbjahr')

        unmount()
    })

    it('changes groups through an Inertia request', async () => {
        const { root, unmount } = mount()
        await nextTick()

        const select = root.querySelector('#evaluations-group')
        select.value = '1'
        select.dispatchEvent(new Event('change', { bubbles: true }))

        expect(get).toHaveBeenCalledWith('/unterrichtsgruppen/1/bewertungen', {}, { preserveState: true, preserveScroll: true })

        unmount()
    })

    it('keeps the draft text and adds the result for confirmed competency evaluations', async () => {
        const { root, unmount } = mountWithEvaluation()
        await nextTick()

        expect(root.textContent).toContain('Du kannst dich ausdrücken.')
        expect(root.textContent).toContain('83%')
        expect(root.textContent).toContain('(2)')

        unmount()
    })

    it('shows only results without text for grades-only evaluations', async () => {
        const { root, unmount } = mountWithEvaluation({ grading_model: 'grades_only' })
        await nextTick()

        expect(root.textContent).not.toContain('Du kannst dich ausdrücken.')
        expect(root.textContent).toContain('83%')
        expect(root.textContent).toContain('(2)')

        unmount()
    })

    it('shows observation scale texts and results one per line', async () => {
        const { root, unmount } = mountWithEvaluation({
            grading_model: 'observation_scales',
            observation_scales: [
                { competence_text_snapshot: 'arbeitet selbstständig', custom_scale_level: 3, custom_scale_status: null, interval_count_snapshot: 4 },
                { competence_text_snapshot: 'übernimmt Verantwortung', custom_scale_level: null, custom_scale_status: 'ne', interval_count_snapshot: 4 },
            ],
        })
        await nextTick()

        const rows = [...root.querySelectorAll('.evaluation-observation-scale')]
        expect(rows[0].cells[0].textContent.trim()).toBe('Ada kann arbeitet selbstständig')
        expect(rows[0].cells[1].textContent.trim()).toBe('+++')
        expect(rows[1].cells[0].textContent.trim()).toBe('Ada kann übernimmt Verantwortung')
        expect(rows[1].cells[1].textContent.trim()).toBe('ne')
        expect(root.textContent).not.toContain('Noch kein Bewertungsentwurf.')
        expect(root.querySelectorAll('.evaluation-observation-scale')).toHaveLength(2)
        expect(root.querySelector('.evaluation-observation-scales').classList).toContain('w-100')
        expect(root.querySelector('.evaluation-observation-scales').getAttribute('style')).toBe('table-layout: fixed;')
        expect(root.querySelector('.evaluation-observation-scales col:first-child').getAttribute('style')).toBe('width: 90%;')
        expect(root.querySelector('.evaluation-observation-scales col:last-child').getAttribute('style')).toBe('width: 10%;')

        unmount()
    })
})
