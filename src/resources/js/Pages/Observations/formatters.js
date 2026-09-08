export function formatObservationDate(value) {
    if (!value) return '–'

    const datePart = String(value).slice(0, 10)
    const [year, month, day] = datePart.split('-')
    return `${day}.${month}.${year}`
}
