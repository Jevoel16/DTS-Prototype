<?php $title = 'Edit User'; ob_start(); ?>
<h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
    Edit User
</h2>
<?php $header = ob_get_clean(); ?>

<?php ob_start(); ?>
<div class="py-10">
    <div class="mx-[20vh] sm:px-6 lg:px-8 space-y-6">
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline"><?= htmlspecialchars($_SESSION['error']) ?></span>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <form action="/users/<?= $user['id'] ?>/update" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Full Name *</label>
                            <input type="text" name="name" id="name" value="<?= htmlspecialchars($user['name']) ?>" required class="mt-1 block w-full border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-md shadow-sm focus:border-accent-1 focus:ring-accent-1 sm:text-sm p-2 border">
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Username / Email *</label>
                            <input type="text" name="email" id="email" value="<?= htmlspecialchars($user['email']) ?>" required class="mt-1 block w-full border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-md shadow-sm focus:border-accent-1 focus:ring-accent-1 sm:text-sm p-2 border">
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Password (Leave blank to keep current)</label>
                            <input type="password" name="password" id="password" class="mt-1 block w-full border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-md shadow-sm focus:border-accent-1 focus:ring-accent-1 sm:text-sm p-2 border">
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Confirm Password (Leave blank to keep current)</label>
                            <input type="password" name="password_confirmation" id="password_confirmation" class="mt-1 block w-full border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-md shadow-sm focus:border-accent-1 focus:ring-accent-1 sm:text-sm p-2 border">
                        </div>

                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700 dark:text-gray-300">System Role *</label>
                            <select name="role" id="role" required class="mt-1 block w-full border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-md shadow-sm focus:border-accent-1 focus:ring-accent-1 sm:text-sm p-2 border">
                                <option value="staff" <?= $user['role'] === 'staff' ? 'selected' : '' ?>>Staff (Department Account)</option>
                                <option value="officer" <?= $user['role'] === 'officer' ? 'selected' : '' ?>>Records Officer (Creates and releases documents)</option>
                                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin (System Configuration)</option>
                            </select>
                        </div>

                    </div>
                    
                    <div class="mt-6 flex items-center justify-end">
                        <a href="/users" class="mr-4 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">Cancel</a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-accent-1 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-accent-1-hover focus:outline-none focus:border-accent-1-border-active focus:ring focus:ring-accent-1-light transition">
                            Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); ?>

<?php require BASE_PATH . '/src/Views/layouts/app.php'; ?>
