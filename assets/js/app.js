/* ============================================================
   MikroTik WireGuard Peer Manager — Frontend Logic
   AppConfig is injected inline by index.php before this file.
   ============================================================ */

'use strict';

/* ── Constants ───────────────────────────────────────────────── */
const TOAST_DURATION = 5000;
const HIGHLIGHT_DURATION = 10000;
const PAGE_SIZE = (AppConfig.pageSize > 0) ? AppConfig.pageSize : 0;

/* ── i18n helper ─────────────────────────────────────────────── */
function t(key) {
    return AppConfig.translations?.[key] || key;
}

/* ── API URL helper ──────────────────────────────────────────── */
function apiUrl(action) {
    return 'src/api.php?action=' + encodeURIComponent(action) + '&server=' + encodeURIComponent(AppConfig.serverKey);
}

/* ── API POST wrapper (fetch + CSRF + JSON) ────────────────── */
async function apiPost(action, payload) {
    const res = await fetch(apiUrl(action), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': AppConfig.csrfToken },
        body: JSON.stringify(payload)
    });
    return await res.json();
}

/* ── Server switch ───────────────────────────────────────────── */
function switchServer(serverKey) {
    window.location.href = '?server=' + encodeURIComponent(serverKey);
}

/* ── State ──────────────────────────────────────────────────── */
let allPeers = [];
let peerToDeleteId = null;
let currentSort = { field: 'name', dir: 'asc' };
let hideOffline = localStorage.getItem('hideOffline') !== 'false';
let searchQuery = localStorage.getItem('searchQuery') || '';
let highlightId = null;
let pendingHighlightId = null;
let currentPage = 1;
let currentTotalPages = 1;
let lastFocusedElement = null;


/* ── Init ───────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('hideOfflineBtn');
    const label = document.getElementById('hideOfflineLabel');
    if (!hideOffline) {
        btn.classList.remove('btn-active');
        label.innerText = t('js.hide_offline');
    } else {
        btn.classList.add('btn-active');
        label.innerText = t('js.show_all');
    }

    document.getElementById('searchInput').value = searchQuery;
    initPeers();
    document.getElementById('searchInput').addEventListener('input', () => { currentPage = 1; applyFiltersAndSort(); });
    document.getElementById('hideOfflineBtn').addEventListener('click', toggleHideOffline);
    document.getElementById('serverSelector').addEventListener('change', (e) => switchServer(e.target.value));
    document.getElementById('addPeerForm').addEventListener('submit', submitAddPeer);
    document.getElementById('editPeerForm').addEventListener('submit', submitEditPeer);
    document.addEventListener('click', handleDelegateClick);
    document.addEventListener('keydown', handleDelegateKeydown);

    // Auto-refresh (pause when modals are open)
    setInterval(() => {
        if (!document.querySelector('.modal-backdrop.active')) refreshPeers();
    }, AppConfig.refreshInterval);
});

function handleDelegateClick(e) {
    const el = e.target.closest('[data-action]');
    if (!el) return;
    const action = el.dataset.action;
    switch (action) {
        case 'open-add': openAddModal(); break;
        case 'open-about': openModal('aboutModalBackdrop'); break;
        case 'open-export-vpn': openExportVpnIpsModal(); break;
        case 'sort': sortPeers(el.dataset.sort); break;
        case 'prev-page': goToPage(currentPage - 1); break;
        case 'next-page': goToPage(currentPage + 1); break;
        case 'close-modal': closeModalById(el.dataset.modal); break;
        case 'add-tab': switchAddTab(el.dataset.tab); break;
        case 'export-tab': switchExportTab(el.dataset.tab); break;
        case 'copy-code': copyToClipboard(el.dataset.target); break;
        case 'copy-name': copyNameToClipboard(el.dataset.value); break;
        case 'copy-ip': copyIp(el.dataset.value); break;
        case 'copy-dnat': copyDnatPort(el.dataset.value); break;
        case 'export-peer': openExportModal(el.dataset.id, el.dataset.name, el.dataset.ip); break;
        case 'toggle-peer': togglePeer(el.dataset.id, el.dataset.disabled === 'true'); break;
        case 'edit-peer': openEditModal(el.dataset.id, el.dataset.name); break;
        case 'delete-peer': openDeleteModal(el.dataset.id, el.dataset.name); break;
        case 'confirm-delete': submitDeletePeer(peerToDeleteId); break;
        case 'confirm-action': {
            const cb = confirmCallback;
            closeConfirmModal();
            if (typeof cb === 'function') cb();
            break;
        }
        case 'regenerate-key': regenerateKey(); break;
        case 'export-vpn-ips': submitExportVpnIps(); break;
    }
}

function handleDelegateKeydown(e) {
    if (e.key === 'Escape') {
        const topModal = document.querySelector('.modal-backdrop.active');
        if (topModal) {
            e.preventDefault();
            closeModalById(topModal.id);
        }
        return;
    }
    const sortable = e.target.closest('[data-action="sort"]');
    if (sortable && (e.key === 'Enter' || e.key === ' ')) {
        e.preventDefault();
        sortPeers(sortable.dataset.sort);
    }
}

/* ── Data Loading ───────────────────────────────────────────── */
// Seed the table from the peers already fetched by the server-side
// connection test, avoiding a duplicate get_peers round-trip on first load.
// When $initialPeers was null (connection failure) the API is queried instead.
function initPeers() {
    if (Array.isArray(AppConfig.initialPeers)) {
        allPeers = AppConfig.initialPeers;
        currentPage = 1;
        applyFiltersAndSort();
        highlightPendingPeer();
        fetchInterfaceStatus();
        updateSortIcons();
    } else {
        loadPeers().then(() => updateSortIcons());
    }
}

