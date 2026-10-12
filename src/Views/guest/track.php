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

<div class="mt-8 bg-gray-50 dark:bg-gray-800 p-4 sm:p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 text-center">
    <div class="flex flex-col sm:flex-row justify-center space-y-4 sm:space-y-0 sm:space-x-4">
        <a href="/" class="bg-gray-500 hover:bg-accent-2 text-white font-semibold py-3 px-6 rounded-md shadow transition duration-200">
            Submit a New Document
        </a>
        <button type="button" data-show-modal="track-modal" class="bg-primary hover:bg-primary-hover text-white font-semibold py-3 px-6 rounded-md shadow transition duration-200">
            Track Another Document
        </button>
    </div>
</div>

<!-- Track Modal -->
<div id="track-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center">
    <div class="relative mx-auto p-5 border w-full max-w-sm shadow-lg rounded-md bg-white dark:bg-gray-800 dark:border-gray-700">
        <div class="mt-3 text-center">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Track Another Document</h3>
            <form id="track-another-form" class="mt-4">
                <div class="mb-4 text-left">
                    <label for="tracking_code_input" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Enter Tracking Code:</label>
                    <input type="text" id="tracking_code_input" class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary dark:bg-gray-700 dark:text-white sm:text-sm" placeholder="e.g., DEPED-A1B2C3D4E5" required>
                </div>
                
                <div id="track-error-message" class="hidden bg-red-100 border border-red-400 text-red-700 dark:bg-red-900/30 dark:border-red-700/50 dark:text-red-300 px-4 py-3 rounded relative mb-4 text-sm text-left"></div>

                <div class="mb-4">
                    <button type="button" id="scan-qr-button" class="w-full border border-primary text-primary hover:bg-primary hover:text-white font-medium py-2 px-4 rounded transition duration-200 flex items-center justify-center dark:border-blue-400 dark:text-blue-400 dark:hover:bg-blue-600 dark:hover:text-white">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                        Scan QR Code
                    </button>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" data-hide-modal="track-modal" class="bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-accent-2 text-gray-800 dark:text-gray-200 font-medium py-2 px-4 rounded">
                        Close
                    </button>
                    <button type="submit" class="bg-primary hover:bg-primary-hover text-white font-medium py-2 px-4 rounded">
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
        <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
            <h3 class="text-lg font-medium">Scan QR Code</h3>
            <button id="close-qr-modal" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div id="qr-reader" style="width: 100%;"></div>
    </div>
</div>

<script src="/js/html5-qrcode.min.js"></script>
<script src="/js/guest-track.js"></script>
