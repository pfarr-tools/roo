// @vitest-environment happy-dom

import { createApp, nextTick, reactive } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const testState = vi.hoisted(() => ({
    forms: [],
    scanClients: [],
    startScan: vi.fn(),
    routerPut: vi.fn(),
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
    router: {
        put: (...args) => testState.routerPut(...args),
    },
    }
})

vi.mock('../../resources/js/Features/AssessmentScan/scanClient', () => ({
    createScanClient: vi.fn((options) => {
        testState.scanClients.push(options)

        return { start: testState.startScan }
    }),
}))

vi.mock('../../resources/js/Components/Ui/AppShell.vue', () => ({
    default: {
        template: '<div><slot name="toolbar"></slot><slot></slot></div>',
    },
}))

import Assess from '../../resources/js/Pages/Assessment/Assess.vue'
import AssessmentScanUploadModal from '../../resources/js/Features/AssessmentEvaluation/AssessmentScanUploadModal.vue'
import BookletAssignment from '../../resources/js/Features/AssessmentEvaluation/BookletAssignment.vue'
import BookletList from '../../resources/js/Features/AssessmentEvaluation/BookletList.vue'
import TaskEvaluation from '../../resources/js/Features/AssessmentEvaluation/TaskEvaluation.vue'
import {
    evaluationSections,
    bookletStudentOptions,
    bookletActionUrl,
    uploadErrorMessage,
    uploadProgressDetails,
    scanPhaseLabel,
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
    testState.routerPut.mockReset()
})

