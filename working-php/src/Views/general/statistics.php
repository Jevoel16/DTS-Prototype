<?php $title = ($_SESSION['department_name'] ?? 'Department') . ' Statistics'; ob_start(); ?>
<h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
    <?= htmlspecialchars($_SESSION['department_name'] ?? 'Department') ?> Statistics
</h2>
<?php $header = ob_get_clean(); ?>
<?php ob_start(); ?>
<div class="py-10">
    <div class="mx-[20vh] sm:px-6 lg:px-8 space-y-6">
        
        <?php require BASE_PATH . '/src/Views/components/department-analytics.php'; ?>

        <?php if (($_SESSION['role'] ?? '') === 'officer'): ?>
        <!-- Hidden form for report generation API -->
        <form id="report-generation-form" action="/statistics/report" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <input type="hidden" name="date" id="form_date">
            <input type="hidden" name="purpose" id="form_purpose">
            <input type="hidden" name="submitter" id="form_submitter">
            <input type="hidden" name="search" id="form_search">
        </form>

        <div id="released-documents-section" data-fetch-url="/statistics">
            <?php
                $panelTitle = 'Released Documents History';
                $panelActionHtml = '
                    <button type="button" class="open-modal-btn inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-none transition mr-2" data-modal="past-reports-modal">Past Reports</button>
                    <button type="button" id="generate-report-btn" class="inline-flex items-center px-4 py-2 bg-accent-1 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-accent-1-hover focus:outline-none transition">Generate Report</button>
                ';
                
                $purposeOptions = [];
                foreach ($purposes as $p) {
                    $purposeOptions[$p['name']] = $p['name'];
                }

                $filterConfig = [
                    'date' => ['type' => 'date', 'label' => 'Date'],
                    'purpose' => [
                        'type' => 'select',
                        'label' => 'Purpose',
                        'options' => array_merge(['all' => 'All Purposes'], $purposeOptions)
                    ],
                    'submitter' => ['type' => 'text', 'label' => 'Submitter Name', 'placeholder' => 'Search submitter...'],
                    'search' => ['type' => 'text', 'label' => 'Search Tracking # / Title', 'placeholder' => 'Search...']
                ];

                foreach ($releasedDocuments as &$document) {
                    $document['submitter_html'] = sprintf('<span class="submitter-name text-gray-500 dark:text-gray-400">%s</span>', htmlspecialchars($document['guest_info']['name'] ?? 'N/A'));
                    $document['district_text'] = htmlspecialchars($document['district'] ?? 'N/A');
                }
                unset($document);

                $tableConfig = [
                    'wrapper_classes' => 'overflow-x-auto mt-4',
                    'columns' => [
                        ['key' => 'tracking_code', 'label' => 'Tracking Code', 'width' => 'w-[20%]', 'type' => 'tracking_link'],
                        ['key' => 'title', 'label' => 'Title', 'width' => 'w-[44%]', 'wrap' => true],
                        ['key' => 'submitter_html', 'label' => 'Submitter', 'width' => 'w-[30%]', 'type' => 'raw'],
                        ['key' => 'released_at', 'label' => 'Date Released', 'width' => 'w-[6%]', 'type' => 'date']
                    ],
                    'data' => $releasedDocuments,
                    'empty_message' => 'No released documents match your search.'
                ];

                require BASE_PATH . '/src/Views/components/data-panel.php';
            ?>
        </div>
        
        <?php
            $formattedReports = [];
            if (!empty($pastReports)) {
                $formattedReports = array_map(function($report) {
                    $report['action'] = '<a href="/statistics/report/download/' . htmlspecialchars($report['id']) . '" class="text-accent-1 hover:underline font-semibold">Download Spreadsheet</a>';
                    return $report;
                }, $pastReports);
            }

            $tableConfig = [
                'wrapper_classes' => 'overflow-x-auto mt-2 max-h-96',
                'columns' => [
                    ['key' => 'created_at', 'label' => 'Date Generated', 'width' => 'w-[40%]', 'type' => 'date'],
                    ['key' => 'total_documents', 'label' => 'Total', 'width' => 'w-[20%]'],
                    ['key' => 'action', 'label' => 'Action', 'width' => 'w-[40%]', 'type' => 'raw']
                ],
                'data' => $formattedReports,
                'empty_message' => 'No past reports available.'
            ];

            ob_start();
            require BASE_PATH . '/src/Views/components/table.php';
            $modalContent = ob_get_clean();

            $modalId = 'past-reports-modal';
            $modalTitle = 'Past Generated Reports';
            $modalSize = 6;
            require BASE_PATH . '/src/Views/components/modal.php';
        ?>
        
        <?php require BASE_PATH . '/src/Views/components/report-progress-modal.php'; ?>
        <?php endif; ?>

    </div>
</div>

<script src="/js/statistics.js"></script>

<?php $content = ob_get_clean(); ?>

<?php require BASE_PATH . '/src/Views/layouts/app.php'; ?>
