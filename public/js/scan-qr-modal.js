// QR scanner modal: fills #tracking_code and submits #scan-form / #find-form
(function () {
    // Named so both DOMContentLoaded (hard load) and dts:page-loaded (PJAX swap)
    // can trigger re-binding of the QR scanner modal buttons.
    function initScanQrModal() {
        const qrModal = document.getElementById('qr-modal');
        const scanBtn = document.getElementById('scan-qr-btn');
        const closeBtn = document.getElementById('close-qr-modal-btn');

        // This partial expects the page to have inputs with id="tracking_code" and form id="scan-form" (or find-form)
        const trackingInput = document.getElementById('tracking_code');
        const targetForm = document.getElementById('scan-form') || document.getElementById('find-form');

        let html5QrcodeScanner = null;

        function onScanSuccess(decodedText, decodedResult) {
            if (html5QrcodeScanner) {
                html5QrcodeScanner.clear().then(() => {
                    qrModal.classList.add('hidden');
                    if (trackingInput && targetForm) {
                        trackingInput.value = decodedText;
                        if (typeof targetForm.requestSubmit === 'function') {
                            targetForm.requestSubmit();
                        } else {
                            if (targetForm.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }))) {
                                targetForm.submit();
                            }
                        }
                    }
                });
            }
        }

        if (scanBtn) {
            scanBtn.addEventListener('click', () => {
                qrModal.classList.remove('hidden');
                if (!html5QrcodeScanner) {
                    html5QrcodeScanner = new Html5QrcodeScanner(
                        "qr-reader", { fps: 10, qrbox: 250 });
                }
                html5QrcodeScanner.render(onScanSuccess);
            });
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                if (html5QrcodeScanner) {
                    html5QrcodeScanner.clear().then(() => {
                        qrModal.classList.add('hidden');
                    });
                } else {
                    qrModal.classList.add('hidden');
                }
            });
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initScanQrModal);
    } else {
        initScanQrModal();
    }
})();
