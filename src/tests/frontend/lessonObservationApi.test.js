import { describe, expect, it, vi } from 'vitest'
import { createLessonObservationApi } from '../../resources/js/Features/LessonObservation/observationApi'

describe('lesson observation API', () => {
    it('saves a student observation through a small axios request', async () => {
        const axios = { put: vi.fn().mockResolvedValue({}) }
        const api = createLessonObservationApi({ axios, debounceMs: 10 })

        await api.saveStudent('/unterricht/7/beobachtungen/3', { student_id: 3, attendance: 'absent' })

        expect(axios.put).toHaveBeenCalledWith('/unterricht/7/beobachtungen/3', { student_id: 3, attendance: 'absent' }, expect.objectContaining({ headers: expect.objectContaining({ 'X-Requested-With': 'XMLHttpRequest' }) }))
    })

    it('debounces note saves so only the latest value is sent', async () => {
        vi.useFakeTimers()
        const axios = { put: vi.fn().mockResolvedValue({}) }
        const api = createLessonObservationApi({ axios, debounceMs: 50 })

        api.saveStudentDebounced('/unterricht/7/beobachtungen/3', { student_id: 3, note: 'a' })
        api.saveStudentDebounced('/unterricht/7/beobachtungen/3', { student_id: 3, note: 'aktuell' })
        await vi.advanceTimersByTimeAsync(50)

        expect(axios.put).toHaveBeenCalledTimes(1)
        expect(axios.put).toHaveBeenCalledWith('/unterricht/7/beobachtungen/3', { student_id: 3, note: 'aktuell' }, expect.any(Object))
        vi.useRealTimers()
    })

    it('sends standard and custom bulk scale values', async () => {
        const axios = { post: vi.fn().mockResolvedValue({ data: { students: [] } }) }
        const api = createLessonObservationApi({ axios, debounceMs: 10 })

        await api.bulkRate('/unterricht/7/beobachtungen/bewerten', {
            scale: 4,
            custom_scale_level: null,
            custom_scale_status: 'ne',
        })

        expect(axios.post).toHaveBeenCalledWith('/unterricht/7/beobachtungen/bewerten', {
            scale: 4,
            custom_scale_level: null,
            custom_scale_status: 'ne',
        }, expect.any(Object))
    })
})
