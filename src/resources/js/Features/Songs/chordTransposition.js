const keyRoots = {
    'C-Dur': 'C', 'G-Dur': 'G', 'D-Dur': 'D', 'A-Dur': 'A', 'E-Dur': 'E',
    'B-Dur': 'B♮', 'F#-Dur': 'F#', 'C#-Dur': 'C#', 'Db-Dur': 'Db', 'Ab-Dur': 'Ab',
    'Eb-Dur': 'Eb', 'Bb-Dur': 'Bb', 'F-Dur': 'F', 'A-Moll': 'A', 'E-Moll': 'E',
    'B-Moll': 'B♮', 'F#-Moll': 'F#', 'C#-Moll': 'C#', 'G#-Moll': 'G#',
    'D#-Moll': 'D#', 'A#-Moll': 'A#', 'F-Moll': 'F', 'C-Moll': 'C',
    'G-Moll': 'G', 'D-Moll': 'D',
};
const pitchClasses = { C: 0, 'B#': 0, 'C#': 1, Db: 1, D: 2, 'D#': 3, Eb: 3, E: 4, Fb: 4, 'E#': 5, F: 5, 'F#': 6, Gb: 6, G: 7, 'G#': 8, Ab: 8, A: 9, 'A#': 10, Bb: 10, B: 11, 'B♮': 11, H: 11 };
const sharpRoots = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B♮'];
const flatRoots = ['C', 'Db', 'D', 'Eb', 'E', 'F', 'Gb', 'G', 'Ab', 'A', 'Bb', 'B♮'];

function normalizeRoot(root) {
    return root === 'H' ? 'B♮' : root === 'B' ? 'B♮' : root;
}

function rootForKey(key) {
    return keyRoots[key] ?? null;
}

function transposeRoot(root, distance, useFlats) {
    const pitch = pitchClasses[normalizeRoot(root)];
    if (pitch === undefined) return root;
    const roots = useFlats ? flatRoots : sharpRoots;
    return roots[(pitch + distance + 120) % 12];
}

export function transposeChord(chord, fromKey, toKey) {
    const fromRoot = rootForKey(fromKey);
    const toRoot = rootForKey(toKey);
    if (!fromRoot || !toRoot || fromKey === toKey) return chord;
    const distance = (pitchClasses[toRoot] - pitchClasses[fromRoot] + 12) % 12;
    const useFlats = toRoot.includes('b');

    return String(chord ?? '').split('/').map((part) => {
        const match = part.match(/^(B♮|[A-G](?:#|b)?)(.*)$/);
        return match ? `${transposeRoot(match[1], distance, useFlats)}${match[2]}` : part;
    }).join('/');
}

export function transposeChords(chords, fromKey, toKey) {
    return (chords ?? []).map((chord) => ({
        ...chord,
        chord: transposeChord(chord.chord, fromKey, toKey),
    }));
}
