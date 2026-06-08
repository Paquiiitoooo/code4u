(function() {
    'use strict';
    
    let loadingInterval = null;
    let loadingTimeout = null;
    
    function hideLoading() {
        const loadingScreen = document.getElementById('loadingScreen');
        if (loadingScreen) {
            loadingScreen.classList.add('hidden');
            loadingScreen.style.cssText = 'display: none !important; opacity: 0 !important; visibility: hidden !important; pointer-events: none !important; z-index: -1 !important; position: fixed !important;';
            document.body.style.overflow = '';
            document.documentElement.style.overflow = '';
            document.body.classList.remove('loading-active');
            document.documentElement.classList.remove('loading-active');
        }
        if (loadingInterval) {
            clearInterval(loadingInterval);
            loadingInterval = null;
        }
        if (loadingTimeout) {
            clearTimeout(loadingTimeout);
            loadingTimeout = null;
        }
    }
    
    function initLoading() {
        const loadingScreen = document.getElementById('loadingScreen');
        
        if (!loadingScreen) {
            document.body.style.overflow = '';
            document.documentElement.style.overflow = '';
            return;
        }
        
        const siteLoaded = sessionStorage.getItem('code4u_site_loaded');
        
        if (siteLoaded === 'true') {
            hideLoading();
            return;
        }
        
        document.body.style.overflow = 'hidden';
        document.documentElement.style.overflow = 'hidden';
        document.body.classList.add('loading-active');
        document.documentElement.classList.add('loading-active');
        
        const progressBar = loadingScreen.querySelector('.loading-progress-bar');
        let progress = 0;
        const maxTime = 1000;
        const startTime = Date.now();
        
        loadingInterval = setInterval(() => {
            const elapsed = Date.now() - startTime;
            progress = Math.min(100, (elapsed / maxTime) * 100);
            
            if (progressBar) {
                progressBar.style.width = progress + '%';
            }
            
            if (progress >= 100 || elapsed >= maxTime) {
                if (loadingInterval) clearInterval(loadingInterval);
                sessionStorage.setItem('code4u_site_loaded', 'true');
                hideLoading();
            }
        }, 20);
        
        loadingTimeout = setTimeout(function() {
            if (loadingInterval) clearInterval(loadingInterval);
            sessionStorage.setItem('code4u_site_loaded', 'true');
            hideLoading();
        }, 1800);
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLoading);
    } else {
        initLoading();
    }
    
    window.addEventListener('load', function() {
        setTimeout(function() {
            hideLoading();
        }, 300);
    });
    
    setTimeout(function() {
        hideLoading();
    }, 2500);
})();

