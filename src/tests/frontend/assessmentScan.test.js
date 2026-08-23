import { expect, it, vi } from 'vitest'
import { parseRooMarker } from '../../resources/js/Features/AssessmentScan/markerParser'
import { groupRooMarkers } from '../../resources/js/Features/AssessmentScan/bookletGrouper'
import { fragmentBounds } from '../../resources/js/Features/AssessmentScan/fragmentBounds'

vi.mock('../../resources/js/Features/AssessmentScan/pdfRenderer', () => ({
    openPdf: vi.fn(async () => ({ numPages: 1 })),
    renderPdfPage: vi.fn(async () => ({
        canvas: {
            toBlob: (callback) => callback(new Blob(['page'], { type: 'image/png' })),
            width: 100,
            height: 100,
        },
    })),
}))

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

it('opens the durable evaluation after all scanned pages were accepted', async () => {
    const { createScanClient } = await import('../../resources/js/Features/AssessmentScan/scanClient')
    const completed = vi.fn()
    vi.stubGlobal('document', { querySelector: () => null })
    vi.stubGlobal('URL', { createObjectURL: () => 'blob:page' })
    const fetchMock = vi.spyOn(globalThis, 'fetch')
        .mockResolvedValueOnce(new Response(JSON.stringify({ session_id: 'session-1' }), { status: 201 }))
        .mockResolvedValueOnce(new Response(JSON.stringify({ markers: [] }), { status: 201 }))
        .mockResolvedValueOnce(new Response(JSON.stringify({ redirect_url: '/unterrichtsgruppen/11/lernstandserhebungen/3/auswertung' }), { status: 200 }))

    const client = createScanClient({
        pdf: new File(['pdf'], 'scan.pdf', { type: 'application/pdf' }),
        sessionUrl: '/session',
        pageUrl: (sessionId) => `/session/${sessionId}/pages`,
        completeUrl: (sessionId) => `/session/${sessionId}/complete`,
        onComplete: completed,
    })

    await client.start()

    expect(completed).toHaveBeenCalledWith('/unterrichtsgruppen/11/lernstandserhebungen/3/auswertung')
    fetchMock.mockRestore()
    vi.unstubAllGlobals()
})

it('reuses the accepted scan session when the completion response was lost', async () => {
    const { createScanClient } = await import('../../resources/js/Features/AssessmentScan/scanClient')
    vi.stubGlobal('document', { querySelector: () => null })
    vi.stubGlobal('URL', { createObjectURL: () => 'blob:page' })
    const fetchMock = vi.spyOn(globalThis, 'fetch')
        .mockResolvedValueOnce(new Response(JSON.stringify({ session_id: 'session-1' }), { status: 201 }))
        .mockResolvedValueOnce(new Response(JSON.stringify({ markers: [] }), { status: 201 }))
        .mockRejectedValueOnce(new Error('Netzwerk unterbrochen.'))
        .mockResolvedValueOnce(new Response(JSON.stringify({ markers: [] }), { status: 201 }))
        .mockResolvedValueOnce(new Response(JSON.stringify({ redirect_url: '/auswertung' }), { status: 200 }))
    const client = createScanClient({
        pdf: new File(['pdf'], 'scan.pdf', { type: 'application/pdf' }),
        sessionUrl: '/session',
        pageUrl: (sessionId) => `/session/${sessionId}/pages`,
        completeUrl: (sessionId) => `/session/${sessionId}/complete`,
    })

    await expect(client.start()).rejects.toThrow('Netzwerk unterbrochen.')
    await client.start()

    expect(fetchMock.mock.calls.map(([url]) => url)).toEqual([
        '/session',
        '/session/session-1/pages',
        '/session/session-1/complete',
        '/session/session-1/pages',
        '/session/session-1/complete',
    ])
    fetchMock.mockRestore()
    vi.unstubAllGlobals()
})
