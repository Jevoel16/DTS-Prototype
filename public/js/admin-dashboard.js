/**
 * Admin Dashboard — Chart initialization and data fetching.
 *
 * Listens to both `DOMContentLoaded` (initial page load) and `dts:page-loaded`
 * (PJAX navigation swap) so charts re-initialize after every navigation without
 * requiring a full browser reload.
 *
 * All fetch() calls are wired to window.__pjaxController.signal so they are
 * automatically cancelled when the user navigates away, preventing orphaned
 * TCP connections and session lock contention.
 */
function initAdminDashboard() {
    const chartContainer = document.querySelector('[data-current-load-url]');
    if (!chartContainer) return;

    // URLs from the main data attribute container
    const { declineTrendsUrl, peakIntakeHoursUrl, statusDistributionUrl, processingHotspotsUrl, avgStepTimeUrl, throughputUrl, loadVsTimeUrl, submissionDistrictsUrl } = chartContainer.dataset;

    // --- Element Selectors ---
    const departmentFilterEl = document.getElementById('department-filter');
    const departmentPeriodEl = document.getElementById('departmentPeriod');
    const globalThroughputPeriodEl = document.getElementById('globalThroughputPeriod');
    const returnDeclinePeriodEl = document.getElementById('returnDeclinePeriod');
    const loadVsTimeTitle = document.getElementById('load-vs-time-title');

    
    // Chart Canvases
    const statusDistributionCtx = document.getElementById('statusDistributionChart')?.getContext('2d');
    const returnDeclineCtx = document.getElementById('returnDeclineChart')?.getContext('2d');
    const peakIntakeHoursCtx = document.getElementById('peakIntakeHoursChart')?.getContext('2d');
    const avgStepTimeCtx = document.getElementById('avgStepTimeChart')?.getContext('2d');
    const throughputCtx = document.getElementById('throughputChart')?.getContext('2d');
    const processingHotspotsCtx = document.getElementById('processingHotspotsChart')?.getContext('2d');
    const submissionDistrictsCtx = document.getElementById('submissionDistrictsChart')?.getContext('2d');
    const loadVsTimeCtx = document.getElementById('loadVsTimeChart')?.getContext('2d'); // New Combo Chart

    // --- Chart Instances ---
    let statusDistributionChart, returnDeclineChart, peakIntakeHoursChart, avgStepTimeChart, throughputChart, processingHotspotsChart, loadVsTimeChart, submissionDistrictsChart;
    let modalChart = null;

    // --- Helper & Modal Functions ---
    function formatDuration(totalSeconds) {
        if (totalSeconds <= 0) return 'N/A';
        const days = Math.floor(totalSeconds / 86400);
        if (days > 0) return `${days}` + (days === 1 ? ' day' : ' days');
        const hours = Math.floor(totalSeconds / 3600);
        if (hours > 0) return `${hours}` + (hours === 1 ? ' hour' : ' hours');
        const minutes = Math.floor(totalSeconds / 60);
        if (minutes > 0) return `${minutes}` + (minutes === 1 ? ' min' : ' mins');
        const seconds = Math.floor(totalSeconds);
        return `${seconds}` + (seconds === 1 ? ' sec' : ' secs');
    }

    // --- Chart Initializers ---
    function initializeCharts() {
        const lineChartOptions = { responsive: true, scales: { y: { beginAtZero: true, title: { display: true, text: 'Average Processing Time (hrs)' } } }, plugins: { title: { display: false } } };
        const barChartOptions = { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } } };
        const horizontalBarOptions = { responsive: true, maintainAspectRatio: false, indexAxis: 'y', scales: { x: { beginAtZero: true, ticks: { callback: (v) => formatDuration(v) } } }, plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => 'Avg Time: ' + formatDuration(c.raw) } } } };

        statusDistributionChart = new Chart(statusDistributionCtx, { type: 'doughnut', data: { labels: [], datasets: [] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' }, title: { display: false } } } });
        returnDeclineChart = new Chart(returnDeclineCtx, { type: 'line', data: { labels: [], datasets: [] }, options: { ...lineChartOptions, scales: { y: { ...lineChartOptions.scales.y, title: { display: true, text: 'Number of Documents' } } } } });
        peakIntakeHoursChart = new Chart(peakIntakeHoursCtx, {
            type: 'bar',
            data: { labels: [], datasets: [] },
            options: {
                ...barChartOptions,
                scales: {
                    x: { title: { display: true, text: 'Hour of Day' } },
                    y: { beginAtZero: true, title: { display: true, text: 'Documents Submitted' } }
                }
            }
        });
        throughputChart = new Chart(throughputCtx, { type: 'line', data: { labels: [], datasets: [] }, options: { ...lineChartOptions, maintainAspectRatio: false } });
        processingHotspotsChart = new Chart(processingHotspotsCtx, {
            type: 'polarArea',
            data: { labels: [], datasets: [] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        beginAtZero: true,
                        title: { display: true, text: 'Document Count' },
                        ticks: {
                            showLabelBackdrop: true,
                            backdropColor: 'rgba(255, 255, 255, 0.75)',
                            color: '#1f2937', // Dark gray for text
                            z: 1 // Ensure ticks are above the chart segments
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)' // Light grid lines
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const dataset = context.dataset;
                                const index = context.dataIndex;
                                const volume = context.raw;
                                const time = dataset.avgHours[index];
                                return `${context.label}: Vol: ${volume}, Avg Time: ${time} hrs`;
                            }
                        }
                    },
                    legend: {
                        display: true,
                        position: 'right',
                        labels: {
                            boxWidth: 10,
                            font: { size: 10 }
                        }
                    }
                }
            }
        });
        
        submissionDistrictsChart = new Chart(submissionDistrictsCtx, {
            type: 'bar',
            data: { labels: [], datasets: [] },
            options: {
                ...barChartOptions,
                indexAxis: 'y',
                scales: {
                    x: { beginAtZero: true, title: { display: true, text: 'Total Documents' } }
                }
            }
        });

        avgStepTimeChart = new Chart(avgStepTimeCtx, { type: 'bar', data: { labels: [], datasets: [] }, options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            scales: {
                x: {
                    beginAtZero: true,
                    title: { display: true, text: 'Average Step Time (hrs)' }
                },
                y: {
                    ticks: {
                        callback: function(v) {
                            const l = this.getLabelForValue(v);
                            return l.length > 15 ? l.substring(0, 12) + '...' : l;
                        }
                    }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            // Display the raw value which is now in hours, with 2 decimal places
                            return `Avg Time: ${Number(context.raw).toFixed(2)} hrs`;
                        }
                    }
                }
            }
        } });
        
        loadVsTimeChart = new Chart(loadVsTimeCtx, {
            type: 'line',
            data: { labels: [], datasets: [] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { type: 'linear', display: true, position: 'left', title: { display: true, text: 'Documents Received' } },
                    y1: { type: 'linear', display: true, position: 'right', title: { display: true, text: 'Avg. Processing Time (hrs)' }, grid: { drawOnChartArea: false } }
                }
            }
        });
    }

    // --- Data Fetching ---
    // All fetch() calls attach window.__pjaxController.signal so they are
    // automatically cancelled when the user navigates away to a new page.
    const fetchData = (url, chart) => {
        const signal = window.__pjaxController?.signal;
        fetch(url, { signal })
            .then(response => response.json())
            .then(data => {
                if (data.datasets) {
                    // It's a full chart object (labels + datasets)
                    chart.data.labels = data.labels || [];
                    chart.data.datasets = data.datasets;
                } else {
                    // It's a simple data array (labels + data)
                    chart.data.labels = data.labels || [];
                    chart.data.datasets = [{
                        data: data.data || [],
                        backgroundColor: chart.data.datasets[0]?.backgroundColor || 'rgba(54, 162, 235, 0.5)',
                        borderColor: chart.data.datasets[0]?.borderColor || 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }];
                }
                chart.update();
            })
            .catch(error => {
                // Suppress AbortError noise — it's expected when navigating away
                if (error.name !== 'AbortError') {
                    console.error(`Error fetching data for ${chart.canvas.id}:`, error);
                }
            });
    };

    const fetchLoadVsTimeData = (period, departmentId) => {
        const url = new URL(loadVsTimeUrl, window.location.origin);
        url.searchParams.append('period', period);
        url.searchParams.append('department_id', departmentId);
        fetchData(url, loadVsTimeChart);
    };
    
    // --- Event Listeners ---
    const updateLoadVsTimeChart = () => {
        const departmentId = departmentFilterEl.value;
        const period = departmentPeriodEl.value;
        const deptName = departmentFilterEl.options[departmentFilterEl.selectedIndex].text;
        if (loadVsTimeTitle) loadVsTimeTitle.textContent = `${deptName} - Load vs. Processing Time`;
        fetchLoadVsTimeData(period, departmentId);
    };

    if (departmentFilterEl) departmentFilterEl.addEventListener('change', updateLoadVsTimeChart);
    if (departmentPeriodEl) departmentPeriodEl.addEventListener('change', updateLoadVsTimeChart);
    if (globalThroughputPeriodEl) globalThroughputPeriodEl.addEventListener('change', () => fetchData(`${throughputUrl}?period=${globalThroughputPeriodEl.value}`, throughputChart));
    if (returnDeclinePeriodEl) returnDeclinePeriodEl.addEventListener('change', () => fetchData(`${declineTrendsUrl}?period=${returnDeclinePeriodEl.value}`, returnDeclineChart));

    // --- Average TAT Modal Logic ---
    const viewAllAvgTatBtn = document.getElementById('view-all-avg-tat-btn');
    const avgTatModal = document.getElementById('avg-tat-modal');
    const allAvgTatCanvas = document.getElementById('allAvgTatChart');
    let allAvgTatChartInstance = null;

    const openAvgTatModal = () => {
        if (!avgTatModal) return;
        avgTatModal.classList.remove('hidden');

        // Fetch full dataset with all departments
        const signal = window.__pjaxController?.signal;
        fetch(`${avgStepTimeUrl}?full=1`, { signal })
            .then(res => res.json())
            .then(data => {
                // Render Full Chart
                if (allAvgTatCanvas && typeof Chart !== 'undefined') {
                    if (allAvgTatChartInstance) {
                        allAvgTatChartInstance.destroy();
                    }

                    const ctx = allAvgTatCanvas.getContext('2d');
                    allAvgTatChartInstance = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.labels || [],
                            datasets: data.datasets || []
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            indexAxis: 'y',
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    title: { display: true, text: 'Average Turnaround Time (hrs)' }
                                },
                                y: {
                                    ticks: {
                                        font: { size: 11 }
                                    }
                                }
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return `Avg TAT: ${Number(context.raw).toFixed(2)} hrs`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            })
            .catch(err => {
                if (err.name !== 'AbortError') {
                    console.error('Error fetching full Average TAT data:', err);
                }
            });
    };

    if (viewAllAvgTatBtn) {
        viewAllAvgTatBtn.addEventListener('click', openAvgTatModal);
    }

    const closeBtns = document.querySelectorAll('#avg-tat-modal .close-modal-btn, #avg-tat-modal .close-modal-backdrop');
    closeBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            if (avgTatModal) avgTatModal.classList.add('hidden');
        });
    });



    // --- Initial Load ---
    initializeCharts();
    const updateAllCharts = () => {
        fetchData(statusDistributionUrl, statusDistributionChart);
        fetchData(processingHotspotsUrl, processingHotspotsChart);
        fetchData(submissionDistrictsUrl, submissionDistrictsChart);
        fetchData(avgStepTimeUrl, avgStepTimeChart);
        fetchData(`${declineTrendsUrl}?period=${returnDeclinePeriodEl.value}`, returnDeclineChart);
        if (peakIntakeHoursUrl) fetchData(peakIntakeHoursUrl, peakIntakeHoursChart);
        fetchData(`${throughputUrl}?period=${globalThroughputPeriodEl.value}`, throughputChart);
        updateLoadVsTimeChart();
    };

    updateAllCharts();

    // Polling: Update all charts every 60 seconds.
    // Store the interval ID on the controller so it can be cleared if needed.
    const pollInterval = setInterval(updateAllCharts, 60000);

    // Clear the polling interval when the user navigates away (PJAX or otherwise)
    // to prevent ghost polling from a page that no longer exists in the DOM.
    window.__pjaxController?.signal.addEventListener('abort', () => {
        clearInterval(pollInterval);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAdminDashboard);
} else {
    initAdminDashboard();
}
