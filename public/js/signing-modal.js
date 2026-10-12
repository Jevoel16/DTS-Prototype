// Digital signature PIN prompt (window.SigningModal), used by pages that sign actions
(function () {
    window.SigningModal = {
        callback: null,
        show: function(message, callback) {
            const cachedPin = sessionStorage.getItem('dts_security_pin');
            if (cachedPin) {
                // Pin is cached client-side in browser memory
                callback(cachedPin);
                return;
            }
            this.callback = callback;
            document.getElementById('signing-modal-message').innerText = message;
            document.getElementById('signing-modal-pin').value = '';
            document.getElementById('signing-modal').classList.remove('hidden');
            setTimeout(() => {
                document.getElementById('signing-modal-pin').focus();
            }, 100);
        },
        hide: function() {
            document.getElementById('signing-modal').classList.add('hidden');
            this.callback = null;
        }
    };

    // Named so both DOMContentLoaded (hard load) and dts:page-loaded (PJAX swap)
    // can trigger re-binding of confirm/cancel buttons inside the modal DOM.
    function initSigningModal() {
        const confirmBtn = document.getElementById('signing-modal-confirm');
        const cancelBtn = document.getElementById('signing-modal-cancel');
        const pinInput = document.getElementById('signing-modal-pin');

        if (!confirmBtn) return;

        confirmBtn.addEventListener('click', function() {
            const pin = pinInput.value;
            if (!pin) {
                alert('Please enter your Security PIN.');
                pinInput.focus();
                return;
            }

            // Store the PIN securely in client-side sessionStorage (cleared when tab closes)
            sessionStorage.setItem('dts_security_pin', pin);

            if (window.SigningModal.callback) {
                const cb = window.SigningModal.callback;
                window.SigningModal.hide();
                cb(pin); // Send the actual PIN to the server, not 'CACHED_PIN'
            }
        });

        cancelBtn.addEventListener('click', function() {
            window.SigningModal.hide();
        });

        pinInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                confirmBtn.click();
            }
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSigningModal);
    } else {
        initSigningModal();
    }
})();
