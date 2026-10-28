
document.addEventListener("DOMContentLoaded", () => {
    // ==========================================
    // BRICKLAB — SITE-WIDE CURSOR GLOW
    // ==========================================

    const cursorGlow = document.querySelector(".cursor-glow");

    const finePointer = window.matchMedia(
        "(hover: hover) and (pointer: fine)"
    );

    const reducedMotion = window.matchMedia(
        "(prefers-reduced-motion: reduce)"
    );

    if (cursorGlow && finePointer.matches && !reducedMotion.matches) {
        let targetX = window.innerWidth / 2;
        let targetY = window.innerHeight / 2;

        let currentX = targetX;
        let currentY = targetY;
        let animationFrame = null;

        function updateGlow() {
            currentX += (targetX - currentX) * 0.16;
            currentY += (targetY - currentY) * 0.16;

            cursorGlow.style.left = `${currentX}px`;
            cursorGlow.style.top = `${currentY}px`;

            if (
                Math.abs(targetX - currentX) > 0.5 ||
                Math.abs(targetY - currentY) > 0.5
            ) {
                animationFrame = requestAnimationFrame(updateGlow);
            } else {
                animationFrame = null;
            }
        }

        document.addEventListener("pointermove", (event) => {
            targetX = event.clientX;
            targetY = event.clientY;

            document.body.classList.add("cursor-glow-active");

            if (animationFrame === null) {
                animationFrame = requestAnimationFrame(updateGlow);
            }
        });

        window.addEventListener("pointerout", (event) => {
            if (event.relatedTarget === null) {
                document.body.classList.remove("cursor-glow-active");
            }
        });
    }
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
