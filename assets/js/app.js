/* ============================================================
   MikroTik WireGuard Peer Manager — Frontend Logic
   AppConfig is injected inline by index.php before this file.
   ============================================================ */

'use strict';

/* ── State ──────────────────────────────────────────────────── */
let allPeers = [];
let peerToDeleteId = null;
let currentSort = { field: 'name', dir: 'asc' };
let hideOffline = localStorage.getItem('hideOffline') !== 'false';
let highlightId = null;
let pendingHighlightId = null;


/* ── Init ───────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('hideOfflineBtn');
    const label = document.getElementById('hideOfflineLabel');
    if (!hideOffline) {
        btn.classList.remove('btn-active');
        label.innerText = 'Nascondi offline';
    } else {
        btn.classList.add('btn-active');
        label.innerText = 'Mostra tutti';
    }

    loadPeers().then(() => updateSortIcons());
    document.getElementById('searchInput').addEventListener('input', () => applyFiltersAndSort());

    // Auto-refresh every 30 seconds (pause when modals are open)
    setInterval(() => {
        const anyModalOpen = ['addModalBackdrop', 'editModalBackdrop', 'deleteModalBackdrop', 'exportModalBackdrop']
            .some(id => document.getElementById(id)?.classList.contains('active'));
        if (!anyModalOpen) refreshPeers();
    }, 10000);
});

/* ── Data Loading ───────────────────────────────────────────── */
async function loadPeers() {
    const loader = document.getElementById('tableLoader');
    loader.classList.add('active');
    try {
        const res = await fetch('src/api.php?action=get_peers');
        const data = await res.json();
        if (data.success) {
            allPeers = data.peers;
            AppConfig.serverPublicKey = data.server_public_key || '';
            applyFiltersAndSort();
            highlightPendingPeer();
        } else {
            showToast('Errore nel caricamento dei dati: ' + data.error, true);
        }
    } catch {
        showToast('Errore di connessione al server backend.', true);
    } finally {
        loader.classList.remove('active');
    }
}

