document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("[data-showcase-filters]").forEach((form) => {
        form.addEventListener("change", (event) => {
            if (event.target instanceof HTMLSelectElement) {
                form.requestSubmit();
            }
        });
    });
});
