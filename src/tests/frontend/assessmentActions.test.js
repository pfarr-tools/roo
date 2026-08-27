// @vitest-environment happy-dom

import { createApp, nextTick } from 'vue'
import { describe, expect, it, vi } from 'vitest'

const { deleteRequest, requestConfirmation } = vi.hoisted(() => ({ deleteRequest: vi.fn(), requestConfirmation: vi.fn().mockResolvedValue(true) }))
vi.mock('@inertiajs/vue3', () => ({
    useForm: () => ({ delete: deleteRequest }),
}))
vi.mock('../../resources/js/utils/confirmation', () => ({ requestConfirmation }))

import AssessmentActions from '../../resources/js/Components/TeachingGroups/AssessmentActions.vue'

function mount() {
    const root = document.createElement('div')
    document.body.append(root)
    const app = createApp(AssessmentActions, { groupId: 12, assessmentId: 34, title: 'LSE Schöpfung' })
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
        expect(actions[1].getAttribute('href')).toBe('/unterrichtsgruppen/12/lernstandserhebungen/34/download')
        expect(actions[2].getAttribute('href')).toBe('/unterrichtsgruppen/12/lernstandserhebungen/34/auswertung')

        actions[3].click()
        await nextTick()
        expect(requestConfirmation).toHaveBeenCalledWith({ message: 'Möchtest du die Lernstandserhebung „LSE Schöpfung“ wirklich löschen? Dieser Vorgang kann nicht rückgängig gemacht werden.' })
        expect(deleteRequest).toHaveBeenCalledWith('/unterrichtsgruppen/12/lernstandserhebungen/34')

        unmount()
    })
})
