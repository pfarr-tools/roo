// @vitest-environment happy-dom

import { createApp, nextTick } from 'vue'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({ usePage: () => ({ props: { auth: { user: { name: 'Max Mustermann' } }, flash: {} } }) }))

import AppShell from '../../resources/js/Components/Ui/AppShell.vue'

describe('AppShell navigation', () => {
    it('places Stundenplan under Unterricht and removes Dokumente und KI', async () => {
        window.localStorage.clear()
        const root = document.createElement('div')
        document.body.append(root)
        const app = createApp(AppShell)

        app.mount(root)
        await nextTick()

        const teachingHeading = [...root.querySelectorAll('.roo-nav-heading')]
            .find((heading) => heading.textContent.trim() === 'Unterricht')

        expect(teachingHeading).not.toBeUndefined()
        expect(teachingHeading.nextElementSibling.textContent).toContain('Stundenplan')
        expect(teachingHeading.nextElementSibling.getAttribute('href')).toBe('/stundenplan')
        expect(root.textContent).not.toContain('Dokumente und KI')
        expect(root.querySelector('.roo-avatar').textContent).toBe('MM')
        expect(root.textContent).toContain('Max Mustermann')
        expect(root.querySelector('.roo-profile-dropdown').textContent).not.toContain('Profil')

        app.unmount()
        root.remove()
        window.localStorage.clear()
    })

    it('persists the pinned navigation state across remounts', async () => {
        window.localStorage.clear()
        const firstRoot = document.createElement('div')
        document.body.append(firstRoot)
        const firstApp = createApp(AppShell)

        firstApp.mount(firstRoot)
        await nextTick()
        firstRoot.querySelector('.roo-sidebar-toggle').click()
        await nextTick()

        expect(window.localStorage.getItem('roo.sidebar.pinned')).toBe('true')
        firstApp.unmount()
        firstRoot.remove()

        const secondRoot = document.createElement('div')
        document.body.append(secondRoot)
        const secondApp = createApp(AppShell)
        secondApp.mount(secondRoot)
        await nextTick()

        expect(secondRoot.querySelector('.roo-sidebar-toggle').getAttribute('aria-label')).toBe('Navigation lösen')

        secondApp.unmount()
        secondRoot.remove()
        window.localStorage.clear()
    })

    it('shows debounced global search results in a dropdown', async () => {
        vi.useFakeTimers()
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ results: { schools: [{ id: 1, slug: 'sonnenschule', name: 'Sonnenschule', city: 'Ulm' }] } }),
        })
        vi.stubGlobal('fetch', fetchMock)
        const root = document.createElement('div')
        document.body.append(root)
        const app = createApp(AppShell)

        app.mount(root)
        const input = root.querySelector('#roo-global-search-input')
        input.value = 'sonne'
        input.dispatchEvent(new Event('input'))
        await vi.advanceTimersByTimeAsync(300)
        await nextTick()

        expect(fetchMock).toHaveBeenCalledWith('/suche?q=sonne', expect.objectContaining({ headers: expect.objectContaining({ Accept: 'application/json' }) }))
        expect(root.textContent).toContain('Sonnenschule')
        expect(root.querySelector('.roo-global-search-results a').getAttribute('href')).toBe('/schulen/sonnenschule')

        app.unmount()
        root.remove()
        vi.unstubAllGlobals()
        vi.useRealTimers()
    })
})