async function loadPeers() {
    const loader = document.getElementById('tableLoader');
    loader.classList.add('active');
    try {
        const res = await fetch(apiUrl('get_peers'));
        const data = await res.json();
        if (data.success) {
            allPeers = data.peers;
            currentPage = 1;
            applyFiltersAndSort();
            highlightPendingPeer();
            fetchInterfaceStatus();
        } else {
            showToast(t('js.load_error').replace('%s', data.error), true);
        }
    } catch {
        showToast(t('js.connection_error'), true);
    } finally {
        loader.classList.remove('active');
    }
}

// Auto-refresh: silent, no loader flicker
async function refreshPeers() {
    try {
        const res = await fetch(apiUrl('get_peers'));
        const data = await res.json();
        if (data.success) {
            allPeers = data.peers;
            applyFiltersAndSort();
            fetchInterfaceStatus();
        }
    } catch {
        // silently ignore errors on auto-refresh
    }
}

function highlightPendingPeer() {
    if (pendingHighlightId) {
        highlightId = pendingHighlightId;
        pendingHighlightId = null;
    }
}

/* ── Interface Status ─────────────────────────────────────────── */
async function fetchInterfaceStatus() {
    try {
        const res = await fetch(apiUrl('get_interface_status'));
        const data = await res.json();
        if (data.success && data.interface) {
            updateInterfaceStatusUI(data.interface);
        }
    } catch {
        // silently ignore
    }
}

function updateInterfaceStatusUI(iface) {
    const label = document.getElementById('wgInterfaceLabel');
    if (!label) return;

    label.style.display = 'inline';
    if (iface.running) {
        // Show the listen port only when it differs from the configured
        // endpoint port (otherwise it is redundant with "Public Endpoint").
        const port = parseInt(iface['listen-port'] || 0, 10);
        const showPort = port > 0 && port !== AppConfig.endpointPort;
        const portSuffix = showPort ? `:${port}` : '';
        const portTitle = showPort ? ` title="${t('js.interface_listen_port')}"` : '';
        label.innerHTML = `<span class="badge-active"${portTitle}>${escapeHtml(iface.name)}${portSuffix}</span>`;
    } else {
        label.innerHTML = `<span class="badge-disabled">${t('js.interface_disabled')}</span>`;
    }
}

/* ── Filter + Sort pipeline ─────────────────────────────────── */
function isPeerActive(peer) {
    if (peer.disabled) return false;
    const handshake = peer['handshake_formatted'] || 'never';
    return handshakeToSeconds(handshake) < (AppConfig.handshakeTimeout ?? 300);
}

function applyFiltersAndSort() {
    const peersCard = document.getElementById('stat-total-peers')?.closest('.stat-card');
    const maxPeers = peersCard ? parseInt(peersCard.dataset.maxPeers) : null;
    document.getElementById('stat-total-peers').innerText = allPeers.length;

    const query = document.getElementById('searchInput').value.toLowerCase().trim();
    if (searchQuery !== query) {
        searchQuery = query;
        localStorage.setItem('searchQuery', query);
    }
    let result = allPeers;

    if (query) {
        result = result.filter(p =>
            (p.name || '').toLowerCase().includes(query) ||
            (p['allowed-address'] || '').toLowerCase().includes(query)
        );
    }

    if (hideOffline) {
        result = result.filter(isPeerActive);
    }

    renderPeers(getSortedPeers(result));
}

function toggleHideOffline() {
    hideOffline = !hideOffline;
    localStorage.setItem('hideOffline', hideOffline);
    const btn = document.getElementById('hideOfflineBtn');
    const label = document.getElementById('hideOfflineLabel');
    if (hideOffline) {
        btn.classList.add('btn-active');
        label.innerText = t('js.show_all');
    } else {
        btn.classList.remove('btn-active');
        label.innerText = t('js.hide_offline');
    }
    currentPage = 1;
    applyFiltersAndSort();
}

