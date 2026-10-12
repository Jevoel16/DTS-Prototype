<?php
    foreach ($releasedDocuments as &$document) {
        $document['submitter_html'] = sprintf('<span class="submitter-name text-gray-500 dark:text-gray-400">%s</span>', htmlspecialchars($document['guest_info']['name'] ?? 'N/A'));
        $document['district_text'] = htmlspecialchars($document['district'] ?? 'N/A');
    }
    unset($document);

    $tableConfig = [
        'wrapper_classes' => 'overflow-x-auto mt-4',
        'columns' => [
            ['key' => 'tracking_code', 'label' => 'Tracking Code', 'width' => 'w-[20%]', 'type' => 'tracking_link'],
            ['key' => 'guest_name', 'label' => 'Submitter', 'width' => 'w-[25%]', 'wrap' => true],
            ['key' => 'title', 'label' => 'Title', 'width' => 'w-[49%]', 'wrap' => true],
            ['key' => 'released_at', 'label' => 'Date Released', 'width' => 'w-[6%]', 'type' => 'date']
        ],
        'data' => $releasedDocuments,
        'empty_message' => 'No released documents match your search.'
    ];
    require BASE_PATH . '/src/Views/components/table.php';
?>

<div class="mt-4">
    <?php 
        if ($paginator instanceof \App\Utils\CursorPaginator) {
            require BASE_PATH . '/src/Views/components/cursor-pagination.php';
        } else {
            require BASE_PATH . '/src/Views/components/pagination.php';
        }
    ?>
</div>
