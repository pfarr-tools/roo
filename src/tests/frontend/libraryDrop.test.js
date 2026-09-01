import { describe, expect, it } from 'vitest'
import { extractLibraryDropItems } from '../../resources/js/Pages/Resources/libraryDrop'

function dataTransfer({ files = [], uriList = '', text = '' } = {}) {
    return {
        files,
        getData(type) {
            return type === 'text/uri-list' ? uriList : text
        },
    }
}

describe('extractLibraryDropItems', () => {
    it('extracts files and unique URLs from a browser drop', () => {
        const file = new File(['Inhalt'], 'Arbeitsblatt.pdf', { type: 'application/pdf' })

        expect(extractLibraryDropItems(dataTransfer({
            files: [file],
            uriList: 'https://example.test/erste\n# Kommentar\nhttps://example.test/zweite',
        }))).toEqual({ files: [file], urls: ['https://example.test/erste', 'https://example.test/zweite'] })
    })

    it('accepts a pasted URL from text/plain when uri-list is unavailable', () => {
        expect(extractLibraryDropItems(dataTransfer({ text: 'https://example.test/ressource' })))
            .toEqual({ files: [], urls: ['https://example.test/ressource'] })
    })
})
