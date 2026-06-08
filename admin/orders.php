<?php
/**
 * Code4U V2 — Admin Orders Management Page
 */
require_once 'includes/auth.php';
requireLogin();

$pageTitle = 'Gestion des Commandes';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="admin-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1>Commandes <span id="totalBadge" class="badge badge-primary">…</span></h1>
            <p class="page-subtitle">Gérez toutes les commandes reçues via le site</p>
        </div>
        <div class="page-actions">
            <button class="btn btn-sm btn-outline" onclick="refreshOrders()">
                <i class="fa fa-refresh"></i> Actualiser
            </button>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="stats-row" id="statsRow">
        <div class="stat-card">
            <div class="stat-val" id="statTotal">—</div>
            <div class="stat-lbl">Total commandes</div>
        </div>
        <div class="stat-card pending">
            <div class="stat-val" id="statPending">—</div>
            <div class="stat-lbl">En attente</div>
        </div>
        <div class="stat-card progress">
            <div class="stat-val" id="statInProgress">—</div>
            <div class="stat-lbl">En cours</div>
        </div>
        <div class="stat-card done">
            <div class="stat-val" id="statCompleted">—</div>
            <div class="stat-lbl">Terminées</div>
        </div>
        <div class="stat-card revenue">
            <div class="stat-val" id="statRevenue">—</div>
            <div class="stat-lbl">CA réalisé</div>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="filters-bar">
        <div class="status-tabs" id="statusTabs">
            <button class="tab active" data-status="all">Toutes</button>
            <button class="tab" data-status="pending">En attente</button>
            <button class="tab" data-status="contacted">Contacté</button>
            <button class="tab" data-status="quote_sent">Devis envoyé</button>
            <button class="tab" data-status="in_progress">En cours</button>
            <button class="tab" data-status="review">Révision</button>
            <button class="tab" data-status="completed">Terminées</button>
            <button class="tab" data-status="cancelled">Annulées</button>
        </div>
        <div class="search-wrap">
            <i class="fa fa-search"></i>
            <input type="text" id="searchInput" placeholder="Rechercher par email, nom, référence…">
        </div>
    </div>

    <!-- Orders Table -->
    <div class="table-wrap">
        <table class="orders-table" id="ordersTable">
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Client</th>
                    <th>Service</th>
                    <th>Total</th>
                    <th>Statut</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="ordersTbody">
                <tr>
                    <td colspan="7" class="loading-row">
                        <i class="fa fa-spinner fa-spin"></i> Chargement…
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="pagination" id="pagination"></div>

</div><!-- /.admin-content -->

<!-- Modal Overlay -->
<div class="modal-overlay" id="modalOverlay" onclick="closeModal()"></div>

<!-- Order Detail Slide Panel -->
<div class="modal-panel" id="modalPanel">
    <div class="modal-header">
        <div class="modal-header-info">
            <h3 id="modalRef">—</h3>
            <div id="modalStatusBadge"></div>
        </div>
        <button class="modal-close" onclick="closeModal()" aria-label="Fermer">
            <i class="fa fa-times"></i>
        </button>
    </div>
    <div class="modal-body" id="modalBody">
        <!-- Filled dynamically by JS -->
    </div>
</div>

<style>
/* ============================================================
   Admin Orders Page — Styles
   ============================================================ */

/* ── Layout ─────────────────────────────────────────────────── */
.admin-content {
    padding: 2rem 2.25rem;
    max-width: 1400px;
    margin: 0 auto;
}

/* ── Page Header ─────────────────────────────────────────────── */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1.5rem;
    margin-bottom: 1.75rem;
}
.page-header h1 {
    font-size: 1.75rem;
    font-weight: 800;
    margin: 0 0 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.page-subtitle {
    color: #64748b;
    font-size: 0.9rem;
    margin: 0;
}
.page-actions {
    display: flex;
    gap: 0.75rem;
    flex-shrink: 0;
    padding-top: 0.25rem;
}

/* ── Badges ──────────────────────────────────────────────────── */
.badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.2em 0.65em;
    border-radius: 50px;
    line-height: 1;
}
.badge-primary {
    background: #4361ee;
    color: white;
}

