import { parseRooMarker } from './markerParser'

const DPI = 300

export function normalizeDecodedMarkers(decodedMarkers, page) {
    return decodedMarkers.flatMap((decoded) => {
        const marker = parseRooMarker(
            decoded.payload,
            page,
            Math.round(((decoded.yPx / DPI) * 2.54) * 100) / 100,
        )
        if (!marker) return []

        return [{
            ...marker,
            y_px: decoded.yPx,
            x_px: decoded.xPx,
            width_px: decoded.widthPx,
            height_px: decoded.heightPx,
        }]
    })
}

export function pageProgress(currentPage, totalPages) {
    return {
        currentPage,
        totalPages,
        percent: totalPages ? Math.round((currentPage / totalPages) * 100) : 0,
    }
}

export function createScanWorker(callbacks = {}) {
    const worker = new Worker(new URL('./scanPage.worker.js', import.meta.url), { type: 'module' })
    worker.addEventListener('message', ({ data }) => callbacks[data.type]?.(data))
    worker.addEventListener('error', (error) => callbacks.error?.(error))

    return {
        scanPage(payload, transfer = []) {
            worker.postMessage({ type: 'scan-page', ...payload }, transfer)
        },
        terminate() {
            worker.terminate()
        },
    }
}
