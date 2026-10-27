
document.addEventListener("DOMContentLoaded", () => {
    const sidebar = document.getElementById("sidebar");
    const menuToggle = document.getElementById("menu-toggle");
    const backdrop = document.getElementById("sidebar-backdrop");
    const collapseToggle = document.getElementById(
        "sidebar-collapse-toggle"
    );

    // Desktop sidebar collapse
    if (sidebar && collapseToggle) {
        collapseToggle.addEventListener("click", () => {
            const isCollapsed =
                document.body.classList.toggle("sidebar-collapsed");

            collapseToggle.setAttribute(
                "aria-expanded",
                String(!isCollapsed)
            );

            collapseToggle.setAttribute(
                "aria-label",
                isCollapsed ? "Expand sidebar" : "Collapse sidebar"
            );

            collapseToggle.title = isCollapsed
                ? "Expand sidebar"
                : "Collapse sidebar";

            collapseToggle.querySelector("span").textContent =
                isCollapsed ? "\u00BB" : "\u00AB";
        });

        sidebar.querySelectorAll(".sidebar-link").forEach((link) => {
            const label = link.querySelector("span:last-child");

            if (label) {
                link.setAttribute("aria-label", label.textContent.trim());
            }
        });
    }

    // Mobile sidebar
    if (!sidebar || !menuToggle || !backdrop) {
        return;
    }

    function openMenu() {
        sidebar.classList.add("is-open");
        backdrop.hidden = false;

        menuToggle.setAttribute("aria-expanded", "true");
        menuToggle.setAttribute("aria-label", "Close navigation menu");

        document.body.style.overflow = "hidden";
    }

    function closeMenu() {
        sidebar.classList.remove("is-open");
        backdrop.hidden = true;

        menuToggle.setAttribute("aria-expanded", "false");
        menuToggle.setAttribute("aria-label", "Open navigation menu");

        document.body.style.overflow = "";
    }

    menuToggle.addEventListener("click", () => {
        if (sidebar.classList.contains("is-open")) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    backdrop.addEventListener("click", closeMenu);

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            closeMenu();
        }
    });

    sidebar.querySelectorAll("a").forEach((link) => {
        link.addEventListener("click", closeMenu);
    });

    window.addEventListener("resize", () => {
        if (window.innerWidth > 800) {
            closeMenu();
        }
    });
});
