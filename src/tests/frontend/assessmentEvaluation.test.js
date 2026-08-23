import { describe, expect, it } from 'vitest'
import {
    evaluationSections,
    bookletStudentOptions,
    bookletActionUrl,
    uploadErrorMessage,
    uploadProgressDetails,
} from '../../resources/js/Features/AssessmentEvaluation/presentation'

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
