// ========================================
// Chat Support Management
// ========================================

let currentChatId = null;
let chatPollInterval = null;
let lastMessageId = null;
let currentChatMessages = new Set(); // Track displayed message IDs

document.addEventListener('DOMContentLoaded', function() {
    loadChats();
    
    document.getElementById('chatSendBtn')?.addEventListener('click', sendMessage);
    document.getElementById('chatInput')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });
    
    // Poll for new messages every 2 seconds (more responsive)
    chatPollInterval = setInterval(() => {
        if (currentChatId) {
            loadMessages(currentChatId);
        }
        loadChats();
        checkNotifications();
    }, 2000);
    
    // Check for inactive chats every 5 minutes
    setInterval(() => {
        checkInactiveChats();
    }, 300000); // 5 minutes
    
    // Check on initial load
    checkInactiveChats();
    
    // Check notifications on load
    checkNotifications();
});

async function loadChats() {
    const chatList = document.getElementById('chatList');
    if (!chatList) return;
    
    try {
        const response = await fetch('api/chat.php?action=list');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            if (data.data && data.data.length > 0) {
                displayChats(data.data);
            } else {
                chatList.innerHTML = '<div class="empty-chat">Aucune conversation active</div>';
            }
        } else {
            console.error('Erreur chargement chats:', data.message);
            chatList.innerHTML = `<div class="empty-chat">Erreur: ${data.message || 'Erreur inconnue'}</div>`;
        }
    } catch (error) {
        console.error('Error loading chats:', error);
        if (chatList) {
            chatList.innerHTML = `<div class="empty-chat">Erreur de connexion: ${error.message}</div>`;
        }
    }
}

function displayChats(chats) {
    const chatList = document.getElementById('chatList');
    if (!chatList) return;
    
    if (chats.length === 0) {
        chatList.innerHTML = '<div class="empty-chat">Aucune conversation active</div>';
        return;
    }
    
    chatList.innerHTML = chats.map(chat => `
        <div class="chat-item ${chat.id == currentChatId ? 'active' : ''}" onclick="selectChat(${chat.id}, '${escapeHtml(chat.customer_name)}', '${escapeHtml(chat.customer_email)}', '${escapeHtml(chat.ticket_number || '')}')">
            <div class="chat-item-header">
                <div class="chat-item-name">
                    ${escapeHtml(chat.customer_name)}
                    ${chat.ticket_number ? `<span style="font-size: 0.7rem; color: #757575; margin-left: 0.5rem; font-weight: 400;">#${escapeHtml(chat.ticket_number)}</span>` : ''}
                </div>
                <div class="chat-item-time">${formatTime(chat.last_message_at || chat.created_at)}</div>
            </div>
            <div class="chat-item-message">${escapeHtml(chat.last_message || 'Nouvelle conversation')}</div>
            <div class="chat-item-footer">
                ${chat.unread_count > 0 ? `<div class="chat-item-badge">${chat.unread_count}</div>` : '<div></div>'}
                ${chat.message_count > 0 ? `<div class="chat-item-count"><i class="fas fa-comments"></i> ${chat.message_count}</div>` : '<div></div>'}
            </div>
        </div>
    `).join('');
}

async function selectChat(chatId, customerName, customerEmail, ticketNumber) {
    currentChatId = chatId;
    lastMessageId = null; // Reset for new chat
    currentChatMessages.clear(); // Clear tracked messages
    
    // Clear chat window
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages) {
        chatMessages.innerHTML = '<div class="empty-chat">Chargement...</div>';
    }
    
    // Update header with customer info
    const customerNameEl = document.getElementById('chatCustomerName');
    const customerInfoEl = document.getElementById('chatCustomerInfo');
    
    if (customerNameEl) {
        customerNameEl.textContent = customerName;
    }
    if (customerInfoEl) {
        customerInfoEl.textContent = customerEmail + (ticketNumber ? ` • Ticket: ${ticketNumber}` : '');
    }
    
    document.getElementById('chatInputArea').style.display = 'flex';
    
    // Show close button
    const closeBtn = document.getElementById('chatCloseBtn');
    if (closeBtn) {
        closeBtn.style.display = 'flex';
    }
    
    // Mark as read
    try {
        await fetch(`api/chat.php?action=read&id=${chatId}`, { method: 'POST' });
    } catch (error) {
        console.error('Error marking as read:', error);
    }
    
    loadMessages(chatId);
    loadChats(); // Refresh list
}

async function loadMessages(chatId) {
    if (!chatId) return;
    
    try {
        const response = await fetch(`api/chat.php?action=messages&id=${chatId}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success && Array.isArray(data.data)) {
            displayMessages(data.data);
        } else {
            console.error('Erreur chargement messages:', data.message || 'Format invalide');
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.innerHTML = '<div class="empty-chat">Erreur: ' + (data.message || 'Impossible de charger les messages') + '</div>';
            }
        }
    } catch (error) {
        console.error('Error loading messages:', error);
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.innerHTML = '<div class="empty-chat">Erreur de connexion: ' + error.message + '</div>';
        }
    }
}

