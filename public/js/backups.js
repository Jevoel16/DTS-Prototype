// Backup manager (admin): restore confirmation modal.
// Re-executed on every PJAX visit; #pjax-content survives swaps, so the listener is attached once
// and looks up the (re-rendered) modal elements on each click.
(function () {
    const root = document.getElementById('pjax-content') || document.body;
    if (root.dataset.backupsBound) return;
    root.dataset.backupsBound = '1';

    root.addEventListener('click', function (e) {
        const modal = document.getElementById('restore-modal');
        if (!modal) return;

        const restoreBtn = e.target.closest('[data-restore-file]');
        if (restoreBtn) {
            const fileName = restoreBtn.dataset.restoreFile;
            document.getElementById('restore-file-name').textContent = fileName;
            document.getElementById('restore-form').action = '/system/backups/restore/' + encodeURIComponent(fileName);
            modal.style.display = 'flex';
            return;
        }

        if (e.target.closest('#restore-cancel-btn')) {
            modal.style.display = 'none';
        }
    });
})();
