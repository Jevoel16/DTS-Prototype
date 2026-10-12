// Forms with class="sign-action": ask for the Security PIN via SigningModal before submitting
(function () {
    // Named so both DOMContentLoaded and dts:page-loaded (PJAX swap) trigger re-binding
    function initSystemOverviewPage() {
        document.querySelectorAll('form.sign-action').forEach(form => {
            form.addEventListener('submit', function(e) {
                const pinInput = this.querySelector('.sign-pin-input');
                if (pinInput && !pinInput.value) {
                    e.preventDefault();
                    const msg = this.dataset.message || "Enter your Security PIN to sign this action.";
                    window.SigningModal.show(msg, function(pin) {
                        pinInput.value = pin;
                        form.submit();
                    });
                }
            });
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSystemOverviewPage);
    } else {
        initSystemOverviewPage();
    }
})();
