<?php $title = 'Document Intake'; ob_start(); ?>
<h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
    Document Intake
</h2>
<?php $header = ob_get_clean(); ?>

<?php ob_start(); ?>
<div class="py-6 sm:py-10">
    <div class="px-4 sm:px-6 lg:px-8 xl:mx-[20vh] space-y-6">
        
        <?php
            $finderTitle = 'Add Document by Tracking Code';
            $finderDescription = "Enter the tracking code from the client's QR code or receipt to begin processing.";
            $finderActionUrl = '/intake/find';
            require BASE_PATH . '/src/Views/components/qr-scanner.php';
        ?>

        <!-- Pending Documents Section -->
        <?php 
            $panelTitle = 'Recently Handled Documents';
            
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
                        'in_transit' => 'In Transit',
                        'processing' => 'Processing',
                        'ready_for_release' => 'Ready For Release',
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

            $tableConfig = [
                'wrapper_classes' => 'overflow-x-auto mt-4',
                'columns' => [
                    ['key' => 'tracking_code', 'label' => 'Tracking Code', 'width' => 'w-[20%]', 'type' => 'tracking_link'],
                    ['key' => 'guest_name', 'label' => 'Submitter', 'width' => 'w-[20%]', 'wrap' => true],
                    ['key' => 'purpose_name', 'label' => 'Purpose', 'width' => 'w-[44%]', 'wrap' => true],
                    ['key' => 'status', 'label' => 'Status', 'width' => 'w-[10%]', 'type' => 'status'],
                    ['key' => 'handled_at', 'label' => 'Date Handled', 'width' => 'w-[6%]', 'type' => 'date']
                ],
                'data' => $documents,
                'empty_message' => 'No recently added documents match your criteria.'
            ];

            require BASE_PATH . '/src/Views/components/data-panel.php';
        ?>
    </div>
</div>

<?php require BASE_PATH . '/src/Views/partials/scan-qr-modal.php'; ?>

<?php $content = ob_get_clean(); ?>

<?php require BASE_PATH . '/src/Views/layouts/app.php'; ?>
