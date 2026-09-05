export function createLessonObservationApi({ axios, debounceMs = 300, onError = () => {} }) {
    const noteTimers = new Map()
    const requestConfig = () => ({
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': globalThis.document?.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
        },
    })

    function saveStudent(url, payload) {
        return axios.put(url, payload, requestConfig())
    }

    function saveStudentDebounced(url, payload) {
        globalThis.clearTimeout(noteTimers.get(url))
        const timer = globalThis.setTimeout(() => {
            noteTimers.delete(url)
            saveStudent(url, payload).catch(onError)
        }, debounceMs)
        noteTimers.set(url, timer)
    }

    function bulkRate(url, payload) {
        return axios.post(url, payload, requestConfig())
    }

    return { saveStudent, saveStudentDebounced, bulkRate }
}
