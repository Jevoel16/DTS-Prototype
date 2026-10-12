<?php
/**
 * Reusable Cursor Pagination Component
 * 
 * Expected variables:
 * @var App\Utils\CursorPaginator $paginator
 */
$totalItems = $paginator->getTotalItems();
$hasMore = $paginator->hasMore();
$nextUrl = $paginator->getNextUrl();
$items = $paginator->getItems();
?>

<nav role="navigation" aria-label="Pagination Navigation" class="flex items-center justify-between">
    <div>
        <p class="text-sm text-gray-700 dark:text-gray-400 leading-5">
            <?php if (isset($_GET['cursor'])): ?>
                <span class="font-medium text-gray-900 dark:text-gray-100">Continuing through results...</span>
            <?php else: ?>
                Showing <span class="font-medium text-gray-900 dark:text-gray-100"><?= count($items) ?></span> items.
            <?php endif; ?>
            Total: <span class="font-medium text-gray-900 dark:text-gray-100"><?= number_format($totalItems) ?></span> results.
        </p>
    </div>

    <div class="flex gap-2 items-center">
        <?php if (isset($_GET['cursor'])): ?>
            <!-- Go back to start, since strict cursor previous is complex, a simple Reset button is used -->
            <a href="?" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-800 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 leading-5 rounded-md hover:text-gray-700 dark:hover:text-gray-300 transition">Back to Start</a>
        <?php else: ?>
            <span class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-400 dark:text-gray-600 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 cursor-not-allowed leading-5 rounded-md">Back to Start</span>
        <?php endif; ?>

        <?php if ($hasMore && $nextUrl): ?>
            <a href="<?= htmlspecialchars($nextUrl) ?>" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-800 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 leading-5 rounded-md hover:text-gray-700 dark:hover:text-gray-300 transition">Next</a>
        <?php else: ?>
            <span class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-400 dark:text-gray-600 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 cursor-not-allowed leading-5 rounded-md">Next</span>
        <?php endif; ?>
    </div>
</nav>
