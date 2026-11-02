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
    const pickTargets = [];
    const pointerNdc = { x: 0, y: 0 };

    let THREE;
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
    let hoveredInteraction = null;
    let pendingHoverClearAt = 0;
    let updateScene = () => {};
    let mediaChangeHandler = null;
    let raycasterInstance = null;
    let blueprintBoxState = null;

    const trackGeometry = (geometry) => {
        geometries.add(geometry);
        return geometry;
    };

    const trackMaterial = (material) => {
        materials.add(material);
        return material;
    };

    function roundedBrickGeometry(width = 0.72, depth = 0.38, height = 0.25) {
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
            curveSegments: 5,
            steps: 1,
        });
        geometry.translate(0, 0, 0.03);
        geometry.computeVertexNormals();
        return trackGeometry(geometry);
    }

    function smoothstep(min, max, value) {
        const normalized = Math.max(0, Math.min(1, (value - min) / (max - min)));
        return normalized * normalized * (3 - 2 * normalized);
    }

    function makeBrick(definition, index, options = {}) {
        const root = new THREE.Group();
        const motion = new THREE.Group();
        const hover = new THREE.Group();
        const color = new THREE.Color(definition.color);
        const bodyMaterial = trackMaterial(new THREE.MeshPhysicalMaterial({
            color,
            roughness: 0.3,
            metalness: 0,
            clearcoat: 0.65,
            clearcoatRoughness: 0.25,
            emissive: color,
            emissiveIntensity: 0,
        }));
        const wireMaterial = options.blueprint
            ? trackMaterial(new THREE.LineBasicMaterial({
                color: definition.index % 2 === 0 ? 0x22d3ee : 0xa78bfa,
                transparent: true,
                opacity: 0.92,
                depthWrite: false,
            }))
            : null;
        const solidMaterial = options.blueprint
            ? trackMaterial(new THREE.MeshPhysicalMaterial({
                color,
                roughness: 0.32,
                metalness: 0,
                clearcoat: 0.55,
                clearcoatRoughness: 0.28,
                transparent: true,
                opacity: 0,
                depthWrite: false,
                emissive: color,
                emissiveIntensity: 0.06,
            }))
            : null;

        root.add(motion);
        motion.add(hover);

        const geometry = options.geometry;
        const studGeometry = options.studGeometry;
        const body = new THREE.Mesh(geometry, bodyMaterial);
        body.castShadow = true;
        body.receiveShadow = true;
        hover.add(body);
        pickTargets.push(body);

        const studs = [];
        [-0.2, 0.2].forEach((x) => {
            const stud = new THREE.Mesh(studGeometry, bodyMaterial);
            stud.position.set(x, 0, options.studHeight ?? 0.28);
            stud.castShadow = true;
            hover.add(stud);
            studs.push(stud);
            pickTargets.push(stud);
        });

        if (options.blueprint) {
            const bodyEdges = new THREE.LineSegments(
                options.bodyEdgeGeometry,
                wireMaterial
            );
            hover.add(bodyEdges);
            const studEdges = studs.map((stud) => {
                const edges = new THREE.LineSegments(
                    options.studEdgeGeometry,
                    wireMaterial
                );
                edges.position.copy(stud.position);
                edges.rotation.copy(stud.rotation);
                hover.add(edges);
                return edges;
            });
            const solidBody = new THREE.Mesh(geometry, solidMaterial);
            solidBody.castShadow = true;
            hover.add(solidBody);
            const solidStuds = studs.map((stud) => {
                const solid = new THREE.Mesh(studGeometry, solidMaterial);
                solid.position.copy(stud.position);
                solid.rotation.copy(stud.rotation);
                solid.castShadow = true;
                hover.add(solid);
                return solid;
            });
            pickTargets.push(bodyEdges, ...studEdges, solidBody, ...solidStuds);
            body.visible = false;
            studs.forEach((stud) => {
                stud.visible = false;
            });
            root.userData.solidMaterial = solidMaterial;
            root.userData.wireMaterial = wireMaterial;
            root.userData.blueprintParts = {
                solidBody,
                solidStuds,
                bodyEdges,
                studEdges,
            };
        }

        root.userData.brick = {
            hover,
            material: bodyMaterial,
            hoverAmount: 0,
            hoverTarget: 0,
            baseRotationX: 0,
            baseRotationY: 0,
            refinementPhase: definition.phase ?? index * 0.83,
            definition,
            index,
            blueprint: Boolean(options.blueprint),
        };
        root.traverse((object) => {
            if (object.isMesh || object.isLineSegments) {
                object.userData.hoverRoot = root;
            }
        });

        stage.add(root);
        return root;
    }

    function createWorkshopScene() {
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

        const fill = new THREE.DirectionalLight(0x79dff2, 1.1);
        fill.position.set(4, 2, 4);
        scene.add(ambient, key, fill);

        const floorGeometry = trackGeometry(new THREE.PlaneGeometry(12, 12));
        const floorMaterial = trackMaterial(new THREE.ShadowMaterial({
            color: 0x100d1a,
            opacity: 0.28,
        }));
        const floor = new THREE.Mesh(floorGeometry, floorMaterial);
        floor.position.z = -0.006;
        floor.receiveShadow = true;
        scene.add(floor);

        const brickGeometry = roundedBrickGeometry();
        const studGeometry = trackGeometry(
            new THREE.CylinderGeometry(0.105, 0.12, 0.105, 18, 1)
        );
        studGeometry.rotateX(Math.PI / 2);

        const definitions = [
            { color: 0x22d3ee, position: [-0.4, -0.21, 0], rotation: 0 },
            { color: 0xa78bfa, position: [0.4, -0.21, 0], rotation: 0 },
            { color: 0x8b5cf6, position: [-0.4, 0.21, 0], rotation: 0 },
            { color: 0x22d3ee, position: [0.4, 0.21, 0], rotation: 0 },
            { color: 0xa78bfa, position: [-0.2, 0, 0.3325], rotation: Math.PI / 2 },
            { color: 0x8b5cf6, position: [0.2, 0, 0.3325], rotation: Math.PI / 2 },
        ];
        const bricks = definitions.map((definition, index) => {
            const brick = makeBrick(definition, index, {
                geometry: brickGeometry,
                studGeometry,
            });
            const data = brick.userData.brick;
            data.motion = brick.children[0];
            data.root = brick;
            data.targetPosition = new THREE.Vector3(...definition.position);
            data.startPosition = data.targetPosition.clone().add(
                new THREE.Vector3(index % 2 === 0 ? -0.8 : 0.8, 0.25, 0.8)
            );
            data.entryProgress = 0;
            brick.rotation.z = definition.rotation;
            return data;
        });

        updateScene = (time, pointer, staticFrame, delta) => {
            stage.rotation.x += (pointer.y * 0.035 - stage.rotation.x) * 0.06;
            stage.rotation.y += (pointer.x * 0.045 - stage.rotation.y) * 0.06;
            key.intensity = 3.15 + Math.sin(time * 0.55) * 0.12;

            bricks.forEach((brick) => {
                const entryStart = brick.index < 4
                    ? brick.index * 0.1
                    : 0.95 + (brick.index - 4) * 0.1;
                const entryDuration = brick.index < 4 ? 0.95 : 0.85;
                const entry = staticFrame
                    ? 1
                    : smoothstep(
                        0,
                        1,
                        (time - entryStart) / entryDuration
                    );
                const refinementTime =
                    (time - 2.8 + brick.refinementPhase) % 13;
                const refining =
                    !staticFrame &&
                    brick.index < 4 &&
                    refinementTime >= 0 &&
                    refinementTime < 1.7;
                const refinementLift = refining
                    ? Math.sin((refinementTime / 1.7) * Math.PI) * 0.045
                    : 0;
                const idle = staticFrame
                    ? 0
                    : Math.sin(time * 0.72 + brick.index * 0.8) * 0.012;

                brick.entryProgress = entry;
                brick.root.position.lerpVectors(
                    brick.startPosition,
                    brick.targetPosition,
                    entry
                );
                brick.root.position.z += idle + refinementLift;
                brick.motion.rotation.x = refining
                    ? Math.sin(refinementTime * Math.PI / 1.7) * 0.025
                    : 0;
                brick.motion.rotation.y = 0;
                brick.motion.rotation.z = 0;
                brick.root.rotation.z = brick.definition.rotation;
                updateHover(brick, delta, staticFrame);
            });
        };
    }

    function createProgressScene() {
        scene = new THREE.Scene();
        stage = new THREE.Group();
        stage.position.z = 0.18;
        scene.add(stage);

        const ambient = new THREE.HemisphereLight(0xdcd5ff, 0x171421, 2.1);
        const key = new THREE.DirectionalLight(0xffffff, 3);
        key.position.set(-3.5, -4, 7);
        const fill = new THREE.PointLight(0x79dff2, 3.2, 8);
        fill.position.set(1.8, 1.4, 2.4);
        scene.add(ambient, key, fill);

        const pathGeometry = trackGeometry(
            new THREE.BufferGeometry().setFromPoints([
                new THREE.Vector3(-1.6, 0, 0.015),
                new THREE.Vector3(1.6, 0, 0.015),
            ])
        );
        const pathMaterial = trackMaterial(new THREE.LineBasicMaterial({
            color: 0x59607c,
            transparent: true,
            opacity: 0.66,
        }));
        stage.add(new THREE.Line(pathGeometry, pathMaterial));

        const brickGeometry = roundedBrickGeometry(0.56, 0.34, 0.22);
        const studGeometry = trackGeometry(
            new THREE.CylinderGeometry(0.09, 0.105, 0.1, 16, 1)
        );
        studGeometry.rotateX(Math.PI / 2);

        const definitions = [
            { x: -1.55, color: 0xa78bfa, count: 1 },
            { x: 0, color: 0x22d3ee, count: 2 },
            { x: 1.55, color: 0x8b5cf6, count: 3 },
        ];
        const stations = definitions.map((definition, stationIndex) => {
            const padMaterial = trackMaterial(new THREE.MeshPhysicalMaterial({
                color: 0x24243a,
                roughness: 0.55,
                metalness: 0.08,
                clearcoat: 0.3,
            }));
            const padGeometry = trackGeometry(
                new THREE.CylinderGeometry(0.43, 0.46, 0.06, 40)
            );
            padGeometry.rotateX(Math.PI / 2);
            const pad = new THREE.Mesh(
                padGeometry,
                padMaterial
            );
            pad.position.set(definition.x, 0, 0.015);
            stage.add(pad);

            const ringMaterial = trackMaterial(new THREE.MeshBasicMaterial({
                color: definition.color,
                transparent: true,
                opacity: 0.18,
            }));
            const ring = new THREE.Mesh(
                trackGeometry(new THREE.TorusGeometry(0.36, 0.014, 8, 40)),
                ringMaterial
            );
            ring.position.set(definition.x, 0, 0.05);
            stage.add(ring);

            const bricks = Array.from({ length: definition.count }, (_, layer) => {
                const brick = makeBrick({
                    color: definition.color,
                    index: stationIndex * 3 + layer,
                    phase: stationIndex * 0.8 + layer * 0.35,
                    position: [definition.x, 0, layer * 0.22],
                }, stationIndex * 3 + layer, {
                    geometry: brickGeometry,
                    studGeometry,
                    studHeight: 0.25,
                });
                const data = brick.userData.brick;
                data.motion = brick.children[0];
                data.root = brick;
                data.targetPosition = new THREE.Vector3(
                    definition.x,
                    0,
                    layer * 0.22
                );
                data.startPosition = data.targetPosition.clone().add(
                    new THREE.Vector3(0, 0.55, 0.7 + layer * 0.08)
                );
                data.stationIndex = stationIndex;
                data.layer = layer;
                brick.rotation.z = 0;
                return data;
            });

            return { ring, ringMaterial, bricks };
        });

        updateScene = (time, pointer, staticFrame, delta) => {
            stage.rotation.x += (pointer.y * 0.025 - stage.rotation.x) * 0.055;
            stage.rotation.y += (pointer.x * 0.035 - stage.rotation.y) * 0.055;
            key.intensity = 2.95 + Math.sin(time * 0.5) * 0.1;

            stations.forEach((station, stationIndex) => {
                const stationStart = stationIndex * 1.05;
                const stationProgress = staticFrame
                    ? 1
                    : smoothstep(0, 1, (time - stationStart - 0.35) / 0.9);
                station.ringMaterial.opacity = 0.15 + stationProgress * 0.38;
                station.ring.scale.setScalar(0.9 + stationProgress * 0.1);

                station.bricks.forEach((brick) => {
                    const entry = staticFrame
                        ? 1
                        : smoothstep(
                            0,
                            1,
                            (time - stationStart - brick.layer * 0.14) / 0.9
                        );
                    brick.root.position.lerpVectors(
                        brick.startPosition,
                        brick.targetPosition,
                        entry
                    );
                    brick.root.position.z += staticFrame
                        ? 0
                        : Math.sin(time * 0.72 + brick.index * 0.8) * 0.01;
                    brick.motion.rotation.set(0, 0, 0);
                    brick.root.rotation.z = 0;
                    updateHover(brick, delta, staticFrame);
                });
            });
        };
    }

    function createBlueprintScene() {
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

        const brickGeometry = roundedBrickGeometry(0.6, 0.38, 0.23);
        const studGeometry = trackGeometry(
            new THREE.CylinderGeometry(0.09, 0.105, 0.1, 16, 1)
        );
        studGeometry.rotateX(Math.PI / 2);
        const bodyEdgeGeometry = trackGeometry(
            new THREE.EdgesGeometry(brickGeometry, 18)
        );
        const studEdgeGeometry = trackGeometry(
            new THREE.EdgesGeometry(studGeometry, 12)
        );
        const boxRoot = new THREE.Group();
        stage.add(boxRoot);

        const boxSolidMaterial = trackMaterial(new THREE.MeshPhysicalMaterial({
            color: 0x7460b2,
            roughness: 0.34,
            metalness: 0.04,
            clearcoat: 0.35,
            transparent: true,
            opacity: 0.72,
            depthWrite: false,
            emissive: 0x39296f,
            emissiveIntensity: 0.12,
        }));
        const boxWireMaterial = trackMaterial(new THREE.LineBasicMaterial({
            color: 0x22d3ee,
            transparent: true,
            opacity: 0.82,
            depthWrite: false,
        }));
        const boxWidth = 1.45;
        const boxDepth = 0.98;
        const boxHeight = 0.68;
        const wall = 0.055;

        function addBoxPart(parent, size, position, pickable = false) {
            const geometry = trackGeometry(new THREE.BoxGeometry(...size));
            const body = new THREE.Mesh(geometry, boxSolidMaterial);
            body.position.set(...position);
            body.castShadow = true;
            body.receiveShadow = true;
            parent.add(body);

            const edges = new THREE.LineSegments(
                trackGeometry(new THREE.EdgesGeometry(geometry)),
                boxWireMaterial
            );
            edges.position.copy(body.position);
            parent.add(edges);

            if (pickable) {
                body.userData.hoverRoot = boxRoot;
                pickTargets.push(body);
            }
        }

        addBoxPart(boxRoot, [boxWidth, boxDepth, wall], [0, 0, wall / 2], true);
        addBoxPart(
            boxRoot,
            [boxWidth, wall, boxHeight],
            [0, -boxDepth / 2 + wall / 2, boxHeight / 2 + wall],
            true
        );
        addBoxPart(
            boxRoot,
            [boxWidth, wall, boxHeight],
            [0, boxDepth / 2 - wall / 2, boxHeight / 2 + wall],
            true
        );
        addBoxPart(
            boxRoot,
            [wall, boxDepth - wall * 2, boxHeight],
            [-boxWidth / 2 + wall / 2, 0, boxHeight / 2 + wall],
            true
        );
        addBoxPart(
            boxRoot,
            [wall, boxDepth - wall * 2, boxHeight],
            [boxWidth / 2 - wall / 2, 0, boxHeight / 2 + wall],
            true
        );

        const lidPivot = new THREE.Group();
        lidPivot.position.set(0, boxDepth / 2 - wall / 2, boxHeight + wall);
        boxRoot.add(lidPivot);
        addBoxPart(
            lidPivot,
            [boxWidth + wall * 2, boxDepth + wall, wall],
            [0, -boxDepth / 2 + wall / 2, 0],
            true
        );
        blueprintBoxState = {
            lidPivot,
            hoverTarget: 0,
            hoverAmount: 0,
            openTarget: 0,
            isBox: true,
        };
        boxRoot.userData.interaction = blueprintBoxState;

        const definitions = [
            { color: 0x8b5cf6, inside: [-0.3, -0.19, 0], position: [-0.37, -0.13, 0.9], rotation: 0 },
            { color: 0xa78bfa, inside: [0.3, -0.19, 0], position: [0.3, -0.2, 0.88], rotation: 0 },
            { color: 0x22d3ee, inside: [-0.3, 0.19, 0], position: [-0.24, 0.24, 1.06], rotation: Math.PI / 2 },
            { color: 0xa78bfa, inside: [0.3, 0.19, 0], position: [0.31, 0.18, 1.08], rotation: 0 },
            { color: 0x22d3ee, inside: [-0.2, 0, 0.26], position: [-0.05, -0.28, 1.19], rotation: 0 },
            { color: 0x8b5cf6, inside: [0.2, 0, 0.26], position: [0.05, 0.08, 1.28], rotation: Math.PI / 2 },
        ];
        const bricks = definitions.map((definition, index) => {
            const brick = makeBrick(
                { ...definition, index },
                index,
                {
                    geometry: brickGeometry,
                    studGeometry,
                    bodyEdgeGeometry,
                    studEdgeGeometry,
                    blueprint: true,
                    studHeight: 0.255,
                }
            );
            const data = brick.userData.brick;
            data.motion = brick.children[0];
            data.root = brick;
            data.targetPosition = new THREE.Vector3(...definition.position);
            data.startPosition = new THREE.Vector3(...definition.inside);
            data.revealProgress = 0;
            data.root.visible = false;
            brick.rotation.z = definition.rotation;
            return data;
        });

        updateScene = (time, pointer, staticFrame, delta) => {
            const cycle = staticFrame ? 0.64 : (time % 18) / 18;
            const assembly = staticFrame
                ? 0.72
                : smoothstep(0.04, 0.46, cycle) *
                    (1 - smoothstep(0.76, 0.98, cycle));
            stage.rotation.x += (pointer.y * 0.025 - stage.rotation.x) * 0.055;
            stage.rotation.y += (pointer.x * 0.04 - stage.rotation.y) * 0.055;
            cyanFill.intensity = 6.7 + Math.sin(time * 0.45) * 0.45;
            purpleFill.intensity = 4.8 + Math.cos(time * 0.38) * 0.35;

            const hoverBlend = staticFrame
                ? 1
                : 1 - Math.exp(-Math.max(delta, 0.001) * 8);
            blueprintBoxState.hoverAmount +=
                (blueprintBoxState.hoverTarget - blueprintBoxState.hoverAmount) *
                hoverBlend;
            blueprintBoxState.lidPivot.rotation.x =
                -blueprintBoxState.hoverAmount * 1.08;
            boxRoot.position.z = -0.0525 + blueprintBoxState.hoverAmount * 0.025;
            boxSolidMaterial.opacity = 0.66 + assembly * 0.18;
            boxWireMaterial.opacity = 0.5 + (1 - assembly) * 0.35;

            bricks.forEach((brick) => {
                const progress = smoothstep(
                    0,
                    1,
                    (assembly - brick.index * 0.035) / 0.88
                );
                const revealBlend = staticFrame
                    ? 1
                    : 1 - Math.exp(-Math.max(delta, 0.001) * 5);
                brick.revealProgress +=
                    (blueprintBoxState.openTarget - brick.revealProgress) *
                    revealBlend;
                brick.root.visible = brick.revealProgress > 0.012;
                brick.root.position.lerpVectors(
                    brick.startPosition,
                    brick.targetPosition,
                    smoothstep(0, 1, brick.revealProgress)
                );
                brick.root.position.z += staticFrame || !brick.root.visible
                    ? 0
                    : Math.sin(time * 0.55 + brick.index) * 0.008;
                brick.root.rotation.z = brick.definition.rotation;

                const parts = brick.root.userData.blueprintParts;
                const solidOpacity = progress * 0.96;
                brick.root.userData.solidMaterial.opacity = solidOpacity;
                brick.root.userData.wireMaterial.opacity =
                    0.3 + (1 - progress) * 0.62;
                parts.solidBody.visible = solidOpacity > 0.015;
                parts.solidStuds.forEach((stud) => {
                    stud.visible = solidOpacity > 0.015;
                });
                updateHover(brick, delta, staticFrame);
            });
        };
    }

    function updateHover(brick, delta, staticFrame) {
        const blend = staticFrame ? 1 : 1 - Math.exp(-Math.max(delta, 0.001) * 13);
        brick.hoverAmount += (brick.hoverTarget - brick.hoverAmount) * blend;
        brick.hover.position.z = brick.hoverAmount * 0.085;
        brick.hover.rotation.x = brick.hoverAmount * 0.07;
        brick.hover.rotation.y = -brick.hoverAmount * 0.08;
        brick.material.emissiveIntensity = brick.hoverAmount * 0.3;
        brick.root.userData.solidMaterial &&
            (brick.root.userData.solidMaterial.emissiveIntensity =
                0.06 + brick.hoverAmount * 0.24);
        brick.root.userData.wireMaterial &&
            (brick.root.userData.wireMaterial.opacity =
                Math.max(
                    brick.root.userData.wireMaterial.opacity,
                    0.3 + brick.hoverAmount * 0.3
                ));
    }

    function findBrickHit(event) {
        const bounds = canvas.getBoundingClientRect();
        if (!bounds.width || !bounds.height) {
            return null;
        }
        pointerNdc.x = ((event.clientX - bounds.left) / bounds.width) * 2 - 1;
        pointerNdc.y = -((event.clientY - bounds.top) / bounds.height) * 2 + 1;
        raycasterInstance.setFromCamera(
            pointerNdc,
            camera
        );
        const intersection = raycasterInstance
            .intersectObjects(pickTargets, false)
            .find(({ object }) => {
                for (let parent = object; parent; parent = parent.parent) {
                    if (!parent.visible) {
                        return false;
                    }
                }
                return true;
            });
        const interactionRoot = intersection?.object.userData.hoverRoot;
        return interactionRoot?.userData.interaction ||
            interactionRoot?.userData.brick ||
            null;
    }

    function setHoveredInteraction(nextInteraction) {
        if (hoveredInteraction === nextInteraction) {
            return;
        }
        if (hoveredInteraction) {
            hoveredInteraction.hoverTarget = 0;
        }
        hoveredInteraction = nextInteraction;
        if (hoveredInteraction) {
            hoveredInteraction.hoverTarget = 1;
        }
        if (sceneType === "blueprint" && blueprintBoxState) {
            blueprintBoxState.openTarget = nextInteraction ? 1 : 0;
        }
    }

    function handlePointerMove(event) {
        if (!finePointer.matches || reducedMotion.matches || disposed) {
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

        const hit = findBrickHit(event);
        canvas.style.cursor = hit ? "pointer" : "grab";
        if (hit) {
            pendingHoverClearAt = 0;
            setHoveredInteraction(hit);
        } else if (hoveredInteraction && pendingHoverClearAt === 0) {
            pendingHoverClearAt = performance.now() + 120;
        }
    }

    function handlePointerDown(event) {
        if (event.pointerType !== "touch" || reducedMotion.matches || disposed) {
            return;
        }
        const hit = findBrickHit(event);
        if (!hit) {
            setHoveredInteraction(null);
            return;
        }
        setHoveredInteraction(hoveredInteraction === hit ? null : hit);
    }

    function handlePointerLeave(event) {
        if (event.pointerType === "touch") {
            return;
        }
        targetPointerX = 0;
        targetPointerY = 0;
        pendingHoverClearAt = 0;
        setHoveredInteraction(null);
        canvas.style.cursor = "grab";
    }

    function drawFrame(time, staticFrame, delta = 0.016) {
        if (!renderer || !scene || !camera) {
            return;
        }
        updateScene(time, { x: pointerX, y: pointerY }, staticFrame, delta);
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
        if (pendingHoverClearAt > 0 && performance.now() >= pendingHoverClearAt) {
            setHoveredInteraction(null);
            pendingHoverClearAt = 0;
        }
        drawFrame(elapsed, false, delta);
        animationFrame = window.requestAnimationFrame(frame);
    }

    function startLoop() {
        if (disposed || !isVisible || document.hidden) {
            return;
        }
        if (reducedMotion.matches) {
            stopLoop();
            setHoveredInteraction(null);
            drawFrame(0, true, 1);
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
        const aspect = width / height;
        const viewHeight = 2.6;
        camera.left = -(viewHeight * aspect) / 2;
        camera.right = (viewHeight * aspect) / 2;
        camera.top = viewHeight / 2;
        camera.bottom = -viewHeight / 2;
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
            !["workshop", "blueprint", "progress"].includes(sceneType)
        ) {
            return;
        }

        try {
            THREE = await import(
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
            raycasterInstance = new THREE.Raycaster();
            raycasterInstance.params.Line.threshold = 0.06;

            camera = new THREE.OrthographicCamera(-2.6, 2.6, 1.3, -1.3, 0.1, 30);
            camera.up.set(0, 0, 1);
            camera.position.set(4.5, -6.5, 4.8);
            camera.lookAt(0, 0, 0.55);

            if (sceneType === "workshop") {
                createWorkshopScene();
            } else if (sceneType === "progress") {
                createProgressScene();
            } else {
                createBlueprintScene();
            }

            resizeObserver = new ResizeObserver(resizeRenderer);
            resizeObserver.observe(artwork);
            resizeRenderer();
            removalObserver = new MutationObserver(() => {
                if (!artwork.isConnected) {
                    dispose();
                }
            });
            removalObserver.observe(artwork.parentElement, {
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
                    setHoveredInteraction(null);
                    drawFrame(0, true, 1);
                } else {
                    startLoop();
                }
            };
            if (typeof reducedMotion.addEventListener === "function") {
                reducedMotion.addEventListener("change", mediaChangeHandler);
            } else {
                reducedMotion.addListener(mediaChangeHandler);
            }

            artwork.addEventListener("pointermove", handlePointerMove, {
                signal: listenerController.signal,
            });
            artwork.addEventListener("pointerdown", handlePointerDown, {
                signal: listenerController.signal,
            });
            artwork.addEventListener("pointerleave", handlePointerLeave, {
                signal: listenerController.signal,
            });
            window.addEventListener("pagehide", dispose, {
                once: true,
                signal: listenerController.signal,
            });
        } catch (error) {
            dispose();
            console.warn(
                "BrickLab authentication scene unavailable; using static artwork.",
                error
            );
        }
    }

    initialize();
})();
