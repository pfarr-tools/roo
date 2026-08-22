export function groupRooMarkers(markers) {
    const booklets = []
    const warnings = []
    let booklet = null
    const openTasks = new Set()

    for (const marker of markers) {
        if (marker.kind === 'PAGE') {
            booklet = {
                number: booklets.length + 1,
                start_page: marker.page,
                markers: [marker],
            }
            booklets.push(booklet)
            openTasks.clear()
            continue
        }

        if (!booklet) {
            warnings.push(`Marker vor dem ersten Booklet auf Seite ${marker.page}.`)
            continue
        }

        booklet.markers.push(marker)
        if (marker.kind === 'START') openTasks.add(marker.task_id)
        if (marker.kind === 'END') {
            if (!openTasks.delete(marker.task_id)) {
                warnings.push(`Endmarker ohne Start für Aufgabe ${marker.task_id} auf Seite ${marker.page}.`)
            }
        }
    }

    for (const taskId of openTasks) {
        warnings.push(`Aufgabe ${taskId} endet nicht innerhalb des Scans.`)
    }

    return { booklets, warnings }
}
