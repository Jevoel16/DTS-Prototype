<?php $title = 'All Documents'; ob_start(); ?>
<h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
    All Documents
</h2>
<?php $header = ob_get_clean(); ?>

<?php ob_start(); ?>
<div class="py-10">
    <div class="mx-[20vh] sm:px-6 lg:px-8 space-y-6">
        
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg" id="documents-section">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-2xl font-bold">All Documents in System</h3>
                </div>
                
                <?php 
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
                    require BASE_PATH . '/src/Views/components/table-filters.php'; 
                ?>

                <div id="documents-table-body">
                <?php
                    $tableConfig = [
                        'wrapper_classes' => 'overflow-x-auto mt-4',
                        'columns' => [
                            ['key' => 'tracking_code', 'label' => 'Tracking Code', 'width' => 'w-[15%]', 'type' => 'tracking_link'],
                            ['key' => 'title', 'label' => 'Title', 'width' => 'w-[29%]', 'wrap' => true],
                            ['key' => 'guest_name', 'label' => 'Submitter', 'width' => 'w-[15%]', 'wrap' => true],
                            ['key' => 'purpose_name', 'label' => 'Purpose', 'width' => 'w-[25%]', 'wrap' => true],
                            ['key' => 'status', 'label' => 'Status', 'width' => 'w-[10%]', 'type' => 'status'],
                            ['key' => 'created_at', 'label' => 'Date Created', 'width' => 'w-[6%]', 'type' => 'date']
                        ],
                        'data' => $documents,
                        'empty_message' => 'No documents match your criteria.'
                    ];
                    require BASE_PATH . '/src/Views/components/table.php';
                ?>

                    <div class="mt-4">
                        <?= isset($paginator) ? $paginator->links() : '' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); ?>

<?php require BASE_PATH . '/src/Views/layouts/app.php'; ?>
