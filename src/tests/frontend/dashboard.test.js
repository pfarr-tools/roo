// @vitest-environment happy-dom

import { createApp, nextTick } from 'vue'
import { describe, expect, it, vi } from 'vitest'

vi.mock('../../resources/js/Components/Ui/AppShell.vue', () => ({
    default: { template: '<div><slot /></div>' },
}))

import Dashboard from '../../resources/js/Pages/Dashboard.vue'

describe('Stundenplan', () => {
    it('marks special slot statuses and does not link unplanned hours', async () => {
        const root = document.createElement('div')
        document.body.append(root)
        const app = createApp(Dashboard, {
            week: '2026-09-14',
            weekOptions: [],
            days: [{ date: '2026-09-15', label: 'Dienstag', entries: [
                { group_id: 1, period_number: 1, starts_at: '08:00', ends_at: '08:45', group_name: '7a', school_name: 'Schule', date: '2026-09-15', schedule_slot_id: 1, slot_status: 'cancelled', lesson: { title: 'Entfallene Stunde', status: 'planned' } },
                { group_id: 2, period_number: 1, starts_at: '08:00', ends_at: '08:45', group_name: '8a', school_name: 'Schule', date: '2026-09-15', schedule_slot_id: 2, slot_status: 'free', lesson: null },
            ] }],
            periodNumbers: [1],
            hasSchoolYear: true,
        })

        app.mount(root)
        await nextTick()

        const cards = [...root.querySelectorAll('.dashboard-lesson-card')]
        expect(cards[0].className).toContain('dashboard-lesson-card-cancelled')
        expect(cards[0].textContent).toContain('entfällt')
        expect(cards[0].getAttribute('role')).toBe('link')
        expect(cards[1].className).toContain('dashboard-lesson-card-unplanned')
        expect(cards[1].getAttribute('role')).toBeNull()
        expect(cards[1].getAttribute('tabindex')).toBeNull()

        app.unmount()
        root.remove()
    })
})
