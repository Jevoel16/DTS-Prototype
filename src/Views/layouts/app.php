<?php
$unreadNotifications = [];
$sessionAlerts = [];

if (isset($_SESSION['success'])) {
    $sessionAlerts[] = ['type' => 'success', 'title' => 'Success', 'message' => $_SESSION['success'], 'is_toast' => true];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $sessionAlerts[] = ['type' => 'error', 'title' => 'Error', 'message' => $_SESSION['error'], 'is_toast' => true];
    unset($_SESSION['error']);
}
if (isset($_SESSION['info'])) {
    $sessionAlerts[] = ['type' => 'info', 'title' => 'Information', 'message' => $_SESSION['info'], 'is_toast' => true];
    unset($_SESSION['info']);
}

if (isset($_SESSION['user_id'])) {
    $notifService = new \App\Core\NotificationService();
    
    // Convert flash alerts into persistent notifications for this user
    foreach ($sessionAlerts as $alert) {
        $notifService->notifyUser($_SESSION['user_id'], $alert['title'], $alert['message'], $alert['type']);
    }
    
    $unreadNotifications = $notifService->getUnreadForCurrentUser();
}
?>
<!DOCTYPE html>
<html lang="en" class="light overflow-y-scroll">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?? '' ?>">
    <title><?= htmlspecialchars($title ?? 'DTS', ENT_QUOTES, 'UTF-8') ?> — DepEd DTS</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/images/logoipsum-411.png">
    <link rel="apple-touch-icon" href="/images/logoipsum-411.png">

    <!-- Local Tailwind CSS -->
    <link href="/css/tailwind.css" rel="stylesheet">
    <script src="/js/theme-init.js"></script>
    <style>
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .animate-slide-in-right {
            animation: slideInRight 0.3s ease-out forwards;
        }
    </style>
    
    <!-- Chart.js is loaded once here (not per page) so PJAX page swaps reuse it -->
    <script src="/js/chart.min.js"></script>
    <script src="/js/chart-theme.js"></script>
