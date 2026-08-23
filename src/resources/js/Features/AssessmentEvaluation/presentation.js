export const evaluationSections = [
    { id: 'scans', label: 'assessmentEvaluationScans' },
    { id: 'booklets', label: 'assessmentEvaluationAssignments' },
    { id: 'tasks', label: 'assessmentEvaluationTasks' },
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

export function uploadErrorMessage(error) {
    return error?.message || 'Die Scan-Anfrage ist fehlgeschlagen.'
}
