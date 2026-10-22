import * as THREE from "three";
import { OrbitControls } from "three/addons/controls/OrbitControls.js";
import {
    LIGHT_DIRECTION,
    createAsteroidBelt,
    createNebula,
    createSpaceObject,
    createStarfield,
    createSun,
    disposeSpaceObject,
} from "./objects.js";

const isSmallScreen = () => window.matchMedia("(max-width: 768px)").matches;

/**
 * Scène « un article = un astre ». Rien n'est calculé tant que start() n'est pas appelé,
 * et la boucle de rendu s'arrête complètement avec stop().
 */
export function createExperience({ container, articles, onPreview }) {
    const mobile = isSmallScreen();
    const quality = { textureSize: mobile ? 512 : 1024 };
    const pixelRatio = Math.min(window.devicePixelRatio, mobile ? 1.5 : 1.75);

    const renderer = new THREE.WebGLRenderer({ antialias: true, powerPreference: "high-performance" });
    renderer.setPixelRatio(pixelRatio);
    renderer.setSize(window.innerWidth, window.innerHeight);
    container.appendChild(renderer.domElement);

    const scene = new THREE.Scene();
    scene.background = new THREE.Color(0x02030f);
    const camera = new THREE.PerspectiveCamera(55, window.innerWidth / window.innerHeight, 0.1, 1200);

    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.06;
    controls.minDistance = 3;
    controls.maxDistance = 90;
    controls.rotateSpeed = 0.6;

    const sunLight = new THREE.DirectionalLight(0xfff1dd, 3.6);
    sunLight.position.copy(LIGHT_DIRECTION).multiplyScalar(50);
    scene.add(sunLight, new THREE.HemisphereLight(0x8f9ce0, 0x2a1f35, 0.85));

    const nebula = createNebula(renderer, mobile ? 1024 : 2048);
    const starfield = createStarfield(mobile ? 2500 : 5000, pixelRatio);
    const sun = createSun();
    scene.add(nebula, starfield, sun);

    // Placement en spirale (angle d'or) : espacement régulier quel que soit le nombre d'articles.
    const entries = articles.map((article, index) => {
        const object = createSpaceObject(renderer, {
            type: article.visualModel,
            seed: Number(article.id) || index + 1,
            color: article.visualColor,
            quality,
        });
        const anchor = new THREE.Group();
        const angle = index * 2.39996 + 0.6;
        const distance = 7 + 5.2 * Math.sqrt(index);
        anchor.position.set(Math.cos(angle) * distance, Math.sin(index * 1.9) * 2.6, Math.sin(angle) * distance);
        anchor.add(object);
        scene.add(anchor);
        return { article, anchor, object, radius: object.userData.radius, hover: 0, phase: index * 1.3 };
    });

    const outerRadius = 7 + 5.2 * Math.sqrt(Math.max(articles.length - 1, 0));
    const belt = createAsteroidBelt(mobile ? 90 : 180, outerRadius + 6, outerRadius + 12);
    scene.add(belt);

    const home = new THREE.Vector3(0, outerRadius * 0.45 + 4, outerRadius * 1.25 + 14);
    camera.position.copy(home);
    controls.target.set(0, 0, 0);

    /* ---------- Modèles .glb personnalisés (chargés seulement s'il y en a) ---------- */

    let modelsRequested = false;
    async function loadCustomModels() {
        if (modelsRequested) return;
        modelsRequested = true;
        const custom = entries.filter((entry) => entry.article.modelUrl);
        if (custom.length === 0) return;
        const { loadModel } = await import("./models.js");
        custom.forEach(async (entry) => {
            try {
                const model = await loadModel(entry.article.modelUrl, entry.radius * 2);
                disposeSpaceObject(entry.object);
                entry.anchor.remove(entry.object);
                entry.anchor.add(model);
                entry.object = model;
            } catch (error) {
                console.error(`Impossible de charger le modèle 3D de l’article ${entry.article.id}.`, error);
            }
        });
    }

    /* ---------- Interaction ---------- */

    const raycaster = new THREE.Raycaster();
    const pointer = new THREE.Vector2();
    const sphere = new THREE.Sphere();
    const hitPoint = new THREE.Vector3();
    const worldPosition = new THREE.Vector3();
    let pointerDirty = false;
    let hovered = null;
    let previewed = null;
    let flight = null;

    function pick(clientX, clientY) {
        const bounds = renderer.domElement.getBoundingClientRect();
        pointer.set(((clientX - bounds.left) / bounds.width) * 2 - 1, -((clientY - bounds.top) / bounds.height) * 2 + 1);
        raycaster.setFromCamera(pointer, camera);
        let best = null;
        let bestDistance = Infinity;
        entries.forEach((entry) => {
            entry.anchor.getWorldPosition(sphere.center);
            sphere.radius = entry.radius * 1.15;
            if (raycaster.ray.intersectSphere(sphere, hitPoint)) {
                const distance = hitPoint.distanceTo(camera.position);
                if (distance < bestDistance) {
                    bestDistance = distance;
                    best = entry;
                }
            }
        });
        return best;
    }

    function preview(entry) {
        if (!entry || entry === previewed) return;
        previewed = entry;
        onPreview(entry.article);
    }

    function flyTo(entry) {
        entry.anchor.getWorldPosition(worldPosition);
        const offset = camera.position.clone().sub(worldPosition).normalize().multiplyScalar(entry.radius * 3.2 + 3);
        flight = {
            progress: 0,
            fromPosition: camera.position.clone(),
            fromTarget: controls.target.clone(),
            toPosition: worldPosition.clone().add(offset),
            toTarget: worldPosition.clone(),
        };
    }

    const onPointerMove = (event) => {
        pointer.userData = event;
        pointerDirty = true;
    };
    const onClick = (event) => {
        const entry = pick(event.clientX, event.clientY);
        if (!entry) return;
        preview(entry);
        flyTo(entry);
    };
    const onResize = () => {
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
    };

    renderer.domElement.addEventListener("pointermove", onPointerMove);
    renderer.domElement.addEventListener("click", onClick);
    controls.addEventListener("start", () => { flight = null; });

    /* ---------- Boucle de rendu ---------- */

    const clock = new THREE.Clock(false);
    let running = false;
    let slowFrames = 0;
    let measuredFrames = 0;
    let time = 0;

    function frame() {
        if (!running) return;
        requestAnimationFrame(frame);
        const delta = Math.min(clock.getDelta(), 0.1);
        time += delta;

        // Qualité adaptative : si l'appareil peine, on réduit la résolution de rendu.
        if (measuredFrames < 90) {
            measuredFrames++;
            if (delta > 1 / 40) slowFrames++;
            if (measuredFrames === 90 && slowFrames > 45 && renderer.getPixelRatio() > 1) {
                renderer.setPixelRatio(1);
                starfield.material.uniforms.uPixelRatio.value = 1;
            }
        }

        if (pointerDirty) {
            pointerDirty = false;
            const event = pointer.userData;
            hovered = pick(event.clientX, event.clientY);
            renderer.domElement.style.cursor = hovered ? "pointer" : "grab";
            preview(hovered);
        }

        let closest = null;
        let closestDistance = Infinity;
        entries.forEach((entry) => {
            entry.object.userData.update?.(time, delta);
            entry.object.position.y = Math.sin(time * 0.5 + entry.phase) * 0.18;
            entry.hover = THREE.MathUtils.damp(entry.hover, entry === hovered ? 1 : 0, 8, delta);
            entry.anchor.scale.setScalar(1 + entry.hover * 0.08);
            const distance = camera.position.distanceTo(entry.anchor.position) - entry.radius;
            if (distance < closestDistance) {
                closestDistance = distance;
                closest = entry;
            }
        });
        if (closest && closestDistance < 5) preview(closest);

        if (flight) {
            flight.progress = Math.min(1, flight.progress + delta / 1.4);
            const eased = 1 - Math.pow(1 - flight.progress, 3);
            camera.position.lerpVectors(flight.fromPosition, flight.toPosition, eased);
            controls.target.lerpVectors(flight.fromTarget, flight.toTarget, eased);
            if (flight.progress === 1) flight = null;
        }

        belt.rotation.y += delta * 0.01;
        sun.userData.update(time, delta);
        starfield.material.uniforms.uTime.value = time;
        nebula.position.copy(camera.position);
        starfield.position.copy(camera.position);
        controls.update();
        renderer.render(scene, camera);
    }

    return {
        start() {
            if (running) return;
            running = true;
            clock.start();
            window.addEventListener("resize", onResize);
            onResize();
            frame();
            loadCustomModels();
        },
        stop() {
            running = false;
            clock.stop();
            window.removeEventListener("resize", onResize);
        },
    };
}
