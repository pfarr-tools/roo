import { expect, it } from 'vitest'
import { normalizeDecodedMarkers, pageProgress } from '../../resources/js/Features/AssessmentScan/scanWorker'

it('normalizes decoder coordinates and preserves marker payloads', () => {
    expect(normalizeDecodedMarkers([
        { payload: 'ROO1|A=42|L=M|K=PAGE', xPx: 20, yPx: 300, widthPx: 40, heightPx: 40 },
    ], 2)).toEqual([{
        page: 2,
        y_cm: 2.54,
        y_px: 300,
        x_px: 20,
        width_px: 40,
        height_px: 40,
        kind: 'PAGE',
        assessment_id: '42',
        task_id: null,
        level: 'M',
        payload: 'ROO1|A=42|L=M|K=PAGE',
    }])
})

it('reports deterministic page progress', () => {
    expect(pageProgress(3, 12)).toEqual({ currentPage: 3, totalPages: 12, percent: 25 })
})
