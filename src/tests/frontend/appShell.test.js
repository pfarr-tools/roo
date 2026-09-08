// @vitest-environment happy-dom

import { createApp, nextTick } from 'vue'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({ usePage: () => ({ props: { auth: { user: { name: 'Max Mustermann' } }, flash: {} } }) }))

import AppShell from '../../resources/js/Components/Ui/AppShell.vue'

describe('AppShell navigation', () => {
    it('places Stundenplan under Unterricht and removes Dokumente und KI', async () => {
        const root = document.createElement('div')
        document.body.append(root)
        const app = createApp(AppShell)

        app.mount(root)
        await nextTick()

        const teachingHeading = [...root.querySelectorAll('.roo-nav-heading')]
            .find((heading) => heading.textContent.trim() === 'Unterricht')

        expect(teachingHeading).not.toBeUndefined()
        expect(teachingHeading.nextElementSibling.textContent).toContain('Stundenplan')
        expect(teachingHeading.nextElementSibling.getAttribute('href')).toBe('/dashboard')
        expect(root.textContent).not.toContain('Dokumente und KI')
        expect(root.querySelector('.roo-avatar').textContent).toBe('MM')
        expect(root.textContent).toContain('Max Mustermann')
        expect(root.querySelector('.roo-profile-dropdown').textContent).not.toContain('Profil')

        app.unmount()
        root.remove()
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
