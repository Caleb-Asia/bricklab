
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

    // ==========================================
    // BRICKLAB — DASHBOARD HERO BRICKS
    // ==========================================

    const hero = document.querySelector(".dashboard-welcome");
    const bricks = hero ? hero.querySelectorAll(".art-block") : [];
    const gsap = window.gsap;

    if (hero && bricks.length > 0 && gsap && !reducedMotion.matches) {
        const rotations = [-14, 12, -5];
        const brickAnimations = [];

        bricks.forEach((brick, index) => {
            const rotation = rotations[index] ?? 0;
            const motion = {
                x: 0,
                y: 0,
                pointerX: 0,
                pointerY: 0,
                rotation,
                scale: 1,
                opacity: 1,
            };
            const renderBrick = () => {
                gsap.set(brick, {
                    x: motion.x + motion.pointerX,
                    y: motion.y + motion.pointerY,
                    rotation: motion.rotation,
                    scale: motion.scale,
                    opacity: motion.opacity,
                });
            };
            const timeline = gsap.timeline({ delay: index * 0.14 });

            brickAnimations.push({ motion, renderBrick });

            timeline.fromTo(
                motion,
                {
                    y: -105,
                    rotation: rotation - 13,
                    scale: 0.76,
                    opacity: 0,
                },
                {
                    y: 0,
                    rotation,
                    scale: 1,
                    opacity: 1,
                    duration: 1.15,
                    ease: "elastic.out(1, 0.65)",
                    onUpdate: renderBrick,
                }
            );

            timeline.to(motion, {
                y: -8,
                rotation: rotation + 1.5,
                duration: 3.8 + index * 0.55,
                ease: "sine.inOut",
                repeat: -1,
                yoyo: true,
                onUpdate: renderBrick,
            });
        });

        if (finePointer.matches) {
            const pointerOffsets = [
                { x: 6, y: -6 },
                { x: -7, y: 6 },
                { x: 5, y: -7 },
            ];
            const pointerQuickTos = brickAnimations.map(
                ({ motion, renderBrick }, index) => {
                    const offset = pointerOffsets[index] ?? { x: 0, y: 0 };
                    return {
                        x: gsap.quickTo(motion, "pointerX", {
                            duration: 0.9,
                            ease: "power2.out",
                            onUpdate: renderBrick,
                        }),
                        y: gsap.quickTo(motion, "pointerY", {
                            duration: 0.9,
                            ease: "power2.out",
                            onUpdate: renderBrick,
                        }),
                        offset,
                    };
                }
            );

            hero.addEventListener("pointermove", (event) => {
                const bounds = hero.getBoundingClientRect();
                if (!bounds.width || !bounds.height) {
                    return;
                }

                const x = (event.clientX - bounds.left) / bounds.width - 0.5;
                const y = (event.clientY - bounds.top) / bounds.height - 0.5;

                pointerQuickTos.forEach((quickTo) => {
                    quickTo.x(x * quickTo.offset.x);
                    quickTo.y(y * quickTo.offset.y);
                });
            });

            hero.addEventListener("pointerleave", () => {
                pointerQuickTos.forEach((quickTo) => {
                    quickTo.x(0);
                    quickTo.y(0);
                });
            });
        }
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
