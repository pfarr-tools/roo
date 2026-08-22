const MARKER_KINDS = new Set(['PAGE', 'START', 'END'])

export function parseRooMarker(payload, page, yCm) {
    if (typeof payload !== 'string' || !payload.startsWith('ROO1|')) return null

    const values = Object.fromEntries(
        payload.slice(5).split('|').map((part) => part.split('=')),
    )
    if (!MARKER_KINDS.has(values.K)) return null
    if (values.K === 'PAGE' && !values.A) return null
    if (values.K !== 'PAGE' && !values.T) return null

    return {
        page,
        y_cm: yCm,
        kind: values.K,
        assessment_id: values.A ?? null,
        task_id: values.T ?? null,
        level: values.L ?? null,
        payload,
    }
}
