import { groupRooMarkers } from './bookletGrouper'
import { fragmentBounds } from './fragmentBounds'
import { openPdf, renderPdfPage } from './pdfRenderer'
import { createScanQueue } from './scanQueue'
import { createScanWorker, pageProgress } from './scanWorker'

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

function pageFragments(markers, page, canvas) {
    const ordered = markers.filter((marker) => marker.page === page).sort((left, right) => left.y_px - right.y_px)
    const fragments = []
    let start = null

    for (const marker of ordered) {
        if (marker.kind === 'START') {
            start = marker
            continue
        }
        if (marker.kind !== 'END' || !start || start.task_id !== marker.task_id) continue
        const bounds = fragmentBounds(start, marker, canvas.width, canvas.height)
        if (bounds) fragments.push({ start, end: marker, bounds })
        start = null
    }
    return fragments
}

export function createScanClient({ pdf, sessionUrl, fragmentUrl, onProgress = () => {}, onFragmentError = () => {} }) {
    let sessionId = null
    let worker = null
    let cancelled = false
    const state = {
        phase: 'idle',
        totalPages: 0,
        currentPage: 0,
        detectedBooklets: 0,
        detectedMarkers: 0,
        queuedFragments: 0,
        uploadedFragments: 0,
        failedFragments: 0,
        status: 'idle',
    }
    const allMarkers = []
    const results = new Map()

    function report(extra = {}) {
        Object.assign(state, extra)
        onProgress({ ...state })
    }

    async function uploadFragment(item) {
        const formData = new FormData()
        formData.append('fragment', item.blob, `${item.id}.png`)
        formData.append('page', String(item.page))
        formData.append('booklet', String(item.booklet))
        formData.append('task_id', String(item.taskId))
        formData.append('start_y_cm', String(item.start.y_cm))
        formData.append('end_y_cm', String(item.end.y_cm))
        return jsonRequest(fragmentUrl(sessionId), { method: 'POST', body: formData })
    }

    async function start() {
        cancelled = false
        report({ phase: 'session', status: 'processing' })
        const session = await jsonRequest(sessionUrl, { method: 'POST', body: JSON.stringify({}) })
        sessionId = session.session_id
        const pdfDocument = await openPdf(pdf)
        report({ phase: 'rendering', totalPages: pdfDocument.numPages })
        const queue = createScanQueue(uploadFragment)
        const pending = []
        worker = createScanWorker({
            'page-result': (result) => results.get(result.pageNumber)?.resolve(result),
            warning: (warning) => onProgress({ ...state, warning }),
            error: (error) => results.get(error.pageNumber)?.reject(error),
        })

        for (let page = 1; page <= pdfDocument.numPages; page += 1) {
            if (cancelled) throw new Error('Scan abgebrochen.')
            report({ phase: 'page', ...pageProgress(page, pdfDocument.numPages) })
            const rendered = await renderPdfPage(pdfDocument, page)
            const resultPromise = new Promise((resolve, reject) => results.set(page, { resolve, reject }))
            const bitmap = await createImageBitmap(rendered.canvas)
            worker.scanPage({ pageNumber: page, bitmap }, [bitmap])
            const result = await resultPromise
            allMarkers.push(...result.markers)
            report({ detectedMarkers: allMarkers.length })

            for (const fragment of pageFragments(result.markers, page, rendered.canvas)) {
                const crop = globalThis.document.createElement('canvas')
                crop.width = fragment.bounds.width
                crop.height = fragment.bounds.height
                crop.getContext('2d').drawImage(rendered.canvas, fragment.bounds.x, fragment.bounds.y, fragment.bounds.width, fragment.bounds.height, 0, 0, crop.width, crop.height)
                const item = {
                    id: `${page}-${fragment.start.task_id}-${pending.length}`,
                    blob: await blobFromCanvas(crop),
                    page,
                    booklet: 1,
                    taskId: fragment.start.task_id,
                    start: fragment.start,
                    end: fragment.end,
                }
                pending.push(item)
                report({ queuedFragments: pending.length })
                const uploadResult = await queue.enqueue(item)
                if (uploadResult.status === 'uploaded') {
                    report({ uploadedFragments: state.uploadedFragments + 1 })
                } else {
                    report({ failedFragments: state.failedFragments + 1 })
                    onFragmentError(item, new Error(uploadResult.error))
                }
            }
            rendered.canvas.width = 0
            rendered.canvas.height = 0
        }

        worker.terminate()
        report({ phase: 'complete', status: 'completed', detectedBooklets: groupRooMarkers(allMarkers).booklets.length })
        return { ...groupRooMarkers(allMarkers), fragments: pending }
    }

    async function cancel() {
        cancelled = true
        worker?.terminate()
        if (sessionId) await jsonRequest(sessionUrl.replace(/\/session$/, `/session/${sessionId}`), { method: 'DELETE' })
        report({ phase: 'cancelled', status: 'cancelled' })
    }

    return { start, cancel }
}