function displayMessages(messages) {
    const chatMessages = document.getElementById('chatMessages');
    if (!chatMessages) return;
    
    if (messages.length === 0) {
        if (chatMessages.children.length === 0) {
            chatMessages.innerHTML = '<div class="empty-chat">Aucun message</div>';
        }
        return;
    }
    
    // If no messages tracked yet, this is first load - show all
    if (currentChatMessages.size === 0) {
        if (messages.length === 0) {
            chatMessages.innerHTML = '<div class="empty-chat">Aucun message</div>';
            return;
        }
        
        // Show all messages on first load
        chatMessages.innerHTML = messages.map(msg => {
            currentChatMessages.add(msg.id);
            let messageClass = 'customer';
            let senderLabel = msg.sender_name || 'Client';
            
            if (msg.sender_type === 'admin') {
                messageClass = 'admin';
                senderLabel = 'Conseiller';
            } else if (msg.sender_type === 'system') {
                messageClass = 'system';
                senderLabel = 'Système';
            }
            
            return `
                <div class="chat-message ${messageClass}">
                    ${msg.sender_type !== 'system' ? `<div class="chat-message-header">${senderLabel}</div>` : ''}
                    <div class="chat-message-content">${escapeHtml(msg.message)}</div>
                    <div class="chat-message-time">${formatTime(msg.created_at)}</div>
                </div>
            `;
        }).join('');
        
        if (messages.length > 0) {
            lastMessageId = messages[messages.length - 1].id;
        }
        chatMessages.scrollTop = chatMessages.scrollHeight;
    } else {
        // Subsequent loads - only show new messages
        const newMessages = messages.filter(msg => !currentChatMessages.has(msg.id));
        
        if (newMessages.length > 0) {
            newMessages.forEach(msg => {
                const messageDiv = document.createElement('div');
                let messageClass = 'customer';
                let senderLabel = msg.sender_name || 'Client';
                
                if (msg.sender_type === 'admin') {
                    messageClass = 'admin';
                    senderLabel = 'Conseiller';
                } else if (msg.sender_type === 'system') {
                    messageClass = 'system';
                    senderLabel = 'Système';
                }
                
                messageDiv.className = `chat-message ${messageClass}`;
                messageDiv.innerHTML = `
                    ${msg.sender_type !== 'system' ? `<div class="chat-message-header">${senderLabel}</div>` : ''}
                    <div class="chat-message-content">${escapeHtml(msg.message)}</div>
                    <div class="chat-message-time">${formatTime(msg.created_at)}</div>
                `;
                chatMessages.appendChild(messageDiv);
                currentChatMessages.add(msg.id);
            });
            
            lastMessageId = messages[messages.length - 1].id;
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }
}

async function sendMessage() {
    const input = document.getElementById('chatInput');
    const message = input?.value.trim();
    
    if (!message || !currentChatId) {
        if (!currentChatId) {
            console.error('❌ Aucun chat sélectionné');
            if (typeof adminUtils !== 'undefined' && adminUtils.showNotification) {
                adminUtils.showNotification('Veuillez sélectionner une conversation', 'warning');
            }
        }
        return;
    }
    
    // Afficher le message immédiatement dans l'interface
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'chat-message admin';
        messageDiv.innerHTML = `
            <div class="chat-message-header">Conseiller</div>
            <div class="chat-message-content">${escapeHtml(message)}</div>
            <div class="chat-message-time">À l'instant</div>
        `;
        chatMessages.appendChild(messageDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    input.value = '';
    
    try {
        const response = await fetch('api/chat.php?action=message', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                chat_id: currentChatId,
                message: message,
                sender_type: 'admin'
            })
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Erreur lors de l\'envoi');
        }
        
        // Recharger les messages pour avoir la version complète
        setTimeout(() => {
            loadMessages(currentChatId);
            loadChats();
        }, 500);
        
    } catch (error) {
        console.error('❌ Erreur envoi message:', error);
        if (typeof adminUtils !== 'undefined' && adminUtils.showNotification) {
            adminUtils.showNotification('Erreur lors de l\'envoi: ' + error.message, 'error');
        } else {
            alert('Erreur lors de l\'envoi: ' + error.message);
        }
    }
}

