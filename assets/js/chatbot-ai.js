(function() {
    'use strict';
    
    let customerEmail = null;
    let customerName = null;
    let customerFirstName = null;
    let isDirectChat = false;
    let directChatTicketId = null;
    let chatPollInterval = null;
    let displayedMessageIds = new Set();
    let conversationStep = 'email';
    let isInitialized = false;
    
    function init() {
        const chatbotToggle = document.getElementById('chatbotToggle');
        const chatbotWindow = document.getElementById('chatbotWindow');
        const chatbotClose = document.getElementById('chatbotClose');
        const chatbotInput = document.getElementById('chatbotInput');
        const chatbotSend = document.getElementById('chatbotSend');
        const chatbotMessages = document.getElementById('chatbotMessages');

        if (!chatbotToggle || !chatbotWindow || !chatbotInput || !chatbotSend || !chatbotMessages) {
            return false;
        }
        
        if (isInitialized) {
            return true;
        }
        
        // Ouvrir le chatbot
        chatbotToggle.addEventListener('click', function() {
            openChatbot();
        });
        
        // Fermer le chatbot
        if (chatbotClose) {
            chatbotClose.addEventListener('click', function() {
                closeChatbot();
            });
        }
        
        // Envoyer un message
        chatbotSend.addEventListener('click', function(e) {
            e.preventDefault();
            sendMessage();
        });
        
        chatbotInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        
        isInitialized = true;
        return true;
    }
    
    // Initialisation immédiate
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            init();
        });
    } else {
        init();
    }
    
    // Réinitialiser après chargement du template footer
    document.addEventListener('templateLoaded', function(e) {
        if (e.detail.placeholder === 'footer-placeholder') {
            setTimeout(() => {
                if (!isInitialized) {
                    init();
                }
            }, 100);
        }
    });
    
    // Ouvrir le chatbot - NETTOYER L'HISTORIQUE
    function openChatbot() {
        const chatbotWindow = document.getElementById('chatbotWindow');
        const chatbotMessages = document.getElementById('chatbotMessages');
        const chatbotInput = document.getElementById('chatbotInput');
        
        if (!chatbotWindow || !chatbotMessages) return;
        
        // TOUJOURS nettoyer l'historique à l'ouverture
        chatbotMessages.innerHTML = '';
        displayedMessageIds.clear();
        
        // Réinitialiser l'état
        conversationStep = 'email';
        isDirectChat = false;
        directChatTicketId = null;
        
        // Arrêter le polling si actif
        if (chatPollInterval) {
            clearInterval(chatPollInterval);
            chatPollInterval = null;
        }
        
        // Ouvrir la fenêtre
        chatbotWindow.classList.add('active');
        
        // Vérifier si on a déjà les infos en localStorage
        const storedEmail = localStorage.getItem('customerEmail');
        const storedName = localStorage.getItem('customerName');
        const storedFirstName = localStorage.getItem('customerFirstName');
        
        if (storedEmail && storedName && storedFirstName) {
            customerEmail = storedEmail;
            customerName = storedName;
            customerFirstName = storedFirstName;
            conversationStep = 'ready';
            
            // Message de bienvenue personnalisé avec le prénom
            setTimeout(() => {
                addMessage(`👋 **Bonjour ${customerFirstName} !**\n\nRavi de vous revoir ! 😊\n\nComment puis-je vous aider aujourd'hui ?`, 'bot');
                showQuickActions();
            }, 300);
        } else {
            // Demander l'email
            setTimeout(() => {
                askForEmail();
            }, 300);
        }
        
        setTimeout(() => {
            if (chatbotInput) {
                chatbotInput.focus();
            }
        }, 500);
    }
    
    // Demander l'email
    function askForEmail() {
        const chatbotMessages = document.getElementById('chatbotMessages');
        if (!chatbotMessages) return;
        
        conversationStep = 'email';
        
        addMessage('👋 **Bonjour !**\n\nJe suis votre assistant virtuel Code4U. Pour commencer, j\'ai besoin de votre adresse email.\n\n📧 **Quelle est votre adresse email ?**', 'bot');
        
        const chatbotInput = document.getElementById('chatbotInput');
        if (chatbotInput) {
            chatbotInput.placeholder = 'Entrez votre adresse email...';
            chatbotInput.type = 'email';
        }
    }
    
    // Demander le prénom
    function askForName() {
        conversationStep = 'name';
        
        addMessage('✅ **Email enregistré !**\n\n📝 **Quel est votre prénom ?**', 'bot');
        
        const chatbotInput = document.getElementById('chatbotInput');
        if (chatbotInput) {
            chatbotInput.placeholder = 'Entrez votre prénom...';
            chatbotInput.type = 'text';
        }
    }
    
    // Prêt à discuter
    function readyToChat() {
        conversationStep = 'ready';
        
        // Extraire le prénom (premier mot du nom)
        customerFirstName = customerName.split(' ')[0];
        localStorage.setItem('customerFirstName', customerFirstName);
        
        addMessage(`✅ **Enchanté ${customerFirstName} !** 😊\n\nJe suis votre assistant IA. Je peux vous aider avec :\n\n• Informations sur nos services\n• Tarifs et devis\n• Prise de contact\n• Questions techniques\n\nComment puis-je vous aider aujourd'hui ?`, 'bot');
        
        showQuickActions();
        
        const chatbotInput = document.getElementById('chatbotInput');
        if (chatbotInput) {
            chatbotInput.placeholder = 'Tapez votre message...';
            chatbotInput.type = 'text';
        }
    }
    
    // Afficher les actions rapides
    function showQuickActions() {
        const chatbotMessages = document.getElementById('chatbotMessages');
        if (!chatbotMessages) return;
        
        const actionsDiv = document.createElement('div');
        actionsDiv.className = 'chatbot-actions';
        actionsDiv.innerHTML = `
            <div class="suggestions-title">💡 Actions rapides :</div>
            <div class="chatbot-suggestions">
                <button class="suggestion-btn" data-action="tarifs">
                    💰 Nos tarifs
                </button>
                <button class="suggestion-btn" data-action="services">
                    🚀 Nos services
                </button>
                <button class="suggestion-btn" data-action="devis">
                    📋 Demander un devis
                </button>
                <button class="suggestion-btn" data-action="contact">
                    📞 Nous contacter
                </button>
                <button class="suggestion-btn" data-action="conseiller">
                    👤 Parler à un conseiller
                </button>
            </div>
        `;
        
        chatbotMessages.appendChild(actionsDiv);
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
        
        // Ajouter les événements
        actionsDiv.querySelectorAll('.suggestion-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const action = this.getAttribute('data-action');
                handleQuickAction(action);
            });
        });
    }
    
    // Gérer les actions rapides
    function handleQuickAction(action) {
        const chatbotInput = document.getElementById('chatbotInput');
        
        switch(action) {
            case 'tarifs':
                if (chatbotInput) chatbotInput.value = 'Quels sont vos tarifs ?';
                sendMessage();
                break;
            case 'services':
                if (chatbotInput) chatbotInput.value = 'Quels services proposez-vous ?';
                sendMessage();
                break;
            case 'devis':
                if (chatbotInput) chatbotInput.value = 'Je souhaite un devis';
                sendMessage();
                break;
            case 'contact':
                if (chatbotInput) chatbotInput.value = 'Comment vous contacter ?';
                sendMessage();
                break;
            case 'conseiller':
                if (chatbotInput) chatbotInput.value = 'Je veux parler à un conseiller';
                sendMessage();
                break;
        }
    }
    
    // Ajouter un message
    function addMessage(text, type) {
        const chatbotMessages = document.getElementById('chatbotMessages');
        if (!chatbotMessages) return;
        
        const messageDiv = document.createElement('div');
        messageDiv.className = 'chatbot-message ' + (type === 'user' ? 'user-message' : 'bot-message');
        
        if (type === 'bot') {
            const avatar = document.createElement('div');
            avatar.className = 'message-avatar';
            avatar.innerHTML = '<i class="fas fa-robot"></i>';
            messageDiv.appendChild(avatar);
        }
        
        const content = document.createElement('div');
        content.className = 'message-content';
        const p = document.createElement('p');
        p.innerHTML = formatMessage(text);
        content.appendChild(p);
        messageDiv.appendChild(content);
        
        chatbotMessages.appendChild(messageDiv);
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
    }
    
    // Formater le message (markdown simple)
    function formatMessage(text) {
        return text
            .replace(/\n/g, '<br>')
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>');
    }
    
    // Envoyer un message
    async function sendMessage() {
        const chatbotInput = document.getElementById('chatbotInput');
        const message = chatbotInput?.value.trim();
        
        if (!message) return;
        
        // Gérer les étapes de collecte d'informations
        if (conversationStep === 'email') {
            const email = message.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (emailRegex.test(email)) {
                customerEmail = email;
                localStorage.setItem('customerEmail', email);
                addMessage(email, 'user');
                chatbotInput.value = '';
                askForName();
                return;
            } else {
                addMessage(email, 'user');
                chatbotInput.value = '';
                addMessage('❌ **Email invalide.**\n\nVeuillez entrer une adresse email valide.\n\nExemple : nom@exemple.com', 'bot');
                return;
            }
        }
        
        if (conversationStep === 'name') {
            const name = message.trim();
            if (name.length >= 2) {
                customerName = name;
                localStorage.setItem('customerName', name);
                addMessage(name, 'user');
                chatbotInput.value = '';
                readyToChat();
                return;
            } else {
                addMessage(name, 'user');
                chatbotInput.value = '';
                addMessage('❌ **Le prénom doit contenir au moins 2 caractères.**\n\nVeuillez réessayer.', 'bot');
                return;
            }
        }
        
        // Message normal
        addMessage(message, 'user');
        chatbotInput.value = '';
        
        // Si chat direct actif, envoyer au conseiller
        if (isDirectChat && directChatTicketId) {
            await sendToAdvisor(message);
            return;
        }
        
        // Traiter avec l'IA
        await processAIMessage(message);
    }
    
    // Traiter le message avec l'IA
    async function processAIMessage(message) {
        const lowerMsg = message.toLowerCase();
        
        // Détecter demande de conseiller
        const advisorKeywords = [
            'conseiller', 'humain', 'personne', 'parler à quelqu\'un', 'agent',
            'opérateur', 'chat direct', 'chat en direct', 'parler avec',
            'je veux parler', 'je souhaite parler', 'parler à un conseiller',
            'besoin d\'aide humaine', 'assistance humaine', 'support humain'
        ];
        
        const wantsAdvisor = advisorKeywords.some(keyword => lowerMsg.includes(keyword));
        
        if (wantsAdvisor) {
            await startDirectChat(message);
            return;
        }
        
        // Réponse IA intelligente
        const response = getAIResponse(message);
        setTimeout(() => {
            addMessage(response.text, 'bot');
            if (response.showActions) {
                setTimeout(() => showQuickActions(), 500);
            }
        }, 500);
    }
    
    // Générer une réponse IA complète
    function getAIResponse(message) {
        const lowerMsg = message.toLowerCase();
        
        // Tarifs
        if (lowerMsg.includes('tarif') || lowerMsg.includes('prix') || lowerMsg.includes('coût') || 
            lowerMsg.includes('combien') || lowerMsg.includes('montant')) {
            return {
                text: '💰 **Nos tarifs :**\n\n• **Site web vitrine** : à partir de 599€ (création) + 99€/an (hébergement)\n• **Site avec base de données** : à partir de 1 199€ (création) + 99€/an (hébergement)\n• **E-commerce** : à partir de 1 490€ (sur devis)\n• **Logiciel sur mesure** : sur devis personnalisé\n• **Maintenance & support** : à partir de 79€/mois\n\n💡 **Souhaitez-vous un devis personnalisé ?**',
                showActions: true
            };
        }
        
        // Services
        if (lowerMsg.includes('service') || lowerMsg.includes('ce que vous faites') || 
            lowerMsg.includes('propos') || lowerMsg.includes('offre')) {
            return {
                text: '🚀 **Nos services :**\n\n• **Développement Web** : Sites vitrines, e-commerce, applications web\n• **Logiciels Métier** : Applications Python sur mesure, automatisation, traitement de données\n• **Maintenance & Support** : Mises à jour, suivi technique\n\nQuel service vous intéresse le plus ?',
                showActions: true
            };
        }
        
        // Contact
        if (lowerMsg.includes('contact') || lowerMsg.includes('joindre') || 
            lowerMsg.includes('appeler') || lowerMsg.includes('téléphone') || 
            lowerMsg.includes('email') || lowerMsg.includes('mail')) {
            return {
                text: '📞 **Nous contacter :**\n\n• **Email** : contact@code4u.fr\n• **Téléphone** : 06 52 37 26 36\n• **Localisation** : Metz, Grand Est, France\n• **Horaires** : Lundi-Vendredi 9h-19h, Samedi 10h-18h\n\n💬 **Souhaitez-vous parler à un conseiller en direct ?**',
                showActions: true
            };
        }
        
        // Devis
        if (lowerMsg.includes('devis') || lowerMsg.includes('estimation') || 
            lowerMsg.includes('budget') || lowerMsg.includes('évaluation')) {
            return {
                text: '📋 **Demande de devis :**\n\nPour établir un devis précis et personnalisé, j\'ai besoin de connaître votre projet en détail.\n\nPouvez-vous me donner plus d\'informations sur :\n• Le type de projet (site web, logiciel, etc.)\n• Vos besoins spécifiques\n• Votre budget approximatif\n\n💬 **Ou préférez-vous parler directement à un conseiller ?**',
                showActions: true
            };
        }
        
        // Site web
        if (lowerMsg.includes('site web') || lowerMsg.includes('site internet') || 
            lowerMsg.includes('créer un site') || lowerMsg.includes('site vitrine') ||
            lowerMsg.includes('e-commerce') || lowerMsg.includes('boutique en ligne')) {
            return {
                text: '🌐 **Création de site web :**\n\nJe peux créer votre site web professionnel adapté à vos besoins :\n\n• **Site vitrine** : À partir de 599€\n• **Site avec BDD** : À partir de 1 199€\n• **Site e-commerce** : À partir de 1 490€ (sur devis)\n• **Optimisation SEO** : Référencement inclus\n• **Hébergement** : 99€/an\n\n💡 **Voulez-vous un devis personnalisé pour votre projet ?**',
                showActions: true
            };
        }
        
        // Logiciel
        if (lowerMsg.includes('logiciel') || lowerMsg.includes('application') || 
            lowerMsg.includes('python') || lowerMsg.includes('automatisation') ||
            lowerMsg.includes('programme')) {
            return {
                text: '💻 **Logiciels sur mesure :**\n\nJe développe des logiciels Python personnalisés pour votre entreprise :\n\n• **Automatisation de processus**\n• **Applications métier**\n• **Traitement de données**\n• **Intégrations API**\n• **Outils de productivité**\n\n💡 **Tarifs sur devis selon vos besoins spécifiques.**\n\nSouhaitez-vous discuter de votre projet ?',
                showActions: true
            };
        }
        
        // PC gamer / dépannage : ne plus proposés, rediriger vers nos services
        if (lowerMsg.includes('pc gamer') || lowerMsg.includes('montage pc') || 
            lowerMsg.includes('dépannage') || lowerMsg.includes('réparation') || 
            lowerMsg.includes('panne informatique')) {
            return {
                text: 'Nous nous concentrons désormais sur le **développement web** et les **logiciels sur mesure** (sites vitrines, e-commerce, automatisation Python).\n\nSouhaitez-vous un devis pour un site web ou un logiciel ?',
                showActions: true
            };
        }
        
        // Salutations
        if (lowerMsg.includes('bonjour') || lowerMsg.includes('salut') || 
            lowerMsg.includes('bonsoir') || lowerMsg.includes('hello') ||
            lowerMsg.includes('coucou')) {
            const greeting = customerFirstName ? `Bonjour ${customerFirstName} !` : 'Bonjour !';
            return {
                text: `${greeting} 😊\n\nComment puis-je vous aider aujourd'hui ?`,
                showActions: true
            };
        }
        
        // Remerciements
        if (lowerMsg.includes('merci') || lowerMsg.includes('remercie')) {
            return {
                text: 'De rien ! 😊\n\nN\'hésitez pas si vous avez d\'autres questions.\n\nY a-t-il autre chose avec lequel je peux vous aider ?',
                showActions: true
            };
        }
        
        // Au revoir
        if (lowerMsg.includes('au revoir') || lowerMsg.includes('bye') || 
            lowerMsg.includes('à bientôt') || lowerMsg.includes('bonne journée')) {
            return {
                text: `Au revoir ${customerFirstName || ''} ! 👋\n\nÀ bientôt et bonne journée !\n\nN\'hésitez pas à revenir si vous avez besoin d\'aide.`,
                showActions: false
            };
        }
        
        // Réponse par défaut intelligente
        return {
            text: `Je comprends votre question. 💡\n\nJe peux vous aider avec :\n• Informations sur nos services\n• Tarifs et devis personnalisés\n• Prise de contact\n• Questions techniques\n• Connexion avec un conseiller\n\n💬 **Souhaitez-vous parler à un conseiller pour plus d'informations ?**`,
            showActions: true
        };
    }
    
    // Démarrer le chat direct avec un conseiller
    async function startDirectChat(initialMessage) {
        if (!customerEmail || !customerName) {
            addMessage('❌ **Erreur :** Je n\'ai pas vos informations de contact.\n\nVeuillez fermer et rouvrir le chatbot pour entrer votre email et votre nom.', 'bot');
            return;
        }
        
        addMessage('🔄 **Connexion à un conseiller en cours...**\n\n⏳ Veuillez patienter quelques instants... 👤', 'bot');
        
        try {
            const ticketData = {
                customer_name: customerName,
                customer_email: customerEmail,
                customer_phone: '',
                subject: 'Chat en direct - Demande de conseiller',
                description: `Chat en direct via chatbot.\n\nMessage initial: "${initialMessage}"`,
                priority: 'high',
                category: 'Support',
                source: 'chatbot'
            };
            
            // Chemin absolu pour l'API
            const apiUrl = '/admin/api/chatbot-ticket.php';
            
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(ticketData)
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success) {
                directChatTicketId = result.data.ticket_id;
                isDirectChat = true;
                localStorage.setItem('directChatTicketId', directChatTicketId);
                
                // Envoyer le message initial au conseiller
                await sendToAdvisor(initialMessage);
                
                // Afficher le message de succès avec les infos du ticket
                const ticketNumber = result.data.ticket_number || 'N/A';
                const accessCode = result.data.access_code || '';
                
                let successMsg = `✅ **Connexion établie avec un conseiller !**\n\n`;
                successMsg += `📋 **Numéro de ticket :** ${ticketNumber}\n\n`;
                
                if (accessCode) {
                    successMsg += `🔐 **Code d'accès secret :** ${accessCode}\n\n`;
                }
                
                successMsg += `📧 **Un email de confirmation avec votre code d'accès a été envoyé à :** ${customerEmail}\n\n`;
                successMsg += `⚠️ **Important :** Si vous ne recevez pas l'email dans quelques minutes, vérifiez votre dossier **SPAM/COURRIER INDÉSIRABLE**. L'email provient de **noreply@code4u.fr**\n\n`;
                successMsg += `💬 **Le chat en direct est maintenant actif !**\n\nUn conseiller va vous répondre dans quelques instants. Vous pouvez continuer à écrire vos questions ici. ✨`;
                
                addMessage(successMsg, 'bot');
                
                showDirectChatIndicator();
                startChatPolling();
            } else {
                addMessage(`⚠️ **Erreur lors de la connexion :** ${result.message || 'Erreur inconnue'}\n\nVeuillez réessayer ou nous contacter par email : contact@code4u.fr`, 'bot');
            }
        } catch (error) {
            console.error('Error starting direct chat:', error);
            addMessage(`⚠️ **Erreur de connexion :** ${error.message}\n\nVeuillez nous contacter par email : contact@code4u.fr`, 'bot');
        }
    }
    
    // Envoyer un message au conseiller
    async function sendToAdvisor(message) {
        if (!directChatTicketId) return;
        
        try {
            const response = await fetch('/admin/api/chat.php?action=message', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    chat_id: directChatTicketId,
                    message: message,
                    sender_type: 'customer'
                })
            });
            
            const data = await response.json();
            if (!data.success) {
                console.error('Erreur envoi message:', data.message);
            }
        } catch (error) {
            console.error('Erreur:', error);
        }
    }
    
    // Polling pour les messages du conseiller
    function startChatPolling() {
        if (chatPollInterval) clearInterval(chatPollInterval);
        
        
        chatPollInterval = setInterval(async () => {
            if (!directChatTicketId || !isDirectChat) {
                clearInterval(chatPollInterval);
                return;
            }
            
            try {
                const response = await fetch(`/admin/api/chat.php?action=messages&id=${directChatTicketId}`);
                const data = await response.json();
                
                if (data.success && data.data && data.data.length > 0) {
                    // Récupérer les messages admin et system (messages de clôture)
                    const newMessages = data.data.filter(msg => 
                        (msg.sender_type === 'admin' || msg.sender_type === 'system') && 
                        !displayedMessageIds.has(msg.id)
                    );
                    
                    newMessages.forEach(msg => {
                        if (msg.sender_type === 'system') {
                            // Message système (clôture, etc.)
                            addMessage('ℹ️ **' + msg.message + '**', 'bot');
                        } else {
                            // Message du conseiller
                            addMessage('👤 **Conseiller :**\n\n' + msg.message, 'bot');
                        }
                        
                        displayedMessageIds.add(msg.id);
                    });
                }
            } catch (error) {
            }
        }, 2000);
    }
    
    // Afficher l'indicateur de chat direct
    function showDirectChatIndicator() {
        const chatbotMessages = document.getElementById('chatbotMessages');
        if (!chatbotMessages) return;
        
        // Vérifier si l'indicateur existe déjà
        const existingIndicator = document.getElementById('directChatIndicator');
        if (existingIndicator) return;
        
        const indicator = document.createElement('div');
        indicator.id = 'directChatIndicator';
        indicator.className = 'direct-chat-indicator';
        indicator.innerHTML = '<i class="fas fa-circle"></i> <span>Chat en direct actif - Un conseiller vous répondra ici</span>';
        
        chatbotMessages.appendChild(indicator);
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
    }
    
    // Fermer le chatbot
    function closeChatbot() {
        const chatbotWindow = document.getElementById('chatbotWindow');
        if (chatbotWindow) {
            chatbotWindow.classList.remove('active');
        }
        
        // Arrêter le polling
        if (chatPollInterval) {
            clearInterval(chatPollInterval);
            chatPollInterval = null;
        }
    }
})();
