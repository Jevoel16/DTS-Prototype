<?php $title = 'Integrity Monitor'; ob_start(); ?>
<h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
    Integrity Monitor
</h2>
<?php $header = ob_get_clean(); ?>

<?php ob_start(); ?>
<div class="py-6 sm:py-10">
    <div class="px-4 sm:px-6 lg:px-8 xl:mx-[20vh] space-y-6">
        <?php 
            $panelTitle = 'All Documents';
            
            $filterConfig = [
                'date' => ['type' => 'date', 'label' => 'Date Created'],
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
                    'options' => array_merge(['all' => 'All Purposes'], array_column($purposes, 'name', 'name'))
                ],
                'submitter' => ['type' => 'text', 'label' => 'Submitter Name', 'placeholder' => 'Search submitter...'],
                'search' => ['type' => 'text', 'label' => 'Search Tracking # / Title', 'placeholder' => 'Search...']
            ];
            
            $tableConfig = [
                'wrapper_classes' => 'overflow-x-auto mt-4',
                'columns' => [
                    [
                        'key' => 'tracking_code',
                        'label' => 'Tracking Code',
                        'width' => 'w-[15%]',
                        'wrap' => false,
                        'type' => 'tracking_link'
                    ],
                    [
                        'key' => 'title',
                        'label' => 'Title',
                        'width' => 'w-[25%]',
                        'wrap' => true,
                    ],
                    [
                        'key' => 'guest_name',
                        'label' => 'Submitter',
                        'width' => 'w-[15%]',
                        'wrap' => true,
                    ],
                    [
                        'key' => 'purpose_name',
                        'label' => 'Purpose',
                        'width' => 'w-[20%]',
                        'wrap' => true,
                    ],
                    [
                        'key' => 'status',
                        'label' => 'Status',
                        'width' => 'w-[10%]',
                        'wrap' => false,
                        'type' => 'status'
                    ],
                    [
                        'key' => 'created_at',
                        'label' => 'Date',
                        'width' => 'w-[15%]',
                        'wrap' => false,
                        'type' => 'date'
                    ]
                ],
                'data' => $documents,
                'empty_message' => 'No documents found.'
            ];
            
            require BASE_PATH . '/src/Views/components/data-panel.php'; 
        ?>
    </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php require BASE_PATH . '/src/Views/layouts/app.php'; ?>
