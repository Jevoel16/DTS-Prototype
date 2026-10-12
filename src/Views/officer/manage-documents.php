<?php $title = 'Manage Route — ' . ($document['tracking_code'] ?? 'Document'); ob_start(); ?>
<h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
    Manage Document Route: <?php echo htmlspecialchars($document['tracking_code']); ?>
</h2>
<?php $header = ob_get_clean(); ?>

<?php ob_start(); ?>

<div class="py-6 sm:py-10">
    <div class="px-4 sm:px-6 lg:px-8 xl:mx-[20vh] space-y-6">
        
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-4 sm:p-6 text-gray-900 dark:text-gray-100">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <!-- Document Details -->
                    <div>
                        <h3 class="text-lg font-bold mb-2">Document Details</h3>
                        <?php 
                        $guestInfo = json_decode($document['guest_info'], true);
                        $guestName = htmlspecialchars($guestInfo['name'] ?? 'N/A');
                        $guestEmail = htmlspecialchars($guestInfo['email'] ?? 'N/A');
                        ?>
                        <p><strong>Submitter:</strong> <?php echo $guestName; ?> (<?php echo $guestEmail; ?>)</p>
                        <p><strong>Purpose:</strong> <?php echo htmlspecialchars($document['purpose_name'] ?? 'Unknown'); ?></p>
                        <p><strong>Submitted:</strong> <?php echo date('M d, Y h:i A', strtotime($document['created_at'])); ?></p>
                        <p><strong>Status:</strong> <?php $status = $document['status']; require BASE_PATH . '/src/Views/components/status-badge.php'; ?></p>
                    </div>

                    <!-- Route Management Form -->
                    <div>
                        <h3 class="text-lg font-bold mb-2">Manage Route</h3>
                        
                        <?php if ($document['status'] === 'pending'): ?>
                            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                                Drag and drop the boxes to re-order them, or add a new step from the dropdown below.
                            </p>
                            <form id="route-form" action="/documents/<?php echo $document['id']; ?>/finalize" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                <input type="hidden" name="final_route" id="final_route">
                                
                                <input type="hidden" name="pin" id="finalize-pin-input">

                                <!-- Horizontal Draggable List -->
                                <div class="overflow-x-auto pb-4">
                                    <div id="route-list" class="flex space-x-4 min-h-[8rem] bg-gray-50 dark:bg-gray-900 p-2 rounded-md">
                                        <?php if (!empty($document['suggested_route'])): ?>
                                            <?php foreach ($document['suggested_route'] as $index => $step): ?>
                                                <?php 
                                                    $stepName = is_array($step) ? ($step['name'] ?? 'Unknown') : $step; 
                                                    $isInjected = is_array($step) && isset($step['is_injected']) && $step['is_injected'];
                                                ?>
                                                <div class="route-step flex-shrink-0 w-40 p-4 bg-white dark:bg-gray-700 rounded-lg shadow-md cursor-move text-center relative mr-4 last:mr-0 select-none border-2 <?php echo $isInjected ? 'border-primary' : 'border-transparent'; ?>">
                                                    <button type="button" class="delete-step-btn w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center text-xl absolute -top-1 -right-1 hover:bg-red-600">&times;</button>
                                                    <div class="font-bold text-lg text-accent-1 dark:text-accent-1-hover step-number"><?php echo $index + 1; ?></div>
                                                    <div class="step-name text-sm mt-1"><?php echo htmlspecialchars($stepName); ?></div>
                                                    <?php if ($isInjected): ?>
                                                        <div class="mt-2 text-xs font-semibold text-primary dark:text-blue-400">Chosen by Guest</div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Add New Step UI -->
                                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                    <label for="department-select" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Add New Step</label>
                                    <div class="mt-1 flex rounded-md shadow-sm">
                                        <select id="department-select" class="block w-full rounded-none rounded-l-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-accent-1 focus:ring-accent-1 sm:text-sm">
                                            <option disabled selected>Choose a department...</option>
                                            <?php foreach ($departments as $department): ?>
                                                <option value="<?php echo htmlspecialchars($department['name']); ?>"><?php echo htmlspecialchars($department['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" id="add-step-btn" class="relative -ml-px inline-flex items-center space-x-2 rounded-r-md border border-gray-300 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:border-accent-1 focus:outline-none focus:ring-1 focus:ring-accent-1 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-accent-2 dark:border-gray-600">
                                            <span>Add</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-6 flex flex-wrap gap-4">
                                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-accent-1 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-accent-1-hover active:bg-accent-1-active focus:outline-none focus:border-accent-1-border-active focus:ring focus:ring-accent-1-light disabled:opacity-25 transition">
                                        Accept & Finalize Route
                                    </button>
                                    <button type="button" onclick="openDeclineModal()" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 active:bg-red-700 focus:outline-none focus:border-red-700 focus:ring focus:ring-red-200 disabled:opacity-25 transition">
                                        Decline Document
                                    </button>
                                </div>
                            </form>

                        <?php else: ?>
                            <div class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-red-800 dark:text-red-200">Management Unauthorized</h3>
                                        <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                                            <p>This document is already <?php echo $document['status']; ?> and the route cannot be modified.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .sortable-ghost {
        opacity: 0.4;
        background: #a5b4fc;
    }
    .sortable-chosen {
        cursor: grabbing;
    }
</style>

<!-- Decline Modal -->
<div id="declineModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75" aria-hidden="true" onclick="closeDeclineModal()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl dark:bg-gray-800 sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form action="/documents/decline" method="POST" id="decline-form">
                <input type="hidden" name="pin" id="decline-pin-input">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <div class="px-4 pt-5 pb-4 bg-white dark:bg-gray-800 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 mx-auto bg-red-100 rounded-full dark:bg-red-900 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100" id="modal-title">Decline Document</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 dark:text-gray-400">Please provide a reason for declining this document. This action cannot be undone.</p>
                                <input type="hidden" name="document_id" value="<?php echo $document['id']; ?>">
                                <textarea name="reason" rows="3" class="w-full mt-3 border-gray-300 rounded-md shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 focus:border-accent-1 focus:ring-accent-1 sm:text-sm" placeholder="Reason for decline..." required></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="inline-flex justify-center w-full px-4 py-2 text-base font-medium text-white bg-red-600 border border-transparent rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Decline Document
                    </button>
                    <button type="button" onclick="closeDeclineModal()" class="inline-flex justify-center w-full px-4 py-2 mt-3 text-base font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-accent-1 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="/js/sortable.min.js"></script>
<script>
    function openDeclineModal() {
        document.getElementById('declineModal').classList.remove('hidden');
    }
    function closeDeclineModal() {
        document.getElementById('declineModal').classList.add('hidden');
    }

    // Named so both DOMContentLoaded (hard load) and dts:page-loaded (PJAX swap)
    // can trigger re-binding of Sortable and form submit handlers.
    function initManageDocumentsPage() {
        const routeList = document.getElementById('route-list');
        const routeForm = document.getElementById('route-form');
        const hiddenInput = document.getElementById('final_route');
        const addStepBtn = document.getElementById('add-step-btn');
        const departmentSelect = document.getElementById('department-select');

        if (routeList) {
            new window.Sortable(routeList, {
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                onUpdate: updateStepNumbers
            });

            function updateStepNumbers() {
                const steps = routeList.querySelectorAll('.route-step');
                steps.forEach((step, index) => {
                    step.querySelector('.step-number').textContent = index + 1;
                });
            }

            function createStepElement(departmentName) {
                const newStep = document.createElement('div');
                newStep.className = 'route-step flex-shrink-0 w-40 p-4 bg-white dark:bg-gray-700 rounded-lg shadow-md cursor-move text-center relative mr-4 last:mr-0 select-none';
                newStep.innerHTML = `
                    <button type="button" class="delete-step-btn w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center text-xl absolute -top-1 -right-1 hover:bg-red-600">&times;</button>
                    <div class="font-bold text-lg text-accent-1 dark:text-accent-1-hover step-number"></div>
                    <div class="step-name text-sm mt-1">${departmentName}</div>
                `;
                return newStep;
            }

            addStepBtn.addEventListener('click', function() {
                const selectedDepartment = departmentSelect.value;
                if (!selectedDepartment || departmentSelect.selectedIndex === 0) {
                    return;
                }
                const newStep = createStepElement(selectedDepartment);
                routeList.appendChild(newStep);
                updateStepNumbers();
                departmentSelect.selectedIndex = 0;
            });

            // Event delegation for delete buttons (avoids re-binding on dynamically created items)
            routeList.addEventListener('click', function(e) {
                if (e.target && e.target.classList.contains('delete-step-btn')) {
                    e.target.closest('.route-step').remove();
                    updateStepNumbers();
                }
            });

            routeForm.addEventListener('submit', function(e) {
                if (document.getElementById('finalize-pin-input').value !== '') {
                    return true;
                }
                e.preventDefault();
                const finalRouteOrder = Array.from(routeList.querySelectorAll('.step-name')).map(el => el.textContent.trim());
                if (finalRouteOrder.length === 0) {
                    alert("Please add at least one step to the route.");
                    return;
                }
                hiddenInput.value = JSON.stringify(finalRouteOrder);
                window.SigningModal.show(`Enter your Security PIN to finalize the route for: <?php echo htmlspecialchars($document['tracking_code']); ?>`, function(pin) {
                    document.getElementById('finalize-pin-input').value = pin;
                    routeForm.submit();
                });
            });
        }

        const declineForm = document.getElementById('decline-form');
        if (declineForm) {
            declineForm.addEventListener('submit', function(e) {
                if (document.getElementById('decline-pin-input').value !== '') {
                    return true;
                }
                e.preventDefault();
                window.SigningModal.show(`Enter your Security PIN to cryptographically sign declining this document.`, function(pin) {
                    document.getElementById('decline-pin-input').value = pin;
                    declineForm.submit();
                });
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initManageDocumentsPage);
    } else {
        initManageDocumentsPage();
    }
</script>

<?php require BASE_PATH . '/src/Views/partials/signing-modal.php'; ?>

<?php $content = ob_get_clean(); ?>

<?php require BASE_PATH . '/src/Views/layouts/app.php'; ?>