</head>
<body class="font-sans antialiased bg-gray-100 dark:bg-darkBg text-gray-900 dark:text-gray-100">

    <div class="min-h-screen bg-gray-100 dark:bg-darkBg">
        <!-- Navigation Bar -->
        <nav class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
            <?php
            // Role-based nav links, rendered both in the desktop bar and the mobile menu
            $navLinks = [];
            $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            if (isset($_SESSION['role'])) {
                if ($_SESSION['role'] === 'admin') {
                    $navLinks = [
                        ['/admin-dashboard', 'Admin Dashboard', $currentPath === '/admin-dashboard'],
                        ['/all-documents', 'All Documents', $currentPath === '/all-documents'],
                        ['/system-overview', 'System Overview', $currentPath === '/system-overview'],
                        ['/users', 'Users', strpos($currentPath, '/users') === 0],
                    ];
                } elseif ($_SESSION['role'] === 'officer') {
                    $navLinks = [
                        ['/intake', 'Dashboard', $currentPath === '/intake'],
                        ['/tasks', 'Tasks', $currentPath === '/tasks'],
                        ['/tasks/completed', 'Completed', $currentPath === '/tasks/completed'],
                        ['/releasing', 'Releasing', $currentPath === '/releasing'],
                        ['/statistics', 'Statistics', $currentPath === '/statistics'],
                    ];
                } elseif ($_SESSION['role'] === 'staff') {
                    $navLinks = [
                        ['/tasks', 'Tasks', $currentPath === '/tasks'],
                        ['/tasks/completed', 'Completed', $currentPath === '/tasks/completed'],
                        ['/statistics', 'Statistics', $currentPath === '/statistics'],
                    ];
                }
            }
            $activeClass = "inline-flex items-center px-1 pt-1 border-b-2 border-accent-1 text-sm font-medium leading-5 text-gray-900 dark:text-gray-100 focus:outline-none focus:border-accent-1-active transition duration-150 ease-in-out";
            $inactiveClass = "inline-flex items-center px-1 pt-1 border-b-2 border-transparent hover:border-accent-1-hover text-sm font-medium leading-5 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none focus:text-gray-700 dark:focus:text-gray-300 focus:border-gray-300 dark:focus:border-gray-600 transition duration-150 ease-in-out";
            $mobileActiveClass = "block pl-3 pr-4 py-2 border-l-4 border-accent-1 text-base font-medium text-gray-900 dark:text-gray-100 bg-gray-50 dark:bg-gray-700 focus:outline-none transition duration-150 ease-in-out";
            $mobileInactiveClass = "block pl-3 pr-4 py-2 border-l-4 border-transparent text-base font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 hover:border-gray-300 dark:hover:border-gray-600 focus:outline-none transition duration-150 ease-in-out";
            ?>
            <div class="px-4 sm:px-6 lg:px-8 xl:mx-[20vh]">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="shrink-0 flex items-center">
                            <a href="/dashboard">
                                <img src="/images/logoipsum-411.png" alt="DTS Logo" class="h-8 w-auto dark:brightness-200">
                            </a>
                        </div>

                        <!-- Navigation Links based on Role (desktop) -->
                        <div id="pjax-nav-links" class="hidden space-x-8 lg:-my-px lg:ml-10 lg:flex">
                            <?php foreach ($navLinks as [$href, $label, $isActive]): ?>
                                <a href="<?= $href ?>" class="<?= $isActive ? $activeClass : $inactiveClass ?>"><?= $label ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="flex items-center">
                        <button type="button" id="theme-toggle" aria-label="Toggle dark mode" class="mr-2 sm:mr-4 p-2 bg-gray-100 dark:bg-gray-700 rounded-full shadow-md text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none focus:ring-2 focus:ring-accent-1-hover transition-colors">
                            <!-- Sun icon for light mode -->
                            <svg id="theme-toggle-light-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" fill-rule="evenodd" clip-rule="evenodd"></path></svg>
                            <!-- Moon icon for dark mode -->
                            <svg id="theme-toggle-dark-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                        </button>

                        <!-- Notification Dropdown -->
                        <div class="sm:relative mr-2 sm:mr-4">
                            <button type="button" id="notification-menu-button" class="relative p-2 bg-gray-100 dark:bg-gray-700 rounded-full shadow-md text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none focus:ring-2 focus:ring-accent-1-hover transition-colors">
                                <span class="sr-only">Notifications</span>
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                            </button>
                            <!-- On phones the panel spans the screen under the nav bar; from sm up it drops down from the bell -->
                            <div id="notification-dropdown-menu" class="hidden fixed inset-x-2 top-16 sm:absolute sm:inset-x-auto sm:top-auto sm:right-0 z-50 mt-2 sm:w-96 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 focus:outline-none py-2 max-h-[80vh] overflow-y-auto">
                                <div class="px-4 py-2 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Notifications</h3>
                                    <?php if (count($unreadNotifications) > 0): ?>
                                    <button id="mark-all-read" class="text-xs text-accent-1 hover:text-accent-1-hover dark:text-accent-2 dark:hover:text-accent-2-hover font-medium">Clear Notifications</button>
                                    <?php endif; ?>
                                </div>
                                <div class="p-2" id="notification-list">
                                    <?php if (empty($unreadNotifications)): ?>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 p-4 text-center">No new notifications.</p>
                                    <?php else: ?>
                                        <?php foreach ($unreadNotifications as $notification): ?>
                                            <?php require BASE_PATH . '/src/Views/components/notification-alert.php'; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- User Dropdown (desktop) -->
                        <div class="relative hidden lg:block">
                            <button type="button" id="user-menu-button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none transition ease-in-out duration-150">
                                <div><?= htmlspecialchars($_SESSION['user_name'] ?? 'Guest') ?> (<?= htmlspecialchars(ucfirst($_SESSION['role'] ?? '')) ?>)</div>
                                <div class="ml-1">
                                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </button>

                            <div id="user-dropdown-menu" class="hidden absolute right-0 z-50 mt-2 w-48 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 focus:outline-none py-1">
                                <a href="/account/password" class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition duration-150 ease-in-out">Change Password</a>
                                <form method="POST" action="/logout">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                    <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition duration-150 ease-in-out">Log Out</button>
                                </form>
                            </div>
                        </div>

                        <!-- Hamburger (mobile/tablet) -->
                        <button type="button" id="mobile-menu-button" aria-controls="mobile-menu" aria-expanded="false" class="lg:hidden inline-flex items-center justify-center p-2 rounded-md text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-accent-1-hover transition">
                            <span class="sr-only">Open main menu</span>
                            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path id="mobile-menu-open-icon" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                <path id="mobile-menu-close-icon" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile menu: pjax-router.js swaps #pjax-mobile-nav-links so active states stay current -->
            <div id="mobile-menu" class="hidden lg:hidden border-t border-gray-200 dark:border-gray-700">
                <div id="pjax-mobile-nav-links" class="pt-2 pb-3 space-y-1">
                    <?php foreach ($navLinks as [$href, $label, $isActive]): ?>
                        <a href="<?= $href ?>" class="<?= $isActive ? $mobileActiveClass : $mobileInactiveClass ?>"><?= $label ?></a>
                    <?php endforeach; ?>
                </div>
                <div class="pt-4 pb-3 border-t border-gray-200 dark:border-gray-700">
                    <div class="px-4">
                        <div class="font-medium text-base text-gray-800 dark:text-gray-200"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Guest') ?></div>
                        <div class="font-medium text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars(ucfirst($_SESSION['role'] ?? '')) ?></div>
                    </div>
                    <a href="/account/password" class="mt-3 block w-full text-left pl-3 pr-4 py-2 border-l-4 border-transparent text-base font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition">Change Password</a>
                    <form method="POST" action="/logout">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <button type="submit" class="block w-full text-left pl-3 pr-4 py-2 border-l-4 border-transparent text-base font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition">Log Out</button>
                    </form>
                </div>
            </div>
        </nav>

        <!-- Page Heading (page titles are h2; this gives every page a single h1 for screen readers) -->
        <h1 class="sr-only">DepEd Document Tracking System</h1>
        <?php if (isset($header)): ?>
            <!-- pjax-router.js also swaps this element's innerHTML after each navigation -->
            <header id="pjax-header" class="bg-white dark:bg-gray-800 shadow dark:shadow-none border-b border-gray-200 dark:border-gray-700 relative z-10">
                <div class="py-4 sm:py-6 px-4 sm:px-6 lg:px-8 xl:mx-[20vh]">
                    <?= $header ?>
                </div>
            </header>
        <?php else: ?>
            <!-- Render an empty placeholder so pjax-router.js always has a stable #pjax-header to swap -->
            <header id="pjax-header" class="hidden"></header>
        <?php endif; ?>

        <!-- Page Content -->

        <!-- PJAX top progress bar: a 3px gradient line that simulates loading
             progress. Driven entirely by pjax-router.js via inline style tweens. -->
        <div id="pjax-progress-bar" style="
            position: fixed; top: 0; left: 0; z-index: 99999;
            height: 3px; width: 0%; opacity: 0;
            background: linear-gradient(90deg, #6366f1, #a855f7, #ec4899);
            pointer-events: none;
            border-radius: 0 2px 2px 0;
        "></div>

        <main>
            <!-- Toast Notification Container -->
            <div id="toast-container" class="flex flex-col gap-3 pointer-events-none fixed bottom-5 left-4 right-4 sm:left-auto sm:right-5 sm:max-w-md" style="z-index: 9999;">
                <?php
                foreach ($sessionAlerts as $alert) {
                    $notification = $alert; // notification-alert.php uses $notification
                    require BASE_PATH . '/src/Views/components/notification-alert.php';
                }
                ?>
            </div>

            <!-- PJAX content wrapper: pjax-router.js swaps innerHTML of this div
                 on every navigation, leaving the nav, progress bar, and modals intact. -->
            <div id="pjax-content">
                <?= $content ?? '' ?>
            </div>
        </main>
    </div>

    <?php if (isset($_SESSION['console_error'])): ?>
        <script type="application/json" id="dts-console-error"><?= json_encode($_SESSION['console_error'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
        <?php unset($_SESSION['console_error']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['needs_security_key']) && empty($_SESSION['must_change_password'])): ?>
    <?php
    $modalId = 'security-key-modal';
    $modalTitle = 'Action Required: Setup Your Digital Signature';
    $modalSize = 4; // max-w-lg
    $hideCloseButton = true;
    
    $modalContent = '
        <p class="mb-2 text-gray-600 dark:text-gray-400">
            To interact with documents, you must generate a cryptographic Ed25519 digital signature. 
            This ensures non-repudiation and secures the blockchain-like document ledger against tampering.
        </p>
        <form action="/security-key" method="POST" id="security-key-form">
            <input type="hidden" name="csrf_token" value="' . ($_SESSION['csrf_token'] ?? '') . '">
            <!-- Hidden submit to safely capture Enter key -->
            <button type="submit" class="hidden" aria-hidden="true"></button>
            <div class="mb-4">
                <label for="pin" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Create a Security PIN (at least 8 characters)</label>
                <input type="password" name="pin" id="pin" minlength="8" required class="mt-1 block w-full border-gray-300 dark:border-gray-600 rounded-md shadow-sm sm:text-sm p-2 border bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-accent-1 focus:border-accent-1">
            </div>
            <div class="mb-4">
                <label for="pin_confirm" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Confirm Security PIN</label>
                <input type="password" name="pin_confirm" id="pin_confirm" minlength="8" required class="mt-1 block w-full border-gray-300 dark:border-gray-600 rounded-md shadow-sm sm:text-sm p-2 border bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-accent-1 focus:border-accent-1">
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">This PIN will be used to encrypt your private key. You will need it every time you approve or process a document.</p>
            </div>
        </form>
    ';
    
    $modalFooter = '
        <form action="/logout" method="POST" class="inline-block m-0">
            <input type="hidden" name="csrf_token" value="' . ($_SESSION['csrf_token'] ?? '') . '">
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none transition">
                Log Out
            </button>
        </form>
        <button type="submit" form="security-key-form" class="inline-flex items-center px-4 py-2 bg-accent-1 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-accent-1-hover active:bg-accent-1-active focus:outline-none focus:border-accent-1-border-active focus:ring focus:ring-accent-1-light transition">
            Generate & Secure Key
        </button>
    ';
    
    require BASE_PATH . '/src/Views/components/modal.php';
    ?>
    <?php endif; ?>

    <!-- Global Confirmation Modal -->
    <?php
    $modalId = 'global-confirmation-modal';
    $modalTitle = 'Confirm Action';
    $modalSize = 3; // max-w-md
    $hideCloseButton = false;
    $modalContent = '<p id="global-confirmation-message" class="text-sm text-gray-600 dark:text-gray-400"></p>';
    $modalFooter = '
        <button type="button" class="close-modal-btn inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-accent-2 focus:outline-none transition" data-modal="global-confirmation-modal">Cancel</button>
        <button id="global-confirm-btn" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 active:bg-red-700 focus:outline-none focus:border-red-700 focus:ring focus:ring-red-200 transition">Yes, Proceed</button>
    ';
    require BASE_PATH . '/src/Views/components/modal.php';
    ?>
    <script src="/js/common.js"></script>
    <script src="/js/app.js"></script>
    <!-- PJAX Router: must be loaded last so all layout-level DOM is available -->
    <script src="/js/pjax-router.js"></script>
</body>
</html>
