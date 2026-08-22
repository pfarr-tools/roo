export function createScanQueue(upload) {
    const items = new Map()
    let chain = Promise.resolve()

    async function uploadOne(item) {
        item.status = 'uploading'
        try {
            const acknowledgement = await upload(item)
            Object.assign(item, acknowledgement, { status: 'uploaded' })
        } catch (error) {
            item.status = 'failed'
            item.error = error instanceof Error ? error.message : String(error)
        }
        return item
    }

    return {
        enqueue(input) {
            const item = { ...input, status: 'queued' }
            items.set(item.id, item)
            const result = chain.then(() => uploadOne(item))
            chain = result.catch(() => undefined)
            return result
        },
        async uploadAll(input) {
            await Promise.all(input.map((item) => this.enqueue(item)))
            return [...items.values()]
        },
        async retry(id) {
            const item = items.get(id)
            if (!item || item.status !== 'failed') return item ?? null
            return uploadOne(item)
        },
        get(id) {
            return items.get(id) ?? null
        },
    }
}
