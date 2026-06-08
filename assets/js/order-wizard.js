/**
 * order-wizard.js — Code4U V2 Order Wizard
 * Module pattern: all logic encapsulated, state managed explicitly.
 */

(function () {
    'use strict';

    /* ──────────────────────────────────────────────────────────
       STATE
    ────────────────────────────────────────────────────────── */
    const state = {
        currentStep: 1,
        totalSteps: 5,
        service: null,
        serviceName: '',
        serviceIcon: '',
        basePrice: 0,
        options: [],          // [{id, name, price}]
        optionsPrice: 0,
        deadline: 'standard',
        deadlineLabel: 'Standard',
        deadlineMultiplier: 1,
        description: '',
        totalPrice: 0,
        client: {
            firstname: '',
            lastname: '',
            email: '',
            phone: '',
            company: '',
            website: '',
            source: '',
            gdpr: false
        }
    };

    /* ──────────────────────────────────────────────────────────
       SERVICE DEFINITIONS
    ────────────────────────────────────────────────────────── */
    const services = {
        vitrine: {
            name: 'Site Vitrine',
            icon: 'fa-globe',
            basePrice: 599,
            options: [
                { id: 'pages',      name: 'Pages supplémentaires (+5 pages)',  desc: 'Ajoutez 5 pages à votre site',                    price: 150, icon: 'fa-copy' },
                { id: 'blog',       name: 'Blog intégré',                       desc: 'Système de blog avec gestion des articles',        price: 200, icon: 'fa-newspaper' },
                { id: 'animations', name: 'Animations avancées',                desc: 'Effets et transitions premium',                    price: 100, icon: 'fa-wand-magic-sparkles' },
                { id: 'multilang',  name: 'Multi-langues (FR/EN)',              desc: 'Version anglaise de votre site',                   price: 300, icon: 'fa-language' },
                { id: 'booking',    name: 'Formulaire de réservation',          desc: 'Agenda en ligne avec prise de RDV',                price: 150, icon: 'fa-calendar-check' },
                { id: 'gallery',    name: 'Galerie photos professionnelle',     desc: 'Galerie avec lightbox et filtres',                 price: 100, icon: 'fa-images' }
            ]
        },
        ecommerce: {
            name: 'E-commerce',
            icon: 'fa-shopping-bag',
            basePrice: 1490,
            options: [
                { id: 'products500',  name: "Jusqu'à 500 produits",            desc: 'Catalogue étendu (vs 100 standard)',               price: 300, icon: 'fa-boxes-stacked' },
                { id: 'multipay',     name: 'Paiements multiples',             desc: 'PayPal, virement, CB supplémentaires',             price: 200, icon: 'fa-credit-card' },
                { id: 'loyalty',      name: 'Programme fidélité',              desc: 'Points, récompenses, coupons',                     price: 400, icon: 'fa-award' },
                { id: 'multicurrency',name: 'Multi-devises',                   desc: 'EUR, USD, GBP automatiques',                       price: 300, icon: 'fa-coins' },
                { id: 'pwa',          name: 'App mobile PWA',                  desc: 'Votre boutique installable sur mobile',            price: 500, icon: 'fa-mobile-screen' },
                { id: 'reviews',      name: 'Module avis clients',             desc: 'Système de notation et commentaires',              price: 150, icon: 'fa-star' }
            ]
        },
        webapp: {
            name: 'Application Web',
            icon: 'fa-code',
            basePrice: 1199,
            options: [
                { id: 'auth',          name: 'Authentification utilisateurs',  desc: 'Inscription, connexion, profils',                  price: 200, icon: 'fa-user-lock' },
                { id: 'roles',         name: 'Rôles et permissions',           desc: 'Gestion fine des accès par rôle',                  price: 300, icon: 'fa-shield-halved' },
                { id: 'api',           name: 'API REST complète',               desc: 'Endpoints documentés, tokens JWT',                 price: 400, icon: 'fa-plug' },
                { id: 'analytics',     name: 'Tableau de bord analytics',      desc: 'Graphiques, statistiques, KPIs',                   price: 350, icon: 'fa-chart-line' },
                { id: 'notifications', name: 'Notifications email/SMS',        desc: 'Alertes automatisées par événement',               price: 250, icon: 'fa-bell' },
                { id: 'export',        name: 'Export données (PDF/Excel)',      desc: 'Génération de rapports automatiques',              price: 200, icon: 'fa-file-export' }
            ]
        },
        logiciel: {
            name: 'Logiciel / Automatisation',
            icon: 'fa-robot',
            basePrice: 499,
            options: [
                { id: 'gui',    name: 'Interface graphique (GUI)',              desc: 'Interface utilisateur avec Tkinter/PyQt',          price: 300, icon: 'fa-desktop' },
                { id: 'db',     name: 'Connexion base de données',             desc: 'MySQL, PostgreSQL, SQLite',                        price: 200, icon: 'fa-database' },
                { id: 'excel',  name: 'Traitement fichiers Excel/CSV',         desc: 'Lecture, transformation, exports',                  price: 150, icon: 'fa-file-excel' },
                { id: 'email',  name: 'Envoi emails automatiques',             desc: 'Notifications et rapports par email',              price: 100, icon: 'fa-envelope' },
                { id: 'pdf',    name: 'Rapport PDF automatique',               desc: 'Génération de PDF avec données',                   price: 200, icon: 'fa-file-pdf' },
                { id: 'deploy', name: 'Déploiement serveur',                   desc: 'Mise en production sur VPS/cloud',                 price: 250, icon: 'fa-cloud-upload-alt' }
            ]
        }
    };

    const deadlines = {
        standard:    { label: 'Standard',        multiplier: 1.0, extra: 'inclus' },
        accelerated: { label: 'Accéléré (+30%)', multiplier: 1.3, extra: '+30%'   },
        urgent:      { label: 'Urgent (+50%)',   multiplier: 1.5, extra: '+50%'   }
    };

    /* ──────────────────────────────────────────────────────────
       HELPERS
    ────────────────────────────────────────────────────────── */

    /**
     * Format a number as French price string: "1 490 €"
     */
    function formatPrice(n) {
        return n.toLocaleString('fr-FR', { maximumFractionDigits: 0 }) + ' €';
    }

    /**
     * Calculate total: (basePrice + optionsPrice) × deadlineMultiplier
     */
    function calculateTotal() {
        const raw = (state.basePrice + state.optionsPrice) * state.deadlineMultiplier;
        state.totalPrice = Math.round(raw);
        return state.totalPrice;
    }

    /* ──────────────────────────────────────────────────────────
       UI UPDATES
    ────────────────────────────────────────────────────────── */

    function updateFloatingQuote() {
        const widget = document.getElementById('floatingQuote');
        const priceEl = document.getElementById('floatingPrice');
        if (!widget || !priceEl) return;

        if (state.currentStep === 2 && state.service !== null) {
            const total = calculateTotal();
            priceEl.textContent = formatPrice(total);
            widget.classList.add('visible');
        } else {
            widget.classList.remove('visible');
        }
    }

    function updateSidebar(step) {
        const indicators = document.querySelectorAll('.step-indicator');
        const connectors = document.querySelectorAll('.step-connector');

        indicators.forEach(function (el) {
            const s = parseInt(el.dataset.step, 10);
            el.classList.remove('active', 'completed');
            if (s < step) {
                el.classList.add('completed');
                // Update step num to checkmark icon
                const numEl = el.querySelector('.step-num');
                if (numEl) numEl.innerHTML = '<i class="fa-solid fa-check" style="font-size:0.8rem"></i>';
            } else if (s === step) {
                el.classList.add('active');
                // Restore number
                const numEl = el.querySelector('.step-num');
                if (numEl && s !== 5) numEl.textContent = String(s);
                if (numEl && s === 5) {
                    if (step === 5) {
                        numEl.innerHTML = '<i class="fa-solid fa-check" style="font-size:0.8rem"></i>';
                    } else {
                        numEl.textContent = '5';
                    }
                }
            } else {
                // pending — restore number if it was replaced with a check
                const numEl = el.querySelector('.step-num');
                if (numEl) numEl.textContent = String(s);
            }
        });

        // Connectors: done if the step before the connector is completed
        connectors.forEach(function (el, i) {
            // connectors are after step i+1
            if ((i + 1) < step) {
                el.classList.add('done');
            } else {
                el.classList.remove('done');
            }
        });
    }

    function updateMobileProgress(step) {
        const fill = document.getElementById('mobileProgressFill');
        const text = document.getElementById('mobileProgressText');
        if (fill) fill.style.width = ((step / state.totalSteps) * 100) + '%';
        if (text) text.textContent = 'Étape ' + step + '/' + state.totalSteps;
    }

    /* ──────────────────────────────────────────────────────────
       NAVIGATION
    ────────────────────────────────────────────────────────── */

    function goToStep(n) {
        // Hide all steps
        document.querySelectorAll('.wizard-step').forEach(function (el) {
            el.classList.remove('active');
        });

        // Show target step
        const target = document.getElementById('step-' + n);
        if (target) target.classList.add('active');

        state.currentStep = n;
        updateSidebar(n);
        updateMobileProgress(n);
        window.scrollTo({ top: 0, behavior: 'smooth' });
        updateFloatingQuote();
    }

    function nextStep() {
        if (!validateStep(state.currentStep)) return;

        // Build step 3 recap when coming from step 2
        if (state.currentStep === 2) {
            renderStep3();
        }

        if (state.currentStep < state.totalSteps) {
            goToStep(state.currentStep + 1);
        }
    }

    function prevStep() {
        if (state.currentStep > 1) {
            goToStep(state.currentStep - 1);
        }
    }

    /* ──────────────────────────────────────────────────────────
       STEP 1 — Service selection
    ────────────────────────────────────────────────────────── */

    function selectService(serviceId) {
        if (!services[serviceId]) return;

        const svc = services[serviceId];
        state.service    = serviceId;
        state.serviceName = svc.name;
        state.serviceIcon = svc.icon;
        state.basePrice   = svc.basePrice;

        // Reset options when service changes
        state.options      = [];
        state.optionsPrice = 0;

        // Visual update
        document.querySelectorAll('.service-card-select').forEach(function (card) {
            card.classList.toggle('selected', card.dataset.service === serviceId);
        });

        // Enable next button on step 1
        const nextBtn = document.getElementById('next-1');
        if (nextBtn) nextBtn.disabled = false;

        // Re-render options for step 2
        renderOptions(serviceId);

        updateFloatingQuote();
    }

    /* ──────────────────────────────────────────────────────────
       STEP 2 — Options
    ────────────────────────────────────────────────────────── */

    function renderOptions(serviceId) {
        const grid = document.getElementById('options-grid');
        if (!grid) return;

        const svcOptions = services[serviceId] ? services[serviceId].options : [];
        grid.innerHTML = '';

        svcOptions.forEach(function (opt) {
            const isSelected = state.options.some(function (o) { return o.id === opt.id; });
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'option-card' + (isSelected ? ' selected' : '');
            btn.dataset.optionId = opt.id;
            btn.innerHTML = [
                '<div class="oc-icon"><i class="fa-solid ' + opt.icon + '"></i></div>',
                '<div class="oc-info">',
                  '<div class="oc-name">' + escapeHtml(opt.name) + '</div>',
                  '<div class="oc-desc">' + escapeHtml(opt.desc) + '</div>',
                '</div>',
                '<div class="oc-price">+' + formatPrice(opt.price) + '</div>',
                '<div class="oc-checkbox">' + (isSelected ? '<i class="fa-solid fa-check"></i>' : '') + '</div>'
            ].join('');

            btn.addEventListener('click', function () {
                toggleOption(opt.id, opt.price, opt.name);
                // Toggle visual
                const wasSelected = btn.classList.contains('selected');
                btn.classList.toggle('selected', !wasSelected);
                const checkbox = btn.querySelector('.oc-checkbox');
                if (!wasSelected) {
                    checkbox.innerHTML = '<i class="fa-solid fa-check"></i>';
                } else {
                    checkbox.innerHTML = '';
                }
            });

            grid.appendChild(btn);
        });
    }

    function toggleOption(optionId, price, name) {
        const idx = state.options.findIndex(function (o) { return o.id === optionId; });
        if (idx === -1) {
            state.options.push({ id: optionId, name: name, price: price });
        } else {
            state.options.splice(idx, 1);
        }
        state.optionsPrice = state.options.reduce(function (sum, o) { return sum + o.price; }, 0);
        updateFloatingQuote();
    }

    /* ──────────────────────────────────────────────────────────
       STEP 2 — Deadline
    ────────────────────────────────────────────────────────── */

    function selectDeadline(type) {
        if (!deadlines[type]) return;

        const dl = deadlines[type];
        state.deadline           = type;
        state.deadlineMultiplier = dl.multiplier;
        state.deadlineLabel      = dl.label;

        document.querySelectorAll('.deadline-card').forEach(function (card) {
            card.classList.toggle('selected', card.dataset.deadline === type);
        });

        updateFloatingQuote();
    }

    /* ──────────────────────────────────────────────────────────
       STEP 3 — Recap render
    ────────────────────────────────────────────────────────── */

    function renderStep3() {
        const total = calculateTotal();

        // Service header
        const iconEl = document.getElementById('recapServiceIcon');
        if (iconEl) iconEl.innerHTML = '<i class="fa-solid ' + state.serviceIcon + '"></i>';

        const nameEl = document.getElementById('recapServiceName');
        if (nameEl) nameEl.textContent = state.serviceName;

        const baseEl = document.getElementById('recapServiceBase');
        if (baseEl) baseEl.textContent = 'Forfait de base : ' + formatPrice(state.basePrice);

        // Line items
        const lineItemsEl = document.getElementById('lineItems');
        if (lineItemsEl) {
            lineItemsEl.innerHTML = '';
            // Base line
            const baseLi = document.createElement('li');
            baseLi.className = 'line-item';
            baseLi.innerHTML = '<span class="line-item-name">Forfait ' + escapeHtml(state.serviceName) + '</span><span class="line-item-price">' + formatPrice(state.basePrice) + '</span>';
            lineItemsEl.appendChild(baseLi);

            // Options
            state.options.forEach(function (opt) {
                const li = document.createElement('li');
                li.className = 'line-item';
                li.innerHTML = '<span class="line-item-name">+ ' + escapeHtml(opt.name) + '</span><span class="line-item-price">' + formatPrice(opt.price) + '</span>';
                lineItemsEl.appendChild(li);
            });

            // Deadline surcharge
            if (state.deadlineMultiplier > 1) {
                const surcharge = Math.round((state.basePrice + state.optionsPrice) * (state.deadlineMultiplier - 1));
                const li = document.createElement('li');
                li.className = 'line-item';
                li.innerHTML = '<span class="line-item-name">Majoration ' + escapeHtml(state.deadlineLabel) + '</span><span class="line-item-price">+' + formatPrice(surcharge) + '</span>';
                lineItemsEl.appendChild(li);
            }
        }

        // Deadline label
        const dlLabel = document.getElementById('recapDeadlineLabel');
        if (dlLabel) dlLabel.textContent = state.deadlineLabel;

        // Quote card
        const qBase = document.getElementById('qBasePrice');
        if (qBase) qBase.textContent = formatPrice(state.basePrice);

        const qOpts = document.getElementById('qOptionsPrice');
        if (qOpts) qOpts.textContent = state.optionsPrice > 0 ? formatPrice(state.optionsPrice) : '0 €';

        const qDlExtra = document.getElementById('qDeadlineExtra');
        const qDlLine  = document.getElementById('qDeadlineLine');
        if (state.deadlineMultiplier > 1) {
            const surcharge = Math.round((state.basePrice + state.optionsPrice) * (state.deadlineMultiplier - 1));
            if (qDlExtra) qDlExtra.textContent = '+' + formatPrice(surcharge);
            if (qDlLine)  qDlLine.style.display = '';
        } else {
            if (qDlExtra) qDlExtra.textContent = 'inclus';
            if (qDlLine)  qDlLine.style.display = '';
        }

        const qSub = document.getElementById('qSubtotal');
        if (qSub) qSub.textContent = formatPrice(total);

        const qTotal = document.getElementById('qTotalPrice');
        if (qTotal) qTotal.textContent = formatPrice(total);
    }

    /* ──────────────────────────────────────────────────────────
       STEP 4 — Validation
    ────────────────────────────────────────────────────────── */

    function validateStep(step) {
        if (step === 1) {
            return state.service !== null;
        }

        if (step === 2 || step === 3) {
            return true;
        }

        if (step === 4) {
            let valid = true;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            function checkField(inputId, errId, condition) {
                const input = document.getElementById(inputId);
                const err   = document.getElementById(errId);
                if (!condition) {
                    if (input) input.classList.add('error');
                    if (err)   err.classList.add('visible');
                    valid = false;
                } else {
                    if (input) input.classList.remove('error');
                    if (err)   err.classList.remove('visible');
                }
            }

            const firstname = (document.getElementById('client-firstname') || {}).value || '';
            const lastname  = (document.getElementById('client-lastname')  || {}).value || '';
            const email     = (document.getElementById('client-email')     || {}).value || '';
            const phone     = (document.getElementById('client-phone')     || {}).value || '';

            checkField('client-firstname', 'err-firstname', firstname.trim().length > 0);
            checkField('client-lastname',  'err-lastname',  lastname.trim().length  > 0);
            checkField('client-email',     'err-email',     emailRegex.test(email.trim()));
            checkField('client-phone',     'err-phone',     phone.trim().length > 5);

            // GDPR
            const gdprErr = document.getElementById('err-gdpr');
            if (!state.client.gdpr) {
                if (gdprErr) gdprErr.classList.add('visible');
                valid = false;
            } else {
                if (gdprErr) gdprErr.classList.remove('visible');
            }

            return valid;
        }

        return true;
    }

    /* ──────────────────────────────────────────────────────────
       STEP 4 — Submit
    ────────────────────────────────────────────────────────── */

    function collectClientData() {
        state.client.firstname = (document.getElementById('client-firstname') || {}).value || '';
        state.client.lastname  = (document.getElementById('client-lastname')  || {}).value || '';
        state.client.email     = (document.getElementById('client-email')     || {}).value || '';
        state.client.phone     = (document.getElementById('client-phone')     || {}).value || '';
        state.client.company   = (document.getElementById('client-company')   || {}).value || '';
        state.client.website   = (document.getElementById('client-website')   || {}).value || '';
        state.client.source    = (document.getElementById('client-source')    || {}).value || '';
    }

    function generateReference() {
        return 'CODE4U-' + new Date().getFullYear() + '-' + Math.floor(1000 + Math.random() * 9000);
    }

    function fillConfirmationPage(ref) {
        const orderRefEl = document.getElementById('orderRef');
        if (orderRefEl) orderRefEl.textContent = ref;

        const confFirstname = document.getElementById('confFirstname');
        if (confFirstname) confFirstname.textContent = state.client.firstname || 'cher client';

        const confService = document.getElementById('confService');
        if (confService) confService.textContent = state.serviceName;

        const confOptions = document.getElementById('confOptions');
        if (confOptions) {
            confOptions.textContent = state.options.length > 0
                ? state.options.map(function (o) { return o.name; }).join(', ')
                : 'Aucune';
        }

        const confDeadline = document.getElementById('confDeadline');
        if (confDeadline) confDeadline.textContent = state.deadlineLabel;

        const confTotal = document.getElementById('confTotal');
        if (confTotal) confTotal.textContent = formatPrice(state.totalPrice);

        const confEmail = document.getElementById('confEmail');
        if (confEmail) confEmail.textContent = state.client.email;
    }

    function submitOrder() {
        collectClientData();
        if (!validateStep(4)) return;

        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Envoi en cours...';
        }

        const payload = {
            action:      'create',
            service:     state.service,
            serviceName: state.serviceName,
            basePrice:   state.basePrice,
            options:     state.options,
            optionsPrice:state.optionsPrice,
            deadline:    state.deadline,
            deadlineLabel: state.deadlineLabel,
            deadlineMultiplier: state.deadlineMultiplier,
            totalPrice:  state.totalPrice,
            description: state.description,
            client:      state.client,
            reference:   generateReference(),
            submittedAt: new Date().toISOString()
        };

        fetch('admin/api/orders.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function (res) {
            return res.text().then(function (txt) {
                var body;
                try { body = JSON.parse(txt); }
                catch (e) { throw new Error('Réponse inattendue du serveur. Réessayez ou écrivez à contact@code4u.fr.'); }
                if (!res.ok || !body.success) {
                    throw new Error(body.error || 'Impossible d enregistrer votre demande');
                }
                return body;
            });
        })
        .then(function (body) {
            const ref = body.reference;
            fillConfirmationPage(ref);
            goToStep(5);
        })
        .catch(function (error) {
            window.alert(error.message || 'Impossible d enregistrer votre demande. Merci de reessayer.');
        })
        .finally(function () {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Envoyer ma commande 🚀';
            }
        });
    }

    /* ──────────────────────────────────────────────────────────
       URL PARAMS — pre-select service from URL
    ────────────────────────────────────────────────────────── */

    function parseURLParams() {
        try {
            const params = new URLSearchParams(window.location.search);
            const svc = params.get('service');
            if (svc && services[svc]) {
                selectService(svc);
                // Skip to step 2 automatically
                goToStep(2);
            }
        } catch (e) {
            // URLSearchParams not supported or other error — silently ignore
        }
    }

    /* ──────────────────────────────────────────────────────────
       UTILITY
    ────────────────────────────────────────────────────────── */

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /* ──────────────────────────────────────────────────────────
       INIT
    ────────────────────────────────────────────────────────── */

    function init() {
        // ── Step 1: service cards
        document.querySelectorAll('.service-card-select').forEach(function (card) {
            card.addEventListener('click', function () {
                selectService(card.dataset.service);
            });
        });

        // ── Next buttons (all steps except submit)
        document.querySelectorAll('.btn-next').forEach(function (btn) {
            btn.addEventListener('click', function () {
                nextStep();
            });
        });

        // ── Prev buttons
        document.querySelectorAll('.btn-prev').forEach(function (btn) {
            btn.addEventListener('click', function () {
                prevStep();
            });
        });

        // ── Submit button
        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn) {
            submitBtn.addEventListener('click', function () {
                submitOrder();
            });
        }

        // ── Project description textarea
        const descArea = document.getElementById('project-description');
        if (descArea) {
            descArea.addEventListener('input', function () {
                state.description = descArea.value;
            });
        }

        // ── Deadline cards
        document.querySelectorAll('.deadline-card').forEach(function (card) {
            card.addEventListener('click', function () {
                selectDeadline(card.dataset.deadline);
            });
        });

        // ── GDPR toggle
        const gdprWrap = document.getElementById('gdprWrap');
        const gdprCheckbox = document.getElementById('gdprCheckbox');
        if (gdprWrap) {
            gdprWrap.addEventListener('click', function () {
                state.client.gdpr = !state.client.gdpr;
                if (gdprCheckbox) {
                    if (state.client.gdpr) {
                        gdprCheckbox.classList.add('checked');
                        gdprCheckbox.innerHTML = '<i class="fa-solid fa-check"></i>';
                    } else {
                        gdprCheckbox.classList.remove('checked');
                        gdprCheckbox.innerHTML = '';
                    }
                }
                // Hide GDPR error if now accepted
                if (state.client.gdpr) {
                    const gdprErr = document.getElementById('err-gdpr');
                    if (gdprErr) gdprErr.classList.remove('visible');
                }
            });
        }

        // ── Disable next button on step 1 until service selected
        const next1 = document.getElementById('next-1');
        if (next1) next1.disabled = true;

        // ── Render initial options (vitrine by default — will re-render on selection)
        renderOptions('vitrine');

        // ── Initial sidebar & progress state
        updateSidebar(1);
        updateMobileProgress(1);

        // ── Check URL params (may jump to step 2 and select a service)
        parseURLParams();
    }

    /* ── Bootstrap ──────────────────────────────────────────── */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
