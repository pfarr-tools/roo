import { describe, expect, it } from 'vitest'
import { observationScaleLabels } from '../../resources/js/Pages/Schools/schoolObservationScale'

describe('observationScaleLabels', () => {
    it('creates plus labels and a separate ne status for the school interval count', () => {
        expect(observationScaleLabels(4)).toEqual(['+', '++', '+++', '++++', 'ne'])
    })

    it('does not create a scale for an invalid interval count', () => {
        expect(() => observationScaleLabels(1)).toThrow('Ungültige Anzahl Beobachtungsstufen')
    })
})
