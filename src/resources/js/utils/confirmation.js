import { reactive } from 'vue'

export const confirmationState = reactive({ open: false, title: '', message: '', actions: [], resolve: null })

export function requestConfirmation({ title = 'Bestätigung', message, actions = [{ value: true, label: 'Bestätigen', variant: 'primary' }, { value: false, label: 'Abbrechen', variant: 'secondary' }] }) {
    return new Promise(resolve => {
        confirmationState.title = title
        confirmationState.message = message
        confirmationState.actions = actions
        confirmationState.resolve = resolve
        confirmationState.open = true
    })
}

export function closeConfirmation(value = false) {
    const resolve = confirmationState.resolve
    confirmationState.open = false
    confirmationState.resolve = null
    if (resolve) resolve(value)
}
