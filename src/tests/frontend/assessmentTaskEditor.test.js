// @vitest-environment happy-dom

import { createApp, nextTick, reactive } from "vue";
import { describe, expect, it, vi } from "vitest";

let transformedPayload;
let submitError = false;

vi.mock("@inertiajs/vue3", () => ({
    router: { visit: vi.fn() },
    useForm(initial) {
        let defaults = { ...initial };
        const form = reactive({
            ...initial,
            processing: false,
            errors: {},
            defaults: vi.fn((values) => { defaults = values; }),
            reset: vi.fn(() => Object.assign(form, defaults)),
            data: vi.fn(() => ({ ...form })),
            transform: vi.fn((callback) => {
                transformedPayload = callback();
                return form;
            }),
            post: vi.fn(),
            put: vi.fn((_url, options) => {
                if (submitError)
                    options?.onError?.({ title: ["Titel fehlt."] });
            }),
        });
        return form;
    },
}));

vi.mock("../../resources/js/Components/Ui/AppShell.vue", () => ({
    default: {
        template: '<div><slot name="toolbar"></slot><slot></slot></div>',
    },
}));

vi.mock(
    "../../resources/js/Components/Planning/CompetencyPickerModal.vue",
    () => ({
        default: { template: "<div />" },
    }),
);

import Edit from "../../resources/js/Pages/AssessmentTask/Edit.vue";

