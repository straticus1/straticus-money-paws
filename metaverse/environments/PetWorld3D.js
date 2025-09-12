/**
 * Money Paws - 3D Pet World Engine
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { WeatherSystem } from './WeatherSystem.js';
import { EcosystemManager } from './EcosystemManager.js';
import { SocialSpaces } from './SocialSpaces.js';

class PetWorld3D {
    constructor(canvasElement, worldConfig = {}) {
        this.canvas = canvasElement;
        this.worldConfig = worldConfig;
        
        // Core Three.js components
        this.scene = null;
        this.camera = null;
        this.renderer = null;
        this.controls = null;
        
        // World systems
        this.weatherSystem = new WeatherSystem();
        this.ecosystem = new EcosystemManager();
        this.socialSpaces = new SocialSpaces();
        
        // Asset management
        this.loader = new GLTFLoader();
        this.textureLoader = new THREE.TextureLoader();
        this.audioListener = new THREE.AudioListener();
        this.audioLoader = new THREE.AudioLoader();
        
        // World state
        this.pets = new Map();
        this.users = new Map();
        this.worldObjects = [];
        this.isRunning = false;
        
        // Educational zones
        this.educationalZones = [];
        this.interactiveElements = [];
        
        this.init();
    }
    
    init() {
        this.createScene();
        this.createCamera();
        this.createRenderer();
        this.createControls();
        this.createLighting();
        this.createTerrain();
        this.initWeatherSystem();
        this.createEducationalZones();
        this.bindEvents();
        
        console.log('🌍 PetWorld3D initialized successfully');
    }
    
    createScene() {
        this.scene = new THREE.Scene();
        this.scene.background = new THREE.Color(0x87CEEB); // Sky blue
        this.scene.fog = new THREE.Fog(0x87CEEB, 100, 1000);
    }
    
    createCamera() {
        const aspect = this.canvas.clientWidth / this.canvas.clientHeight;
        this.camera = new THREE.PerspectiveCamera(75, aspect, 0.1, 2000);
        this.camera.position.set(0, 50, 100);
        this.camera.add(this.audioListener);
    }
    
    createRenderer() {
        this.renderer = new THREE.WebGLRenderer({
            canvas: this.canvas,
            antialias: true,
            alpha: true
        });
        
        this.renderer.setSize(this.canvas.clientWidth, this.canvas.clientHeight);
        this.renderer.setPixelRatio(window.devicePixelRatio);
        this.renderer.shadowMap.enabled = true;
        this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        this.renderer.outputEncoding = THREE.sRGBEncoding;
        this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
        this.renderer.toneMappingExposure = 1.0;
    }
    
    createControls() {
        this.controls = new OrbitControls(this.camera, this.renderer.domElement);
        this.controls.enableDamping = true;
        this.controls.dampingFactor = 0.05;
        this.controls.maxPolarAngle = Math.PI / 2 - 0.1; // Prevent going underground
        this.controls.minDistance = 10;
        this.controls.maxDistance = 500;
    }
    
    createLighting() {
        // Ambient light for general illumination
        const ambientLight = new THREE.AmbientLight(0x404040, 0.6);
        this.scene.add(ambientLight);
        
        // Directional light for sun
        const directionalLight = new THREE.DirectionalLight(0xffffff, 1.0);
        directionalLight.position.set(50, 100, 50);
        directionalLight.castShadow = true;
        
        // Shadow settings
        directionalLight.shadow.mapSize.width = 2048;
        directionalLight.shadow.mapSize.height = 2048;
        directionalLight.shadow.camera.near = 0.5;
        directionalLight.shadow.camera.far = 500;
        directionalLight.shadow.camera.left = -100;
        directionalLight.shadow.camera.right = 100;
        directionalLight.shadow.camera.top = 100;
        directionalLight.shadow.camera.bottom = -100;
        
        this.scene.add(directionalLight);
        
        // Helper for development (remove in production)
        if (this.worldConfig.showHelpers) {
            const helper = new THREE.DirectionalLightHelper(directionalLight, 5);
            this.scene.add(helper);
        }
    }
    
    createTerrain() {
        const worldType = this.worldConfig.type || 'park';
        
        switch (worldType) {
            case 'park':
                this.createParkTerrain();
                break;
            case 'beach':
                this.createBeachTerrain();
                break;
            case 'forest':
                this.createForestTerrain();
                break;
            case 'educational_zone':
                this.createEducationalTerrain();
                break;
            default:
                this.createDefaultTerrain();
        }
    }
    
    createParkTerrain() {
        // Create rolling hills with grass texture
        const geometry = new THREE.PlaneGeometry(500, 500, 50, 50);
        
        // Add height variation for hills
        const vertices = geometry.attributes.position.array;
        for (let i = 0; i < vertices.length; i += 3) {
            const x = vertices[i];
            const z = vertices[i + 2];
            vertices[i + 1] = Math.sin(x * 0.01) * 5 + Math.sin(z * 0.01) * 5; // Hills
        }
        geometry.attributes.position.needsUpdate = true;
        geometry.computeVertexNormals();
        
        // Grass material
        const grassTexture = this.textureLoader.load('/assets/textures/grass.jpg');
        grassTexture.wrapS = grassTexture.wrapT = THREE.RepeatWrapping;
        grassTexture.repeat.set(20, 20);
        
        const material = new THREE.MeshLambertMaterial({
            map: grassTexture,
            color: 0x90EE90
        });
        
        const ground = new THREE.Mesh(geometry, material);
        ground.rotation.x = -Math.PI / 2;
        ground.receiveShadow = true;
        this.scene.add(ground);
        
        // Add trees, flowers, and park elements
        this.addParkElements();
    }
    
    createBeachTerrain() {
        // Sandy terrain with water
        const geometry = new THREE.PlaneGeometry(500, 500);
        const sandTexture = this.textureLoader.load('/assets/textures/sand.jpg');
        sandTexture.wrapS = sandTexture.wrapT = THREE.RepeatWrapping;
        sandTexture.repeat.set(15, 15);
        
        const sandMaterial = new THREE.MeshLambertMaterial({ map: sandTexture });
        const sand = new THREE.Mesh(geometry, sandMaterial);
        sand.rotation.x = -Math.PI / 2;
        sand.receiveShadow = true;
        this.scene.add(sand);
        
        // Add water plane
        const waterGeometry = new THREE.PlaneGeometry(200, 500);
        const waterMaterial = new THREE.MeshLambertMaterial({
            color: 0x006994,
            transparent: true,
            opacity: 0.8
        });
        const water = new THREE.Mesh(waterGeometry, waterMaterial);
        water.rotation.x = -Math.PI / 2;
        water.position.x = -150;
        water.position.y = 0.5;
        this.scene.add(water);
        
        this.addBeachElements();
    }
    
    createForestTerrain() {
        // Forest floor
        const geometry = new THREE.PlaneGeometry(500, 500);
        const forestTexture = this.textureLoader.load('/assets/textures/forest_floor.jpg');
        forestTexture.wrapS = forestTexture.wrapT = THREE.RepeatWrapping;
        forestTexture.repeat.set(10, 10);
        
        const material = new THREE.MeshLambertMaterial({ map: forestTexture });
        const ground = new THREE.Mesh(geometry, material);
        ground.rotation.x = -Math.PI / 2;
        ground.receiveShadow = true;
        this.scene.add(ground);
        
        this.addForestElements();
    }
    
    createEducationalTerrain() {
        // Modern, clean educational space
        const geometry = new THREE.PlaneGeometry(300, 300);
        const material = new THREE.MeshLambertMaterial({ color: 0xf0f0f0 });
        const ground = new THREE.Mesh(geometry, material);
        ground.rotation.x = -Math.PI / 2;
        ground.receiveShadow = true;
        this.scene.add(ground);
        
        this.createLearningStations();
    }
    
    addParkElements() {
        // Trees
        for (let i = 0; i < 20; i++) {
            const tree = this.createTree();
            tree.position.set(
                (Math.random() - 0.5) * 400,
                0,
                (Math.random() - 0.5) * 400
            );
            this.scene.add(tree);
        }
        
        // Flowers
        for (let i = 0; i < 50; i++) {
            const flower = this.createFlower();
            flower.position.set(
                (Math.random() - 0.5) * 300,
                2,
                (Math.random() - 0.5) * 300
            );
            this.scene.add(flower);
        }
        
        // Benches for social spaces
        this.socialSpaces.createParkBenches(this.scene);
    }
    
    addBeachElements() {
        // Palm trees
        for (let i = 0; i < 10; i++) {
            const palm = this.createPalmTree();
            palm.position.set(
                Math.random() * 100 + 50,
                0,
                (Math.random() - 0.5) * 400
            );
            this.scene.add(palm);
        }
        
        // Beach umbrellas
        this.socialSpaces.createBeachUmbrellas(this.scene);
    }
    
    addForestElements() {
        // Dense tree placement
        for (let i = 0; i < 100; i++) {
            const tree = this.createForestTree();
            tree.position.set(
                (Math.random() - 0.5) * 450,
                0,
                (Math.random() - 0.5) * 450
            );
            this.scene.add(tree);
        }
        
        // Clearing with seating
        this.socialSpaces.createForestClearing(this.scene);
    }
    
    createTree() {
        const group = new THREE.Group();
        
        // Trunk
        const trunkGeometry = new THREE.CylinderGeometry(1, 1.5, 8);
        const trunkMaterial = new THREE.MeshLambertMaterial({ color: 0x8B4513 });
        const trunk = new THREE.Mesh(trunkGeometry, trunkMaterial);
        trunk.position.y = 4;
        trunk.castShadow = true;
        group.add(trunk);
        
        // Leaves
        const leavesGeometry = new THREE.SphereGeometry(6, 8, 6);
        const leavesMaterial = new THREE.MeshLambertMaterial({ color: 0x228B22 });
        const leaves = new THREE.Mesh(leavesGeometry, leavesMaterial);
        leaves.position.y = 12;
        leaves.castShadow = true;
        group.add(leaves);
        
        return group;
    }
    
    createFlower() {
        const group = new THREE.Group();
        
        // Stem
        const stemGeometry = new THREE.CylinderGeometry(0.1, 0.1, 2);
        const stemMaterial = new THREE.MeshLambertMaterial({ color: 0x32CD32 });
        const stem = new THREE.Mesh(stemGeometry, stemMaterial);
        stem.position.y = 1;
        group.add(stem);
        
        // Flower
        const flowerGeometry = new THREE.SphereGeometry(0.5, 6, 6);
        const colors = [0xFF69B4, 0xFF0000, 0xFFFF00, 0xFF4500, 0x9370DB];
        const flowerMaterial = new THREE.MeshLambertMaterial({
            color: colors[Math.floor(Math.random() * colors.length)]
        });
        const flower = new THREE.Mesh(flowerGeometry, flowerMaterial);
        flower.position.y = 2.5;
        group.add(flower);
        
        return group;
    }
    
    createLearningStations() {
        // Biology station
        const biologyStation = this.createInteractiveStation('biology', {
            x: -50, z: -50, color: 0x32CD32
        });
        this.scene.add(biologyStation);
        
        // Ecology station  
        const ecologyStation = this.createInteractiveStation('ecology', {
            x: 50, z: -50, color: 0x4169E1
        });
        this.scene.add(ecologyStation);
        
        // Care training station
        const careStation = this.createInteractiveStation('care_training', {
            x: 0, z: 50, color: 0xFF69B4
        });
        this.scene.add(careStation);
        
        this.educationalZones.push(biologyStation, ecologyStation, careStation);
    }
    
    createInteractiveStation(type, config) {
        const group = new THREE.Group();
        
        // Platform
        const platformGeometry = new THREE.CylinderGeometry(8, 10, 2);
        const platformMaterial = new THREE.MeshLambertMaterial({ color: config.color });
        const platform = new THREE.Mesh(platformGeometry, platformMaterial);
        platform.position.y = 1;
        platform.userData = { type, interactive: true };
        group.add(platform);
        
        // Holographic display
        const displayGeometry = new THREE.PlaneGeometry(6, 4);
        const displayMaterial = new THREE.MeshBasicMaterial({
            color: 0x00ffff,
            transparent: true,
            opacity: 0.7
        });
        const display = new THREE.Mesh(displayGeometry, displayMaterial);
        display.position.set(0, 5, 0);
        group.add(display);
        
        group.position.set(config.x, 0, config.z);
        return group;
    }
    
    initWeatherSystem() {
        this.weatherSystem.init(this.scene, this.worldConfig);
    }
    
    // Pet management
    addPet(petData) {
        const pet = this.createPetAvatar(petData);
        this.pets.set(petData.id, pet);
        this.scene.add(pet);
        return pet;
    }
    
    removePet(petId) {
        const pet = this.pets.get(petId);
        if (pet) {
            this.scene.remove(pet);
            this.pets.delete(petId);
        }
    }
    
    createPetAvatar(petData) {
        // Simplified pet avatar - in production, load from GLTF
        const group = new THREE.Group();
        
        // Body
        const bodyGeometry = new THREE.SphereGeometry(1, 8, 6);
        const bodyMaterial = new THREE.MeshLambertMaterial({ color: petData.color || 0x8B4513 });
        const body = new THREE.Mesh(bodyGeometry, bodyMaterial);
        body.position.y = 1;
        body.castShadow = true;
        group.add(body);
        
        // Basic animation
        group.userData = {
            id: petData.id,
            type: 'pet',
            animationMixer: null
        };
        
        // Random position
        group.position.set(
            (Math.random() - 0.5) * 100,
            0,
            (Math.random() - 0.5) * 100
        );
        
        return group;
    }
    
    // User management
    addUser(userData) {
        const userAvatar = this.createUserAvatar(userData);
        this.users.set(userData.id, userAvatar);
        this.scene.add(userAvatar);
        return userAvatar;
    }
    
    createUserAvatar(userData) {
        // Simple user representation
        const group = new THREE.Group();
        
        const geometry = new THREE.CapsuleGeometry(0.5, 2);
        const material = new THREE.MeshLambertMaterial({ color: userData.avatarColor || 0x0066CC });
        const avatar = new THREE.Mesh(geometry, material);
        avatar.position.y = 1;
        avatar.castShadow = true;
        group.add(avatar);
        
        group.userData = {
            id: userData.id,
            type: 'user',
            name: userData.name
        };
        
        return group;
    }
    
    // Animation and rendering
    animate() {
        if (!this.isRunning) return;
        
        requestAnimationFrame(() => this.animate());
        
        this.controls.update();
        this.weatherSystem.update();
        this.updatePetAnimations();
        this.renderer.render(this.scene, this.camera);
    }
    
    updatePetAnimations() {
        // Simple pet movement
        this.pets.forEach(pet => {
            if (Math.random() < 0.01) { // 1% chance per frame to move
                const angle = Math.random() * Math.PI * 2;
                const distance = Math.random() * 2;
                pet.position.x += Math.cos(angle) * distance;
                pet.position.z += Math.sin(angle) * distance;
                
                // Keep pets in bounds
                pet.position.x = Math.max(-200, Math.min(200, pet.position.x));
                pet.position.z = Math.max(-200, Math.min(200, pet.position.z));
            }
        });
    }
    
    // Event handling
    bindEvents() {
        window.addEventListener('resize', () => this.onWindowResize());
        this.renderer.domElement.addEventListener('click', (event) => this.onWorldClick(event));
    }
    
    onWindowResize() {
        const width = this.canvas.clientWidth;
        const height = this.canvas.clientHeight;
        
        this.camera.aspect = width / height;
        this.camera.updateProjectionMatrix();
        this.renderer.setSize(width, height);
    }
    
    onWorldClick(event) {
        // Raycast to detect clicks on interactive elements
        const mouse = new THREE.Vector2();
        const rect = this.renderer.domElement.getBoundingClientRect();
        
        mouse.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
        mouse.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;
        
        const raycaster = new THREE.Raycaster();
        raycaster.setFromCamera(mouse, this.camera);
        
        const intersects = raycaster.intersectObjects(this.scene.children, true);
        
        for (const intersect of intersects) {
            if (intersect.object.userData.interactive) {
                this.onInteractiveElementClick(intersect.object);
                break;
            }
        }
    }
    
    onInteractiveElementClick(element) {
        const type = element.userData.type;
        
        switch (type) {
            case 'biology':
                this.openEducationalModule('animal_biology');
                break;
            case 'ecology':
                this.openEducationalModule('ecosystem_balance');
                break;
            case 'care_training':
                this.openEducationalModule('responsible_pet_care');
                break;
        }
    }
    
    openEducationalModule(moduleType) {
        // Emit event for parent application to handle
        this.canvas.dispatchEvent(new CustomEvent('openEducationalModule', {
            detail: { moduleType }
        }));
    }
    
    // Public methods
    start() {
        this.isRunning = true;
        this.animate();
        console.log('🎮 PetWorld3D started');
    }
    
    stop() {
        this.isRunning = false;
        console.log('⏹️ PetWorld3D stopped');
    }
    
    dispose() {
        this.stop();
        this.renderer.dispose();
        this.scene.clear();
    }
    
    // Weather controls
    setWeather(weatherType) {
        this.weatherSystem.setWeather(weatherType);
    }
    
    // Time controls
    setTimeOfDay(hour) {
        this.weatherSystem.setTimeOfDay(hour);
    }
}

export { PetWorld3D };