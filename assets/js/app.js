
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
    const gsap = window.gsap;

    if (hero) {
        const canvas = hero.querySelector(".welcome-canvas");

        if (
            canvas &&
            "WebGLRenderingContext" in window &&
            "IntersectionObserver" in window &&
            "ResizeObserver" in window
        ) {
            initializeBrickScene(hero, canvas, gsap, finePointer, reducedMotion)
                .catch((error) => {
                    console.warn(
                        "BrickLab 3D hero unavailable; showing the static brick artwork.",
                        error
                    );
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

async function initializeBrickScene(hero, canvas, gsap, finePointer, reducedMotion) {
    const THREE = await import(
        "https://cdn.jsdelivr.net/npm/three@0.170.0/build/three.module.js"
    );
    const artwork = canvas.parentElement;
    const renderer = new THREE.WebGLRenderer({
        canvas,
        alpha: true,
        antialias: true,
        powerPreference: "low-power",
    });
    const scene = new THREE.Scene();
    const stage = new THREE.Group();
    const camera = new THREE.OrthographicCamera(-2, 2, 2, -2, 0.1, 30);
    const listenerController = new AbortController();
    const timelines = [];
    const geometries = new Set();
    const materials = new Set();
    const pointer = { x: 0, y: 0 };
    const pointerTweens = [];
    const bricks = [];
    let animationFrame = null;
    let isVisible = false;
    let isDisposed = false;

    scene.add(stage);
    camera.up.set(0, 0, 1);
    camera.position.set(3.8, -5.8, 7.2);
    camera.lookAt(0, 0, 0.15);
    renderer.setClearColor(0x000000, 0);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5));
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;

    const ambientLight = new THREE.AmbientLight(0xdcd5ff, 1.9);
    const keyLight = new THREE.DirectionalLight(0xffffff, 3.1);
    keyLight.position.set(-3.5, -4, 7);
    keyLight.castShadow = true;
    keyLight.shadow.mapSize.set(512, 512);
    keyLight.shadow.camera.left = -3;
    keyLight.shadow.camera.right = 3;
    keyLight.shadow.camera.top = 3;
    keyLight.shadow.camera.bottom = -3;
    keyLight.shadow.bias = -0.0004;
    keyLight.shadow.normalBias = 0.035;
    keyLight.shadow.radius = 5;

    const fillLight = new THREE.DirectionalLight(0x8ddfff, 1.1);
    fillLight.position.set(4, 2, 4);
    scene.add(ambientLight, keyLight, fillLight);

    const floorGeometry = new THREE.PlaneGeometry(12, 12);
    const floorMaterial = new THREE.ShadowMaterial({
        color: 0x100d1a,
        opacity: 0.26,
    });
    const floor = new THREE.Mesh(floorGeometry, floorMaterial);
    floor.position.z = -0.08;
    floor.receiveShadow = true;
    scene.add(floor);
    geometries.add(floorGeometry);
    materials.add(floorMaterial);

    const brickGeometry = createBrickGeometry(THREE);
    const studGeometry = new THREE.CylinderGeometry(0.145, 0.17, 0.14, 18, 1);
    studGeometry.rotateX(Math.PI / 2);
    geometries.add(brickGeometry, studGeometry);

    const brickDefinitions = [
        {
            color: 0x8b5cf6,
            position: [-0.48, 0.62, 0.32],
            rotation: -0.2,
            float: 0.07,
            duration: 3.8,
        },
        {
            color: 0x0891b2,
            position: [0.52, 0.02, 0.32],
            rotation: 0.17,
            float: 0.055,
            duration: 4.4,
        },
        {
            color: 0xa78bfa,
            position: [-0.22, -0.62, 0.32],
            rotation: -0.08,
            float: 0.065,
            duration: 4.1,
        },
    ];

    brickDefinitions.forEach((definition, index) => {
        const group = new THREE.Group();
        const motion = {
            entry: gsap && !reducedMotion.matches ? -0.85 : 0,
            float: 0,
            rotation: definition.rotation,
        };
        const material = new THREE.MeshPhysicalMaterial({
            color: definition.color,
            roughness: 0.31,
            metalness: 0,
            clearcoat: 0.62,
            clearcoatRoughness: 0.27,
        });
        const body = new THREE.Mesh(brickGeometry, material);
        body.castShadow = true;
        body.receiveShadow = true;
        group.add(body);
        materials.add(material);

        [-0.42, 0, 0.42].forEach((x) => {
            const stud = new THREE.Mesh(studGeometry, material);
            stud.position.set(x, 0, 0.325);
            stud.castShadow = true;
            stud.receiveShadow = true;
            group.add(stud);
        });

        stage.add(group);
        bricks.push({ group, motion, definition });

        if (gsap && !reducedMotion.matches) {
            const timeline = gsap.timeline({ delay: index * 0.12 });
            timeline.to(motion, {
                entry: 0,
                duration: 0.9,
                ease: "power2.out",
            });
            timeline.to(motion, {
                float: 1,
                rotation: definition.rotation + 0.025,
                duration: definition.duration,
                ease: "sine.inOut",
                repeat: -1,
                yoyo: true,
            });
            timelines.push(timeline);
        }
    });

    const updateSize = () => {
        const width = Math.max(1, artwork.clientWidth);
        const height = Math.max(1, artwork.clientHeight);
        const aspect = width / height;
        const viewWidth = 4.5;

        camera.left = -viewWidth / 2;
        camera.right = viewWidth / 2;
        camera.top = viewWidth / aspect / 2;
        camera.bottom = -camera.top;
        camera.updateProjectionMatrix();
        renderer.setSize(width, height, false);

        if (!isDisposed) {
            renderer.render(scene, camera);
        }
    };

    const renderFrame = () => {
        animationFrame = null;
        if (isDisposed || !isVisible || document.hidden) {
            return;
        }

        stage.rotation.x = pointer.y * 0.045;
        stage.rotation.y = pointer.x * 0.06;

        bricks.forEach(({ group, motion, definition }) => {
            group.position.set(
                definition.position[0],
                definition.position[1],
                definition.position[2] +
                    motion.entry +
                    motion.float * definition.float
            );
            group.rotation.z = motion.rotation;
        });

        renderer.render(scene, camera);
        if (timelines.length > 0 && !reducedMotion.matches) {
            animationFrame = window.requestAnimationFrame(renderFrame);
        }
    };

    const startRendering = () => {
        if (
            isDisposed ||
            animationFrame !== null ||
            !isVisible ||
            document.hidden
        ) {
            return;
        }

        if (timelines.length === 0 || reducedMotion.matches) {
            timelines.forEach((timeline) => timeline.pause());
            pointerTweens.forEach((tween) => tween.pause());
            renderFrame();
            return;
        }
        timelines.forEach((timeline) => timeline.resume());
        animationFrame = window.requestAnimationFrame(renderFrame);
    };

    const stopRendering = () => {
        if (animationFrame !== null) {
            window.cancelAnimationFrame(animationFrame);
            animationFrame = null;
        }
        timelines.forEach((timeline) => timeline.pause());
    };

    const intersectionObserver = new IntersectionObserver(([entry]) => {
        isVisible = entry.isIntersecting;
        if (isVisible) {
            startRendering();
        } else {
            stopRendering();
        }
    });
    intersectionObserver.observe(artwork);

    const resizeObserver = new ResizeObserver(updateSize);
    resizeObserver.observe(artwork);
    updateSize();

    document.addEventListener(
        "visibilitychange",
        () => {
            if (document.hidden) {
                stopRendering();
            } else {
                startRendering();
            }
        },
        { signal: listenerController.signal }
    );

    if (finePointer.matches && gsap) {
        const pointerX = gsap.quickTo(pointer, "x", {
            duration: 0.8,
            ease: "power2.out",
        });
        const pointerY = gsap.quickTo(pointer, "y", {
            duration: 0.8,
            ease: "power2.out",
        });
        pointerTweens.push(pointerX.tween, pointerY.tween);

        hero.addEventListener(
            "pointermove",
            (event) => {
                if (reducedMotion.matches) {
                    return;
                }

                const bounds = hero.getBoundingClientRect();
                if (!bounds.width || !bounds.height) {
                    return;
                }

                pointerX(
                    THREE.MathUtils.clamp(
                        (event.clientX - bounds.left) / bounds.width - 0.5,
                        -0.5,
                        0.5
                    )
                );
                pointerY(
                    THREE.MathUtils.clamp(
                        (event.clientY - bounds.top) / bounds.height - 0.5,
                        -0.5,
                        0.5
                    )
                );
            },
            { signal: listenerController.signal }
        );

        hero.addEventListener(
            "pointerleave",
            () => {
                pointerX(0);
                pointerY(0);
            },
            { signal: listenerController.signal }
        );
    }

    const reducedMotionChange = () => {
        if (reducedMotion.matches) {
            stopRendering();
            pointerTweens.forEach((tween) => tween.pause());
            pointer.x = 0;
            pointer.y = 0;
            if (!isDisposed && isVisible && !document.hidden) {
                renderFrame();
            }
        } else {
            startRendering();
        }
    };
    reducedMotion.addEventListener("change", reducedMotionChange, {
        signal: listenerController.signal,
    });

    window.addEventListener(
        "pagehide",
        () => {
            isDisposed = true;
            stopRendering();
            intersectionObserver.disconnect();
            resizeObserver.disconnect();
            listenerController.abort();
            reducedMotion.removeEventListener("change", reducedMotionChange);
            timelines.forEach((timeline) => timeline.kill());
            geometries.forEach((geometry) => geometry.dispose());
            materials.forEach((material) => material.dispose());
            renderer.dispose();
        },
        { once: true, signal: listenerController.signal }
    );

    artwork.classList.add("is-3d-ready");
    if (reducedMotion.matches) {
        isVisible = true;
        renderFrame();
    }
}

function createBrickGeometry(THREE) {
    const width = 1.42;
    const depth = 0.66;
    const height = 0.44;
    const radius = 0.1;
    const shape = new THREE.Shape();

    shape.moveTo(-width / 2 + radius, -depth / 2);
    shape.lineTo(width / 2 - radius, -depth / 2);
    shape.quadraticCurveTo(
        width / 2,
        -depth / 2,
        width / 2,
        -depth / 2 + radius
    );
    shape.lineTo(width / 2, depth / 2 - radius);
    shape.quadraticCurveTo(
        width / 2,
        depth / 2,
        width / 2 - radius,
        depth / 2
    );
    shape.lineTo(-width / 2 + radius, depth / 2);
    shape.quadraticCurveTo(
        -width / 2,
        depth / 2,
        -width / 2,
        depth / 2 - radius
    );
    shape.lineTo(-width / 2, -depth / 2 + radius);
    shape.quadraticCurveTo(
        -width / 2,
        -depth / 2,
        -width / 2 + radius,
        -depth / 2
    );

    const geometry = new THREE.ExtrudeGeometry(shape, {
        depth: height,
        bevelEnabled: true,
        bevelSegments: 3,
        bevelSize: 0.035,
        bevelThickness: 0.035,
        curveSegments: 5,
    });
    geometry.translate(0, 0, -height / 2);
    geometry.computeVertexNormals();
    return geometry;
}
