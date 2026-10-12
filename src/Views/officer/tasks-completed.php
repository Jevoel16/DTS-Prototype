<?php $title = ($_SESSION['department_name'] ?? 'Department') . ' Completed Tasks'; ob_start(); ?>
<h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
    <?= htmlspecialchars($_SESSION['department_name'] ?? 'Department') ?> Completed Tasks
</h2>
<?php $header = ob_get_clean(); ?>

<?php ob_start(); ?>
<div class="py-6 sm:py-10">
    <div class="px-4 sm:px-6 lg:px-8 xl:mx-[20vh] space-y-6">
        
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-4 sm:p-6 text-gray-900 dark:text-gray-100">
                <div class="flex flex-wrap justify-between items-center gap-3 mb-6">
                    <h3 class="text-lg font-bold">Recently Completed Tasks</h3>
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

                <?php
                    $tableConfig = [
                        'wrapper_classes' => 'overflow-x-auto mt-4',
                        'columns' => [
                            ['key' => 'tracking_code', 'label' => 'Tracking Code', 'width' => 'w-[15%]', 'type' => 'tracking_link'],
                            ['key' => 'guest_name', 'label' => 'Submitter', 'width' => 'w-[20%]', 'wrap' => true],
                            ['key' => 'title', 'label' => 'Title', 'width' => 'w-[29%]', 'wrap' => true],
                            ['key' => 'purpose_name', 'label' => 'Purpose', 'width' => 'w-[30%]', 'wrap' => true],
                            ['key' => 'handled_at', 'label' => 'Date Completed', 'width' => 'w-[6%]', 'type' => 'date']
                        ],
                        'data' => $documents,
                        'empty_message' => 'No completed tasks match your criteria.'
                    ];
                    require BASE_PATH . '/src/Views/components/table.php';
                ?>

                <div class="mt-4">
                    <?php 
                    if (isset($paginator)) {
                        if ($paginator instanceof \App\Utils\CursorPaginator) {
                            require BASE_PATH . '/src/Views/components/cursor-pagination.php';
                        } else {
                            require BASE_PATH . '/src/Views/components/pagination.php';
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); ?>

<?php require BASE_PATH . '/src/Views/layouts/app.php'; ?>
