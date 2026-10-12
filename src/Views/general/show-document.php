<?php $title = 'Document — ' . ($document['tracking_code'] ?? 'Details'); ob_start(); ?>
<div class="flex flex-wrap justify-between items-center gap-3 w-full">
    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Document Details: <?php echo htmlspecialchars($document['tracking_code']); ?>
    </h2>
    
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <div>
            <?php if (trim($document['status']) === 'frozen'): ?>
                <form action="/documents/<?php echo htmlspecialchars($document['tracking_code']); ?>/unfreeze" method="POST" class="inline-block m-0 sign-action" data-message="Enter your Security PIN to digitally sign and unfreeze this document.">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    <input type="hidden" name="pin" class="sign-pin-input">
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-green-700 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-green-500 transition ease-in-out duration-150 shadow">
                        Unfreeze Document
                    </button>
                </form>
            <?php else: ?>
                <form action="/documents/<?php echo htmlspecialchars($document['tracking_code']); ?>/freeze" method="POST" class="inline-block m-0 sign-action" data-message="Enter your Security PIN to digitally sign and freeze this document. This will halt all operations on it.">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    <input type="hidden" name="pin" class="sign-pin-input">
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-red-700 active:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-500 transition ease-in-out duration-150 shadow">
                        Freeze Document
                    </button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php $header = ob_get_clean(); ?>

