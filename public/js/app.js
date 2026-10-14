/**
 * App layout behaviour (authenticated pages). Loaded once at the end of <body>, outside
 * #pjax-content, so everything here uses event delegation and keeps working after PJAX swaps.
 */
(function () {
    'use strict';

    // --- Mobile menu & user dropdown ---
    var mobileMenuBtn = document.getElementById('mobile-menu-button');
    var mobileMenu = document.getElementById('mobile-menu');

    window.closeMobileMenu = function () {
        if (!mobileMenu) return;
        mobileMenu.classList.add('hidden');
        mobileMenuBtn.setAttribute('aria-expanded', 'false');
        document.getElementById('mobile-menu-open-icon').classList.remove('hidden');
        document.getElementById('mobile-menu-close-icon').classList.add('hidden');
    };

    if (mobileMenuBtn && mobileMenu) {
        mobileMenuBtn.addEventListener('click', function () {
            var isOpen = !mobileMenu.classList.toggle('hidden');
            mobileMenuBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            document.getElementById('mobile-menu-open-icon').classList.toggle('hidden', isOpen);
            document.getElementById('mobile-menu-close-icon').classList.toggle('hidden', !isOpen);
        });
    }

    var userMenuBtn = document.getElementById('user-menu-button');
    var userDropdownMenu = document.getElementById('user-dropdown-menu');

    if (userMenuBtn && userDropdownMenu) {
        userMenuBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            userDropdownMenu.classList.toggle('hidden');
        });

        document.addEventListener('click', function (e) {
            if (!userMenuBtn.contains(e.target) && !userDropdownMenu.contains(e.target)) {
                userDropdownMenu.classList.add('hidden');
            }
        });
    }

    // --- Mandatory digital signature setup (the modal is only rendered when the user has no key) ---
    var secModal = document.getElementById('security-key-modal');
    if (secModal) {
        secModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        // Prevent closing on backdrop click by removing the triggering class
        var backdrop = secModal.querySelector('.close-modal-backdrop');
        if (backdrop) backdrop.classList.remove('close-modal-backdrop');
    }

    // --- <form data-trim-input="field"> trims that field before submit (capture phase: runs before page handlers) ---
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.trimInput) return;
        var field = form.elements[form.dataset.trimInput];
        if (field && typeof field.value === 'string') {
            field.value = field.value.trim();
        }
    }, true);

    // --- Global confirmation modal for <form class="confirm-action" data-message="..."> ---
    var activeConfirmForm = null;
    var globalConfirmModal = document.getElementById('global-confirmation-modal');
    var globalConfirmMessage = document.getElementById('global-confirmation-message');
    var globalConfirmBtn = document.getElementById('global-confirm-btn');

    // Delegated, so forms added by PJAX navigation are covered too
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement) || !form.classList.contains('confirm-action') || !globalConfirmModal) return;

        if (form.dataset.confirmed === 'true') {
            // Confirmed through the modal: let it continue to other listeners / native submit
            delete form.dataset.confirmed;
            return;
        }
        e.preventDefault();
        activeConfirmForm = form;
        globalConfirmMessage.textContent = form.dataset.message || 'Are you sure you want to proceed?';

        globalConfirmModal.classList.remove('hidden');
        requestAnimationFrame(function () {
            globalConfirmModal.querySelector('.fixed.inset-0.bg-gray-900').classList.add('opacity-100');
            globalConfirmModal.querySelector('.relative.w-full').classList.add('scale-100', 'opacity-100');
        });
    });

    if (globalConfirmBtn) {
        globalConfirmBtn.addEventListener('click', function () {
            if (!activeConfirmForm) return;
            globalConfirmModal.classList.add('hidden');
            activeConfirmForm.dataset.confirmed = 'true';
            // Dispatch submit so other JS listeners can intercept it; otherwise submit natively
            var submitEvent = new Event('submit', { bubbles: true, cancelable: true });
            var cancelled = !activeConfirmForm.dispatchEvent(submitEvent);
            if (!cancelled) {
                activeConfirmForm.submit();
            }
        });
    }

    // --- Generic modal open/close: [data-modal] on .open-modal-btn / .close-modal-btn / .close-modal-backdrop ---
    document.addEventListener('click', function (e) {
        var closer = e.target.closest('.close-modal-btn, .close-modal-backdrop');
        if (closer && closer.dataset.modal) {
            // Backdrop closes only when the backdrop itself (not a child) was clicked
            if (closer.classList.contains('close-modal-backdrop') && e.target !== closer) return;
            var modal = document.getElementById(closer.dataset.modal);
            if (modal) {
                closeGenericModal(modal);
            }
            return;
        }

        var opener = e.target.closest('.open-modal-btn');
        if (opener && opener.dataset.modal) {
            e.preventDefault();
            var target = document.getElementById(opener.dataset.modal);
            if (target) {
                target.classList.remove('hidden');
                modalReturnFocus = opener;
                focusFirstField(target);
            }
        }
    });

    // Keyboard users: focus moves into an opened modal, Escape closes it (only modals that offer a
    // close button; the forced key-setup dialog has none), and focus returns to the opener
    var modalReturnFocus = null;

    function focusFirstField(modal) {
        var field = modal.querySelector('input:not([type="hidden"]), select, textarea, button:not(.close-modal-btn)');
        if (field) setTimeout(function () { field.focus(); }, 0);
    }

    function closeGenericModal(modal) {
        modal.classList.add('hidden');
        if (modal.id === 'global-confirmation-modal') {
            activeConfirmForm = null;
        }
        if (modalReturnFocus && document.contains(modalReturnFocus)) {
            modalReturnFocus.focus();
        }
        modalReturnFocus = null;
    }

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var open = Array.prototype.filter.call(document.querySelectorAll('[role="dialog"]'), function (m) {
            return !m.classList.contains('hidden') && m.querySelector('.close-modal-btn');
        });
        if (open.length) {
            closeGenericModal(open[open.length - 1]);
        }
    });

    // --- Copy buttons: <button data-copy-text="..."> with .copy-icon / .check-icon children ---
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-copy-text]');
        if (!btn) return;
        var copyIcon = btn.querySelector('.copy-icon');
        var checkIcon = btn.querySelector('.check-icon');
        if (copyIcon) copyIcon.style.display = 'none';
        if (checkIcon) checkIcon.style.display = 'block';
        navigator.clipboard.writeText(btn.dataset.copyText);
        setTimeout(function () {
            if (copyIcon) copyIcon.style.display = 'block';
            if (checkIcon) checkIcon.style.display = 'none';
        }, 2000);
    });

    // --- Notifications & toasts ---
    window.__shownNotifIds = window.__shownNotifIds || new Set();

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // Auto-dismiss toast messages after 5 seconds
    function setupToastAutoDismiss() {
        document.querySelectorAll('#toast-container .toast-message').forEach(function (toast) {
            if (toast.dataset.dismissScheduled) return;
            toast.dataset.dismissScheduled = 'true';
            setTimeout(function () {
                if (toast.parentNode) {
                    toast.style.transition = 'opacity 0.5s ease-out';
                    toast.style.opacity = '0';
                    setTimeout(function () { toast.remove(); }, 500);
                }
            }, 5000);
        });
    }

    // Seed known unread notification IDs from the DOM
    function syncShownNotificationIds() {
        document.querySelectorAll('#notification-list .mark-notification-read').forEach(function (btn) {
            if (btn.dataset.id) {
                window.__shownNotifIds.add(String(btn.dataset.id));
            }
        });
    }

    var TOAST_STYLES = {
        info: {
            border: 'border-blue-500', bg: 'bg-blue-50 dark:bg-blue-900', icon: 'text-blue-700 dark:text-blue-400',
            title: 'text-blue-800 dark:text-blue-300', text: 'text-blue-700 dark:text-blue-400',
            svg: '<path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />'
        },
        success: {
            border: 'border-green-500', bg: 'bg-green-50 dark:bg-green-900', icon: 'text-green-700 dark:text-green-400',
            title: 'text-green-800 dark:text-green-300', text: 'text-green-700 dark:text-green-400',
            svg: '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />'
        },
        error: {
            border: 'border-red-500', bg: 'bg-red-50 dark:bg-red-900', icon: 'text-red-700 dark:text-red-400',
            title: 'text-red-800 dark:text-red-300', text: 'text-red-700 dark:text-red-400',
            svg: '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />'
        }
    };

    // Fetch unread notifications to surface background job updates as toasts
    async function pollUnreadNotifications() {
        try {
            var res = await fetch('/api/notifications/unread');
            if (!res.ok) return;
            var data = await res.json();
            if (!data.success || !Array.isArray(data.notifications)) return;

            var toastContainer = document.getElementById('toast-container');
            data.notifications.forEach(function (notif) {
                var notifIdStr = String(notif.id);
                if (window.__shownNotifIds.has(notifIdStr)) return;
                window.__shownNotifIds.add(notifIdStr);
                if (!toastContainer) return;

                var s = TOAST_STYLES[notif.type] || TOAST_STYLES.info;
                // Title/message can contain document data, so they are escaped
                var toastHtml =
                    '<div role="alert" class="rounded-md border ' + s.border + ' ' + s.bg + ' p-4 shadow-sm mb-3 relative toast-message pointer-events-auto animate-slide-in-right">' +
                        '<div class="flex items-start gap-4">' +
                            '<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="-mt-0.5 w-6 h-6 ' + s.icon + '">' + s.svg + '</svg>' +
                            '<div class="flex-1 pr-6">' +
                                '<strong class="block leading-tight font-medium ' + s.title + '"> ' + escapeHtml(notif.title) + ' </strong>' +
                                '<p class="mt-0.5 text-sm ' + s.text + '">' + escapeHtml(notif.message) + '</p>' +
                            '</div>' +
                            '<button class="mark-notification-read absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition" data-id="' + escapeHtml(notif.id) + '" title="Dismiss">' +
                                '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>' +
                            '</button>' +
                        '</div>' +
                    '</div>';
                toastContainer.insertAdjacentHTML('beforeend', toastHtml);
                setupToastAutoDismiss();
            });
        } catch (err) {
            console.error('[DTS Notif System] Error polling unread notifications:', err);
        }
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    async function markRead(body) {
        var res = await fetch('/api/notifications/mark-read', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify(body)
        });
        return res.json();
    }

    // Delegated handlers for the notification dropdown, dismiss and mark-as-read buttons
    document.addEventListener('click', async function (e) {
        var notifBtn = e.target.closest('#notification-menu-button');
        if (notifBtn) {
            e.stopPropagation();
            var dropdown = document.getElementById('notification-dropdown-menu');
            if (dropdown) dropdown.classList.toggle('hidden');
            return;
        }

        var notifDropdown = document.getElementById('notification-dropdown-menu');
        if (notifDropdown && !notifDropdown.contains(e.target) && !notifDropdown.classList.contains('hidden')) {
            notifDropdown.classList.add('hidden');
        }

        var markReadBtn = e.target.closest('.mark-notification-read');
        if (markReadBtn) {
            e.stopPropagation();
            var notifId = markReadBtn.dataset.id;
            if (!notifId) return;
            try {
                var data = await markRead({ id: notifId });
                if (data.success) {
                    var alertEl = markReadBtn.closest('[role="alert"]');
                    if (alertEl) alertEl.remove();
                }
            } catch (err) {
                console.error('[DTS Notif System] Error marking notification read:', err);
            }
            return;
        }

        var dismissBtn = e.target.closest('.dismiss-alert');
        if (dismissBtn) {
            e.stopPropagation();
            var alertBox = dismissBtn.closest('[role="alert"]');
            if (alertBox) alertBox.remove();
            return;
        }

        var markAllBtn = e.target.closest('#mark-all-read');
        if (markAllBtn) {
            e.stopPropagation();
            try {
                var result = await markRead({});
                if (result.success) {
                    var list = document.getElementById('notification-list');
                    if (list) {
                        list.innerHTML = '<p class="text-sm text-gray-500 dark:text-gray-400 p-4 text-center">No new notifications.</p>';
                    }
                    markAllBtn.remove();
                }
            } catch (err) {
                console.error('[DTS Notif System] Error marking all notifications read:', err);
            }
        }
    });

    // Lifecycle: this file runs at the end of <body>, so the DOM is ready now
    setupToastAutoDismiss();
    syncShownNotificationIds();

    document.addEventListener('dts:page-loaded', function () {
        setupToastAutoDismiss();
        syncShownNotificationIds();
        pollUnreadNotifications();
    });

    // Periodically poll for background notifications every 30s
    setInterval(pollUnreadNotifications, 30000);
})();