document.addEventListener('DOMContentLoaded', function() {
    function animateCounter(element, target) {
        let current = 0;
        const increment = target / 50;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            element.textContent = Math.floor(current) + (target >= 100 ? '' : '+');
        }, 30);
    }


    const html = document.documentElement;
    const savedTheme = localStorage.getItem('theme') || 'light';
    html.setAttribute('data-theme', savedTheme);
    
    function updateThemeUI(theme) {
        // Update all theme icons (both desktop and mobile)
        const themeIcons = document.querySelectorAll('#theme-icon, #theme-icon-desktop, #theme-icon-option, .theme-toggle i');
        themeIcons.forEach(icon => {
            icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        });
        
        // Update theme labels
        const themeLabels = document.querySelectorAll('.theme-label');
        themeLabels.forEach(label => {
            label.textContent = theme === 'dark' ? 'Mode clair' : 'Mode sombre';
        });
        
        // Update options menu label
        const optionLabel = document.querySelector('#themeToggleOption span');
        if (optionLabel) {
            optionLabel.textContent = theme === 'dark' ? 'Mode clair' : 'Mode sombre';
        }
    }
    
    function initThemeToggle() {
        const themeToggles = document.querySelectorAll('.theme-toggle, #themeToggleOption');
        
        // Remove existing listeners by cloning
        themeToggles.forEach(toggle => {
            const newToggle = toggle.cloneNode(true);
            toggle.parentNode.replaceChild(newToggle, toggle);
        });
        
        // Re-select after cloning
        const newThemeToggles = document.querySelectorAll('.theme-toggle, #themeToggleOption');
        
        // Add click event to all theme toggle buttons
        newThemeToggles.forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.stopPropagation();
                const currentTheme = html.getAttribute('data-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                html.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);
                updateThemeUI(newTheme);
            });
        });
        
        // Update UI with current theme
        updateThemeUI(savedTheme);
    }
    
    initThemeToggle();
    
    document.addEventListener('templateLoaded', function(e) {
        if (e.detail.placeholder === 'header-placeholder') {
            setTimeout(initThemeToggle, 50);
        }
    });
    
    function initOptionsMenu() {
        setTimeout(() => {
            const optionsToggle = document.getElementById('optionsToggle');
            const navOptions = document.querySelector('.nav-options');
            const optionsDropdown = document.getElementById('optionsDropdown');
            
            if (!optionsToggle || !navOptions) {
                return;
            }
            
            const newToggle = optionsToggle.cloneNode(true);
            optionsToggle.parentNode.replaceChild(newToggle, optionsToggle);
            
            newToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const currentNavOptions = document.querySelector('.nav-options');
                if (currentNavOptions) {
                    currentNavOptions.classList.toggle('active');
                }
            });
            
            if (optionsDropdown) {
                optionsDropdown.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }
            
            document.addEventListener('click', function(e) {
                const currentNavOptions = document.querySelector('.nav-options');
                if (currentNavOptions && !currentNavOptions.contains(e.target)) {
                    currentNavOptions.classList.remove('active');
                }
            });
            
            document.addEventListener('keydown', function(e) {
                const currentNavOptions = document.querySelector('.nav-options');
                if (e.key === 'Escape' && currentNavOptions && currentNavOptions.classList.contains('active')) {
                    currentNavOptions.classList.remove('active');
                }
            });
        }, 100);
    }
    
    initOptionsMenu();
    let menuInitialized = false;
    
    function initMobileMenu() {
        const menuToggle = document.querySelector('.menu-toggle');
        const navMenu = document.querySelector('.nav-menu');
        const navRight = document.querySelector('.nav-right');
        
        if (!menuToggle || !navMenu) {
            return false;
        }
        
        // Si déjà initialisé, ne pas réinitialiser
        if (menuToggle.dataset.initialized === 'true') {
            return true;
        }
        
        // Marquer comme initialisé
        menuToggle.dataset.initialized = 'true';
        
        // Créer le backdrop s'il n'existe pas
        let navBackdrop = document.querySelector('.nav-backdrop');
        if (!navBackdrop) {
            navBackdrop = document.createElement('div');
            navBackdrop.className = 'nav-backdrop';
            document.body.appendChild(navBackdrop);
        }
        
        const navLinks = document.querySelectorAll('.nav-menu a');
        
        // Move Contact and Options to mobile menu when menu opens
        if (navRight && navMenu) {
            const contactBtn = navRight.querySelector('.btn-contact');
            const optionsMenu = navRight.querySelector('.nav-options');
            
            const moveToMobileMenu = () => {
                if (window.innerWidth <= 768) {
                    // Move Contact button
                    if (contactBtn && !navMenu.contains(contactBtn)) {
                        const contactLi = document.createElement('li');
                        contactLi.appendChild(contactBtn);
                        navMenu.appendChild(contactLi);
                    }
                    // Move Options menu
                    if (optionsMenu && !navMenu.contains(optionsMenu)) {
                        // Expand options dropdown in mobile
                        optionsMenu.classList.add('active');
                        // Ensure dropdown is visible
                        const dropdown = optionsMenu.querySelector('.options-dropdown');
                        if (dropdown) {
                            dropdown.style.display = 'flex';
                            dropdown.style.opacity = '1';
                            dropdown.style.visibility = 'visible';
                            dropdown.style.pointerEvents = 'auto';
                        }
                        navMenu.appendChild(optionsMenu);
                    }
                }
            };
            
            const moveBackToRight = () => {
                if (window.innerWidth > 768 && navRight) {
                    // Move Contact back
                    if (contactBtn) {
                        const contactLi = contactBtn.parentElement;
                        if (contactLi && navMenu.contains(contactLi)) {
                            navRight.insertBefore(contactBtn, navRight.firstChild);
                            contactLi.remove();
                        }
                    }
                    // Move Options back
                    if (optionsMenu && navMenu.contains(optionsMenu)) {
                        // Collapse options dropdown when moving back
                        optionsMenu.classList.remove('active');
                        navRight.appendChild(optionsMenu);
                    }
                }
            };
            
            // Utiliser une seule instance de l'event listener resize
            if (!window.menuResizeHandler) {
                window.menuResizeHandler = () => {
                    if (window.innerWidth <= 768) {
                        moveToMobileMenu();
                    } else {
                        moveBackToRight();
                    }
                };
                window.addEventListener('resize', window.menuResizeHandler);
            }
            
            // Initial check
            if (window.innerWidth <= 768) {
                moveToMobileMenu();
            }
        }

        if (menuToggle) {
            menuToggle.setAttribute('aria-expanded', 'false');
        }

        const toggleMenu = (shouldOpen) => {
            const currentNavMenu = document.querySelector('.nav-menu');
            const currentMenuToggle = document.querySelector('.menu-toggle');
            if (!currentNavMenu || !currentMenuToggle) return;
            const isOpen = shouldOpen ?? !currentNavMenu.classList.contains('active');
            currentNavMenu.classList.toggle('active', isOpen);
            currentMenuToggle.classList.toggle('active', isOpen);
            navBackdrop.classList.toggle('active', isOpen);
            document.body.classList.toggle('nav-open', isOpen);
            currentMenuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        };

        if (menuToggle && navMenu) {
            menuToggle.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                toggleMenu();
            });
        }

        navBackdrop.addEventListener('click', () => toggleMenu(false));

        // Gérer les dropdowns sur mobile
        const dropdownLinks = document.querySelectorAll('.nav-link-dropdown');
        dropdownLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (window.innerWidth <= 768) {
                    e.preventDefault();
                    e.stopPropagation();
                    const dropdown = this.closest('.nav-dropdown');
                    if (dropdown) {
                        // Fermer les autres dropdowns
                        document.querySelectorAll('.nav-dropdown').forEach(d => {
                            if (d !== dropdown) {
                                d.classList.remove('active');
                            }
                        });
                        // Toggle le dropdown actuel
                        dropdown.classList.toggle('active');
                    }
                }
            });
        });
        
        navLinks.forEach(link => {
            // Ne pas fermer le menu si c'est un dropdown toggle ou un lien dans un mega-menu
            if (!link.classList.contains('nav-link-dropdown') && !link.closest('.mega-menu')) {
                link.addEventListener('click', () => {
                    // Fermer le menu mobile après un court délai pour permettre la navigation
                    setTimeout(() => toggleMenu(false), 100);
                });
            }
        });
        
        // Fermer les dropdowns quand on clique sur un lien du mega-menu
        const megaMenuLinks = document.querySelectorAll('.mega-menu-item, .mega-menu a');
        megaMenuLinks.forEach(link => {
            link.addEventListener('click', function() {
                // Fermer tous les dropdowns
                document.querySelectorAll('.nav-dropdown').forEach(d => {
                    d.classList.remove('active');
                });
                // Fermer le menu mobile
                toggleMenu(false);
            });
        });
        
        menuInitialized = true;
        return true;
    }
    
    // Initialiser le menu immédiatement
    initMobileMenu();
    
    // Réinitialiser après chargement du template header
    document.addEventListener('templateLoaded', function(e) {
        if (e.detail.placeholder === 'header-placeholder') {
            setTimeout(() => {
                initMobileMenu();
            }, 100);
        }
    });

    function initScrollProgress() {
        if (document.querySelector('.scroll-progress')) {
            return;
        }
        
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
            const scrolled = window.pageYOffset || window.scrollY || 0;
            const progress = windowHeight > 0 ? (scrolled / windowHeight) * 100 : 0;
            progressBar.style.width = Math.min(100, Math.max(0, progress)) + '%';
        }
        
        window.addEventListener('scroll', updateProgress, { passive: true });
        updateProgress();
    }
    
    initScrollProgress();
    
    function initNavbarScroll() {
        const navbar = document.getElementById('navbar');
        const topBar = document.querySelector('.top-bar');
        
        if (!navbar) {
            setTimeout(initNavbarScroll, 100);
            return;
        }
        
        let hasScrolled = false;
        
        const handleFirstScroll = () => {
            if (!hasScrolled && window.scrollY > 0) {
                hasScrolled = true;
                navbar.classList.add('scrolled');
                if (topBar) {
                    topBar.style.transform = 'translateY(-100%)';
                    topBar.style.opacity = '0';
                    topBar.style.pointerEvents = 'none';
                }
            } else if (hasScrolled && window.scrollY === 0) {
                // Si on revient tout en haut, on remet tout
                hasScrolled = false;
                navbar.classList.remove('scrolled');
                if (topBar) {
                    topBar.style.transform = '';
                    topBar.style.opacity = '';
                    topBar.style.pointerEvents = '';
                }
            }
        };
        
        // Écouter le scroll - dès le premier pixel
        window.addEventListener('scroll', handleFirstScroll, { passive: true });
        
        // Vérifier au chargement si on est déjà scrollé
        if (window.scrollY > 0) {
            handleFirstScroll();
        }
    }
    
    // Initialiser immédiatement
    initNavbarScroll();
    
    // Écouter l'événement de chargement des templates
    document.addEventListener('templateLoaded', function(e) {
        if (e.detail.placeholder === 'header-placeholder') {
            // Réinitialiser après le chargement du header
            setTimeout(initNavbarScroll, 50);
        }
    });

    const megaMenuItems = document.querySelectorAll('.mega-menu-item');
    const navDropdowns = document.querySelectorAll('.nav-dropdown');
    
    megaMenuItems.forEach(item => {
        item.addEventListener('click', function() {
            // Fermer tous les mega menus en ajoutant une classe
            navDropdowns.forEach(dropdown => {
                const megaMenu = dropdown.querySelector('.mega-menu');
                if (megaMenu) {
                    megaMenu.classList.add('closed');
                }
            });
        });
    });
    
    // Réinitialiser la classe closed au hover pour permettre la réouverture
    navDropdowns.forEach(dropdown => {
        dropdown.addEventListener('mouseenter', function() {
            const megaMenu = this.querySelector('.mega-menu');
            if (megaMenu) {
                megaMenu.classList.remove('closed');
            }
        });
    });
    
    // Réinitialiser aussi quand on survole directement le mega menu
    document.querySelectorAll('.mega-menu').forEach(megaMenu => {
        megaMenu.addEventListener('mouseenter', function() {
            this.classList.remove('closed');
        });
    });

    

    // Active links on scroll
    function updateActiveNavLink() {
        const sections = document.querySelectorAll('section[id]');
        const scrollPosition = window.scrollY + 100;

        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.offsetHeight;
            const sectionId = section.getAttribute('id');
            
            if (scrollPosition >= sectionTop && scrollPosition < sectionTop + sectionHeight) {
                document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
                const activeLink = document.querySelector(`.nav-link[href*="${sectionId}"]`);
                if (activeLink && !activeLink.closest('.nav-dropdown')) {
                    activeLink.classList.add('active');
                }
            }
        });
    }

    window.addEventListener('scroll', updateActiveNavLink);
    updateActiveNavLink();

    // Smooth scroll
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href === '#' || href === '') return;
            
            e.preventDefault();
            const target = document.getElementById(href.substring(1));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    const bentoCards = document.querySelectorAll('.bento-card');
    if (bentoCards.length > 0) {
        const bentoObserver = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    bentoObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.2, rootMargin: '0px 0px -100px 0px' });

        bentoCards.forEach(card => bentoObserver.observe(card));
    }

    // Process Steps - Fall from sky
    const processSteps = document.querySelectorAll('.process-step');
    if (processSteps.length > 0) {
        const processObserver = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fall-in');
                    processObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -80px 0px' });

        processSteps.forEach(step => processObserver.observe(step));
    }

    // Service Cards - Staggered animation
    const serviceCards = document.querySelectorAll('.service-card');
    if (serviceCards.length > 0) {
        const serviceObserver = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-in');
                    serviceObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -80px 0px' });

        serviceCards.forEach(card => serviceObserver.observe(card));
    }

    // Section Headers - Fade in from top
    const sectionHeaders = document.querySelectorAll('.solutions .section-header, .solutions .section-title');
    if (sectionHeaders.length > 0) {
        const headerObserver = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in-down');
                    headerObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.2, rootMargin: '0px 0px -50px 0px' });

        sectionHeaders.forEach(header => headerObserver.observe(header));
    }

    // Generic animations (About, Tarifs)
    const animatedElements = document.querySelectorAll('.about-card, .tarif-card');
    if (animatedElements.length > 0) {
        const scrollObserver = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

        animatedElements.forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
            scrollObserver.observe(el);
        });
    }


    // ========================================
    // COOKIE BANNER (RGPD)
    // ========================================
    const cookieBanner = document.getElementById('cookieBanner');
    const cookieModal = document.getElementById('cookieModal');
    const cookieAccept = document.getElementById('cookieAccept');
    const cookiePreferencesBtn = document.getElementById('cookiePreferences');
    const cookieModalClose = document.getElementById('cookieModalClose');
    const cookieSavePreferences = document.getElementById('cookieSavePreferences');
    const cookieRejectAll = document.getElementById('cookieRejectAll');
    const cookieAnalyticsCheckbox = document.getElementById('cookieAnalytics');

    if (cookieBanner) {
        const cookieConsent = localStorage.getItem('cookieConsent');

        if (!cookieConsent) {
            cookieBanner.classList.add('visible');
        }

        if (cookieAccept) {
            cookieAccept.addEventListener('click', () => {
                localStorage.setItem('cookieConsent', 'all');
                localStorage.setItem('cookieAnalytics', 'true');
                cookieBanner.classList.remove('visible');
            });
        }

        if (cookiePreferencesBtn && cookieModal) {
            cookiePreferencesBtn.addEventListener('click', () => cookieModal.classList.add('active'));
        }

        const cookieSettingsFooter = document.getElementById('cookieSettingsFooter');
        if (cookieSettingsFooter && cookieModal) {
            cookieSettingsFooter.addEventListener('click', e => {
                e.preventDefault();
                cookieModal.classList.add('active');
            });
        }

        if (cookieModalClose && cookieModal) {
            cookieModalClose.addEventListener('click', () => cookieModal.classList.remove('active'));
        }

        if (cookieModal) {
            cookieModal.addEventListener('click', e => {
                if (e.target === cookieModal) cookieModal.classList.remove('active');
            });
        }

        if (cookieSavePreferences && cookieAnalyticsCheckbox) {
            cookieSavePreferences.addEventListener('click', () => {
                const analytics = cookieAnalyticsCheckbox.checked;
                localStorage.setItem('cookieConsent', 'custom');
                localStorage.setItem('cookieAnalytics', analytics.toString());
                if (cookieModal) cookieModal.classList.remove('active');
                cookieBanner.classList.remove('visible');
            });
        }

        if (cookieRejectAll) {
            cookieRejectAll.addEventListener('click', () => {
                localStorage.setItem('cookieConsent', 'essential');
                localStorage.setItem('cookieAnalytics', 'false');
                if (cookieModal) cookieModal.classList.remove('active');
                cookieBanner.classList.remove('visible');
            });
        }
    }

    const contactForm = document.getElementById('contactForm');
    const formMessage = document.getElementById('formMessage');

    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(contactForm);
            formMessage.textContent = 'Envoi en cours...';
            formMessage.className = 'form-message';
            
            // Chemin du formulaire de contact (relatif à la racine)
            const contactUrl = 'contact.php';
            
            fetch(contactUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    formMessage.textContent = data.message;
                    formMessage.className = 'form-message success';
                    contactForm.reset();
                    // SEA / GA4 : conversion "demande de devis"
                    if (typeof gtag === 'function') {
                        gtag('event', 'generate_lead', { method: 'contact_form' });
                    }
                    if (typeof dataLayer !== 'undefined' && dataLayer.push) {
                        dataLayer.push({ event: 'form_submit_success', cta: 'contact_form' });
                    }
                } else {
                    formMessage.textContent = data.message;
                    formMessage.className = 'form-message error';
                }
            })
            .catch(error => {
                formMessage.textContent = 'Erreur. Réessayez ou contactez-nous par email.';
                formMessage.className = 'form-message error';
            });
        });
    }

    const faqItems = document.querySelectorAll('.faq-item');
    
    if (faqItems.length > 0) {
        faqItems.forEach(item => {
            const question = item.querySelector('.faq-question');
            
            if (question) {
                question.addEventListener('click', () => {
                    // Close other items
                    const wasActive = item.classList.contains('active');
                    
                    faqItems.forEach(otherItem => {
                        if (otherItem !== item) {
                            otherItem.classList.remove('active');
                        }
                    });
                    
                    // Toggle current item
                    if (wasActive) {
                        item.classList.remove('active');
                    } else {
                        item.classList.add('active');
                    }
                });
            }
        });
    }
});

