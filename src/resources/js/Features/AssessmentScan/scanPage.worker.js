import { decodeDataMatrix } from './browserDecoder'
import { normalizeDecodedMarkers } from './scanWorker'

self.addEventListener('message', async ({ data }) => {
    if (data.type !== 'scan-page') return

    try {
        const canvas = new OffscreenCanvas(data.bitmap.width, data.bitmap.height)
        canvas.getContext('2d').drawImage(data.bitmap, 0, 0)
        data.bitmap.close()
        const decoded = await decodeDataMatrix(canvas)
        self.postMessage({
            type: 'page-result',
            pageNumber: data.pageNumber,
            markers: normalizeDecodedMarkers(decoded, data.pageNumber),
        })
    } catch (error) {
        self.postMessage({
            type: 'warning',
            pageNumber: data.pageNumber,
            message: 'Die DataMatrix-Erkennung dieser Seite ist im Browser fehlgeschlagen.',
            error: error instanceof Error ? error.message : String(error),
        })
    }
})