describe('assessment evaluation presentation', () => {
    it('offers booklet assignment and task evaluation as the only sections', () => {
        expect(evaluationSections.map((section) => section.id)).toEqual([
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

    it('renders German labels for scan processing phases', () => {
        expect(scanPhaseLabel('rendering')).toBe('Seiten werden gerendert')
        expect(scanPhaseLabel('complete')).toBe('Abschluss wird gespeichert')
    })
})

describe('assessment evaluation components', () => {

    it('reuses the existing scan client when completion is retried', async () => {
        testState.startScan.mockRejectedValueOnce(new Error('Netzwerk unterbrochen.')).mockResolvedValueOnce({})
        const { root, unmount } = mount(AssessmentScanUploadModal, { group, assessment })
        const input = root.querySelector('#assessment-scan-pdf')
        Object.defineProperty(input, 'files', { value: [new File(['pdf'], 'scan.pdf', { type: 'application/pdf' })] })
        input.dispatchEvent(new Event('change'))
        root.querySelector('form').dispatchEvent(new Event('submit', { cancelable: true }))
        await nextTick()
        await Promise.resolve()
        await nextTick()

        root.querySelector('.btn-outline-primary').click()
        await nextTick()
        await Promise.resolve()

        expect(testState.scanClients).toHaveLength(1)
        expect(testState.startScan).toHaveBeenCalledTimes(2)
        unmount()
    })

    it('switches freely between sections and tasks without a required sequence', async () => {
        const { root, unmount } = mount(Assess, {
            group,
            assessment: { ...assessment, title: 'Jesus und seine Gleichnisse' },
            scan: { warnings: [] },
            students: [],
            booklets: [],
            progress: {},
            tasks: [
                { id: 21, title: 'Schöpfung beschreiben', expectations: [] },
                { id: 22, title: 'Verantwortung erklären', expectations: [] },
            ],
            taskFragments: [
                { id: 41, booklet_id: 8, assessment_task_id: 21, image_url: '/private/task-one.png', review: null },
                { id: 42, booklet_id: 9, assessment_task_id: 22, image_url: '/private/task-two.png', review: null },
            ],
        })
        expect(root.querySelector('h1').textContent).toBe('Jesus und seine Gleichnisse auswerten')
        expect(root.querySelectorAll('.assessment-evaluation-stat')).toHaveLength(4)
        expect(root.querySelectorAll('.card')).toHaveLength(0)

        const sections = root.querySelectorAll('[role="tab"]')

        sections[1].click()
        await nextTick()
        expect(root.querySelector('#assessment-tasks-heading')).not.toBeNull()

        const taskSelect = root.querySelector('#assessment-task-select')
        expect(taskSelect).not.toBeNull()
        expect(root.querySelector('.col-xl-4')).toBeNull()
        taskSelect.value = '21'
        taskSelect.dispatchEvent(new Event('change'))
        await nextTick()
        expect(root.querySelector('h3').textContent.trim()).toBe('Schöpfung beschreiben (0 VP)')

        sections[0].click()
        await nextTick()
        expect(root.querySelector('#assessment-booklets-heading')).not.toBeNull()

        sections[1].click()
        await nextTick()
        taskSelect.value = '22'
        taskSelect.dispatchEvent(new Event('change'))
        await nextTick()
        expect(root.querySelector('h3').textContent.trim()).toBe('Verantwortung erklären (0 VP)')

        unmount()
    })

    it('puts discard next to unassigned booklets', () => {
        const { root, unmount } = mount(Assess, {
            group,
            assessment,
            scan: { warnings: [] },
            booklets: [
                { id: 8, number: 1, status: 'open', student_id: null, name_fragment_url: null },
                { id: 9, number: 2, status: 'open', student_id: 12, name_fragment_url: null },
            ],
            progress: {},
        })

        const assignmentTab = root.querySelector('[aria-controls="booklets-panel"]')
        assignmentTab.click()

        expect(root.textContent).toContain('Verwerfen')
        expect(root.querySelectorAll('.btn-outline-danger')).toHaveLength(1)
        unmount()
    })

    it('renders repeated expectations independently and saves a reviewed task fragment', async () => {
        const task = {
            id: 21,
            title: 'Schöpfung beschreiben',
            expectations: [{ id: 31, text: 'Nennt Beispiele.', points: 2, repetitions: 2 }],
        }
        const { root, unmount } = mount(TaskEvaluation, {
            group,
            assessment,
            task,
            fragments: [{ id: 41, booklet_id: 8, image_url: '/private/task.png', review: null }],
            openKey: 1,
        })

        expect(root.querySelector('article').classList.contains('col-xxl-6')).toBe(false)
        expect(root.querySelectorAll('[data-testid="expectation-row"]')).toHaveLength(2)
        expect(root.querySelector('[data-testid="expectation-row"] .row').classList.contains('align-items-start')).toBe(true)
        expect(root.querySelector('[data-testid="expectation-row"]').textContent).toContain('Nennt Beispiele. (2 VP)')
        expect(root.querySelector('[data-testid="extra-points-row"] .row').classList.contains('align-items-start')).toBe(true)
        expect(root.querySelector('[data-testid="extra-points-row"] .col-12.col-lg-4')).not.toBeNull()
        expect(root.querySelector('[data-testid="extra-points-row"] .col-auto')).not.toBeNull()
        expect(root.querySelector('[data-testid="extra-points-row"] .col-12.col-sm-3.col-lg-2')).not.toBeNull()
        expect(root.querySelector('[data-testid="expectation-row"]').classList.contains('p-3')).toBe(false)
        expect(root.querySelector('button[type="submit"]').classList.contains('btn-warning')).toBe(true)
        const fullPointsButton = root.querySelector('[data-testid="full-points-41-31-1"]')
        expect(fullPointsButton.textContent.trim()).toBe('')
        expect(fullPointsButton.getAttribute('aria-label')).toBe('Volle Punktzahl')
        expect(fullPointsButton.classList.contains('btn-outline-danger')).toBe(true)
        expect(fullPointsButton.querySelector('.bi-x-lg')).not.toBeNull()
        expect(root.querySelector('[data-testid="points-41-31-1"]').getAttribute('min')).toBe('0')
        expect(root.querySelector('[data-testid="points-41-31-1"]').getAttribute('max')).toBe('2')
        expect(root.querySelector('[data-testid="points-41-31-1"]').getAttribute('step')).toBe('1')
        expect(root.querySelector('[data-testid="points-41-31-1"]').value).toBe('0')
        fullPointsButton.click()
        await nextTick()
        expect(fullPointsButton.classList.contains('btn-outline-success')).toBe(true)
        expect(fullPointsButton.querySelector('.bi-check-lg')).not.toBeNull()
        expect(root.querySelector('[data-testid="points-41-31-1"]').value).toBe('2')
        fullPointsButton.click()
        await nextTick()
        expect(fullPointsButton.classList.contains('btn-outline-danger')).toBe(true)
        expect(root.querySelector('[data-testid="points-41-31-1"]').value).toBe('0')
        fullPointsButton.click()
        await nextTick()
        expect(fullPointsButton.classList.contains('btn-outline-success')).toBe(true)
        const points = root.querySelector('[data-testid="points-41-31-2"]')
        points.value = '0.5'
        points.dispatchEvent(new Event('input'))
        await nextTick()
        expect(root.querySelector('[data-testid="full-points-41-31-2"]').classList.contains('btn-outline-danger')).toBe(true)
        const note = root.querySelector('[data-testid="note-41-31-2"]')
        note.value = 'teilweise'
        note.dispatchEvent(new Event('input'))
        const extraPoints = root.querySelector('[data-testid="extra-points-41"]')
        extraPoints.value = '-1'
        extraPoints.dispatchEvent(new Event('input'))
        root.querySelector('form').dispatchEvent(new Event('submit', { cancelable: true }))
        await nextTick()

        expect(testState.routerPut).toHaveBeenCalledWith(
            '/unterrichtsgruppen/11/lernstandserhebungen/3/auswertung/booklets/8/tasks/21/review',
            {
                items: [
                    { expectation_id: 31, occurrence: 1, awarded_points: 2, note: null },
                    { expectation_id: 31, occurrence: 2, awarded_points: 0.5, note: 'teilweise' },
                ],
                extra_points: -1,
                extra_note: null,
            },
            expect.objectContaining({ preserveScroll: true, preserveState: true }),
        )
        testState.routerPut.mock.calls[0][2].onSuccess()
        await nextTick()
        expect(root.querySelector('button[type="submit"]').classList.contains('btn-success')).toBe(true)
        note.value = 'erneut geändert'
        note.dispatchEvent(new Event('input'))
        await nextTick()
        expect(root.querySelector('button[type="submit"]').classList.contains('btn-warning')).toBe(true)
        unmount()
    })

    it('uses the standard free-text evaluation UI for subtask tables', async () => {
        const task = {
            id: 22,
            title: 'Tabelle',
            task_type: 'subtask_table',
            max_points: 3,
            content: { subtasks: [
                { key: 'a', label: 'Nenne ein Beispiel.', solution: 'Ein Beispiel', points: 1 },
                { key: 'b', label: 'Begründe.', solution: '', points: null },
            ] },
            expectations: [
                { id: 32, subtask_key: 'a', text: 'Lösung: Ein Beispiel', points: 1, repetitions: 1 },
                { id: 33, subtask_key: 'b', text: 'Begründung nennt das Merkmal.', points: 2, repetitions: 1 },
            ],
        }
        const { root, unmount } = mount(TaskEvaluation, {
            group,
            assessment,
            task,
            fragments: [{ id: 42, booklet_id: 8, image_url: '/private/task.png', review: null }],
            openKey: 1,
        })

        expect(root.querySelectorAll('[data-testid="expectation-row"]')).toHaveLength(2)
        expect(root.querySelectorAll('[data-testid="subtask-solution-checkbox"]')).toHaveLength(0)
        expect(root.querySelectorAll('[data-testid="subtask-expectation-checkbox"]')).toHaveLength(0)
        expect(root.querySelector('[data-testid="expectation-row"]').textContent).toContain('Lösung: Ein Beispiel')
        expect(root.querySelector('h3').textContent).toContain('Tabelle (3 VP)')
        expect(root.querySelector('[data-testid="expectation-row"]').classList.contains('border')).toBe(false)
        expect(root.querySelector('[data-testid="expectation-row"]').textContent).not.toContain('Ausprägung')
        expect(root.querySelectorAll('input[type="number"]')).toHaveLength(3)
        expect(root.querySelectorAll('[data-testid="subtask-heading"]')).toHaveLength(2)
        expect(root.querySelectorAll('[data-testid="subtask-heading"]')[0].textContent).toContain('Nenne ein Beispiel.')
        expect(root.querySelectorAll('[data-testid="subtask-heading"]')[1].textContent).toContain('Begründe.')
        unmount()
    })

    it('uses and saves expectation rows for image labeling tasks', async () => {
        const { root, unmount } = mount(TaskEvaluation, {
            group,
            assessment,
            task: {
                id: 23,
                title: 'Pflanze beschriften',
                task_type: 'image_labeling',
                max_points: 2,
                content: { points_per_correct_answer: 2 },
                label_options: [{ id: 'label-1', text: 'Wurzel' }],
                expectations: [{ id: 34, text: 'Zusätzliche Beobachtung', points: 1, repetitions: 1 }],
            },
            fragments: [{ id: 43, booklet_id: 8, image_url: '/private/task.png', review: null }],
            openKey: 1,
        })

        expect(root.querySelectorAll('[data-testid="expectation-row"]')).toHaveLength(2)
        expect(root.querySelectorAll('[data-testid="expectation-row"]')[0].textContent).toContain('Korrekt beschriftet: Wurzel (2 VP)')
        expect(root.querySelectorAll('[data-testid="expectation-row"]')[1].textContent).toContain('Zusätzliche Beobachtung (1 VP)')
        expect(root.querySelector('[data-testid="image-matching-task-evaluation"]')).toBeNull()
        expect(root.querySelectorAll('input[type="checkbox"]')).toHaveLength(0)
        expect(root.querySelector('[data-testid="points-summary-43"]').textContent).toContain('0 / 2 Punkte')
        root.querySelector('[data-testid="full-points-43-label-1-1"]').click()
        await nextTick()
        expect(root.querySelector('[data-testid="points-summary-43"]').textContent).toContain('2 / 2 Punkte')
        root.querySelector('form').dispatchEvent(new Event('submit', { cancelable: true }))
        await nextTick()
        expect(testState.routerPut).toHaveBeenCalledWith(
            '/unterrichtsgruppen/11/lernstandserhebungen/3/auswertung/booklets/8/tasks/23/review',
            expect.objectContaining({
                items: [{ expectation_id: 34, occurrence: 1, awarded_points: 0, note: null }],
                options: [{ id: 'label-1', selected: true }],
            }),
            expect.anything(),
        )
        unmount()
    })

    it('uses temporary expectation rows with thumbnails for image matching tasks', async () => {
        const { root, unmount } = mount(TaskEvaluation, {
            group,
            assessment,
            task: {
                id: 24,
                title: 'Bilder zuordnen',
                task_type: 'image_matching',
                max_points: 2,
                content: { points_per_correct_answer: 2 },
                images: [{ id: 'pair-1', label: 'Apfel', answer: 'Obst', image_url: '/apple.png' }],
                expectations: [],
            },
            fragments: [{ id: 44, booklet_id: 8, image_url: '/private/task.png', review: null }],
            openKey: 1,
        })

        expect(root.querySelectorAll('[data-testid="expectation-row"]')).toHaveLength(1)
        expect(root.querySelector('[data-testid="expectation-row"]').textContent).toContain('Du hast Obst dem korrekten Bild zugeordnet. (2 VP)')
        expect(root.querySelector('[data-testid="expectation-thumbnail"]').getAttribute('src')).toBe('/apple.png')
        expect(root.querySelector('[data-testid="image-matching-task-evaluation"]')).toBeNull()
        root.querySelector('[data-testid="full-points-44-pair-1-1"]').click()
        await nextTick()
        expect(root.querySelector('[data-testid="points-summary-44"]').textContent).toContain('2 / 2 Punkte')
        root.querySelector('form').dispatchEvent(new Event('submit', { cancelable: true }))
        await nextTick()
        expect(testState.routerPut).toHaveBeenCalledWith(
            '/unterrichtsgruppen/11/lernstandserhebungen/3/auswertung/booklets/8/tasks/24/review',
            expect.objectContaining({ items: [], options: [{ id: 'pair-1', selected: true }] }),
            expect.anything(),
        )
        unmount()
    })

    it('shows live assigned and maximum points for checkbox evaluation', async () => {
        const { root, unmount } = mount(TaskEvaluation, {
            group,
            assessment,
            task: {
                id: 22,
                title: 'Angekreuzte Antworten',
                task_type: 'checkbox',
                max_points: 4,
                checkbox_scoring_mode: 'correct_states',
                content: {
                    options: [
                        { text: 'Richtig', correct: true },
                        { text: 'Falsch', correct: false },
                    ],
                    points_per_correct_answer: 2,
                },
                expectations: [],
            },
            fragments: [{ id: 42, booklet_id: 8, image_url: '/private/task.png', review: null }],
            openKey: 1,
        })

        expect(root.querySelector('[data-testid="points-summary-42"]').textContent).toContain('2 / 4 Punkte')
        root.querySelectorAll('input[type="checkbox"]')[0].click()
        await nextTick()
        expect(root.querySelector('[data-testid="points-summary-42"]').textContent).toContain('4 / 4 Punkte')

        unmount()
    })

    it('renders the checkbox evaluator for migrated checkbox tasks', () => {
        const { root, unmount } = mount(TaskEvaluation, {
            group,
            assessment,
            task: {
                id: 22,
                title: 'Angekreuzte Antworten',
                task_type: 'checkbox',
                evaluation_mode: 'legacy_checkbox',
                content: {
                    options: [{ id: 'a1', text: 'Richtige Antwort', correct: true }],
                    points_per_correct_answer: 1,
                },
                expectations: [],
            },
            fragments: [{ id: 42, booklet_id: 8, image_url: '/private/task.png', review: null }],
            openKey: 1,
        })

        expect(root.querySelector('[data-testid="checkbox-task-evaluation"]')).not.toBeNull()
        unmount()
    })

    it('marks a reviewed task fragment open again when any review field changes', async () => {
        const task = {
            id: 21,
            title: 'Schöpfung beschreiben',
            expectations: [{ id: 31, text: 'Nennt Beispiele.', points: 2, repetitions: 1 }],
        }
        const fragments = [{
            id: 41,
            booklet_id: 8,
            image_url: '/private/task.png',
            review: {
                items: [{ expectation_id: 31, occurrence: 1, awarded_points: 2, note: 'vollständig' }],
                extra_points: 1,
                extra_note: 'Zusatz',
            },
        }]
        const state = reactive({ group, assessment, task, fragments, openKey: 1 })
        const root = document.createElement('div')
        document.body.append(root)
        const app = createApp({
            components: { TaskEvaluation },
            setup: () => ({ state }),
            template: '<TaskEvaluation v-bind="state" />',
        })
        app.mount(root)

        for (const selector of [
            '[data-testid="points-41-31-1"]',
            '[data-testid="note-41-31-1"]',
            '[data-testid="extra-points-41"]',
            '#extra-note-41',
        ]) {
            expect(root.querySelector('.text-bg-success')).not.toBeNull()
            const field = root.querySelector(selector)
            field.value = `${field.value}x`
            field.dispatchEvent(new Event('input'))
            await nextTick()
            expect(root.querySelector('.text-bg-success')).toBeNull()
            state.openKey += 1
            await nextTick()
        }

        app.unmount()
        root.remove()
    })

    it('shuffles task fragments whenever the task panel is opened again', async () => {
        const task = { id: 21, title: 'Schöpfung beschreiben', expectations: [] }
        const fragments = [
            { id: 41, image_url: '/private/1.png', review: null },
            { id: 42, image_url: '/private/2.png', review: null },
            { id: 43, image_url: '/private/3.png', review: null },
        ]
        const random = vi.spyOn(Math, 'random')
            .mockReturnValueOnce(0)
            .mockReturnValueOnce(0)
            .mockReturnValueOnce(0.99)
            .mockReturnValueOnce(0.99)
        const state = reactive({ group, assessment, task, fragments, openKey: 1 })
        const root = document.createElement('div')
        document.body.append(root)
        const app = createApp({
            components: { TaskEvaluation },
            setup: () => ({ state }),
            template: '<TaskEvaluation v-bind="state" />',
        })
        app.mount(root)
        const firstOrder = [...root.querySelectorAll('[data-testid="task-fragment-id"]')].map((element) => element.textContent)
        state.openKey = 2
        await nextTick()
        const secondOrder = [...root.querySelectorAll('[data-testid="task-fragment-id"]')].map((element) => element.textContent)

        expect(firstOrder).not.toEqual(secondOrder)
        random.mockRestore()
        app.unmount()
        root.remove()
    })

    it('keeps a review validation error on the affected task fragment only', async () => {
        testState.routerPut.mockImplementation((url, data, options) => options.onError({ items: 'Bitte bewerte jede Ausprägung.' }))
        const { root, unmount } = mount(TaskEvaluation, {
            group,
            assessment,
            task: { id: 21, title: 'Schöpfung beschreiben', expectations: [] },
            fragments: [
                { id: 41, booklet_id: 8, image_url: '/private/1.png', review: null },
                { id: 42, booklet_id: 9, image_url: '/private/2.png', review: null },
            ],
            openKey: 1,
        })

        root.querySelector('form').dispatchEvent(new Event('submit', { cancelable: true }))
        await nextTick()

        const cards = root.querySelectorAll('.card')
        expect(cards[0].textContent).toContain('Bitte bewerte jede Ausprägung.')
        expect(cards[1].textContent).not.toContain('Bitte bewerte jede Ausprägung.')
        unmount()
    })

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
        testState.forms[0].errors.status = 'Das Exemplar kann noch nicht verworfen werden.'
        await nextTick()
        const cards = root.querySelectorAll('.card')
        expect(cards[0].querySelector('button').disabled).toBe(true)
        expect(cards[1].querySelector('button').disabled).toBe(false)
        expect(cards[0].textContent).toContain('Das Exemplar kann noch nicht verworfen werden.')
        expect(cards[1].textContent).not.toContain('Das Exemplar kann noch nicht verworfen werden.')
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
        expect(testState.scanClients).toHaveLength(1)
        expect(testState.startScan).toHaveBeenCalledTimes(2)
        unmount()
    })
})
