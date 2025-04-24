/**
 * Particles.js - Sistema de partículas para páginas de autenticação
 * Versão 1.0.0
 */

document.addEventListener('DOMContentLoaded', function() {
    // Criar o container de partículas se ainda não existir
    if (!document.getElementById('particles-container')) {
        const particlesContainer = document.createElement('div');
        particlesContainer.id = 'particles-container';
        
        // Adicionar antes do primeiro elemento filho do body
        document.body.insertBefore(particlesContainer, document.body.firstChild);
    }
    
    const particlesContainer = document.getElementById('particles-container');
    
    // Detectar dispositivo para ajustar configurações
    const isMobile = window.innerWidth < 768;
    const isLowPower = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    
    // Configurações de partículas - ajustadas para desempenho
    const config = {
        particleCount: isLowPower ? 15 : (isMobile ? 25 : 40),
        particleColors: [
            'rgba(0, 207, 155, 0.5)',   // primary
            'rgba(20, 193, 73, 0.5)',   // secondary
            'rgba(12, 90, 84, 0.5)',    // accent
            'rgba(71, 233, 196, 0.5)',  // primary-light
            'rgba(34, 197, 185, 0.5)'   // info
        ],
        minSize: isMobile ? 2 : 3,
        maxSize: isMobile ? 5 : 8,
        minOpacity: 0.2,
        maxOpacity: 0.8,
        connectionDistance: isMobile ? 10 : 15,
        useGlow: !isLowPower && !isMobile,
        animationSpeed: isLowPower ? 0.5 : 1
    };
    
    // Array para armazenar todas as partículas
    const particles = [];
    
    // Criar SVG container
    const svgNS = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(svgNS, 'svg');
    svg.setAttribute('width', '100%');
    svg.setAttribute('height', '100%');
    svg.style.position = 'absolute';
    particlesContainer.appendChild(svg);
    
    // Defs para filtros
    const defs = document.createElementNS(svgNS, 'defs');
    svg.appendChild(defs);
    
    // Criar filtro de glow apenas se necessário
    if (config.useGlow) {
        const filter = document.createElementNS(svgNS, 'filter');
        filter.setAttribute('id', 'glow');
        
        const feGaussianBlur = document.createElementNS(svgNS, 'feGaussianBlur');
        feGaussianBlur.setAttribute('stdDeviation', '2');
        feGaussianBlur.setAttribute('result', 'blur');
        filter.appendChild(feGaussianBlur);
        
        const feColorMatrix = document.createElementNS(svgNS, 'feColorMatrix');
        feColorMatrix.setAttribute('in', 'blur');
        feColorMatrix.setAttribute('type', 'matrix');
        feColorMatrix.setAttribute('values', '1 0 0 0 0 0 1 0 0 0 0 0 1 0 0 0 0 0 10 -3');
        feColorMatrix.setAttribute('result', 'glow');
        filter.appendChild(feColorMatrix);
        
        const feMerge = document.createElementNS(svgNS, 'feMerge');
        const feMergeNode1 = document.createElementNS(svgNS, 'feMergeNode');
        feMergeNode1.setAttribute('in', 'glow');
        const feMergeNode2 = document.createElementNS(svgNS, 'feMergeNode');
        feMergeNode2.setAttribute('in', 'SourceGraphic');
        feMerge.appendChild(feMergeNode1);
        feMerge.appendChild(feMergeNode2);
        filter.appendChild(feMerge);
        
        defs.appendChild(filter);
    }
    
    // Grupo para conexões
    const connections = document.createElementNS(svgNS, 'g');
    connections.setAttribute('stroke', 'rgba(0, 207, 155, 0.1)');
    connections.setAttribute('stroke-width', isMobile ? '0.3' : '0.5');
    svg.appendChild(connections);
    
    // Grupo para partículas
    const particlesGroup = document.createElementNS(svgNS, 'g');
    svg.appendChild(particlesGroup);
    
    // Função para criar uma partícula
    function createParticle() {
        const particle = {
            element: document.createElementNS(svgNS, 'circle'),
            x: Math.random() * 100,
            y: Math.random() * 100,
            size: config.minSize + Math.random() * (config.maxSize - config.minSize),
            speedX: (Math.random() - 0.5) * 0.1 * config.animationSpeed,
            speedY: (Math.random() - 0.5) * 0.1 * config.animationSpeed,
            opacity: config.minOpacity + Math.random() * (config.maxOpacity - config.minOpacity),
            color: config.particleColors[Math.floor(Math.random() * config.particleColors.length)],
            pulse: Math.random() * 2 * Math.PI,
            pulseSpeed: 0.01 + Math.random() * 0.02 * config.animationSpeed
        };
        
        particle.element.setAttribute('cx', `${particle.x}%`);
        particle.element.setAttribute('cy', `${particle.y}%`);
        particle.element.setAttribute('r', particle.size);
        particle.element.setAttribute('fill', particle.color);
        particle.element.setAttribute('opacity', particle.opacity);
        
        if (config.useGlow) {
            particle.element.setAttribute('filter', 'url(#glow)');
        }
        
        particlesGroup.appendChild(particle.element);
        particles.push(particle);
    }
    
    // Criar partículas iniciais
    for (let i = 0; i < config.particleCount; i++) {
        createParticle();
    }
    
    // Redimensionar o SVG quando a janela mudar de tamanho
    function handleResize() {
        // Ajustar atributos ou configurações se necessário
        const isMobileNow = window.innerWidth < 768;
        if (isMobile !== isMobileNow) {
            // Se mudou de mobile para desktop ou vice-versa, ajustar configurações
            connections.setAttribute('stroke-width', isMobileNow ? '0.3' : '0.5');
        }
    }
    
    window.addEventListener('resize', handleResize);
    
    // Variável para controlar se a animação deve continuar
    let animating = true;
    
    // Otimização: verificar se a página está visível
    document.addEventListener('visibilitychange', function() {
        animating = !document.hidden;
        if (animating) {
            requestAnimationFrame(updateParticles);
        }
    });
    
    // Atualizar partículas e conexões
    function updateParticles() {
        if (!animating) return;
        
        // Limpar conexões anteriores para redesenhar
        while (connections.firstChild) {
            connections.removeChild(connections.firstChild);
        }
        
        // Atualizar cada partícula
        particles.forEach((particle, index) => {
            // Mover partícula
            particle.x += particle.speedX;
            particle.y += particle.speedY;
            
            // Aplicar efeito de pulsação suave
            particle.pulse += particle.pulseSpeed;
            const pulseFactor = 0.2 * Math.sin(particle.pulse) + 1;
            const currentSize = particle.size * pulseFactor;
            
            // Verificar limites da tela e inverter direção se necessário
            if (particle.x < 0 || particle.x > 100) {
                particle.speedX *= -1;
                particle.x = particle.x < 0 ? 0 : 100;
            }
            if (particle.y < 0 || particle.y > 100) {
                particle.speedY *= -1;
                particle.y = particle.y < 0 ? 0 : 100;
            }
            
            // Atualizar posição e tamanho da partícula
            particle.element.setAttribute('cx', `${particle.x}%`);
            particle.element.setAttribute('cy', `${particle.y}%`);
            particle.element.setAttribute('r', currentSize);
            
            // Otimização: limitar a criação de conexões apenas para partículas próximas
            for (let i = index + 1; i < particles.length; i++) {
                const otherParticle = particles[i];
                
                // Calcular distância entre partículas
                const dx = particle.x - otherParticle.x;
                const dy = particle.y - otherParticle.y;
                
                // Otimização: verificação rápida de distância antes do cálculo completo
                if (Math.abs(dx) < config.connectionDistance && Math.abs(dy) < config.connectionDistance) {
                    const distance = Math.sqrt(dx * dx + dy * dy);
                    
                    // Conectar se estiver próximo
                    if (distance < config.connectionDistance) {
                        const opacity = 0.2 * (1 - distance / config.connectionDistance);
                        const line = document.createElementNS(svgNS, 'line');
                        line.setAttribute('x1', `${particle.x}%`);
                        line.setAttribute('y1', `${particle.y}%`);
                        line.setAttribute('x2', `${otherParticle.x}%`);
                        line.setAttribute('y2', `${otherParticle.y}%`);
                        line.setAttribute('stroke-opacity', opacity);
                        connections.appendChild(line);
                    }
                }
            }
        });
        
        // Continuar a animação
        requestAnimationFrame(updateParticles);
    }
    
    // Iniciar animação
    updateParticles();
    
    // Interação com mouse/toque
    function addInteractivity() {
        // Não adicionar interatividade se preferir movimento reduzido
        if (isLowPower) return;
        
        const pointerEvents = ['mousemove', 'touchmove'];
        
        pointerEvents.forEach(event => {
            document.addEventListener(event, (e) => {
                // Obter posição normalizada do ponteiro (0-100%)
                let pointerX, pointerY;
                
                if (event === 'touchmove') {
                    pointerX = (e.touches[0].clientX / window.innerWidth) * 100;
                    pointerY = (e.touches[0].clientY / window.innerHeight) * 100;
                } else {
                    pointerX = (e.clientX / window.innerWidth) * 100;
                    pointerY = (e.clientY / window.innerHeight) * 100;
                }
                
                // Raio de influência do mouse
                const influenceRadius = isMobile ? 10 : 20;
                
                // Aplicar efeito às partículas próximas do cursor
                particles.forEach(particle => {
                    const dx = particle.x - pointerX;
                    const dy = particle.y - pointerY;
                    const distance = Math.sqrt(dx * dx + dy * dy);
                    
                    // Se a partícula estiver próxima do cursor
                    if (distance < influenceRadius) {
                        // Calcular fator baseado na distância (mais próximo = efeito mais forte)
                        const factor = 1 - distance / influenceRadius;
                        
                        // Adicionamos pequena velocidade de repulsão
                        const repelStrength = isMobile ? 0.05 : 0.1;
                        const angleRad = Math.atan2(dy, dx);
                        
                        particle.speedX += Math.cos(angleRad) * factor * repelStrength;
                        particle.speedY += Math.sin(angleRad) * factor * repelStrength;
                        
                        // Limitar velocidade máxima
                        const maxSpeed = 0.3 * config.animationSpeed;
                        const currentSpeed = Math.sqrt(
                            particle.speedX * particle.speedX + 
                            particle.speedY * particle.speedY
                        );
                        
                        if (currentSpeed > maxSpeed) {
                            const scale = maxSpeed / currentSpeed;
                            particle.speedX *= scale;
                            particle.speedY *= scale;
                        }
                    }
                });
            }, { passive: true });
        });
    }
    
    // Adicionar interatividade se não for dispositivo de baixa potência
    addInteractivity();
});