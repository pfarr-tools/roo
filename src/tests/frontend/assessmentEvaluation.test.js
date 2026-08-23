// @vitest-environment happy-dom

import { createApp, nextTick } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const testState = vi.hoisted(() => ({
    forms: [],
    scanClients: [],
    startScan: vi.fn(),
}))

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')

    return {
    useForm(initial) {
        const form = reactive({
            ...initial,
            processing: false,
            errors: {},
            put: vi.fn(),
            patch: vi.fn(),
        })
        testState.forms.push(form)

        return form
    },
    }
})

vi.mock('../../resources/js/Features/AssessmentScan/scanClient', () => ({
    createScanClient: vi.fn((options) => {
        testState.scanClients.push(options)

        return { start: testState.startScan }
    }),
}))

import AssessmentScanUploadModal from '../../resources/js/Features/AssessmentEvaluation/AssessmentScanUploadModal.vue'
import BookletAssignment from '../../resources/js/Features/AssessmentEvaluation/BookletAssignment.vue'
import BookletList from '../../resources/js/Features/AssessmentEvaluation/BookletList.vue'
import {
    evaluationSections,
    bookletStudentOptions,
    bookletActionUrl,
    uploadErrorMessage,
    uploadProgressDetails,
} from '../../resources/js/Features/AssessmentEvaluation/presentation'

function mount(component, props) {
    const root = document.createElement('div')
    document.body.append(root)
    const app = createApp(component, props)
    app.mount(root)

    return {
        root,
        unmount: () => {
            app.unmount()
            root.remove()
        },
    }
}

const group = { id: 11 }
const assessment = { id: 3 }

beforeEach(() => {
    testState.forms.length = 0
    testState.scanClients.length = 0
    testState.startScan.mockReset()
})

describe('assessment evaluation presentation', () => {
    it('keeps scans, booklet assignment, and task evaluation freely selectable', () => {
        expect(evaluationSections.map((section) => section.id)).toEqual([
            'scans',
            'booklets',
            'tasks',
        ])
    })

    it('shows current-group students while reserving a student assigned to another open booklet', () => {
        const options = bookletStudentOptions(
            { id: 8, student_id: null },
            [
                { id: 11, first_name: 'Ada', last_name: 'Lovelace', class_name: '4a' },
                { id: 12, first_name: 'Grace', last_name: 'Hopper', class_name: null },
            ],
            [
                { id: 7, number: 1, status: 'open', student_id: 11 },
                { id: 8, number: 2, status: 'open', student_id: null },
            ],
        )

        expect(options).toEqual([
            { id: 11, label: 'Lovelace, Ada · 4a', disabled: true },
            { id: 12, label: 'Hopper, Grace', disabled: false },
        ])
    })

    it('keeps the current booklet student selectable for a correction', () => {
        const [option] = bookletStudentOptions(
            { id: 8, student_id: 11 },
            [{ id: 11, first_name: 'Ada', last_name: 'Lovelace', class_name: '4a' }],
            [{ id: 8, number: 2, status: 'open', student_id: 11 }],
        )

        expect(option.disabled).toBe(false)
    })

    it('builds assignment and reversible status endpoints for a booklet', () => {
        expect(bookletActionUrl(11, 3, 8, 'assignment')).toBe('/unterrichtsgruppen/11/lernstandserhebungen/3/auswertung/booklets/8/zuordnung')
        expect(bookletActionUrl(11, 3, 8, 'status')).toBe('/unterrichtsgruppen/11/lernstandserhebungen/3/auswertung/booklets/8/status')
    })

    it('shows page, marker, and fragment progress during an upload', () => {
        expect(uploadProgressDetails({
            currentPage: 2,
            totalPages: 5,
            detectedMarkers: 6,
            detectedFragments: 2,
            uploadedPages: 1,
        })).toEqual({
            page: '2 / 5',
            markers: 6,
            fragments: 2,
            uploadedPages: '1 / 5',
        })
    })

    it('keeps a scan failure visible and supplies a useful fallback message', () => {
        expect(uploadErrorMessage(new Error('Die Seite 3 konnte nicht verarbeitet werden.'))).toBe('Die Seite 3 konnte nicht verarbeitet werden.')
        expect(uploadErrorMessage()).toBe('Die Scan-Anfrage ist fehlgeschlagen.')
    })
})

