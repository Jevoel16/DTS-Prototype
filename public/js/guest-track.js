// Guest tracking page: add codes, QR scanner, and status polling
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        const trackForm = document.getElementById('track-another-form');
        const trackModal = document.getElementById('track-modal');
        const trackingCodeInput = document.getElementById('tracking_code_input');
        const trackedDocumentsContainer = document.getElementById('tracked-documents-container');
        const trackErrorMessage = document.getElementById('track-error-message');

        const scanQrButton = document.getElementById('scan-qr-button');
        const qrScannerModal = document.getElementById('qr-scanner-modal');
        const closeQrModal = document.getElementById('close-qr-modal');
        let html5QrCode = null;

        function displayError(message) {
            trackErrorMessage.textContent = message;
            trackErrorMessage.classList.remove('hidden');
        }

        function clearError() {
            trackErrorMessage.classList.add('hidden');
            trackErrorMessage.textContent = '';
        }

        trackingCodeInput.addEventListener('input', clearError);

        // Asynchronously fetches and injects the document tracking card HTML, 
        // preventing duplicate queries for the same tracking code.
        async function trackDocument(trackingCode) {
            clearError();
            if (!trackingCode) {
                displayError('Please enter a tracking code.');
                return;
            }

            const urlParams = new URLSearchParams(window.location.search);
            let currentCodes = urlParams.get('codes') ? urlParams.get('codes').split(',') : [];

            if (currentCodes.includes(trackingCode)) {
                displayError(`Document ${trackingCode} is already being tracked on this page.`);
                return;
            }

            currentCodes.push(trackingCode);
            urlParams.set('codes', currentCodes.join(','));
            history.pushState(null, '', `?${urlParams.toString()}`);

            try {
                const response = await fetch(`/api/track/module/${trackingCode}`);
                if (!response.ok) {
                    if (response.status === 404) {
                        displayError(`Document with tracking code ${trackingCode} not found.`);
                    } else {
                        displayError('Error tracking document. Please try again.');
                    }
                    // Remove the bad code from URL
                    const failedCodeIndex = currentCodes.indexOf(trackingCode);
                    if(failedCodeIndex > -1) {
                        currentCodes.splice(failedCodeIndex, 1);
                        urlParams.set('codes', currentCodes.join(','));
                        history.pushState(null, '', `?${urlParams.toString()}`);
                    }
                    return;
                }
                const htmlContent = await response.text();

                // Remove the "No documents" alert if it exists
                const noDocsAlert = trackedDocumentsContainer.querySelector('.bg-blue-50');
                if (noDocsAlert) noDocsAlert.remove();

                trackedDocumentsContainer.insertAdjacentHTML('beforeend', htmlContent);
                trackModal.classList.add('hidden');
                trackingCodeInput.value = '';

            } catch (error) {
                console.error('Error fetching document module:', error);
                displayError('Network error. Please try again.');
            }
        }

        if (trackForm) {
            trackForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const trackingCode = trackingCodeInput.value.trim();
                trackDocument(trackingCode);
            });
        }

        function onScanSuccess(decodedText, decodedResult) {
            stopQrCodeScanner();
            trackDocument(decodedText);
        }

        function startQrCodeScanner() {
            trackModal.classList.add('hidden');
            qrScannerModal.style.display = 'block';

            if (!html5QrCode && window.Html5Qrcode) {
                html5QrCode = new window.Html5Qrcode("qr-reader");
                html5QrCode.start(
                    { facingMode: "environment" },
                    { fps: 10, qrbox: { width: 250, height: 250 } },
                    onScanSuccess,
                    (err) => {}
                ).catch(err => {
                    alert("Could not start QR scanner. Please grant camera permissions.");
                    stopQrCodeScanner();
                });
            }
        }

        function stopQrCodeScanner() {
            if (html5QrCode && html5QrCode.isScanning) {
                html5QrCode.stop().catch(() => {});
            }
            qrScannerModal.style.display = 'none';
        }

        scanQrButton.addEventListener('click', startQrCodeScanner);
        closeQrModal.addEventListener('click', stopQrCodeScanner);

        window.addEventListener('click', function(event) {
            if (event.target == qrScannerModal) {
                stopQrCodeScanner();
            }
        });
    });

    // Real-time Polling Logic
    const POLLING_INTERVAL = 60000; // 60 seconds

    // Periodically polls the server to check if any tracked document has changed status, automatically refreshing the card if it has.
    async function pollForUpdates() {
        const documentCards = document.querySelectorAll('.document-card');
        if (documentCards.length === 0) return;

        const trackingCodes = Array.from(documentCards).map(card => card.dataset.trackingCode);

        try {
            const response = await fetch(`/api/track/status?codes=${trackingCodes.join(',')}`);
            if (!response.ok) return;

            const statuses = await response.json();

            for (const update of statuses) {
                const card = document.querySelector(`.document-card[data-tracking-code="${update.tracking_code}"]`);
                if (card) {
                    const isChanged = card.dataset.status !== update.status || card.dataset.currentStep != update.current_step;

                    if (isChanged) {
                        const cardResponse = await fetch(`/api/track/module/${update.tracking_code}`);
                        if (cardResponse.ok) {
                            const newCardHtml = await cardResponse.text();
                            card.outerHTML = newCardHtml;
                        }
                    }
                }
            }
        } catch (error) {
            console.error('Error during polling:', error);
        }
    }

    setInterval(pollForUpdates, POLLING_INTERVAL);
})();