function mount(props = {}) {
    const root = document.createElement("div");
    document.body.append(root);
    const app = createApp(Edit, {
        backUrl: "/back",
        submitUrl: "/save",
        educationPlans: [],
        ...props,
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

it("shows checkbox points and keeps expectations manual", async () => {
    const { root, unmount } = mount({ method: "put" });
    root.querySelectorAll('[role="tab"]')[1].click();
    await nextTick();
    Array.from(root.querySelectorAll("button"))
        .find((button) =>
            button.textContent.includes("Richtige Sätze ankreuzen"),
        )
        .click();
    await nextTick();

    expect(
        root.querySelector("#assessment-task-points-per-correct-answer"),
    ).not.toBeNull();
    expect(
        root.querySelector("#assessment-task-checkbox-scoring-mode"),
    ).not.toBeNull();
    expect(
        root.querySelector("#assessment-task-checkbox-scoring-mode").value,
    ).toBe("correct_states");
    expect(root.textContent).not.toContain("Automatische Erwartungen");
    unmount();
});

it("submits Lösungsanzeige and Lineatur for subtask tables", async () => {
    transformedPayload = undefined;
    const { root, unmount } = mount();
    root.querySelectorAll('[role="tab"]')[1].click();
    await nextTick();
    Array.from(root.querySelectorAll("button"))
        .find((button) => button.textContent.includes("Tabelle mit Teilaufgaben"))
        .click();
    await nextTick();

    const checkboxes = root.querySelectorAll('input[type="checkbox"]');
    checkboxes[0].click();
    checkboxes[1].click();
    await nextTick();
    root.querySelector("form").dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
    await nextTick();

    expect(transformedPayload.content.show_solutions).toBe(true);
    expect(transformedPayload.content.lineated).toBe(true);
    unmount();
});

it("prefills the education plan and competency for a new task", async () => {
    const { root, unmount } = mount({
        educationPlans: [{ id: 7, title: "Bildungsplan" }],
        initialEducationPlanId: 7,
        initialCompetency: {
            id: 55,
            external_identifier: "3.1.1",
            text: "Kann unterscheiden",
        },
    });
    await nextTick();

    expect(root.querySelector("#assessment-task-plan").value).toBe("7");
    expect(root.textContent).toContain("3.1.1 – Kann unterscheiden");

    unmount();
});

it("shows image matching points and the shared image width slider", async () => {
    const { root, unmount } = mount();
    root.querySelectorAll('[role="tab"]')[1].click();
    await nextTick();
    Array.from(root.querySelectorAll("button"))
        .find((button) => button.textContent.includes("Zuordnung zu Bildern"))
        .click();
    await nextTick();

    expect(
        root.querySelector("#assessment-task-points-per-correct-answer"),
    ).not.toBeNull();
    expect(
        root.querySelector("#assessment-task-image-width").getAttribute("min"),
    ).toBe("1.5");
    expect(
        root.querySelector("#assessment-task-image-width").getAttribute("max"),
    ).toBe("4");
    expect(
        root.querySelector("#assessment-task-checkbox-scoring-mode"),
    ).toBeNull();
    unmount();
});

it("offers images and an optional reading text for free text tasks", async () => {
    const { root, unmount } = mount();
    root.querySelectorAll('[role="tab"]')[1].click();
    await nextTick();

    expect(root.textContent).not.toContain("Freitext mit Bildern");
    expect(root.textContent).not.toContain("Lesetext mit Freitextfragen");

    expect(root.querySelector("#assessment-task-image-width")).not.toBeNull();
    expect(root.querySelector("#assessment-task-optional-reading-text")).not.toBeNull();
    expect(root.textContent).toContain("Bilder aus der Bibliothek");
    unmount();
});

it("submits free text images and optional reading text", async () => {
    transformedPayload = undefined;
    const { root, unmount } = mount({
        imageLibrary: [{ id: 7, name: "Baum.png", preview_url: "/baum.png" }],
    });
    root.querySelectorAll('[role="tab"]')[1].click();
    await nextTick();

    root.querySelector("#assessment-task-optional-reading-text").value = "Lies den Text.";
    root.querySelector("#assessment-task-optional-reading-text").dispatchEvent(new Event("input", { bubbles: true }));
    Array.from(root.querySelectorAll("button"))
        .find((button) => button.textContent.includes("Bibliothek"))
        .click();
    await nextTick();
    root.querySelector(".list-group button").click();
    await nextTick();
    root.querySelector("form").dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
    await nextTick();

    expect(transformedPayload.content.optional_reading_text).toBe("Lies den Text.");
    expect(transformedPayload.content.image_width_cm).toBe(3);
    expect(transformedPayload.images[0].resource_id).toBe(7);
    unmount();
});

it("initializes a matching table with categories and text rows", async () => {
    const { root, unmount } = mount();
    root.querySelectorAll('[role="tab"]')[1].click();
    await nextTick();
    Array.from(root.querySelectorAll("button"))
        .find((button) => button.textContent.includes("Zuordnungstabelle"))
        .click();
    await nextTick();

    expect(root.querySelector("#assessment-task-matching-scoring-mode")).not.toBeNull();
    expect(root.querySelectorAll('[data-matching-category]').length).toBe(1);
    expect(root.querySelectorAll('[data-matching-row]').length).toBe(1);
    expect(root.querySelectorAll('[data-matching-row] input[type="checkbox"]').length).toBe(1);
    unmount();
});

it("submits matching table categories, rows, points, and scoring mode", async () => {
    transformedPayload = undefined;
    const { root, unmount } = mount();
    root.querySelectorAll('[role="tab"]')[1].click();
    await nextTick();
    Array.from(root.querySelectorAll("button"))
        .find((button) => button.textContent.includes("Zuordnungstabelle"))
        .click();
    await nextTick();

    root.querySelector('[data-matching-category] input').value = "Kategorie";
    root.querySelector('[data-matching-category] input').dispatchEvent(new Event("input", { bubbles: true }));
    root.querySelector('[data-matching-row] input[type="text"]').value = "Aussage";
    root.querySelector('[data-matching-row] input[type="text"]').dispatchEvent(new Event("input", { bubbles: true }));
    root.querySelector('[data-matching-row] input[type="checkbox"]').click();
    root.querySelector("#assessment-task-points-per-correct-answer").value = "2";
    root.querySelector("#assessment-task-points-per-correct-answer").dispatchEvent(new Event("input", { bubbles: true }));
    root.querySelector("#assessment-task-matching-scoring-mode").value = "complete_row";
    root.querySelector("#assessment-task-matching-scoring-mode").dispatchEvent(new Event("change", { bubbles: true }));
    root.querySelector("form").dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
    await nextTick();

    expect(transformedPayload.content.points_per_correct_answer).toBe(2);
    expect(transformedPayload.content.matching_scoring_mode).toBe("complete_row");
    expect(transformedPayload.content.categories[0].text).toBe("Kategorie");
    expect(transformedPayload.content.rows[0].category_ids).toEqual([transformedPayload.content.categories[0].id]);
    expect(transformedPayload.content.columns).toBeUndefined();
    unmount();
});

it("submits image answer table rows with the selected image and width", async () => {
    transformedPayload = undefined;
    const { root, unmount } = mount({
        imageLibrary: [{ id: 7, name: "Baum.png", preview_url: "/baum.png" }],
    });
    root.querySelectorAll('[role="tab"]')[1].click();
    await nextTick();
    Array.from(root.querySelectorAll("button"))
        .find((button) => button.textContent.includes("Tabelle mit Bildern und Lösungsfeldern"))
        .click();
    await nextTick();

    expect(root.querySelector("#assessment-task-image-width")).not.toBeNull();
    expect(root.textContent).not.toContain("Spaltenüberschrift");
    Array.from(root.querySelectorAll("button"))
        .find((button) => button.textContent.includes("Bibliothek"))
        .click();
    await nextTick();
    root.querySelector(".list-group button").click();
    await nextTick();
    root.querySelector("form").dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
    await nextTick();

    expect(transformedPayload.content.image_width_cm).toBe(3);
    expect(transformedPayload.content.subtasks[0].image_identifier).toBe(transformedPayload.images[0].identifier);
    expect(transformedPayload.images[0].resource_id).toBe(7);
    unmount();
});

it("shows heading table options without generic row labels", async () => {
    const { root, unmount } = mount();
    root.querySelectorAll('[role="tab"]')[1].click();
    await nextTick();
    Array.from(root.querySelectorAll("button"))
        .find((button) => button.textContent.includes("Tabelle mit Überschriften"))
        .click();
    await nextTick();

    expect(root.textContent).toContain("Antworten anzeigen");
    expect(root.textContent).toContain("Lineatur");
    expect(root.textContent).not.toContain("Zeile / Teilaufgabe");
    expect(root.querySelectorAll("input[placeholder='Spaltenüberschrift']")).toHaveLength(0);
    unmount();
});

it("creates and removes image-label reference points", async () => {
    const { root, unmount } = mount({
        imageLibrary: [{ id: 7, name: "Baum.png", preview_url: "/baum.png" }],
    });
    root.querySelectorAll('[role="tab"]')[1].click();
    await nextTick();
    Array.from(root.querySelectorAll("button"))
        .find((button) => button.textContent.includes("Bild beschriften"))
        .click();
    await nextTick();
    expect(root.querySelector("#assessment-task-label-image-width").getAttribute("min")).toBe("4");
    expect(root.querySelector("#assessment-task-label-image-width").getAttribute("max")).toBe("8");

    Array.from(root.querySelectorAll("button"))
        .find((button) => button.textContent.includes("Bibliothek"))
        .click();
    await nextTick();
    root.querySelector(".list-group button").click();
    await nextTick();
    root.querySelector(".image-labeling-stage").click();
    await nextTick();

    expect(root.querySelectorAll(".image-labeling-point")).toHaveLength(1);
    expect(root.querySelectorAll("input[placeholder='Lösung für diesen Punkt']")).toHaveLength(1);
    root.querySelector(".input-group .btn-outline-danger").click();
    await nextTick();
    expect(root.querySelectorAll(".image-labeling-point")).toHaveLength(0);
    unmount();
});

it("offers the three image-label layouts and submits the selected layout", async () => {
    transformedPayload = undefined;
    const { root, unmount } = mount();
    root.querySelectorAll('[role="tab"]')[1].click();
    await nextTick();
    Array.from(root.querySelectorAll("button"))
        .find((button) => button.textContent.includes("Bild beschriften"))
        .click();
    await nextTick();

    const layout = root.querySelector("#assessment-task-label-layout");
    expect(Array.from(layout.options).map((option) => option.value)).toEqual([
        "center",
        "left",
        "right",
    ]);
    expect(layout.value).toBe("center");
    layout.value = "right";
    layout.dispatchEvent(new Event("change", { bubbles: true }));
    root.querySelector("form").dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
    await nextTick();

    expect(transformedPayload.content.image_label_layout).toBe("right");
    unmount();
});

it("does not submit the empty options placeholder for image matching", async () => {
    transformedPayload = undefined;
    const { root, unmount } = mount();
    root.querySelectorAll('[role="tab"]')[1].click();
    await nextTick();
    Array.from(root.querySelectorAll("button"))
        .find((button) => button.textContent.includes("Zuordnung zu Bildern"))
        .click();
    await nextTick();

    root.querySelector("form").dispatchEvent(
        new Event("submit", { bubbles: true, cancelable: true }),
    );
    await nextTick();

    expect(transformedPayload.content.options).toBeUndefined();
    unmount();
});

it("keeps the editor open and shows a validation error when saving fails", async () => {
    submitError = true;
    const { root, unmount } = mount({ method: "put" });
    root.querySelector("form").dispatchEvent(
        new Event("submit", { bubbles: true, cancelable: true }),
    );
    await nextTick();

    expect(root.querySelector('[role="alert"]').textContent).toContain(
        "Titel fehlt.",
    );
    submitError = false;
    unmount();
});
