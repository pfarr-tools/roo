export function observationScaleLabels(intervalCount) {
    const count = Number(intervalCount)

    if (!Number.isInteger(count) || count < 2 || count > 6) {
        throw new Error('Ungültige Anzahl Beobachtungsstufen')
    }

    return [...Array.from({ length: count }, (_, index) => '+'.repeat(index + 1)), 'ne']
}
