<!-- Digital Signature Modal -->
<?php

$modalId = 'signing-modal';
$modalTitle = 'Digital Signature';
$modalSize = 3; // max-w-md
$hideCloseButton = true;
$modalContent = '
<div class="flex items-center mb-4 text-accent-1 dark:text-accent-1-hover">
    <svg class="w-8 h-8 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
    </svg>
    <span class="text-sm font-medium">Authentication Required</span>
</div>

<p id="signing-modal-message" class="mb-4 text-gray-600 dark:text-gray-400">
    Please enter your Security PIN to cryptographically sign this transaction.
</p>

<div class="mb-4">
    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
        Security PIN
    </label>
    <input type="password" id="signing-modal-pin" class="block w-full border-gray-300 dark:border-gray-600 rounded-md shadow-sm sm:text-sm p-2 border bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-accent-1 focus:border-accent-1" placeholder="Enter your PIN..." required>
</div>
';
$modalFooter = '
<button type="button" id="signing-modal-cancel" class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-accent-1 sm:text-sm dark:bg-accent-2 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-accent-2-hover">
    Cancel
</button>
<button type="button" id="signing-modal-confirm" class="inline-flex items-center px-4 py-2 bg-accent-1 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-accent-1-hover active:bg-accent-1-active focus:outline-none focus:border-accent-1-border-active focus:ring focus:ring-accent-1-light transition">
    Sign & Confirm
</button>
';

require BASE_PATH . '/src/Views/components/modal.php';
?>

<script src="/js/signing-modal.js"></script>