/* ── Stat Cards ──────────────────────────────────────────────── */
.stats-row {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1rem;
    margin-bottom: 1.75rem;
}
@media (max-width: 1024px) {
    .stats-row { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 640px) {
    .stats-row { grid-template-columns: repeat(2, 1fr); }
}

.stat-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.25rem 1.4rem;
    position: relative;
    overflow: hidden;
    transition: box-shadow 0.2s, transform 0.2s;
}
.stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: #cbd5e1;
    border-radius: 14px 14px 0 0;
}
.stat-card:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    transform: translateY(-1px);
}
.stat-card.pending::before  { background: linear-gradient(90deg,#f59e0b,#d97706); }
.stat-card.progress::before { background: linear-gradient(90deg,#3b82f6,#2563eb); }
.stat-card.done::before     { background: linear-gradient(90deg,#10b981,#059669); }
.stat-card.revenue::before  { background: linear-gradient(90deg,#8b5cf6,#7c3aed); }

.stat-val {
    font-size: 1.85rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.02em;
    line-height: 1;
    margin-bottom: 0.4rem;
}
.stat-card.pending  .stat-val { color: #d97706; }
.stat-card.progress .stat-val { color: #2563eb; }
.stat-card.done     .stat-val { color: #059669; }
.stat-card.revenue  .stat-val { color: #7c3aed; }

.stat-lbl {
    font-size: 0.8rem;
    color: #94a3b8;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

/* ── Filters Bar ─────────────────────────────────────────────── */
.filters-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
}

.status-tabs {
    display: flex;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.tab {
    padding: 0.45rem 1rem;
    border-radius: 50px;
    border: 1px solid #e2e8f0;
    background: white;
    color: #475569;
    font-size: 0.83rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.18s;
    white-space: nowrap;
}
.tab:hover {
    border-color: #4361ee;
    color: #4361ee;
    background: rgba(67,97,238,0.04);
}
.tab.active {
    background: #4361ee;
    border-color: #4361ee;
    color: white;
    font-weight: 600;
}

.search-wrap {
    position: relative;
    flex-shrink: 0;
}
.search-wrap i {
    position: absolute;
    left: 0.85rem;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 0.85rem;
    pointer-events: none;
}
.search-wrap input {
    padding: 0.5rem 1rem 0.5rem 2.25rem;
    border: 1px solid #e2e8f0;
    border-radius: 50px;
    font-size: 0.875rem;
    color: #1e293b;
    outline: none;
    width: 280px;
    transition: border-color 0.18s, box-shadow 0.18s;
    background: white;
}
.search-wrap input:focus {
    border-color: #4361ee;
    box-shadow: 0 0 0 3px rgba(67,97,238,0.12);
}

/* ── Table ───────────────────────────────────────────────────── */
.table-wrap {
    overflow-x: auto;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    background: white;
    box-shadow: 0 1px 6px rgba(0,0,0,0.04);
}

.orders-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.orders-table th {
    padding: 0.9rem 1rem;
    text-align: left;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #94a3b8;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}
.orders-table th:first-child { border-radius: 16px 0 0 0; }
.orders-table th:last-child  { border-radius: 0 16px 0 0; }

.orders-table td {
    padding: 0.875rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    vertical-align: middle;
}
.orders-table tbody tr {
    cursor: pointer;
    transition: background 0.14s;
}
.orders-table tbody tr:hover td {
    background: rgba(67,97,238,0.03);
}
.orders-table tbody tr:last-child td {
    border-bottom: none;
}

.client-name  { font-weight: 600; color: #1e293b; margin-bottom: 2px; }
.client-email { font-size: 0.8rem; color: #94a3b8; }

.loading-row {
    text-align: center;
    padding: 3.5rem 1rem;
    color: #94a3b8;
    font-size: 0.95rem;
}

/* ── Status Badge ────────────────────────────────────────────── */
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.3em 0.85em;
    border-radius: 50px;
    font-size: 0.775rem;
    font-weight: 600;
    white-space: nowrap;
    letter-spacing: 0.01em;
}

/* ── Pagination ──────────────────────────────────────────────── */
.pagination {
    display: flex;
    justify-content: center;
    gap: 0.4rem;
    margin-top: 1.5rem;
    flex-wrap: wrap;
}
.page-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: white;
    color: #475569;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.page-btn:hover { border-color: #4361ee; color: #4361ee; }
.page-btn.active { background: #4361ee; border-color: #4361ee; color: white; font-weight: 700; }

/* ── Buttons (local overrides) ───────────────────────────────── */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 1.1rem;
    border-radius: 50px;
    border: none;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.18s;
    text-decoration: none;
}
.btn-sm     { padding: 0.35rem 0.85rem; font-size: 0.8rem; }
.btn-primary { background: linear-gradient(135deg,#4361ee,#7209b7); color: white; }
.btn-primary:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(67,97,238,0.35); }
.btn-outline { border: 1px solid #e2e8f0; background: white; color: #475569; }
.btn-outline:hover { border-color: #4361ee; color: #4361ee; }

/* ── Modal Overlay ───────────────────────────────────────────── */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,0.5);
    z-index: 900;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s;
    backdrop-filter: blur(2px);
}
.modal-overlay.open {
    opacity: 1;
    pointer-events: all;
}

/* ── Modal Panel (slide-in from right) ───────────────────────── */
.modal-panel {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    width: 500px;
    max-width: 100vw;
    background: white;
    z-index: 901;
    transform: translateX(100%);
    transition: transform 0.35s cubic-bezier(0.4,0,0.2,1);
    display: flex;
    flex-direction: column;
    box-shadow: -8px 0 40px rgba(0,0,0,0.14);
}
.modal-panel.open {
    transform: translateX(0);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 1.5rem 1.75rem;
    border-bottom: 1px solid #f1f5f9;
    flex-shrink: 0;
    gap: 1rem;
}
.modal-header-info h3 {
    margin: 0 0 0.4rem;
    font-size: 1rem;
    font-weight: 700;
    color: #1e293b;
    font-family: 'Courier New', monospace;
}
.modal-close {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: white;
    color: #64748b;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    transition: all 0.15s;
    flex-shrink: 0;
}
.modal-close:hover { background: #fee2e2; border-color: #fca5a5; color: #dc2626; }

.modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem 1.75rem;
}

/* ── Modal detail sections ───────────────────────────────────── */
.detail-section {
    margin-bottom: 1.75rem;
    padding-bottom: 1.75rem;
    border-bottom: 1px solid #f1f5f9;
}
.detail-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}
.detail-section h4 {
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #94a3b8;
    font-weight: 600;
    margin: 0 0 0.9rem;
}
.detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.65rem;
}
.detail-item {
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 10px;
    padding: 0.65rem 0.85rem;
}
.detail-item.full {
    grid-column: 1 / -1;
}
.detail-item span {
    display: block;
    font-size: 0.72rem;
    color: #94a3b8;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 0.25rem;
}
.detail-item strong, .detail-item a {
    font-size: 0.88rem;
    font-weight: 600;
    color: #1e293b;
    text-decoration: none;
}
.detail-item a { color: #4361ee; }
.desc-box {
    background: #f8fafc;
    border-radius: 10px;
    padding: 0.85rem;
    font-size: 0.875rem;
    color: #475569;
    line-height: 1.65;
    margin: 0.75rem 0 0;
    white-space: pre-wrap;
    word-break: break-word;
}

/* ── Status Update Form ──────────────────────────────────────── */
.status-update-form {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.admin-select, .admin-textarea {
    width: 100%;
    padding: 0.6rem 0.9rem;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    font-size: 0.875rem;
    color: #1e293b;
    outline: none;
    background: white;
    transition: border-color 0.18s, box-shadow 0.18s;
    font-family: inherit;
    box-sizing: border-box;
}
.admin-select:focus, .admin-textarea:focus {
    border-color: #4361ee;
    box-shadow: 0 0 0 3px rgba(67,97,238,0.12);
}
.admin-textarea {
    min-height: 90px;
    resize: vertical;
}

/* ── History List ────────────────────────────────────────────── */
.history-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.history-item {
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 10px;
    padding: 0.65rem 0.85rem;
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 0.5rem 1rem;
    align-items: center;
    font-size: 0.82rem;
}
.history-status {
    font-weight: 700;
    color: #4361ee;
    white-space: nowrap;
}
.history-note {
    color: #64748b;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.history-date {
    color: #94a3b8;
    font-size: 0.75rem;
    white-space: nowrap;
}
</style>

<script>
/* ============================================================
   Admin Orders — Client-Side Logic
   ============================================================ */

let currentPage   = 1;
let currentStatus = 'all';
let currentSearch = '';
let searchTimeout = null;
let currentOrder  = null;

// ── Status colour map ─────────────────────────────────────────
const statusColors = {
    pending:    { bg: '#fef3c7', color: '#92400e', label: 'En attente'   },
    contacted:  { bg: '#dbeafe', color: '#1e40af', label: 'Contacté'     },
    quote_sent: { bg: '#cffafe', color: '#155e75', label: 'Devis envoyé' },
    in_progress:{ bg: '#fed7aa', color: '#9a3412', label: 'En cours'     },
    review:     { bg: '#ede9fe', color: '#5b21b6', label: 'En révision'  },
    completed:  { bg: '#d1fae5', color: '#065f46', label: 'Terminé'      },
    cancelled:  { bg: '#fee2e2', color: '#991b1b', label: 'Annulée'      },
};

function statusBadge(status) {
    const s = statusColors[status] || { bg: '#f1f5f9', color: '#475569', label: status };
    return `<span class="status-badge" style="background:${s.bg};color:${s.color}">${s.label}</span>`;
}

function formatPrice(n) {
    return parseFloat(n).toLocaleString('fr-FR', { minimumFractionDigits: 0 }) + ' €';
}

function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('fr-FR', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
}

// ── Fetch & render orders list ────────────────────────────────
async function fetchOrders() {
    const params = new URLSearchParams({
        action: 'list',
        status: currentStatus,
        search: currentSearch,
        page:   currentPage,
    });
    try {
        const res  = await fetch('api/orders.php?' + params);
        const data = await res.json();
        if (!data.success) return;

        document.getElementById('totalBadge').textContent = data.total;

        const tbody = document.getElementById('ordersTbody');
        if (!data.orders || !data.orders.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="loading-row">Aucune commande trouvée</td></tr>';
            document.getElementById('pagination').innerHTML = '';
            return;
        }

        tbody.innerHTML = data.orders.map(o => `
            <tr onclick="openOrder(${o.id})">
                <td><code style="font-size:0.8rem;color:#4361ee;font-weight:700">${o.reference}</code></td>
                <td>
                    <div class="client-name">${escHtml(o.client_firstname || '')} ${escHtml(o.client_lastname || '')}</div>
                    <div class="client-email">${escHtml(o.client_email)}</div>
                </td>
                <td>${escHtml(o.service_name)}</td>
                <td style="font-weight:700;white-space:nowrap">${formatPrice(o.total_price)}</td>
                <td>${statusBadge(o.status)}</td>
                <td style="white-space:nowrap;color:#64748b;font-size:0.82rem">${formatDate(o.created_at)}</td>
                <td>
                    <button class="btn btn-sm btn-outline"
                            onclick="event.stopPropagation();openOrder(${o.id})"
                            title="Voir le détail">
                        <i class="fa fa-eye"></i>
                    </button>
                </td>
            </tr>
        `).join('');

        renderPagination(data.page, data.pages);
    } catch (err) {
        console.error('[fetchOrders]', err);
    }
}

// ── Fetch & render stats ──────────────────────────────────────
async function fetchStats() {
    try {
        const res  = await fetch('api/orders.php?action=stats');
        const data = await res.json();
        if (!data.success) return;
        document.getElementById('statTotal').textContent      = data.total;
        document.getElementById('statPending').textContent    = data.pending;
        document.getElementById('statInProgress').textContent = data.in_progress;
        document.getElementById('statCompleted').textContent  = data.completed;
        document.getElementById('statRevenue').textContent    = formatPrice(data.revenue);
    } catch (err) {
        console.error('[fetchStats]', err);
    }
}

// ── Open order detail modal ───────────────────────────────────
async function openOrder(id) {
    try {
        const res  = await fetch('api/orders.php?action=get&id=' + id);
        const data = await res.json();
        if (!data.success) { alert('Erreur: ' + (data.error || 'Commande introuvable')); return; }

        currentOrder = data.order;
        const o = data.order;

        document.getElementById('modalRef').textContent = o.reference;
        document.getElementById('modalStatusBadge').innerHTML = statusBadge(o.status);

        // Options
        let optionsHtml = '';
        try {
            const opts = JSON.parse(o.options || '[]');
            if (opts && opts.length) {
                optionsHtml = '<ul style="margin:0.5rem 0 0;padding-left:1.2rem">'
                    + opts.map(op => `<li style="font-size:0.875rem;color:#475569;padding:2px 0">${escHtml(op.name || '')} — +${formatPrice(op.price || 0)}</li>`).join('')
                    + '</ul>';
            }
        } catch(e) {}

        // History
        let histHtml = '';
        if (o.history && o.history.length) {
            histHtml = `
                <div class="detail-section">
                    <h4>Historique des statuts</h4>
                    <div class="history-list">
                        ${o.history.map(h => `
                            <div class="history-item">
                                <span class="history-status">${escHtml(h.new_status)}</span>
                                <span class="history-note">${escHtml(h.note || '')}</span>
                                <span class="history-date">${formatDate(h.changed_at)}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>`;
        }

        // Status select options
        const statusOptions = Object.entries(statusColors)
            .map(([k, v]) => `<option value="${k}" ${k === o.status ? 'selected' : ''}>${v.label}</option>`)
            .join('');

        document.getElementById('modalBody').innerHTML = `
            <!-- Project info -->
            <div class="detail-section">
                <h4>Projet</h4>
                <div class="detail-grid">
                    <div class="detail-item"><span>Service</span><strong>${escHtml(o.service_name)}</strong></div>
                    <div class="detail-item"><span>Délai</span><strong>${escHtml(o.deadline)}</strong></div>
                    <div class="detail-item"><span>Prix de base</span><strong>${formatPrice(o.base_price)}</strong></div>
                    <div class="detail-item"><span>Options</span><strong>${formatPrice(o.options_price)}</strong></div>
                    <div class="detail-item full"><span>Total estimé</span><strong style="color:#4361ee;font-size:1.25rem">${formatPrice(o.total_price)}</strong></div>
                </div>
                ${optionsHtml}
                ${o.description ? `<p class="desc-box">${escHtml(o.description)}</p>` : ''}
            </div>

            <!-- Client info -->
            <div class="detail-section">
                <h4>Client</h4>
                <div class="detail-grid">
                    <div class="detail-item"><span>Nom</span><strong>${escHtml((o.client_firstname || '') + ' ' + (o.client_lastname || ''))}</strong></div>
                    <div class="detail-item"><span>Email</span><a href="mailto:${escHtml(o.client_email)}">${escHtml(o.client_email)}</a></div>
                    <div class="detail-item"><span>Téléphone</span><a href="tel:${escHtml(o.client_phone || '')}">${escHtml(o.client_phone || '—')}</a></div>
                    <div class="detail-item"><span>Entreprise</span><strong>${escHtml(o.client_company || '—')}</strong></div>
                    <div class="detail-item"><span>Source</span><strong>${escHtml(o.client_source || '—')}</strong></div>
                    <div class="detail-item"><span>Commandé le</span><strong>${formatDate(o.created_at)}</strong></div>
                </div>
            </div>

            <!-- Status update -->
            <div class="detail-section">
                <h4>Mettre à jour le statut</h4>
                <div class="status-update-form">
                    <select id="newStatus" class="admin-select">${statusOptions}</select>
                    <textarea id="statusNote" class="admin-textarea" placeholder="Note interne (optionnel)…">${escHtml(o.admin_notes || '')}</textarea>
                    <button class="btn btn-primary btn-sm" onclick="updateStatus()">
                        <i class="fa fa-save"></i> Enregistrer
                    </button>
                </div>
            </div>

            ${histHtml}
        `;

        document.getElementById('modalPanel').classList.add('open');
        document.getElementById('modalOverlay').classList.add('open');
        document.body.style.overflow = 'hidden';

    } catch (err) {
        console.error('[openOrder]', err);
    }
}

// ── Update order status ───────────────────────────────────────
async function updateStatus() {
    if (!currentOrder) return;
    const status = document.getElementById('newStatus').value;
    const note   = document.getElementById('statusNote').value;

    try {
        const res  = await fetch('api/orders.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action: 'update_status', id: currentOrder.id, status, note }),
        });
        const data = await res.json();
        if (data.success) {
            closeModal();
            fetchOrders();
            fetchStats();
        } else {
            alert('Erreur: ' + (data.error || 'Impossible de mettre à jour le statut'));
        }
    } catch (err) {
        console.error('[updateStatus]', err);
    }
}

// ── Close modal ───────────────────────────────────────────────
function closeModal() {
    document.getElementById('modalPanel').classList.remove('open');
    document.getElementById('modalOverlay').classList.remove('open');
    document.body.style.overflow = '';
}

// ── Pagination ────────────────────────────────────────────────
function renderPagination(page, pages) {
    const el = document.getElementById('pagination');
    if (pages <= 1) { el.innerHTML = ''; return; }
    let html = '';
    const start = Math.max(1, page - 2);
    const end   = Math.min(pages, page + 2);
    if (start > 1)     html += `<button class="page-btn" onclick="goPage(1)">1</button>`;
    if (start > 2)     html += `<span style="align-self:center;color:#94a3b8;padding:0 4px">…</span>`;
    for (let i = start; i <= end; i++) {
        html += `<button class="page-btn ${i === page ? 'active' : ''}" onclick="goPage(${i})">${i}</button>`;
    }
    if (end < pages - 1) html += `<span style="align-self:center;color:#94a3b8;padding:0 4px">…</span>`;
    if (end < pages)     html += `<button class="page-btn" onclick="goPage(${pages})">${pages}</button>`;
    el.innerHTML = html;
}

function goPage(p) { currentPage = p; fetchOrders(); }
function refreshOrders() { fetchOrders(); fetchStats(); }

// ── XSS helper ───────────────────────────────────────────────
function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

// ── Tab clicks ────────────────────────────────────────────────
document.getElementById('statusTabs').addEventListener('click', function(e) {
    const tab = e.target.closest('.tab');
    if (!tab) return;
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    currentStatus = tab.dataset.status;
    currentPage   = 1;
    fetchOrders();
});

// ── Search with debounce ──────────────────────────────────────
document.getElementById('searchInput').addEventListener('input', function(e) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(function() {
        currentSearch = e.target.value.trim();
        currentPage   = 1;
        fetchOrders();
    }, 350);
});

// ── Keyboard: Escape closes modal ─────────────────────────────
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});

// ── Init ──────────────────────────────────────────────────────
fetchOrders();
fetchStats();
</script>

</body>
</html>
