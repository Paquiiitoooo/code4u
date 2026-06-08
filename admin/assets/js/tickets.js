// ========================================
// Tickets Management JavaScript
// ========================================

let currentPage = 1;
let currentTab = 'active'; // 'active' or 'archived'
let currentFilters = {
    status: '',
    priority: '',
    search: ''
};

document.addEventListener('DOMContentLoaded', function() {
    initTickets();
});

function initTickets() {
    // Load tickets
    loadTickets();
    
    // Tab handlers
    document.getElementById('tabActive')?.addEventListener('click', function() {
        switchTab('active');
    });
    
    document.getElementById('tabArchived')?.addEventListener('click', function() {
        switchTab('archived');
    });
    
    
    // Filter handlers
    document.getElementById('filterStatus')?.addEventListener('change', function() {
        currentFilters.status = this.value;
        currentPage = 1;
        loadTickets();
    });
    
    document.getElementById('filterPriority')?.addEventListener('change', function() {
        currentFilters.priority = this.value;
        currentPage = 1;
        loadTickets();
    });
    
    document.getElementById('searchInput')?.addEventListener('input', debounce(function() {
        currentFilters.search = this.value;
        currentPage = 1;
        loadTickets();
    }, 500));
    
    // New ticket button
    document.getElementById('newTicketBtn')?.addEventListener('click', function() {
        openNewTicketModal();
    });
    
    // Auto-refresh every 30 seconds
    setInterval(() => {
        loadTickets();
    }, 30000);
}

function switchTab(tab) {
    currentTab = tab;
    currentPage = 1;
    
    // Update tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.tab === tab) {
            btn.classList.add('active');
        }
    });
    
    // Update filters visibility
    const filtersBar = document.getElementById('filtersBar');
    if (filtersBar) {
        if (tab === 'archived') {
            filtersBar.style.display = 'none';
        } else {
            filtersBar.style.display = 'flex';
        }
    }
    
    loadTickets();
}

