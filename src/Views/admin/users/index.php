<?php $title = 'User Management'; ob_start(); ?>
<h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
    User Management
</h2>
<?php $header = ob_get_clean(); ?>

<?php ob_start(); ?>
<div class="py-6 sm:py-10">
    <div class="px-4 sm:px-6 lg:px-8 xl:mx-[20vh] space-y-6">
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-success-light border border-green-400 text-green-700 dark:bg-green-900/30 dark:border-green-700/50 dark:text-green-300 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline"><?= htmlspecialchars($_SESSION['success']) ?></span>
                <?php unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 dark:bg-red-900/30 dark:border-red-700/50 dark:text-red-300 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline"><?= htmlspecialchars($_SESSION['error']) ?></span>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg" id="users-section">
            <div class="p-4 sm:p-6 text-gray-900 dark:text-gray-100">
                <div class="flex flex-wrap justify-between items-center gap-3 mb-6">
                    <h3 class="text-xl sm:text-2xl font-bold">All User Accounts</h3>
                    <a href="/users/create" class="inline-flex items-center px-4 py-2 bg-accent-1 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-accent-1-hover active:bg-accent-1-active focus:outline-none focus:border-accent-1-border-active focus:ring focus:ring-accent-1-light disabled:opacity-25 transition">
                        Create User
                    </a>
                </div>
                
                <?php 
                    $filterConfig = [
                        'search' => ['type' => 'text', 'label' => 'Search by Name or Email', 'placeholder' => 'Search...'],
                        'role' => [
                            'type' => 'select', 
                            'label' => 'Role', 
                            'options' => [
                                'all' => 'All Roles',
                                'admin' => 'Admin',
                                'officer' => 'Officer',
                                'staff' => 'Staff'
                            ]
                        ]
                    ];
                    require BASE_PATH . '/src/Views/components/table-filters.php'; 
                ?>

                <div id="users-table-body">
                    <?php
                        foreach ($users as &$user) {
                            $roleColor = $user['role'] === 'admin' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' : ($user['role'] === 'officer' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300' : 'bg-success-light text-success-active dark:bg-green-900/30 dark:text-green-300');
                            $user['role_html'] = sprintf('<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full %s uppercase">%s</span>', $roleColor, htmlspecialchars($user['role']));
                            
                            $user['account_html'] = sprintf('
                                <div class="flex items-center space-x-3">
                                    <button data-copy-text="%s" class="text-gray-400 hover:text-accent-1 transition-colors focus:outline-none" title="Copy Email">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="copy-icon h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="check-icon h-4 w-4 text-green-500" style="display: none;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </button>
                                    <div class="flex flex-col">
                                        <span class="font-medium text-gray-900 dark:text-gray-100">%s</span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400 break-all">%s</span>
                                    </div>
                                </div>
                            ', htmlspecialchars($user['email']), htmlspecialchars($user['name']), htmlspecialchars($user['email']));
                            
                            if (!empty($user['public_key'])) {
                                $user['signature_html'] = sprintf('
                                    <div class="flex items-center text-green-600">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        <span>Active</span>
                                        <form action="/users/%s/reset-signature" method="POST" class="inline-block ml-2 confirm-action" data-message="Reset signature for %s?">
                                            <input type="hidden" name="csrf_token" value="' . ($_SESSION['csrf_token'] ?? '') . '">
                                            <button type="submit" class="text-xs text-red-500 hover:text-red-700 underline">Reset</button>
                                        </form>
                                    </div>
                                ', $user['id'], htmlspecialchars($user['name']));
                            } else {
                                $user['signature_html'] = '<span class="text-gray-400 italic text-xs">Not set</span>';
                            }

                            // Align action links to the left to maintain visual alignment with the column header
                            $actions = sprintf('<a href="/users/%s/edit" class="text-accent-1 hover:text-accent-1-active">Edit</a>', $user['id']);
                            if ($_SESSION['user_id'] != $user['id']) {
                                $actions .= sprintf('
                                    <form action="/users/%s/delete" method="POST" class="inline-block ml-2 confirm-action" data-message="Are you sure you want to delete this user?">
                                        <input type="hidden" name="csrf_token" value="' . ($_SESSION['csrf_token'] ?? '') . '">
                                        <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                    </form>
                                ', $user['id']);
                            }
                            $user['actions_html'] = '<div class="space-x-2 text-left">' . $actions . '</div>';
                        }
                        unset($user);

                        $tableConfig = [
                            'wrapper_classes' => 'overflow-x-auto mt-4',
                            'columns' => [
                                ['key' => 'account_html', 'label' => 'Account', 'width' => 'w-[52%]', 'type' => 'raw'],
                                ['key' => 'role_html', 'label' => 'Role', 'width' => 'w-1/12', 'type' => 'raw'],
                                ['key' => 'signature_html', 'label' => 'Signature', 'width' => 'w-2/12', 'type' => 'raw'],
                                ['key' => 'created_at', 'label' => 'Registered', 'width' => 'w-[6%]', 'type' => 'date'],
                                ['key' => 'actions_html', 'label' => 'Actions', 'width' => 'w-2/12', 'type' => 'raw']
                            ],
                            'data' => $users,
                            'empty_message' => 'No users match your criteria.'
                        ];
                        require BASE_PATH . '/src/Views/components/table.php';
                    ?>

                    <div class="mt-4">
                        <?php if (isset($paginator)) require BASE_PATH . '/src/Views/components/pagination.php'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); ?>

<?php require BASE_PATH . '/src/Views/layouts/app.php'; ?>
