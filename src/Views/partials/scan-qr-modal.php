<!-- QR Scanner Modal -->
<?php
$modalId = 'qr-modal';
$modalTitle = 'Scan QR Code';
$modalSize = 4; // lg
$hideCloseButton = true;
$modalContent = '
<div id="qr-reader" style="width: 100%;"></div>
';
$modalFooter = '
<button type="button" id="close-qr-modal-btn" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-accent-1 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-accent-2 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-accent-2-hover">
    Cancel
</button>
';

require BASE_PATH . '/src/Views/components/modal.php';
?>

<script src="/js/html5-qrcode.min.js"></script>
<script>
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
</script>
