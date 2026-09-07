import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'

const yearPlanSource = readFileSync(new URL('../../resources/js/Pages/YearPlans/Show.vue', import.meta.url), 'utf8')

describe('year plan LSE menu', () => {
    it('stops status menu clicks from opening the LSE editor', () => {
        expect(yearPlanSource).toContain('@click.stop="selectSlotStatus(slot, option.value)"')
    })
})
