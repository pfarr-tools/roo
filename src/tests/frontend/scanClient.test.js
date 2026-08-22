import { expect, it, vi } from 'vitest'
import { createScanQueue } from '../../resources/js/Features/AssessmentScan/scanQueue'

it('uploads fragments sequentially and preserves acknowledgement order', async () => {
    const active = { value: 0, max: 0 }
    const upload = vi.fn(async (item) => {
        active.value += 1
        active.max = Math.max(active.max, active.value)
        await Promise.resolve()
        active.value -= 1
        return { fragment_id: item.id }
    })
    const queue = createScanQueue(upload)

    const result = await queue.uploadAll([{ id: 'one' }, { id: 'two' }])

    expect(upload.mock.calls.map(([item]) => item.id)).toEqual(['one', 'two'])
    expect(active.max).toBe(1)
    expect(result.map((item) => item.fragment_id)).toEqual(['one', 'two'])
})

it('retries only the failed fragment', async () => {
    const upload = vi.fn()
        .mockResolvedValueOnce({ fragment_id: 'one' })
        .mockRejectedValueOnce(new Error('temporary'))
        .mockResolvedValueOnce({ fragment_id: 'two' })
    const queue = createScanQueue(upload)

    const result = await queue.uploadAll([{ id: 'one' }, { id: 'two' }])

    expect(result).toEqual([
        { id: 'one', fragment_id: 'one', status: 'uploaded' },
        { id: 'two', status: 'failed', error: 'temporary' },
    ])
    await queue.retry('two')
    expect(upload.mock.calls.map(([item]) => item.id)).toEqual(['one', 'two', 'two'])
})