<?php ob_start(); ?>
<div class="py-6 sm:py-10">
    <div class="px-4 sm:px-6 lg:px-8 xl:mx-[20vh] space-y-6">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-4 sm:p-6 text-gray-900 dark:text-gray-100">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Document Information -->
                    <div>
                        <h3 class="text-lg font-bold mb-4 border-b border-gray-200 dark:border-gray-600 pb-2">Document Information</h3>
                        <div class="space-y-4">
                            <p class="flex items-center">
                                <strong>Tracking Code:</strong> 
                                <span class="ml-1"><?php echo htmlspecialchars($document['tracking_code']); ?></span>
                                <button onclick="const btn=this; btn.querySelector('.copy-icon').style.display='none'; btn.querySelector('.check-icon').style.display='block'; navigator.clipboard.writeText('<?php echo htmlspecialchars(addslashes($document['tracking_code'])); ?>'); setTimeout(() => { btn.querySelector('.copy-icon').style.display='block'; btn.querySelector('.check-icon').style.display='none'; }, 2000);" class="text-gray-400 hover:text-accent-1 transition-colors focus:outline-none ml-2" title="Copy Tracking Code">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="copy-icon h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="check-icon h-4 w-4 text-green-500" style="display: none;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </button>
                            </p>
                            <p><strong>Title:</strong> <?php echo htmlspecialchars($document['title'] ?? 'N/A'); ?></p>
                            <?php 
                            $guestInfo = json_decode($document['guest_info'], true);
                            ?>
                            <p><strong>Submitter Name:</strong> <?php echo htmlspecialchars($guestInfo['name'] ?? 'N/A'); ?></p>
                            <p><strong>Submitter Email:</strong> <?php echo htmlspecialchars($guestInfo['email'] ?? 'N/A'); ?></p>
                            <p><strong>Purpose:</strong> <?php echo htmlspecialchars($document['purpose_name'] ?? 'Unknown'); ?></p>
                            <p><strong>Status:</strong> 
                                <?php $status = $document['status']; require BASE_PATH . '/src/Views/components/status-badge.php'; ?>
                            </p>
                            <p><strong>Submitted At:</strong> <?php echo date('M d, Y h:i A', strtotime($document['created_at'])); ?></p>
                        </div>
                    </div>
                    <!-- Routing Information -->
                    <div>
                        <h3 class="text-lg font-bold mb-4 border-b border-gray-200 dark:border-gray-600 pb-2">Routing</h3>
                        
                        <p class="mb-2"><strong>Status:</strong> 
                                <?php $status = $document['status']; require BASE_PATH . '/src/Views/components/status-badge.php'; ?>
                        </p>

                        <?php if ($document['status'] == 'declined'): ?>
                            <p class="mt-2 text-red-600"><strong>Reason:</strong> Document declined.</p>
                        <?php elseif ($document['status'] == 'pending'): ?>
                            <p class="mt-2 text-gray-500 dark:text-gray-400">The route will be finalized upon intake.</p>
                        <?php else: ?>
                            <h4 class="font-semibold text-md text-gray-700 dark:text-gray-300 mt-4">Document Path:</h4>
                            <div class="mt-2">
                                <?php 
                                $route = $document['finalized_route'] ? json_decode($document['finalized_route'], true) : []; 
                                $currentStep = $document['current_step'] ?? 1;
                                ?>
                                <ul class="space-y-3">
                                    <!-- Step 1: Intake -->
                                    <li class="flex items-center justify-between text-gray-700 dark:text-gray-300">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-gray-500 dark:text-gray-400 w-5">1.</span>
                                            <span>Intake</span>
                                        </div>
                                        <div>
                                            <?php $status = 'completed'; require BASE_PATH . '/src/Views/components/status-badge.php'; ?>
                                        </div>
                                    </li>

                                    <!-- Step 2: Records Unit (Intake) -->
                                    <li class="flex items-center justify-between text-gray-700 dark:text-gray-300">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-gray-500 dark:text-gray-400 w-5">2.</span>
                                            <span>Records Unit (Intake)</span>
                                        </div>
                                        <div>
                                            <?php $status = 'completed'; require BASE_PATH . '/src/Views/components/status-badge.php'; ?>
                                        </div>
                                    </li>

                                    <?php foreach ($route as $index => $step): ?>
                                        <li class="flex items-center justify-between <?php echo ($index + 1) === $currentStep ? 'font-bold text-gray-900 dark:text-gray-100' : 'text-gray-700 dark:text-gray-300'; ?>">
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono text-gray-500 dark:text-gray-400 font-normal w-5"><?= $index + 3 ?>.</span>
                                                <span>
                                                    <?php 
                                                    $displayName = $step['name'];
                                                    if ($displayName === 'Records Unit') {
                                                        $displayName .= ' (Processing)';
                                                    }
                                                    echo htmlspecialchars($displayName); 
                                                    ?>
                                                </span>
                                            </div>
                                            <div>
                                                <?php if (($index + 1) < $currentStep): ?>
                                                    <?php $status = 'completed'; require BASE_PATH . '/src/Views/components/status-badge.php'; ?>
                                                <?php elseif (($index + 1) === $currentStep): ?>
                                                    <?php $status = $document['status']; require BASE_PATH . '/src/Views/components/status-badge.php'; ?>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>

                                    <li class="flex items-center justify-between <?php echo $currentStep > count($route) ? 'font-bold text-gray-900 dark:text-gray-100' : 'text-gray-700 dark:text-gray-300'; ?>">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-gray-500 dark:text-gray-400 font-normal w-5"><?= count($route) + 3 ?>.</span>
                                            <span>Records Unit (Releasing)</span>
                                        </div>
                                        <div>
                                            <?php if ($document['status'] === 'completed'): ?>
                                                <?php $status = 'completed'; require BASE_PATH . '/src/Views/components/status-badge.php'; ?>
                                            <?php elseif ($currentStep > count($route)): ?>
                                                <?php $status = $document['status']; require BASE_PATH . '/src/Views/components/status-badge.php'; ?>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Document Logs -->
                <div class="mt-8">
                    <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
                        <h3 class="text-xl sm:text-2xl font-bold">Document History</h3>
                        <div class="flex items-center space-x-2">
                            <a href="/documents/<?php echo htmlspecialchars($document['tracking_code']); ?>/hash-chain" class="inline-flex items-center px-4 py-2 bg-accent-1 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-accent-1-hover active:bg-accent-1-active focus:outline-none focus:ring-2 focus:ring-accent-1 transition ease-in-out duration-150">
                                View Hash Chain
                            </a>
                            <?php 
                                $fallbackReturn = $_SESSION['doc_return_url'] ?? (($_SESSION['role'] ?? '') === 'admin' ? '/admin-dashboard' : '/dashboard');
                            ?>
                            <a id="doc-back-button" href="<?= htmlspecialchars($fallbackReturn) ?>" class="inline-flex items-center px-4 py-2 bg-accent-2 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-accent-2-hover active:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-accent-2 transition ease-in-out duration-150">
                                Back
                            </a>
                        </div>
                    </div>
                    <?php
                        foreach ($logs as &$log) {
                            $log['user_name_text'] = htmlspecialchars($log['user_name'] ?? 'System');
                        }
                        unset($log);

                        $tableConfig = [
                            'wrapper_classes' => 'overflow-x-auto',
                            'columns' => [
                                ['key' => 'created_at', 'label' => 'Timestamp', 'width' => 'w-[6%]', 'type' => 'date'],
                                ['key' => 'action', 'label' => 'Action', 'width' => 'w-[39%]', 'wrap' => true],
                                ['key' => 'user_name_text', 'label' => 'Performed By', 'width' => 'w-[20%]'],
                                ['key' => 'remarks', 'label' => 'Remarks', 'width' => 'w-[35%]', 'wrap' => true]
                            ],
                            'data' => $logs,
                            'empty_message' => 'No history found.'
                        ];
                        require BASE_PATH . '/src/Views/components/table.php';
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
// Named so both DOMContentLoaded (hard load) and dts:page-loaded (PJAX swap)
// can trigger re-binding of sign-action form handlers and back button origin.
function initShowDocumentPage() {
    const backBtn = document.getElementById('doc-back-button');
    if (backBtn) {
        const origin = sessionStorage.getItem('dts_doc_origin');
        if (origin && origin !== window.location.pathname && !origin.includes('/documents/')) {
            backBtn.setAttribute('href', origin);
        }
    }

    document.querySelectorAll('form.sign-action').forEach(form => {
        form.addEventListener('submit', function(e) {
            const pinInput = this.querySelector('.sign-pin-input');
            if (pinInput && !pinInput.value) {
                e.preventDefault();
                const msg = this.dataset.message || "Enter your Security PIN to sign this action.";
                window.SigningModal.show(msg, function(pin) {
                    pinInput.value = pin;
                    form.submit();
                });
            }
        });
    });
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initShowDocumentPage);
} else {
    initShowDocumentPage();
}
</script>
<?php require BASE_PATH . '/src/Views/partials/signing-modal.php'; ?>
<?php $content = ob_get_clean(); ?>

<?php require BASE_PATH . '/src/Views/layouts/app.php'; ?>
