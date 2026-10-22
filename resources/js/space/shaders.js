// Shaders GLSL utilisés pour « cuire » les textures des astres sur le GPU.
// Chaque texture est générée une seule fois (quelques millisecondes) : rien à télécharger.

export const NOISE_GLSL = /* glsl */ `
vec3 mod289(vec3 x) { return x - floor(x * (1.0 / 289.0)) * 289.0; }
vec4 mod289(vec4 x) { return x - floor(x * (1.0 / 289.0)) * 289.0; }
vec4 permute(vec4 x) { return mod289(((x * 34.0) + 1.0) * x); }
vec4 taylorInvSqrt(vec4 r) { return 1.79284291400159 - 0.85373472095314 * r; }

float snoise(vec3 v) {
    const vec2 C = vec2(1.0 / 6.0, 1.0 / 3.0);
    const vec4 D = vec4(0.0, 0.5, 1.0, 2.0);
    vec3 i = floor(v + dot(v, C.yyy));
    vec3 x0 = v - i + dot(i, C.xxx);
    vec3 g = step(x0.yzx, x0.xyz);
    vec3 l = 1.0 - g;
    vec3 i1 = min(g.xyz, l.zxy);
    vec3 i2 = max(g.xyz, l.zxy);
    vec3 x1 = x0 - i1 + C.xxx;
    vec3 x2 = x0 - i2 + C.yyy;
    vec3 x3 = x0 - D.yyy;
    i = mod289(i);
    vec4 p = permute(permute(permute(
        i.z + vec4(0.0, i1.z, i2.z, 1.0))
        + i.y + vec4(0.0, i1.y, i2.y, 1.0))
        + i.x + vec4(0.0, i1.x, i2.x, 1.0));
    float n_ = 0.142857142857;
    vec3 ns = n_ * D.wyz - D.xzx;
    vec4 j = p - 49.0 * floor(p * ns.z * ns.z);
    vec4 x_ = floor(j * ns.z);
    vec4 y_ = floor(j - 7.0 * x_);
    vec4 x = x_ * ns.x + ns.yyyy;
    vec4 y = y_ * ns.x + ns.yyyy;
    vec4 h = 1.0 - abs(x) - abs(y);
    vec4 b0 = vec4(x.xy, y.xy);
    vec4 b1 = vec4(x.zw, y.zw);
    vec4 s0 = floor(b0) * 2.0 + 1.0;
    vec4 s1 = floor(b1) * 2.0 + 1.0;
    vec4 sh = -step(h, vec4(0.0));
    vec4 a0 = b0.xzyw + s0.xzyw * sh.xxyy;
    vec4 a1 = b1.xzyw + s1.xzyw * sh.zzww;
    vec3 p0 = vec3(a0.xy, h.x);
    vec3 p1 = vec3(a0.zw, h.y);
    vec3 p2 = vec3(a1.xy, h.z);
    vec3 p3 = vec3(a1.zw, h.w);
    vec4 norm = taylorInvSqrt(vec4(dot(p0, p0), dot(p1, p1), dot(p2, p2), dot(p3, p3)));
    p0 *= norm.x; p1 *= norm.y; p2 *= norm.z; p3 *= norm.w;
    vec4 m = max(0.6 - vec4(dot(x0, x0), dot(x1, x1), dot(x2, x2), dot(x3, x3)), 0.0);
    m = m * m;
    return 42.0 * dot(m * m, vec4(dot(p0, x0), dot(p1, x1), dot(p2, x2), dot(p3, x3)));
}

float fbm(vec3 p, int octaves) {
    float value = 0.0;
    float amplitude = 0.5;
    for (int i = 0; i < 8; i++) {
        if (i >= octaves) break;
        value += amplitude * snoise(p);
        p = p * 2.03 + vec3(1.7, 9.2, 3.1);
        amplitude *= 0.5;
    }
    return value;
}

float ridged(vec3 p, int octaves) {
    float value = 0.0;
    float amplitude = 0.5;
    for (int i = 0; i < 8; i++) {
        if (i >= octaves) break;
        float n = 1.0 - abs(snoise(p));
        value += amplitude * n * n;
        p = p * 2.07 + vec3(4.1, 1.3, 7.7);
        amplitude *= 0.5;
    }
    return value;
}

vec3 hash33(vec3 p) {
    p = fract(p * vec3(0.1031, 0.1030, 0.0973));
    p += dot(p, p.yxz + 33.33);
    return fract((p.xxy + p.yxx) * p.zyx);
}

vec3 toLinear(vec3 c) { return pow(max(c, 0.0), vec3(2.2)); }
`;

