<?php $title = 'Change Password'; ob_start(); ?>
<h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
    Change Password
</h2>
<?php $header = ob_get_clean(); ?>

<?php ob_start(); ?>
<div class="py-6 sm:py-10">
    <div class="px-4 sm:px-6 lg:px-8 xl:mx-[20vh] space-y-6">

        <?php if ($forced): ?>
            <div class="bg-yellow-50 border border-yellow-400 text-yellow-800 dark:bg-yellow-900/30 dark:border-yellow-700/50 dark:text-yellow-200 px-4 py-3 rounded" role="alert">
                Your account is using a temporary or default password. Please choose a new password to continue.
            </div>
        <?php endif; ?>

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg max-w-xl">
            <div class="p-4 sm:p-6 text-gray-900 dark:text-gray-100">
                <form action="/account/password" method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

                    <div>
                        <label for="current_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Current password</label>
                        <input type="password" name="current_password" id="current_password" required autocomplete="current-password" class="mt-1 block w-full border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-md shadow-sm focus:border-accent-1 focus:ring-accent-1 sm:text-sm p-2 border">
                    </div>

                    <div>
                        <label for="new_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">New password (at least <?= (int) $minLength ?> characters)</label>
                        <input type="password" name="new_password" id="new_password" required minlength="<?= (int) $minLength ?>" autocomplete="new-password" class="mt-1 block w-full border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-md shadow-sm focus:border-accent-1 focus:ring-accent-1 sm:text-sm p-2 border">
                    </div>

                    <div>
                        <label for="new_password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Confirm new password</label>
                        <input type="password" name="new_password_confirmation" id="new_password_confirmation" required minlength="<?= (int) $minLength ?>" autocomplete="new-password" class="mt-1 block w-full border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-md shadow-sm focus:border-accent-1 focus:ring-accent-1 sm:text-sm p-2 border">
                    </div>

                    <p class="text-sm text-gray-500 dark:text-gray-400">This is your sign-in password. Your signing PIN is separate and does not change.</p>

                    <div class="flex items-center justify-end">
                        <?php if (!$forced): ?>
                            <a href="/dashboard" class="mr-4 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">Cancel</a>
                        <?php endif; ?>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-accent-1 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-accent-1-hover focus:outline-none focus:ring focus:ring-accent-1-light transition">
                            Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); ?>

<?php require BASE_PATH . '/src/Views/layouts/app.php'; ?>
