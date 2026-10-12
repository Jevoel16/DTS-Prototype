<?php
/**
 * Reusable Department Analytics Component
 * 
 * Assumes JS variables are handled externally or `statistics.js` handles fetching.
 */
?>
<div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
    <div class="p-6 text-gray-900 dark:text-gray-100">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-2xl font-bold">Performance Analytics for <?= htmlspecialchars($_SESSION['department_name'] ?? 'Your Department') ?></h3>
            <form action="/clear-personal-cache" method="POST" class="confirm-action m-0" data-message="Are you sure you want to clear your dashboard cache? This will refresh your view but preserve others' caches.">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <button type="submit" class="inline-flex items-center px-3 py-1 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-accent-2 focus:outline-none transition">
                    Clear Cache
                </button>
            </form>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6"
             data-current-load-url="/api/statistics/current-load"
             data-throughput-url="/api/statistics/throughput"
             data-avg-processing-time-url="/api/statistics/avg-processing-time">
            
            <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg shadow">
                <div class="flex justify-between items-center mb-4 border-b border-gray-200 dark:border-gray-600 pb-2">
                    <h4 class="text-lg font-bold">Documents Received</h4>
                    <select id="currentLoadPeriod" class="chart-period-selector block bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-600 rounded-md shadow-sm sm:text-sm p-2 border focus:border-accent-1 focus:ring-accent-1">
                        <option value="daily">Daily (30 Days)</option>
                        <option value="weekly">Weekly (4 Weeks)</option>
                        <option value="monthly">Monthly (12 Months)</option>
                    </select>
                </div>
                <div class="relative" style="height:200px">
                    <canvas id="currentLoadChart"></canvas>
                </div>
            </div>
            
            <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg shadow">
                <div class="flex justify-between items-center mb-4 border-b border-gray-200 dark:border-gray-600 pb-2">
                    <h4 class="text-lg font-bold">Average Processing Time</h4>
                    <select id="avgProcessingTimePeriod" class="chart-period-selector block bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-600 rounded-md shadow-sm sm:text-sm p-2 border focus:border-accent-1 focus:ring-accent-1">
                        <option value="daily">Daily (30 Days)</option>
                        <option value="weekly">Weekly (4 Weeks)</option>
                        <option value="monthly">Monthly (12 Months)</option>
                    </select>
                </div>
                <div class="relative" style="height:200px">
                    <canvas id="avgProcessingTimeChart"></canvas>
                </div>
            </div>
            
            <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg shadow md:col-span-2">
                <div class="flex justify-between items-center mb-4 border-b border-gray-200 dark:border-gray-600 pb-2">
                    <h4 class="text-lg font-bold">Documents Processed Over Time</h4>
                    <select id="throughputPeriod" class="chart-period-selector block bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-600 rounded-md shadow-sm sm:text-sm p-2 border focus:border-accent-1 focus:ring-accent-1">
                        <option value="daily">Daily (Last 30 Days)</option>
                        <option value="weekly">Weekly (Last 4 Weeks)</option>
                        <option value="monthly">Monthly (Last 12 Months)</option>
                        <option value="yearly">Yearly (Last 5 Years)</option>
                    </select>
                </div>
                <div class="relative" style="height:400px">
                    <canvas id="throughputChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>
