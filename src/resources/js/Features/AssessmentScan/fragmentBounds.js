export function fragmentBounds(startMarker, endMarker, pageWidth, pageHeight) {
    if (startMarker?.page !== endMarker?.page) return null

    const y = Math.max(0, Math.min(pageHeight, startMarker.y_px))
    const endY = Math.max(0, Math.min(pageHeight, endMarker.y_px))
    if (endY <= y) return null

    return { x: 0, y, width: pageWidth, height: endY - y }
}
