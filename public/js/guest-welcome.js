// Guest submission page: purpose requirements, phone input, track-by-code and QR scanner
document.addEventListener('DOMContentLoaded', function () {
    const purposeSelect = document.getElementById('purpose-select');
    const otherPurposeInput = document.getElementById('other-purpose-input');
    const otherPurposeTextField = document.getElementById('other_purpose_text');
    const requirementsSection = document.getElementById('requirements-section');
    const requirementsList = document.getElementById('requirements-list');

    // Dynamically reveal or hide the "Requirements" section based on the user's selected purpose dropdown.
    function updatePurposeFields() {
        const selectedOptionValue = purposeSelect.value;
        const selectedOption = purposeSelect.options[purposeSelect.selectedIndex];

        if (selectedOptionValue === '0') {
            otherPurposeInput.classList.remove('hidden');
            otherPurposeTextField.setAttribute('required', 'required');
            requirementsSection.classList.add('hidden');
            requirementsList.innerHTML = '';
        } else if (selectedOptionValue) {
            otherPurposeInput.classList.add('hidden');
            otherPurposeTextField.removeAttribute('required');
            otherPurposeTextField.value = '';

            const requirements = selectedOption.dataset.requirements ? JSON.parse(selectedOption.dataset.requirements) : [];
            requirementsList.innerHTML = '';

            if (requirements.length > 0) {
                requirements.forEach(req => {
                    const li = document.createElement('li');
                    li.textContent = req;
                    li.className = 'bg-gray-100 dark:bg-gray-700 px-4 py-2 rounded-md text-sm';
                    requirementsList.appendChild(li);
                });
                requirementsSection.classList.remove('hidden');
            } else {
                requirementsSection.classList.add('hidden');
            }
        } else {
            otherPurposeInput.classList.add('hidden');
            requirementsSection.classList.add('hidden');
        }
    }

    if (purposeSelect) {
        updatePurposeFields();
        purposeSelect.addEventListener('change', updatePurposeFields);
    }

    // Numeric-only restriction for Phone Number
    const phoneInput = document.getElementById('guest_phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    }

    // Track Logic
    const trackForm = document.getElementById('track-document-form');
    const trackingCodeInput = document.getElementById('tracking_code_input');
    const trackErrorMessage = document.getElementById('track-error-message');

    function displayError(message) {
        if(trackErrorMessage) {
            trackErrorMessage.textContent = message;
            trackErrorMessage.classList.remove('hidden');
        }
    }

    function clearError() {
        if(trackErrorMessage) {
            trackErrorMessage.classList.add('hidden');
            trackErrorMessage.textContent = '';
        }
    }

    if(trackingCodeInput) {
        trackingCodeInput.addEventListener('input', clearError);
    }

    function trackAndRedirect(trackingCode) {
        clearError();
        if (!trackingCode) {
            displayError('Please enter a tracking code.');
            return;
        }
        window.location.href = `/track?codes=${encodeURIComponent(trackingCode)}`;
    }

    if (trackForm) {
        trackForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const trackingCode = trackingCodeInput.value.trim();
            trackAndRedirect(trackingCode);
        });
    }

    // QR Scanner Logic
    const scanQrButton = document.getElementById('scan-qr-button');
    const qrScannerModal = document.getElementById('qr-scanner-modal');
    const closeQrModal = document.getElementById('close-qr-modal');
    const trackModal = document.getElementById('track-modal');
    let html5QrCode = null;

    function onScanSuccess(decodedText, decodedResult) {
        stopQrCodeScanner();
        trackAndRedirect(decodedText);
    }

    function onScanError(errorMessage) {
        // Ignore minor errors while scanning
    }

    function startQrCodeScanner() {
        trackModal.classList.add('hidden');
        qrScannerModal.style.display = 'block';

        if (!html5QrCode && window.Html5Qrcode) {
            html5QrCode = new window.Html5Qrcode("qr-reader");
            html5QrCode.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: { width: 250, height: 250 } },
                onScanSuccess,
                onScanError
            ).catch(err => {
                console.error("Could not start QR scanner.", err);
                alert("Could not start QR scanner. Please ensure you are using HTTPS and grant camera permissions.");
                stopQrCodeScanner();
            });
        } else if (!window.Html5Qrcode) {
            alert("QR Scanner library not loaded.");
        }
    }

    function stopQrCodeScanner() {
        if (html5QrCode && html5QrCode.isScanning) {
            html5QrCode.stop().catch(err => {
                console.error("Error stopping the QR scanner.", err);
            });
        }
        if (qrScannerModal) {
            qrScannerModal.style.display = 'none';
        }
    }

    if (scanQrButton) {
        scanQrButton.addEventListener('click', startQrCodeScanner);
    }
    if (closeQrModal) {
        closeQrModal.addEventListener('click', stopQrCodeScanner);
    }

    window.addEventListener('click', function(event) {
        if (event.target == qrScannerModal) {
            stopQrCodeScanner();
        }
    });
});
