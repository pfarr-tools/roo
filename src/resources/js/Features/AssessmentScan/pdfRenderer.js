import * as pdfjsLib from 'pdfjs-dist'
import pdfWorkerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url'

const pageOrigin = globalThis.location?.origin
const workerOrigin = pageOrigin ? new URL(pdfWorkerUrl, globalThis.location.href).origin : pageOrigin
const useWorker = !pageOrigin || workerOrigin === pageOrigin

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorkerUrl

export async function openPdf(file) {
    return pdfjsLib.getDocument({ data: await file.arrayBuffer(), disableWorker: !useWorker }).promise
}

export async function renderPdfPage(pdf, pageNumber, dpi = 300) {
    const page = await pdf.getPage(pageNumber)
    const viewport = page.getViewport({ scale: dpi / 72 })
    const canvas = document.createElement('canvas')
    canvas.width = Math.ceil(viewport.width)
    canvas.height = Math.ceil(viewport.height)

    await page.render({
        canvasContext: canvas.getContext('2d', { willReadFrequently: true }),
        viewport,
    }).promise

    return { canvas, width: canvas.width, height: canvas.height }
}
