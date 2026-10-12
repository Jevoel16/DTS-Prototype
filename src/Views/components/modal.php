<?php
// Default values if not set
$modalId = $modalId ?? 'default-modal';
$modalTitle = $modalTitle ?? 'Modal Title';
$modalSizeClass = 'sm:max-w-md';

if (isset($modalSize)) {
    if ($modalSize === 1) $modalSizeClass = 'sm:max-w-sm';
    elseif ($modalSize === 2) $modalSizeClass = 'sm:max-w-sm';
    elseif ($modalSize === 3) $modalSizeClass = 'sm:max-w-md';
    elseif ($modalSize === 4) $modalSizeClass = 'sm:max-w-lg';
    elseif ($modalSize === 5) $modalSizeClass = 'sm:max-w-xl';
    elseif ($modalSize === 6) $modalSizeClass = 'sm:max-w-2xl';
    elseif ($modalSize === 7) $modalSizeClass = 'sm:max-w-3xl';
    elseif ($modalSize === 8) $modalSizeClass = 'sm:max-w-4xl';
    else $modalSizeClass = 'sm:max-w-md';
}

$hideCloseButton = $hideCloseButton ?? false;
$modalContent = $modalContent ?? '';
$modalFooter = $modalFooter ?? '';
?>
<div id="<?php echo htmlspecialchars($modalId); ?>" class="fixed inset-0 z-50 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <!-- Backdrop -->
        <div class="fixed inset-0 transition-opacity bg-gray-500/75 dark:bg-gray-900/75 close-modal-backdrop" data-modal="<?php echo htmlspecialchars($modalId); ?>" aria-hidden="true"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <!-- Modal Content -->
        <div class="inline-block w-full align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle <?php echo $modalSizeClass; ?> sm:w-full">
            <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                        <?php echo htmlspecialchars($modalTitle); ?>
                    </h3>
                    <?php if (!$hideCloseButton): ?>
                    <button type="button" class="close-modal-btn text-gray-400 hover:text-gray-500 focus:outline-none" data-modal="<?php echo htmlspecialchars($modalId); ?>">
                        <span class="sr-only">Close</span>
                        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                    <?php endif; ?>
                </div>
                <div class="mt-2">
                    <?php echo $modalContent; ?>
                </div>
            </div>
            <?php if (!empty($modalFooter)): ?>
            <div class="bg-gray-50 dark:bg-gray-700/50 px-4 py-3 sm:px-6 flex justify-end space-x-3">
                <?php echo $modalFooter; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
