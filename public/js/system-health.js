/**
 * System Health Page — integrity checks, DB performance charts, modal logic.
 *
 * Listens to both `DOMContentLoaded` and `dts:page-loaded` (PJAX swap)
 * so all elements re-bind correctly after client-side navigation.
 */
function initSystemHealth() {
    // Reusable Modal Logic
    const closeBtns = document.querySelectorAll('.close-modal-btn');
    closeBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const modalId = btn.getAttribute('data-modal');
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('hidden');
                modal.style.display = '';
            }
        });
    });

    // Database Performance Chart
    const dbPerformanceContainer = document.getElementById('db-performance-chart-container');
    if (dbPerformanceContainer && typeof Chart !== 'undefined') {
        const dbPerformanceCtx = document.getElementById('dbPerformanceChart').getContext('2d');
        const dbPerformancePeriodSelect = document.getElementById('db-performance-period');
        const dbPerformanceUrl = dbPerformanceContainer.dataset.url;
        let dbPerformanceChart;

        const fetchAndRenderDbPerformanceChart = (period) => {
            // Wire to PJAX controller so this request is cancelled on navigation
            const signal = window.__pjaxController?.signal;
            fetch(`${dbPerformanceUrl}?period=${period}`, { signal })
                .then(response => response.json())
                .then(data => {
                    if (dbPerformanceChart) {
                        dbPerformanceChart.destroy();
                    }
                    dbPerformanceChart = new Chart(dbPerformanceCtx, {
                        type: 'line',
                        data: data,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    title: { display: true, text: 'Connections' }
                                },
                                y1: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    title: { display: true, text: 'Avg Query Time (ms)' },
                                    grid: { drawOnChartArea: false }
                                },
                                y2: {
                                    type: 'linear',
                                    display: false,
                                    position: 'right',
                                }
                            }
                        }
                    });
                })
                .catch(err => {
                    if (err.name !== 'AbortError') console.error('DB chart fetch error:', err);
                });
        };

        fetchAndRenderDbPerformanceChart(dbPerformancePeriodSelect.value);
        dbPerformancePeriodSelect.addEventListener('change', () => {
            fetchAndRenderDbPerformanceChart(dbPerformancePeriodSelect.value);
        });
    }

    // Run Integrity Check with Progress Modal
    const runCheckButton = document.getElementById('run-integrity-check');
    const integrityModal = document.getElementById('integrity-progress-modal');
    const closeIntegrityModalBtn = document.getElementById('close-integrity-modal');
    const integrityProgressBar = document.getElementById('integrity-progress-bar');
    const integrityProgressText = document.getElementById('integrity-progress-text');
    const integrityProgressTime = document.getElementById('integrity-progress-time');

    let pollingInterval;
    let startTime;
    let currentJobId;

    if (runCheckButton) {
        runCheckButton.addEventListener('click', async function() {
            try {
                runCheckButton.disabled = true;
                
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const response = await fetch(runCheckButton.dataset.url, {
                    method: 'POST',
                    headers: { 
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                });

                if (!response.ok) throw new Error('Could not start integrity check.');

                const data = await response.json();
                currentJobId = data.job_id;

                // Show Modal
                if (integrityModal) {
                    integrityModal.classList.remove('hidden');
                    integrityModal.style.display = '';
                }
                
                integrityProgressBar.style.width = '0%';
                integrityProgressBar.classList.remove('bg-green-500', 'bg-red-500');
                integrityProgressBar.classList.add('bg-accent-1');
                integrityProgressText.textContent = 'Initializing check...';
                integrityProgressTime.textContent = 'Est. time remaining: Calculating...';
                
                closeIntegrityModalBtn.textContent = 'Cancel Verification';
                closeIntegrityModalBtn.classList.remove('bg-white', 'text-gray-700', 'hover:bg-gray-50', 'dark:bg-accent-2', 'dark:text-gray-200', 'dark:hover:bg-accent-2-hover');
                closeIntegrityModalBtn.classList.add('bg-red-500', 'text-white', 'hover:bg-red-600');

                startTime = Date.now();

                // Start polling
                pollingInterval = setInterval(() => {
                    pollIntegrityStatus(currentJobId);
                }, 3000);

            } catch (error) {
                console.error('Error:', error);
                alert(error.message);
                runCheckButton.disabled = false;
            }
        });
    }

    async function pollIntegrityStatus(jobId) {
        try {
            const response = await fetch(`/api/system-health/integrity-status/${jobId}`);
            if (!response.ok) throw new Error('Could not get integrity status.');
            const job = await response.json();

            integrityProgressBar.style.width = `${job.progress || 0}%`;
            
            let statusMsg = "Verifying...";
            if (job.progress <= 5) statusMsg = "Job queued...";
            else if (job.progress <= 50) statusMsg = "Verifying historical log hashes and signatures...";
            else if (job.progress < 100) statusMsg = "Comparing live document states with last logs...";
            
            integrityProgressText.textContent = `${statusMsg} (${job.progress || 0}%)`;

            if (job.progress > 0 && job.progress < 100) {
                const elapsed = (Date.now() - startTime) / 1000;
                const estimatedTotal = elapsed / (job.progress / 100);
                const remaining = Math.max(0, estimatedTotal - elapsed);
                integrityProgressTime.textContent = `Est. time remaining: ${formatTime(remaining)}`;
            }

            if (job.status === 'completed') {
                finishIntegrityJob(true, jobId);
            } else if (job.status === 'failed') {
                finishIntegrityJob(false, jobId, job.error_message);
            } else if (job.status === 'cancelled') {
                finishIntegrityJob(false, jobId, "Verification was cancelled.");
            }
        } catch (error) {
            console.error('Polling error:', error);
        }
    }

    function finishIntegrityJob(success, jobId, error = null) {
        clearInterval(pollingInterval);
        integrityProgressBar.style.width = '100%';
        integrityProgressBar.classList.remove('bg-accent-1');
        closeIntegrityModalBtn.textContent = 'Close & Refresh';
        closeIntegrityModalBtn.classList.remove('bg-red-500', 'text-white', 'hover:bg-red-600');
        closeIntegrityModalBtn.classList.add('bg-white', 'text-gray-700', 'hover:bg-gray-50', 'dark:bg-accent-2', 'dark:text-gray-200', 'dark:hover:bg-accent-2-hover');

        if (success) {
            integrityProgressBar.classList.add('bg-green-500');
            integrityProgressText.textContent = 'Integrity verification complete!';
            integrityProgressTime.textContent = 'System verified.';
            
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            integrityProgressBar.classList.add('bg-red-500');
            integrityProgressText.textContent = `Error: ${error || 'An unknown error occurred.'}`;
            integrityProgressTime.textContent = 'Verification Failed';
        }
    }

    function formatTime(seconds) {
        if (seconds < 1) return "Soon...";
        if (seconds < 60) return `${Math.round(seconds)}s`;
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = Math.round(seconds % 60);
        return `${minutes}m ${remainingSeconds}s`;
    }

    if (closeIntegrityModalBtn) {
        closeIntegrityModalBtn.addEventListener('click', async () => {
            if (closeIntegrityModalBtn.textContent === 'Cancel Verification' && currentJobId) {
                if (confirm('Are you sure you want to stop the integrity check?')) {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    await fetch(`/api/system-health/integrity-cancel/${currentJobId}`, {
                        method: 'POST',
                        headers: { 
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken
                        }
                    });
                    finishIntegrityJob(false, currentJobId, "User cancelled the task.");
                }
            } else {
                if (integrityModal) {
                    integrityModal.classList.add('hidden');
                    integrityModal.style.display = '';
                }
                window.location.reload();
            }
        });
    }

    // Debug Hash Modal
    const debugModal = document.getElementById('debug-hash-modal');
    const componentsBody = document.getElementById('debug-components-body');
    const storedHashVal = document.getElementById('stored-hash-val');
    const recalculatedHashVal = document.getElementById('recalculated-hash-val');
    const rawDataStringVal = document.getElementById('raw-data-string-val');

    document.querySelectorAll('.debug-log-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const url = btn.dataset.url;
            btn.disabled = true;
            btn.textContent = '...';

            try {
                const response = await fetch(url);
                const data = await response.json();

                storedHashVal.textContent = data.stored_hash;
                recalculatedHashVal.textContent = data.recalculated_hash;
                rawDataStringVal.textContent = data.raw_data_string;

                componentsBody.innerHTML = '';
                for (const [key, value] of Object.entries(data.components)) {
                    componentsBody.innerHTML += `
                        <tr>
                            <td class="px-4 py-2 font-bold text-accent-1 dark:text-accent-1-hover">${key}</td>
                            <td class="px-4 py-2 break-all dark:text-gray-300">${value === null ? '<span class="text-red-500 italic">null</span>' : value}</td>
                        </tr>
                    `;
                }

                if (debugModal) {
                    debugModal.classList.remove('hidden');
                    debugModal.style.display = '';
                }
            } catch (error) {
                console.error('Debug error:', error);
                alert('Failed to fetch debug info.');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Debug';
            }
        });
    });

    const viewBtn = document.getElementById('view-failed-jobs');
    const detailsSection = document.getElementById('failed-jobs-details');
    if (viewBtn && detailsSection) {
        viewBtn.addEventListener('click', () => {
            detailsSection.classList.toggle('hidden');
            viewBtn.textContent = detailsSection.classList.contains('hidden') ? 'View Details' : 'Hide Details';
        });
    }

    // Handle form submissions for actions (AJAX version)
    document.querySelectorAll('.rebuild-form, .freeze-form, .unfreeze-form').forEach(form => {
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            submitFormAjax(this);
        });
    });

}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSystemHealth);
} else {
    initSystemHealth();
}