export const BAKE_VERTEX = /* glsl */ `
varying vec2 vUv;
void main() {
    vUv = uv;
    gl_Position = vec4(position.xy, 0.0, 1.0);
}
`;

// Convertit une coordonnée UV (projection équirectangulaire de SphereGeometry) en direction 3D,
// ce qui donne des textures sans couture ni pincement aux pôles.
const BAKE_MAIN = /* glsl */ `
varying vec2 vUv;
uniform vec3 uSeed;
uniform vec3 uColorA;
uniform vec3 uColorB;
uniform vec3 uColorC;
uniform float uMode;
uniform float uParam;
uniform vec3 uVector;

vec4 surface(vec3 p);

void main() {
    float phi = vUv.x * 6.283185307;
    float theta = (1.0 - vUv.y) * 3.141592653;
    vec3 p = vec3(-cos(phi) * sin(theta), cos(theta), sin(phi) * sin(theta));
    gl_FragColor = surface(p);
}
`;

const SURFACES = {
    // Planète tellurique : océans, continents, végétation, montagnes enneigées et calottes glaciaires.
    // uMode 1 = carte de données (R = relief pour le bump, G = rugosité).
    terran: /* glsl */ `
    vec4 surface(vec3 p) {
        vec3 q = p + uSeed;
        vec3 warp = vec3(fbm(q * 0.9, 3), fbm(q * 0.9 + 7.3, 3), fbm(q * 0.9 + 3.1, 3));
        float h = fbm(q * 1.2 + warp * 0.6, 6) + ridged(q * 3.5, 4) * 0.12 - 0.06;
        float sea = uParam;
        float lat = abs(p.y);
        float ice = smoothstep(0.80, 0.90, lat + fbm(q * 5.0, 3) * 0.12);
        float land = smoothstep(sea - 0.005, sea + 0.005, h);
        float elevation = clamp((h - sea) * 2.4, 0.0, 1.0);

        if (uMode > 0.5) {
            float relief = land * elevation + ice * 0.12;
            float roughness = mix(0.55, 0.95, max(land, ice));
            return vec4(relief, roughness, 0.0, 1.0);
        }

        float depth = clamp((sea - h) * 3.5, 0.0, 1.0);
        vec3 ocean = mix(uColorA * 1.25 + 0.04, uColorA * 0.22, pow(depth, 0.6));
        float moist = fbm(q * 2.4 + 11.0, 4) * 0.5 + 0.5;
        vec3 lowland = mix(uColorB, uColorC, smoothstep(0.42, 0.68, moist) * (1.0 - lat * 0.8));
        vec3 highland = mix(uColorB * 0.75, vec3(0.42, 0.39, 0.37), 0.55);
        vec3 ground = mix(lowland, highland, smoothstep(0.15, 0.55, elevation));
        ground = mix(ground, vec3(0.93, 0.94, 0.97), smoothstep(0.62, 0.85, elevation));
        ground *= 0.85 + 0.3 * fbm(q * 14.0, 3);
        vec3 coast = mix(ocean, uColorA * 1.6 + 0.1, smoothstep(sea - 0.04, sea, h) * 0.45);
        vec3 color = mix(coast, ground, land);
        color = mix(color, vec3(0.9, 0.94, 1.0), ice);
        return vec4(toLinear(color), 1.0);
    }`,

    // Couche de nuages tourbillonnants (niveau de gris lu comme alphaMap).
    clouds: /* glsl */ `
    vec4 surface(vec3 p) {
        vec3 q = p * 2.0 + uSeed * 1.7;
        vec3 warp = vec3(fbm(q, 3), fbm(q + 5.2, 3), fbm(q + 9.1, 3));
        float c = fbm(q * 1.4 + warp * 1.8, 6);
        float bands = 0.75 + 0.25 * sin(p.y * 9.0 + warp.x * 2.0);
        c = smoothstep(0.12, 0.5, c * bands) * uParam;
        return vec4(vec3(c), 1.0);
    }`,

    // Géante gazeuse : bandes turbulentes et grande tempête.
    gas: /* glsl */ `
    vec4 surface(vec3 p) {
        vec3 q = p + uSeed;
        float turbulence = fbm(q * vec3(2.2, 9.0, 2.2), 6);
        float swirl = fbm(q * 3.5 + turbulence, 4);
        float lat = p.y + turbulence * 0.07 + swirl * 0.025;
        float bands = sin(lat * uParam) * 0.5 + 0.5;
        float fine = sin(lat * uParam * 2.7 + 1.3) * 0.5 + 0.5;
        vec3 color = mix(uColorA, uColorB, smoothstep(0.2, 0.8, bands));
        color = mix(color, uColorC, fine * 0.3);

        vec3 delta = p - uVector;
        delta.y *= 1.9;
        float stormDistance = length(delta) + swirl * 0.04;
        float storm = smoothstep(0.2, 0.05, stormDistance);
        float stormRing = smoothstep(0.24, 0.17, stormDistance) - storm;
        color = mix(color, uColorC * 1.15, stormRing * 0.5);
        color = mix(color, uColorB * vec3(1.15, 0.6, 0.45), storm * 0.9);
        color *= 0.88 + 0.24 * fbm(q * 16.0, 3);
        return vec4(toLinear(color), 1.0);
    }`,

    // Lune : mers sombres, cratères à plusieurs échelles avec rebords.
    moon: /* glsl */ `
    float craterLayer(vec3 p, float density) {
        vec3 cell = floor(p);
        vec3 local = fract(p);
        float height = 0.0;
        for (int x = -1; x <= 1; x++)
        for (int y = -1; y <= 1; y++)
        for (int z = -1; z <= 1; z++) {
            vec3 offset = vec3(float(x), float(y), float(z));
            vec3 rnd = hash33(cell + offset);
            if (rnd.z > density) continue;
            vec3 center = offset + rnd;
            float radius = mix(0.12, 0.42, fract(rnd.x * 7.31 + rnd.y));
            float d = length(local - center) / radius;
            float bowl = d < 1.0 ? (d * d - 1.0) * 0.8 : 0.0;
            float rim = 0.32 * exp(-pow((d - 1.0) * 4.5, 2.0));
            height += bowl + rim;
        }
        return height;
    }

    vec4 surface(vec3 p) {
        vec3 q = p + uSeed;
        float maria = smoothstep(0.05, 0.3, fbm(q * 1.1, 4));
        float craters = craterLayer(q * 3.0, 0.55) * 1.0
            + craterLayer(q * 7.0 + 3.7, 0.65) * 0.5
            + craterLayer(q * 16.0 + 9.1, 0.75) * 0.22
            + craterLayer(q * 34.0 + 1.3, 0.8) * 0.1;
        craters *= 1.0 - maria * 0.55;
        float grain = fbm(q * 40.0, 3) * 0.06;
        float height = craters + grain;

        if (uMode > 0.5) {
            return vec4(clamp(height * 0.35 + 0.5, 0.0, 1.0), 0.95, 0.0, 1.0);
        }

        vec3 highlands = uColorA;
        vec3 seas = uColorA * 0.55;
        vec3 color = mix(highlands, seas, maria);
        color *= 1.05 + craters * 0.18 + fbm(q * 9.0, 4) * 0.12;
        color = mix(color, color * 1.25, smoothstep(0.1, 0.3, craters));
        return vec4(toLinear(color), 1.0);
    }`,

    // Surface d'étoile : granulation brillante.
    star: /* glsl */ `
    vec4 surface(vec3 p) {
        vec3 q = p * 3.0 + uSeed;
        float granules = ridged(q * 2.5, 4);
        float flow = fbm(q + fbm(q * 0.7, 3), 5) * 0.5 + 0.5;
        vec3 color = mix(uColorA, vec3(1.0, 0.98, 0.92), clamp(flow * 0.7 + granules * 0.35, 0.0, 1.0));
        color = mix(color, uColorA * 0.55, smoothstep(0.62, 0.8, 1.0 - flow) * 0.6);
        return vec4(toLinear(color * 1.1), 1.0);
    }`,

    // Fond de ciel : nébuleuses colorées et bande de la Voie lactée.
    nebula: /* glsl */ `
    vec4 surface(vec3 p) {
        vec3 q = p * 1.6 + uSeed;
        vec3 warp = vec3(fbm(q, 4), fbm(q + 4.7, 4), fbm(q + 8.3, 4));
        float cloudA = fbm(q * 1.2 + warp * 1.6, 6) * 0.5 + 0.5;
        float cloudB = fbm(q * 2.1 - warp * 1.2 + 13.0, 5) * 0.5 + 0.5;
        float dust = fbm(q * 3.4 + warp, 5) * 0.5 + 0.5;
        float band = exp(-pow(dot(p, uVector) * 3.2, 2.0));

        vec3 color = vec3(0.006, 0.008, 0.035);
        color += uColorA * pow(smoothstep(0.4, 0.95, cloudA), 2.0) * 0.7;
        color += uColorB * pow(smoothstep(0.45, 1.0, cloudB), 2.2) * 0.55;
        color += vec3(0.32, 0.28, 0.42) * band * (0.35 + 0.65 * cloudB) * 0.45;
        color *= mix(1.0, smoothstep(0.35, 0.7, dust), band * 0.7);
        return vec4(toLinear(color), 1.0);
    }`,
};