async function loadTickets() {
    const tbody = document.getElementById('ticketsTableBody');
    if (!tbody) return;
    
    tbody.innerHTML = '<tr><td colspan="8" class="loading">Chargement...</td></tr>';
    
    try {
        const params = new URLSearchParams({
            action: 'list',
            page: currentPage,
            archived: currentTab === 'archived' ? '1' : '0',
            ...currentFilters
        });
        
        const response = await fetch(`api/tickets.php?${params}`);
        const data = await response.json();
        
        if (data.success) {
            displayTickets(data.data);
            displayPagination(data.pagination);
        } else {
            tbody.innerHTML = `<tr><td colspan="8" class="error">Erreur: ${data.message || 'Erreur inconnue'}</td></tr>`;
        }
    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="8" class="error">Erreur: ${error.message}</td></tr>`;
    }
}

function displayTickets(tickets) {
    const tbody = document.getElementById('ticketsTableBody');
    
    if (tickets.length === 0) {
        tbody.innerHTML = '<tr class="ticket-row"><td colspan="8" class="empty" style="text-align: center; padding: 3rem; color: var(--admin-text-secondary);"><i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.3; margin-bottom: 1rem; display: block;"></i>Aucun ticket trouvé</td></tr>';
        return;
    }
    
    tbody.innerHTML = tickets.map(ticket => `
        <tr class="ticket-row" onclick="viewTicket(${ticket.id})">
            <td data-label="Numéro"><strong style="color: var(--admin-primary);">${ticket.ticket_number}</strong></td>
            <td data-label="Sujet">
                <a href="#" class="ticket-subject" data-id="${ticket.id}" onclick="event.stopPropagation(); viewTicket(${ticket.id});">
                    ${escapeHtml(ticket.subject)}
                </a>
                ${ticket.message_count > 0 ? `<br><small style="color: var(--admin-text-secondary);"><i class="fas fa-comments"></i> ${ticket.message_count} message(s)</small>` : ''}
            </td>
            <td data-label="Client">
                <div class="ticket-customer">
                    <strong>${escapeHtml(ticket.customer_name)}</strong>
                    <small>${escapeHtml(ticket.customer_email)}</small>
                </div>
            </td>
            <td data-label="Statut"><span class="badge ${getStatusBadgeClass(ticket.status)}"><i class="fas fa-circle"></i> ${getStatusLabel(ticket.status)}</span></td>
            <td data-label="Priorité"><span class="badge ${getPriorityBadgeClass(ticket.priority)}"><i class="fas fa-flag"></i> ${getPriorityLabel(ticket.priority)}</span></td>
            <td data-label="Assigné">${ticket.assigned_name ? `<span style="color: var(--admin-text);"><i class="fas fa-user-tie"></i> ${escapeHtml(ticket.assigned_name)}</span>` : '<em style="color: var(--admin-text-secondary);">Non assigné</em>'}</td>
            <td data-label="Date">
                <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                    <span style="color: var(--admin-text);">${adminUtils.formatDate(ticket.created_at)}</span>
                    ${ticket.last_message_at ? `<small style="color: var(--admin-text-secondary); font-size: 0.75rem;">Dernier message: ${formatRelativeTime(ticket.last_message_at)}</small>` : ''}
                </div>
            </td>
            <td data-label="Actions">
                <div class="table-actions" onclick="event.stopPropagation();">
                    <button class="btn-icon" onclick="viewTicket(${ticket.id})" title="Voir">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn-icon" onclick="deleteTicket(${ticket.id}, '${escapeHtml(ticket.ticket_number)}')" title="Supprimer" style="color: var(--admin-danger);">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function getStatusLabel(status) {
    const labels = {
        'open': 'Ouvert',
        'in_progress': 'En cours',
        'waiting': 'En attente',
        'resolved': 'Résolu',
        'closed': 'Fermé'
    };
    return labels[status] || status;
}

function getPriorityLabel(priority) {
    const labels = {
        'low': 'Basse',
        'medium': 'Moyenne',
        'high': 'Haute',
        'urgent': 'Urgente'
    };
    return labels[priority] || priority;
}

function formatRelativeTime(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    
    if (diffMins < 1) return 'À l\'instant';
    if (diffMins < 60) return `Il y a ${diffMins} min`;
    if (diffHours < 24) return `Il y a ${diffHours}h`;
    if (diffDays < 7) return `Il y a ${diffDays}j`;
    return date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' });
}

function displayPagination(pagination) {
    const paginationDiv = document.getElementById('pagination');
    if (!paginationDiv || pagination.pages <= 1) {
        paginationDiv.innerHTML = '';
        return;
    }
    
    let html = '';
    
    // Previous button
    html += `<button ${currentPage === 1 ? 'disabled' : ''} onclick="changePage(${currentPage - 1})">
        <i class="fas fa-chevron-left"></i>
    </button>`;
    
    // Page numbers
    for (let i = 1; i <= pagination.pages; i++) {
        if (i === 1 || i === pagination.pages || (i >= currentPage - 2 && i <= currentPage + 2)) {
            html += `<button class="${i === currentPage ? 'active' : ''}" onclick="changePage(${i})">${i}</button>`;
        } else if (i === currentPage - 3 || i === currentPage + 3) {
            html += `<span>...</span>`;
        }
    }
    
    // Next button
    html += `<button ${currentPage === pagination.pages ? 'disabled' : ''} onclick="changePage(${currentPage + 1})">
        <i class="fas fa-chevron-right"></i>
    </button>`;
    
    paginationDiv.innerHTML = html;
}

function changePage(page) {
    currentPage = page;
    loadTickets();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function viewTicket(id) {
    try {
        const data = await adminUtils.apiRequest(`api/tickets.php?action=single&id=${id}`);
        
        if (data.success) {
            displayTicketModal(data.data);
        }
    } catch (error) {
        adminUtils.showNotification('Erreur lors du chargement du ticket', 'error');
    }
}

function displayTicketModal(ticket) {
    const modal = document.getElementById('ticketModal');
    const modalBody = document.getElementById('ticketModalBody');
    const modalTitle = document.getElementById('modalTitle');
    
    modalTitle.innerHTML = `<i class="fas fa-ticket-alt"></i> Ticket ${ticket.ticket_number}`;
    
    modalBody.innerHTML = `
        <div class="ticket-details">
            <div class="ticket-details-header">
                <div>
                    <h3 style="margin: 0 0 0.75rem 0; font-size: 1.5rem; color: var(--admin-text);">${escapeHtml(ticket.subject)}</h3>
                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                        <span class="badge ${getStatusBadgeClass(ticket.status)}"><i class="fas fa-circle"></i> ${getStatusLabel(ticket.status)}</span>
                        <span class="badge ${getPriorityBadgeClass(ticket.priority)}"><i class="fas fa-flag"></i> ${getPriorityLabel(ticket.priority)}</span>
                    </div>
                </div>
                <div style="display: flex; gap: 0.75rem;">
                    <select id="ticketStatusSelect" class="form-select" style="min-width: 150px;">
                        <option value="open" ${ticket.status === 'open' ? 'selected' : ''}>Ouvert</option>
                        <option value="in_progress" ${ticket.status === 'in_progress' ? 'selected' : ''}>En cours</option>
                        <option value="waiting" ${ticket.status === 'waiting' ? 'selected' : ''}>En attente</option>
                        <option value="resolved" ${ticket.status === 'resolved' ? 'selected' : ''}>Résolu</option>
                        <option value="closed" ${ticket.status === 'closed' ? 'selected' : ''}>Fermé</option>
                    </select>
                </div>
            </div>
            
            <div class="ticket-info-grid">
                <div class="info-card">
                    <div class="info-card-label"><i class="fas fa-user"></i> Client</div>
                    <div class="info-card-value">
                        <div>${escapeHtml(ticket.customer_name)}</div>
                        <small style="color: var(--admin-text-secondary); font-size: 0.85rem;">${escapeHtml(ticket.customer_email)}</small>
                        ${ticket.customer_phone ? `<br><small style="color: var(--admin-text-secondary); font-size: 0.85rem;"><i class="fas fa-phone"></i> ${escapeHtml(ticket.customer_phone)}</small>` : ''}
                    </div>
                </div>
                <div class="info-card">
                    <div class="info-card-label"><i class="fas fa-calendar"></i> Créé le</div>
                    <div class="info-card-value">${adminUtils.formatDate(ticket.created_at)}</div>
                </div>
                <div class="info-card">
                    <div class="info-card-label"><i class="fas fa-user-tie"></i> Assigné à</div>
                    <select id="assignSelect" class="form-select" style="width: 100%; margin-top: 0.5rem;">
                        <option value="">Non assigné</option>
                    </select>
                </div>
                <div class="info-card">
                    <div class="info-card-label"><i class="fas fa-tag"></i> Source</div>
                    <div class="info-card-value">${ticket.source || 'N/A'}</div>
                </div>
            </div>
            
            <div style="padding: 1.5rem; border-top: 1px solid var(--admin-border);">
                <h4 style="margin: 0 0 1rem 0; color: var(--admin-text); font-size: 1.1rem;"><i class="fas fa-align-left"></i> Description</h4>
                <div style="background: var(--admin-bg); padding: 1.25rem; border-radius: 12px; border: 1px solid var(--admin-border); line-height: 1.7; color: var(--admin-text); white-space: pre-wrap;">${escapeHtml(ticket.description)}</div>
            </div>
            
            <div class="messages-container" style="border-top: 1px solid var(--admin-border);">
                <h4 style="margin: 0 0 1.5rem 0; color: var(--admin-text); font-size: 1.1rem;"><i class="fas fa-comments"></i> Messages (${ticket.messages?.length || 0})</h4>
                <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1.5rem; max-height: 400px; overflow-y: auto; padding-right: 0.5rem;">
                    ${(ticket.messages || []).map(msg => `
                        <div class="message-bubble ${msg.sender_type}">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <strong style="font-size: 0.9rem;">${msg.sender_name || ticket.customer_name}</strong>
                                <small style="opacity: 0.7; font-size: 0.75rem;">${adminUtils.formatDate(msg.created_at)}</small>
                            </div>
                            <div style="line-height: 1.6;">${escapeHtml(msg.message)}</div>
                        </div>
                    `).join('')}
                </div>
                
                <div class="message-form" style="border-top: 2px solid var(--admin-border); padding-top: 1.5rem;">
                    <textarea id="newMessage" class="form-textarea" placeholder="Écrire une réponse..." style="min-height: 100px; border-radius: 12px; padding: 1rem;"></textarea>
                    <button class="btn btn-primary" onclick="sendMessage(${ticket.id})" style="margin-top: 1rem;">
                        <i class="fas fa-paper-plane"></i> Envoyer la réponse
                    </button>
                </div>
            </div>
        </div>
    `;
    
    // Load admins for assignment
    loadAdminsForAssignment(ticket.assigned_to);
    
    // Status change handler
    document.getElementById('ticketStatusSelect')?.addEventListener('change', function() {
        updateTicketStatus(ticket.id, this.value);
    });
    
    // Assignment change handler
    document.getElementById('assignSelect')?.addEventListener('change', function() {
        assignTicket(ticket.id, this.value);
    });
    
    adminUtils.openModal('ticketModal');
}

async function updateTicketStatus(ticketId, status) {
    try {
        await adminUtils.apiRequest(`api/tickets.php?id=${ticketId}`, {
            method: 'PUT',
            body: JSON.stringify({ status })
        });
        
        adminUtils.showNotification('Statut mis à jour', 'success');
        loadTickets();
    } catch (error) {
        adminUtils.showNotification('Erreur lors de la mise à jour', 'error');
    }
}

async function sendMessage(ticketId) {
    const messageInput = document.getElementById('newMessage');
    const message = messageInput.value.trim();
    
    if (!message) {
        adminUtils.showNotification('Veuillez entrer un message', 'warning');
        return;
    }
    
    try {
        await adminUtils.apiRequest('api/tickets.php?action=message', {
            method: 'POST',
            body: JSON.stringify({
                ticket_id: ticketId,
                message: message
            })
        });
        
        messageInput.value = '';
        adminUtils.showNotification('Message envoyé', 'success');
        viewTicket(ticketId); // Reload ticket
    } catch (error) {
        adminUtils.showNotification('Erreur lors de l\'envoi', 'error');
    }
}

function getStatusBadgeClass(status) {
    const classes = {
        'open': 'badge-open',
        'in_progress': 'badge-progress',
        'waiting': 'badge-waiting',
        'resolved': 'badge-resolved',
        'closed': 'badge-closed'
    };
    return classes[status] || 'badge-default';
}

function getPriorityBadgeClass(priority) {
    const classes = {
        'low': 'badge-low',
        'medium': 'badge-medium',
        'high': 'badge-high',
        'urgent': 'badge-urgent'
    };
    return classes[priority] || 'badge-medium';
}

async function loadAdminsForAssignment(currentAssignedId) {
    try {
        const data = await adminUtils.apiRequest('api/admins.php');
        const select = document.getElementById('assignSelect');
        
        if (data.success && select) {
            select.innerHTML = '<option value="">Non assigné</option>';
            data.data.forEach(admin => {
                const option = document.createElement('option');
                option.value = admin.id;
                option.textContent = admin.full_name || admin.username;
                if (currentAssignedId && admin.id == currentAssignedId) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading admins:', error);
    }
}

async function assignTicket(ticketId, adminId) {
    try {
        await adminUtils.apiRequest(`api/tickets.php?id=${ticketId}`, {
            method: 'PUT',
            body: JSON.stringify({ 
                assigned_to: adminId || null 
            })
        });
        
        adminUtils.showNotification('Ticket assigné avec succès', 'success');
        loadTickets();
        viewTicket(ticketId); // Reload to show updated assignment
    } catch (error) {
        adminUtils.showNotification('Erreur lors de l\'assignation', 'error');
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function openNewTicketModal() {
    adminUtils.showNotification('Fonctionnalité à venir', 'info');
    // TODO: Implement new ticket modal
}

async function deleteTicket(ticketId, ticketNumber) {
    const confirmed = await adminUtils.confirmDialog(
        `Êtes-vous sûr de vouloir supprimer le ticket <strong>${ticketNumber}</strong> ?<br><br>Cette action est <strong>irréversible</strong> et supprimera également tous les messages associés.`,
        'Supprimer le ticket'
    );
    
    if (!confirmed) return;
    
    try {
        const response = await fetch(`api/tickets.php?id=${ticketId}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (data.success) {
            adminUtils.showNotification('Ticket supprimé avec succès', 'success');
            loadTickets();
            if (typeof loadStats === 'function') {
                loadStats();
            }
        } else {
            adminUtils.showNotification('Erreur lors de la suppression: ' + (data.message || 'Erreur inconnue'), 'error');
        }
    } catch (error) {
        console.error('Error deleting ticket:', error);
        adminUtils.showNotification('Erreur lors de la suppression du ticket', 'error');
    }
}

// Export functions
window.viewTicket = viewTicket;
window.editTicket = viewTicket; // For now, same as view
window.changePage = changePage;
window.sendMessage = sendMessage;
window.deleteTicket = deleteTicket;

