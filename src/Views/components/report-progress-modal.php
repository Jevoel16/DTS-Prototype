<?php
$modalId = 'report-progress-modal';
$modalTitle = 'Generating Report';
$modalSize = 4; // lg
$hideCloseButton = true;
$modalContent = '
<div class="mt-4">
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
        Please wait while your report is being generated. This may take some time.
    </p>
    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
        <div id="report-progress-bar" class="bg-accent-1 h-2.5 rounded-full transition-all duration-300" style="width: 0%"></div>
    </div>
    <div class="mt-2 flex justify-between text-sm text-gray-600 dark:text-gray-300">
        <span id="report-progress-text">Job queued...</span>
        <span id="report-progress-time">Est. time remaining: calculating...</span>
    </div>
    <div id="report-download-container"></div>
</div>
';
$modalFooter = '
<button id="close-report-modal" type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-500 text-base font-medium text-white hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition">
    Cancel Report
</button>
';

require BASE_PATH . '/src/Views/components/modal.php';
?>
