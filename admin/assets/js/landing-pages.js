// ========================================
// Landing Pages Management JavaScript
// ========================================

let currentPage = 1;
let currentFilters = {
    status: '',
    search: ''
};

document.addEventListener('DOMContentLoaded', function() {
    initLandingPages();
});

function initLandingPages() {
    loadLandingPages();
    
    // Filter handlers
    document.getElementById('filterStatus')?.addEventListener('change', function() {
        currentFilters.status = this.value;
        currentPage = 1;
        loadLandingPages();
    });
    
    document.getElementById('searchInput')?.addEventListener('input', debounce(function() {
        currentFilters.search = this.value;
        currentPage = 1;
        loadLandingPages();
    }, 500));
    
    // New landing page button
    document.getElementById('newLandingPageBtn')?.addEventListener('click', function() {
        openNewLandingPageModal();
    });
}

async function loadLandingPages() {
    const grid = document.getElementById('landingPagesGrid');
    if (!grid) return;
    
    grid.innerHTML = '<div class="loading">Chargement...</div>';
    
    try {
        const params = new URLSearchParams({
            action: 'list',
            page: currentPage,
            ...currentFilters
        });
        
        const data = await adminUtils.apiRequest(`api/landing-pages.php?${params}`);
        
        if (data.success) {
            displayLandingPages(data.data);
            displayPagination(data.pagination);
        }
    } catch (error) {
        grid.innerHTML = `<div class="error">Erreur: ${error.message}</div>`;
    }
}

