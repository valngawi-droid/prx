/* ============================================================
   ChiperX — Hero 3D (Three.js): objek melayang + parallax kursor
   ============================================================ */
'use strict';

(function () {
    const canvas = document.getElementById('hero3d');
    if (!canvas || typeof THREE === 'undefined') return;

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(60, 1, 0.1, 100);
    camera.position.z = 9;

    const renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));

    // ---- Pencahayaan neon ----
    scene.add(new THREE.AmbientLight(0x334155, 1.4));
    const lightPurple = new THREE.PointLight(0x8b5cf6, 60, 40);
    lightPurple.position.set(-6, 4, 6);
    scene.add(lightPurple);
    const lightCyan = new THREE.PointLight(0x22d3ee, 50, 40);
    lightCyan.position.set(6, -3, 5);
    scene.add(lightCyan);

    // ---- Koleksi objek melayang ----
    const group = new THREE.Group();
    scene.add(group);

    const materials = [
        new THREE.MeshStandardMaterial({ color: 0x8b5cf6, roughness: 0.25, metalness: 0.8, emissive: 0x2e1065, emissiveIntensity: 0.6 }),
        new THREE.MeshStandardMaterial({ color: 0x22d3ee, roughness: 0.2, metalness: 0.7, emissive: 0x083344, emissiveIntensity: 0.7 }),
        new THREE.MeshStandardMaterial({ color: 0x4ade80, roughness: 0.3, metalness: 0.6, emissive: 0x052e16, emissiveIntensity: 0.5 }),
        new THREE.MeshStandardMaterial({ color: 0xf472b6, roughness: 0.25, metalness: 0.75, emissive: 0x500724, emissiveIntensity: 0.5 }),
    ];

    const geometries = [
        new THREE.TorusKnotGeometry(0.9, 0.28, 140, 20),
        new THREE.IcosahedronGeometry(0.8, 0),
        new THREE.OctahedronGeometry(0.75, 0),
        new THREE.TorusGeometry(0.7, 0.25, 16, 60),
    ];

    const floaters = [];
    const positions = [
        [4.6, 1.4, 0, 0, 0], [6.2, -1.2, -1, 1, 1], [3.4, -2.2, 1, 2, 2],
        [5.6, 3.0, -2, 3, 3], [2.6, 2.6, -1.5, 1, 0], [7.2, 0.8, -3, 2, 1],
    ];
    positions.forEach(([x, y, z, gi, mi], i) => {
        const mesh = new THREE.Mesh(geometries[gi], materials[mi]);
        mesh.position.set(x, y, z);
        mesh.rotation.set(Math.random() * Math.PI, Math.random() * Math.PI, 0);
        mesh.userData = {
            baseY: y,
            floatSpeed: 0.5 + Math.random() * 0.7,
            floatAmp: 0.35 + Math.random() * 0.35,
            rotSpeed: (Math.random() - 0.5) * 0.012,
            phase: Math.random() * Math.PI * 2,
        };
        group.add(mesh);
        floaters.push(mesh);
    });

    // ---- Partikel bintang ----
    const starCount = 350;
    const starGeo = new THREE.BufferGeometry();
    const starPos = new Float32Array(starCount * 3);
    for (let i = 0; i < starCount * 3; i++) starPos[i] = (Math.random() - 0.5) * 40;
    starGeo.setAttribute('position', new THREE.BufferAttribute(starPos, 3));
    const stars = new THREE.Points(starGeo, new THREE.PointsMaterial({ color: 0x818cf8, size: 0.05, transparent: true, opacity: 0.8 }));
    scene.add(stars);

    // ---- Interaksi kursor (parallax halus) ----
    let targetX = 0, targetY = 0;
    window.addEventListener('pointermove', (e) => {
        targetX = (e.clientX / window.innerWidth - 0.5) * 2;
        targetY = (e.clientY / window.innerHeight - 0.5) * 2;
    }, { passive: true });

    function resize() {
        const w = canvas.clientWidth, h = canvas.clientHeight;
        if (canvas.width !== w || canvas.height !== h) {
            renderer.setSize(w, h, false);
            camera.aspect = w / h;
            camera.updateProjectionMatrix();
        }
    }

    const clock = new THREE.Clock();
    (function animate() {
        requestAnimationFrame(animate);
        resize();
        const t = clock.getElapsedTime();

        floaters.forEach((m) => {
            const u = m.userData;
            m.position.y = u.baseY + Math.sin(t * u.floatSpeed + u.phase) * u.floatAmp; // melayang
            m.rotation.x += u.rotSpeed;
            m.rotation.y += u.rotSpeed * 1.4;
        });
        stars.rotation.y = t * 0.015;

        // Kamera mengikuti kursor (dengan easing)
        camera.position.x += (targetX * 1.3 - camera.position.x) * 0.045;
        camera.position.y += (-targetY * 0.9 - camera.position.y) * 0.045;
        camera.lookAt(3.5, 0, 0);

        renderer.render(scene, camera);
    })();
})();
