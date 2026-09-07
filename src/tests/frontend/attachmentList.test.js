// @vitest-environment happy-dom

import { createApp, nextTick } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { axiosPost } = vi.hoisted(() => ({ axiosPost: vi.fn() }))

vi.mock('axios', () => ({ default: { post: axiosPost } }))
vi.mock('@inertiajs/vue3', () => ({
    router: { reload: vi.fn() },
    useForm: initial => ({
        ...initial,
        processing: false,
        errors: {},
        reset: vi.fn(),
        submit: vi.fn(),
    }),
}))

import AttachmentList from '../../resources/js/Components/Ui/AttachmentList.vue'
import { closeConfirmation } from '../../resources/js/utils/confirmation'

function mount(props) {
    const root = document.createElement('div')
    document.body.append(root)
    const app = createApp(AttachmentList, props)
    app.mount(root)

    return {
        root,
        unmount: () => {
            app.unmount()
            root.remove()
        },
    }
}

describe('AttachmentList', () => {
    beforeEach(() => {
        axiosPost.mockReset()
        axiosPost.mockResolvedValue({ data: {} })
        global.fetch = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ association_count: 1 }) })
    })

    it('removes an assigned file from the visible list after deletion', async () => {
        const { root, unmount } = mount({
            resources: [{ id: 7, original_name: 'Arbeitsblatt.pdf', mime_type: 'application/pdf', size: 1024 }],
            libraryAttachUrl: '/jahresplanung/1/ressourcen',
            libraryTargetType: 'lesson',
            libraryTargetId: 81,
            downloadBaseUrl: '/download',
            manage: true,
        })

        await nextTick()
        expect(root.textContent).toContain('Arbeitsblatt.pdf')

        root.querySelector('button[title="Löschen"]').click()
        await new Promise(resolve => setTimeout(resolve, 300))
        closeConfirmation(true)
        await new Promise(resolve => setTimeout(resolve, 0))
        await nextTick()

        expect(axiosPost).toHaveBeenCalledWith('/jahresplanung/1/ressourcen/file/7/trennen', { target_type: 'lesson', target_id: 81, permanent: true }, expect.objectContaining({ headers: expect.objectContaining({ Accept: 'application/json' }) }))
        expect(root.textContent).not.toContain('Arbeitsblatt.pdf')
        unmount()
    })

    it('adds a file assigned from the library to the visible list', async () => {
        const resource = { id: 8, kind: 'file', original_name: 'Bibliotheksdatei.pdf', mime_type: 'application/pdf', size: 2048 }
        global.fetch = vi.fn().mockResolvedValue({ ok: true, json: async () => [resource] })
        axiosPost.mockResolvedValue({ data: { item: resource } })
        const { root, unmount } = mount({
            libraryAttachUrl: '/jahresplanung/1/ressourcen',
            libraryTargetType: 'lesson',
            libraryTargetId: 81,
            downloadBaseUrl: '/download',
            manage: true,
        })

        const libraryButton = Array.from(root.querySelectorAll('button')).find(button => button.textContent.includes('Bibliothek'))
        libraryButton.click()
        await new Promise(resolve => setTimeout(resolve, 300))
        await nextTick()
        root.querySelector('.library-picker-list button').click()
        await new Promise(resolve => setTimeout(resolve, 0))
        await nextTick()

        expect(axiosPost).toHaveBeenCalledWith('/jahresplanung/1/ressourcen/8/zuordnen', { target_type: 'lesson', target_id: 81 }, expect.objectContaining({ headers: expect.objectContaining({ Accept: 'application/json' }) }))
        expect(root.textContent).toContain('Bibliotheksdatei.pdf')
        unmount()
    })

    it('updates URL resources immediately when assigning and detaching them', async () => {
        const resource = { id: 9, kind: 'resource', title: 'Bibliothekslink', url: 'https://example.test' }
        global.fetch = vi.fn().mockResolvedValue({ ok: true, json: async () => [resource] })
        axiosPost.mockResolvedValue({ data: { item: resource } })
        const { root, unmount } = mount({
            resourceLinks: [],
            libraryAttachUrl: '/jahresplanung/1/ressourcen',
            libraryTargetType: 'lesson',
            libraryTargetId: 81,
            downloadBaseUrl: '/download',
            manage: true,
        })

        const libraryButton = Array.from(root.querySelectorAll('button')).find(button => button.textContent.includes('Bibliothek'))
        libraryButton.click()
        await new Promise(resolve => setTimeout(resolve, 300))
        await nextTick()
        root.querySelector('.library-picker-list button').click()
        await new Promise(resolve => setTimeout(resolve, 0))
        await nextTick()

        expect(root.textContent).toContain('Bibliothekslink')
        root.querySelector('button[title="Löschen"]').click()
        await new Promise(resolve => setTimeout(resolve, 0))
        closeConfirmation(true)
        await new Promise(resolve => setTimeout(resolve, 0))
        await nextTick()

        expect(root.textContent).not.toContain('Bibliothekslink')
        unmount()
    })

    it('updates assessment tasks immediately when assigning and detaching them', async () => {
        const task = { id: 10, title: 'Neue Aufgabe', kind: 'assessment-task' }
        global.fetch = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ association_count: 2 }) })
        axiosPost.mockResolvedValue({ data: { message: 'Zuordnung wurde entfernt.' } })
        const first = mount({
            assessmentTasks: [task],
            libraryAttachUrl: '/jahresplanung/1/ressourcen',
            libraryTargetType: 'lesson',
            libraryTargetId: 81,
            downloadBaseUrl: '/download',
            manage: true,
        })

        await nextTick()
        expect(first.root.textContent).toContain('Neue Aufgabe')
        first.root.querySelector('button[title="Löschen"]').click()
        await new Promise(resolve => setTimeout(resolve, 0))
        closeConfirmation(true)
        await new Promise(resolve => setTimeout(resolve, 0))
        await nextTick()
        expect(first.root.textContent).not.toContain('Neue Aufgabe')
        first.unmount()

        const libraryTask = { id: 11, kind: 'assessment-task', title: 'Bibliotheksaufgabe', description: 'Aufgabe' }
        global.fetch = vi.fn().mockResolvedValue({ ok: true, json: async () => [libraryTask] })
        axiosPost.mockResolvedValue({ data: { task: libraryTask } })
        const second = mount({
            libraryAttachUrl: '/jahresplanung/1/ressourcen',
            libraryTargetType: 'lesson',
            libraryTargetId: 81,
            downloadBaseUrl: '/download',
            manage: true,
        })

        Array.from(second.root.querySelectorAll('button')).find(button => button.textContent.includes('Bibliothek')).click()
        await new Promise(resolve => setTimeout(resolve, 300))
        await nextTick()
        second.root.querySelector('.library-picker-list button').click()
        await new Promise(resolve => setTimeout(resolve, 0))
        await nextTick()
        expect(second.root.textContent).toContain('Bibliotheksaufgabe')
        second.unmount()
    })
})
