// Intake route builder (officer): sortable route steps, PIN-signed finalize and decline
(function () {
    // Runs on hard load and again after each PJAX swap (the router re-executes this file)
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
                    <div class="step-name text-sm mt-1"></div>
                `;
                // Department names are data, so set them as text rather than HTML
                newStep.querySelector('.step-name').textContent = departmentName;
                return newStep;
            }

            addStepBtn.addEventListener('click', function () {
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
            routeList.addEventListener('click', function (e) {
                if (e.target && e.target.classList.contains('delete-step-btn')) {
                    e.target.closest('.route-step').remove();
                    updateStepNumbers();
                }
            });

            routeForm.addEventListener('submit', function (e) {
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
                const trackingCode = routeForm.dataset.trackingCode || '';
                window.SigningModal.show(`Enter your Security PIN to finalize the route for: ${trackingCode}`, function (pin) {
                    document.getElementById('finalize-pin-input').value = pin;
                    routeForm.submit();
                });
            });
        }

        const declineForm = document.getElementById('decline-form');
        if (declineForm) {
            declineForm.addEventListener('submit', function (e) {
                if (document.getElementById('decline-pin-input').value !== '') {
                    return true;
                }
                e.preventDefault();
                window.SigningModal.show(`Enter your Security PIN to cryptographically sign declining this document.`, function (pin) {
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
})();
