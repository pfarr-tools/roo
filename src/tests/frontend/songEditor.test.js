// @vitest-environment happy-dom

import { createApp, nextTick, reactive } from "vue";
import { describe, expect, it, vi } from "vitest";

const { visit, forms } = vi.hoisted(() => ({
    visit: vi.fn(),
    forms: [],
}));

vi.mock("@inertiajs/vue3", () => ({
    router: { visit },
    useForm(initial) {
        const form = reactive({
            ...initial,
            processing: false,
            errors: {},
            defaults: vi.fn(),
            reset: vi.fn(),
            transform: vi.fn((callback) => {
                form.transformed = callback(form);
                return form;
            }),
            put: vi.fn((url, options) => {
                form.lastPut = { url, options };
            }),
            post: vi.fn(),
        });
        forms.push(form);
        return form;
    },
}));

vi.mock("../../resources/js/Components/Ui/AppShell.vue", () => ({
    default: { template: "<div><slot name=\"toolbar\"></slot><slot></slot></div>" },
}));

vi.mock("../../resources/js/Components/Ui/FluxImageGeneratorModal.vue", () => ({
    default: { template: "<div />" },
}));

vi.mock("../../resources/js/Components/Songs/ChordEditor.vue", () => ({
    default: { template: "<div />" },
}));

import SongEditor from "../../resources/js/Pages/Songs/Index.vue";

function mount() {
    const root = document.createElement("div");
    document.body.append(root);
    const app = createApp(SongEditor, {
        songVersion: {
            id: 7,
            name: "Standardfassung",
            language: "de",
            song: { title: "Ein Lied" },
            parts: [],
            chord_sets: [],
            images: [],
            layout_data: { images: [] },
        },
        isCreating: false,
        songStyles: {},
        libraryImages: [],
        flux: {},
    });
    app.mount(root);

    return {
        root,
        unmount: () => {
            app.unmount();
            root.remove();
        },
    };
}

describe("SongEditor", () => {
    it("keeps the editor open after saving", async () => {
        forms.length = 0;
        visit.mockClear();
        const { root, unmount } = mount();
        await nextTick();

        root.querySelector("button.btn-primary").click();
        await nextTick();

        forms[1].lastPut.options.onSuccess();

        expect(visit).not.toHaveBeenCalled();
        unmount();
    });

    it("closes without saving when the close button is clicked", async () => {
        forms.length = 0;
        visit.mockClear();
        const { root, unmount } = mount();
        await nextTick();

        root.querySelector('button[title="Schließen"]').click();

        expect(forms[0].put).not.toHaveBeenCalled();
        expect(visit).toHaveBeenCalledWith("/bibliothek");
        unmount();
    });
});
