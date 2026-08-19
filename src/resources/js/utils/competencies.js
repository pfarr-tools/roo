export function formatCompetencyIdentifier(value) {
    const identifier = String(value ?? '')
    const match = identifier.match(/^(\d+\.\d+\.\d+)\.(\d+)$/)

    return match ? `${match[1]} (${match[2]})` : identifier
}
