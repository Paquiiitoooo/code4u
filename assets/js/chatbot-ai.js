(function() {
    'use strict';

    var AI_ENDPOINT = '/admin/api/chatbot-ai.php';
    var TICKET_ENDPOINT = '/admin/api/chatbot-ticket.php';
    var MAX_HISTORY = 8;

    var isInitialized = false;
    var hasWelcomed = false;
    var isSending = false;
    var mode = 'chat';
    var history = [];
    var handoffDraft = { name: '', email: '', message: '' };

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
            addBotMessage("Bonjour, je suis l'assistant IA Code4U. Je peux répondre à vos questions sur les services, tarifs, projets, espace client et support. Vous pouvez aussi demander à parler à un humain.");
            showQuickActions();
            hasWelcomed = true;
        }

        resetInput('Posez votre question...');

        setTimeout(function() {
            chatbotInput?.focus();
        }, 120);
    }

    function closeChatbot() {
        document.getElementById('chatbotWindow')?.classList.remove('active');
    }

    async function sendMessage() {
        var chatbotInput = document.getElementById('chatbotInput');
        var message = chatbotInput?.value.trim();

        if (!message || isSending) return;

        addUserMessage(message);
        chatbotInput.value = '';

        if (mode === 'handoff_name') {
            handoffDraft.name = message;
            mode = 'handoff_email';
            addBotMessage('Merci. Quelle adresse email Code4U peut utiliser pour vous répondre ?');
            resetInput('votre@email.fr');
            return;
        }

        if (mode === 'handoff_email') {
            if (!isValidEmail(message)) {
                addBotMessage('Cette adresse email ne semble pas valide. Pouvez-vous la saisir à nouveau ?');
                resetInput('votre@email.fr');
                return;
            }
            handoffDraft.email = message;
            mode = 'handoff_message';
            addBotMessage('Parfait. Résumez votre demande pour que Code4U puisse vous répondre efficacement.');
            resetInput('Votre demande...');
            return;
        }

        if (mode === 'handoff_message') {
            handoffDraft.message = message;
            await createHumanTicket();
            return;
        }

        if (wantsHuman(message)) {
            addBotMessage("Je peux vous mettre en relation avec un humain. Pour créer la demande, j'ai besoin de votre nom.");
            mode = 'handoff_name';
            handoffDraft = { name: '', email: '', message: message };
            resetInput('Votre nom...');
            return;
        }

        await askAi(message);
    }

    async function askAi(message) {
        var chatbotInput = document.getElementById('chatbotInput');
        isSending = true;
        chatbotInput.disabled = true;
        showTyping();

        try {
            var response = await fetch(AI_ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    message: message,
                    history: history.slice(-MAX_HISTORY)
                })
            });

            var data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Réponse IA indisponible.');
            }

            removeTyping();
            addBotMessage(data.reply || 'Je ne peux pas répondre pour le moment.');
            remember('user', message);
            remember('assistant', data.reply || '');

            if (data.handoff?.suggest) {
                showHumanHandoffButton();
            }
        } catch (error) {
            removeTyping();
            addBotMessage("Je n'arrive pas à joindre l'IA pour le moment. Vous pouvez reformuler, ou demander une mise en relation humaine.");
            showHumanHandoffButton();
        } finally {
            isSending = false;
            chatbotInput.disabled = false;
            resetInput('Posez votre question...');
            chatbotInput.focus();
        }
    }

    async function createHumanTicket() {
        var chatbotInput = document.getElementById('chatbotInput');
        isSending = true;
        chatbotInput.disabled = true;
        showTyping();

        var description = handoffDraft.message || 'Demande de mise en relation depuis le chatbot.';
        if (history.length) {
            description += "\n\nContexte chatbot :\n" + history.slice(-6).map(function(item) {
                return (item.role === 'assistant' ? 'Assistant' : 'Visiteur') + ' : ' + item.content;
            }).join("\n");
        }

        try {
            var response = await fetch(TICKET_ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    customer_name: handoffDraft.name,
                    customer_email: handoffDraft.email,
                    customer_phone: '',
                    subject: 'Mise en relation humaine - Chatbot IA',
                    description: description,
                    priority: 'medium',
                    category: 'Chatbot IA',
                    source: 'chatbot'
                })
            });

            var data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Création de ticket impossible.');
            }

            removeTyping();
            var ticketNumber = data.data?.ticket_number ? ' Numéro de ticket : **' + data.data.ticket_number + '**.' : '';
            addBotMessage('Votre demande a bien été transmise à Code4U.' + ticketNumber + ' Vous recevrez une réponse par email.');
            mode = 'chat';
            handoffDraft = { name: '', email: '', message: '' };
            resetInput('Posez votre question...');
        } catch (error) {
            removeTyping();
            addBotMessage("Je n'ai pas pu créer la demande automatiquement. Vous pouvez contacter Code4U à **contact@code4u.fr** ou au **06 52 37 26 36**.");
        } finally {
            isSending = false;
            chatbotInput.disabled = false;
            chatbotInput.focus();
        }
    }

    function showQuickActions() {
        var chatbotMessages = document.getElementById('chatbotMessages');
        if (!chatbotMessages) return;

        var actions = document.createElement('div');
        actions.className = 'chatbot-actions';
        actions.innerHTML = [
            '<div class="suggestions-title">Questions fréquentes :</div>',
            '<div class="chatbot-suggestions quick-start">',
            '<button class="suggestion-btn" type="button" data-message="Quels sont vos tarifs ?">Tarifs</button>',
            '<button class="suggestion-btn" type="button" data-message="Quels services proposez-vous ?">Services</button>',
            '<button class="suggestion-btn" type="button" data-message="Comment se déroule un projet ?">Projet</button>',
            '<button class="suggestion-btn" type="button" data-message="Je veux parler à un humain">Humain</button>',
            '</div>'
        ].join('');

        actions.querySelectorAll('[data-message]').forEach(function(button) {
            button.addEventListener('click', function() {
                var input = document.getElementById('chatbotInput');
                input.value = button.getAttribute('data-message') || '';
                sendMessage();
            });
        });

        chatbotMessages.appendChild(actions);
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
    }

    function showHumanHandoffButton() {
        var chatbotMessages = document.getElementById('chatbotMessages');
        if (!chatbotMessages) return;

        var actions = document.createElement('div');
        actions.className = 'chatbot-actions';
        actions.innerHTML = '<div class="chatbot-suggestions"><button class="suggestion-btn" type="button">Demander un humain</button></div>';
        actions.querySelector('button').addEventListener('click', function() {
            mode = 'handoff_name';
            addBotMessage("D'accord. Quel est votre nom ?");
            resetInput('Votre nom...');
        });

        chatbotMessages.appendChild(actions);
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
    }

    function addUserMessage(text) {
        addMessage(text, 'user');
    }

    function addBotMessage(text) {
        addMessage(text, 'bot');
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
        typing.innerHTML = '<div class="message-avatar"><i class="fas fa-robot" aria-hidden="true"></i></div><div class="message-content"><p>L’IA réfléchit...</p></div>';
        chatbotMessages.appendChild(typing);
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
    }

    function removeTyping() {
        document.getElementById('chatbotTyping')?.remove();
    }

    function resetMessages() {
        var chatbotMessages = document.getElementById('chatbotMessages');
        if (chatbotMessages) {
            chatbotMessages.innerHTML = '';
        }
    }

    function resetInput(placeholder) {
        var chatbotInput = document.getElementById('chatbotInput');
        if (!chatbotInput) return;
        chatbotInput.type = mode === 'handoff_email' ? 'email' : 'text';
        chatbotInput.placeholder = placeholder || 'Posez votre question...';
    }

    function remember(role, content) {
        content = String(content || '').trim();
        if (!content) return;
        history.push({ role: role, content: content.slice(0, 900) });
        if (history.length > MAX_HISTORY) {
            history = history.slice(-MAX_HISTORY);
        }
    }

    function wantsHuman(message) {
        var text = normalize(message);
        return [
            'humain', 'conseiller', 'agent', 'rappel', 'appelez-moi',
            'appeler moi', 'contactez-moi', 'me contacter', 'etre rappele',
            'etre contacte', 'mise en relation', 'parler a un humain',
            'parler avec un humain', 'parler a quelqu un', 'parler avec quelqu un',
            'joindre une personne'
        ].some(function(keyword) {
            return text.includes(normalize(keyword));
        });
    }

    function isValidEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || '').trim());
    }

    function normalize(value) {
        return String(value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
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
