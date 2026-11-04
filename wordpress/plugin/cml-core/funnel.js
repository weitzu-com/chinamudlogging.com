// Generated from src/scripts/funnel.ts; run wordpress/scripts/build-funnel.mjs.
(function(exports,CML_FUNNEL_BASE){
"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.recordIntent = recordIntent;
const storageKey = 'cml-funnel-session-v1';
const preferenceKey = 'cml-measurement-disabled';
const api = typeof CML_FUNNEL_BASE === 'undefined' ? '/api/funnel' : CML_FUNNEL_BASE;
const path = window.location.pathname;
function enabled() {
    try {
        return window.location.hostname === 'www.chinamudlogging.com' && navigator.doNotTrack !== '1' && !navigator.globalPrivacyControl && localStorage.getItem(preferenceKey) !== '1';
    }
    catch {
        return false;
    }
}
async function post(route, data) {
    const r = await fetch(`${api}/${route}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data), keepalive: true, signal: AbortSignal.timeout(2500) });
    if (!r.ok)
        throw new Error('Measurement unavailable');
    return r.json();
}
function source() {
    if (!document.referrer)
        return 'direct';
    try {
        const host = new URL(document.referrer).hostname;
        if (host === location.hostname)
            return 'direct';
        if (/(^|\.)google\.[a-z]{2,3}(\.[a-z]{2})?$/.test(host))
            return 'google';
        if (/(^|\.)(bing\.com|baidu\.com|duckduckgo\.com|yahoo\.com)$/.test(host))
            return 'search';
        if (/(^|\.)(linkedin\.com|facebook\.com|instagram\.com|twitter\.com|x\.com)$/.test(host))
            return 'social';
    }
    catch { }
    return 'other';
}
async function init() {
    if (!enabled())
        return null;
    try {
        const test = new URLSearchParams(location.search).get('funnel_test');
        let ticket = null;
        if (test && /^[a-f0-9-]{36}\.[a-f0-9]{64}$/.test(test)) {
            const [id, token] = test.split('.');
            ticket = { id, token, expiresAt: new Date(Date.now() + 15 * 60 * 1000).toISOString(), isTest: true };
        }
        else {
            try {
                ticket = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
            }
            catch {
                ticket = null;
            }
        }
        if (!ticket || !ticket.id || !ticket.token || ticket.expiresAt <= new Date().toISOString())
            ticket = await post('session', { path, source: source() });
        if (!ticket)
            return null;
        sessionStorage.setItem(storageKey, JSON.stringify(ticket));
        await post('event', { sessionId: ticket.id, token: ticket.token, id: crypto.randomUUID(), kind: 'page_view', path });
        const kind = /^\/(?:zh\/|es\/|ru\/|ar\/|fr\/|de\/)?contact-us(?:-(?:zh|es|ru|ar|fr|de))?\/$/.test(path) ? 'contact_view' : /^\/(?:zh\/|es\/|ru\/|ar\/|fr\/|de\/)?product\/[^/]+\/$/.test(path) ? 'product_view' : null;
        if (kind)
            await post('event', { sessionId: ticket.id, token: ticket.token, id: crypto.randomUUID(), kind, path });
        return ticket;
    }
    catch {
        return null;
    }
}
const ready = init();
async function recordIntent(kind, requestId = crypto.randomUUID()) {
    if (!enabled())
        return null;
    try {
        const ticket = await ready;
        if (!ticket)
            return null;
        const r = await post('intent', { sessionId: ticket.id, token: ticket.token, requestId, kind, path });
        return /^CML-[a-f0-9-]{36}$/.test(r.reference) ? r.reference : null;
    }
    catch {
        return null;
    }
}
document.addEventListener('click', async (event) => {
    const a = event.target?.closest('a[href^="mailto:"],a[href^="tel:"]');
    if (!a || event.defaultPrevented || (event instanceof MouseEvent && (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey)))
        return;
    if (a.href.startsWith('tel:')) {
        void recordIntent('phone_click');
        return;
    }
    if (!a.href.startsWith('mailto:sales@chinamudlogging.com'))
        return;
    event.preventDefault();
    const ref = await recordIntent('email_click');
    const mail = new URL(a.href);
    if (ref)
        mail.searchParams.set('subject', `[${ref}] ${mail.searchParams.get('subject') || 'Mud logging inquiry'}`);
    window.location.href = mail.toString();
});
document.querySelector('#measurement-toggle')?.addEventListener('click', () => {
    try {
        const off = localStorage.getItem(preferenceKey) !== '1';
        localStorage.setItem(preferenceKey, off ? '1' : '0');
        sessionStorage.removeItem(storageKey);
        const status = document.querySelector('#measurement-status');
        const toggle = document.querySelector('#measurement-toggle');
        if (status)
            status.textContent = off ? (toggle?.dataset.disabledText || 'Anonymous measurement is disabled in this browser.') : (toggle?.dataset.enabledText || 'Anonymous measurement is enabled unless your browser sends Do Not Track or Global Privacy Control. Reload to apply.');
    }
    catch { }
});

})(window.CMLFunnel={},"https://chinamudlogging-funnel.kenny-bd6.workers.dev/api/funnel");
