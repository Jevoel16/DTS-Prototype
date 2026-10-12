<?php
/**
 * Reusable Pagination Component
 * 
 * Expected variables:
 * @var App\Utils\Paginator $paginator
 */
$totalPages = $paginator->getTotalPages();
$currentPage = $paginator->getCurrentPage();
$totalItems = $paginator->getTotalItems();
$perPage = $paginator->getLimit();
$offset = $paginator->getOffset();

if ($totalPages <= 1) {
    return;
}
?>

<nav role="navigation" aria-label="Pagination Navigation">
    <!-- Mobile View -->
    <div class="flex gap-2 items-center justify-between sm:hidden">
        <?php if ($currentPage <= 1): ?>
            <span class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 cursor-not-allowed leading-5 rounded-md">Previous</span>
        <?php else: ?>
            <a href="<?= $paginator->getUrl($currentPage - 1) ?>" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-800 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 leading-5 rounded-md hover:text-gray-700 dark:hover:text-gray-300">Previous</a>
        <?php endif; ?>

        <?php if ($currentPage >= $totalPages): ?>
            <span class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 cursor-not-allowed leading-5 rounded-md">Next</span>
        <?php else: ?>
            <a href="<?= $paginator->getUrl($currentPage + 1) ?>" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-800 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 leading-5 rounded-md hover:text-gray-700 dark:hover:text-gray-300">Next</a>
        <?php endif; ?>
    </div>

    <!-- Desktop View -->
    <div class="hidden sm:flex-1 sm:flex sm:gap-2 sm:items-center sm:justify-between">
        <!-- Showing text -->
        <div>
            <p class="text-sm text-gray-700 dark:text-gray-400 leading-5">
                Showing 
                <?php if ($totalItems > 0): ?>
                    <span class="font-medium text-gray-900 dark:text-gray-100"><?= $offset + 1 ?></span> to 
                    <span class="font-medium text-gray-900 dark:text-gray-100"><?= min($offset + $perPage, $totalItems) ?></span> of 
                <?php endif; ?>
                <span class="font-medium text-gray-900 dark:text-gray-100"><?= $totalItems ?></span> results
            </p>
        </div>

        <!-- Links -->
        <div>
            <span class="inline-flex shadow-sm rounded-md">
                
                <!-- Prev Arrow -->
                <?php if ($currentPage <= 1): ?>
                    <span aria-disabled="true">
                        <span class="inline-flex items-center px-2 py-2 text-sm font-medium text-gray-500 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 cursor-not-allowed rounded-l-md leading-5">&laquo;</span>
                    </span>
                <?php else: ?>
                    <a href="<?= $paginator->getUrl($currentPage - 1) ?>" class="inline-flex items-center px-2 py-2 text-sm font-medium text-gray-500 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-l-md leading-5 hover:text-gray-400 dark:hover:text-gray-300">&laquo;</a>
                <?php endif; ?>

                <!-- Page Numbers -->
                <?php 
                $startPage = max(1, $currentPage - 2);
                $endPage = min($totalPages, $currentPage + 2);
                
                if ($startPage > 1): ?>
                    <a href="<?= $paginator->getUrl(1) ?>" class="inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 leading-5 hover:text-gray-700 dark:hover:text-white">1</a>
                    <?php if ($startPage > 2): ?>
                        <span aria-disabled="true">
                            <span class="inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 cursor-default leading-5">...</span>
                        </span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <?php if ($i == $currentPage): ?>
                        <span aria-current="page">
                            <span class="inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-gray-900 dark:text-white bg-gray-200 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 cursor-default leading-5"><?= $i ?></span>
                        </span>
                    <?php else: ?>
                        <a href="<?= $paginator->getUrl($i) ?>" class="inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 leading-5 hover:text-gray-700 dark:hover:text-white"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                        <span aria-disabled="true">
                            <span class="inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 cursor-default leading-5">...</span>
                        </span>
                    <?php endif; ?>
                    <a href="<?= $paginator->getUrl($totalPages) ?>" class="inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 leading-5 hover:text-gray-700 dark:hover:text-white"><?= $totalPages ?></a>
                <?php endif; ?>

                <!-- Next Arrow -->
                <?php if ($currentPage >= $totalPages): ?>
                    <span aria-disabled="true">
                        <span class="inline-flex items-center px-2 py-2 -ml-px text-sm font-medium text-gray-500 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 cursor-not-allowed rounded-r-md leading-5">&raquo;</span>
                    </span>
                <?php else: ?>
                    <a href="<?= $paginator->getUrl($currentPage + 1) ?>" class="inline-flex items-center px-2 py-2 -ml-px text-sm font-medium text-gray-500 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-r-md leading-5 hover:text-gray-400 dark:hover:text-gray-300">&raquo;</a>
                <?php endif; ?>
            </span>
        </div>
    </div>
</nav>
