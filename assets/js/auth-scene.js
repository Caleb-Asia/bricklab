(() => {
    const artwork = document.querySelector("[data-auth-scene]");
    const canvas = artwork?.querySelector(".auth-scene-canvas");

    if (!artwork || !canvas) {
        return;
    }

    const sceneType = artwork.dataset.authScene;
    const reducedMotion = window.matchMedia(
        "(prefers-reduced-motion: reduce)"
    );
    const finePointer = window.matchMedia(
        "(hover: hover) and (pointer: fine)"
    );
    const listenerController = new AbortController();
    const geometries = new Set();
    const materials = new Set();

    let renderer = null;
    let scene = null;
    let camera = null;
    let stage = null;
    let intersectionObserver = null;
    let resizeObserver = null;
    let removalObserver = null;
    let animationFrame = null;
    let disposed = false;
    let isVisible = false;
    let elapsed = 0;
    let previousFrameTime = null;
    let pointerX = 0;
    let pointerY = 0;
    let targetPointerX = 0;
    let targetPointerY = 0;
    let updateScene = () => {};
    let mediaChangeHandler = null;

    const trackGeometry = (geometry) => {
        geometries.add(geometry);
        return geometry;
    };

    const trackMaterial = (material) => {
        materials.add(material);
        return material;
    };

    function roundedBrickGeometry(THREE, width, depth, height) {
        const radius = Math.min(0.07, width * 0.12, depth * 0.2);
        const shape = new THREE.Shape();
        shape.moveTo(-width / 2 + radius, -depth / 2);
        shape.lineTo(width / 2 - radius, -depth / 2);
        shape.absarc(width / 2 - radius, -depth / 2 + radius, radius, -Math.PI / 2, 0);
        shape.lineTo(width / 2, depth / 2 - radius);
        shape.absarc(width / 2 - radius, depth / 2 - radius, radius, 0, Math.PI / 2);
        shape.lineTo(-width / 2 + radius, depth / 2);
        shape.absarc(-width / 2 + radius, depth / 2 - radius, radius, Math.PI / 2, Math.PI);
        shape.lineTo(-width / 2, -depth / 2 + radius);
        shape.absarc(-width / 2 + radius, -depth / 2 + radius, radius, Math.PI, Math.PI * 1.5);

        const geometry = new THREE.ExtrudeGeometry(shape, {
            depth: height - 0.06,
            bevelEnabled: true,
            bevelSegments: 2,
            bevelSize: 0.025,
            bevelThickness: 0.03,
            curveSegments: 4,
            steps: 1,
        });
        geometry.translate(0, 0, 0.03);
        geometry.computeVertexNormals();
        return trackGeometry(geometry);
    }

    function createWorkshopScene(THREE) {
        scene = new THREE.Scene();
        stage = new THREE.Group();
        scene.add(stage);

        const ambient = new THREE.HemisphereLight(0xdcd5ff, 0x171421, 2.2);
        const key = new THREE.DirectionalLight(0xffffff, 3.2);
        key.position.set(-3.5, -4, 7);
        key.castShadow = true;
        key.shadow.mapSize.set(512, 512);
        key.shadow.camera.left = -3;
        key.shadow.camera.right = 3;
        key.shadow.camera.top = 3;
        key.shadow.camera.bottom = -3;
        key.shadow.normalBias = 0.035;
        key.shadow.radius = 5;

        const fill = new THREE.DirectionalLight(0x79dff2, 1.1);
        fill.position.set(4, 2, 4);
        scene.add(ambient, key, fill);

        const floorGeometry = trackGeometry(new THREE.PlaneGeometry(12, 12));
        const floorMaterial = trackMaterial(new THREE.ShadowMaterial({
            color: 0x100d1a,
            opacity: 0.28,
        }));
        const floor = new THREE.Mesh(floorGeometry, floorMaterial);
        floor.position.z = -0.05;
        floor.receiveShadow = true;
        scene.add(floor);

        const studGeometry = trackGeometry(
            new THREE.CylinderGeometry(0.105, 0.12, 0.105, 16, 1)
        );
        studGeometry.rotateX(Math.PI / 2);
        const bodyGeometry = roundedBrickGeometry(THREE, 0.72, 0.38, 0.25);
        const materialsByColor = new Map();
        const definitions = [
            { color: 0x22d3ee, position: [-0.76, -0.2, 0], rotation: -0.04 },
            { color: 0x8b5cf6, position: [0.02, -0.2, 0], rotation: 0.025 },
            { color: 0xa78bfa, position: [0.8, -0.2, 0], rotation: -0.03 },
            { color: 0xa78bfa, position: [-0.38, -0.2, 0.27], rotation: 0.015 },
            { color: 0x22d3ee, position: [0.4, -0.2, 0.27], rotation: -0.02 },
            { color: 0x8b5cf6, position: [0.02, -0.2, 0.54], rotation: 0.01 },
            { color: 0xa78bfa, position: [-0.38, 0.22, 0.27], rotation: -0.015 },
            { color: 0x22d3ee, position: [0.4, 0.22, 0.27], rotation: 0.02 },
            { color: 0x8b5cf6, position: [0.02, 0.22, 0.54], rotation: -0.01 },
        ];
        const bricks = definitions.map((definition, index) => {
            const group = new THREE.Group();
            let material = materialsByColor.get(definition.color);
            if (!material) {
                material = trackMaterial(new THREE.MeshPhysicalMaterial({
                    color: definition.color,
                    roughness: 0.3,
                    metalness: 0,
                    clearcoat: 0.65,
                    clearcoatRoughness: 0.25,
                }));
                materialsByColor.set(definition.color, material);
            }
            const body = new THREE.Mesh(bodyGeometry, material);
            body.castShadow = true;
            body.receiveShadow = true;
            group.add(body);

            [-0.2, 0.2].forEach((x) => {
                const stud = new THREE.Mesh(studGeometry, material);
                stud.position.set(x, 0, 0.28);
                stud.castShadow = true;
                group.add(stud);
            });

            const target = new THREE.Vector3(...definition.position);
            const direction = index % 2 === 0 ? 1 : -1;
            const start = target.clone().add(
                new THREE.Vector3(direction * (1.2 + (index % 3) * 0.12), 0.7, 1.3)
            );
            const control = target.clone().add(
                new THREE.Vector3(direction * 0.35, 0.55, 1.6)
            );
            stage.add(group);
            return { group, definition, target, start, control, index };
        });

        updateScene = (time, pointer, staticFrame) => {
            stage.rotation.x += (pointer.y * 0.035 - stage.rotation.x) * 0.06;
            stage.rotation.y += (pointer.x * 0.045 - stage.rotation.y) * 0.06;
            key.intensity = 3.15 + Math.sin(time * 0.55) * 0.12;

            bricks.forEach((brick) => {
                const entry = staticFrame
                    ? 1
                    : smoothstep(0, 1, (time - brick.index * 0.14) / 1.15);
                const inverse = 1 - entry;
                const curvedPosition = new THREE.Vector3()
                    .addScaledVector(brick.start, inverse * inverse)
                    .addScaledVector(brick.control, 2 * inverse * entry)
                    .addScaledVector(brick.target, entry * entry);

                const refinementTime = (time - 3 + brick.index * 0.82) % 12;
                const refining = !staticFrame && refinementTime >= 0 && refinementTime < 2;
                const refinementProgress = refining ? refinementTime / 2 : 0;
                const lift = refining
                    ? Math.sin(refinementProgress * Math.PI) * 0.27
                    : 0;
                const drift = refining
                    ? Math.sin(refinementProgress * Math.PI * 2) * 0.09
                    : 0;
                const idle = staticFrame ? 0 : Math.sin(time * 0.72 + brick.index) * 0.018;

                brick.group.position.set(
                    curvedPosition.x + drift,
                    curvedPosition.y,
                    curvedPosition.z + lift + idle
                );
                brick.group.rotation.set(
                    refining ? Math.sin(refinementProgress * Math.PI) * 0.08 : 0,
                    refining ? Math.sin(refinementProgress * Math.PI) * 0.1 : 0,
                    brick.definition.rotation +
                        (refining ? Math.sin(refinementProgress * Math.PI) * 0.16 : 0)
                );
            });
        };
    }

    function createBlueprintScene(THREE) {
        scene = new THREE.Scene();
        stage = new THREE.Group();
        scene.add(stage);

        const ambient = new THREE.HemisphereLight(0xbdeeff, 0x151626, 1.8);
        const key = new THREE.DirectionalLight(0xd8d1ff, 2.5);
        key.position.set(-3, -4, 6);
        const cyanFill = new THREE.PointLight(0x22d3ee, 7, 7);
        cyanFill.position.set(2.2, 1.6, 2.2);
        const purpleFill = new THREE.PointLight(0xa78bfa, 5, 6);
        purpleFill.position.set(-2, -1.5, 1.6);
        scene.add(ambient, key, cyanFill, purpleFill);

        const gridPositions = [];
        for (let x = -2.4; x <= 2.4; x += 0.3) {
            gridPositions.push(
                new THREE.Vector3(x, -1.1, -0.025),
                new THREE.Vector3(x, 1.1, -0.025)
            );
        }
        for (let y = -1.05; y <= 1.05; y += 0.3) {
            gridPositions.push(
                new THREE.Vector3(-2.4, y, -0.025),
                new THREE.Vector3(2.4, y, -0.025)
            );
        }
        const gridGeometry = trackGeometry(
            new THREE.BufferGeometry().setFromPoints(gridPositions)
        );
        const gridMaterial = trackMaterial(new THREE.LineBasicMaterial({
            color: 0x334464,
            transparent: true,
            opacity: 0.48,
        }));
        stage.add(new THREE.LineSegments(gridGeometry, gridMaterial));

        const definitions = [
            { size: [1.65, 0.82, 0.22], position: [0, 0, 0.04], color: 0x8b5cf6 },
            { size: [0.72, 0.72, 0.55], position: [-0.42, 0, 0.42], color: 0xa78bfa },
            { size: [0.72, 0.72, 0.55], position: [0.42, 0, 0.42], color: 0x22d3ee },
            { size: [1.48, 0.68, 0.18], position: [0, 0, 0.79], color: 0xa78bfa },
            { size: [0.66, 0.66, 0.42], position: [0, 0, 1.08], color: 0x22d3ee },
        ];
        const lineMaterials = [];
        const pieces = definitions.map((definition, index) => {
            const geometry = trackGeometry(
                new THREE.BoxGeometry(...definition.size)
            );
            const edgeGeometry = trackGeometry(new THREE.EdgesGeometry(geometry));
            const wireMaterial = trackMaterial(new THREE.LineBasicMaterial({
                color: index % 2 === 0 ? 0x22d3ee : 0xa78bfa,
                transparent: true,
                opacity: 0.9,
                depthWrite: false,
            }));
            lineMaterials.push(wireMaterial);
            const wire = new THREE.LineSegments(edgeGeometry, wireMaterial);
            wire.position.set(...definition.position);
            stage.add(wire);

            const solidMaterial = trackMaterial(new THREE.MeshStandardMaterial({
                color: definition.color,
                roughness: 0.36,
                metalness: 0.08,
                emissive: definition.color,
                emissiveIntensity: 0.1,
                transparent: true,
                opacity: 0,
                depthWrite: false,
            }));
            const solid = new THREE.Mesh(geometry, solidMaterial);
            solid.position.set(...definition.position);
            solid.castShadow = true;
            stage.add(solid);

            const target = new THREE.Vector3(...definition.position);
            const angle = (index / definitions.length) * Math.PI * 2;
            const start = target.clone().add(
                new THREE.Vector3(Math.cos(angle) * 2.1, Math.sin(angle) * 1.5, 1.3)
            );
            return { definition, target, start, solid, solidMaterial, wire, wireMaterial, index };
        });

        updateScene = (time, pointer, staticFrame) => {
            const cycle = staticFrame ? 0.78 : (time % 16) / 16;
            const assemble = staticFrame
                ? 0.78
                : smoothstep(0.04, 0.43, cycle) * (1 - smoothstep(0.79, 0.98, cycle));
            stage.rotation.x += (pointer.y * 0.025 - stage.rotation.x) * 0.055;
            stage.rotation.y += (pointer.x * 0.045 - stage.rotation.y) * 0.055;
            cyanFill.intensity = 6.7 + Math.sin(time * 0.45) * 0.45;
            purpleFill.intensity = 4.8 + Math.cos(time * 0.38) * 0.35;

            pieces.forEach((piece) => {
                const progress = smoothstep(
                    0,
                    1,
                    (assemble - piece.index * 0.035) / 0.88
                );
                piece.solid.position.lerpVectors(piece.start, piece.target, progress);
                piece.solidMaterial.opacity = progress * 0.96;
                piece.solid.scale.setScalar(0.92 + progress * 0.08);
                piece.wireMaterial.opacity = 0.22 + (1 - progress) * 0.72;
            });
            lineMaterials.forEach((material) => {
                material.opacity = 0.24 + (1 - assemble) * 0.68;
            });
        };
    }

    function smoothstep(min, max, value) {
        const normalized = Math.max(0, Math.min(1, (value - min) / (max - min)));
        return normalized * normalized * (3 - 2 * normalized);
    }

    function drawFrame(time, staticFrame) {
        if (!renderer || !scene || !camera) {
            return;
        }

        updateScene(time, { x: pointerX, y: pointerY }, staticFrame);
        renderer.render(scene, camera);
        artwork.dataset.sceneReady = "true";
    }

    function stopLoop() {
        if (animationFrame !== null) {
            window.cancelAnimationFrame(animationFrame);
            animationFrame = null;
        }
        previousFrameTime = null;
    }

    function frame(now) {
        animationFrame = null;
        if (
            disposed ||
            !isVisible ||
            document.hidden ||
            reducedMotion.matches
        ) {
            return;
        }

        const delta = previousFrameTime === null
            ? 0
            : Math.min((now - previousFrameTime) / 1000, 0.05);
        previousFrameTime = now;
        elapsed += delta;
        pointerX += (targetPointerX - pointerX) * 0.075;
        pointerY += (targetPointerY - pointerY) * 0.075;
        drawFrame(elapsed, false);
        animationFrame = window.requestAnimationFrame(frame);
    }

    function startLoop() {
        if (disposed || !isVisible || document.hidden) {
            return;
        }

        if (reducedMotion.matches) {
            stopLoop();
            drawFrame(0, true);
            return;
        }

        if (animationFrame === null) {
            previousFrameTime = null;
            animationFrame = window.requestAnimationFrame(frame);
        }
    }

    function resizeRenderer() {
        if (!renderer || !artwork.isConnected) {
            return;
        }

        const width = Math.max(1, artwork.clientWidth);
        const height = Math.max(1, artwork.clientHeight);
        camera.aspect = width / height;
        camera.left = -2.6 * camera.aspect / 1.8;
        camera.right = 2.6 * camera.aspect / 1.8;
        camera.top = 1.3;
        camera.bottom = -1.3;
        camera.updateProjectionMatrix();
        renderer.setPixelRatio(
            Math.min(window.devicePixelRatio || 1, width < 500 ? 1.15 : 1.5)
        );
        renderer.setSize(width, height, false);

        if (isVisible && !document.hidden) {
            drawFrame(reducedMotion.matches ? 0 : elapsed, reducedMotion.matches);
        }
    }

    function dispose() {
        if (disposed) {
            return;
        }
        disposed = true;
        stopLoop();
        listenerController.abort();
        intersectionObserver?.disconnect();
        resizeObserver?.disconnect();
        removalObserver?.disconnect();
        if (mediaChangeHandler) {
            if (typeof reducedMotion.removeEventListener === "function") {
                reducedMotion.removeEventListener("change", mediaChangeHandler);
            } else {
                reducedMotion.removeListener(mediaChangeHandler);
            }
        }
        geometries.forEach((geometry) => geometry.dispose());
        materials.forEach((material) => material.dispose());
        renderer?.dispose();
        artwork.removeAttribute("data-scene-ready");
    }

    async function initialize() {
        if (
            !("WebGLRenderingContext" in window) ||
            !("IntersectionObserver" in window) ||
            !("ResizeObserver" in window) ||
            !["workshop", "blueprint"].includes(sceneType)
        ) {
            return;
        }

        try {
            const THREE = await import(
                "https://cdn.jsdelivr.net/npm/three@0.170.0/build/three.module.js"
            );
            if (disposed || !artwork.isConnected) {
                return;
            }

            renderer = new THREE.WebGLRenderer({
                canvas,
                alpha: true,
                antialias: true,
                powerPreference: "low-power",
            });
            renderer.setClearColor(0x000000, 0);
            renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5));
            renderer.outputColorSpace = THREE.SRGBColorSpace;
            renderer.toneMapping = THREE.ACESFilmicToneMapping;
            renderer.toneMappingExposure = 1.05;
            renderer.shadowMap.enabled = sceneType === "workshop";
            renderer.shadowMap.type = THREE.PCFSoftShadowMap;

            camera = new THREE.OrthographicCamera(-2.6, 2.6, 1.3, -1.3, 0.1, 30);
            camera.up.set(0, 0, 1);
            camera.position.set(4.5, -6.5, 4.8);
            camera.lookAt(0, 0, 0.55);

            if (sceneType === "workshop") {
                createWorkshopScene(THREE);
            } else {
                createBlueprintScene(THREE);
            }

            resizeObserver = new ResizeObserver(resizeRenderer);
            resizeObserver.observe(artwork);
            resizeRenderer();
            removalObserver = new MutationObserver(() => {
                if (!artwork.isConnected) {
                    dispose();
                }
            });
            removalObserver.observe(document.documentElement, {
                childList: true,
                subtree: true,
            });

            intersectionObserver = new IntersectionObserver(([entry]) => {
                isVisible = entry.isIntersecting;
                if (isVisible) {
                    startLoop();
                } else {
                    stopLoop();
                }
            });
            intersectionObserver.observe(artwork);
            drawFrame(0, true);

            document.addEventListener(
                "visibilitychange",
                () => {
                    if (document.hidden) {
                        stopLoop();
                    } else {
                        startLoop();
                    }
                },
                { signal: listenerController.signal }
            );

            mediaChangeHandler = () => {
                if (reducedMotion.matches) {
                    stopLoop();
                    drawFrame(0, true);
                } else {
                    startLoop();
                }
            };
            if (typeof reducedMotion.addEventListener === "function") {
                reducedMotion.addEventListener("change", mediaChangeHandler);
            } else {
                reducedMotion.addListener(mediaChangeHandler);
            }

            if (finePointer.matches) {
                artwork.addEventListener(
                    "pointermove",
                    (event) => {
                        if (reducedMotion.matches) {
                            return;
                        }
                        const bounds = artwork.getBoundingClientRect();
                        targetPointerX = Math.max(
                            -1,
                            Math.min(1, ((event.clientX - bounds.left) / bounds.width - 0.5) * 2)
                        );
                        targetPointerY = Math.max(
                            -1,
                            Math.min(1, ((event.clientY - bounds.top) / bounds.height - 0.5) * 2)
                        );
                    },
                    { signal: listenerController.signal }
                );
                artwork.addEventListener(
                    "pointerleave",
                    () => {
                        targetPointerX = 0;
                        targetPointerY = 0;
                    },
                    { signal: listenerController.signal }
                );
            }

            window.addEventListener("pagehide", dispose, {
                once: true,
                signal: listenerController.signal,
            });

        } catch (error) {
            dispose();
            console.warn("BrickLab authentication scene unavailable; using static artwork.", error);
        }
    }

    initialize();
})();