/* ──────────────────────────────────────────────────────────────
   Reveal au scroll — animations d'entrée (2026)
   La classe .reveal est posée par JS : si ce script ne tourne pas,
   le contenu reste visible (pas de contenu caché).
   ────────────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
    if (!('IntersectionObserver' in window)) return;
    var selector = '.section-header, .story__step, .work-card, .trust-card, .contact-info-col, .contact-form-col, .client-card, .client-features';
    var els = Array.prototype.slice.call(document.querySelectorAll(selector));
    if (!els.length) return;

    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduce) return;

    els.forEach(function (el) { el.classList.add('reveal'); });
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('in-view');
                io.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -60px 0px' });
    els.forEach(function (el) { io.observe(el); });
});

/* ──────────────────────────────────────────────────────────────
   Parallax du hero — profondeur multi-couches au scroll (2026)
   Mouvement naturel, eased, coupe si prefers-reduced-motion.
   ────────────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
    var hero = document.querySelector('.hero-x');
    if (!hero) return;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    var bg = hero.querySelector('.hero-x__bg');
    var visual = hero.querySelector('.hero-x__visual');
    var copy = hero.querySelector('.hero-x__copy');
    var ticking = false;

    function update() {
        var y = window.pageYOffset || window.scrollY || 0;
        // On n'anime que tant que le hero est (presque) visible
        if (y <= window.innerHeight + 120) {
            if (bg)     bg.style.transform     = 'translate3d(0,' + (y * 0.34).toFixed(1) + 'px,0)';
            if (visual) visual.style.transform = 'translate3d(0,' + (y * -0.07).toFixed(1) + 'px,0)';
            if (copy)   copy.style.transform   = 'translate3d(0,' + (y * 0.10).toFixed(1) + 'px,0)';
        }
        ticking = false;
    }
    function onScroll() {
        if (!ticking) { ticking = true; requestAnimationFrame(update); }
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    update();
});

/* Parallax générique : éléments [data-parallax] (glows décoratifs des sections) */
document.addEventListener('DOMContentLoaded', function () {
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    var nodes = Array.prototype.slice.call(document.querySelectorAll('[data-parallax]'));
    if (!nodes.length) return;
    var items = nodes.map(function (el) { return { el: el, speed: parseFloat(el.getAttribute('data-parallax')) || 0.1 }; });
    var ticking = false;
    function update() {
        var vh = window.innerHeight;
        items.forEach(function (it) {
            var rect = it.el.getBoundingClientRect();
            if (rect.bottom < -200 || rect.top > vh + 200) return;
            var offset = (rect.top + rect.height / 2) - vh / 2;
            it.el.style.transform = 'translate3d(0,' + (offset * it.speed).toFixed(1) + 'px,0)';
        });
        ticking = false;
    }
    window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(update); } }, { passive: true });
    update();
});

