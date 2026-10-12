<div id="tracked-documents-container" class="space-y-6">
    <?php if (empty($documents)): ?>
        <div class="bg-blue-50 border border-blue-200 text-blue-700 px-6 py-8 rounded-lg shadow-sm text-center dark:bg-blue-900 dark:border-blue-800 dark:text-blue-200">
            <h4 class="text-xl font-bold mb-2">No documents are being tracked yet.</h4>
            <p>Enter a tracking code below to get started.</p>
        </div>
    <?php else: ?>
        <?php foreach ($documents as $document): ?>
            <?php include __DIR__ . '/partials/document-card.php'; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="mt-8 bg-gray-50 dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 text-center">
    <div class="flex flex-col sm:flex-row justify-center space-y-4 sm:space-y-0 sm:space-x-4">
        <a href="/" class="bg-gray-500 hover:bg-accent-2 text-white font-semibold py-3 px-6 rounded-md shadow transition duration-200">
            Submit a New Document
        </a>
        <button type="button" onclick="document.getElementById('track-modal').classList.remove('hidden')" class="bg-primary hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-md shadow transition duration-200">
            Track Another Document
        </button>
    </div>
</div>

<!-- Track Modal -->
<div id="track-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center">
    <div class="relative mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800 dark:border-gray-700">
        <div class="mt-3 text-center">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Track Another Document</h3>
            <form id="track-another-form" class="mt-4">
                <div class="mb-4 text-left">
                    <label for="tracking_code_input" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Enter Tracking Code:</label>
                    <input type="text" id="tracking_code_input" class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary dark:bg-gray-700 dark:text-white sm:text-sm" placeholder="e.g., DEPED-A1B2C3D4E5" required>
                </div>
                
                <div id="track-error-message" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 text-sm text-left"></div>

                <div class="mb-4">
                    <button type="button" id="scan-qr-button" class="w-full border border-primary text-primary hover:bg-primary hover:text-white font-medium py-2 px-4 rounded transition duration-200 flex items-center justify-center dark:border-blue-400 dark:text-blue-400 dark:hover:bg-blue-600 dark:hover:text-white">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                        Scan QR Code
                    </button>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="document.getElementById('track-modal').classList.add('hidden')" class="bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-accent-2 text-gray-800 dark:text-gray-200 font-medium py-2 px-4 rounded">
                        Close
                    </button>
                    <button type="submit" class="bg-primary hover:bg-blue-700 text-white font-medium py-2 px-4 rounded">
                        Track
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- QR Scanner Modal -->
<div id="qr-scanner-modal" class="qr-modal">
    <div class="qr-modal-content">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium">Scan QR Code</h3>
            <button id="close-qr-modal" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div id="qr-reader" style="width: 100%;"></div>
    </div>
</div>

<script src="/js/html5-qrcode.min.js"></script>
<script>
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
</script>
