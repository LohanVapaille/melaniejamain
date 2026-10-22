import * as THREE from "three";
import { mergeVertices } from "three/addons/utils/BufferGeometryUtils.js";
import { RoomEnvironment } from "three/addons/environments/RoomEnvironment.js";
import {
    ATMOSPHERE_FRAGMENT,
    ATMOSPHERE_VERTEX,
    BAKE_VERTEX,
    STARFIELD_FRAGMENT,
    STARFIELD_VERTEX,
    bakeFragment,
} from "./shaders.js";

export const LIGHT_DIRECTION = new THREE.Vector3(0.75, 0.35, 0.55).normalize();

// Couleur dominante par défaut de chaque type (utilisée si l'article n'en définit pas).
const DEFAULT_PALETTES = {
    planet: ["#2f6fb5", "#2a8f9c", "#7a4fc2", "#c0564b", "#3a7d5a"],
    gas_giant: ["#d9a066", "#c98f5a", "#7fa6c9", "#b77fc9", "#d4c08a"],
    moon: ["#b9b4ab", "#c4bcb0", "#a9adb5"],
    meteor: ["#7d6a58", "#6e6660", "#85705c"],
    satellite: ["#d4af37", "#c9c9d1", "#d48b37"],
    station: ["#ffd27a", "#8fd3ff", "#ff9f7a"],
    star: ["#ffd27a", "#9cc8ff", "#ffb36b", "#fff1c4"],
};

