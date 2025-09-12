/**
 * Money Paws - Dynamic Weather System
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

import * as THREE from 'three';

class WeatherSystem {
    constructor() {
        this.scene = null;
        this.currentWeather = 'sunny';
        this.timeOfDay = 12; // 12 = noon
        
        // Weather particles
        this.rainSystem = null;
        this.snowSystem = null;
        this.fogSystem = null;
        
        // Lighting
        this.ambientLight = null;
        this.sunLight = null;
        this.moonLight = null;
        
        // Sky
        this.skybox = null;
        this.skyMaterial = null;
        
        // Weather transition
        this.isTransitioning = false;
        this.transitionDuration = 3000; // 3 seconds
        
        this.weatherTypes = [
            'sunny', 'partly_cloudy', 'cloudy', 'rainy', 
            'stormy', 'snowy', 'foggy', 'misty'
        ];
        
        this.weatherEffects = new Map();
    }
    
    init(scene, worldConfig = {}) {
        this.scene = scene;
        this.worldConfig = worldConfig;
        
        this.createSky();
        this.createWeatherParticles();
        this.updateLighting();
        
        // Auto weather changes if enabled
        if (worldConfig.autoWeatherChange) {
            this.startAutoWeatherCycle();
        }
        
        console.log('🌤️ Weather System initialized');
    }
    
    createSky() {
        const skyGeometry = new THREE.SphereGeometry(800, 32, 15);
        this.skyMaterial = new THREE.MeshBasicMaterial({
            side: THREE.BackSide,
            fog: false
        });
        
        this.skybox = new THREE.Mesh(skyGeometry, this.skyMaterial);
        this.scene.add(this.skybox);
        
        this.updateSkyColor();
    }
    
    createWeatherParticles() {
        // Rain system
        this.createRainSystem();
        
        // Snow system
        this.createSnowSystem();
        
        // Fog system
        this.createFogSystem();
    }
    
    createRainSystem() {
        const particleCount = 1000;
        const geometry = new THREE.BufferGeometry();
        const positions = new Float32Array(particleCount * 3);
        const velocities = new Float32Array(particleCount * 3);
        
        for (let i = 0; i < particleCount * 3; i += 3) {
            positions[i] = (Math.random() - 0.5) * 400; // x
            positions[i + 1] = Math.random() * 200 + 100; // y
            positions[i + 2] = (Math.random() - 0.5) * 400; // z
            
            velocities[i] = (Math.random() - 0.5) * 2; // x velocity
            velocities[i + 1] = -Math.random() * 20 - 10; // y velocity (downward)
            velocities[i + 2] = (Math.random() - 0.5) * 2; // z velocity
        }
        
        geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        geometry.setAttribute('velocity', new THREE.BufferAttribute(velocities, 3));
        
        const material = new THREE.PointsMaterial({
            color: 0x87CEEB,
            size: 0.5,
            transparent: true,
            opacity: 0.7
        });
        
        this.rainSystem = new THREE.Points(geometry, material);
        this.rainSystem.visible = false;
        this.scene.add(this.rainSystem);
    }
    
    createSnowSystem() {
        const particleCount = 500;
        const geometry = new THREE.BufferGeometry();
        const positions = new Float32Array(particleCount * 3);
        
        for (let i = 0; i < particleCount * 3; i += 3) {
            positions[i] = (Math.random() - 0.5) * 400;
            positions[i + 1] = Math.random() * 200 + 50;
            positions[i + 2] = (Math.random() - 0.5) * 400;
        }
        
        geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        
        const material = new THREE.PointsMaterial({
            color: 0xFFFFFF,
            size: 2,
            transparent: true,
            opacity: 0.9
        });
        
        this.snowSystem = new THREE.Points(geometry, material);
        this.snowSystem.visible = false;
        this.scene.add(this.snowSystem);
    }
    
    createFogSystem() {
        // Fog is handled by the scene fog property
        this.fogSystem = {
            enabled: false,
            color: 0xCCCCCC,
            near: 50,
            far: 300
        };
    }
    
    setWeather(weatherType) {
        if (!this.weatherTypes.includes(weatherType)) {
            console.warn(`Unknown weather type: ${weatherType}`);
            return;
        }
        
        if (this.isTransitioning) return;
        
        this.isTransitioning = true;
        const previousWeather = this.currentWeather;
        this.currentWeather = weatherType;
        
        this.transitionWeather(previousWeather, weatherType);
        
        setTimeout(() => {
            this.isTransitioning = false;
        }, this.transitionDuration);
        
        console.log(`🌦️ Weather changed to: ${weatherType}`);
    }
    
    transitionWeather(from, to) {
        // Hide all weather effects first
        this.hideAllWeatherEffects();
        
        // Apply new weather effects
        switch (to) {
            case 'sunny':
                this.applySunnyWeather();
                break;
            case 'partly_cloudy':
                this.applyPartlyCloudyWeather();
                break;
            case 'cloudy':
                this.applyCloudyWeather();
                break;
            case 'rainy':
                this.applyRainyWeather();
                break;
            case 'stormy':
                this.applyStormyWeather();
                break;
            case 'snowy':
                this.applySnowyWeather();
                break;
            case 'foggy':
                this.applyFoggyWeather();
                break;
            case 'misty':
                this.applyMistyWeather();
                break;
        }
        
        this.updateSkyColor();
        this.updateLighting();
    }
    
    hideAllWeatherEffects() {
        if (this.rainSystem) this.rainSystem.visible = false;
        if (this.snowSystem) this.snowSystem.visible = false;
        
        // Clear fog
        this.scene.fog = null;
    }
    
    applySunnyWeather() {
        this.skyMaterial.color.setHex(0x87CEEB); // Bright blue
        this.ambientLightIntensity = 0.8;
        this.sunLightIntensity = 1.2;
    }
    
    applyPartlyCloudyWeather() {
        this.skyMaterial.color.setHex(0xB0C4DE); // Light steel blue
        this.ambientLightIntensity = 0.7;
        this.sunLightIntensity = 1.0;
    }
    
    applyCloudyWeather() {
        this.skyMaterial.color.setHex(0x708090); // Slate gray
        this.ambientLightIntensity = 0.6;
        this.sunLightIntensity = 0.8;
    }
    
    applyRainyWeather() {
        this.skyMaterial.color.setHex(0x696969); // Dim gray
        this.ambientLightIntensity = 0.4;
        this.sunLightIntensity = 0.6;
        
        this.rainSystem.visible = true;
        this.scene.fog = new THREE.Fog(0x696969, 100, 400);
    }
    
    applyStormyWeather() {
        this.skyMaterial.color.setHex(0x2F4F4F); // Dark slate gray
        this.ambientLightIntensity = 0.3;
        this.sunLightIntensity = 0.4;
        
        this.rainSystem.visible = true;
        this.scene.fog = new THREE.Fog(0x2F4F4F, 50, 300);
        
        // Add lightning effect (simplified)
        this.addLightningEffect();
    }
    
    applySnowyWeather() {
        this.skyMaterial.color.setHex(0xE6E6FA); // Lavender
        this.ambientLightIntensity = 0.7;
        this.sunLightIntensity = 0.9;
        
        this.snowSystem.visible = true;
        this.scene.fog = new THREE.Fog(0xE6E6FA, 150, 500);
    }
    
    applyFoggyWeather() {
        this.skyMaterial.color.setHex(0xDCDCDC); // Gainsboro
        this.ambientLightIntensity = 0.5;
        this.sunLightIntensity = 0.6;
        
        this.scene.fog = new THREE.Fog(0xDCDCDC, 20, 200);
    }
    
    applyMistyWeather() {
        this.skyMaterial.color.setHex(0xF5F5F5); // White smoke
        this.ambientLightIntensity = 0.6;
        this.sunLightIntensity = 0.8;
        
        this.scene.fog = new THREE.Fog(0xF5F5F5, 50, 300);
    }
    
    updateSkyColor() {
        // Adjust sky color based on time of day
        const dayProgress = (this.timeOfDay - 6) / 12; // 0 = dawn, 1 = dusk
        const nightFactor = Math.max(0, Math.min(1, Math.abs(dayProgress - 0.5) * 2));
        
        // Interpolate between day and night colors
        const currentColor = this.skyMaterial.color;
        const nightColor = new THREE.Color(0x191970); // Midnight blue
        
        if (nightFactor > 0.7) {
            currentColor.lerp(nightColor, nightFactor * 0.8);
        }
    }
    
    setTimeOfDay(hour) {
        this.timeOfDay = Math.max(0, Math.min(24, hour));
        this.updateLighting();
        this.updateSkyColor();
        
        console.log(`🕐 Time set to: ${hour}:00`);
    }
    
    updateLighting() {
        if (!this.scene) return;
        
        // Find existing lights
        const lights = this.scene.children.filter(child => child instanceof THREE.Light);
        
        // Update ambient light
        const ambientLight = lights.find(light => light instanceof THREE.AmbientLight);
        if (ambientLight) {
            ambientLight.intensity = this.ambientLightIntensity || 0.6;
        }
        
        // Update directional light (sun)
        const directionalLight = lights.find(light => light instanceof THREE.DirectionalLight);
        if (directionalLight) {
            directionalLight.intensity = this.sunLightIntensity || 1.0;
            
            // Adjust sun position based on time
            const sunAngle = (this.timeOfDay - 6) * (Math.PI / 12); // Sunrise to sunset
            directionalLight.position.set(
                Math.sin(sunAngle) * 100,
                Math.cos(sunAngle) * 100,
                50
            );
        }
    }
    
    addLightningEffect() {
        // Simple lightning flash effect
        const lightning = () => {
            if (this.currentWeather !== 'stormy') return;
            
            const ambientLight = this.scene.children.find(light => light instanceof THREE.AmbientLight);
            if (ambientLight) {
                const originalIntensity = ambientLight.intensity;
                ambientLight.intensity = 2.0;
                ambientLight.color.setHex(0xE0E6FF);
                
                setTimeout(() => {
                    ambientLight.intensity = originalIntensity;
                    ambientLight.color.setHex(0x404040);
                }, 100);
            }
            
            // Random next lightning
            setTimeout(lightning, Math.random() * 10000 + 5000);
        };
        
        // Start lightning after random delay
        setTimeout(lightning, Math.random() * 3000 + 1000);
    }
    
    update() {
        // Update weather particle animations
        this.updateRainAnimation();
        this.updateSnowAnimation();
        
        // Auto weather progression
        if (this.worldConfig.autoWeatherChange && Math.random() < 0.0001) { // Very rare
            this.randomWeatherChange();
        }
    }
    
    updateRainAnimation() {
        if (!this.rainSystem || !this.rainSystem.visible) return;
        
        const positions = this.rainSystem.geometry.attributes.position.array;
        const velocities = this.rainSystem.geometry.attributes.velocity.array;
        
        for (let i = 0; i < positions.length; i += 3) {
            positions[i] += velocities[i] * 0.1;     // x
            positions[i + 1] += velocities[i + 1] * 0.1; // y
            positions[i + 2] += velocities[i + 2] * 0.1; // z
            
            // Reset particles that hit the ground
            if (positions[i + 1] < 0) {
                positions[i] = (Math.random() - 0.5) * 400;
                positions[i + 1] = 200;
                positions[i + 2] = (Math.random() - 0.5) * 400;
            }
        }
        
        this.rainSystem.geometry.attributes.position.needsUpdate = true;
    }
    
    updateSnowAnimation() {
        if (!this.snowSystem || !this.snowSystem.visible) return;
        
        const positions = this.snowSystem.geometry.attributes.position.array;
        
        for (let i = 0; i < positions.length; i += 3) {
            positions[i] += Math.sin(Date.now() * 0.001 + i) * 0.1; // Gentle sway
            positions[i + 1] -= 0.5; // Slow fall
            positions[i + 2] += Math.cos(Date.now() * 0.001 + i) * 0.1;
            
            // Reset particles that hit the ground
            if (positions[i + 1] < 0) {
                positions[i] = (Math.random() - 0.5) * 400;
                positions[i + 1] = 200;
                positions[i + 2] = (Math.random() - 0.5) * 400;
            }
        }
        
        this.snowSystem.geometry.attributes.position.needsUpdate = true;
    }
    
    randomWeatherChange() {
        const currentIndex = this.weatherTypes.indexOf(this.currentWeather);
        let newWeather;
        
        do {
            newWeather = this.weatherTypes[Math.floor(Math.random() * this.weatherTypes.length)];
        } while (newWeather === this.currentWeather);
        
        this.setWeather(newWeather);
    }
    
    startAutoWeatherCycle() {
        // Change weather every 5-15 minutes
        const nextChange = Math.random() * 600000 + 300000; // 5-15 minutes
        
        setTimeout(() => {
            this.randomWeatherChange();
            this.startAutoWeatherCycle(); // Schedule next change
        }, nextChange);
    }
    
    // Educational weather information
    getWeatherEducationalInfo() {
        const info = {
            sunny: {
                title: "Sunny Weather",
                description: "Clear skies and bright sunshine. Perfect for pets to play outside!",
                effects: "High visibility, warm temperatures, active wildlife",
                petCare: "Make sure pets have shade and water available"
            },
            rainy: {
                title: "Rainy Weather", 
                description: "Precipitation from clouds provides water for plants and animals",
                effects: "Limited visibility, cooler temperatures, animals seek shelter",
                petCare: "Keep pets dry and warm, perfect time for indoor activities"
            },
            snowy: {
                title: "Snowy Weather",
                description: "Frozen precipitation creates a winter wonderland",
                effects: "Cold temperatures, snow accumulation, seasonal animal behaviors",
                petCare: "Protect pets from cold, watch for ice on paws"
            }
        };
        
        return info[this.currentWeather] || info.sunny;
    }
    
    // Public getters
    getCurrentWeather() {
        return this.currentWeather;
    }
    
    getTimeOfDay() {
        return this.timeOfDay;
    }
    
    getAvailableWeatherTypes() {
        return [...this.weatherTypes];
    }
}

export { WeatherSystem };