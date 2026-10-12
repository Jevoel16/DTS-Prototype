/**
 * DTS PJAX Router — Instant Client-Side Navigation
 *
 * Intercepts <a> clicks to swap only the inner page content via fetch(),
 * without triggering a full browser document reload. Cancels in-flight
 * API requests (chart fetches, etc.) from the previous page on every
 * navigation to prevent orphaned TCP connections and session lock contention.
 *
 * Dispatches `dts:page-loaded` after every successful swap so page-specific
 * JS files can re-initialize their Chart.js instances, event listeners, etc.
 */
(function () {
    'use strict';

    // Shared AbortController for the active PJAX fetch AND for any page-level
    // background fetches (charts, polling) that opt in via window.__pjaxController.
    let pjaxController = null;

    // Expose the active controller globally so page JS files can attach their
    // fetch() calls to the same signal, getting cancelled on navigation.
    function refreshController() {
        if (pjaxController) {
            pjaxController.abort();
        }
        pjaxController = new AbortController();
        window.__pjaxController = pjaxController;
    }

    // --- Progress Bar ---
    const bar = document.getElementById('pjax-progress-bar');

    function showBar() {
        if (!bar) return;
        bar.style.transition = 'none';
        bar.style.width = '0%';
        bar.style.opacity = '1';
        // Force reflow so the transition starts from 0
        bar.getBoundingClientRect();
        bar.style.transition = 'width 8s cubic-bezier(0.05, 0.6, 0.4, 0.9)';
        bar.style.width = '80%';
    }

    function completeBar() {
        if (!bar) return;
        bar.style.transition = 'width 0.15s ease-out';
        bar.style.width = '100%';
        setTimeout(() => {
            bar.style.opacity = '0';
            bar.style.width = '0%';
        }, 200);
    }

    // Re-executes <script src="..."> tags found inside the swapped container.
    // innerHTML assignment does not run scripts; we must clone and re-append them.
    // Inline <script> blocks (not src-based) are also re-executed this way.
    function reExecuteScripts(container) {
        container.querySelectorAll('script').forEach(oldScript => {
            const newScript = document.createElement('script');

            if (oldScript.src) {
                // Cache-bust with timestamp to force re-evaluation by the browser
                newScript.src = oldScript.src.split('?')[0] + '?_pjax=' + Date.now();
                newScript.defer = oldScript.defer;
                newScript.async = oldScript.async;
            } else {
                newScript.textContent = oldScript.textContent;
            }

            oldScript.replaceWith(newScript);
        });
    }

    // Core navigation function — fetches target URL, parses full HTML,
    // swaps #pjax-content, re-runs scripts, fires lifecycle event.
    async function navigateTo(url, pushHistory = true) {
        // Track origin URL before entering a document page from a non-document page
        const currentPath = window.location.pathname;
        const targetPath = url.split('?')[0].split('#')[0];
        if (!currentPath.includes('/documents/') && targetPath.includes('/documents/')) {
            sessionStorage.setItem('dts_doc_origin', currentPath + window.location.search);
        }

        // Abort previous in-flight PJAX fetch AND all chart/poll requests
        // that registered themselves on window.__pjaxController.
        refreshController();

        showBar();

        if (pushHistory) {
            // Optimistically push the URL so the browser address bar updates instantly
            history.pushState({ pjax: true, url }, '', url);
        }

        try {
            const response = await fetch(url, {
                signal: pjaxController.signal,
                headers: {
                    // Signal to the server that this is a PJAX request (future use)
                    'X-PJAX': 'true'
                }
            });

            // If the server issued a redirect (e.g. auth guard → /login), follow it natively.
            // fetch() with redirect:'follow' (default) gives us the final URL in response.url.
            if (response.redirected || response.url !== new URL(url, window.location.origin).href) {
                window.location.href = response.url;
                return;
            }

            if (!response.ok) {
                // Non-2xx (403, 404, 500, etc.) — fall back to a full page load
                window.location.href = url;
                return;
            }

            const html = await response.text();
            const parser = new DOMParser();
            const newDoc = parser.parseFromString(html, 'text/html');

            // Swap <title>
            const newTitle = newDoc.querySelector('title');
            if (newTitle) {
                document.title = newTitle.textContent;
            }

            // Refresh CSRF meta token from the incoming page so POST forms remain valid
            const newCsrf = newDoc.querySelector('meta[name="csrf-token"]');
            const currentCsrf = document.querySelector('meta[name="csrf-token"]');
            if (newCsrf && currentCsrf) {
                currentCsrf.setAttribute('content', newCsrf.getAttribute('content'));
            }

            // Swap the inner page content block
            const newContent = newDoc.getElementById('pjax-content');
            const currentContent = document.getElementById('pjax-content');

            if (!newContent || !currentContent) {
                // Structure mismatch (guest page, login page, error page) — hard navigate
                window.location.href = url;
                return;
            }

            // Destroy all active Chart.js instances before swapping the DOM to prevent
            // "Canvas is already in use" errors and memory/event-listener leaks.
            if (typeof Chart !== 'undefined') {
                for (let id in Chart.instances) {
                    Chart.instances[id].destroy();
                }
            }

            currentContent.innerHTML = newContent.innerHTML;

            // Swap the page heading (the <header id="pjax-header"> above <main>).
            // Each view sets a $header variable that app.php renders here. Without
            // swapping it, the old page title stays stuck after navigation.
            const newHeader = newDoc.getElementById('pjax-header');
            const currentHeader = document.getElementById('pjax-header');
            if (newHeader && currentHeader) {
                currentHeader.innerHTML = newHeader.innerHTML;
                // Toggle visibility: pages without a $header render a hidden placeholder
                if (newHeader.classList.contains('hidden')) {
                    currentHeader.classList.add('hidden');
                } else {
                    currentHeader.classList.remove('hidden');
                }
            }

            // Swap the navigation links container so the server-rendered active states
            // are perfectly replicated without buggy client-side string matching
            const newNav = newDoc.getElementById('pjax-nav-links');
            const currentNav = document.getElementById('pjax-nav-links');
            if (newNav && currentNav) {
                currentNav.innerHTML = newNav.innerHTML;
            }

            // Synchronize notification bell badge and dropdown list across PJAX swaps
            const newNotifBtn = newDoc.getElementById('notification-menu-button');
            const currentNotifBtn = document.getElementById('notification-menu-button');
            if (newNotifBtn && currentNotifBtn) {
                currentNotifBtn.innerHTML = newNotifBtn.innerHTML;
            }

            const newNotifDropdown = newDoc.getElementById('notification-dropdown-menu');
            const currentNotifDropdown = document.getElementById('notification-dropdown-menu');
            if (newNotifDropdown && currentNotifDropdown) {
                currentNotifDropdown.innerHTML = newNotifDropdown.innerHTML;
            }

            // Sync toast notification container so bottom-right toast modals pop up
            const newToastContainer = newDoc.getElementById('toast-container');
            const currentToastContainer = document.getElementById('toast-container');
            if (newToastContainer && currentToastContainer) {
                const newToasts = newToastContainer.querySelectorAll('.toast-message');
                if (newToasts.length > 0) {
                    console.log(`[DTS PJAX Router] Synchronizing ${newToasts.length} new toast modal(s) into DOM.`);
                    newToasts.forEach(toast => {
                        currentToastContainer.appendChild(toast.cloneNode(true));
                    });
                }
            }
            console.log('[DTS PJAX Router] Notification bell & toasts synchronized.');

            // Re-run any <script> tags embedded in the new content (e.g. page-specific JS)
            reExecuteScripts(currentContent);

            // Notify all page-specific JS initializers that a new page is live
            document.dispatchEvent(new CustomEvent('dts:page-loaded', {
                detail: { url }
            }));

            completeBar();

            // Scroll back to top like a real page navigation
            window.scrollTo({ top: 0, behavior: 'instant' });

        } catch (err) {
            if (err.name === 'AbortError') {
                // User navigated away before this fetch resolved — do nothing.
                // The new navigation's fetch is already in flight.
                return;
            }
            console.error('[PJAX] Navigation failed, falling back to full load:', err);
            window.location.href = url;
        }
    }

    // --- Global Link Interception ---
    document.addEventListener('click', (e) => {
        const link = e.target.closest('a');
        if (!link) return;

        const href = link.getAttribute('href');
        if (!href) return;

        // Bail out for all link types that must not be PJAX-intercepted:
        // external URLs, new tabs, mailto/tel/javascript, anchor fragments,
        // explicit opt-outs (data-pjax="false"), and file downloads.
        if (
            link.target === '_blank' ||
            link.target === '_self' ||
            link.dataset.pjax === 'false' ||
            href.startsWith('http://') ||
            href.startsWith('https://') ||
            href.startsWith('//') ||
            href.startsWith('mailto:') ||
            href.startsWith('tel:') ||
            href.startsWith('#') ||
            href.startsWith('javascript:') ||
            link.hasAttribute('download')
        ) {
            return;
        }

        // Same-page: current path + same hash — let the browser handle it
        const targetPath = href.split('?')[0].split('#')[0];
        if (targetPath === window.location.pathname && !href.includes('?')) {
            return;
        }

        e.preventDefault();
        navigateTo(href);
    });

    // --- Browser Back / Forward ---
    window.addEventListener('popstate', (e) => {
        if (e.state && e.state.pjax) {
            navigateTo(window.location.pathname + window.location.search, false);
        } else {
            // Non-PJAX history entry (initial hard load) — full reload for safety
            window.location.reload();
        }
    });

    // Mark the initial page load in history so popstate can identify PJAX entries
    history.replaceState({ pjax: true, url: window.location.href }, '');

    // Expose the initial controller so page scripts loaded before this file
    // (which shouldn't happen, but as a safety net) can still use it
    refreshController();
})();
