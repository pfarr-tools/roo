export const evaluationSections = [
    { id: 'booklets', label: 'assessmentEvaluationAssignments' },
    { id: 'tasks', label: 'assessmentEvaluationTasks' },
    { id: 'results', label: 'assessmentEvaluationResults' },
]

export function bookletStudentOptions(booklet, students, booklets) {
    const assignedBooklets = new Map(
        booklets
            .filter((candidate) => candidate.status === 'open' && candidate.student_id && candidate.id !== booklet.id)
            .map((candidate) => [candidate.student_id, candidate.number]),
    )

    return students.map((student) => ({
        id: student.id,
        label: [
            `${student.last_name}, ${student.first_name}`,
            student.class_name,
        ].filter(Boolean).join(' · '),
        disabled: assignedBooklets.has(student.id),
    }))
}

export function bookletActionUrl(groupId, assessmentId, bookletId, action) {
    const base = `/unterrichtsgruppen/${groupId}/lernstandserhebungen/${assessmentId}/auswertung/booklets/${bookletId}`

    return action === 'assignment' ? `${base}/zuordnung` : `${base}/status`
}

export function uploadProgressDetails(progress) {
    return {
        page: progress.totalPages ? `${progress.currentPage} / ${progress.totalPages}` : null,
        markers: progress.detectedMarkers ?? 0,
        fragments: progress.detectedFragments ?? 0,
        uploadedPages: progress.totalPages ? `${progress.uploadedPages ?? 0} / ${progress.totalPages}` : null,
    }
}

export function scanPhaseLabel(phase) {
    return {
        idle: de.assessmentScanPhaseIdle,
        session: de.assessmentScanPhaseSession,
        rendering: de.assessmentScanPhaseRendering,
        page: de.assessmentScanPhasePage,
        complete: de.assessmentScanPhaseComplete,
        cancelled: de.assessmentScanPhaseCancelled,
    }[phase] ?? de.assessmentScanPhaseIdle
}

export function uploadErrorMessage(error) {
    return error?.message || 'Die Scan-Anfrage ist fehlgeschlagen.'
}
import de from '../../i18n/de'
