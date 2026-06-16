(function() {
    'use strict';

    var isInitialized = false;
    var hasWelcomed = false;

    function init() {
        var chatbotToggle = document.getElementById('chatbotToggle');
        var chatbotWindow = document.getElementById('chatbotWindow');
        var chatbotClose = document.getElementById('chatbotClose');
        var chatbotInput = document.getElementById('chatbotInput');
        var chatbotSend = document.getElementById('chatbotSend');
        var chatbotMessages = document.getElementById('chatbotMessages');

        if (!chatbotToggle || !chatbotWindow || !chatbotInput || !chatbotSend || !chatbotMessages) {
            return false;
        }

        if (isInitialized) {
            return true;
        }

        chatbotToggle.addEventListener('click', openChatbot);

        chatbotClose?.addEventListener('click', closeChatbot);

        chatbotSend.addEventListener('click', function(event) {
            event.preventDefault();
            sendMessage();
        });

        chatbotInput.addEventListener('keydown', function(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendMessage();
            }
        });

        isInitialized = true;
        return true;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    document.addEventListener('templateLoaded', function(event) {
        if (event.detail.placeholder === 'footer-placeholder') {
            setTimeout(init, 100);
        }
    });

    function openChatbot() {
        var chatbotWindow = document.getElementById('chatbotWindow');
        var chatbotInput = document.getElementById('chatbotInput');

        if (!chatbotWindow) return;

        chatbotWindow.classList.add('active');

        if (!hasWelcomed) {
            resetMessages();
            addMessage(
                "Bonjour, je suis le chatbot Code4U. Posez-moi votre question sur les services, les tarifs, le deroulement d'un projet ou l'espace client.",
                'bot'
            );
            hasWelcomed = true;
        }

        if (chatbotInput) {
            chatbotInput.type = 'text';
            chatbotInput.placeholder = 'Tapez votre message...';
            setTimeout(function() {
                chatbotInput.focus();
            }, 120);
        }
    }

    function closeChatbot() {
        var chatbotWindow = document.getElementById('chatbotWindow');
        chatbotWindow?.classList.remove('active');
    }

    function resetMessages() {
        var chatbotMessages = document.getElementById('chatbotMessages');
        if (chatbotMessages) {
            chatbotMessages.innerHTML = '';
        }
    }

    function sendMessage() {
        var chatbotInput = document.getElementById('chatbotInput');
        var message = chatbotInput?.value.trim();

        if (!message) return;

        addMessage(message, 'user');
        chatbotInput.value = '';
        chatbotInput.disabled = true;

        showTyping();

        window.setTimeout(function() {
            removeTyping();
            addMessage(getChatbotResponse(message), 'bot');
            chatbotInput.disabled = false;
            chatbotInput.focus();
        }, 350);
    }

    function addMessage(text, type) {
        var chatbotMessages = document.getElementById('chatbotMessages');
        if (!chatbotMessages) return;

        var messageDiv = document.createElement('div');
        messageDiv.className = 'chatbot-message ' + (type === 'user' ? 'user-message' : 'bot-message');

        if (type === 'bot') {
            var avatar = document.createElement('div');
            avatar.className = 'message-avatar';
            avatar.innerHTML = '<i class="fas fa-robot" aria-hidden="true"></i>';
            messageDiv.appendChild(avatar);
        }

        var content = document.createElement('div');
        content.className = 'message-content';
        var paragraph = document.createElement('p');
        paragraph.innerHTML = formatMessage(text);
        content.appendChild(paragraph);
        messageDiv.appendChild(content);

        chatbotMessages.appendChild(messageDiv);
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
    }

    function showTyping() {
        var chatbotMessages = document.getElementById('chatbotMessages');
        if (!chatbotMessages || document.getElementById('chatbotTyping')) return;

        var typing = document.createElement('div');
        typing.id = 'chatbotTyping';
        typing.className = 'chatbot-message bot-message';
        typing.innerHTML = '<div class="message-avatar"><i class="fas fa-robot" aria-hidden="true"></i></div><div class="message-content"><p>Je reflechis...</p></div>';
        chatbotMessages.appendChild(typing);
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
    }

    function removeTyping() {
        document.getElementById('chatbotTyping')?.remove();
    }

    function getChatbotResponse(message) {
        var text = normalize(message);

        if (hasAny(text, ['bonjour', 'salut', 'hello', 'bonsoir', 'coucou'])) {
            return "Bonjour ! Je peux vous renseigner sur Code4U, les types de projets, les prix indicatifs et les prochaines etapes.";
        }

        if (hasAny(text, ['tarif', 'prix', 'combien', 'cout', 'coût', 'budget'])) {
            return "**Tarifs indicatifs Code4U :**\n\n- Site vitrine : a partir de 599 euros\n- Site avec base de donnees : a partir de 1 199 euros\n- E-commerce : a partir de 1 490 euros\n- Logiciel sur mesure : sur devis\n- Maintenance/support : a partir de 79 euros/mois\n\nPour un prix exact, il faut decrire le besoin, les pages, les fonctionnalites et les integrations.";
        }

        if (hasAny(text, ['service', 'services', 'faites', 'propose', 'prestation', 'offre'])) {
            return "**Code4U propose principalement :**\n\n- Sites vitrines et pages de vente\n- Sites e-commerce\n- Applications web et espaces clients\n- Logiciels metier sur mesure\n- Automatisations et connexions ERP/facturation\n- Maintenance et support";
        }

        if (hasAny(text, ['site', 'vitrine', 'internet', 'web', 'ecommerce', 'e-commerce', 'boutique'])) {
            return "Pour un projet web, le chatbot peut vous guider sur le cadrage : type de site, nombre de pages, contenus, design, SEO, paiement en ligne, espace client ou back-office. Plus le besoin est precis, plus l'estimation sera fiable.";
        }

        if (hasAny(text, ['logiciel', 'application', 'automatisation', 'python', 'erp', 'facturation', 'api'])) {
            return "Pour un logiciel ou une automatisation, les points importants sont : le processus actuel, les donnees a traiter, les roles utilisateurs, les integrations API, les exports attendus et les regles metier.";
        }

        if (hasAny(text, ['devis', 'estimation', 'chiffrage'])) {
            return "Pour preparer un devis, indiquez le type de projet, les objectifs, les fonctionnalites attendues, les delais souhaites et les contraintes techniques. Vous pouvez aussi utiliser le simulateur via le bouton **Estimer mon projet**.";
        }

        if (hasAny(text, ['contact', 'mail', 'email', 'telephone', 'téléphone', 'appeler', 'joindre'])) {
            return "**Contact Code4U :**\n\n- Email : contact@code4u.fr\n- Telephone : 06 52 37 26 36\n- Zone : Metz, Grand Est\n\nLe chatbot ne transmet pas de message automatiquement : il vous donne simplement les informations utiles.";
        }

        if (hasAny(text, ['espace client', 'client', 'connexion', 'facture', 'devis signe', 'devis signé', 'ticket', 'support'])) {
            return "L'espace client permet de suivre les devis, factures, paiements, tickets, documents, projets et abonnements support. L'acces se fait depuis le bouton **Espace client** du menu.";
        }

        if (hasAny(text, ['merci', 'parfait', 'ok', 'super'])) {
            return "Avec plaisir. Posez-moi une autre question si vous voulez continuer.";
        }

        if (hasAny(text, ['au revoir', 'bye', 'a bientot', 'à bientôt'])) {
            return "A bientot. Je reste disponible si vous avez une autre question sur Code4U.";
        }

        return "Je suis le chatbot Code4U. Je peux repondre aux questions sur les services, les tarifs, le deroulement d'un projet, l'espace client et les moyens de contact. Reformulez votre question avec un peu plus de detail si besoin.";
    }

    function normalize(value) {
        return String(value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    function hasAny(text, keywords) {
        return keywords.some(function(keyword) {
            return text.includes(normalize(keyword));
        });
    }

    function formatMessage(text) {
        return escapeHtml(text)
            .replace(/\n/g, '<br>')
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
})();
