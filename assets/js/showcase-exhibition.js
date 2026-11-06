(() => {
    const initializeExhibition = () => {
        const exhibition = document.querySelector("[data-showcase-exhibition]");
        const gsap = window.gsap;

        if (!exhibition || !gsap) {
            return;
        }

        const reducedMotion = window.matchMedia(
            "(prefers-reduced-motion: reduce)"
        );
        const finePointer = window.matchMedia(
            "(hover: hover) and (pointer: fine)"
        );
        const stage = exhibition.querySelector(".showcase-exhibition-stage");
        const depth = exhibition.querySelector("[data-exhibition-depth]");
        const canvas = exhibition.querySelector("[data-exhibition-canvas]");
        const entranceElements = exhibition.querySelectorAll(
            "[data-exhibition-reveal], [data-exhibition-panel]"
        );
        const floatingElements = exhibition.querySelectorAll(
            "[data-exhibition-float]"
        );
        const ambientTweens = [];
        const listenerController = new AbortController();
        let isInView = false;
        let isDisposed = false;
        let threeScene = null;
        let threeSceneRequested = false;
        let intersectionObserver = null;

        gsap.from(entranceElements, {
            autoAlpha: 0,
            y: reducedMotion.matches ? 0 : 14,
            duration: reducedMotion.matches ? 0.2 : 0.62,
            ease: "power3.out",
            stagger: reducedMotion.matches ? 0.025 : 0.065,
            clearProps: "opacity,transform",
        });

        if (!reducedMotion.matches) {
            floatingElements.forEach((element, index) => {
                const tween = gsap.to(element, {
                    y: index % 2 === 0 ? -5 : 4,
                    duration: 4.4 + index * 0.7,
                    ease: "sine.inOut",
                    repeat: -1,
                    yoyo: true,
                    paused: true,
                });
                ambientTweens.push(tween);
            });
        }

        const updateMotionState = () => {
            const shouldAnimate =
                isInView && !document.hidden && !reducedMotion.matches;

            ambientTweens.forEach((tween) => {
                if (shouldAnimate) {
                    tween.play();
                } else {
                    tween.pause();
                }
            });

            if (threeScene) {
                if (shouldAnimate) {
                    threeScene.play();
                } else {
                    threeScene.pause();
                }
            }
        };

        const initializeThreeScene = async () => {
            if (
                threeSceneRequested ||
                reducedMotion.matches ||
                !canvas ||
                !("WebGLRenderingContext" in window)
            ) {
                return;
            }

            threeSceneRequested = true;

            try {
                const THREE = await import(
                    "https://cdn.jsdelivr.net/npm/three@0.170.0/build/three.module.js"
                );

                if (isDisposed || reducedMotion.matches) {
                    return;
                }

                const renderer = new THREE.WebGLRenderer({
                    canvas,
                    alpha: true,
                    antialias: false,
                    powerPreference: "low-power",
                });
                const scene = new THREE.Scene();
                const camera = new THREE.PerspectiveCamera(42, 1, 0.1, 50);
                const group = new THREE.Group();
                const geometries = [];
                const materials = [];

                camera.position.z = 8;
                renderer.setPixelRatio(
                    Math.min(window.devicePixelRatio || 1, 1.25)
                );
                renderer.setClearColor(0x000000, 0);

                scene.add(new THREE.AmbientLight(0xdcd5ff, 1.7));
                const keyLight = new THREE.DirectionalLight(0x9c83ff, 2.2);
                keyLight.position.set(-2, 4, 6);
                scene.add(keyLight);

                const makeMaterial = (color, opacity = 1) => {
                    const material = new THREE.MeshStandardMaterial({
                        color,
                        metalness: 0.18,
                        roughness: 0.38,
                        transparent: opacity < 1,
                        opacity,
                    });
                    materials.push(material);
                    return material;
                };
                const addMesh = (geometry, material, position) => {
                    geometries.push(geometry);
                    const mesh = new THREE.Mesh(geometry, material);
                    mesh.position.set(...position);
                    group.add(mesh);
                    return mesh;
                };

                const orbit = addMesh(
                    new THREE.TorusGeometry(1.45, 0.018, 8, 72),
                    makeMaterial(0x9f87f5, 0.58),
                    [-0.45, 0.12, -0.5]
                );
                orbit.rotation.set(0.9, 0.25, -0.4);

                const violetForm = addMesh(
                    new THREE.IcosahedronGeometry(0.55, 0),
                    makeMaterial(0x9874ed, 0.76),
                    [1.55, 0.38, 0.3]
                );
                const cyanForm = addMesh(
                    new THREE.OctahedronGeometry(0.42, 0),
                    makeMaterial(0x36cadc, 0.8),
                    [-2.2, -0.58, 0.1]
                );

                scene.add(group);

                const resize = () => {
                    const bounds = canvas.getBoundingClientRect();
                    if (bounds.width === 0 || bounds.height === 0) {
                        return;
                    }
                    renderer.setSize(bounds.width, bounds.height, false);
                    camera.aspect = bounds.width / bounds.height;
                    camera.updateProjectionMatrix();
                    renderer.render(scene, camera);
                };
                const resizeObserver = "ResizeObserver" in window
                    ? new ResizeObserver(resize)
                    : null;

                if (resizeObserver) {
                    resizeObserver.observe(canvas);
                } else {
                    window.addEventListener("resize", resize, {
                        passive: true,
                        signal: listenerController.signal,
                    });
                }

                const renderMotion = gsap.timeline({
                    paused: true,
                    repeat: -1,
                    onUpdate: () => renderer.render(scene, camera),
                });
                renderMotion
                    .to(orbit.rotation, {
                        z: orbit.rotation.z + Math.PI * 2,
                        duration: 44,
                        ease: "none",
                    }, 0)
                    .to(violetForm.rotation, {
                        x: Math.PI * 2,
                        y: -Math.PI * 2,
                        duration: 22,
                        ease: "none",
                    }, 0)
                    .to(cyanForm.rotation, {
                        y: Math.PI * 2,
                        duration: 44,
                        ease: "none",
                    }, 0);

                resize();
                threeScene = {
                    play: () => renderMotion.play(),
                    pause: () => renderMotion.pause(),
                    dispose: () => {
                        renderMotion.kill();
                        resizeObserver?.disconnect();
                        geometries.forEach((geometry) => geometry.dispose());
                        materials.forEach((material) => material.dispose());
                        renderer.dispose();
                    },
                };
                updateMotionState();
            } catch (error) {
                console.warn(
                    "BrickLab exhibition 3D artwork unavailable; keeping the CSS artwork visible.",
                    error
                );
            }
        };

        const handleMotionPreferenceChange = () => {
            if (!reducedMotion.matches && isInView) {
                initializeThreeScene();
            }
            updateMotionState();
        };

        if ("IntersectionObserver" in window) {
            intersectionObserver = new IntersectionObserver(
                (entries) => {
                    isInView = entries.some((entry) => entry.isIntersecting);
                    updateMotionState();
                    if (isInView) {
                        initializeThreeScene();
                    }
                },
                { rootMargin: "120px" }
            );
            intersectionObserver.observe(exhibition);
        } else {
            isInView = true;
            initializeThreeScene();
        }

        if (finePointer.matches && stage && depth) {
            const moveX = gsap.quickTo(depth, "x", {
                duration: 0.8,
                ease: "power3.out",
            });
            const moveY = gsap.quickTo(depth, "y", {
                duration: 0.8,
                ease: "power3.out",
            });

            stage.addEventListener("pointermove", (event) => {
                if (reducedMotion.matches || window.innerWidth <= 700) {
                    return;
                }
                const bounds = stage.getBoundingClientRect();
                const x = (event.clientX - bounds.left) / bounds.width - 0.5;
                const y = (event.clientY - bounds.top) / bounds.height - 0.5;
                moveX(x * 8);
                moveY(y * 6);
            }, {
                passive: true,
                signal: listenerController.signal,
            });
            stage.addEventListener("pointerleave", () => {
                moveX(0);
                moveY(0);
            }, { signal: listenerController.signal });
        }

        reducedMotion.addEventListener("change", handleMotionPreferenceChange, {
            signal: listenerController.signal,
        });
        document.addEventListener("visibilitychange", updateMotionState, {
            signal: listenerController.signal,
        });
        window.addEventListener("pagehide", () => {
            isDisposed = true;
            intersectionObserver?.disconnect();
            listenerController.abort();
            ambientTweens.forEach((tween) => tween.kill());
            threeScene?.dispose();
        }, { once: true });
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initializeExhibition, {
            once: true,
        });
    } else {
        initializeExhibition();
    }
})();
