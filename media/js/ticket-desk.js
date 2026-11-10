/** Local QR decoding and explicit, separate cashier/admission operations. */
import QrScanner from './qr-scanner/qr-scanner.min.js';

document.querySelectorAll('[data-ticket-desk]').forEach(root => {
    const dialog = root.querySelector('dialog');
    const lookup = root.querySelector('[data-ticket-lookup]');
    const event = lookup.elements.event_id;
    const reference = lookup.elements.reference;
    const result = root.querySelector('[data-ticket-result]');
    const message = root.querySelector('[data-ticket-message]');
    const pool = root.querySelector('#ticket-pool');
    const quantity = root.querySelector('#ticket-quantity');
    const amount = root.querySelector('#ticket-amount');
    const method = root.querySelector('#ticket-method');
    const receipt = root.querySelector('#ticket-receipt');
    const videoBox = root.querySelector('[data-ticket-video-box]');
    const cameras = root.querySelector('#ticket-camera');
    const t = key => Joomla.Text._(key);
    const money = value => value === null ? '—' : (value / 100).toFixed(2);
    let current = null;
    let scanner = null;
    let busy = false;
    let changed = false;
    let openingCamera = false;

    const stop = () => { scanner?.destroy(); scanner = null; videoBox.hidden = true; };
    const clear = () => { current = null; result.hidden = true; message.textContent = ''; };
    const controls = disabled => {
        busy = disabled;
        root.querySelectorAll('button:not([data-ticket-close]),input,select').forEach(node => { node.disabled = disabled; });
    };
    const available = () => { quantity.max = String(current?.pools[pool.value]?.remaining || 0); quantity.value = '1'; };
    const render = ticket => {
        current = ticket;
        result.hidden = false;
        root.querySelector('[data-ticket-name]').textContent = ticket.event;
        root.querySelector('[data-ticket-summary]').textContent = `${ticket.holder} · ${ticket.reference} · ${t('COM_JEM_TK_PLACES')}: ${ticket.places} · ${t('COM_JEM_RS_ENTERED')}: ${ticket.entered} · ${t('COM_JEM_TK_REMAINING')}: ${ticket.remaining}`;
        root.querySelector('[data-ticket-payment]').textContent = `${t('COM_JEM_TK_PAYMENT_' + ticket.payment.state.toUpperCase())} · ${t('COM_JEM_TK_TOTAL')}: ${money(ticket.payment.total)} ${ticket.currency} · ${t('COM_JEM_TK_COLLECTED')}: ${money(ticket.payment.paid)} ${ticket.currency} · ${t('COM_JEM_TK_DUE')}: ${money(ticket.payment.due)} ${ticket.currency}`;
        if (ticket.payment.refund > 0) root.querySelector('[data-ticket-payment]').textContent += ` · ${t('COM_JEM_TK_REFUND_DUE')}: ${money(ticket.payment.refund)} ${ticket.currency}`;
        root.querySelector('[data-ticket-block]').textContent = ticket.blocked ? t(ticket.blocked) : '';
        root.querySelector('[data-ticket-pay]').hidden = !ticket.rights.pay || !ticket.confirmed || !ticket.event_open || !(ticket.payment.due > 0);
        root.querySelector('[data-ticket-admit]').hidden = !ticket.rights.admit || ticket.blocked !== '';
        amount.value = '';
        amount.placeholder = `${money(ticket.payment.due)} ${ticket.currency}`;
        receipt.value = '';
        pool.replaceChildren();
        Object.entries(ticket.pools).forEach(([key, item]) => {
            if (item.remaining > 0) pool.add(new Option(`${item.name === 'COM_JEM_TK_GENERAL' ? t(item.name) : item.name} (${item.remaining})`, key));
        });
        available();
        const download = root.querySelector('[data-ticket-download]');
        download.hidden = !ticket.confirmed;
        download.elements.event_id.value = ticket.event_id;
        download.elements.reference.value = ticket.reference;
        const audit = root.querySelector('[data-ticket-audit]');
        audit.replaceChildren();
        ticket.history.forEach(item => {
            const li = document.createElement('li');
            li.textContent = `${item.occurred} UTC · ${t(item.action === 'payment' ? 'COM_JEM_TK_PAYMENT' : 'COM_JEM_TK_ADMISSION')} · ${item.action === 'payment' ? item.amount + ' ' + item.currency : item.quantity} · ${t('COM_JEM_TK_OPERATOR')}: ${item.operator}`;
            audit.append(li);
        });
    };
    const request = async (action, details = {}) => {
        if (busy) return;
        const body = new FormData(lookup);
        Object.entries(details).forEach(([key, value]) => body.set(key, value));
        controls(true);
        message.textContent = t('COM_JEM_TK_WORKING');
        try {
            const endpoint = new URL(lookup.action);
            endpoint.searchParams.set('task', `tickets.${action}`);
            const response = await fetch(endpoint, {
                method: 'POST', body, credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }
            });
            const payload = await response.json();
            if (!payload.success) throw new Error(payload.message || t('COM_JEM_TK_ERROR'));
            if (action !== 'lookup') changed = true;
            if (dialog.open) render(payload.data);
            message.textContent = action === 'lookup' ? '' : t(payload.data.duplicate ? 'COM_JEM_TK_ALREADY_RECORDED' : 'COM_JEM_TK_RECORDED');
        } catch (error) {
            // Preserve the operation reference after an uncertain network result: retry is idempotent.
            message.textContent = error.message || t('COM_JEM_TK_ERROR');
        } finally { controls(false); }
    };
    lookup.addEventListener('submit', e => { e.preventDefault(); if (lookup.reportValidity()) { stop(); clear(); request('lookup'); } });
    event.addEventListener('change', clear);
    reference.addEventListener('input', clear);
    pool.addEventListener('change', available);
    root.querySelector('[data-ticket-open]').addEventListener('click', () => { dialog.showModal(); reference.focus(); });
    root.querySelector('[data-ticket-close]').addEventListener('click', () => { dialog.close(); });
    dialog.addEventListener('close', () => { stop(); clear(); if (changed) window.location.reload(); });
    root.querySelector('[data-ticket-stop]').addEventListener('click', stop);
    root.querySelector('[data-ticket-payment-save]').addEventListener('click', () => {
        if (!current || busy) return;
        const value = amount.value.trim().replace(',', '.');
        if (!window.confirm(`${t('COM_JEM_TK_ASK_PAYMENT')}\n${current.event}\n${current.reference}\n${value} ${current.currency}`)) return;
        request('payment', { version: current.version, operation: current.operation, amount: value,
            currency: current.currency, method: method.value, receipt: receipt.value });
    });
    root.querySelector('[data-ticket-admission-save]').addEventListener('click', () => {
        if (!current || busy || !quantity.reportValidity()) return;
        if (!window.confirm(`${t('COM_JEM_TK_ASK_ADMISSION')}\n${current.event}\n${current.reference}\n${pool.selectedOptions[0]?.textContent}\n${t('COM_JEM_TK_QUANTITY')}: ${quantity.value}`)) return;
        request('admission', { version: current.version, operation: current.operation, quantity: quantity.value, pool: pool.value });
    });
    root.querySelector('[data-ticket-camera]').addEventListener('click', async () => {
        if (openingCamera || busy) return;
        if (!event.value) { event.reportValidity(); return; }
        openingCamera = true;
        stop();
        try {
            const active = new QrScanner(root.querySelector('video'), decoded => {
                const value = decoded.data.trim().toUpperCase();
                if (!/^R-[0-9A-HJKMNP-TV-Z]{26}$/.test(value)) { message.textContent = t('COM_JEM_TK_INVALID_QR'); return; }
                stop(); reference.value = value; clear(); request('lookup');
            }, { preferredCamera: 'environment', returnDetailedScanResult: true, highlightScanRegion: true });
            scanner = active;
            videoBox.hidden = false;
            await active.start();
            if (!dialog.open || scanner !== active) { active.destroy(); return; }
            const devices = await QrScanner.listCameras(true);
            cameras.replaceChildren();
            devices.forEach(device => cameras.add(new Option(device.label || device.id, device.id)));
            const selected = root.querySelector('video').srcObject?.getVideoTracks()[0]?.getSettings().deviceId;
            if (selected) cameras.value = selected;
        } catch {
            stop(); message.textContent = t('COM_JEM_TK_CAMERA_ERROR');
        } finally { openingCamera = false; }
    });
    cameras.addEventListener('change', async () => {
        try { await scanner?.setCamera(cameras.value); }
        catch { stop(); message.textContent = t('COM_JEM_TK_CAMERA_ERROR'); }
    });
    document.addEventListener('visibilitychange', () => { if (document.hidden) stop(); });
    window.addEventListener('pagehide', stop);
});
