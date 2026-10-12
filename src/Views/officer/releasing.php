<?php $title = 'Document Releasing'; ob_start(); ?>
<h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
    Document Releasing
</h2>
<?php $header = ob_get_clean(); ?>

<?php ob_start(); ?>
<div class="py-6 sm:py-10">
    <div class="px-4 sm:px-6 lg:px-8 xl:mx-[20vh] space-y-6">

        <!-- Receive Document Section -->
        <?php
            $finderTitle = 'Receive Document for Releasing';
            $finderDescription = "Scan or enter the tracking code of a completed document to receive it at the Records Unit.";
            $finderActionUrl = '/documents/scan';
            $finderFormId = 'scan-form';
            $finderButtonText = 'Receive';
            require BASE_PATH . '/src/Views/components/qr-scanner.php';
        ?>
        
        <?php 
            $panelTitle = 'Documents Ready for Release';
            
            $purposeOptions = [];
            foreach ($allPurposes as $p) {
                $purposeOptions[$p['name']] = $p['name'];
            }

            $filterConfig = [
                'date' => ['type' => 'date', 'label' => 'Date'],
                'purpose' => [
                    'type' => 'select',
                    'label' => 'Purpose',
                    'options' => array_merge(['all' => 'All Purposes'], $purposeOptions)
                ],
                'submitter' => ['type' => 'text', 'label' => 'Submitter Name', 'placeholder' => 'Search submitter...'],
                'search' => ['type' => 'text', 'label' => 'Search Tracking # / Title', 'placeholder' => 'Search...']
            ];

            foreach ($documents as &$doc) {
                $doc['status_html'] = '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-success-light text-success-active capitalize">Ready for Release</span>';
                $doc['action_html'] = sprintf('
                    <form class="complete-form inline-block" action="/releasing/%s/complete" method="POST">
                        <input type="hidden" name="csrf_token" value="' . ($_SESSION['csrf_token'] ?? '') . '">
                        <input type="hidden" name="pin" class="complete-pin-input">
                        <button type="button" class="complete-btn px-4 py-2 bg-success text-white rounded hover:bg-success-hover transition font-semibold" data-tracking-code="%s">
                            Release
                        </button>
                    </form>
                ', $doc['id'], htmlspecialchars($doc['tracking_code']));
            }
            unset($doc);

            $tableConfig = [
                'wrapper_classes' => 'overflow-x-auto mt-4',
                'columns' => [
                    ['key' => 'tracking_code', 'label' => 'Tracking Code', 'width' => 'w-[15%]', 'type' => 'tracking_link'],
                    ['key' => 'title', 'label' => 'Document Title', 'width' => 'w-[25%]', 'wrap' => true],
                    ['key' => 'guest_name', 'label' => 'Submitter', 'width' => 'w-[15%]', 'wrap' => true],
                    ['key' => 'purpose_name', 'label' => 'Purpose', 'width' => 'w-[20%]', 'wrap' => true],
                    ['key' => 'status_html', 'label' => 'Status', 'width' => 'w-[12%]', 'type' => 'raw'],
                    ['key' => 'action_html', 'label' => 'Action', 'width' => 'w-[13%]', 'type' => 'raw']
                ],
                'data' => $documents,
                'empty_message' => 'No documents are currently ready for release.'
            ];

            require BASE_PATH . '/src/Views/components/data-panel.php';
        ?>
    </div>
</div>

<?php require BASE_PATH . '/src/Views/partials/scan-qr-modal.php'; ?>
<?php require BASE_PATH . '/src/Views/partials/signing-modal.php'; ?>

<script>
// Named function so both DOMContentLoaded (hard load) and dts:page-loaded
// (PJAX swap) can trigger re-binding after navigation.
function initReleasingPage() {
    const completeBtns = document.querySelectorAll('.complete-btn');
    completeBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const form = this.closest('.complete-form');
            const trackingCode = this.dataset.trackingCode;
            window.SigningModal.show(`Enter your Security PIN to cryptographically sign the release of document: ${trackingCode}`, function(pin) {
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
    document.addEventListener('DOMContentLoaded', initReleasingPage);
} else {
    initReleasingPage();
}
</script>

<?php $content = ob_get_clean(); ?>

<?php require BASE_PATH . '/src/Views/layouts/app.php'; ?>
