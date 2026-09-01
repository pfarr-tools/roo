export function extractLibraryDropItems(dataTransfer) {
    const files = Array.from(dataTransfer?.files ?? [])
    const uriList = dataTransfer?.getData?.('text/uri-list') ?? ''
    const text = dataTransfer?.getData?.('text/plain') ?? ''
    const source = uriList.trim() ? uriList : text
    const urls = source
        .split(/\r?\n/)
        .map(value => value.trim())
        .filter(value => value && !value.startsWith('#'))
        .filter(value => /^https?:\/\//i.test(value))
        .filter((value, index, values) => values.indexOf(value) === index)

    return { files, urls }
}
