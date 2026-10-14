// Backup manager (admin): PIN confirmation modal for restoring or deleting a backup.
// Re-executed on every PJAX visit; #pjax-content survives swaps, so the listener is attached once
// and looks up the (re-rendered) modal elements on each click.
(function () {
    const root = document.getElementById('pjax-content') || document.body;
    if (root.dataset.backupsBound) return;
    root.dataset.backupsBound = '1';

    let lastTrigger = null;

    function closeModal(modal) {
        modal.style.display = 'none';
        if (lastTrigger) lastTrigger.focus();
    }

    // Backup names end in _<document count>.sql.zip
    function documentCountOf(fileName) {
        const match = /_(\d+)\.sql\.zip$/.exec(fileName);
        return match ? parseInt(match[1], 10) : null;
    }

    function openModal(modal, action, fileName) {
        const isRestore = action === 'restore';
        document.getElementById('restore-title').textContent = isRestore ? 'FINAL WARNING: Restore Database?' : 'Delete Backup?';
        document.getElementById('restore-verb').textContent = isRestore ? 'Restore' : 'Permanently delete';
        document.getElementById('restore-file-name').textContent = fileName;
        document.getElementById('restore-submit-btn').textContent = isRestore ? 'Restore Now' : 'Delete Backup';
        document.getElementById('restore-form').action = '/system/backups/' + action + '/' + encodeURIComponent(fileName);

        const impact = document.getElementById('restore-impact');
        if (isRestore) {
            const current = parseInt(modal.dataset.currentDocuments || '0', 10);
            const inBackup = documentCountOf(fileName);
            let text = 'Every table is replaced with the backup. The current database is saved as a "pre-restore" backup first.';
            if (inBackup !== null) {
                text += ' The backup has ' + inBackup + ' document(s); the system currently has ' + current + '.';
                if (current > inBackup) {
                    text += ' At least ' + (current - inBackup) + ' newer document(s) will no longer appear, and their tracking codes will stop working.';
                }
            }
            impact.textContent = text;
        } else {
            impact.textContent = 'This cannot be undone. Keep a copy elsewhere if this backup might still be needed.';
        }

        modal.style.display = 'flex';
        document.getElementById('restore-pin').value = '';
        document.getElementById('restore-pin').focus();
    }

    root.addEventListener('click', function (e) {
        const modal = document.getElementById('restore-modal');
        if (!modal) return;

        const actionBtn = e.target.closest('[data-backup-action]');
        if (actionBtn) {
            lastTrigger = actionBtn;
            openModal(modal, actionBtn.dataset.backupAction, actionBtn.dataset.backupFile);
            return;
        }

        if (e.target.closest('#restore-cancel-btn') || e.target === modal) {
            closeModal(modal);
        }
    });

    document.addEventListener('keydown', function (e) {
        const modal = document.getElementById('restore-modal');
        if (e.key === 'Escape' && modal && modal.style.display !== 'none') {
            closeModal(modal);
        }
    });
})();
