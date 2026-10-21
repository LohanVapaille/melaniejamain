// Starfield
const canvas = document.getElementById("stars-canvas");
const ctx = canvas.getContext("2d");
let stars = [];

function resize() {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
}

function initStars() {
    stars = [];
    const count = Math.floor((canvas.width * canvas.height) / 3000);
    for (let i = 0; i < count; i++) {
        stars.push({
            x: Math.random() * canvas.width,
            y: Math.random() * canvas.height,
            r: Math.random() * 1.2 + 0.2,
            alpha: Math.random() * 0.7 + 0.2,
            speed: Math.random() * 0.003 + 0.001,
            phase: Math.random() * Math.PI * 2,
        });
    }
}

function drawStars(t) {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    for (const s of stars) {
        const a = s.alpha * (0.6 + 0.4 * Math.sin(t * s.speed + s.phase));
        ctx.beginPath();
        ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
        ctx.fillStyle = `rgba(255, 245, 220, ${a})`;
        ctx.fill();
    }
    requestAnimationFrame(drawStars);
}

resize();
initStars();
window.addEventListener("resize", () => {
    resize();
    initStars();
});
requestAnimationFrame(drawStars);
