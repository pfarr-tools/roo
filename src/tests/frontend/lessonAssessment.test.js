// @vitest-environment happy-dom

import { createApp, nextTick } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { axiosPost } = vi.hoisted(() => ({ axiosPost: vi.fn() }))

vi.mock('axios', () => ({ default: { post: axiosPost } }))
vi.mock('@inertiajs/vue3', () => ({
    router: {
        post: vi.fn(),
    },
}))

import LessonAssessmentTab from '../../resources/js/Components/Planning/LessonAssessmentTab.vue'
import { closeConfirmation } from '../../resources/js/utils/confirmation'

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
    beforeEach(() => {
        axiosPost.mockReset()
        global.fetch = vi.fn().mockResolvedValue({ ok: true, json: async () => [{ id: 7, title: 'Bibliotheksaufgabe', max_points: 4, education_plan_competency_id: 55 }] })
    })

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

    it('assigns a library task with a small axios request and updates the list locally', async () => {
        axiosPost.mockResolvedValue({ data: { task: { id: 7, title: 'Bibliotheksaufgabe', max_points: 4, teaching_unit_competency_id: 101 } } })
        const { root, unmount } = mount({
            scheduleSlotId: 81,
            groupId: 1,
            lessonId: 81,
            competencies: [{ id: 101, education_plan_competency_id: 55, label: 'Kompetenz' }],
        })

        root.querySelector('td.text-end button').click()
        await Promise.resolve()
        await new Promise(resolve => setTimeout(resolve, 0))
        await nextTick()
        root.querySelector('.library-picker-list button').click()
        await nextTick()

        expect(global.fetch).toHaveBeenCalledWith('/ressourcen/bibliothek?q=&type=assessment-task&education_plan_competency_id=55', expect.anything())
        expect(axiosPost).toHaveBeenCalledWith('/jahresplanung/1/ressourcen/assessment-task/7/zuordnen', { target_type: 'lesson', target_id: 81 }, expect.objectContaining({ headers: expect.objectContaining({ Accept: 'application/json' }) }))
        expect(root.textContent).toContain('Bibliotheksaufgabe')
        unmount()
    })

    it('shows an assigned task even when its competency cannot be matched after reload', async () => {
        const { root, unmount } = mount({
            scheduleSlotId: 81,
            groupId: 1,
            lessonId: 81,
            competencies: [{ id: 101, education_plan_competency_id: 55, label: 'Kompetenz' }],
            assessmentTasks: [{ id: 7, title: 'Nicht passend bezeichnete Aufgabe', max_points: 4, education_plan_competency_id: 999 }],
        })

        await nextTick()

        expect(root.textContent).toContain('Ohne Kompetenzzuordnung')
        expect(root.textContent).toContain('Nicht passend bezeichnete Aufgabe')
        unmount()
    })

    it('removes an assigned task with a small axios request', async () => {
        axiosPost.mockResolvedValue({ data: { message: 'Prüfungsaufgabe wurde entfernt.' } })
        const { root, unmount } = mount({
            scheduleSlotId: 81,
            groupId: 1,
            lessonId: 81,
            competencies: [{ id: 101, education_plan_competency_id: 55, label: 'Kompetenz' }],
            assessmentTasks: [{ id: 7, title: 'Aufgabe', education_plan_competency_id: 55 }],
        })

        await nextTick()
        root.querySelector('button[aria-label="Prüfungsaufgabe entfernen"]').click()
        closeConfirmation(true)
        await new Promise(resolve => setTimeout(resolve, 0))
        await nextTick()

        expect(axiosPost).toHaveBeenCalledWith('/jahresplanung/1/ressourcen/assessment-task/7/trennen', { target_type: 'lesson', target_id: 81 }, expect.objectContaining({ headers: expect.objectContaining({ Accept: 'application/json' }) }))
        expect(root.querySelectorAll('.list-group-item')).toHaveLength(0)
        unmount()
    })
})
