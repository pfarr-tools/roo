import { groupRooMarkers } from './bookletGrouper'
import { openPdf, renderPdfPage } from './pdfRenderer'
import { pageProgress } from './scanWorker'

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''

async function jsonRequest(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            ...(options.headers ?? {}),
        },
    })
    if (!response.ok) throw new Error((await response.json().catch(() => null))?.message ?? 'Die Scan-Anfrage ist fehlgeschlagen.')
    return response.status === 204 ? null : response.json()
}

function blobFromCanvas(canvas) {
    return new Promise((resolve, reject) => canvas.toBlob((blob) => blob ? resolve(blob) : reject(new Error('Das Fragment konnte nicht erzeugt werden.')), 'image/png'))
}

export function createScanClient({ pdf, sessionUrl, pageUrl, completeUrl, onProgress = () => {}, onPagePreview = () => {}, onComplete = () => {} }) {
    let sessionId = null
    let cancelled = false
    const state = {
        phase: 'idle',
        totalPages: 0,
        currentPage: 0,
        detectedBooklets: 0,
        detectedMarkers: 0,
        detectedFragments: 0,
        uploadedPages: 0,
        status: 'idle',
    }
    const allMarkers = []

    function report(extra = {}) {
        Object.assign(state, extra)
        onProgress({ ...state })
    }

    async function uploadPage(sessionId, page, blob) {
        const formData = new FormData()
        formData.append('image', blob, `page-${page}.png`)
        formData.append('page', String(page))
        return jsonRequest(pageUrl(sessionId), { method: 'POST', body: formData })
    }

    async function start() {
        cancelled = false
        report({ phase: 'session', status: 'processing' })
        const session = await jsonRequest(sessionUrl, { method: 'POST', body: JSON.stringify({}) })
        sessionId = session.session_id
        const pdfDocument = await openPdf(pdf)
        report({ phase: 'rendering', totalPages: pdfDocument.numPages })
        for (let page = 1; page <= pdfDocument.numPages; page += 1) {
            if (cancelled) throw new Error('Scan abgebrochen.')
            report({ phase: 'page', ...pageProgress(page, pdfDocument.numPages) })
            const rendered = await renderPdfPage(pdfDocument, page)
            const previewBlob = await blobFromCanvas(rendered.canvas)
            onPagePreview(URL.createObjectURL(previewBlob), page)
            const result = await uploadPage(sessionId, page, previewBlob)
            allMarkers.push(...(result.markers ?? []))
            report({
                detectedMarkers: allMarkers.length,
                detectedFragments: allMarkers.filter((marker) => marker.kind === 'END').length,
            })
            report({ uploadedPages: state.uploadedPages + 1 })
            rendered.canvas.width = 0
            rendered.canvas.height = 0
        }

        const scan = groupRooMarkers(allMarkers)
        report({ phase: 'complete', status: 'completed', detectedBooklets: scan.booklets.length })
        const completed = await jsonRequest(completeUrl(sessionId), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({}),
        })
        if (completed.redirect_url) onComplete(completed.redirect_url)
        return { ...scan, ...completed }
    }

    async function cancel() {
        cancelled = true
        if (sessionId) await jsonRequest(sessionUrl.replace(/\/session$/, `/session/${sessionId}`), { method: 'DELETE' })
        report({ phase: 'cancelled', status: 'cancelled' })
    }

    return { start, cancel }
}