document.addEventListener('DOMContentLoaded', function() {
    var carousel = document.querySelector('[data-carousel="work"]');
    if (!carousel) return;

    var track = carousel.querySelector('.work-grid');
    var cards = Array.prototype.slice.call(carousel.querySelectorAll('.work-card'));
    var prevButton = carousel.querySelector('.work-carousel__control--prev');
    var nextButton = carousel.querySelector('.work-carousel__control--next');
    var dots = carousel.querySelector('.work-carousel__dots');

    if (!track || !cards.length || !prevButton || !nextButton || !dots) return;

    var activeIndex = 0;

    function getCardStep() {
        if (cards.length < 2) return cards[0].offsetWidth;
        return cards[1].offsetLeft - cards[0].offsetLeft;
    }

    function getMaxIndex() {
        var step = getCardStep();
        if (!step) return 0;
        return Math.max(0, Math.ceil((track.scrollWidth - track.clientWidth) / step));
    }

    function buildDots() {
        var count = getMaxIndex() + 1;
        dots.innerHTML = '';
        for (var i = 0; i < count; i += 1) {
            var dot = document.createElement('button');
            dot.type = 'button';
            dot.className = 'work-carousel__dot';
            dot.setAttribute('aria-label', 'Aller a la realisation ' + (i + 1));
            dot.dataset.index = String(i);
            dots.appendChild(dot);
        }
    }

    function updateState() {
        var step = getCardStep();
        activeIndex = step ? Math.round(track.scrollLeft / step) : 0;
        var maxIndex = getMaxIndex();
        activeIndex = Math.max(0, Math.min(activeIndex, maxIndex));

        prevButton.disabled = activeIndex === 0;
        nextButton.disabled = activeIndex >= maxIndex;

        Array.prototype.forEach.call(dots.children, function(dot, index) {
            dot.classList.toggle('is-active', index === activeIndex);
            dot.setAttribute('aria-current', index === activeIndex ? 'true' : 'false');
        });
    }

    function goTo(index) {
        var maxIndex = getMaxIndex();
        var nextIndex = Math.max(0, Math.min(index, maxIndex));
        track.scrollTo({
            left: nextIndex * getCardStep(),
            behavior: 'smooth'
        });
    }

    prevButton.addEventListener('click', function() {
        goTo(activeIndex - 1);
    });

    nextButton.addEventListener('click', function() {
        goTo(activeIndex + 1);
    });

    dots.addEventListener('click', function(event) {
        var dot = event.target.closest('.work-carousel__dot');
        if (!dot) return;
        goTo(parseInt(dot.dataset.index, 10));
    });

    carousel.addEventListener('keydown', function(event) {
        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            goTo(activeIndex - 1);
        }
        if (event.key === 'ArrowRight') {
            event.preventDefault();
            goTo(activeIndex + 1);
        }
    });

    var scrollTimer = null;
    track.addEventListener('scroll', function() {
        if (scrollTimer) window.clearTimeout(scrollTimer);
        scrollTimer = window.setTimeout(updateState, 80);
    }, { passive: true });

    window.addEventListener('resize', function() {
        buildDots();
        goTo(activeIndex);
        window.setTimeout(updateState, 120);
    });

    buildDots();
    updateState();
});