/* ── Sorting ────────────────────────────────────────────────── */
function sortPeers(field) {
    if (currentSort.field === field) {
        currentSort.dir = currentSort.dir === 'asc' ? 'desc' : 'asc';
    } else {
        currentSort.field = field;
        currentSort.dir = 'asc';
    }
    updateSortIcons();
    currentPage = 1;
    applyFiltersAndSort();
}

function handshakeToSeconds(h) {
    if (!h || h === 'never') return Infinity;
    let total = 0;
    const d = h.match(/(\d+)d/); if (d) total += parseInt(d[1]) * 86400;
    const hr = h.match(/(\d+)h/); if (hr) total += parseInt(hr[1]) * 3600;
    const m = h.match(/(\d+)m/); if (m) total += parseInt(m[1]) * 60;
    const s = h.match(/(\d+)s/); if (s) total += parseInt(s[1]);
    return total;
}

function dnatPort(ip) {
    const parts = (ip || '').split('.');
    return AppConfig.dnatBase + parseInt(parts[2] || 0) * AppConfig.dnatMultiplier + parseInt(parts[3] || 0);
}

function getSortedPeers(peers) {
    if (!currentSort.field) return peers;
    return [...peers].sort((a, b) => {
        let va, vb;
        if (currentSort.field === 'name') {
            va = (a.name || '').toLowerCase();
            vb = (b.name || '').toLowerCase();
        } else if (currentSort.field === 'handshake') {
            va = handshakeToSeconds(a['handshake_formatted']);
            vb = handshakeToSeconds(b['handshake_formatted']);
        } else if (currentSort.field === 'dnat') {
            va = dnatPort(a['allowed-address']);
            vb = dnatPort(b['allowed-address']);
        } else {
            va = ipToNum(a['allowed-address'] || '');
            vb = ipToNum(b['allowed-address'] || '');
        }
        if (va < vb) return currentSort.dir === 'asc' ? -1 : 1;
        if (va > vb) return currentSort.dir === 'asc' ? 1 : -1;
        return 0;
    });
}

function ipToNum(addr) {
    const ip = (addr.split('/')[0] || '0.0.0.0').split('.');
    if (ip.length !== 4) return 0;
    return ip.reduce((acc, oct) => {
        const n = parseInt(oct, 10);
        if (isNaN(n) || n < 0 || n > 255) return 0;
        return (acc << 8) + n;
    }, 0) >>> 0;
}

function updateSortIcons() {
    ['name', 'ip', 'dnat', 'handshake'].forEach(f => {
        const th = document.getElementById('th-' + f);
        const icon = document.getElementById('sort-' + f + '-icon');
        if (!th || !icon) return;
        th.classList.remove('sort-active');
        icon.textContent = '↕';
    });
    if (currentSort.field) {
        document.getElementById('th-' + currentSort.field)?.classList.add('sort-active');
        const icon = document.getElementById('sort-' + currentSort.field + '-icon');
        if (icon) icon.textContent = currentSort.dir === 'asc' ? '↑' : '↓';
    }
}

