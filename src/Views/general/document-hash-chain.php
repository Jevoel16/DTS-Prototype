<?php $title = 'Hash Chain — ' . ($document['tracking_code'] ?? 'Document'); ob_start(); ?>
<h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
    Hash Chain for Document: <?php echo htmlspecialchars($document['tracking_code']); ?>
</h2>
<?php $header = ob_get_clean(); ?>

<?php ob_start(); ?>
<div class="py-10">
    <div class="mx-[20vh] sm:px-6 lg:px-8 space-y-6">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <div class="mb-6 flex justify-between items-center">
                    <h3 class="text-lg font-bold">Document: <?php echo htmlspecialchars($document['tracking_code']); ?></h3>
                    <a href="/documents/<?php echo htmlspecialchars($document['tracking_code']); ?>" class="inline-flex items-center px-4 py-2 bg-accent-2 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-accent-2-hover active:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-accent-1 transition ease-in-out duration-150">
                        Back to Document
                    </a>
                </div>

                <?php
                    foreach ($logs as &$log) {
                        $log['user_name_text'] = htmlspecialchars($log['user_name'] ?? 'System');
                        
                        $prevHash = $log['previous_hash'] ?? 'N/A';
                        $prevHashEsc = htmlspecialchars($prevHash);
                        $prevHashAddSlashes = htmlspecialchars(addslashes($prevHash));
                        if ($prevHash !== 'N/A' && !empty($prevHash)) {
                            $log['prev_hash_html'] = sprintf(
                                '<div class="flex items-start justify-between gap-1 group">
                                    <span id="prev-hash-%s" class="font-mono break-all text-gray-500 dark:text-gray-400" title="%s">%s</span>
                                    <button onclick="const btn=this; btn.querySelector(\'.copy-icon\').style.display=\'none\'; btn.querySelector(\'.check-icon\').style.display=\'block\'; navigator.clipboard.writeText(\'%s\'); setTimeout(() => { btn.querySelector(\'.copy-icon\').style.display=\'block\'; btn.querySelector(\'.check-icon\').style.display=\'none\'; }, 2000);" class="text-gray-400 hover:text-accent-1 transition-colors focus:outline-none shrink-0 mt-0.5" title="Copy Previous Hash">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="copy-icon h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="check-icon h-3.5 w-3.5 text-green-500" style="display: none;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </button>
                                </div>',
                                $log['id'],
                                $prevHashEsc,
                                $prevHashEsc,
                                $prevHashAddSlashes
                            );
                        } else {
                            $log['prev_hash_html'] = '<span class="font-mono text-gray-400">N/A</span>';
                        }

                        $currHash = $log['hash'] ?? 'N/A';
                        $currHashEsc = htmlspecialchars($currHash);
                        $currHashAddSlashes = htmlspecialchars(addslashes($currHash));
                        if ($currHash !== 'N/A' && !empty($currHash)) {
                            $log['curr_hash_html'] = sprintf(
                                '<div class="flex items-start justify-between gap-1 group">
                                    <span id="curr-hash-%s" class="font-mono break-all text-gray-500 dark:text-gray-400" title="%s">%s</span>
                                    <button onclick="const btn=this; btn.querySelector(\'.copy-icon\').style.display=\'none\'; btn.querySelector(\'.check-icon\').style.display=\'block\'; navigator.clipboard.writeText(\'%s\'); setTimeout(() => { btn.querySelector(\'.copy-icon\').style.display=\'block\'; btn.querySelector(\'.check-icon\').style.display=\'none\'; }, 2000);" class="text-gray-400 hover:text-accent-1 transition-colors focus:outline-none shrink-0 mt-0.5" title="Copy Current Hash">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="copy-icon h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="check-icon h-3.5 w-3.5 text-green-500" style="display: none;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </button>
                                </div>',
                                $log['id'],
                                $currHashEsc,
                                $currHashEsc,
                                $currHashAddSlashes
                            );
                        } else {
                            $log['curr_hash_html'] = '<span class="font-mono text-gray-400">N/A</span>';
                        }
                    }
                    unset($log);

                    $tableConfig = [
                        'wrapper_classes' => 'overflow-x-auto',
                        'columns' => [
                            ['key' => 'created_at', 'label' => 'Timestamp', 'width' => 'w-[12%]', 'type' => 'date'],
                            ['key' => 'action', 'label' => 'Action', 'width' => 'w-[24%]', 'wrap' => true],
                            ['key' => 'user_name_text', 'label' => 'Performed By', 'width' => 'w-[14%]', 'wrap' => true],
                            ['key' => 'prev_hash_html', 'label' => 'Previous Hash', 'width' => 'w-[25%]', 'type' => 'raw', 'wrap' => true],
                            ['key' => 'curr_hash_html', 'label' => 'Current Hash', 'width' => 'w-[25%]', 'type' => 'raw', 'wrap' => true]
                        ],
                        'data' => $logs,
                        'empty_message' => 'No hash chain history found for this document.'
                    ];
                    require BASE_PATH . '/src/Views/components/table.php';
                ?>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); ?>

<?php require BASE_PATH . '/src/Views/layouts/app.php'; ?>
