import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'

const yearPlanSource = readFileSync(new URL('../../resources/js/Pages/YearPlans/Show.vue', import.meta.url), 'utf8')

describe('year plan LSE menu', () => {
    it('stops status menu clicks from opening the LSE editor', () => {
        expect(yearPlanSource).toContain('@click.stop="selectSlotStatus(slot, option.value)"')
    })

    it('does not submit an empty target slot when deleting an LSE', () => {
        expect(yearPlanSource).toContain("...(assessmentTargetSlotId != null ? { assessment_target_slot_id: assessmentTargetSlotId } : {})")
    })

    it('keeps the active drag payload available while dragover protects dataTransfer', () => {
        expect(yearPlanSource).toContain('const activeDragItem = ref(null)')
        expect(yearPlanSource).toContain('return activeDragItem.value')
        expect(yearPlanSource).toContain('activeDragItem.value = item')
    })

    it('keeps the teaching-unit column as a curriculum drop target', () => {
        expect(yearPlanSource).toContain('@dragover="dragOverUnitsColumn($event)"')
        expect(yearPlanSource).toContain('@drop="dropToRemoveOrTopic($event)"')
    })
})
