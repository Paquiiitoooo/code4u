(function() {
    'use strict';
    
    let parallaxInitialized = false;
    let observerInitialized = false;
    let ticking = false;
    let scrollHandler = null;
    function initParallax() {
        if (parallaxInitialized) return true;
        
        const parallaxElements = document.querySelectorAll('.parallax-element');
        const floatingElements = document.querySelectorAll('.floating-element');
        
        if (parallaxElements.length === 0 && floatingElements.length === 0) {
            return false;
        }
        
        function updateParallax() {
            const scrolled = window.pageYOffset || window.scrollY || 0;
            const windowHeight = window.innerHeight;
            
            // Parallax pour les éléments du hero
            parallaxElements.forEach(element => {
                const rect = element.getBoundingClientRect();
                const elementTop = rect.top + scrolled;
                const elementCenter = elementTop + rect.height / 2;
                
                // Calculer la distance depuis le centre de l'écran
                const viewportCenter = scrolled + windowHeight / 2;
                const distanceFromCenter = viewportCenter - elementCenter;
                
                const speed = parseFloat(element.dataset.speed) || 0.3;
                const yPos = -(distanceFromCenter * speed);
                
                element.style.transform = `translate3d(0, ${yPos}px, 0)`;
            });
            
            // Parallax pour les éléments flottants dans les sections
            floatingElements.forEach(element => {
                const section = element.closest('.parallax-section');
                if (!section) return;
                
                const sectionRect = section.getBoundingClientRect();
                const sectionTop = sectionRect.top + scrolled;
                const sectionCenter = sectionTop + sectionRect.height / 2;
                
                const viewportCenter = scrolled + windowHeight / 2;
                const distanceFromCenter = viewportCenter - sectionCenter;
                
                const speed = parseFloat(element.dataset.speed) || 0.3;
                const yPos = -(distanceFromCenter * speed);
                const rotation = (distanceFromCenter * 0.1) % 360;
                
                element.style.transform = `translate3d(0, ${yPos}px, 0) rotate(${rotation}deg)`;
            });
            
            ticking = false;
        }
        
        function handleScroll() {
            if (!ticking) {
                window.requestAnimationFrame(updateParallax);
                ticking = true;
            }
        }
        
        scrollHandler = handleScroll;
        window.addEventListener('scroll', scrollHandler, { passive: true });
        updateParallax();
        
        parallaxInitialized = true;
        return true;
    }
    
    function initIntersectionObserver() {
        if (observerInitialized) return true;
        
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    
                    if (entry.target.classList.contains('web-hero-stat')) {
                        animateCounter(entry.target);
                    }
                }
            });
        }, observerOptions);

        function observeElements() {
            const serviceCards = document.querySelectorAll('.service-card');
            const techItems = document.querySelectorAll('.tech-item');
            const processSteps = document.querySelectorAll('.process-step');
            const stats = document.querySelectorAll('.web-hero-stat');
            
            if (serviceCards.length === 0 && techItems.length === 0 && processSteps.length === 0) {
                return false;
            }
            
            serviceCards.forEach((card, index) => {
                card.style.transitionDelay = `${index * 0.1}s`;
                observer.observe(card);
            });

            techItems.forEach((item, index) => {
                item.style.transitionDelay = `${index * 0.05}s`;
                observer.observe(item);
            });

            processSteps.forEach((step, index) => {
                step.style.transitionDelay = `${index * 0.1}s`;
                observer.observe(step);
            });
            
            stats.forEach(stat => {
                observer.observe(stat);
            });
            
            observerInitialized = true;
            return true;
        }
        
        return observeElements();
    }
    
    function animateCounter(statElement) {
        const numberElement = statElement.querySelector('.stat-number');
        if (!numberElement || numberElement.dataset.animated) return;
        
        numberElement.dataset.animated = 'true';
        const text = numberElement.textContent.trim();
        const number = parseInt(text.replace(/\D/g, ''));
        const suffix = text.replace(/\d/g, '');
        
        if (isNaN(number)) return;
        
        let current = 0;
        const increment = number / 50;
        const duration = 2000;
        const stepTime = duration / 50;
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= number) {
                current = number;
                clearInterval(timer);
            }
            numberElement.textContent = Math.floor(current) + suffix;
        }, stepTime);
    }
    
    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (href === '#' || href === '') return;
                
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    const headerOffset = 100;
                    const elementPosition = target.getBoundingClientRect().top;
                    const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
                    
                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });
                }
            });
        });
    }
    
    function initScrollProgress() {
        const progressBar = document.createElement('div');
        progressBar.className = 'scroll-progress';
        progressBar.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 0%;
            height: 3px;
            background: linear-gradient(90deg, var(--secondary-color), var(--accent-color));
            z-index: 9999;
            transition: width 0.1s ease;
            pointer-events: none;
        `;
        document.body.appendChild(progressBar);
        
        function updateProgress() {
            const windowHeight = document.documentElement.scrollHeight - window.innerHeight;
            const scrolled = window.pageYOffset;
            const progress = windowHeight > 0 ? (scrolled / windowHeight) * 100 : 0;
            progressBar.style.width = Math.min(100, Math.max(0, progress)) + '%';
        }
        
        window.addEventListener('scroll', updateProgress, { passive: true });
        updateProgress();
    }
    
    function initAll() {
        const parallaxOk = initParallax();
        const observerOk = initIntersectionObserver();
        
        initSmoothScroll();
        initScrollProgress();
        
        return parallaxOk && observerOk;
    }
    
    function retryInit(maxRetries = 10, delay = 200) {
        let retries = 0;
        
        function tryInit() {
            if (initAll()) {
                return;
            }
            
            retries++;
            if (retries < maxRetries) {
                setTimeout(tryInit, delay);
            }
        }
        
        tryInit();
    }
    
    // ========================================
    // TECH ITEMS FILTER SYSTEM - Filtrage par catégories
    // ========================================
    function initTechItemsAnimations() {
        const techItems = document.querySelectorAll('.web-page .tech-stack .tech-item');
        const techFilters = document.querySelectorAll('.tech-filter-btn');
        const techStack = document.querySelector('.web-page .tech-stack');
        
        if (!techItems.length || !techFilters.length || !techStack) {
            return false;
        }
        
        // Initialiser tous les éléments comme visibles
        techItems.forEach((item, index) => {
            setTimeout(() => {
                item.classList.add('visible');
            }, index * 50); // Délai progressif pour l'animation
        });
        
        // Fonction de filtrage avec réorganisation
        function filterTechItems(category) {
            const visibleItems = [];
            const hiddenItems = [];
            
            // Séparer les éléments visibles et cachés
            techItems.forEach((item) => {
                const itemCategory = item.getAttribute('data-category');
                const shouldShow = category === 'all' || itemCategory === category;
                
                if (shouldShow) {
                    visibleItems.push(item);
                } else {
                    hiddenItems.push(item);
                }
            });
            
            // Cacher d'abord tous les éléments
            techItems.forEach((item) => {
                item.classList.remove('visible');
                item.classList.add('hidden');
            });
            
            // Réorganiser : mettre les éléments visibles en premier dans le DOM
            visibleItems.forEach((item, index) => {
                techStack.appendChild(item);
            });
            
            // Ajouter les éléments cachés à la fin
            hiddenItems.forEach((item) => {
                techStack.appendChild(item);
            });
            
            // Animer l'apparition des éléments visibles avec délai progressif
            visibleItems.forEach((item, index) => {
                setTimeout(() => {
                    item.classList.remove('hidden');
                    item.classList.add('visible');
                }, index * 50); // Délai progressif pour l'animation en cascade
            });
        }
        
        // Gestion des clics sur les filtres
        techFilters.forEach(btn => {
            btn.addEventListener('click', function() {
                // Retirer la classe active de tous les boutons
                techFilters.forEach(b => b.classList.remove('active'));
                
                // Ajouter la classe active au bouton cliqué
                this.classList.add('active');
                
                // Récupérer la catégorie
                const category = this.getAttribute('data-filter');
                
                // Filtrer les éléments
                filterTechItems(category);
            });
        });
        
        return true;
    }
    
    // Initialisation
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                retryInit();
                initTechItemsAnimations();
            }, 100);
        });
    } else {
        setTimeout(() => {
            retryInit();
            initTechItemsAnimations();
        }, 100);
    }
    
    document.addEventListener('templateLoaded', function(e) {
        if (e.detail.placeholder === 'header-placeholder' || e.detail.placeholder === 'footer-placeholder') {
            parallaxInitialized = false;
            observerInitialized = false;
            
            if (scrollHandler) {
                window.removeEventListener('scroll', scrollHandler);
            }
            
            setTimeout(() => {
                retryInit(5, 150);
                initTechItemsAnimations();
            }, 200);
        }
    });
    
})();
