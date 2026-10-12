<?php $title = ($_SESSION['department_name'] ?? 'Department') . ' Tasks'; ob_start(); ?>
<h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
    <?= htmlspecialchars($_SESSION['department_name'] ?? 'Department') ?> Tasks
</h2>
<?php $header = ob_get_clean(); ?>

<?php ob_start(); ?>
<div class="py-6 sm:py-10">
    <div class="px-4 sm:px-6 lg:px-8 xl:mx-[20vh] space-y-6">
        
        <?php
            $finderTitle = 'Receive In-Transit Document';
            $finderDescription = "Scan or enter the tracking code of a document that has been physically delivered to your department to add it to your queue.";
            $finderActionUrl = '/documents/scan';
            $finderFormId = 'scan-form';
            $finderButtonText = 'Receive';
            require BASE_PATH . '/src/Views/components/qr-scanner.php';
        ?>

        <!-- Documents Awaiting Action Section -->
        <?php 
            $panelTitle = htmlspecialchars($_SESSION['department_name'] ?? 'Your') . ' Tasks';
            
            $purposeOptions = [];
            foreach ($allPurposes as $p) {
                $purposeOptions[$p['name']] = $p['name'];
            }

            $filterConfig = [
                'date' => ['type' => 'date', 'label' => 'Date'],
                'status' => [
                    'type' => 'select', 
                    'label' => 'Status', 
                    'options' => [
                        'all' => 'All Statuses',
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'declined' => 'Declined',
                        'frozen' => 'Frozen'
                    ]
                ],
                'purpose' => [
                    'type' => 'select',
                    'label' => 'Purpose',
                    'options' => array_merge(['all' => 'All Purposes'], $purposeOptions)
                ],
                'submitter' => ['type' => 'text', 'label' => 'Submitter Name', 'placeholder' => 'Search submitter...'],
                'search' => ['type' => 'text', 'label' => 'Search Tracking # / Title', 'placeholder' => 'Search...']
            ];

            // Pre-process action column for the table component
            foreach ($documents as &$doc) {
                $doc['action_html'] = sprintf('
                    <form class="complete-form inline-block" action="/tasks/%s/complete" method="POST">
                        <input type="hidden" name="csrf_token" value="' . ($_SESSION['csrf_token'] ?? '') . '">
                        <input type="hidden" name="pin" class="complete-pin-input">
                        <button type="button" class="complete-btn px-4 py-2 bg-success text-white rounded hover:bg-success-hover transition font-semibold" data-tracking-code="%s">
                            Complete Task
                        </button>
                    </form>
                ', htmlspecialchars($doc['id']), htmlspecialchars($doc['tracking_code']));
            }
            unset($doc);

            $tableConfig = [
                'wrapper_classes' => 'overflow-x-auto mt-4',
                'columns' => [
                    ['key' => 'tracking_code', 'label' => 'Tracking Code', 'width' => 'w-[20%]', 'type' => 'tracking_link'],
                    ['key' => 'title', 'label' => 'Title', 'width' => 'w-[29%]', 'wrap' => true],
                    ['key' => 'purpose_name', 'label' => 'Purpose', 'width' => 'w-[25%]', 'wrap' => true],
                    ['key' => 'status', 'label' => 'Status', 'width' => 'w-[10%]', 'type' => 'status'],
                    ['key' => 'created_at', 'label' => 'Received At', 'width' => 'w-[6%]', 'type' => 'date'],
                    ['key' => 'action_html', 'label' => 'Action', 'width' => 'w-[10%]', 'type' => 'raw']
                ],
                'data' => $documents,
                'empty_message' => 'No documents are currently assigned to your department.'
            ];

            require BASE_PATH . '/src/Views/components/data-panel.php';
        ?>
    </div>
</div>

<?php require BASE_PATH . '/src/Views/partials/scan-qr-modal.php'; ?>
<?php require BASE_PATH . '/src/Views/partials/signing-modal.php'; ?>

<script>
// Named so both DOMContentLoaded (hard load) and dts:page-loaded (PJAX swap)
// can trigger re-binding of complete buttons and scan form.
function initStaffTasksPage() {
    const completeBtns = document.querySelectorAll('.complete-btn');
    completeBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const form = this.closest('.complete-form');
            const trackingCode = this.dataset.trackingCode;
            window.SigningModal.show(`Enter your Security PIN to cryptographically sign the completion of document: ${trackingCode}`, function(pin) {
                form.querySelector('.complete-pin-input').value = pin;
                form.submit();
            });
        });
    });

    const scanForm = document.getElementById('scan-form');
    if (scanForm) {
        scanForm.addEventListener('submit', function(e) {
            if (!scanForm.querySelector('.scan-pin-input')) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'pin';
                input.className = 'scan-pin-input';
                scanForm.appendChild(input);
            }
            
            const pinInput = scanForm.querySelector('.scan-pin-input');
            if (!pinInput.value) {
                e.preventDefault();
                const trackingCode = scanForm.elements['tracking_code'].value.trim();
                if (trackingCode) {
                    window.SigningModal.show(`Enter your Security PIN to cryptographically sign the receipt of document: ${trackingCode}`, function(pin) {
                        pinInput.value = pin;
                        scanForm.submit();
                    });
                }
            }
        });
    }
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initStaffTasksPage);
} else {
    initStaffTasksPage();
}
</script>

<?php $content = ob_get_clean(); ?>

<?php require BASE_PATH . '/src/Views/layouts/app.php'; ?>
