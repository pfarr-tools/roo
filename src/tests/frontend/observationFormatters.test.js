import { describe, expect, it } from 'vitest'
import { formatObservationDate } from '../../resources/js/Pages/Observations/formatters'

describe('observation formatters', () => {
    it('formats Laravel ISO timestamps as German calendar dates', () => {
        expect(formatObservationDate('2026-09-08T00:00:00.000000Z')).toBe('08.09.2026')
    })
})
