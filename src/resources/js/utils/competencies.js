export function formatCompetencyIdentifier(value) {
    const identifier = String(value ?? '')
    const match = identifier.match(/^(\d+(?:\.\d+){2})\.(\d+)$/)

    return match ? `${match[1]} (${match[2]})` : identifier
}

export function competencyNumber(competency) {
    const presentation = competency?.competency_presentation || competency?.presentation || {}
    return formatCompetencyIdentifier(presentation.identifier || competency?.external_identifier || competency?.number || '')
}

export function competencyText(competency, { removeParentheses = false } = {}) {
    const presentation = competency?.competency_presentation || competency?.presentation || {}
    const variants = (competency?.variants || competency?.education_plan_competency?.variants || competency?.educationPlanCompetency?.variants || [])
        .map(variant => variant.text)
        .filter(Boolean)
        .join(' / ')
    let text = presentation.text || competency?.text || competency?.local_wording || competency?.display || variants || ''
    if (removeParentheses) {
        while (/\([^()]*\)/u.test(text)) text = text.replace(/\s*\([^()]*\)/gu, '')
        text = text.replace(/\s+/gu, ' ').trim()
    }
    return text
}

export function competencyNumberAndText(competency) {
    return [competencyNumber(competency), competencyText(competency)].filter(Boolean).join(' – ')
}

export function competencyDuKannst(competency, { numberInParentheses = true, removeParentheses = true } = {}) {
    const text = competencyText(competency, { removeParentheses })
    const number = competencyNumber(competency)
    return `Du kannst ${text}${numberInParentheses && number ? ` (${number})` : ''}`.trim()
}
