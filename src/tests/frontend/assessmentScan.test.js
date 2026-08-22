import { expect, it } from 'vitest'
import { parseRooMarker } from '../../resources/js/Features/AssessmentScan/markerParser'
import { groupRooMarkers } from '../../resources/js/Features/AssessmentScan/bookletGrouper'
import { fragmentBounds } from '../../resources/js/Features/AssessmentScan/fragmentBounds'

it('normalizes the Roo marker contract', () => {
    expect(parseRooMarker('ROO1|T=7|K=START', 2, 4.5)).toMatchObject({
        page: 2,
        y_cm: 4.5,
        kind: 'START',
        task_id: '7',
    })
})

it('groups pages and task markers into anonymous booklets', () => {
    const result = groupRooMarkers([
        parseRooMarker('ROO1|A=42|L=M|K=PAGE', 1, 2),
        parseRooMarker('ROO1|T=7|K=START', 1, 4),
        parseRooMarker('ROO1|T=7|K=END', 1, 20),
        parseRooMarker('ROO1|A=42|L=M|K=PAGE', 3, 2),
    ])

    expect(result.booklets).toHaveLength(2)
    expect(result.booklets[0].markers[1].task_id).toBe('7')
})

it('returns a crop rectangle only for an ordered same-page pair', () => {
    expect(fragmentBounds(
        { page: 1, y_px: 200 },
        { page: 1, y_px: 1000 },
        1600,
        2200,
    )).toEqual({ x: 0, y: 200, width: 1600, height: 800 })
    expect(fragmentBounds(
        { page: 1, y_px: 200 },
        { page: 2, y_px: 1000 },
        1600,
        2200,
    )).toBeNull()
})