export function bakeFragment(name) {
    return NOISE_GLSL + BAKE_MAIN + SURFACES[name];
}

// Halo atmosphérique : BackSide pour la lueur autour du disque, FrontSide pour le voile sur le bord.
export const ATMOSPHERE_VERTEX = /* glsl */ `
varying vec3 vNormalW;
varying vec3 vPositionW;
void main() {
    vec4 world = modelMatrix * vec4(position, 1.0);
    vPositionW = world.xyz;
    vNormalW = normalize(mat3(modelMatrix) * normal);
    gl_Position = projectionMatrix * viewMatrix * world;
}
`;

export const ATMOSPHERE_FRAGMENT = /* glsl */ `
uniform vec3 uColor;
uniform vec3 uLightDir;
uniform float uIntensity;
uniform float uOuter;
varying vec3 vNormalW;
varying vec3 vPositionW;
void main() {
    vec3 n = normalize(vNormalW);
    vec3 viewDir = normalize(cameraPosition - vPositionW);
    float facing = dot(n, viewDir);
    float glow = uOuter > 0.5
        ? pow(smoothstep(0.0, 0.36, -facing), 1.6)
        : pow(1.0 - clamp(facing, 0.0, 1.0), 3.5);
    float light = smoothstep(-0.35, 0.55, dot(n, uLightDir)) * 0.9 + 0.1;
    gl_FragColor = vec4(uColor * glow * light * uIntensity, 1.0);
}
`;

