import { BrowserDatamatrixCodeReader } from '@zxing/browser'

const reader = new BrowserDatamatrixCodeReader()

export async function decodeDataMatrix(canvas) {
    try {
        const result = await reader.decodeFromCanvas(canvas)
        const points = result.getResultPoints?.() ?? []
        const xs = points.map((point) => point.getX())
        const ys = points.map((point) => point.getY())
        const minX = xs.length ? Math.min(...xs) : 0
        const minY = ys.length ? Math.min(...ys) : 0
        const maxX = xs.length ? Math.max(...xs) : 0
        const maxY = ys.length ? Math.max(...ys) : 0

        return [{
            payload: result.getText(),
            xPx: minX,
            yPx: minY,
            widthPx: maxX - minX,
            heightPx: maxY - minY,
        }]
    } catch {
        return []
    }
}
