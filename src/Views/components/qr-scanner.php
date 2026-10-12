<?php
/**
 * Reusable QR Scanner / Tracker Input Component
 * 
 * Expected variables:
 * @var string $finderTitle       The header title
 * @var string $finderDescription The description text
 * @var string $finderActionUrl   The form action endpoint
 * @var string $finderFormId      (Optional) Form ID, defaults to 'find-form'
 * @var string $finderButtonText  (Optional) Button text, defaults to 'Find'
 * @var string $finderInputName   (Optional) Input name, defaults to 'tracking_code'
 */

$finderFormId = $finderFormId ?? 'find-form';
$finderButtonText = $finderButtonText ?? 'Find';
$finderInputName = $finderInputName ?? 'tracking_code';
?>

<div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
    <div class="p-6 text-gray-900 dark:text-gray-100">
        <h3 class="text-2xl font-bold mb-4"><?= htmlspecialchars($finderTitle) ?></h3>
        <p class="mb-6 text-gray-600 dark:text-gray-400"><?= htmlspecialchars($finderDescription) ?></p>

        <form id="<?= htmlspecialchars($finderFormId) ?>" action="<?= htmlspecialchars($finderActionUrl) ?>" method="POST" onsubmit="this.elements['<?= htmlspecialchars($finderInputName) ?>'].value = this.elements['<?= htmlspecialchars($finderInputName) ?>'].value.trim()">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <div>
                <label for="<?= htmlspecialchars($finderInputName) ?>" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tracking Code</label>
                <div class="mt-1 flex rounded-md shadow-sm">
                    <input type="text" name="<?= htmlspecialchars($finderInputName) ?>" id="<?= htmlspecialchars($finderInputName) ?>" class="block w-full rounded-none rounded-l-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-accent-1 focus:ring-accent-1 sm:text-sm p-2 border" placeholder="DEPED-XXXXXXXXXX" required>
                    <button type="submit" class="relative -ml-px inline-flex items-center space-x-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-accent-2 focus:border-accent-1 focus:outline-none focus:ring-1 focus:ring-accent-1 transition">
                        <span><?= htmlspecialchars($finderButtonText) ?></span>
                    </button>
                </div>
            </div>
        </form>
        
        <button type="button" id="scan-qr-btn" class="mt-4 inline-flex items-center px-4 py-2 bg-accent-1 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-accent-1-hover active:bg-accent-1-active focus:outline-none focus:border-accent-1-border-active focus:ring focus:ring-accent-1-light disabled:opacity-25 transition">
            Scan QR Code
        </button>
    </div>
</div>
