import * as THREE from "three";
import { GLTFLoader } from "three/addons/loaders/GLTFLoader.js";
import { DRACOLoader } from "three/addons/loaders/DRACOLoader.js";
import { MeshoptDecoder } from "three/addons/libs/meshopt_decoder.module.js";

// Ce module n'est téléchargé que si au moins un article utilise un fichier .glb personnalisé.
const loader = new GLTFLoader();
const dracoLoader = new DRACOLoader();
dracoLoader.setDecoderPath(document.querySelector('meta[name="draco-decoder-path"]')?.content || "/draco/");
loader.setDRACOLoader(dracoLoader);
loader.setMeshoptDecoder(MeshoptDecoder);

// Charge un .glb et le recentre / redimensionne pour qu'il tienne dans `size` unités.
export async function loadModel(url, size) {
    const gltf = await loader.loadAsync(url);
    const model = gltf.scene;
    const bounds = new THREE.Box3().setFromObject(model);
    const dimensions = bounds.getSize(new THREE.Vector3());
    const center = bounds.getCenter(new THREE.Vector3());
    const scale = size / Math.max(dimensions.x, dimensions.y, dimensions.z, 0.0001);
    model.scale.setScalar(scale);
    model.position.copy(center).multiplyScalar(-scale);
    const wrapper = new THREE.Group();
    wrapper.add(model);
    wrapper.userData.update = (time, delta) => {
        wrapper.rotation.y += delta * 0.12;
    };
    return wrapper;
}