// Auto-refresh: silent, preserves scroll, no loader flicker
async function refreshPeers() {
    const tableWrapper = document.querySelector('.table-wrapper');
    const savedScroll = tableWrapper?.scrollTop || 0;
    try {
        const res = await fetch('src/api.php?action=get_peers');
        const data = await res.json();
        if (data.success) {
            allPeers = data.peers;
            AppConfig.serverPublicKey = data.server_public_key || '';
            applyFiltersAndSort();
            if (tableWrapper) tableWrapper.scrollTop = savedScroll;
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

/* ── Filter + Sort pipeline ─────────────────────────────────── */
function isPeerActive(peer) {
    const handshake = peer['handshake_formatted'] || 'never';
    return handshakeToSeconds(handshake) < 300; // 5 minuti
}

function applyFiltersAndSort() {
    const peersCard = document.getElementById('stat-total-peers').closest('.stat-card');
    const maxPeers = peersCard ? parseInt(peersCard.dataset.maxPeers) : null;
    document.getElementById('stat-total-peers').innerText = allPeers.length;

    const query = document.getElementById('searchInput').value.toLowerCase().trim();
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
        label.innerText = 'Mostra tutti';
    } else {
        btn.classList.remove('btn-active');
        label.innerText = 'Nascondi offline';
    }
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
    return ip.reduce((acc, oct) => (acc << 8) + parseInt(oct, 10), 0) >>> 0;
}

function updateSortIcons() {
    ['name', 'ip', 'handshake'].forEach(f => {
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
        emptyState.style.display = 'flex';
        document.getElementById('stat-active-peers').innerText = '0';
        return;
    }

    emptyState.style.display = 'none';
    let activeCount = 0;

    peers.forEach(peer => {
        const handshake = peer['handshake_formatted'] || 'never';
        let isActive = isPeerActive(peer);
        if (isActive) activeCount++;

        const endpoint = peer['current-endpoint-address'] || 'Non Connesso';

        const tr = document.createElement('tr');
        tr.setAttribute('data-peer-ip', (peer['allowed-address'] || '').split('/')[0]);
        //console.log(peer);
        tr.innerHTML = `
            <td data-label="Nome & Commento">
                <span class="peer-name">${escapeHtml(peer.name || 'Senza Nome')}</span>
            </td>
            <td data-label="IP Assegnato">
                <span class="peer-ip-badge" style="cursor:pointer;" onclick="copyDnatPort('${escapeJs(peer['allowed-address'].split('/')[0])}')" title="Copia Porta Winbox (DNAT)">${escapeHtml(peer['allowed-address'].split('/')[0])}</span>
            </td>
            <td data-label="Ultimo Handshake">
                <div class="handshake-cell">
                    <span class="handshake-pulse ${isActive ? 'active' : ''}"></span>
                    <span class="handshake-dot-inactive"></span>
                    <span>${escapeHtml(handshake)}</span>
                </div>
            </td>
            <td data-label="Endpoint">
                <span style="font-family:monospace;color:${peer['current-endpoint-address'] ? 'var(--text-color)' : 'var(--text-muted)'};">
                    ${escapeHtml(endpoint)}
                </span>
            </td>
            <td data-label="Traffico">
                <div class="traffic-info">
                    <div class="traffic-row">
                        <span class="traffic-label">↓ rx</span>
                        <span class="traffic-val">${escapeHtml(peer.rx_formatted)}</span>
                    </div>
                    <div class="traffic-row">
                        <span class="traffic-label">↑ tx</span>
                        <span class="traffic-val">${escapeHtml(peer.tx_formatted)}</span>
                    </div>
                </div>
            </td>
            <td data-label="Azioni" style="text-align:right;">
                <div class="actions-cell">
                    <button class="icon-btn" onclick="openExportModal('${escapeJs(peer['.id'])}','${escapeJs(peer.name)}','${escapeJs(peer['allowed-address'])}')" title="Scarica configurazione client">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    </button>
                    <button class="icon-btn" onclick="openEditModal('${peer['.id']}','${escapeJs(peer.name)}')" title="Modifica Nome">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>
                    </button>
                    <button class="icon-btn icon-btn-danger" onclick="openDeleteModal('${peer['.id']}','${escapeJs(peer.name)}')" title="Elimina peer">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                    </button>
                </div>
            </td>`;
        tbody.appendChild(tr);
    });

    // Highlight and scroll to a recently created/edited peer
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
}

/* ── Add Peer Modal ─────────────────────────────────────────── */
function openAddModal() {
    pendingHighlightId = null;
    highlightId = null;
    document.getElementById('modalFormContent').style.display = 'block';
    document.getElementById('modalResultContent').style.display = 'none';
    document.getElementById('modalFooterActions').style.display = 'flex';
    document.getElementById('peerName').value = '';
    document.getElementById('addModalBackdrop').classList.add('active');
    document.getElementById('peerName').focus();
    const submitBtn = document.getElementById('btnSubmitAdd');
    submitBtn.innerText = 'Crea Peer';
    submitBtn.disabled = false;
}

function closeAddModal() {
    document.getElementById('addModalBackdrop').classList.remove('active');
    loadPeers();
}

async function submitAddPeer(event) {
    event.preventDefault();
    const nameInput = document.getElementById('peerName');
    const submitBtn = document.getElementById('btnSubmitAdd');
    const orig = submitBtn.innerText;
    submitBtn.innerText = 'Creazione...';
    submitBtn.disabled = true;

    try {
        const res = await fetch('src/api.php?action=add_peer', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: nameInput.value })
        });
        const data = await res.json();
        if (data.success) {
            showToast('Peer WireGuard aggiunto con successo!');
            pendingHighlightId = data.peer.ip;
            displayAddResult(data.peer);
        } else {
            showToast('Errore durante la creazione: ' + data.error, true);
            submitBtn.innerText = orig;
            submitBtn.disabled = false;
        }
    } catch {
        showToast('Errore di connessione API.', true);
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

    setupDownload(document.getElementById('btnDownloadConf'), `${peer.name}.conf`, peer.config);
    setupDownload(document.getElementById('btnDownloadScript'), `${peer.name}.rsc`, peer.script);

    // Default to .rsc tab
    switchAddTab('script');
}

function switchAddTab(tab) {
    const modal = document.getElementById('addModalBackdrop');
    modal.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    modal.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    if (tab === 'script') {
        document.getElementById('tab-script').classList.add('active');
        modal.querySelector('[onclick="switchAddTab(\'script\')"]')?.classList.add('active');
    } else {
        document.getElementById('tab-conf').classList.add('active');
        modal.querySelector('[onclick="switchAddTab(\'conf\')"]')?.classList.add('active');
    }
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
        showToast(`File ${filename} scaricato!`);
    });
}

