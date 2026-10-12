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
<script src="/js/scan-qr-modal.js"></script>
