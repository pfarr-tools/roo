// @vitest-environment happy-dom

import { createApp, nextTick } from 'vue'
import { describe, expect, it, vi } from 'vitest'

const { deleteRequest, requestConfirmation } = vi.hoisted(() => ({ deleteRequest: vi.fn(), requestConfirmation: vi.fn().mockResolvedValue(true) }))
vi.mock('@inertiajs/vue3', () => ({
    useForm: () => ({ delete: deleteRequest }),
}))
vi.mock('../../resources/js/utils/confirmation', () => ({ requestConfirmation }))

import AssessmentActions from '../../resources/js/Components/TeachingGroups/AssessmentActions.vue'

function mount({ differentiated = false } = {}) {
    const root = document.createElement('div')
    document.body.append(root)
    const app = createApp(AssessmentActions, { groupId: 12, assessmentId: 34, title: 'LSE Schöpfung', differentiated })
    app.mount(root)

    return { root, unmount: () => { app.unmount(); root.remove() } }
}

describe('AssessmentActions', () => {
    it('offers edit, print, evaluation and confirmed delete actions in order', async () => {
        const { root, unmount } = mount()
        await nextTick()

        const actions = [...root.querySelectorAll('a, button')]
        expect(actions.map(action => action.getAttribute('aria-label'))).toEqual([
            'Lernstandserhebung bearbeiten',
            'Lernstandserhebung drucken',
            'Lernstandserhebung bewerten',
            'Lernstandserhebung löschen',
        ])
        expect(actions[0].getAttribute('href')).toBe('/unterrichtsgruppen/12/lernstandserhebungen/34/bearbeiten?return_tab=assessments')
        expect(actions[1].tagName).toBe('BUTTON')
        expect(actions[2].getAttribute('href')).toBe('/unterrichtsgruppen/12/lernstandserhebungen/34/auswertung')

        actions[3].click()
        await nextTick()
        expect(requestConfirmation).toHaveBeenCalledWith({ message: 'Möchtest du die Lernstandserhebung „LSE Schöpfung“ wirklich löschen? Dieser Vorgang kann nicht rückgängig gemacht werden.' })
        expect(deleteRequest).toHaveBeenCalledWith('/unterrichtsgruppen/12/lernstandserhebungen/34')

        unmount()
    })

    it('opens download choices for differentiated assessments', async () => {
        const { root, unmount } = mount({ differentiated: true })
        await nextTick()

        root.querySelector('[aria-label="Lernstandserhebung drucken"]').click()
        await nextTick()

        expect(root.querySelector('[role="dialog"]').textContent).toContain('Download')
        expect([...root.querySelector('#assessment-download-level').options].map(option => option.value)).toEqual(['G', 'M', 'E'])
        expect(root.querySelector('#assessment-download-level').value).toBe('M')
        expect([...root.querySelector('#assessment-download-format').options].map(option => option.value)).toEqual(['odt', 'docx'])
        expect(root.querySelector('#assessment-download-template').value).toBe('primary-school-lower-secondary')

        root.querySelector('#assessment-download-level').value = 'E'
        root.querySelector('#assessment-download-level').dispatchEvent(new Event('change'))
        root.querySelector('#assessment-download-format').value = 'docx'
        root.querySelector('#assessment-download-format').dispatchEvent(new Event('change'))
        await nextTick()

        expect(root.querySelector('[data-testid="assessment-download"]').getAttribute('href')).toBe('/unterrichtsgruppen/12/lernstandserhebungen/34/download?level=E&format=docx&template=primary-school-lower-secondary')

        unmount()
    })
})
