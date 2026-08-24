// @vitest-environment happy-dom

import { createApp, nextTick } from 'vue'
import { expect, it } from 'vitest'
import ImageMatchingTaskEvaluation from '../../resources/js/Features/AssessmentEvaluation/ImageMatchingTaskEvaluation.vue'

it('emits the checked image-text match', async () => {
    const root = document.createElement('div')
    document.body.append(root)
    const selections = []
    const app = createApp(ImageMatchingTaskEvaluation, {
        options: [{ id: 'pair-1', label: 'Löwe', answer: 'Mut', image_url: '/loewe.png', selected: false }],
        'onUpdate:selection': value => selections.push(value),
    })
    app.mount(root)

    root.querySelector('input[type="checkbox"]').click()
    await nextTick()

    expect(selections[0]).toEqual([{ id: 'pair-1', label: 'Löwe', answer: 'Mut', image_url: '/loewe.png', selected: true }])
    app.unmount()
    root.remove()
})