function formatTime(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;
    const minutes = Math.floor(diff / 60000);
    
    if (minutes < 1) return 'À l\'instant';
    if (minutes < 60) return `Il y a ${minutes} min`;
    if (minutes < 1440) return `Il y a ${Math.floor(minutes / 60)}h`;
    return date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Check for new notifications
let lastNotificationCheck = null;
async function checkNotifications() {
    try {
        const response = await fetch('api/notifications.php');
        const data = await response.json();
        if (data.success) {
            updateNotificationBadge(data.data.unread_chats);
            
            // Show notification for new messages
            if (data.data.recent_messages && data.data.recent_messages.length > 0) {
                data.data.recent_messages.forEach(msg => {
                    const messageKey = `msg_${msg.ticket_id}_${msg.created_at}`;
                    if (!lastNotificationCheck || !lastNotificationCheck.has(messageKey)) {
                        if (!lastNotificationCheck) lastNotificationCheck = new Set();
                        lastNotificationCheck.add(messageKey);
                        
                        // Only show notification if not viewing this chat
                        if (currentChatId != msg.ticket_id) {
                            showChatNotification(msg);
                        }
                    }
                });
            }
        }
    } catch (error) {
        console.error('Error checking notifications:', error);
    }
}

// Update notification badge
function updateNotificationBadge(count) {
    const badge = document.querySelector('.notification-badge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }
    
    // Also update sidebar badge
    const sidebarBadge = document.querySelector('.nav-item[href="chat.php"] .nav-badge');
    if (sidebarBadge) {
        if (count > 0) {
            sidebarBadge.textContent = count > 99 ? '99+' : count;
            sidebarBadge.style.display = 'flex';
        } else {
            sidebarBadge.style.display = 'none';
        }
    }
}

// Show chat notification
function showChatNotification(message) {
    if (typeof adminUtils !== 'undefined' && adminUtils.showNotification) {
        adminUtils.showNotification(
            `Nouveau message de ${message.customer_name} (${message.ticket_number})`,
            'info'
        );
    } else {
        // Fallback notification
        const notification = document.createElement('div');
        notification.className = 'chat-notification';
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-comments"></i>
                <div>
                    <strong>Nouveau message</strong>
                    <p>${escapeHtml(message.customer_name)} - ${escapeHtml(message.message.substring(0, 50))}...</p>
                </div>
            </div>
        `;
        document.body.appendChild(notification);
        
        setTimeout(() => notification.classList.add('show'), 10);
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }
}

async function closeCurrentChat() {
    if (!currentChatId) {
        return;
    }
    
    if (!confirm('Êtes-vous sûr de vouloir fermer cette conversation ? Un message automatique sera envoyé au client.')) {
        return;
    }
    
    const closeBtn = document.getElementById('chatCloseBtn');
    if (closeBtn) {
        closeBtn.disabled = true;
    }
    
    try {
        const response = await fetch(`api/chat.php?action=close&id=${currentChatId}`, {
            method: 'POST'
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Reload messages to show closure message
            await loadMessages(currentChatId);
            
            // Clear current chat
            currentChatId = null;
            
            // Hide input area and close button
            document.getElementById('chatInputArea').style.display = 'none';
            if (closeBtn) {
                closeBtn.style.display = 'none';
            }
            
            // Reset header
            document.getElementById('chatCustomerName').textContent = 'Sélectionnez une conversation';
            document.getElementById('chatCustomerInfo').textContent = '';
            
            // Clear messages
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.innerHTML = `
                    <div class="empty-chat">
                        <i class="fas fa-comments"></i>
                        <p>Sélectionnez une conversation pour commencer</p>
                    </div>
                `;
            }
            
            // Refresh chat list
            loadChats();
            
            if (typeof adminUtils !== 'undefined' && adminUtils.showNotification) {
                adminUtils.showNotification('Conversation fermée avec succès', 'success');
            }
        } else {
            throw new Error(data.message || 'Erreur lors de la fermeture');
        }
    } catch (error) {
        console.error('Error closing chat:', error);
        if (typeof adminUtils !== 'undefined' && adminUtils.showNotification) {
            adminUtils.showNotification('Erreur lors de la fermeture: ' + error.message, 'error');
        } else {
            alert('Erreur lors de la fermeture: ' + error.message);
        }
    } finally {
        if (closeBtn) {
            closeBtn.disabled = false;
        }
    }
}

async function checkInactiveChats() {
    try {
        const response = await fetch('api/chat.php?action=check_inactive', {
            method: 'POST'
        });
        
        const data = await response.json();
        
        if (data.success && data.closed_count > 0) {
            // Refresh chat list if any chats were closed
            loadChats();
            
            // If current chat was closed, reload messages
            if (currentChatId) {
                loadMessages(currentChatId);
            }
        }
    } catch (error) {
        console.error('Error checking inactive chats:', error);
    }
}

window.selectChat = selectChat;
window.closeCurrentChat = closeCurrentChat;

