// Point d'entrée léger : three.js et la scène ne sont téléchargés qu'à la première ouverture
// (préchargés dès le survol du bouton), ce qui n'alourdit plus le chargement des pages.
const modal = document.getElementById("visualisation-modal");
const canvasContainer = document.getElementById("visualisation-canvas");
const openButtons = document.querySelectorAll(".visualisation-open");
const closeButton = document.getElementById("close-visualisation");
const preview = document.getElementById("visualisation-preview");
const previewTitle = document.getElementById("visualisation-preview-title");
const previewText = document.getElementById("visualisation-preview-text");
const previewLink = document.getElementById("visualisation-preview-link");
const dataElement = document.getElementById("visualisation-articles");
const articleData = dataElement ? JSON.parse(dataElement.textContent) : [];

if (modal && canvasContainer && openButtons.length > 0 && closeButton && articleData.length > 0) {
    let experiencePromise;
    let experience;
    let isOpen = false;

    const importScene = () => import("./space/experience.js");

    function showPreview(article) {
        previewTitle.textContent = article.title;
        previewText.textContent = article.excerpt;
        previewLink.href = article.url;
        preview.classList.add("is-visible");
    }

    function loadExperience() {
        experiencePromise ??= importScene()
            // Laisse le navigateur afficher l'indicateur de chargement avant de générer les textures.
            .then((module) => new Promise((resolve) => requestAnimationFrame(() => setTimeout(() => resolve(module)))))
            .then(({ createExperience }) =>
                createExperience({ container: canvasContainer, articles: articleData, onPreview: showPreview })
            );
        return experiencePromise;
    }

    async function openExperience() {
        isOpen = true;
        modal.classList.add("is-open");
        modal.setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";
        if (!experience) modal.classList.add("is-loading");
        try {
            experience = await loadExperience();
            modal.classList.remove("is-loading");
            if (isOpen) experience.start();
        } catch (error) {
            experiencePromise = null;
            modal.classList.remove("is-loading");
            console.error("Impossible de lancer la visualisation 3D.", error);
        }
    }

    function closeExperience() {
        isOpen = false;
        modal.classList.remove("is-open");
        modal.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";
        experience?.stop();
    }

    openButtons.forEach((button) => {
        button.addEventListener("pointerenter", importScene, { once: true });
        button.addEventListener("focus", importScene, { once: true });
        button.addEventListener("click", openExperience);
    });
    closeButton.addEventListener("click", closeExperience);
    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && modal.classList.contains("is-open")) closeExperience();
    });
}
