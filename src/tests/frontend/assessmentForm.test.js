// @vitest-environment happy-dom

import { createApp, nextTick, reactive } from 'vue'
import { describe, expect, it, vi } from 'vitest'

const { forms } = vi.hoisted(() => ({ forms: [] }))
vi.mock('@inertiajs/vue3', () => ({
    useForm: vi.fn(initial => {
        const form = reactive({ ...initial, errors: {}, processing: false, put: vi.fn(), post: vi.fn() })
        forms.push(form)
        return form
    }),
}))
vi.mock('../../resources/js/Components/Ui/AppShell.vue', () => ({
    default: { template: '<div><slot name="toolbar" /><slot /></div>' },
}))

import AssessmentForm from '../../resources/js/Pages/Assessments/Form.vue'

function mount(assessmentTasks = [
    { id: 10, title: 'Erste Aufgabe', max_points: 4, competency: 'Kompetenz', checked: true, position: 1, weight: 50 },
    { id: 11, title: 'Zweite Aufgabe', max_points: 8, competency: 'Kompetenz', checked: true, position: 2, weight: 50 },
]) {
    forms.length = 0
    const root = document.createElement('div')
    document.body.append(root)
    const app = createApp(AssessmentForm, {
        group: { id: 1 },
        assessment: { id: 2, title: 'LSE' },
        assessmentTasks,
    })
    app.mount(root)

    return { root, form: forms[0], unmount: () => { app.unmount(); root.remove() } }
}

describe('Assessment form task list', () => {
    it('persists the selected levels when saving a differentiated assessment', async () => {
        const { root, form, unmount } = mount([
            { id: 10, title: 'Differenzierte Aufgabe', max_points: 4, competency: 'Kompetenz', levels: ['G', 'M', 'E'], checked: true, position: 1, weight: 50 },
        ])
        await nextTick()

        root.querySelector('#assessment-form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))

        expect(form.tasks).toEqual([{ task_id: 10, levels: ['G', 'M', 'E'], weight: 50 }])
        unmount()
    })

    it('removes the export actions and persists reordered tasks with their weights', async () => {
        const { root, form, unmount } = mount()
        await nextTick()

        expect(root.querySelector('a[href*="/download"]')).toBeNull()
        expect(root.querySelector('a[href*="/auswertung"]')).toBeNull()
        expect(root.querySelectorAll('.assessment-task-drag-handle')).toHaveLength(2)
        expect(root.querySelector('.assessment-task-weight-value')).toBeNull()
        expect([...root.querySelectorAll('input[type="range"]')].map(input => input.value)).toEqual(['50', '50'])

        const sliders = [...root.querySelectorAll('input[type="range"]')]
        sliders[0].value = '0'
        sliders[0].dispatchEvent(new Event('input', { bubbles: true }))
        const rows = [...root.querySelectorAll('tbody tr')]
        rows[1].querySelector('.assessment-task-drag-handle').dispatchEvent(new Event('dragstart', { bubbles: true }))
        rows[0].dispatchEvent(new Event('drop', { bubbles: true }))
        await nextTick()
        root.querySelector('#assessment-form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))

        expect(form.tasks).toEqual([{ task_id: 11, weight: 50 }, { task_id: 10, weight: 0 }])
        expect(form.put).toHaveBeenCalledWith('/unterrichtsgruppen/1/lernstandserhebungen/2')

        unmount()
    })

    it('synchronizes a changed weight before saving', async () => {
        const { root, form, unmount } = mount([
            { id: 10, title: 'Aufgabe', max_points: 4, competency: 'Kompetenz', checked: true, position: 1, weight: 50 },
        ])
        await nextTick()

        const slider = root.querySelector('input[type="range"]')
        slider.value = '25'
        slider.dispatchEvent(new Event('input', { bubbles: true }))
        root.querySelector('#assessment-form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))

        expect(form.tasks).toEqual([{ task_id: 10, weight: 25 }])
        unmount()
    })
})
