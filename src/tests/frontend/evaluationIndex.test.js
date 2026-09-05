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

        expect(get).toHaveBeenCalledWith('/bewertungen', { group: '1' }, { preserveState: true, preserveScroll: true })

        unmount()
    })
})