function displayLandingPages(pages) {
    const grid = document.getElementById('landingPagesGrid');
    
    if (pages.length === 0) {
        grid.innerHTML = '<div class="empty">Aucune landing page trouvée</div>';
        return;
    }
    
    grid.innerHTML = pages.map(page => `
        <div class="landing-page-card">
            <div class="page-card-header">
                <h3>${escapeHtml(page.title)}</h3>
                <span class="badge ${getStatusBadgeClass(page.status)}">${page.status}</span>
            </div>
            <div class="page-card-body">
                <p class="page-description">${escapeHtml(page.description || 'Aucune description')}</p>
                <div class="page-stats">
                    <div class="stat-item">
                        <i class="fas fa-eye"></i>
                        <span>${page.views || 0} vues</span>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-mouse-pointer"></i>
                        <span>${page.conversions || 0} conversions</span>
                    </div>
                </div>
                <div class="page-meta">
                    <small>
                        <i class="fas fa-calendar"></i> ${adminUtils.formatDate(page.created_at)}
                    </small>
                    <small>
                        <i class="fas fa-user"></i> ${page.created_by_name || 'Système'}
                    </small>
                </div>
            </div>
            <div class="page-card-actions">
                <button class="btn btn-primary" onclick="editLandingPage(${page.id})">
                    <i class="fas fa-edit"></i> Modifier
                </button>
                <button class="btn btn-secondary" onclick="viewLandingPage('${page.slug}')">
                    <i class="fas fa-eye"></i> Voir
                </button>
                ${page.status === 'published' ? `
                    <a href="/landing/${page.slug}" target="_blank" class="btn btn-success">
                        <i class="fas fa-external-link-alt"></i> Ouvrir
                    </a>
                ` : ''}
                <button class="btn btn-danger" onclick="deleteLandingPage(${page.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
}

function displayPagination(pagination) {
    const paginationDiv = document.getElementById('pagination');
    if (!paginationDiv || pagination.pages <= 1) {
        paginationDiv.innerHTML = '';
        return;
    }
    
    let html = '';
    
    html += `<button ${currentPage === 1 ? 'disabled' : ''} onclick="changePage(${currentPage - 1})">
        <i class="fas fa-chevron-left"></i>
    </button>`;
    
    for (let i = 1; i <= pagination.pages; i++) {
        if (i === 1 || i === pagination.pages || (i >= currentPage - 2 && i <= currentPage + 2)) {
            html += `<button class="${i === currentPage ? 'active' : ''}" onclick="changePage(${i})">${i}</button>`;
        } else if (i === currentPage - 3 || i === currentPage + 3) {
            html += `<span>...</span>`;
        }
    }
    
    html += `<button ${currentPage === pagination.pages ? 'disabled' : ''} onclick="changePage(${currentPage + 1})">
        <i class="fas fa-chevron-right"></i>
    </button>`;
    
    paginationDiv.innerHTML = html;
}

function changePage(page) {
    currentPage = page;
    loadLandingPages();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function editLandingPage(id) {
    try {
        const data = await adminUtils.apiRequest(`api/landing-pages.php?action=single&id=${id}`);
        
        if (data.success) {
            displayLandingPageModal(data.data);
        }
    } catch (error) {
        adminUtils.showNotification('Erreur lors du chargement', 'error');
    }
}

function displayLandingPageModal(page) {
    const modal = document.getElementById('landingPageModal');
    const modalBody = document.getElementById('landingPageModalBody');
    const modalTitle = document.getElementById('modalTitle');
    
    modalTitle.textContent = page.id ? `Modifier: ${page.title}` : 'Nouvelle Landing Page';
    
    modalBody.innerHTML = `
        <form id="landingPageForm">
            <input type="hidden" id="pageId" value="${page.id || ''}">
            
            <div class="form-group">
                <label for="pageTitle">Titre *</label>
                <input type="text" id="pageTitle" value="${escapeHtml(page.title || '')}" required>
            </div>
            
            <div class="form-group">
                <label for="pageSlug">Slug (URL)</label>
                <input type="text" id="pageSlug" value="${escapeHtml(page.slug || '')}">
                <small>Généré automatiquement si vide</small>
            </div>
            
            <div class="form-group">
                <label for="pageDescription">Description</label>
                <textarea id="pageDescription" rows="3">${escapeHtml(page.description || '')}</textarea>
            </div>
            
            <div class="form-group">
                <label for="pageContent">Contenu HTML *</label>
                <textarea id="pageContent" rows="15" required>${escapeHtml(page.content || '')}</textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="pageStatus">Statut</label>
                    <select id="pageStatus">
                        <option value="draft" ${page.status === 'draft' ? 'selected' : ''}>Brouillon</option>
                        <option value="published" ${page.status === 'published' ? 'selected' : ''}>Publié</option>
                        <option value="archived" ${page.status === 'archived' ? 'selected' : ''}>Archivé</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="pageTemplate">Template</label>
                    <select id="pageTemplate">
                        <option value="default" ${page.template === 'default' ? 'selected' : ''}>Défaut</option>
                        <option value="minimal" ${page.template === 'minimal' ? 'selected' : ''}>Minimal</option>
                        <option value="modern" ${page.template === 'modern' ? 'selected' : ''}>Moderne</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="pageMetaTitle">Meta Title (SEO)</label>
                <input type="text" id="pageMetaTitle" value="${escapeHtml(page.meta_title || '')}">
            </div>
            
            <div class="form-group">
                <label for="pageMetaDescription">Meta Description (SEO)</label>
                <textarea id="pageMetaDescription" rows="2">${escapeHtml(page.meta_description || '')}</textarea>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="adminUtils.closeModal('landingPageModal')">
                    Annuler
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
            </div>
        </form>
    `;
    
    // Form submit handler
    document.getElementById('landingPageForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        await saveLandingPage();
    });
    
    adminUtils.openModal('landingPageModal');
}

async function saveLandingPage() {
    const form = document.getElementById('landingPageForm');
    const pageId = document.getElementById('pageId').value;
    
    const data = {
        title: document.getElementById('pageTitle').value,
        slug: document.getElementById('pageSlug').value,
        description: document.getElementById('pageDescription').value,
        content: document.getElementById('pageContent').value,
        status: document.getElementById('pageStatus').value,
        template: document.getElementById('pageTemplate').value,
        meta_title: document.getElementById('pageMetaTitle').value,
        meta_description: document.getElementById('pageMetaDescription').value
    };
    
    try {
        if (pageId) {
            // Update
            await adminUtils.apiRequest(`api/landing-pages.php?id=${pageId}`, {
                method: 'PUT',
                body: JSON.stringify(data)
            });
            adminUtils.showNotification('Landing page mise à jour', 'success');
        } else {
            // Create
            await adminUtils.apiRequest('api/landing-pages.php', {
                method: 'POST',
                body: JSON.stringify(data)
            });
            adminUtils.showNotification('Landing page créée', 'success');
        }
        
        adminUtils.closeModal('landingPageModal');
        loadLandingPages();
    } catch (error) {
        adminUtils.showNotification('Erreur lors de la sauvegarde', 'error');
    }
}

function openNewLandingPageModal() {
    displayLandingPageModal({});
}

async function deleteLandingPage(id) {
    const confirmed = await adminUtils.confirmDialog('Êtes-vous sûr de vouloir supprimer cette landing page ?');
    
    if (confirmed) {
        try {
            await adminUtils.apiRequest(`api/landing-pages.php?id=${id}`, {
                method: 'DELETE'
            });
            adminUtils.showNotification('Landing page supprimée', 'success');
            loadLandingPages();
        } catch (error) {
            adminUtils.showNotification('Erreur lors de la suppression', 'error');
        }
    }
}

function viewLandingPage(slug) {
    window.open(`/landing/${slug}`, '_blank');
}

function getStatusBadgeClass(status) {
    const classes = {
        'draft': 'badge-draft',
        'published': 'badge-published',
        'archived': 'badge-archived'
    };
    return classes[status] || 'badge-default';
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

// Export functions
window.editLandingPage = editLandingPage;
window.deleteLandingPage = deleteLandingPage;
window.viewLandingPage = viewLandingPage;
window.changePage = changePage;