/* ── Render ─────────────────────────────────────────────────── */
function renderPeers(peers) {
    const tbody = document.getElementById('peersTableBody');
    const emptyState = document.getElementById('emptyState');
    tbody.innerHTML = '';

    if (peers.length === 0) {
        const emptyTitle = document.getElementById('emptyStateTitle');
        const emptyDesc = document.getElementById('emptyStateDesc');
        if (allPeers.length > 0) {
            emptyTitle.innerText = t('js.empty_no_results_title');
            emptyDesc.innerText = t('js.empty_no_results_desc');
        } else {
            emptyTitle.innerText = t('js.empty_title');
            emptyDesc.innerText = t('js.empty_description');
        }
        emptyState.style.display = 'flex';
        document.getElementById('stat-active-peers').innerText = '0';
        document.getElementById('pagination').style.display = 'none';
        currentTotalPages = 1;
        return;
    }

    emptyState.style.display = 'none';

    const totalPages = PAGE_SIZE > 0 ? Math.ceil(peers.length / PAGE_SIZE) || 1 : 1;
    currentTotalPages = totalPages;
    if (currentPage > totalPages) currentPage = totalPages;
    const pagePeers = PAGE_SIZE > 0 ? peers.slice((currentPage - 1) * PAGE_SIZE, currentPage * PAGE_SIZE) : peers;

    let activeCount = 0;
    // Count active from full list for accurate stats
    peers.forEach(p => { if (isPeerActive(p)) activeCount++; });

    pagePeers.forEach(peer => {
        const handshake = peer['handshake_formatted'] || 'never';
        let isActive = isPeerActive(peer);

        const endpoint = peer['current-endpoint-address'] || t('js.endpoint_na');
        const ip = (peer['allowed-address'] || '').split('/')[0];
        const peerName = peer.name || t('js.unnamed');
        const peerId = peer['.id'] || '';
        const allowedAddress = peer['allowed-address'] || '';

        const tr = document.createElement('tr');
        tr.setAttribute('data-peer-ip', ip);
        if (peer.disabled) tr.classList.add('row-disabled');
        tr.innerHTML = `
            <td data-label="${t('js.col_name')}">
                <button type="button" class="peer-name btn-copy" data-action="copy-name" data-value="${escapeHtml(peerName)}" title="${t('js.copy_name_title')}" aria-label="${t('js.copy_name_title')}">${escapeHtml(peerName)}</button>
                ${peer.disabled ? `<span class="badge-disabled">${t('js.disabled_badge')}</span>` : ''}
            </td>
            <td data-label="${t('js.col_ip')}">
                <button type="button" class="peer-ip-badge btn-copy" data-action="copy-ip" data-value="${escapeHtml(ip)}" title="${t('js.copy_ip_title')}" aria-label="${t('js.copy_ip_title')}">${escapeHtml(ip)}</button>
            </td>
            ${AppConfig.showDnatColumn ? `<td data-label="${t('js.col_dnat_port')}">
                <button type="button" class="peer-ip-badge btn-copy" data-action="copy-dnat" data-value="${escapeHtml(ip)}" title="${t('js.copy_port_title')}" aria-label="${t('js.copy_port_title')}">${dnatPort(ip)}</button>
            </td>` : ''}
            <td data-label="${t('js.col_handshake')}">
                <div class="handshake-cell">
                    <span class="handshake-pulse ${isActive ? 'active' : ''}"></span>
                    <span class="handshake-dot-inactive"></span>
                    <span>${escapeHtml(handshake)}</span>
                </div>
            </td>
            <td data-label="${t('js.col_endpoint')}">
                <span style="font-family:monospace;color:${peer['current-endpoint-address'] ? 'var(--text-color)' : 'var(--text-muted)'};">
                    ${escapeHtml(endpoint)}
                </span>
            </td>
            ${AppConfig.showTrafficColumn ? `<td data-label="${t('js.col_traffic')}">
                <div class="traffic-info">
                    <span class="traffic-val">↓ ${escapeHtml(peer.rx_formatted)}</span>
                    <span class="traffic-val">↑ ${escapeHtml(peer.tx_formatted)}</span>
                </div>
            </td>` : ''}
            <td data-label="${t('js.col_actions')}" class="text-right">
                <div class="actions-cell">
                    <button class="icon-btn" data-action="export-peer" data-id="${escapeHtml(peerId)}" data-name="${escapeHtml(peerName)}" data-ip="${escapeHtml(allowedAddress)}" title="${t('js.download_title')}" aria-label="${t('js.download_title')}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    </button>
                    <button class="icon-btn ${peer.disabled ? '' : 'icon-btn-toggle-on'}" data-action="toggle-peer" data-id="${escapeHtml(peerId)}" data-disabled="${peer.disabled}" title="${peer.disabled ? t('js.toggle_enable_title') : t('js.toggle_disable_title')}" aria-label="${peer.disabled ? t('js.toggle_enable_title') : t('js.toggle_disable_title')}">
                        ${peer.disabled
                ? '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z"/></svg>'
                : '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 7.5A2.25 2.25 0 0 1 7.5 5.25h9a2.25 2.25 0 0 1 2.25 2.25v9a2.25 2.25 0 0 1-2.25 2.25h-9A2.25 2.25 0 0 1 5.25 16.5v-9Z"/></svg>'}
                    </button>
                    <button class="icon-btn" data-action="edit-peer" data-id="${escapeHtml(peerId)}" data-name="${escapeHtml(peerName)}" title="${t('js.edit_title')}" aria-label="${t('js.edit_title')}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>
                    </button>
                    <button class="icon-btn icon-btn-danger" data-action="delete-peer" data-id="${escapeHtml(peerId)}" data-name="${escapeHtml(peerName)}" title="${t('js.delete_title')}" aria-label="${t('js.delete_title')}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                    </button>
                </div>
            </td>`;
        tbody.appendChild(tr);
    });

    if (highlightId) {
        const targetRow = tbody.querySelector(`tr[data-peer-ip="${highlightId}"]`);
        if (targetRow) {
            targetRow.classList.add('highlight-new');
            setTimeout(() => {
                targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 100);
            setTimeout(() => {
                highlightId = null;
                targetRow.classList.remove('highlight-new');
            }, 10000);
        } else {
            highlightId = null;
        }
    }

    document.getElementById('stat-active-peers').innerText = activeCount;
    renderPagination(currentPage, totalPages, peers.length);
}

function renderPagination(page, totalPages, totalPeers) {
    const pagination = document.getElementById('pagination');
    if (totalPages <= 1) {
        pagination.style.display = 'none';
        return;
    }
    pagination.style.display = 'flex';
    document.getElementById('paginationInfo').innerText =
        t('js.pagination_info').replace('%d', page).replace('%d', totalPages).replace('%d', totalPeers);
    document.getElementById('prevPageBtn').disabled = page <= 1;
    document.getElementById('nextPageBtn').disabled = page >= totalPages;
}

function goToPage(page) {
    if (PAGE_SIZE <= 0) return;
    // Use the page count of the currently filtered list, not of all peers,
    // so pagination stays consistent while filters are active.
    if (page < 1 || page > currentTotalPages) return;
    currentPage = page;
    applyFiltersAndSort();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* ── Session check ───────────────────────────────────────────── */
async function checkSession() {
    try {
        const res = await fetch(apiUrl('check_session'));
        const data = await res.json();
        if (!data.success) {
            window.location.href = 'login.php';
            return false;
        }
        return true;
    } catch {
        window.location.href = 'login.php';
        return false;
    }
}

async function withSessionCheck(fn) {
    if (!(await checkSession())) return;
    fn();
}

/* ── Focus trap ──────────────────────────────────────────────── */
function trapFocus(modalElement) {
    const focusable = modalElement.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    const firstFocusable = focusable[0];
    const lastFocusable = focusable[focusable.length - 1];

    function handleKeydown(e) {
        if (e.key !== 'Tab') return;
        if (e.shiftKey) {
            if (document.activeElement === firstFocusable) {
                e.preventDefault();
                lastFocusable.focus();
            }
        } else {
            if (document.activeElement === lastFocusable) {
                e.preventDefault();
                firstFocusable.focus();
            }
        }
    }

    modalElement.addEventListener('keydown', handleKeydown);
    // Store reference for cleanup
    modalElement._trapFocusHandler = handleKeydown;

    // Focus first element
    if (firstFocusable) firstFocusable.focus();
}

function releaseFocus(modalElement) {
    const handler = modalElement._trapFocusHandler;
    if (handler) {
        modalElement.removeEventListener('keydown', handler);
        delete modalElement._trapFocusHandler;
    }
}

/* ── Generic modal helpers ──────────────────────────────────── */
function openModal(id) {
    const backdrop = document.getElementById(id);
    if (!backdrop) return;
    lastFocusedElement = document.activeElement;
    backdrop.classList.add('active');
    backdrop.setAttribute('aria-hidden', 'false');
    trapFocus(backdrop);
}

function closeModal(id) {
    const backdrop = document.getElementById(id);
    if (!backdrop) return;
    backdrop.classList.remove('active');
    backdrop.setAttribute('aria-hidden', 'true');
    releaseFocus(backdrop);
    if (lastFocusedElement) lastFocusedElement.focus();
    lastFocusedElement = null;
}

function closeAboutModal() {
    closeModal('aboutModalBackdrop');
}

const CLOSE_HANDLERS = {
    'addModalBackdrop': closeAddModal,
    'editModalBackdrop': closeEditModal,
    'deleteModalBackdrop': closeDeleteModal,
    'confirmModalBackdrop': closeConfirmModal,
    'exportModalBackdrop': closeExportModal,
    'exportVpnIpsModalBackdrop': closeExportVpnIpsModal,
    'aboutModalBackdrop': closeAboutModal
};

function closeModalById(id) {
    const fn = CLOSE_HANDLERS[id];
    if (fn) fn();
}

/* ── Add Peer Modal ─────────────────────────────────────────── */
async function openAddModal() {
    if (!(await checkSession())) return;
    pendingHighlightId = null;
    highlightId = null;
    document.getElementById('modalFormContent').style.display = 'block';
    document.getElementById('modalResultContent').style.display = 'none';
    document.getElementById('modalFooterActions').style.display = 'flex';
    document.getElementById('addQrTab').disabled = true;
    clearQrCode('addQrCode');
    document.getElementById('peerName').value = '';
    const submitBtn = document.getElementById('btnSubmitAdd');
    submitBtn.disabled = false;
    submitBtn.innerText = submitBtn.dataset.origText || 'Create Peer';
    openModal('addModalBackdrop');
    document.getElementById('peerName').focus();
}

function closeAddModal() {
    clearQrCode('addQrCode');
    closeModal('addModalBackdrop');
    loadPeers();
}

async function submitAddPeer(event) {
    event.preventDefault();
    const nameInput = document.getElementById('peerName');
    const submitBtn = document.getElementById('btnSubmitAdd');
    const orig = submitBtn.innerText;
    submitBtn.innerText = t('js.creating');
    submitBtn.disabled = true;

    const data = await apiPost('add_peer', { name: nameInput.value });
    if (data.success) {
        showToast(t('js.peer_created'));
        pendingHighlightId = data.peer.ip;
        displayAddResult(data.peer);
    } else {
        showToast(t('js.create_error').replace('%s', data.error), true);
        submitBtn.innerText = orig;
        submitBtn.disabled = false;
    }
}

function displayAddResult(peer) {
    document.getElementById('modalFormContent').style.display = 'none';
    document.getElementById('modalFooterActions').style.display = 'none';
    document.getElementById('modalResultContent').style.display = 'block';

    document.getElementById('resIp').innerText = peer.ip + '/32';
    document.getElementById('code-conf-text').innerText = peer.config;
    document.getElementById('code-script-text').innerText = peer.script;
    document.getElementById('addQrTab').disabled = false;
    renderQrCode('addQrCode', peer.config);

    setupDownload(document.getElementById('btnDownloadConf'), `${peer.name}.conf`, peer.config);
    setupDownload(document.getElementById('btnDownloadScript'), `${peer.name}.rsc`, peer.script);

    const mode = AppConfig.exportMode === 'conf' ? 'conf' : 'script';
    switchAddTab(mode);
    const content = mode === 'conf' ? peer.config : peer.script;
    navigator.clipboard.writeText(content)
        .then(() => showToast(t('js.code_auto_copied')))
        .catch(() => {});
}

function switchAddTab(tab) {
    const modal = document.getElementById('addModalBackdrop');
    modal.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    modal.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    if (tab === 'script') {
        document.getElementById('tab-script').classList.add('active');
        modal.querySelector('[data-tab="script"]')?.classList.add('active');
    } else if (tab === 'qrcode') {
        document.getElementById('tab-qrcode').classList.add('active');
        modal.querySelector('[data-tab="qrcode"]')?.classList.add('active');
    } else {
        document.getElementById('tab-conf').classList.add('active');
        modal.querySelector('[data-tab="conf"]')?.classList.add('active');
    }
}

function renderQrCode(containerId, configText) {
    const container = document.getElementById(containerId);
    if (!container) return;
    if (!configText || typeof qrcode !== 'function') {
        container.textContent = t('js.qrcode_error');
        container.setAttribute('role', 'alert');
        return;
    }

    try {
        qrcode.stringToBytes = qrcode.stringToBytesFuncs['UTF-8'];
        const code = qrcode(0, 'M');
        code.addData(configText);
        code.make();
        container.innerHTML = code.createSvgTag({
            cellSize: 5,
            margin: 20,
            scalable: true,
            title: { text: t('js.qrcode_title'), id: `${containerId}-title` },
            alt: { text: t('js.qrcode_alt'), id: `${containerId}-description` }
        });
        container.setAttribute('aria-label', t('js.qrcode_alt'));
    } catch (error) {
        container.textContent = t('js.qrcode_error');
        container.setAttribute('role', 'alert');
    }
}

function clearQrCode(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    container.replaceChildren();
    container.removeAttribute('aria-label');
    container.setAttribute('role', 'status');
}

/* ── Download helper ────────────────────────────────────────── */
function setupDownload(el, filename, content) {
    const clone = el.cloneNode(true);
    el.parentNode.replaceChild(clone, el);
    clone.addEventListener('click', () => {
        const url = URL.createObjectURL(new Blob([content], { type: 'text/plain;charset=utf-8' }));
        const a = Object.assign(document.createElement('a'), { href: url, download: filename });
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        showToast(t('js.file_downloaded').replace('%s', filename));
    }, { once: true });
}

/* ── Edit Peer Modal ────────────────────────────────────────── */
async function openEditModal(id, name) {
    if (!(await checkSession())) return;
    pendingHighlightId = null;
    highlightId = null;
    document.getElementById('editPeerId').value = id;
    document.getElementById('editPeerName').value = name;
    openModal('editModalBackdrop');
    document.getElementById('editPeerName').focus();
}

function closeEditModal() {
    closeModal('editModalBackdrop');
}

async function submitEditPeer(event) {
    event.preventDefault();
    const id = document.getElementById('editPeerId').value;
    const name = document.getElementById('editPeerName').value;
    const data = await apiPost('update_peer', { id, name });
    if (data.success) {
        showToast(t('js.peer_updated'));
        const editedPeer = allPeers.find(p => p['.id'] === id);
        pendingHighlightId = editedPeer ? (editedPeer['allowed-address'] || '').split('/')[0] : null;
        closeEditModal();
        loadPeers();
    } else {
        showToast(t('js.update_error').replace('%s', data.error), true);
    }
}

/* ── Delete Peer Modal ──────────────────────────────────────── */
async function openDeleteModal(id, name) {
    if (!(await checkSession())) return;
    peerToDeleteId = id;
    document.getElementById('deletePeerNameText').innerText = name;
    openModal('deleteModalBackdrop');
}

function closeDeleteModal() {
    closeModal('deleteModalBackdrop');
    peerToDeleteId = null;
}

/* ── Confirm Modal ──────────────────────────────────────────── */
let confirmCallback = null;

function openConfirmModal(message, callback) {
    document.getElementById('confirmModalText').innerText = message;
    confirmCallback = callback;
    openModal('confirmModalBackdrop');
}

function closeConfirmModal() {
    closeModal('confirmModalBackdrop');
    confirmCallback = null;
}

async function submitDeletePeer(id) {
    const data = await apiPost('delete_peer', { id });
    if (data.success) {
        showToast(t('js.peer_deleted'));
        closeDeleteModal();
        loadPeers();
    } else {
        showToast(t('js.delete_error').replace('%s', data.error), true);
    }
}

/* ── Toggle Peer (Enable/Disable) ──────────────────────────── */
async function togglePeer(id, currentlyDisabled) {
    if (!(await checkSession())) return;
    const newDisabled = !currentlyDisabled;

    const doToggle = async () => {
        const data = await apiPost('toggle_peer', { id, disabled: newDisabled });
        if (data.success) {
            showToast(newDisabled ? t('js.peer_disabled') : t('js.peer_enabled'));
            loadPeers();
        } else {
            showToast(t('js.toggle_error').replace('%s', data.error), true);
        }
    };

    if (newDisabled) {
        openConfirmModal(t('js.toggle_disable_confirm'), doToggle);
    } else {
        doToggle();
    }
}

/* ── Export Config Modal ────────────────────────────────────── */
let exportPeerId = null;
let exportPeerName = null;
let exportPeerIp = null;

async function openExportModal(id, name, allowedAddress) {
    if (!(await checkSession())) return;
    exportPeerId = id;
    exportPeerName = name;
    exportPeerIp = allowedAddress.split('/')[0];

    const dnatPortValue = dnatPort(exportPeerIp);

    document.getElementById('exportIp').innerText = exportPeerIp;
    document.getElementById('exportPort').innerText = dnatPortValue;

    // Hide config tabs, show only IP/port + generate button
    document.getElementById('exportConfigSection').style.display = 'none';
    document.getElementById('tabBtnExportQr').disabled = true;
    clearQrCode('exportQrCode');
    document.getElementById('btnRegenerateKey').disabled = false;
    document.getElementById('btnRegenerateKey').innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.688-5.57m-1.246-7.755v4.992m0 0h-4.992m4.993 0-3.183-3.183a8.25 8.25 0 0 0-13.688 5.57"/></svg>
        ${t('js.regenerate_btn')}`;

    switchExportTab(AppConfig.exportMode === 'conf' ? 'conf' : 'script');
    openModal('exportModalBackdrop');
}

function updateExportConfig(data) {
    const confContent = data.config || '';
    const scriptContent = data.script || '';

    document.getElementById('code-export-conf-text').innerText = confContent;
    document.getElementById('code-export-script-text').innerText = scriptContent;
    document.getElementById('tabBtnExportQr').disabled = false;
    renderQrCode('exportQrCode', confContent);

    setupDownload(document.getElementById('btnDownloadExportConf'), `${exportPeerName}.conf`, confContent);
    setupDownload(document.getElementById('btnDownloadExportScript'), `${exportPeerName}.rsc`, scriptContent);

    const mode = AppConfig.exportMode === 'conf' ? 'conf' : 'script';
    switchExportTab(mode);
    const content = mode === 'conf' ? confContent : scriptContent;
    navigator.clipboard.writeText(content)
        .then(() => showToast(t('js.code_auto_copied')))
        .catch(() => {});
}

async function regenerateKey() {
    openConfirmModal(t('js.regenerate_confirm'), async () => {
        const btn = document.getElementById('btnRegenerateKey');
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner" style="width:16px;height:16px;border-width:2px;"></span> ${t('js.regenerating')}`;

        const data = await apiPost('regenerate_key', { id: exportPeerId });
        if (data.success) {
            updateExportConfig(data);

            document.getElementById('exportConfigSection').style.display = 'block';

            showToast(t('js.key_regenerated'));
            btn.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                ${t('js.config_updated')}`;
        } else {
            showToast(t('js.regenerate_error').replace('%s', data.error), true);
            btn.disabled = false;
            btn.innerHTML = `${t('js.regenerate_btn')}`;
        }
    });
}

function closeExportModal() {
    clearQrCode('exportQrCode');
    closeModal('exportModalBackdrop');
}

function switchExportTab(tab) {
    ['tabBtnExportConf', 'tabBtnExportScript', 'tabBtnExportQr'].forEach(id => document.getElementById(id).classList.remove('active'));
    ['tab-export-conf', 'tab-export-script', 'tab-export-qrcode'].forEach(id => document.getElementById(id).classList.remove('active'));
    if (tab === 'script') {
        document.getElementById('tabBtnExportScript').classList.add('active');
        document.getElementById('tab-export-script').classList.add('active');
    } else if (tab === 'qrcode') {
        document.getElementById('tabBtnExportQr').classList.add('active');
        document.getElementById('tab-export-qrcode').classList.add('active');
    } else {
        document.getElementById('tabBtnExportConf').classList.add('active');
        document.getElementById('tab-export-conf').classList.add('active');
    }
}

/* ── Clipboard ──────────────────────────────────────────────── */
function copyDnatPort(ip) {
    const dnatPortValue = dnatPort(ip);
    navigator.clipboard.writeText(dnatPortValue.toString())
        .then(() => showToast(t('js.dnat_copied').replace('%d', dnatPortValue)))
        .catch(() => showToast(t('js.copy_failed'), true));
}

function copyIp(ip) {
    navigator.clipboard.writeText(ip)
        .then(() => showToast(t('js.ip_copied')))
        .catch(() => showToast(t('js.copy_failed'), true));
}

function copyNameToClipboard(name) {
    navigator.clipboard.writeText(name)
        .then(() => showToast(t('js.name_copied')))
        .catch(() => showToast(t('js.copy_failed'), true));
}

function copyToClipboard(elementId) {
    navigator.clipboard.writeText(document.getElementById(elementId).innerText)
        .then(() => showToast(t('js.code_copied')))
        .catch(() => showToast(t('js.code_copy_failed'), true));
}

/* ── Export VPN IPs Modal ──────────────────────────────────── */
async function openExportVpnIpsModal() {
    if (!(await checkSession())) return;
    document.getElementById('exportVpnIpsResult').style.display = 'none';
    document.getElementById('exportVpnIpsFooter').style.display = 'flex';
    document.getElementById('includeSstpCheck').checked = false;
    document.getElementById('includePptpCheck').checked = false;
    const btn = document.getElementById('btnExportVpnIps');
    btn.disabled = false;
    btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;margin-right:4px;vertical-align:middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg> ${t('js.export_vpn_download')}`;
    openModal('exportVpnIpsModalBackdrop');
}

function closeExportVpnIpsModal() {
    closeModal('exportVpnIpsModalBackdrop');
}

async function submitExportVpnIps() {
    const btn = document.getElementById('btnExportVpnIps');
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner" style="width:16px;height:16px;border-width:2px;"></span> ${t('js.exporting')}`;

    const data = await apiPost('export_vpn_ips', {
        include_sstp: document.getElementById('includeSstpCheck').checked,
        include_pptp: document.getElementById('includePptpCheck').checked
    });
    if (data.success) {
        document.getElementById('exportVpnIpsWgCount').innerText = data.stats.wireguard;
        document.getElementById('exportVpnIpsSstpCount').innerText = data.stats.sstp;
        document.getElementById('exportVpnIpsPptpCount').innerText = data.stats.pptp;

        if (data.secret_error) {
            const errMsg = data.stats.sstp || data.stats.pptp ? t('js.export_warn_sstp') : data.secret_error;
            document.getElementById('exportVpnIpsSstpCount').innerText = data.stats.sstp > 0 ? data.stats.sstp + ' (' + errMsg + ')' : data.stats.sstp;
            document.getElementById('exportVpnIpsPptpCount').innerText = data.stats.pptp > 0 ? data.stats.pptp + ' (' + errMsg + ')' : data.stats.pptp;
        }

        document.getElementById('exportVpnIpsResult').style.display = 'block';
        document.getElementById('exportVpnIpsFooter').style.display = 'none';

        const url = URL.createObjectURL(new Blob([data.content], { type: 'text/plain;charset=utf-8' }));
        const a = Object.assign(document.createElement('a'), { href: url, download: data.filename });
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        showToast(t('js.file_downloaded').replace('%s', data.filename));
    } else {
        showToast(t('js.export_error').replace('%s', data.error), true);
        btn.disabled = false;
        btn.innerHTML = `${t('js.export_vpn_download')}`;
    }
}

/* ── Toast ──────────────────────────────────────────────────── */
function showToast(message, isError = false) {
    const toast = document.getElementById('toast');
    const color = isError ? 'var(--danger)' : 'var(--success)';
    document.getElementById('toastText').innerText = message;
    toast.style.borderLeftColor = color;
    toast.querySelector('svg').style.color = color;
    toast.classList.add('active');
    setTimeout(() => toast.classList.remove('active'), TOAST_DURATION);
}

/* ── Escape helpers ─────────────────────────────────────────── */
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}
