import { describe, expect, it } from 'vitest'

import { transposeChord, transposeChords } from '../../resources/js/Features/Songs/chordTransposition'

describe('chord transposition', () => {
    it('transposes a chord while preserving its suffix', () => {
        expect(transposeChord('G7', 'G-Dur', 'D-Dur')).toBe('D7')
    })

    it('transposes every chord in a copied instrument set', () => {
        expect(transposeChords([
            { chord: 'G', line_number: 0 },
            { chord: 'Em', line_number: 1 },
        ], 'G-Dur', 'D-Dur').map(item => item.chord)).toEqual(['D', 'B♮m'])
    })
})
