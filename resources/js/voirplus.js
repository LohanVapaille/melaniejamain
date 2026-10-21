window.chargerPlusArticles = function () {
    const hiddenArticles = document.querySelectorAll(".article-hidden");
    let count = 0;
    const increment = 3;

    hiddenArticles.forEach((article) => {
        if (count < increment) {
            article.style.display = "flex";
            article.classList.remove("article-hidden");
            count++;
        }
    });

    // S'il ne reste plus d'articles cachés, on cache les boutons
    if (document.querySelectorAll(".article-hidden").length === 0) {
        const btnVoirPlus = document.getElementById("btn-voir-plus");
        if (btnVoirPlus) btnVoirPlus.style.display = "none";
        const btnVoirTout = document.getElementById("btn-voir-tout");
        if (btnVoirTout) btnVoirTout.style.display = "none";
    }
};

window.afficherToutArticles = function () {
    const hiddenArticles = document.querySelectorAll(".article-hidden");
    hiddenArticles.forEach((article) => {
        article.style.display = "flex";
        article.classList.remove("article-hidden");
    });

    // Masquer les deux boutons une fois tout affiché
    const actionsDiv = document.querySelector(".articles-actions");
    if (actionsDiv) actionsDiv.style.display = "none";
};