/* ── Edit Peer Modal ────────────────────────────────────────── */
function openEditModal(id, name) {
    pendingHighlightId = null;
    highlightId = null;
    document.getElementById('editPeerId').value = id;
    document.getElementById('editPeerName').value = name;
    document.getElementById('editModalBackdrop').classList.add('active');
    document.getElementById('editPeerName').focus();
}

function closeEditModal() {
    document.getElementById('editModalBackdrop').classList.remove('active');
}

async function submitEditPeer(event) {
    event.preventDefault();
    const id = document.getElementById('editPeerId').value;
    const name = document.getElementById('editPeerName').value;
    try {
        const res = await fetch('src/api.php?action=update_peer', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, name })
        });
        const data = await res.json();
        if (data.success) {
            showToast('Nome peer aggiornato con successo!');
            const editedPeer = allPeers.find(p => p['.id'] === id);
            pendingHighlightId = editedPeer ? (editedPeer['allowed-address'] || '').split('/')[0] : null;
            closeEditModal();
            loadPeers();
        } else {
            showToast('Errore durante la modifica: ' + data.error, true);
        }
    } catch {
        showToast('Errore di connessione API.', true);
    }
}

/* ── Delete Peer Modal ──────────────────────────────────────── */
function openDeleteModal(id, name) {
    peerToDeleteId = id;
    document.getElementById('deletePeerNameText').innerText = name;
    const btn = document.getElementById('btnConfirmDelete');
    const clone = btn.cloneNode(true);
    btn.parentNode.replaceChild(clone, btn);
    clone.addEventListener('click', () => submitDeletePeer(peerToDeleteId));
    document.getElementById('deleteModalBackdrop').classList.add('active');
}

function closeDeleteModal() {
    document.getElementById('deleteModalBackdrop').classList.remove('active');
    peerToDeleteId = null;
}

