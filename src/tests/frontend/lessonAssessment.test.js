// @vitest-environment happy-dom

import { createApp, nextTick } from 'vue'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
    router: {
        delete: vi.fn(),
        post: vi.fn(),
    },
}))

import LessonAssessmentTab from '../../resources/js/Components/Planning/LessonAssessmentTab.vue'

function mount(props) {
    const root = document.createElement('div')
    document.body.append(root)
    const app = createApp(LessonAssessmentTab, props)
    app.mount(root)

    return {
        root,
        unmount: () => {
            app.unmount()
            root.remove()
        },
    }
}

describe('LessonAssessmentTab', () => {
    it('lists all assessment tasks associated with the displayed competency', async () => {
        const { root, unmount } = mount({
            scheduleSlotId: 81,
            groupId: 1,
            lessonId: 81,
            competencies: [{
                id: 101,
                education_plan_competency_id: 55,
                source_identifier: '3.2.1.3',
                label: '3.2.1 (3) – Kompetenz',
            }],
            assessmentTasks: [
                { id: 1, title: 'Aufgabe über direkte Kompetenz', teaching_unit_competency_id: 101 },
                { id: 2, title: 'Aufgabe über Bildungsplan-ID', education_plan_competency_id: 55 },
                { id: 3, title: 'Aufgabe über Kompetenzkennung', competency_identifier: '3.2.1.3' },
            ],
        })

        await nextTick()

        expect(root.textContent).toContain('Aufgabe über direkte Kompetenz')
        expect(root.textContent).toContain('Aufgabe über Bildungsplan-ID')
        expect(root.textContent).toContain('Aufgabe über Kompetenzkennung')
        expect(root.querySelectorAll('td .list-group-item')).toHaveLength(3)

        unmount()
    })

    it('prefills the new-task link with the competency and education plan', async () => {
        const { root, unmount } = mount({
            scheduleSlotId: 81,
            groupId: 1,
            lessonId: 81,
            competencies: [{
                id: 101,
                education_plan_id: 7,
                education_plan_competency_id: 55,
                label: '3.2.1 (3) – Kompetenz',
            }],
        })

        await nextTick()

        expect(root.querySelector('a[href*="/pruefungsaufgaben/neu"]').getAttribute('href'))
            .toBe('/unterricht/81/pruefungsaufgaben/neu?education_plan_id=7&education_plan_competency_id=55')

        unmount()
    })
})
