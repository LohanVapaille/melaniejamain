// Formulaire d'article : choix de l'astre, de sa couleur et aperçu 3D en direct.
const editor = document.querySelector(".space-editor");
const colorInput = document.getElementById("visual_color");
const colorPicker = document.getElementById("visual_color_picker");
const swatches = document.querySelectorAll(".space-color[data-color]");
const fileInput = document.getElementById("model_3d");
const fileFeedback = document.getElementById("model_3d_feedback");

function selectedType() {
    return editor.querySelector('input[name="visual_model"]:checked')?.value || "planet";
}

function highlightColor() {
    const value = colorInput.value.toLowerCase();
    let matched = false;
    swatches.forEach((swatch) => {
        const active = swatch.dataset.color.toLowerCase() === value;
        matched ||= active;
        swatch.classList.toggle("is-active", active);
    });
    colorPicker.closest(".space-color").classList.toggle("is-active", !matched && value !== "");
}

if (fileInput) {
    fileInput.addEventListener("change", () => {
        const file = fileInput.files[0];
        const maxSize = Number(fileInput.dataset.maxSize);
        fileFeedback.classList.remove("is-error");
        fileFeedback.textContent = "";
        if (!file) return;
        const megabytes = (file.size / 1048576).toLocaleString("fr-FR", { maximumFractionDigits: 1 });
        if (!file.name.toLowerCase().endsWith(".glb")) {
            fileFeedback.textContent = "Seuls les fichiers .glb sont acceptés.";
            fileFeedback.classList.add("is-error");
            fileInput.value = "";
        } else if (file.size > maxSize) {
            fileFeedback.textContent = `Fichier trop lourd (${megabytes} Mo, 15 Mo maximum). Compressez-le sur gltf.report puis réessayez.`;
            fileFeedback.classList.add("is-error");
            fileInput.value = "";
        } else {
            fileFeedback.textContent = file.size > 3 * 1048576
                ? `${megabytes} Mo : accepté, mais il retardera l’affichage de cet article.`
                : `${megabytes} Mo : parfait.`;
        }
    });
}

if (editor && colorInput) {
    const container = document.getElementById("space-preview-canvas");
    const seed = Number(editor.dataset.articleId) || 1;
    let preview;

    async function createPreview() {
        const THREE = await import("three");
        const { createSpaceObject, disposeSpaceObject, createStarfield } = await import("./space/objects.js");
        const width = container.clientWidth || 220;
        const height = container.clientHeight || 220;
        const renderer = new THREE.WebGLRenderer({ antialias: true });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        renderer.setSize(width, height);
        container.appendChild(renderer.domElement);

        const scene = new THREE.Scene();
        scene.background = new THREE.Color(0x02030f);
        const camera = new THREE.PerspectiveCamera(40, width / height, 0.1, 1000);
        const light = new THREE.DirectionalLight(0xfff1dd, 3.6);
        light.position.set(0.75, 0.35, 0.55).multiplyScalar(10);
        scene.add(light, new THREE.HemisphereLight(0x8f9ce0, 0x2a1f35, 0.85), createStarfield(600, renderer.getPixelRatio()));

        let object;
        const clock = new THREE.Clock();
        let visible = true;
        let time = 0;

        function rebuild() {
            if (object) {
                scene.remove(object);
                disposeSpaceObject(object);
            }
            object = createSpaceObject(renderer, { type: selectedType(), seed, color: colorInput.value || null, quality: { textureSize: 512 } });
            scene.add(object);
            const radius = object.userData.radius;
            const distance = selectedType() === "gas_giant" ? radius * 4.6 : radius * 3.4;
            camera.position.set(0, radius * 0.5, distance);
            camera.lookAt(0, 0, 0);
        }

        function frame() {
            const delta = Math.min(clock.getDelta(), 0.1);
            if (!visible) return;
            requestAnimationFrame(frame);
            time += delta;
            object.userData.update?.(time, delta);
            object.rotation.y += delta * 0.2;
            renderer.render(scene, camera);
        }

        // L'aperçu ne consomme rien lorsqu'il n'est pas visible à l'écran.
        new IntersectionObserver(([entry]) => {
            const wasVisible = visible;
            visible = entry.isIntersecting;
            if (visible && !wasVisible) {
                clock.getDelta();
                frame();
            }
        }).observe(container);

        rebuild();
        frame();
        return { rebuild };
    }

    const refresh = () => {
        highlightColor();
        preview?.then((instance) => instance.rebuild());
    };

    swatches.forEach((swatch) => swatch.addEventListener("click", () => {
        colorInput.value = swatch.dataset.color;
        refresh();
    }));
    colorPicker.addEventListener("input", () => {
        colorInput.value = colorPicker.value;
        highlightColor();
    });
    colorPicker.addEventListener("change", refresh);
    editor.querySelectorAll('input[name="visual_model"]').forEach((radio) => radio.addEventListener("change", refresh));

    highlightColor();
    preview = createPreview();
}
