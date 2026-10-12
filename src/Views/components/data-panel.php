<?php
/**
 * Reusable Data Panel Component
 * Wraps the title, table filters, table, and pagination into a standardized panel.
 * 
 * Expected variables:
 * @var string $panelTitle      The header title (e.g., 'Your Tasks', 'Recently Handled Documents')
 * @var string $panelActionHtml (Optional) Raw HTML for action buttons in the header
 * @var array  $filterConfig    (Optional) Configuration array for table-filters.php
 * @var array  $tableConfig     Configuration array for table.php
 * @var \App\Utils\Paginator $paginator (Optional) Paginator object for pagination.php
 */

$panelTitle = $panelTitle ?? 'Documents';
?>

<div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
    <div class="p-6 text-gray-900 dark:text-gray-100">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold"><?= htmlspecialchars($panelTitle) ?></h3>
            <?php if (isset($panelActionHtml)): ?>
                <div>
                    <?= $panelActionHtml ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($filterConfig)): ?>
            <?php require BASE_PATH . '/src/Views/components/table-filters.php'; ?>
        <?php endif; ?>

        <?php if (!empty($tableConfig)): ?>
            <?php require BASE_PATH . '/src/Views/components/table.php'; ?>
        <?php endif; ?>
        
        <?php if (isset($paginator)): ?>
            <div class="mt-4">
                <?php 
                    if ($paginator instanceof \App\Utils\CursorPaginator) {
                        require BASE_PATH . '/src/Views/components/cursor-pagination.php';
                    } else {
                        require BASE_PATH . '/src/Views/components/pagination.php';
                    }
                ?>
            </div>
        <?php endif; ?>
    </div>
</div>