describe('assessment evaluation components', () => {
    it('renders the protected name fragment and submits an unassignment', async () => {
        const { root, unmount } = mount(BookletAssignment, {
            group,
            assessment,
            booklet: { id: 8, number: 2, student_id: 12, name_fragment_url: '/private/name-fragment' },
            students: [{ id: 12, first_name: 'Grace', last_name: 'Hopper', class_name: '4a' }],
            booklets: [{ id: 8, number: 2, status: 'open', student_id: 12 }],
        })

        expect(root.querySelector('img').getAttribute('src')).toBe('/private/name-fragment')
        root.querySelector('button.btn-outline-secondary').click()
        await nextTick()

        expect(testState.forms[0].student_id).toBeNull()
        expect(testState.forms[0].put).toHaveBeenCalledWith(
            '/unterrichtsgruppen/11/lernstandserhebungen/3/auswertung/booklets/8/zuordnung',
            { preserveScroll: true },
        )
        unmount()
    })

    it('submits the student selected from the current group for a booklet', async () => {
        const { root, unmount } = mount(BookletAssignment, {
            group,
            assessment,
            booklet: { id: 8, number: 2, student_id: null, name_fragment_url: '/private/name-fragment' },
            students: [{ id: 12, first_name: 'Grace', last_name: 'Hopper', class_name: '4a' }],
            booklets: [{ id: 8, number: 2, status: 'open', student_id: null }],
        })

        const select = root.querySelector('select')
        select.value = '12'
        select.dispatchEvent(new Event('change'))
        await nextTick()
        root.querySelector('form').dispatchEvent(new Event('submit', { cancelable: true }))
        await nextTick()

        expect(testState.forms[0].student_id).toBe(12)
        expect(testState.forms[0].put).toHaveBeenCalledWith(
            '/unterrichtsgruppen/11/lernstandserhebungen/3/auswertung/booklets/8/zuordnung',
            { preserveScroll: true },
        )
        unmount()
    })

    it('keeps discard and restore requests and errors isolated per booklet card', async () => {
        const { root, unmount } = mount(BookletList, {
            group,
            assessment,
            booklets: [
                { id: 8, number: 2, status: 'open', fragment_count: 1, reviewed_fragment_count: 0 },
                { id: 9, number: 3, status: 'discarded', fragment_count: 2, reviewed_fragment_count: 1 },
            ],
        })

        const [discardButton, restoreButton] = root.querySelectorAll('button')
        discardButton.click()
        restoreButton.click()
        await nextTick()

        expect(testState.forms).toHaveLength(2)
        expect(testState.forms[0].patch).toHaveBeenCalledWith(
            '/unterrichtsgruppen/11/lernstandserhebungen/3/auswertung/booklets/8/status',
            { preserveScroll: true },
        )
        expect(testState.forms[1].patch).toHaveBeenCalledWith(
            '/unterrichtsgruppen/11/lernstandserhebungen/3/auswertung/booklets/9/status',
            { preserveScroll: true },
        )
        expect(testState.forms.map((form) => form.status)).toEqual(['discarded', 'open'])
        testState.forms[0].processing = true
        testState.forms[0].errors.status = 'Das Booklet kann noch nicht verworfen werden.'
        await nextTick()
        const cards = root.querySelectorAll('.card')
        expect(cards[0].querySelector('button').disabled).toBe(true)
        expect(cards[1].querySelector('button').disabled).toBe(false)
        expect(cards[0].textContent).toContain('Das Booklet kann noch nicht verworfen werden.')
        expect(cards[1].textContent).not.toContain('Das Booklet kann noch nicht verworfen werden.')
        unmount()
    })

    it('shows processing status and the current preview, then offers a retry after a scan error', async () => {
        let rejectScan
        testState.startScan.mockImplementation(() => new Promise((_, reject) => { rejectScan = reject }))
        const { root, unmount } = mount(AssessmentScanUploadModal, { group, assessment })
        const input = root.querySelector('input[type="file"]')
        Object.defineProperty(input, 'files', { value: [{ name: 'scan.pdf' }] })
        input.dispatchEvent(new Event('change'))
        root.querySelector('form').dispatchEvent(new Event('submit', { cancelable: true }))
        await Promise.resolve()
        await nextTick()

        expect(root.querySelector('[role="status"][aria-live="polite"]')).not.toBeNull()
        testState.scanClients[0].onProgress({ phase: 'page', currentPage: 2, totalPages: 3, detectedMarkers: 4, detectedFragments: 1, uploadedPages: 1 })
        testState.scanClients[0].onPagePreview('blob:page-2', 2)
        await nextTick()
        expect(root.querySelector('img').getAttribute('src')).toBe('blob:page-2')

        rejectScan(new Error('Die Seite konnte nicht verarbeitet werden.'))
        await Promise.resolve()
        await nextTick()
        const retryButton = [...root.querySelectorAll('button')].find((button) => button.textContent === 'Erneut versuchen')
        expect(root.textContent).toContain('Die Seite konnte nicht verarbeitet werden.')
        retryButton.click()
        await Promise.resolve()
        expect(testState.scanClients).toHaveLength(2)
        unmount()
    })
})
