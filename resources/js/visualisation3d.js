import * as THREE from "three";
import { OrbitControls } from "three/addons/controls/OrbitControls.js";
import { GLTFLoader } from "three/addons/loaders/GLTFLoader.js";
import { DRACOLoader } from "three/addons/loaders/DRACOLoader.js";

const modal = document.getElementById("visualisation-modal");
const canvasContainer = document.getElementById("visualisation-canvas");
const openButton = document.getElementById("open-visualisation");
const closeButton = document.getElementById("close-visualisation");
const preview = document.getElementById("visualisation-preview");
const previewTitle = document.getElementById("visualisation-preview-title");
const previewText = document.getElementById("visualisation-preview-text");
const previewLink = document.getElementById("visualisation-preview-link");
const articleData = JSON.parse(document.getElementById("visualisation-articles").textContent);

if (modal && canvasContainer && openButton && closeButton && articleData.length > 0) {
    let scene;
    let camera;
    let renderer;
    let controls;
    let animationFrame;
    let articleObjects = [];
    let activeArticle;
    const raycaster = new THREE.Raycaster();
    const pointer = new THREE.Vector2();
    const satellites = [];
    const meteors = [];
    const loadingModels = new Set();
    const loadedModels = new Set();
    const gltfLoader = new GLTFLoader();
    const dracoLoader = new DRACOLoader();
    dracoLoader.setDecoderPath(document.querySelector('meta[name="draco-decoder-path"]')?.content || "/build/draco/");
    gltfLoader.setDRACOLoader(dracoLoader);

    function randomForArticle(article, salt = 0) {
        let seed = Number(article.id) * 9301 + salt * 49297 + 233280;
        seed = (seed * 16807) % 2147483647;
        return (seed - 1) / 2147483646;
    }

    function createTexture(article, type, index) {
        const canvas = document.createElement("canvas");
        canvas.width = 256;
        canvas.height = 256;
        const context = canvas.getContext("2d");
        const hue = Math.round(randomForArticle(article, 1) * 360);
        const saturation = type === "star" ? 80 : 45 + Math.round(randomForArticle(article, 2) * 30);
        const lightness = type === "moon" ? 55 : 35 + Math.round(randomForArticle(article, 3) * 25);
        const baseColor = `hsl(${hue}, ${saturation}%, ${lightness}%)`;
        context.fillStyle = baseColor;
        context.fillRect(0, 0, canvas.width, canvas.height);

        const detailCount = type === "star" ? 70 : type === "meteor" ? 90 : 420;
        for (let i = 0; i < detailCount; i++) {
            const x = Math.random() * canvas.width;
            const y = Math.random() * canvas.height;
            const size = Math.random() * 18 + 2;
            context.fillStyle = type === "moon"
                ? `rgba(245, 245, 235, ${Math.random() * 0.35})`
                : `rgba(255, 235, 180, ${Math.random() * 0.3})`;
            context.beginPath();
            context.ellipse(x, y, size, size * (Math.random() * 0.45 + 0.25), Math.random(), 0, Math.PI * 2);
            context.fill();
        }

        const texture = new THREE.CanvasTexture(canvas);
        texture.colorSpace = THREE.SRGBColorSpace;
        return texture;
    }

    function createSatellite(article, index) {
        const satellite = new THREE.Group();
        const body = new THREE.Mesh(
            new THREE.BoxGeometry(0.22, 0.22, 0.35),
            new THREE.MeshStandardMaterial({ color: 0xb7c4d8, metalness: 0.7, roughness: 0.3 })
        );
        const panelMaterial = new THREE.MeshBasicMaterial({ color: 0x5b8fc4 });
        const panel = new THREE.Mesh(new THREE.PlaneGeometry(0.65, 0.22), panelMaterial);
        panel.position.x = 0.43;
        const secondPanel = panel.clone();
        secondPanel.position.x = -0.43;
        satellite.add(body, panel, secondPanel);
        satellite.userData.angle = index * 1.7;
        satellite.userData.radius = 2.5;
        satellite.userData.speed = 0.004 + index * 0.0005;
        satellite.userData.article = article;
        satellite.userData.basePosition = new THREE.Vector3();
        return satellite;
    }

    function createArticleObject(article, index) {
        const type = article.visualModel || "planet";
        const angle = index * Math.PI * 2 / articleData.length;
        const radius = 7 + (index % 3) * 3;
        const position = new THREE.Vector3(
            Math.cos(angle) * radius,
            (index % 2 - 0.5) * 4,
            Math.sin(angle) * radius
        );
        let object;

        if (type === "satellite") {
            object = createSatellite(article, index);
            object.position.copy(position);
            object.userData.basePosition.copy(position);
            satellites.push(object);
        } else if (type === "meteor") {
            object = new THREE.Mesh(
                new THREE.DodecahedronGeometry(1.15, 1),
                new THREE.MeshStandardMaterial({
                    map: createTexture(article, type, index),
                    roughness: 1,
                })
            );
            object.position.copy(position);
            object.scale.set(1.25, 0.75, 0.9);
        } else if (type === "star") {
            const material = new THREE.SpriteMaterial({
                map: createTexture(article, type, index),
                color: 0xfff1bd,
                transparent: true,
                blending: THREE.AdditiveBlending,
            });
            object = new THREE.Sprite(material);
            object.scale.set(3.2, 3.2, 1);
            object.position.copy(position);
        } else {
            const isMoon = type === "moon";
            object = new THREE.Mesh(
                new THREE.SphereGeometry(isMoon ? 1.25 : 1.7, 32, 32),
                new THREE.MeshStandardMaterial({
                    map: createTexture(article, type, index),
                    emissive: isMoon ? 0x252525 : 0x241308,
                    emissiveIntensity: isMoon ? 0.12 : 0.3,
                    roughness: isMoon ? 0.95 : 0.7,
                })
            );
            object.position.copy(position);
            if (!isMoon && index % 2 === 0) {
                const ring = new THREE.Mesh(
                    new THREE.RingGeometry(2.1, 2.35, 48),
                    new THREE.MeshBasicMaterial({
                        color: 0xd8bd7b,
                        side: THREE.DoubleSide,
                        transparent: true,
                        opacity: 0.45,
                    })
                );
                ring.rotation.x = Math.PI / 2.5;
                object.add(ring);
            }
        }

        object.userData.article = article;
        object.userData.objectType = type;
        object.traverse((child) => {
            child.userData.article = article;
        });
        scene.add(object);
        return object;
    }

    function createMeteor(index) {
        const meteor = new THREE.Mesh(
            new THREE.DodecahedronGeometry(0.12 + (index % 3) * 0.06, 0),
            new THREE.MeshStandardMaterial({ color: 0x9a8069, roughness: 1 })
        );
        meteor.position.set(
            (Math.random() - 0.5) * 55,
            (Math.random() - 0.5) * 32,
            (Math.random() - 0.5) * 55
        );
        meteor.userData.velocity = new THREE.Vector3(
            0.012 + Math.random() * 0.018,
            -0.003 - Math.random() * 0.006,
            0.008 + Math.random() * 0.012
        );
        scene.add(meteor);
        meteors.push(meteor);
    }

    function createScene() {
        scene = new THREE.Scene();
        scene.background = new THREE.Color(0x03051c);
        scene.fog = new THREE.FogExp2(0x03051c, 0.004);

        camera = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 0.1, 1000);
        camera.position.set(0, 5, 26);

        renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        renderer.setSize(window.innerWidth, window.innerHeight);
        canvasContainer.appendChild(renderer.domElement);

        controls = new OrbitControls(camera, renderer.domElement);
        controls.enableDamping = true;
        controls.enablePan = true;
        controls.enableZoom = true;
        controls.target.set(0, 0, 0);

        scene.add(new THREE.AmbientLight(0x8b9cff, 1.8));
        const sun = new THREE.PointLight(0xffd98a, 5, 80);
        sun.position.set(0, 8, 8);
        scene.add(sun);

        const starGeometry = new THREE.BufferGeometry();
        const starPositions = new Float32Array(900 * 3);
        for (let i = 0; i < starPositions.length; i += 3) {
            starPositions[i] = (Math.random() - 0.5) * 180;
            starPositions[i + 1] = (Math.random() - 0.5) * 120;
            starPositions[i + 2] = (Math.random() - 0.5) * 180;
        }
        starGeometry.setAttribute("position", new THREE.BufferAttribute(starPositions, 3));
        scene.add(new THREE.Points(starGeometry, new THREE.PointsMaterial({ color: 0xf0eeea, size: 0.15 })));

        articleObjects = articleData.map(createArticleObject);

        for (let index = 0; index < 18; index++) createMeteor(index);

        renderer.domElement.addEventListener("pointermove", handlePointerMove);
        renderer.domElement.addEventListener("click", handleClick);
        window.addEventListener("resize", resize);
        animate();
    }

    function loadCustomModel(index) {
        const article = articleData[index];
        if (!article?.modelUrl || loadingModels.has(index) || loadedModels.has(index)) return;

        loadingModels.add(index);
        gltfLoader.load(
            article.modelUrl,
            (gltf) => {
                const model = gltf.scene;
                const currentObject = articleObjects[index];
                const bounds = new THREE.Box3().setFromObject(model);
                const size = bounds.getSize(new THREE.Vector3());
                const center = bounds.getCenter(new THREE.Vector3());
                const largestDimension = Math.max(size.x, size.y, size.z);
                model.position.copy(currentObject.position);
                model.position.sub(center.multiplyScalar(3.2 / largestDimension));
                model.scale.setScalar(3.2 / largestDimension);
                model.rotation.copy(currentObject.rotation);
                model.userData.article = article;
                model.userData.objectType = "custom";
                model.traverse((child) => {
                    child.userData.article = article;
                });
                scene.remove(currentObject);
                scene.add(model);
                articleObjects[index] = model;
                loadingModels.delete(index);
                loadedModels.add(index);
            },
            undefined,
            (error) => {
                loadingModels.delete(index);
                console.error(`Impossible de charger le modèle 3D de l’article ${article.id}.`, error);
            }
        );
    }

    function showPreview(article) {
        activeArticle = article;
        const articleIndex = articleData.findIndex((item) => item.id === article.id);
        loadCustomModel(articleIndex);
        previewTitle.textContent = article.title;
        previewText.textContent = article.excerpt;
        previewLink.href = article.url;
        preview.classList.add("is-visible");
    }

    function handlePointerMove(event) {
        const bounds = renderer.domElement.getBoundingClientRect();
        pointer.x = ((event.clientX - bounds.left) / bounds.width) * 2 - 1;
        pointer.y = -((event.clientY - bounds.top) / bounds.height) * 2 + 1;
        raycaster.setFromCamera(pointer, camera);
        const hit = raycaster.intersectObjects(articleObjects, true)[0];
        renderer.domElement.style.cursor = hit ? "pointer" : "grab";
        if (hit) showPreview(hit.object.userData.article);
    }

    function handleClick() {
        if (activeArticle && preview.classList.contains("is-visible")) {
            showPreview(activeArticle);
        }
    }

    function animate() {
        animationFrame = requestAnimationFrame(animate);
        articleObjects.forEach((object, index) => {
            object.rotation.y += 0.002 + index * 0.0002;
        });
        satellites.forEach((satellite) => {
            satellite.userData.angle += satellite.userData.speed;
            satellite.position.set(
                satellite.userData.basePosition.x + Math.cos(satellite.userData.angle) * satellite.userData.radius,
                satellite.userData.basePosition.y + Math.sin(satellite.userData.angle * 1.4) * 0.8,
                satellite.userData.basePosition.z + Math.sin(satellite.userData.angle) * satellite.userData.radius
            );
            satellite.rotation.y += 0.01;
        });
        meteors.forEach((meteor) => {
            meteor.position.add(meteor.userData.velocity);
            meteor.rotation.x += 0.01;
            meteor.rotation.z += 0.006;
            if (meteor.position.x > 30 || meteor.position.y < -18 || meteor.position.z > 30) {
                meteor.position.set(-30, 15 + Math.random() * 15, -30 - Math.random() * 15);
            }
        });
        controls.update();
        const closest = articleObjects
            .map((object) => ({ object, distance: camera.position.distanceTo(object.position) }))
            .sort((a, b) => a.distance - b.distance)[0];
        if (closest && closest.distance < 6.5) {
            showPreview(closest.object.userData.article);
        }
        renderer.render(scene, camera);
    }

    function resize() {
        if (!renderer) return;
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
    }

    function openExperience() {
        modal.classList.add("is-open");
        modal.setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";
        if (!renderer) createScene();
    }

    function closeExperience() {
        modal.classList.remove("is-open");
        modal.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";
    }

    openButton.addEventListener("click", openExperience);
    closeButton.addEventListener("click", closeExperience);
    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && modal.classList.contains("is-open")) closeExperience();
    });
}
