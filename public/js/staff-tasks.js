// Tasks page (staff/officer): PIN-signed receive (scan) and complete actions
(function () {
    // Named so both DOMContentLoaded (hard load) and dts:page-loaded (PJAX swap)
    // can trigger re-binding of complete buttons and scan form.
    function initStaffTasksPage() {
        const completeBtns = document.querySelectorAll('.complete-btn');
        completeBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const form = this.closest('.complete-form');
                const trackingCode = this.dataset.trackingCode;
                window.SigningModal.show(`Enter your Security PIN to cryptographically sign the completion of document: ${trackingCode}`, function(pin) {
                    form.querySelector('.complete-pin-input').value = pin;
                    form.submit();
                });
            });
        });

        const scanForm = document.getElementById('scan-form');
        if (scanForm) {
            scanForm.addEventListener('submit', function(e) {
                if (!scanForm.querySelector('.scan-pin-input')) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'pin';
                    input.className = 'scan-pin-input';
                    scanForm.appendChild(input);
                }

                const pinInput = scanForm.querySelector('.scan-pin-input');
                if (!pinInput.value) {
                    e.preventDefault();
                    const trackingCode = scanForm.elements['tracking_code'].value.trim();
                    if (trackingCode) {
                        window.SigningModal.show(`Enter your Security PIN to cryptographically sign the receipt of document: ${trackingCode}`, function(pin) {
                            pinInput.value = pin;
                            scanForm.submit();
                        });
                    }
                }
            });
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initStaffTasksPage);
    } else {
        initStaffTasksPage();
    }
})();
