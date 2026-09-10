// @vitest-environment happy-dom

import { createApp, nextTick } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { requestConfirmation } = vi.hoisted(() => ({
    requestConfirmation: vi.fn().mockResolvedValue(true),
}))

vi.mock('../../resources/js/utils/confirmation', () => ({ requestConfirmation }))

import ChordEditor from '../../resources/js/Components/Songs/ChordEditor.vue'

describe('ChordEditor instrument versions', () => {
    beforeEach(() => requestConfirmation.mockClear())

    function mountEditor(modelValue = [{ instrument: 'Gitarre', name: 'Original', key_signature: 'G-Dur', chords: [] }]) {
        const root = document.createElement('div')
        document.body.append(root)
        const updates = []
        const app = createApp(ChordEditor, {
            modelValue,
            parts: [{ id: 1, content: 'Geh mit mir' }],
            'onUpdate:modelValue': value => updates.push(value),
        })
        app.mount(root)
        return { app, root, updates }
    }

    it('keeps the name entered when copying an instrument version', async () => {
        const { app, root, updates } = mountEditor()
        root.querySelector('[aria-label="Akkordsatz kopieren"]').click()
        await nextTick()

        const input = root.querySelector('#copy-chord-set-name')
        input.value = 'Ukulele im Verein'
        input.dispatchEvent(new Event('input', { bubbles: true }))
        await nextTick()
        ;[...root.querySelectorAll('button')].find(button => button.textContent.includes('Kopie anlegen')).click()
        await nextTick()

        expect(updates.at(-1)[1].instrument).toBe('Ukulele im Verein')
        expect(updates.at(-1)[1].name).toBe('Original')
        app.unmount()
        root.remove()
    })

    it('löscht eine Instrumentversion erst nach Bestätigung', async () => {
        const { app, root, updates } = mountEditor()
        root.querySelector('[aria-label="Akkordsatz löschen"]').click()
        await nextTick()

        expect(requestConfirmation).toHaveBeenCalled()
        expect(updates.at(-1)).toEqual([])
        app.unmount()
        root.remove()
    })

    it('asks how to handle existing chords when changing the key', async () => {
        const { app, root, updates } = mountEditor([{
            instrument: 'Gitarre',
            key_signature: 'G-Dur',
            chords: [{ song_part_id: 1, line_number: 0, character_offset: 0, chord: 'G' }],
        }])
        const key = root.querySelector('select')
        key.value = 'D-Dur'
        key.dispatchEvent(new Event('change', { bubbles: true }))
        await nextTick()

        expect(root.textContent).toContain('Akkorde für neue Tonart beibehalten')
        expect(root.textContent).toContain('Alle Akkorde entfernen')
        expect(root.textContent).toContain('Akkorde in neue Tonart transponieren')
        expect(updates).toHaveLength(0)

        ;[...root.querySelectorAll('button')].find(button => button.textContent.includes('Akkorde in neue Tonart transponieren')).click()
        await nextTick()

        expect(updates.at(-1)[0].key_signature).toBe('D-Dur')
        expect(updates.at(-1)[0].chords[0].chord).toBe('D')
        app.unmount()
        root.remove()
    })

    it('can keep existing chords when changing the key', async () => {
        const { app, root, updates } = mountEditor([{
            instrument: 'Gitarre', key_signature: 'G-Dur',
            chords: [{ song_part_id: 1, line_number: 0, character_offset: 0, chord: 'G' }],
        }])
        const key = root.querySelector('select')
        key.value = 'D-Dur'
        key.dispatchEvent(new Event('change', { bubbles: true }))
        await nextTick()
        ;[...root.querySelectorAll('button')].find(button => button.textContent.includes('Akkorde für neue Tonart beibehalten')).click()
        await nextTick()

        expect(updates.at(-1)[0].key_signature).toBe('D-Dur')
        expect(updates.at(-1)[0].chords[0].chord).toBe('G')
        app.unmount()
        root.remove()
    })

    it('can remove existing chords when changing the key', async () => {
        const { app, root, updates } = mountEditor([{
            instrument: 'Gitarre', key_signature: 'G-Dur',
            chords: [{ song_part_id: 1, line_number: 0, character_offset: 0, chord: 'G' }],
        }])
        const key = root.querySelector('select')
        key.value = 'D-Dur'
        key.dispatchEvent(new Event('change', { bubbles: true }))
        await nextTick()
        ;[...root.querySelectorAll('button')].find(button => button.textContent.includes('Alle Akkorde entfernen')).click()
        await nextTick()

        expect(updates.at(-1)[0].key_signature).toBe('D-Dur')
        expect(updates.at(-1)[0].chords).toEqual([])
        app.unmount()
        root.remove()
    })
})
