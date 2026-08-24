// @vitest-environment happy-dom

import { createApp, nextTick } from 'vue'
import { expect, it } from 'vitest'
import CheckboxTaskEvaluation from '../../resources/js/Features/AssessmentEvaluation/CheckboxTaskEvaluation.vue'

it('renders and emits changed checkbox selections', async () => {
    const root = document.createElement('div')
    document.body.append(root)
    const selections = []
    const app = createApp(CheckboxTaskEvaluation, {
        options: [
            { id: 'a1', text: 'Richtig', selected: false },
            { id: 'a2', text: 'Falsch', selected: true },
        ],
        'onUpdate:selection': value => selections.push(value),
    })
    app.mount(root)

    expect(root.querySelectorAll('input[type="checkbox"]')).toHaveLength(2)
    root.querySelector('#checkbox-option-a1').click()
    await nextTick()
    expect(selections[0]).toEqual([
        { id: 'a1', text: 'Richtig', selected: true },
        { id: 'a2', text: 'Falsch', selected: true },
    ])

    app.unmount()
    root.remove()
})

it('toggles only the clicked option when legacy options have no ids', async () => {
    const root = document.createElement('div')
    document.body.append(root)
    const selections = []
    const app = createApp(CheckboxTaskEvaluation, {
        options: [
            { text: 'Erste Antwort', selected: false },
            { text: 'Zweite Antwort', selected: false },
        ],
        'onUpdate:selection': value => selections.push(value),
    })
    app.mount(root)

    root.querySelectorAll('input[type="checkbox"]')[0].click()
    await nextTick()

    expect(selections[0].map(option => option.selected)).toEqual([true, false])

    app.unmount()
    root.remove()
})

it('prefixes each option with a status icon without coloring the label text', () => {
    const root = document.createElement('div')
    document.body.append(root)
    const app = createApp(CheckboxTaskEvaluation, {
        options: [
            { id: 'a1', text: 'Korrekt', correct: true, selected: true },
            { id: 'a2', text: 'Nicht korrekt', correct: false, selected: true },
        ],
    })
    app.mount(root)

    const labels = root.querySelectorAll('label')
    const icons = root.querySelectorAll('label i')
    expect(labels[0].classList.contains('text-success')).toBe(false)
    expect(labels[0].classList.contains('text-danger')).toBe(false)
    expect(icons[0].classList.contains('bi-check-lg')).toBe(true)
    expect(icons[0].classList.contains('text-success')).toBe(true)
    expect(icons[1].classList.contains('bi-x-lg')).toBe(true)
    expect(icons[1].classList.contains('text-danger')).toBe(true)

    app.unmount()
    root.remove()
})
