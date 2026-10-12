<?php $title = 'System Overview'; ob_start(); ?>
<h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
    System Overview
</h2>
<?php $header = ob_get_clean();
ob_start(); ?>
<div class="py-6 sm:py-10">
    <div class="px-4 sm:px-6 lg:px-8 xl:mx-[20vh] space-y-6">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-4 sm:p-6 text-gray-900 dark:text-gray-100">
                <div class="space-y-6">
                    <!-- Section: System Status Overview -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Failed Jobs -->
                        <div class="bg-gray-50 dark:bg-gray-700/50 p-4 sm:p-6 rounded-lg shadow">
                            <h4 class="text-lg font-bold mb-2">Failed Jobs</h4>
                            <div class="flex items-center justify-between">
                                <p class="text-3xl font-semibold <?= $appHealthMetrics['failed_jobs_count'] > 0 ? 'text-red-500' : '' ?>"><?= htmlspecialchars($appHealthMetrics['failed_jobs_count']) ?></p>
                                <?php if($appHealthMetrics['failed_jobs_count'] > 0): ?>
                                    <button id="view-failed-jobs" class="text-sm text-accent-1 dark:text-accent-1-hover hover:underline focus:outline-none">
                                        View Details
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <!-- Cache Status -->
                        <div class="bg-gray-50 dark:bg-gray-700/50 p-4 sm:p-6 rounded-lg shadow">
                            <h4 class="text-lg font-bold mb-2">Cache Status</h4>
                            <?php if($appHealthMetrics['cache_status']): ?>
                                <p class="text-3xl font-semibold text-green-500">Operational</p>
                            <?php else: ?>
                                <p class="text-3xl font-semibold text-red-500">Not Responding</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Section: Failed Jobs Details (Conditional) -->
                    <?php if($appHealthMetrics['failed_jobs_count'] > 0): ?>
                        <div id="failed-jobs-details" class="bg-red-50 dark:bg-red-900/10 pt-3 px-5 pb-5 rounded-lg shadow hidden">
                            <div class="flex flex-wrap justify-between items-center gap-3 mb-4 border-b border-red-200 dark:border-red-800 pb-2">
                                <h3 class="text-xl font-bold text-red-700 dark:text-red-400">Failed Jobs Details</h3>
                                <form action="/system-health/failed-jobs/delete-all" method="POST" class="confirm-action" data-message="Are you sure you want to clear ALL failed jobs?">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                    <button type="submit" class="text-xs text-red-600 dark:text-red-400 hover:underline">Clear All Jobs</button>
                                </form>
                            </div>
                            <?php
                                foreach($appHealthMetrics['failed_jobs'] as &$job) {
                                    $payloadData = json_decode($job['payload'], true);
                                    $jobName = $payloadData['displayName'] ?? 'Unknown Job';
                                    if (strpos($jobName, '\\') !== false) {
                                        $parts = explode('\\', $jobName);
                                        $jobName = end($parts);
                                    }
                                    
                                    $job['payload_html'] = sprintf('<span class="font-mono text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded" title="%s">%s</span>', htmlspecialchars($payloadData['displayName'] ?? 'Unknown Job'), htmlspecialchars($jobName));
                                    
                                    $exceptionEsc = htmlspecialchars($job['exception']);
                                    $exceptionAttr = htmlspecialchars($job['exception']);
                                    $job['error_html'] = sprintf(
                                        '<div class="flex items-start justify-between gap-2 group">
                                            <div class="text-xs text-red-600 dark:text-red-400 line-clamp-3 break-words" title="%s">%s</div>
                                            <button data-copy-text="%s" class="text-red-400 hover:text-red-600 dark:hover:text-red-300 transition-colors focus:outline-none shrink-0 mt-0.5" title="Copy Error Stack Trace">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="copy-icon h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                </svg>
                                                <svg xmlns="http://www.w3.org/2000/svg" class="check-icon h-3.5 w-3.5 text-green-500" style="display: none;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </button>
                                        </div>',
                                        $exceptionEsc,
                                        $exceptionEsc,
                                        $exceptionAttr
                                    );
                                    $job['action_html'] = sprintf('
                                        <form action="/system-health/failed-jobs/%s/delete" method="POST" class="confirm-action" data-message="Are you sure you want to resolve this failed job? This will remove it from the list.">
                                            <input type="hidden" name="csrf_token" value="' . ($_SESSION['csrf_token'] ?? '') . '">
                                            <button type="submit" class="text-xs bg-red-100 text-red-700 px-3 py-1.5 rounded hover:bg-red-200 dark:bg-red-900/30 dark:text-red-300 transition-colors">Resolve</button>
                                        </form>
                                    ', $job['id']);
                                }
                                unset($job);

                                $tableConfig = [
                                    'wrapper_classes' => 'overflow-x-auto',
                                    'columns' => [
                                        ['key' => 'payload_html', 'label' => 'Job', 'width' => 'w-[20%]', 'type' => 'raw'],
                                        ['key' => 'failed_at', 'label' => 'Failed At', 'width' => 'w-[20%]', 'wrap' => false],
                                        ['key' => 'error_html', 'label' => 'Error', 'width' => 'w-[50%]', 'type' => 'raw', 'wrap' => true],
                                        ['key' => 'action_html', 'label' => 'Action', 'width' => 'w-[10%]', 'type' => 'raw']
                                    ],
                                    'data' => $appHealthMetrics['failed_jobs'],
                                    'empty_message' => 'No failed jobs.'
                                ];
                                require BASE_PATH . '/src/Views/components/table.php';
                            ?>
                            <?php if ($appHealthMetrics['failed_jobs_paginator']->getTotalPages() > 1): ?>
                                <div class="mt-4">
                                    <?= $appHealthMetrics['failed_jobs_paginator']->links() ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Section: Database Performance -->
                    <div id="db-performance-chart-container" 
                         class="bg-gray-50 dark:bg-gray-700/50 pt-5 px-5 pb-5 rounded-lg shadow"
                         data-url="/api/system-health/db-performance">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-gray-200 dark:border-gray-700 w-full mb-6">
                            <h3 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-gray-100 whitespace-nowrap shrink-0">
                                Database Performance
                            </h3>

                            <div class="flex flex-row flex-wrap sm:flex-nowrap items-end gap-3">
                                <div class="w-full sm:w-auto">
                                    <label for="db-performance-period" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                                        Time Period
                                    </label>
                                    <select id="db-performance-period" class="filter-input block w-full border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-xs p-2 border bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-accent-1 focus:border-accent-1 max-w-xs">
                                        <option value="hourly">Hourly (Last 24 Hours)</option>
                                        <option value="daily">Daily (Last 30 Days)</option>
                                        <option value="weekly">Weekly (Last 12 Weeks)</option>
                                        <option value="monthly">Monthly (Last 12 Months)</option>
                                    </select>
                                </div>
                                <a href="/admin/system-health/export-db-metrics" class="flex-shrink-0 inline-flex items-center px-3 py-2 bg-accent-1 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-accent-1-hover focus:bg-indigo-500 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-accent-1 focus:ring-offset-2 transition ease-in-out duration-150 shadow-sm h-[34px]">
                                    Export CSV
                                </a>
                            </div>
                        </div>

                        <div class="relative h-96">
                            <canvas id="dbPerformanceChart"></canvas>
                        </div>
                    </div>

                    <!-- Section: Database Integrity -->
                    <div class="bg-gray-50 dark:bg-gray-700/50 pt-3 px-5 pb-5 rounded-lg shadow">
                        <h3 class="text-xl font-bold mb-4 border-b border-gray-200 dark:border-gray-600 pb-2">Database Integrity</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4">
                            <div class="md:col-span-1 bg-white dark:bg-gray-800 p-4 sm:p-6 rounded-lg shadow-inner">
                                <h3 class="text-lg font-bold mb-4 text-center">Verification Status</h3>
                                <div id="integrity-status-container" class="text-center">
                                    <div class="text-5xl font-bold <?= $integrityCheckResult['verified_percentage'] == 100 && ($integrityCheckResult['live_state_errors_count'] ?? 0) == 0 ? 'text-green-500' : 'text-red-500' ?>" id="verified-percentage">
                                        <?= htmlspecialchars($integrityCheckResult['verified_percentage']) ?>%
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400 mt-2">Chain Integrity</div>
                                    
                                    <?php if(($integrityCheckResult['live_state_errors_count'] ?? 0) > 0): ?>
                                        <div class="mt-4 p-2 bg-red-100 dark:bg-red-900/30 rounded-lg">
                                            <div class="text-xl font-bold text-red-600 dark:text-red-400"><?= $integrityCheckResult['live_state_errors_count'] ?></div>
                                            <div class="text-xs text-red-500 dark:text-red-300 uppercase font-bold">Live State Errors</div>
                                        </div>
                                    <?php else: ?>
                                        <div class="mt-4 p-2 bg-success-light dark:bg-green-900/30 rounded-lg">
                                            <div class="text-xs text-green-600 dark:text-green-400 uppercase font-bold">Live State OK</div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="text-xs text-gray-400 dark:text-gray-500 mt-3" id="last-checked-at">
                                        Last checked: <?= htmlspecialchars($integrityCheckResult['last_checked']) ?>
                                    </div>
                                </div>
                                <div class="mt-6 text-center">
                                    <button id="run-integrity-check" 
                                            data-url="/system-health/run-check"
                                            class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-accent-1 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-accent-1">
                                        <svg id="button-spinner" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span id="button-text">Run Verification</span>
                                    </button>
                                </div>
                            </div>
                            <div class="md:col-span-2 bg-white dark:bg-gray-800 p-4 sm:p-6 rounded-lg shadow-inner">
                                <h3 class="text-lg font-bold mb-4">How it Works</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-300">
                                    This tool provides a powerful way to verify the integrity of the document tracking system's data. It leverages a "hash chain" mechanism and an "Active State Comparison" to ensure that records are immutable and tamper-proof.
                                </p>
                                <br>
                                <ol class="list-decimal list-inside text-sm text-gray-600 dark:text-gray-300 space-y-2">
                                    <li><strong>Hash-Chaining:</strong> When a document log is created, a unique digital signature (a "hash") is generated from its data and the hash of the previous log. This creates a linked chain of records.</li>
                                    <li><strong>Active State Comparison:</strong> The system compares the current live database state of every document against the state recorded in its last cryptographic log. Any unauthorized modification to document details (title, submitter, route) is immediately detected.</li>
                                    <li><strong>Verification Process:</strong> Clicking "Run Verification" triggers a system-wide check of both the historical log chain and the current live states.</li>
                                    <li><strong>Status Indication:</strong> If all historical logs and live states match their cryptographic hashes, the system is 100% verified.</li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Integrity Issues -->
                    <?php if (!empty($paginatedIssues)): ?>
                        <div class="bg-red-50 dark:bg-red-900/20 pt-3 px-5 pb-5 rounded-lg shadow">
                            <div class="flex flex-wrap justify-between items-center gap-3 mb-4 border-b border-red-200 dark:border-red-700 pb-2">
                                <h3 class="text-xl font-bold text-red-600 dark:text-red-400">Integrity Issues Detected</h3>
                                <form action="/system-health/freeze-all" method="POST" class="sign-action" data-message="Enter your Security PIN to digitally sign and freeze ALL documents with integrity issues.">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                    <input type="hidden" name="pin" class="sign-pin-input">
                                    <button type="submit" class="bg-red-600 text-white px-3 py-1.5 rounded hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-600 transition-colors font-bold text-xs shadow">Freeze All</button>
                                </form>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                The following records indicate database tampering or cryptographic chain corruption.
                            </p>
                            <?php
                                foreach ($paginatedIssues as &$issue) {
                                    $issue['type_html'] = sprintf('<span class="font-bold %s">%s</span>', $issue['type'] === 'Live State Tampering' ? 'text-orange-600 dark:text-orange-400' : 'text-red-600 dark:text-red-400', htmlspecialchars($issue['type']));
                                    
                                    $issue['doc_html'] = sprintf('%s<br><span class="text-xs text-gray-500 dark:text-gray-400">%s</span>', htmlspecialchars($issue['tracking_code']), htmlspecialchars($issue['title']));
                                    
                                    $actions = sprintf('<a href="/documents/%s?back_to=system-overview" class="text-accent-1 hover:text-accent-1-active dark:text-accent-1-hover">View</a>', htmlspecialchars($issue['tracking_code']));
                                    
                                    if ($issue['type'] === 'Live State Tampering') {
                                        $actions .= sprintf('
                                            <form action="/documents/%s/autoresolve" method="POST" class="autoresolve-form sign-action inline-block ml-3" data-message="Enter your Security PIN to digitally sign the Auto-resolve action for this document.">
                                                <input type="hidden" name="csrf_token" value="' . ($_SESSION['csrf_token'] ?? '') . '">
                                                <input type="hidden" name="pin" class="sign-pin-input">
                                                <button type="submit" class="bg-green-100 text-green-700 px-3 py-1 rounded hover:bg-green-200 dark:bg-green-900/30 dark:text-green-300 transition-colors font-bold text-xs">Auto-resolve</button>
                                            </form>
                                        ', htmlspecialchars($issue['tracking_code']));
                                    }

                                    if ($issue['log_id']) {
                                        $actions .= sprintf('
                                            <button type="button" 
                                                    class="text-accent-1 hover:text-accent-1-active dark:text-accent-1-hover debug-log-btn ml-3" 
                                                    data-url="/system-health/debug-log/%s">
                                                Debug
                                            </button>
                                        ', $issue['log_id']);
                                    }
                                    $issue['actions_html'] = '<div class="flex items-center justify-end">' . $actions . '</div>';
                                }
                                unset($issue);

                                $tableConfig = [
                                    'wrapper_classes' => 'overflow-x-auto',
                                    'columns' => [
                                        ['key' => 'type_html', 'label' => 'Type', 'width' => 'w-[20%]', 'type' => 'raw'],
                                        ['key' => 'doc_html', 'label' => 'Document / Code', 'width' => 'w-[25%]', 'type' => 'raw'],
                                        ['key' => 'description', 'label' => 'Details', 'width' => 'w-[35%]', 'wrap' => true],
                                        ['key' => 'actions_html', 'label' => 'Actions', 'width' => 'w-[20%]', 'type' => 'raw']
                                    ],
                                    'data' => $paginatedIssues,
                                    'empty_message' => 'No integrity issues found.'
                                ];
                                require BASE_PATH . '/src/Views/components/table.php';
                            ?>
                            <!-- Pagination -->
                            <?php if ($paginator->getTotalPages() > 1): ?>
                                <div class="mt-4 bg-white dark:bg-gray-800 px-4 py-3 border-t border-gray-200 dark:border-gray-700 sm:px-6">
                                    <?= clone $paginator ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Section: Admin Utilities -->
                    <div class="bg-gray-50 dark:bg-gray-700/50 pt-3 px-5 pb-5 rounded-lg shadow">
                        <h3 class="text-xl font-bold mb-4 border-b border-gray-200 dark:border-gray-600 pb-2">Admin Utilities</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                            <!-- Backup Manager Link -->
                            <a href="/system/backups" class="block p-6 bg-white dark:bg-gray-800 rounded-lg shadow-inner hover:bg-gray-100 dark:hover:bg-gray-700 transition ease-in-out duration-150">
                                <h4 class="text-lg font-bold mb-2 text-accent-1 dark:text-accent-1-hover">Backup Manager &rarr;</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-300">
                                    Create, download, and manage database backups.
                                </p>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Modals using our new reusable component!

// 1. Progress Modal
$modalId = 'integrity-progress-modal';
$modalTitle = 'Integrity Verification in Progress';
$modalSize = 5; // max-w-xl
$hideCloseButton = true;
$modalContent = '
    <div class="text-center py-4">
        <svg class="mx-auto h-12 w-12 text-accent-1 animate-pulse mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <h4 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-2">Analyzing cryptographic integrity...</h4>
        <div class="w-full bg-gray-200 rounded-full h-4 mb-4 dark:bg-gray-700">
            <div id="integrity-progress-bar" class="bg-accent-1 h-4 rounded-full transition-all duration-500 ease-out" style="width: 0%"></div>
        </div>
        <p id="integrity-progress-text" class="text-sm text-gray-500 dark:text-gray-400">Initializing check...</p>
        <p id="integrity-progress-time" class="text-xs text-gray-400 mt-2 font-mono"></p>
    </div>
';
$modalFooter = '<button id="close-integrity-modal" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-500 text-base font-medium text-white hover:bg-red-600 sm:text-sm">Cancel Verification</button>';
require BASE_PATH . '/src/Views/components/modal.php';

// 2. Debug Modal
$modalId = 'debug-hash-modal';
$modalTitle = 'Hash Integrity Debugger';
$modalSize = 8; // max-w-4xl
$hideCloseButton = false;
$modalContent = '
    <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                <h4 class="text-sm font-bold text-red-700 dark:text-red-400 uppercase mb-2">Stored in Database</h4>
                <p id="stored-hash-val" class="font-mono text-xs break-all dark:text-red-300"></p>
            </div>
            <div class="p-4 bg-indigo-50 dark:bg-indigo-900/20 border border-accent-1-light dark:border-indigo-800 rounded-lg">
                <h4 class="text-sm font-bold text-accent-1-active dark:text-accent-1-hover uppercase mb-2">Recalculated Now</h4>
                <p id="recalculated-hash-val" class="font-mono text-xs break-all dark:text-accent-1-light"></p>
            </div>
        </div>
        <div>
            <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase mb-2">Hash Formula Components</h4>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Field</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Value used for Hashing</th>
                        </tr>
                    </thead>
                    <tbody id="debug-components-body" class="divide-y divide-gray-200 dark:divide-gray-700 font-mono text-xs">
                    </tbody>
                </table>
            </div>
        </div>
        <div>
            <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase mb-2">Final Concatenated String (SHA-256 Input)</h4>
            <div class="p-4 bg-gray-100 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700">
                <p id="raw-data-string-val" class="font-mono text-xs break-all dark:text-gray-400"></p>
            </div>
        </div>
    </div>
';
$modalFooter = '';
require BASE_PATH . '/src/Views/components/modal.php';


?>

<script src="/js/system-health.js"></script>
<script src="/js/sign-action.js"></script>
<?php require BASE_PATH . '/src/Views/partials/signing-modal.php'; ?>

<?php $content = ob_get_clean(); ?>
<?php require BASE_PATH . '/src/Views/layouts/app.php'; ?>
