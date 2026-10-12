<?php $title = 'Backup Manager'; ob_start(); ?>
<h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
    Backup Manager
</h2>
<?php $header = ob_get_clean(); ?>

<?php ob_start(); ?>
<div class="py-6 sm:py-10">
    <div class="px-4 sm:px-6 lg:px-8 xl:mx-[20vh] space-y-6">
        <!-- Action Buttons -->
        <div class="flex justify-end mb-6">
            <form id="create-backup-form" action="/system/backups/create" method="POST">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <button id="create-backup-button" type="submit" class="inline-flex items-center px-4 py-2 bg-accent-1 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-accent-1-hover transition">
                    Create Database Backup Now
                </button>
            </form>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div id="success-message" class="mb-4 p-4 bg-success-light text-success-active dark:bg-green-900/30 dark:text-green-300 rounded-lg">
                <?= htmlspecialchars($_SESSION['success']) ?>
                <?php unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="mb-4 p-4 bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300 rounded-lg">
                <?= htmlspecialchars($_SESSION['error']) ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Backup List -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-4 sm:p-6 text-gray-900 dark:text-gray-100">
                <h3 class="text-lg font-semibold mb-4">Available Backups</h3>
                <form action="/system/backups" method="GET" class="mb-4">
                    <div class="flex flex-wrap items-center gap-4">
                        <input type="text" name="search" placeholder="Search by file name or date" class="w-full sm:w-80 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:border-accent-1 focus:ring-accent-1 rounded-md shadow-sm p-2" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        <a href="/system/backups" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">Clear</a>
                    </div>
                </form>
                <?php
                    foreach ($backups as &$backup) {
                        $backup['date_html'] = htmlspecialchars(date('M d, Y, h:i A', $backup['last_modified_raw']));
                        $backup['file_name_html'] = sprintf('<span class="font-mono">%s</span>', htmlspecialchars($backup['file_name']));
                        $backup['actions_html'] = sprintf('
                            <div class="flex justify-end items-center space-x-4">
                                <a href="/system/backups/download/%s" class="text-accent-1 hover:text-accent-1-active">Download</a>
                                <button type="button" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200" data-restore-file="%s">Restore</button>
                                <form action="/system/backups/delete/%s" method="POST" class="confirm-action" data-message="Delete this backup?">
                                    <input type="hidden" name="csrf_token" value="' . ($_SESSION['csrf_token'] ?? '') . '">
                                    <button type="submit" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">Delete</button>
                                </form>
                            </div>
                        ', urlencode($backup['file_name']), htmlspecialchars($backup['file_name'], ENT_QUOTES), urlencode($backup['file_name']));
                    }
                    unset($backup);

                    $tableConfig = [
                        'wrapper_classes' => 'overflow-x-auto',
                        'columns' => [
                            ['key' => 'date_html', 'label' => 'Date', 'width' => 'w-[25%]', 'type' => 'raw'],
                            ['key' => 'file_name_html', 'label' => 'File Name', 'width' => 'w-[40%]', 'type' => 'raw'],
                            ['key' => 'file_size', 'label' => 'Size', 'width' => 'w-[15%]'],
                            ['key' => 'actions_html', 'label' => 'Actions', 'width' => 'w-[20%]', 'type' => 'raw']
                        ],
                        'data' => $backups,
                        'empty_message' => 'No backups found.'
                    ];
                    require BASE_PATH . '/src/Views/components/table.php';
                ?>
            </div>
        </div>
    </div>
</div>

<!-- Restore Modal -->
<div id="restore-modal" style="display:none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-75">
    <div class="bg-white dark:bg-gray-800 p-4 sm:p-6 rounded-lg shadow-xl max-w-md w-full">
        <form id="restore-form" method="POST" class="confirm-action" data-message="Restore database?">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <h2 class="text-lg font-medium text-red-600 dark:text-red-400">FINAL WARNING: Restore Database?</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Restore <strong id="restore-file-name"></strong>?
            </p>
            <div class="mt-4">
                <label for="restore-pin" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Your Security PIN</label>
                <input type="password" name="pin" id="restore-pin" required autocomplete="off" class="mt-1 block w-full border-gray-300 dark:border-gray-600 rounded-md shadow-sm sm:text-sm p-2 border bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-accent-1 focus:border-accent-1">
            </div>
            <div class="mt-6 flex justify-end space-x-2">
                <button type="button" id="restore-cancel-btn" class="px-4 py-2 bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 rounded-md transition">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white hover:bg-red-700 rounded-md transition">Restore Now</button>
            </div>
        </form>
    </div>
</div>

<script src="/js/backups.js"></script>

<?php $content = ob_get_clean(); ?>
<?php require BASE_PATH . '/src/Views/layouts/app.php'; ?>
