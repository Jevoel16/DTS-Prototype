// Document details page: back-button origin and PIN-signed freeze/unfreeze
(function () {
    // Named so both DOMContentLoaded (hard load) and dts:page-loaded (PJAX swap)
    // can trigger re-binding of sign-action form handlers and back button origin.
    function initShowDocumentPage() {
        const backBtn = document.getElementById('doc-back-button');
        if (backBtn) {
            const origin = sessionStorage.getItem('dts_doc_origin');
            if (origin && origin !== window.location.pathname && !origin.includes('/documents/')) {
                backBtn.setAttribute('href', origin);
            }
        }

        document.querySelectorAll('form.sign-action').forEach(form => {
            form.addEventListener('submit', function(e) {
                const pinInput = this.querySelector('.sign-pin-input');
                if (pinInput && !pinInput.value) {
                    e.preventDefault();
                    const msg = this.dataset.message || "Enter your Security PIN to sign this action.";
                    window.SigningModal.show(msg, function(pin) {
                        pinInput.value = pin;
                        form.submit();
                    });
                }
            });
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initShowDocumentPage);
    } else {
        initShowDocumentPage();
    }
})();