export function seededRandom(seed) {
    let state = (Math.abs(Math.floor(seed)) * 2654435761 + 1013904223) >>> 0;
    return () => {
        state = (state + 0x6d2b79f5) >>> 0;
        let t = state;
        t = Math.imul(t ^ (t >>> 15), t | 1);
        t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

export function defaultColor(type, seed) {
    const palette = DEFAULT_PALETTES[type] || DEFAULT_PALETTES.planet;
    return palette[Math.abs(Math.floor(seed)) % palette.length];
}

function shiftColor(color, hue, saturation, lightness) {
    const hsl = {};
    color.getHSL(hsl);
    return new THREE.Color().setHSL(
        (hsl.h + hue + 1) % 1,
        THREE.MathUtils.clamp(hsl.s * saturation, 0, 1),
        THREE.MathUtils.clamp(hsl.l * lightness, 0, 1)
    );
}

/* ---------- Cuisson des textures sur le GPU ---------- */

const bakeCamera = new THREE.OrthographicCamera(-1, 1, 1, -1, 0, 1);
const bakeScene = new THREE.Scene();
const bakeQuad = new THREE.Mesh(new THREE.PlaneGeometry(2, 2));
bakeQuad.frustumCulled = false;
bakeScene.add(bakeQuad);
const bakeMaterials = new Map();

function getBakeMaterial(name) {
    if (!bakeMaterials.has(name)) {
        bakeMaterials.set(name, new THREE.ShaderMaterial({
            vertexShader: BAKE_VERTEX,
            fragmentShader: bakeFragment(name),
            depthTest: false,
            depthWrite: false,
            uniforms: {
                uSeed: { value: new THREE.Vector3() },
                uColorA: { value: new THREE.Color() },
                uColorB: { value: new THREE.Color() },
                uColorC: { value: new THREE.Color() },
                uMode: { value: 0 },
                uParam: { value: 0 },
                uVector: { value: new THREE.Vector3(0, 1, 0) },
            },
        }));
    }
    return bakeMaterials.get(name);
}

function bake(renderer, name, uniforms, width, height, type = THREE.UnsignedByteType) {
    const material = getBakeMaterial(name);
    Object.entries(uniforms).forEach(([key, value]) => {
        const uniform = material.uniforms[key];
        if (uniform.value?.copy) uniform.value.copy(value);
        else uniform.value = value;
    });
    const target = new THREE.WebGLRenderTarget(width, height, {
        depthBuffer: false,
        generateMipmaps: true,
        minFilter: THREE.LinearMipmapLinearFilter,
        magFilter: THREE.LinearFilter,
        wrapS: THREE.RepeatWrapping,
        wrapT: THREE.ClampToEdgeWrapping,
        type,
    });
    target.texture.anisotropy = Math.min(4, renderer.capabilities.getMaxAnisotropy());
    const previousTarget = renderer.getRenderTarget();
    bakeQuad.material = material;
    renderer.setRenderTarget(target);
    renderer.render(bakeScene, bakeCamera);
    renderer.setRenderTarget(previousTarget);
    return target;
}

function seedVector(random) {
    return new THREE.Vector3(random() * 100, random() * 100, random() * 100);
}

/* ---------- Ressources partagées (créées une seule fois) ---------- */

const shared = {};

function canvasTexture(width, height, draw, srgb = true) {
    const canvas = document.createElement("canvas");
    canvas.width = width;
    canvas.height = height;
    draw(canvas.getContext("2d"), width, height);
    const texture = new THREE.CanvasTexture(canvas);
    if (srgb) texture.colorSpace = THREE.SRGBColorSpace;
    return texture;
}

export function glowTexture() {
    shared.glow ??= canvasTexture(128, 128, (ctx, w, h) => {
        const gradient = ctx.createRadialGradient(w / 2, h / 2, 0, w / 2, h / 2, w / 2);
        gradient.addColorStop(0, "rgba(255,255,255,1)");
        gradient.addColorStop(0.18, "rgba(255,255,255,0.75)");
        gradient.addColorStop(0.45, "rgba(255,255,255,0.18)");
        gradient.addColorStop(1, "rgba(255,255,255,0)");
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, w, h);
    });
    return shared.glow;
}

function raysTexture() {
    shared.rays ??= canvasTexture(256, 256, (ctx, w, h) => {
        ctx.translate(w / 2, h / 2);
        ctx.globalCompositeOperation = "lighter";
        for (let i = 0; i < 6; i++) {
            ctx.save();
            ctx.rotate((i * Math.PI) / 6);
            const length = i % 2 === 0 ? w / 2 : w / 3;
            const gradient = ctx.createLinearGradient(-length, 0, length, 0);
            gradient.addColorStop(0, "rgba(255,255,255,0)");
            gradient.addColorStop(0.5, "rgba(255,255,255,0.9)");
            gradient.addColorStop(1, "rgba(255,255,255,0)");
            ctx.fillStyle = gradient;
            ctx.fillRect(-length, -1.5, length * 2, 3);
            ctx.restore();
        }
    });
    return shared.rays;
}

function solarPanelTexture() {
    shared.panel ??= canvasTexture(256, 128, (ctx, w, h) => {
        ctx.fillStyle = "#c8ccd4";
        ctx.fillRect(0, 0, w, h);
        const columns = 8;
        const rows = 4;
        const cellW = w / columns;
        const cellH = h / rows;
        for (let x = 0; x < columns; x++) {
            for (let y = 0; y < rows; y++) {
                const gradient = ctx.createLinearGradient(x * cellW, y * cellH, (x + 1) * cellW, (y + 1) * cellH);
                gradient.addColorStop(0, "#22468f");
                gradient.addColorStop(0.55, "#10275a");
                gradient.addColorStop(1, "#1c3c7c");
                ctx.fillStyle = gradient;
                ctx.fillRect(x * cellW + 2, y * cellH + 2, cellW - 4, cellH - 4);
                ctx.strokeStyle = "rgba(140,170,220,0.25)";
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.moveTo(x * cellW + cellW / 2, y * cellH + 2);
                ctx.lineTo(x * cellW + cellW / 2, (y + 1) * cellH - 2);
                ctx.stroke();
            }
        }
    });
    return shared.panel;
}

function foilTexture() {
    shared.foil ??= canvasTexture(128, 128, (ctx, w, h) => {
        const random = seededRandom(42);
        ctx.fillStyle = "#b8b8b8";
        ctx.fillRect(0, 0, w, h);
        for (let i = 0; i < 140; i++) {
            const shade = Math.floor(150 + random() * 105);
            ctx.fillStyle = `rgb(${shade},${shade},${shade})`;
            ctx.beginPath();
            const x = random() * w;
            const y = random() * h;
            ctx.moveTo(x, y);
            for (let j = 0; j < 3; j++) ctx.lineTo(x + (random() - 0.5) * 40, y + (random() - 0.5) * 40);
            ctx.fill();
        }
    });
    return shared.foil;
}

function windowsTexture() {
    shared.windows ??= canvasTexture(512, 64, (ctx, w, h) => {
        const random = seededRandom(7);
        ctx.fillStyle = "#000";
        ctx.fillRect(0, 0, w, h);
        [0.25, 0.75].forEach((row) => {
            for (let x = 4; x < w; x += 12) {
                if (random() < 0.25) continue;
                ctx.fillStyle = random() < 0.2 ? "#9fd6ff" : "#ffd68a";
                ctx.fillRect(x, row * h - 3, 6, 5);
            }
        });
    });
    return shared.windows;
}

function ringTexture(random, color) {
    const tint = new THREE.Color(color);
    return canvasTexture(512, 4, (ctx, w, h) => {
        let density = 0.6;
        let smooth = 0.6;
        for (let x = 0; x < w; x++) {
            const t = x / w;
            density = THREE.MathUtils.clamp(density + (random() - 0.5) * 0.35, 0.1, 1);
            smooth += (density - smooth) * 0.25;
            const bands = smooth * (0.8 + 0.2 * Math.sin(t * 9 + 1.7));
            const gap = t > 0.62 && t < 0.66 ? 0.08 : 1;
            const edge = Math.min(1, t * 12) * Math.min(1, (1 - t) * 6);
            const alpha = THREE.MathUtils.clamp(bands * gap * edge * (0.75 + random() * 0.25), 0, 1) * 0.85;
            const light = 0.75 + bands * 0.35;
            ctx.fillStyle = `rgba(${Math.round(tint.r * 255 * light)},${Math.round(tint.g * 255 * light)},${Math.round(tint.b * 255 * light)},${alpha})`;
            ctx.fillRect(x, 0, 1, h);
        }
    });
}

function environmentMap(renderer) {
    shared.environments ??= new WeakMap();
    if (!shared.environments.has(renderer)) {
        const generator = new THREE.PMREMGenerator(renderer);
        const environment = generator.fromScene(new RoomEnvironment(), 0.04).texture;
        generator.dispose();
        shared.environments.set(renderer, environment);
    }
    return shared.environments.get(renderer);
}

function sphereGeometry() {
    shared.sphere ??= new THREE.SphereGeometry(1, 96, 48);
    return shared.sphere;
}

/* ---------- Petites aides ---------- */

function atmosphere(radius, color, intensity) {
    const group = new THREE.Group();
    [[1.12, 1, THREE.BackSide], [1.012, 0, THREE.FrontSide]].forEach(([scale, outer, side]) => {
        const mesh = new THREE.Mesh(sphereGeometry(), new THREE.ShaderMaterial({
            vertexShader: ATMOSPHERE_VERTEX,
            fragmentShader: ATMOSPHERE_FRAGMENT,
            uniforms: {
                uColor: { value: new THREE.Color(color) },
                uLightDir: { value: LIGHT_DIRECTION },
                uIntensity: { value: outer ? intensity : intensity * 0.8 },
                uOuter: { value: outer },
            },
            side,
            transparent: true,
            depthWrite: false,
            blending: THREE.AdditiveBlending,
        }));
        mesh.scale.setScalar(radius * scale);
        group.add(mesh);
    });
    return group;
}

function blinkingLight(color, size, phase) {
    const sprite = new THREE.Sprite(new THREE.SpriteMaterial({
        map: glowTexture(),
        color,
        transparent: true,
        depthWrite: false,
        blending: THREE.AdditiveBlending,
    }));
    sprite.scale.setScalar(size);
    sprite.userData.blink = (time) => {
        const pulse = Math.max(0, Math.sin(time * 3 + phase));
        sprite.material.opacity = 0.15 + Math.pow(pulse, 8) * 0.85;
    };
    return sprite;
}

// Bruit 3D léger (côté CPU) pour déformer les roches.
function valueNoise(random) {
    const table = new Float32Array(512).map(() => random() * 2 - 1);
    const hash = (x, y, z) => table[((x * 73856093) ^ (y * 19349663) ^ (z * 83492791)) & 511];
    const fade = (t) => t * t * (3 - 2 * t);
    return (x, y, z) => {
        const ix = Math.floor(x), iy = Math.floor(y), iz = Math.floor(z);
        const fx = fade(x - ix), fy = fade(y - iy), fz = fade(z - iz);
        const lerp = THREE.MathUtils.lerp;
        const plane = (dz) => lerp(
            lerp(hash(ix, iy, iz + dz), hash(ix + 1, iy, iz + dz), fx),
            lerp(hash(ix, iy + 1, iz + dz), hash(ix + 1, iy + 1, iz + dz), fx),
            fy
        );
        return lerp(plane(0), plane(1), fz);
    };
}

export function createRockGeometry(seed, detail = 8) {
    const random = seededRandom(seed);
    const noise = valueNoise(random);
    let geometry = new THREE.IcosahedronGeometry(1, detail);
    geometry.deleteAttribute("normal");
    geometry.deleteAttribute("uv");
    geometry = mergeVertices(geometry);
    const position = geometry.attributes.position;
    const colors = new Float32Array(position.count * 3);
    const offset = random() * 50;
    const craters = Array.from({ length: 14 }, () => ({
        direction: new THREE.Vector3(random() - 0.5, random() - 0.5, random() - 0.5).normalize(),
        size: 0.1 + random() * 0.3,
    }));
    const vertex = new THREE.Vector3();
    for (let i = 0; i < position.count; i++) {
        vertex.fromBufferAttribute(position, i).normalize();
        let height = noise(vertex.x * 1.6 + offset, vertex.y * 1.6, vertex.z * 1.6) * 0.32
            + noise(vertex.x * 4 + offset, vertex.y * 4, vertex.z * 4) * 0.12
            + noise(vertex.x * 10, vertex.y * 10 + offset, vertex.z * 10) * 0.05
            + Math.abs(noise(vertex.x * 22 + offset, vertex.y * 22, vertex.z * 22)) * 0.035;
        craters.forEach(({ direction, size }) => {
            const d = vertex.distanceTo(direction) / size;
            if (d < 1) height -= (1 - d * d) * 0.12;
            else if (d < 1.4) height += (1.4 - d) * 0.08;
        });
        position.setXYZ(i, vertex.x * (1 + height), vertex.y * (1 + height), vertex.z * (1 + height));
        const shade = 0.72 + noise(vertex.x * 6, vertex.y * 6, vertex.z * 6 + offset) * 0.3 + height * 0.4;
        colors.set([shade, shade * 0.97, shade * 0.93], i * 3);
    }
    geometry.setAttribute("color", new THREE.BufferAttribute(colors, 3));
    geometry.computeVertexNormals();
    return geometry;
}

/* ---------- Fabriques d'astres ---------- */

function createPlanet(renderer, random, color, quality) {
    const group = new THREE.Group();
    const radius = 1.6;
    const seed = seedVector(random);
    const ocean = color;
    const land = new THREE.Color(0.62, 0.5, 0.34).lerp(shiftColor(color, 0.45, 0.5, 1), 0.2);
    const vegetation = new THREE.Color(0.2, 0.4, 0.17).lerp(shiftColor(color, 0.3, 0.8, 0.8), 0.25);
    const sea = -0.04 + random() * 0.1;
    const size = quality.textureSize;
    const colorMap = bake(renderer, "terran", { uSeed: seed, uColorA: ocean, uColorB: land, uColorC: vegetation, uMode: 0, uParam: sea }, size, size / 2);
    const dataMap = bake(renderer, "terran", { uSeed: seed, uMode: 1, uParam: sea }, size / 2, size / 4);
    const cloudMap = bake(renderer, "clouds", { uSeed: seed, uParam: 0.75 + random() * 0.25 }, size, size / 2);

    const surface = new THREE.Mesh(sphereGeometry(), new THREE.MeshStandardMaterial({
        map: colorMap.texture,
        bumpMap: dataMap.texture,
        bumpScale: 0.9,
        roughnessMap: dataMap.texture,
        roughness: 1,
        metalness: 0,
    }));
    surface.scale.setScalar(radius);

    const clouds = new THREE.Mesh(sphereGeometry(), new THREE.MeshStandardMaterial({
        color: 0xffffff,
        alphaMap: cloudMap.texture,
        transparent: true,
        depthWrite: false,
        roughness: 1,
    }));
    clouds.scale.setScalar(radius * 1.015);

    const sky = shiftColor(color, 0, 0.9, 1.6);
    group.add(surface, clouds, atmosphere(radius, sky, 1.3));
    group.userData.radius = radius * 1.15;
    group.userData.disposables = [colorMap, dataMap, cloudMap];
    group.userData.update = (time, delta) => {
        surface.rotation.y += delta * 0.05;
        clouds.rotation.y += delta * 0.068;
    };
    return group;
}

function createGasGiant(renderer, random, color, quality) {
    const group = new THREE.Group();
    const radius = 2.3;
    const seed = seedVector(random);
    const storm = new THREE.Vector3(random() - 0.5, (random() - 0.5) * 0.6, random() - 0.5).normalize();
    const colorMap = bake(renderer, "gas", {
        uSeed: seed,
        uColorA: color,
        uColorB: shiftColor(color, -0.03, 1.1, 0.7),
        uColorC: shiftColor(color, 0.05, 0.4, 1.35),
        uParam: 16 + random() * 14,
        uVector: storm,
    }, quality.textureSize, quality.textureSize / 2);

    const body = new THREE.Mesh(sphereGeometry(), new THREE.MeshStandardMaterial({ map: colorMap.texture, roughness: 1 }));
    body.scale.setScalar(radius);
    group.add(body, atmosphere(radius, shiftColor(color, 0, 0.7, 1.4), 0.9));

    const disposables = [colorMap];
    if (random() < 0.75) {
        const inner = radius * 1.3;
        const outer = radius * (2 + random() * 0.4);
        const geometry = new THREE.RingGeometry(inner, outer, 128, 1);
        const position = geometry.attributes.position;
        const uv = geometry.attributes.uv;
        for (let i = 0; i < position.count; i++) {
            const length = Math.hypot(position.getX(i), position.getY(i));
            uv.setXY(i, (length - inner) / (outer - inner), 0.5);
        }
        const texture = ringTexture(random, shiftColor(color, 0.02, 0.6, 1.25));
        const ring = new THREE.Mesh(geometry, new THREE.MeshStandardMaterial({
            map: texture,
            side: THREE.DoubleSide,
            transparent: true,
            depthWrite: false,
            roughness: 1,
        }));
        ring.rotation.x = -Math.PI / 2;
        const tilt = new THREE.Group();
        tilt.rotation.set(0.35 + random() * 0.3, 0, (random() - 0.5) * 0.4);
        tilt.add(ring);
        group.add(tilt);
        disposables.push(texture, geometry);
    }

    group.userData.radius = radius * 1.1;
    group.userData.disposables = disposables;
    group.userData.update = (time, delta) => {
        body.rotation.y += delta * 0.08;
    };
    return group;
}

function createMoon(renderer, random, color, quality) {
    const group = new THREE.Group();
    const radius = 1.15;
    const seed = seedVector(random);
    const size = quality.textureSize;
    const colorMap = bake(renderer, "moon", { uSeed: seed, uColorA: color, uMode: 0 }, size, size / 2);
    const dataMap = bake(renderer, "moon", { uSeed: seed, uMode: 1 }, size, size / 2);
    const surface = new THREE.Mesh(sphereGeometry(), new THREE.MeshStandardMaterial({
        map: colorMap.texture,
        bumpMap: dataMap.texture,
        bumpScale: 4,
        roughness: 0.95,
        metalness: 0,
    }));
    surface.scale.setScalar(radius);
    group.add(surface);
    group.userData.radius = radius;
    group.userData.disposables = [colorMap, dataMap];
    group.userData.update = (time, delta) => {
        surface.rotation.y += delta * 0.04;
    };
    return group;
}

function createMeteor(renderer, random, color) {
    const group = new THREE.Group();
    const geometry = createRockGeometry(Math.floor(random() * 100000), 18);
    const rock = new THREE.Mesh(geometry, new THREE.MeshStandardMaterial({
        color,
        vertexColors: true,
        roughness: 0.95,
        metalness: 0.05,
    }));
    rock.scale.set(1.3, 0.85, 1);
    const companions = Array.from({ length: 4 }, (_, index) => {
        const pebble = new THREE.Mesh(createRockGeometry(index * 31 + 5, 3), rock.material);
        pebble.scale.setScalar(0.12 + random() * 0.14);
        pebble.userData.orbit = { angle: random() * Math.PI * 2, distance: 1.9 + random() * 0.8, speed: 0.15 + random() * 0.2, lift: (random() - 0.5) * 0.8 };
        return pebble;
    });
    group.add(rock, ...companions);
    group.userData.radius = 1.3;
    group.userData.disposables = [geometry, ...companions.map((pebble) => pebble.geometry)];
    group.userData.update = (time, delta) => {
        rock.rotation.x += delta * 0.12;
        rock.rotation.y += delta * 0.07;
        companions.forEach((pebble) => {
            const orbit = pebble.userData.orbit;
            orbit.angle += delta * orbit.speed;
            pebble.position.set(Math.cos(orbit.angle) * orbit.distance, orbit.lift + Math.sin(orbit.angle * 2) * 0.15, Math.sin(orbit.angle) * orbit.distance);
            pebble.rotation.x += delta * 0.5;
        });
    };
    return group;
}

function metalMaterials(renderer, accent) {
    const envMap = environmentMap(renderer);
    return {
        hull: new THREE.MeshStandardMaterial({ color: 0xe9edf2, metalness: 0.35, roughness: 0.42, envMap, envMapIntensity: 0.9 }),
        dark: new THREE.MeshStandardMaterial({ color: 0x4d535e, metalness: 0.85, roughness: 0.35, envMap, envMapIntensity: 0.8 }),
        foil: new THREE.MeshStandardMaterial({ color: accent, map: foilTexture(), bumpMap: foilTexture(), bumpScale: 1.5, metalness: 1, roughness: 0.28, envMap, envMapIntensity: 1.3 }),
        panel: new THREE.MeshStandardMaterial({ map: solarPanelTexture(), metalness: 0.65, roughness: 0.28, envMap, envMapIntensity: 1, emissive: 0x0a1a3a, emissiveIntensity: 0.6 }),
    };
}

function solarWing(materials, length, width, segments, side) {
    const wing = new THREE.Group();
    const arm = new THREE.Mesh(new THREE.CylinderGeometry(0.025, 0.025, 0.5, 8), materials.dark);
    arm.rotation.z = Math.PI / 2;
    arm.position.x = side * 0.25;
    wing.add(arm);
    const panelLength = length / segments;
    for (let i = 0; i < segments; i++) {
        const panel = new THREE.Mesh(new THREE.BoxGeometry(panelLength - 0.04, 0.025, width), [
            materials.dark, materials.dark, materials.panel, materials.panel, materials.dark, materials.dark,
        ]);
        panel.position.x = side * (0.5 + panelLength * (i + 0.5));
        wing.add(panel);
    }
    return wing;
}

function createSatellite(renderer, random, color) {
    const group = new THREE.Group();
    const craft = new THREE.Group();
    const materials = metalMaterials(renderer, color);

    const body = new THREE.Mesh(new THREE.BoxGeometry(0.55, 0.55, 0.8), materials.foil);
    const topDeck = new THREE.Mesh(new THREE.BoxGeometry(0.58, 0.06, 0.83), materials.hull);
    topDeck.position.y = 0.3;
    const bottomDeck = topDeck.clone();
    bottomDeck.position.y = -0.3;

    const dish = new THREE.Mesh(
        new THREE.SphereGeometry(0.42, 32, 12, 0, Math.PI * 2, 0, 0.75),
        new THREE.MeshStandardMaterial({ color: 0xf2f2f2, metalness: 0.2, roughness: 0.5, side: THREE.DoubleSide, envMap: materials.hull.envMap })
    );
    dish.rotation.x = Math.PI;
    dish.position.z = 0.15;
    const dishMount = new THREE.Group();
    dishMount.add(dish);
    dishMount.rotation.x = -Math.PI / 2;
    dishMount.position.z = 0.75;
    const feed = new THREE.Mesh(new THREE.CylinderGeometry(0.015, 0.015, 0.45, 6), materials.dark);
    feed.rotation.x = Math.PI / 2;
    feed.position.z = 0.75;

    const antenna = new THREE.Mesh(new THREE.CylinderGeometry(0.01, 0.01, 0.7, 6), materials.dark);
    antenna.position.set(0.15, 0.62, -0.2);
    const thruster = new THREE.Mesh(new THREE.ConeGeometry(0.12, 0.2, 16, 1, true), materials.dark);
    thruster.rotation.x = -Math.PI / 2;
    thruster.position.z = -0.5;

    const light = blinkingLight(0xff4d4d, 0.35, random() * 6);
    light.position.set(0.15, 0.98, -0.2);

    craft.add(body, topDeck, bottomDeck, dishMount, feed, antenna, thruster, light,
        solarWing(materials, 1.8, 0.55, 3, 1), solarWing(materials, 1.8, 0.55, 3, -1));
    craft.rotation.set(0.3, 0.6, 0.15);
    group.add(craft);
    group.userData.radius = 1.4;
    group.userData.disposables = Object.values(materials);
    group.userData.update = (time, delta) => {
        craft.rotation.y += delta * 0.15;
        craft.position.y = Math.sin(time * 0.6) * 0.12;
        light.userData.blink(time);
    };
    return group;
}

function createStation(renderer, random, color) {
    const group = new THREE.Group();
    const station = new THREE.Group();
    const materials = metalMaterials(renderer, 0xd4af37);
    const windows = new THREE.MeshStandardMaterial({
        color: 0xdfe4ea,
        metalness: 0.4,
        roughness: 0.4,
        envMap: materials.hull.envMap,
        emissive: new THREE.Color(color),
        emissiveMap: windowsTexture(),
        emissiveIntensity: 1.6,
    });

    const hub = new THREE.Mesh(new THREE.CylinderGeometry(0.2, 0.2, 2.6, 24), materials.hull);
    const core = new THREE.Mesh(new THREE.SphereGeometry(0.34, 24, 16), materials.hull);
    const modules = [-0.9, 0.85].map((y) => {
        const module = new THREE.Mesh(new THREE.CylinderGeometry(0.28, 0.28, 0.55, 24), materials.foil);
        module.position.y = y;
        return module;
    });
    const dock = new THREE.Mesh(new THREE.CylinderGeometry(0.1, 0.16, 0.3, 16), materials.dark);
    dock.position.y = 1.45;

    const ring = new THREE.Group();
    const torus = new THREE.Mesh(new THREE.TorusGeometry(1.45, 0.14, 16, 96), windows);
    torus.rotation.x = Math.PI / 2;
    windows.emissiveMap.wrapS = THREE.RepeatWrapping;
    windows.emissiveMap.repeat.set(3, 1);
    ring.add(torus);
    for (let i = 0; i < 4; i++) {
        const spoke = new THREE.Mesh(new THREE.CylinderGeometry(0.035, 0.035, 1.3, 8), materials.dark);
        spoke.rotation.z = Math.PI / 2;
        spoke.position.x = 0.72;
        const holder = new THREE.Group();
        holder.rotation.y = (i * Math.PI) / 2;
        holder.add(spoke);
        ring.add(holder);
    }

    const truss = new THREE.Mesh(new THREE.BoxGeometry(4.2, 0.08, 0.08), materials.dark);
    truss.position.y = -0.4;
    const arrays = [-1, 1].flatMap((side) => [0.35, -0.35].map((z) => {
        const wing = solarWing(materials, 1.1, 0.5, 2, side);
        wing.position.set(side * 1.0, -0.4, z);
        return wing;
    }));

    const lights = [[0, 1.65, 0, 0xffffff], [2.1, -0.4, 0, 0xff4d4d], [-2.1, -0.4, 0, 0x4dff88]].map(([x, y, z, tint], index) => {
        const light = blinkingLight(tint, 0.4, index * 2.1);
        light.position.set(x, y, z);
        return light;
    });

    station.add(hub, core, ...modules, dock, ring, truss, ...arrays, ...lights);
    station.rotation.set(0.45, 0, 0.25);
    station.scale.setScalar(0.85);
    group.add(station);
    group.userData.radius = 1.9;
    group.userData.disposables = [...Object.values(materials), windows];
    group.userData.update = (time, delta) => {
        ring.rotation.y += delta * 0.25;
        station.rotation.y += delta * 0.03;
        lights.forEach((light) => light.userData.blink(time));
    };
    return group;
}

function createStar(renderer, random, color, quality) {
    const group = new THREE.Group();
    const radius = 1.0;
    const tint = new THREE.Color(color);
    const colorMap = bake(renderer, "star", { uSeed: seedVector(random), uColorA: tint }, quality.textureSize / 2, quality.textureSize / 4);
    const surface = new THREE.Mesh(sphereGeometry(), new THREE.MeshBasicMaterial({ map: colorMap.texture }));
    surface.scale.setScalar(radius);

    const glowMaterial = (map, opacity) => new THREE.SpriteMaterial({
        map,
        color: tint,
        transparent: true,
        opacity,
        depthWrite: false,
        blending: THREE.AdditiveBlending,
    });
    const corona = new THREE.Sprite(glowMaterial(glowTexture(), 0.9));
    corona.scale.setScalar(radius * 5);
    const halo = new THREE.Sprite(glowMaterial(glowTexture(), 0.35));
    halo.scale.setScalar(radius * 11);
    const rays = new THREE.Sprite(glowMaterial(raysTexture(), 0.75));
    rays.scale.setScalar(radius * 9);

    group.add(halo, corona, surface, rays);
    group.userData.radius = radius * 1.6;
    group.userData.disposables = [colorMap];
    const phase = random() * 10;
    group.userData.update = (time, delta) => {
        surface.rotation.y += delta * 0.1;
        const pulse = Math.sin(time * 1.7 + phase) * 0.5 + 0.5;
        corona.scale.setScalar(radius * (4.6 + pulse * 0.8));
        rays.material.rotation += delta * 0.05;
        rays.material.opacity = 0.55 + pulse * 0.35;
    };
    return group;
}

const FACTORIES = {
    planet: createPlanet,
    gas_giant: createGasGiant,
    moon: createMoon,
    meteor: createMeteor,
    satellite: createSatellite,
    station: createStation,
    star: createStar,
};

export const SPACE_OBJECT_TYPES = Object.keys(FACTORIES);

/**
 * Crée un objet spatial prêt à l'emploi.
 * userData.radius : rayon approximatif (survol, placement) ; userData.update(time, delta) : animation.
 */
export function createSpaceObject(renderer, { type = "planet", seed = 1, color = null, quality = {} } = {}) {
    const factory = FACTORIES[type] || FACTORIES.planet;
    const random = seededRandom(seed * 7919 + type.length * 104729);
    const tint = new THREE.Color(color || defaultColor(type, seed));
    const object = factory(renderer, random, tint, { textureSize: 1024, ...quality });
    object.userData.objectType = type;
    return object;
}

// Libère la mémoire GPU propre à un objet (les ressources partagées sont conservées).
export function disposeSpaceObject(object) {
    object.userData.disposables?.forEach((item) => item.dispose());
    object.traverse((child) => {
        if (child.geometry && child.geometry !== shared.sphere && !child.geometry.userData.shared) child.geometry.dispose();
        const materials = Array.isArray(child.material) ? child.material : [child.material];
        materials.forEach((material) => material?.dispose());
    });
}

/* ---------- Décor ---------- */

export function createStarfield(count, pixelRatio) {
    const random = seededRandom(2026);
    const positions = new Float32Array(count * 3);
    const colors = new Float32Array(count * 3);
    const sizes = new Float32Array(count);
    const phases = new Float32Array(count);
    const palette = [new THREE.Color(0xffffff), new THREE.Color(0xcfe0ff), new THREE.Color(0xfff0d0), new THREE.Color(0xffd9a8), new THREE.Color(0xaec8ff)];
    const vector = new THREE.Vector3();
    for (let i = 0; i < count; i++) {
        vector.set(random() * 2 - 1, random() * 2 - 1, random() * 2 - 1).normalize().multiplyScalar(320 + random() * 60);
        positions.set([vector.x, vector.y, vector.z], i * 3);
        const tint = palette[Math.floor(random() * palette.length)];
        const brightness = 0.55 + random() * 0.45;
        colors.set([tint.r * brightness, tint.g * brightness, tint.b * brightness], i * 3);
        const big = random() < 0.012;
        sizes[i] = big ? 9 + random() * 7 : 1.5 + Math.pow(random(), 3) * 4;
        phases[i] = random();
    }
    const geometry = new THREE.BufferGeometry();
    geometry.setAttribute("position", new THREE.BufferAttribute(positions, 3));
    geometry.setAttribute("aColor", new THREE.BufferAttribute(colors, 3));
    geometry.setAttribute("aSize", new THREE.BufferAttribute(sizes, 1));
    geometry.setAttribute("aPhase", new THREE.BufferAttribute(phases, 1));
    const material = new THREE.ShaderMaterial({
        vertexShader: STARFIELD_VERTEX,
        fragmentShader: STARFIELD_FRAGMENT,
        uniforms: { uTime: { value: 0 }, uPixelRatio: { value: pixelRatio } },
        transparent: true,
        depthWrite: false,
        blending: THREE.AdditiveBlending,
    });
    const points = new THREE.Points(geometry, material);
    points.frustumCulled = false;
    return points;
}

export function createNebula(renderer, size) {
    const target = bake(renderer, "nebula", {
        uSeed: new THREE.Vector3(12.3, 45.6, 78.9),
        uColorA: new THREE.Color(0x5a3fa8),
        uColorB: new THREE.Color(0x1f6fa8),
        uVector: new THREE.Vector3(0.3, 1, 0.2).normalize(),
    }, size, size / 2, THREE.HalfFloatType);
    const sky = new THREE.Mesh(
        new THREE.SphereGeometry(450, 64, 32),
        new THREE.MeshBasicMaterial({ map: target.texture, side: THREE.BackSide, depthWrite: false })
    );
    sky.renderOrder = -1;
    sky.userData.disposables = [target];
    return sky;
}

export function createAsteroidBelt(count, innerRadius, outerRadius) {
    const random = seededRandom(99);
    const geometry = createRockGeometry(3, 2);
    const material = new THREE.MeshStandardMaterial({ color: 0x8a7a6a, vertexColors: true, roughness: 1, flatShading: true });
    const belt = new THREE.InstancedMesh(geometry, material, count);
    const matrix = new THREE.Matrix4();
    const rotation = new THREE.Quaternion();
    const scale = new THREE.Vector3();
    const position = new THREE.Vector3();
    const euler = new THREE.Euler();
    for (let i = 0; i < count; i++) {
        const angle = random() * Math.PI * 2;
        const distance = innerRadius + random() * (outerRadius - innerRadius);
        position.set(Math.cos(angle) * distance, (random() - 0.5) * 4, Math.sin(angle) * distance);
        rotation.setFromEuler(euler.set(random() * 6, random() * 6, random() * 6));
        const size = 0.06 + Math.pow(random(), 3) * 0.35;
        scale.set(size * (0.8 + random() * 0.5), size, size * (0.8 + random() * 0.5));
        belt.setMatrixAt(i, matrix.compose(position, rotation, scale));
    }
    belt.instanceMatrix.needsUpdate = true;
    return belt;
}

export function createSun() {
    const group = new THREE.Group();
    const material = (opacity, map) => new THREE.SpriteMaterial({ map, color: 0xffe2b0, transparent: true, opacity, depthWrite: false, blending: THREE.AdditiveBlending, fog: false });
    const core = new THREE.Sprite(material(1, glowTexture()));
    core.scale.setScalar(40);
    const halo = new THREE.Sprite(material(0.35, glowTexture()));
    halo.scale.setScalar(130);
    const rays = new THREE.Sprite(material(0.5, raysTexture()));
    rays.scale.setScalar(110);
    group.add(halo, core, rays);
    group.position.copy(LIGHT_DIRECTION).multiplyScalar(380);
    group.userData.update = (time, delta) => {
        rays.material.rotation += delta * 0.01;
    };
    return group;
}
