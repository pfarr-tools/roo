// @vitest-environment happy-dom

import { createApp, nextTick, reactive } from 'vue'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
    router: { visit: vi.fn() },
    useForm(initial) {
        const form = reactive({
            ...initial,
            processing: false,
            errors: {},
            defaults: vi.fn(),
            reset: vi.fn(),
            data: vi.fn(() => ({ ...form })),
            transform: vi.fn(() => form),
            post: vi.fn(),
            put: vi.fn(),
        })
        return form
    },
}))

vi.mock('../../resources/js/Components/Ui/AppShell.vue', () => ({
    default: { template: '<div><slot name="toolbar"></slot><slot></slot></div>' },
}))

vi.mock('../../resources/js/Components/Planning/CompetencyPickerModal.vue', () => ({
    default: { template: '<div />' },
}))

import Edit from '../../resources/js/Pages/AssessmentTask/Edit.vue'

function mount(props = {}) {
    const root = document.createElement('div')
    document.body.append(root)
    const app = createApp(Edit, {
        backUrl: '/back',
        submitUrl: '/save',
        educationPlans: [],
        ...props,
    })
    app.mount(root)

    return { root, unmount: () => { app.unmount(); root.remove() } }
}

it('shows checkbox points and keeps expectations manual', async () => {
    const { root, unmount } = mount()
    root.querySelectorAll('[role="tab"]')[1].click()
    await nextTick()
    Array.from(root.querySelectorAll('button')).find(button => button.textContent.includes('Richtige Sätze ankreuzen')).click()
    await nextTick()

    expect(root.querySelector('#assessment-task-points-per-correct-answer')).not.toBeNull()
    expect(root.textContent).not.toContain('Automatische Erwartungen')
    unmount()
})