// Champ d'étoiles scintillantes ; les plus grosses ont des aigrettes en croix.
export const STARFIELD_VERTEX = /* glsl */ `
attribute float aSize;
attribute float aPhase;
attribute vec3 aColor;
uniform float uTime;
uniform float uPixelRatio;
varying vec3 vColor;
varying float vTwinkle;
varying float vSize;
void main() {
    vColor = aColor;
    vTwinkle = 0.65 + 0.35 * sin(uTime * (0.8 + aPhase * 2.2) + aPhase * 40.0);
    vSize = aSize;
    vec4 mvPosition = modelViewMatrix * vec4(position, 1.0);
    gl_PointSize = aSize * uPixelRatio * (0.85 + 0.25 * vTwinkle);
    gl_Position = projectionMatrix * mvPosition;
}
`;

export const STARFIELD_FRAGMENT = /* glsl */ `
varying vec3 vColor;
varying float vTwinkle;
varying float vSize;
void main() {
    vec2 uv = gl_PointCoord - 0.5;
    float d = length(uv);
    float core = smoothstep(0.5, 0.0, d);
    core = pow(core, 2.6);
    float spikes = 0.0;
    if (vSize > 7.0) {
        spikes = max(0.0, 1.0 - abs(uv.x) * 26.0) * max(0.0, 1.0 - abs(uv.y) * 2.0)
               + max(0.0, 1.0 - abs(uv.y) * 26.0) * max(0.0, 1.0 - abs(uv.x) * 2.0);
        spikes *= 0.7;
    }
    float alpha = (core + spikes) * vTwinkle;
    if (alpha < 0.01) discard;
    gl_FragColor = vec4(vColor * alpha, 1.0);
}
`;
