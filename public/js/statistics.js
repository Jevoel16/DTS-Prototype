/**
 * Statistics Page — Chart initialization and report generation.
 *
 * Listens to both `DOMContentLoaded` and `dts:page-loaded` (PJAX swap)
 * so charts re-initialize correctly after client-side navigation.
 */
function initStatistics() {
    const chartContainer = document.querySelector('.grid');
    if (!chartContainer) return;

    // URLs from the data attributes
    const currentLoadUrl = chartContainer.dataset.currentLoadUrl;
    const throughputUrl = chartContainer.dataset.throughputUrl;
    const avgProcessingTimeUrl = chartContainer.dataset.avgProcessingTimeUrl;

    // Dropdown selectors
    const currentLoadPeriodEl = document.getElementById('currentLoadPeriod');
    const avgProcessingTimePeriodEl = document.getElementById('avgProcessingTimePeriod');
    const throughputPeriodEl = document.getElementById('throughputPeriod');

    // Canvas contexts
    const currentLoadCtx = document.getElementById('currentLoadChart')?.getContext('2d');
    const throughputCtx = document.getElementById('throughputChart')?.getContext('2d');
    const avgProcessingTimeCtx = document.getElementById('avgProcessingTimeChart')?.getContext('2d');

    if (!currentLoadCtx || !throughputCtx || !avgProcessingTimeCtx) {
        return;
    }

    let currentLoadChart, throughputChart, avgProcessingTimeChart;
    window.dtsCharts = { currentLoadChart, throughputChart, avgProcessingTimeChart };

    const defaultLineOptions = {
        responsive: true,
        maintainAspectRatio: false,
        scales: { y: { beginAtZero: true } },
        plugins: { title: { display: false }, legend: { display: false } },
        interaction: { intersect: false, mode: 'index' },
    };

    function initializeCharts() {
        currentLoadChart = new Chart(currentLoadCtx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Documents Received',
                    data: [],
                    borderColor: 'rgba(54, 162, 235, 1)',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    fill: true,
                    tension: 0.1
                }]
            },
            options: { ...defaultLineOptions, scales: { y: { ...defaultLineOptions.scales.y, title: { display: true, text: 'Number of Documents' } } } }
        });

        avgProcessingTimeChart = new Chart(avgProcessingTimeCtx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Avg. Time (Hours)',
                    data: [],
                    borderColor: 'rgba(255, 159, 64, 1)',
                    backgroundColor: 'rgba(255, 159, 64, 0.2)',
                    fill: true,
                    tension: 0.1
                }]
            },
            options: { ...defaultLineOptions, scales: { y: { ...defaultLineOptions.scales.y, title: { display: true, text: 'Hours' } } } }
        });

        throughputChart = new Chart(throughputCtx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Documents Processed',
                    data: [],
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    fill: true,
                    tension: 0.1
                }]
            },
            options: { ...defaultLineOptions, scales: { y: { ...defaultLineOptions.scales.y, title: { display: true, text: 'Number of Documents' } } } }
        });

        window.dtsCharts.currentLoadChart = currentLoadChart;
        window.dtsCharts.avgProcessingTimeChart = avgProcessingTimeChart;
        window.dtsCharts.throughputChart = throughputChart;
    }

    function fetchChartData(chart, baseUrl, period, chartName) {
        if (!chart) return;
        const url = new URL(baseUrl, window.location.origin);
        url.searchParams.append('period', period);
        // Wire to PJAX controller so this request is cancelled on navigation
        const signal = window.__pjaxController?.signal;
        fetch(url, { signal })
            .then(response => response.json())
            .then(data => {
                chart.data.labels = data.labels;
                chart.data.datasets[0].data = data.data;
                chart.update();
            })
            .catch(error => {
                if (error.name !== 'AbortError') {
                    console.error(`Error fetching ${chartName} data:`, error);
                }
            });
    }

    function initialize() {
        initializeCharts();

        const updateAllStatisticsCharts = () => {
            if (currentLoadPeriodEl) fetchChartData(currentLoadChart, currentLoadUrl, currentLoadPeriodEl.value, 'documents received');
            if (avgProcessingTimePeriodEl) fetchChartData(avgProcessingTimeChart, avgProcessingTimeUrl, avgProcessingTimePeriodEl.value, 'avg processing time');
            if (throughputPeriodEl) fetchChartData(throughputChart, throughputUrl, throughputPeriodEl.value, 'throughput');
        };

        // Initial data fetch
        updateAllStatisticsCharts();

        // Polling: Update all statistics charts every 60 seconds
        const pollInterval = setInterval(updateAllStatisticsCharts, 60000);
        
        window.__pjaxController?.signal.addEventListener('abort', () => {
            clearInterval(pollInterval);
        });

        // Add event listeners
        currentLoadPeriodEl?.addEventListener('change', (e) => fetchChartData(currentLoadChart, currentLoadUrl, e.target.value, 'documents received'));
        avgProcessingTimePeriodEl?.addEventListener('change', (e) => fetchChartData(avgProcessingTimeChart, avgProcessingTimeUrl, e.target.value, 'avg processing time'));
        throughputPeriodEl?.addEventListener('change', (e) => fetchChartData(throughputChart, throughputUrl, e.target.value, 'throughput'));
        
        initializeReportGeneration();
    }
    
    function formatTime(seconds) {
        if (seconds < 1) return "Soon...";
        if (seconds < 60) return `${Math.round(seconds)}s`;
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = Math.round(seconds % 60);
        return `${minutes}m ${remainingSeconds}s`;
    }

    function initializeReportGeneration() {
        const generateReportBtn = document.getElementById('generate-report-btn');
        const reportForm = document.getElementById('report-generation-form');
        
        if (!generateReportBtn || !reportForm) return;

        const reportModal = document.getElementById('report-progress-modal');
        const closeReportModalBtn = document.getElementById('close-report-modal');
        const progressBar = document.getElementById('report-progress-bar');
        const progressText = document.getElementById('report-progress-text');
        const progressTime = document.getElementById('report-progress-time');
        const downloadBtnContainer = document.getElementById('report-download-container');
        
        let pollingInterval;
        let startTime;
        let currentJobId;

        generateReportBtn.addEventListener('click', async function(e) {
            e.preventDefault();
            
            // Populate hidden form with current filters
            const dateFilter = document.querySelector('input[name="date"]');
            const purposeFilter = document.querySelector('select[name="purpose"]');
            const submitterFilter = document.querySelector('input[name="submitter"]');
            const searchFilter = document.querySelector('input[name="search"]');

            // Wait, we need to handle the actual table filter IDs from table-filters.php
            // By default, data-panel.php filters look like #filter-date, #filter-purpose, etc. Let's look for both possible IDs or name attributes
            document.getElementById('form_date').value = document.querySelector('input[name="date"]')?.value || document.getElementById('filter-date')?.value || '';
            document.getElementById('form_purpose').value = document.querySelector('select[name="purpose"]')?.value || document.getElementById('filter-purpose')?.value || 'all';
            document.getElementById('form_submitter').value = document.querySelector('input[name="submitter"]')?.value || document.getElementById('filter-submitter')?.value || '';
            document.getElementById('form_search').value = document.querySelector('input[name="search"]')?.value || document.getElementById('table-search')?.value || '';

            try {
                generateReportBtn.disabled = true;
                generateReportBtn.textContent = 'Preparing...';

                const formData = new FormData(reportForm);
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const response = await fetch(reportForm.action, {
                    method: 'POST',
                    body: formData,
                    headers: { 
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                });

                generateReportBtn.disabled = false;
                generateReportBtn.textContent = 'Generate Report';

                if (!response.ok) {
                    let errMsg = 'Could not start report generation.';
                    try {
                        const errJson = await response.json();
                        if (errJson && errJson.message) errMsg = errJson.message;
                    } catch (e) {}
                    throw new Error(errMsg);
                }

                const data = await response.json();
                currentJobId = data.job_id;

                reportModal.style.display = 'block';
                progressBar.style.width = '0%';
                progressBar.classList.remove('bg-green-500', 'bg-red-500');
                progressBar.classList.add('bg-accent-1');
                progressText.textContent = 'Report is in the queue...';
                progressTime.textContent = 'Est. time remaining: Calculating...';
                
                closeReportModalBtn.textContent = 'Cancel Report';
                closeReportModalBtn.classList.remove('bg-white', 'text-gray-700', 'hover:bg-gray-50');
                closeReportModalBtn.classList.add('bg-red-500', 'text-white', 'hover:bg-red-600');
                downloadBtnContainer.innerHTML = '';

                startTime = Date.now();
                pollingInterval = setInterval(() => pollJobStatus(currentJobId), 3000);

            } catch (error) {
                console.error(error);
                alert(error.message || 'An error occurred starting the report generation.');
                generateReportBtn.disabled = false;
                generateReportBtn.textContent = 'Generate Report';
            }
        });

        closeReportModalBtn.addEventListener('click', async function() {
            if (closeReportModalBtn.textContent === 'Cancel Report') {
                if (currentJobId) {
                    const fd = new FormData();
                    fd.append('job_id', currentJobId);
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    await fetch('/statistics/report/cancel', { 
                        method: 'POST', 
                        body: fd,
                        headers: { 'X-CSRF-TOKEN': csrfToken }
                    });
                }
                finishJob(false, currentJobId, "Report cancelled.");
            } else {
                reportModal.style.display = 'none';
            }
        });

        async function pollJobStatus(jobId) {
            try {
                const response = await fetch(`/statistics/report/status?job_id=${jobId}`);
                if (!response.ok) throw new Error('Could not get report status.');
                const job = await response.json();

                if (!job) return;

                progressBar.style.width = `${job.progress || 0}%`;
                
                let statusMsg = "Processing...";
                if (job.progress <= 5) statusMsg = "Job queued...";
                else if (job.progress <= 50) statusMsg = "Fetching documents...";
                else if (job.progress < 100) statusMsg = "Saving Spreadsheet...";
                
                progressText.textContent = `${statusMsg} (${job.progress || 0}%)`;

                if (job.progress > 0 && job.progress < 100) {
                    const elapsed = (Date.now() - startTime) / 1000;
                    const estimatedTotal = elapsed / (job.progress / 100);
                    const remaining = Math.max(0, estimatedTotal - elapsed);
                    progressTime.textContent = `Est. time remaining: ${formatTime(remaining)}`;
                }

                if (job.status === 'completed') {
                    finishJob(true, jobId);
                } else if (job.status === 'failed') {
                    finishJob(false, jobId, job.error_message);
                } else if (job.status === 'cancelled') {
                    finishJob(false, jobId, 'Job cancelled.');
                }
            } catch (error) {
                console.error(error);
            }
        }

        async function refreshPastReports() {
            const modal = document.getElementById('past-reports-modal');
            if (!modal) return;
            const tbody = modal.querySelector('tbody');
            if (!tbody) return;

            try {
                const res = await fetch('/api/statistics/past-reports');
                if (!res.ok) return;
                const reports = await res.json();

                if (!reports || reports.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="3" class="px-4 py-4 whitespace-nowrap text-center text-sm text-gray-500 dark:text-gray-400">
                                No past reports available.
                            </td>
                        </tr>
                    `;
                    return;
                }

                tbody.innerHTML = reports.map(r => {
                    const dateObj = new Date(r.created_at);
                    const dateStr = dateObj.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
                    const timeStr = dateObj.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
                    return `
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition duration-150">
                            <td class="px-4 py-4 text-sm text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                <div class="flex flex-col text-gray-500 dark:text-gray-400">
                                    <span>${dateStr}</span>
                                    <span class="text-xs mt-0.5">${timeStr}</span>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                <span class="text-gray-500 dark:text-gray-400">${r.total_documents}</span>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                <a href="/statistics/report/download/${r.id}" class="text-accent-1 hover:underline font-semibold">Download Spreadsheet</a>
                            </td>
                        </tr>
                    `;
                }).join('');
            } catch (e) {
                console.error('Failed to refresh past reports:', e);
            }
        }

        document.querySelectorAll('[data-modal="past-reports-modal"]').forEach(btn => {
            btn.addEventListener('click', refreshPastReports);
        });

        function finishJob(success, jobId, error = null) {
            clearInterval(pollingInterval);
            progressBar.style.width = '100%';
            
            closeReportModalBtn.textContent = 'Close';
            closeReportModalBtn.classList.remove('bg-red-500', 'text-white', 'hover:bg-red-600');
            closeReportModalBtn.classList.add('bg-white', 'text-gray-700', 'hover:bg-gray-50');

            if (success) {
                progressBar.classList.remove('bg-accent-1');
                progressBar.classList.add('bg-green-500');
                progressText.textContent = 'Report generated successfully!';
                progressTime.textContent = 'Ready for download.';
                
                downloadBtnContainer.innerHTML = `
                    <div class="mt-4 flex justify-center">
                        <a href="/statistics/report/download/${jobId}" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none transition">
                            Download Spreadsheet Again
                        </a>
                    </div>
                `;
                refreshPastReports();
                window.location.href = `/statistics/report/download/${jobId}`;
            } else {
                progressBar.classList.remove('bg-accent-1');
                progressBar.classList.add('bg-red-500');
                progressText.textContent = 'Report generation failed.';
                progressTime.textContent = error || 'An unknown error occurred.';
            }
        }
    }

    initialize();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initStatistics);
} else {
    initStatistics();
}
