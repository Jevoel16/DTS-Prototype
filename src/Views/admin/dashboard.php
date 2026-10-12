<?php $title = 'Admin Dashboard'; ?>

<?php ob_start(); ?>
<h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
    Admin Dashboard
</h2>
<?php $header = ob_get_clean(); ?>

<?php ob_start(); ?>
<div class="py-10">
    <div class="mx-[20vh] sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-2xl font-bold">Global Analytics</h3>
                        <form action="/clear-personal-cache" method="POST" class="confirm-action m-0" data-message="Are you sure you want to clear your dashboard cache? This will refresh your view but preserve others' caches.">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                            <button type="submit" class="inline-flex items-center px-3 py-1 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-accent-2 focus:outline-none transition">
                                Clear Cache
                            </button>
                        </form>
                    </div>
                    <div class="space-y-6"
                         data-current-load-url="/api/admin-dashboard/current-load"
                         data-throughput-url="/api/admin-dashboard/throughput"
                         data-decline-trends-url="/api/admin-dashboard/decline-trends"
                         data-peak-intake-hours-url="/api/admin-dashboard/peak-intake-hours"
                         data-status-distribution-url="/api/admin-dashboard/status-distribution"
                         data-processing-hotspots-url="/api/admin-dashboard/processing-hotspots"
                         data-avg-step-time-url="/api/admin-dashboard/avg-step-time"
                         data-load-vs-time-url="/api/admin-dashboard/department-load-vs-time"
                         data-submission-districts-url="/api/admin-dashboard/submission-districts">

                        <!-- Section: Processing Analytics -->
                        <div class="bg-gray-50 dark:bg-gray-700/50 p-5 rounded-lg shadow">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                                <!-- Document Status Distribution Chart -->
                                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow-inner flex flex-col justify-between">
                                    <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-600 pb-3 mb-4 h-[68px]">
                                        <h4 class="text-lg font-bold">Document Status Distribution</h4>
                                    </div>
                                    <div class="relative h-64 flex-grow">
                                        <canvas id="statusDistributionChart"></canvas>
                                    </div>
                                </div>

                                <!-- Global Throughput Chart -->
                                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow-inner flex flex-col justify-between">
                                    <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-600 pb-3 mb-4 h-[68px]">
                                        <h4 class="text-lg font-bold leading-tight pr-2">Departmental Average TAT over time (hrs)</h4>
                                        <select id="globalThroughputPeriod" class="filter-input border-gray-300 dark:border-gray-600 rounded-md shadow-sm sm:text-sm p-1.5 border bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-accent-1 focus:border-accent-1 text-xs shrink-0">
                                            <option value="daily">Daily</option>
                                            <option value="weekly">Weekly</option>
                                            <option value="monthly">Monthly</option>
                                            <option value="yearly">Yearly</option>
                                        </select>
                                    </div>
                                    <div class="relative h-64 flex-grow">
                                        <canvas id="throughputChart"></canvas>
                                    </div>
                                </div>

                                <!-- Average Processing Time by Dept Chart -->
                                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow-inner flex flex-col justify-between">
                                    <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-600 pb-3 mb-4 h-[68px]">
                                        <h4 class="text-lg font-bold leading-tight pr-2">Average TAT by Department (hrs)</h4>
                                        <button id="view-all-avg-tat-btn" type="button" class="flex-shrink-0 inline-flex items-center px-4 py-2 bg-accent-1 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-accent-1-hover focus:bg-indigo-500 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-accent-1 focus:ring-offset-2 transition ease-in-out duration-150 shadow-sm h-[32px]">
                                            View All
                                        </button>
                                    </div>
                                    <div class="relative h-64 flex-grow">
                                        <canvas id="avgStepTimeChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section: Declines & Peak Intake Analytics -->
                        <div class="bg-gray-50 dark:bg-gray-700/50 p-5 rounded-lg shadow">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Decline Trends Chart -->
                                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow-inner flex flex-col justify-between">
                                    <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-600 pb-3 mb-4 h-[48px]">
                                        <h4 class="text-lg font-bold pr-2">Decline Rate Trends</h4>
                                        <select id="returnDeclinePeriod" class="filter-input border-gray-300 dark:border-gray-600 rounded-md shadow-sm sm:text-sm p-1.5 border bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-accent-1 focus:border-accent-1 text-xs shrink-0">
                                            <option value="daily">Daily</option>
                                            <option value="weekly">Weekly</option>
                                            <option value="monthly">Monthly</option>
                                            <option value="yearly">Yearly</option>
                                        </select>
                                    </div>
                                    <div class="relative h-64 flex-grow">
                                        <canvas id="returnDeclineChart"></canvas>
                                    </div>
                                </div>

                                <!-- Peak Intake Hours Chart -->
                                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow-inner flex flex-col justify-between">
                                    <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-600 pb-3 mb-4 h-[48px]">
                                        <h4 class="text-lg font-bold">Peak Intake Hours (Submissions by Hour)</h4>
                                    </div>
                                    <div class="relative h-64 flex-grow">
                                        <canvas id="peakIntakeHoursChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section: Department Drill-Down -->
                        <div class="bg-gray-50 dark:bg-gray-700/50 p-5 rounded-lg shadow">
                            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow-inner">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-gray-200 dark:border-gray-600 pb-3 mb-4 gap-3">
                                    <h4 id="load-vs-time-title" class="text-lg font-bold">Load vs. Processing Time</h4>
                                    <div class="flex flex-row flex-wrap items-center gap-3 shrink-0">
                                        <select id="department-filter" class="filter-input border-gray-300 dark:border-gray-600 rounded-md shadow-sm sm:text-sm p-1.5 border bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-accent-1 focus:border-accent-1 text-xs">
                                            <?php foreach($departments as $department): ?>
                                                <option value="<?= htmlspecialchars($department['id']) ?>"><?= htmlspecialchars($department['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select id="departmentPeriod" class="filter-input border-gray-300 dark:border-gray-600 rounded-md shadow-sm sm:text-sm p-1.5 border bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-accent-1 focus:border-accent-1 text-xs">
                                            <option value="daily">Daily</option>
                                            <option value="weekly">Weekly</option>
                                            <option value="monthly">Monthly</option>
                                            <option value="yearly">Yearly</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="relative h-96">
                                    <canvas id="loadVsTimeChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Section: Purpose & Origin Analysis -->
                        <div class="bg-gray-50 dark:bg-gray-700/50 p-5 rounded-lg shadow mt-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Processing Hotspots Chart -->
                                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow-inner flex flex-col justify-between">
                                    <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-600 pb-3 mb-4 h-[48px]">
                                        <h4 class="text-lg font-bold">Processing Hotspots (Purpose Popularity)</h4>
                                    </div>
                                    <div class="relative h-96 flex-grow">
                                        <canvas id="processingHotspotsChart"></canvas>
                                    </div>
                                </div>

                                <!-- Submission Volume by District Chart -->
                                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow-inner flex flex-col justify-between">
                                    <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-600 pb-3 mb-4 h-[48px]">
                                        <h4 class="text-lg font-bold">Submission Volume by District</h4>
                                    </div>
                                    <div class="relative h-96 flex-grow">
                                        <canvas id="submissionDistrictsChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

        <!-- Modal: Average TAT by Department (All Departments) -->
        <div id="avg-tat-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="avg-tat-modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <!-- Backdrop -->
                <div class="fixed inset-0 transition-opacity bg-gray-500/75 dark:bg-gray-900/75 close-modal-backdrop" data-modal="avg-tat-modal" aria-hidden="true"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <!-- Modal Box -->
                <div class="inline-block w-full align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                    <div class="px-6 pt-5 pb-4 sm:p-6">
                        <!-- Header -->
                        <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                            <h3 id="avg-tat-modal-title" class="text-xl font-bold text-gray-900 dark:text-white">
                                Average Turnaround Time (TAT) by Department
                            </h3>
                            <button type="button" class="close-modal-btn text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition" data-modal="avg-tat-modal">
                                <span class="sr-only">Close</span>
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Modal Body: Full Chart Container -->
                        <div class="bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg shadow-inner">
                            <div class="relative w-full" style="height: 420px;">
                                <canvas id="allAvgTatChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script src="/js/admin-dashboard.js"></script>
        
    <?php $content = ob_get_clean(); require BASE_PATH . '/src/Views/layouts/app.php'; ?>
    