async function submitDeletePeer(id) {
    try {
        const res = await fetch('src/api.php?action=delete_peer', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (data.success) {
            showToast('Peer WireGuard rimosso con successo!');
            closeDeleteModal();
            loadPeers();
        } else {
            showToast('Errore durante la cancellazione: ' + data.error, true);
        }
    } catch {
        showToast('Errore di connessione API.', true);
    }
}

/* ── Export Config Modal ────────────────────────────────────── */
let exportPeerId = null;
let exportPeerName = null;
let exportPeerIp = null;

function openExportModal(id, name, allowedAddress) {
    exportPeerId = id;
    exportPeerName = name;
    exportPeerIp = allowedAddress.split('/')[0];

    // Calculate DNAT port: 30000 + third*1000 + fourth
    const parts = exportPeerIp.split('.');
    const dnatPort = 30000 + parseInt(parts[2]) * 1000 + parseInt(parts[3]);

    document.getElementById('exportIp').innerText = exportPeerIp;
    document.getElementById('exportPort').innerText = dnatPort;

    // Hide config tabs, show only IP/port + generate button
    document.getElementById('exportConfigSection').style.display = 'none';
    document.getElementById('btnRegenerateKey').disabled = false;
    document.getElementById('btnRegenerateKey').innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.688-5.57m-1.246-7.755v4.992m0 0h-4.992m4.993 0-3.183-3.183a8.25 8.25 0 0 0-13.688 5.57"/></svg>
        Rigenera Chiave & Scarica Config`;

    switchExportTab('script');
    document.getElementById('exportModalBackdrop').classList.add('active');
}

function updateExportConfig(privateKey) {
    const serverPubKey = AppConfig.serverPublicKey || 'SERVER_PUBLIC_KEY';
    const endpointParts = AppConfig.endpoint.split(':');
    const endpointHost = endpointParts[0] || '';
    const endpointPort = endpointParts[1] || '13231';

    const confContent = `[Interface]
PrivateKey = ${privateKey}
Address = ${exportPeerIp}/21
DNS = 1.1.1.1

[Peer]
PublicKey = ${serverPubKey}
Endpoint = ${AppConfig.endpoint}
AllowedIPs = ${AppConfig.clientAllowedIps}
PersistentKeepalive = 25`;

    const scriptContent = `# --- MikroTik Client Setup Script ---
# Incolla questo codice nel terminale del tuo MikroTik

/interface wireguard
add name="wg-resnovae" private-key="${privateKey}" mtu=1420

/interface wireguard peers
add interface="wg-resnovae" public-key="${serverPubKey}" \\
    endpoint-address="${endpointHost}" endpoint-port=${endpointPort} \\
    allowed-address="${AppConfig.clientAllowedIps}" persistent-keepalive=25s \\
    comment="ResNovae VPN Server"

/ip address
add address="${exportPeerIp}/21" network="3.0.0.0" interface="wg-resnovae"

/ip firewall address-list
add address=3.0.0.1 list=MANAGEMENT`;

    document.getElementById('code-export-conf-text').innerText = confContent;
    document.getElementById('code-export-script-text').innerText = scriptContent;

    setupDownload(document.getElementById('btnDownloadExportConf'), `${exportPeerName}.conf`, confContent);
    setupDownload(document.getElementById('btnDownloadExportScript'), `${exportPeerName}.rsc`, scriptContent);
}

async function regenerateKey() {
    if (!confirm('ATTENZIONE: La rigenerazione della chiave interromperà immediatamente la VPN su questo cliente fino a quando non importi la nuova configurazione sul router. Procedere?')) {
        return;
    }
    const btn = document.getElementById('btnRegenerateKey');
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner" style="width:16px;height:16px;border-width:2px;"></span> Rigenerazione...`;

    try {
        const res = await fetch('src/api.php?action=regenerate_key', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: exportPeerId })
        });
        const data = await res.json();
        if (data.success) {
            updateExportConfig(data.private_key);

            // Show config section
            document.getElementById('exportConfigSection').style.display = 'block';
            switchExportTab('script');

            showToast('Chiave rigenerata con successo!');
            btn.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                Configurazione Aggiornata`;
        } else {
            showToast('Errore: ' + data.error, true);
            btn.disabled = false;
            btn.innerHTML = `Rigenera Chiave & Scarica Config`;
        }
    } catch {
        showToast('Errore di connessione API.', true);
        btn.disabled = false;
        btn.innerHTML = `Rigenera Chiave & Scarica Config`;
    }
}

function closeExportModal() {
    document.getElementById('exportModalBackdrop').classList.remove('active');
}

function switchExportTab(tab) {
    ['tabBtnExportConf', 'tabBtnExportScript'].forEach(id => document.getElementById(id).classList.remove('active'));
    ['tab-export-conf', 'tab-export-script'].forEach(id => document.getElementById(id).classList.remove('active'));
    if (tab === 'script') {
        document.getElementById('tabBtnExportScript').classList.add('active');
        document.getElementById('tab-export-script').classList.add('active');
    } else {
        document.getElementById('tabBtnExportConf').classList.add('active');
        document.getElementById('tab-export-conf').classList.add('active');
    }
}

/* ── Clipboard ──────────────────────────────────────────────── */
function copyDnatPort(ip) {
    const parts = ip.split('.');
    const dnatPort = 30000 + parseInt(parts[2]) * 1000 + parseInt(parts[3]);
    navigator.clipboard.writeText(dnatPort.toString())
        .then(() => showToast(`Porta Winbox (DNAT): ${dnatPort} copiata!`))
        .catch(() => showToast('Impossibile copiare.', true));
}

function copyToClipboard(elementId) {
    navigator.clipboard.writeText(document.getElementById(elementId).innerText)
        .then(() => showToast('Codice copiato negli appunti!'))
        .catch(() => showToast('Impossibile copiare il codice automaticamente.', true));
}

/* ── Toast ──────────────────────────────────────────────────── */
function showToast(message, isError = false) {
    const toast = document.getElementById('toast');
    const color = isError ? 'var(--danger)' : 'var(--success)';
    document.getElementById('toastText').innerText = message;
    toast.style.borderLeftColor = color;
    toast.querySelector('svg').style.color = color;
    toast.classList.add('active');
    setTimeout(() => toast.classList.remove('active'), 3000);
}

/* ── Escape helpers ─────────────────────────────────────────── */
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function escapeJs(str) {
    if (!str) return '';
    return str.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"').replace(/\n/g, '\\n').replace(/\r/g, '\\r');
}
