<?php

$appStartTime = microtime(true);

// Base Path Definition
define('BASE_PATH', dirname(__DIR__));

// Composer autoloader (also loads src/helpers.php via autoload.files)
require BASE_PATH . '/vendor/autoload.php';

$appConfig = require BASE_PATH . '/src/Config/config.php';

// Front Controller & Session Hardening.
// In production the cookie is always Secure, even if TLS ends at a proxy and PHP sees plain HTTP.
session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => $appConfig['app']['env'] === 'production' || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true,
    'use_only_cookies' => true,
]);

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Hashes in document_logs include timestamps, so the timezone must never change
date_default_timezone_set('Asia/Manila');

// Error display follows APP_DEBUG in .env (errors are always logged)
$debug = $appConfig['app']['debug'];
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

use App\Core\Router;

// Initialize the Custom Router
$router = new Router();

// --- Define Routes Below ---



// --- Auth Routes ---
$router->get('/login', [App\Controllers\AuthController::class, 'showLogin']);
$router->post('/login', [App\Controllers\AuthController::class, 'login'], [
    App\Middleware\RateLimitMiddleware::class . ':login,20,60'
]);
$router->post('/logout', [App\Controllers\AuthController::class, 'logout']);

// --- Guest / Public Routes ---
$router->get('/', [App\Controllers\GuestController::class, 'welcome'], [
    App\Middleware\CacheMiddleware::class . ':55'
]);
$router->post('/submit-document', [App\Controllers\GuestController::class, 'store'], [
    App\Middleware\RateLimitMiddleware::class . ':submit,10,3600'
]);
$router->get('/success', [App\Controllers\GuestController::class, 'success']);
$router->get('/track', [App\Controllers\GuestController::class, 'track'], [
    App\Middleware\RateLimitMiddleware::class . ':track,60,60'
]);
$router->get('/api/track/module/(?P<tracking_code>[A-Za-z0-9\-]+)', [App\Controllers\GuestController::class, 'getTrackedDocumentModule'], [
    App\Middleware\RateLimitMiddleware::class . ':track,60,60'
]);
$router->get('/api/track/status', [App\Controllers\GuestController::class, 'getStatusUpdates'], [
    App\Middleware\RateLimitMiddleware::class . ':track,60,60'
]);

// Role-based dashboard redirect — routes each role to its dedicated landing page.
$router->get('/dashboard', function() {
    $role = $_SESSION['role'] ?? 'staff';
    $destinations = [
        'admin'   => '/admin-dashboard',
        'officer' => '/intake',
        'staff'   => '/tasks',
    ];
    header('Location: ' . ($destinations[$role] ?? '/tasks'));
    exit;
}, [
    App\Middleware\AuthMiddleware::class,
]);

$router->get('/statistics', [App\Controllers\StatisticsController::class, 'index'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin,officer,staff',
    App\Middleware\CacheMiddleware::class . ':55'
]);

// --- Protected Routes ---

$router->get('/system-overview', [App\Controllers\SystemHealthController::class, 'index'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);

$router->get('/all-documents', [App\Controllers\IntegrityMonitorController::class, 'index'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);


$router->post('/system-health/run-check', [App\Controllers\SystemHealthController::class, 'runIntegrityCheck'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);
$router->get('/system-health/debug-log/(?P<id>\d+)', [App\Controllers\SystemHealthController::class, 'debugLog'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);
$router->get('/api/system-health/integrity-status/(?P<jobId>[a-zA-Z0-9_-]+)', [App\Controllers\SystemHealthController::class, 'getIntegrityCheckStatus'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);
$router->post('/api/system-health/integrity-cancel/(?P<jobId>[a-zA-Z0-9_-]+)', [App\Controllers\SystemHealthController::class, 'cancelIntegrityCheck'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);
$router->get('/system-health/results', [App\Controllers\SystemHealthController::class, 'getIntegrityCheckResults'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);
$router->post('/system-health/rebuild-chain/(?P<logId>\d+)', [App\Controllers\SystemHealthController::class, 'rebuildChain'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);
$router->get('/api/system-health/db-performance', [App\Controllers\SystemHealthController::class, 'getDbPerformanceData'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);
$router->get('/admin/system-health/export-db-metrics', [App\Controllers\SystemHealthController::class, 'exportDbPerformanceMetrics'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);
$router->post('/system-health/failed-jobs/(?P<id>\d+)/delete', [App\Controllers\SystemHealthController::class, 'deleteFailedJob'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);
$router->post('/system-health/failed-jobs/delete-all', [App\Controllers\SystemHealthController::class, 'deleteAllFailedJobs'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);
$router->post('/system-health/freeze-all', [App\Controllers\SystemHealthController::class, 'freezeAll'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);

$router->get('/system/backups', [App\Controllers\BackupManagerController::class, 'index'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);
$router->post('/system/backups/create', [App\Controllers\BackupManagerController::class, 'create'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);
$router->post('/system/backups/restore/(?P<fileName>.+)', [App\Controllers\BackupManagerController::class, 'restore'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);
$router->post('/system/backups/delete/(?P<fileName>.+)', [App\Controllers\BackupManagerController::class, 'delete'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);
$router->get('/system/backups/download/(?P<fileName>.+)', [App\Controllers\BackupManagerController::class, 'download'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);

// Legacy route alias redirecting to primary documents & integrity monitor view
$router->get('/integrity-monitor', function() {
    header('Location: /all-documents', true, 301);
    exit;
}, [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);

// Security key setup
$router->post('/security-key', [App\Controllers\SecurityKeyController::class, 'store'], [
    App\Middleware\AuthMiddleware::class
]);

// Admin Dashboard Routes
$router->get('/admin-dashboard', [\App\Controllers\AdminDashboardController::class, 'index'], [
    App\Middleware\AuthMiddleware::class, 
    App\Middleware\RoleMiddleware::class . ':admin',
    App\Middleware\CacheMiddleware::class . ':55'
]);
$router->post('/admin-dashboard/clear-cache', [\App\Controllers\AdminDashboardController::class, 'clearCache'], [
    App\Middleware\AuthMiddleware::class, 
    App\Middleware\RoleMiddleware::class . ':admin'
]);
$router->post('/clear-personal-cache', [App\Controllers\SystemHealthController::class, 'clearPersonalCache'], [
    App\Middleware\AuthMiddleware::class
]);

// Admin Dashboard API Routes
$router->get('/api/admin-dashboard/current-load', [\App\Controllers\AdminDashboardController::class, 'getCurrentLoadData'], [App\Middleware\AuthMiddleware::class, App\Middleware\RoleMiddleware::class . ':admin']);
$router->get('/api/admin-dashboard/throughput', [\App\Controllers\AdminDashboardController::class, 'getThroughputData'], [App\Middleware\AuthMiddleware::class, App\Middleware\RoleMiddleware::class . ':admin']);
$router->get('/api/admin-dashboard/decline-trends', [\App\Controllers\AdminDashboardController::class, 'getDeclineTrendData'], [App\Middleware\AuthMiddleware::class, App\Middleware\RoleMiddleware::class . ':admin']);
$router->get('/api/admin-dashboard/status-distribution', [\App\Controllers\AdminDashboardController::class, 'getDocumentStatusDistributionData'], [App\Middleware\AuthMiddleware::class, App\Middleware\RoleMiddleware::class . ':admin']);
$router->get('/api/admin-dashboard/processing-hotspots', [\App\Controllers\AdminDashboardController::class, 'getProcessingHotspotsData'], [App\Middleware\AuthMiddleware::class, App\Middleware\RoleMiddleware::class . ':admin']);
$router->get('/api/admin-dashboard/submission-districts', [\App\Controllers\AdminDashboardController::class, 'getSubmissionDistrictsData'], [App\Middleware\AuthMiddleware::class, App\Middleware\RoleMiddleware::class . ':admin']);
$router->get('/api/admin-dashboard/avg-step-time', [\App\Controllers\AdminDashboardController::class, 'getAvgStepTimeByDepartmentData'], [App\Middleware\AuthMiddleware::class, App\Middleware\RoleMiddleware::class . ':admin']);
$router->get('/api/admin-dashboard/department-load-vs-time', [\App\Controllers\AdminDashboardController::class, 'getDepartmentalLoadVsTimeData'], [App\Middleware\AuthMiddleware::class, App\Middleware\RoleMiddleware::class . ':admin']);
$router->get('/api/admin-dashboard/peak-intake-hours', [\App\Controllers\AdminDashboardController::class, 'getPeakIntakeHoursData'], [App\Middleware\AuthMiddleware::class, App\Middleware\RoleMiddleware::class . ':admin']);

// Notifications
$router->get('/api/notifications/unread', [App\Controllers\NotificationController::class, 'getUnread'], [
    App\Middleware\AuthMiddleware::class
]);
$router->post('/api/notifications/mark-read', [App\Controllers\NotificationController::class, 'markAsRead'], [
    App\Middleware\AuthMiddleware::class
]);

// Users Management (RESTful & Form POST aliases)
$router->get('/users', [App\Controllers\UserController::class, 'index'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);

$router->get('/users/create', [App\Controllers\UserController::class, 'create'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);

$router->post('/users', [App\Controllers\UserController::class, 'store'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);

$router->get('/users/(?P<id>\d+)/edit', [App\Controllers\UserController::class, 'edit'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);

$router->post('/users/(?P<id>\d+)/update', [App\Controllers\UserController::class, 'update'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);

$router->add('PUT', '/users/(?P<id>\d+)', [App\Controllers\UserController::class, 'update'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);

$router->post('/users/(?P<id>\d+)/delete', [App\Controllers\UserController::class, 'destroy'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);

$router->add('DELETE', '/users/(?P<id>\d+)', [App\Controllers\UserController::class, 'destroy'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);

$router->post('/users/(?P<id>\d+)/reset-signature', [App\Controllers\UserController::class, 'resetSignature'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);

// Officer Routes
$router->get('/intake', [App\Controllers\IntakeController::class, 'index'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':officer'
]);

$router->post('/intake/find', [App\Controllers\DocumentController::class, 'find'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':officer'
]);

$router->get('/documents/(?P<id>\d+)/manage', [App\Controllers\DocumentController::class, 'manage'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':officer'
]);

$router->get('/documents/(?P<tracking_code>[A-Za-z0-9\-]+)', [App\Controllers\DocumentController::class, 'show'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin,officer,staff'
]);
$router->get('/documents/(?P<tracking_code>[A-Za-z0-9\-]+)/hash-chain', [App\Controllers\DocumentController::class, 'showHashChain'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin,officer,staff'
]);
$router->get('/documents/(?P<tracking_code>[A-Za-z0-9\-]+)/print-tracking-form', [App\Controllers\DocumentController::class, 'printTrackingForm'], [
    App\Middleware\RateLimitMiddleware::class . ':pdf,20,600'
]);

$router->post('/documents/(?P<tracking_code>[A-Za-z0-9\-]+)/freeze', [App\Controllers\SystemHealthController::class, 'freeze'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);
$router->post('/documents/(?P<tracking_code>[A-Za-z0-9\-]+)/unfreeze', [App\Controllers\SystemHealthController::class, 'unfreeze'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);
$router->post('/documents/(?P<tracking_code>[A-Za-z0-9\-]+)/autoresolve', [App\Controllers\SystemHealthController::class, 'autoResolve'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin'
]);

$router->post('/documents/(?P<id>\d+)/finalize', [App\Controllers\DocumentController::class, 'finalize'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin,officer'
]);

$router->post('/documents/decline', [App\Controllers\DocumentController::class, 'decline'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':admin,officer'
]);

$router->get('/tasks/completed', [App\Controllers\DashboardController::class, 'officerCompletedTasks'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':officer,staff'
]);

$router->get('/releasing', [App\Controllers\DashboardController::class, 'officerReleasing'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':officer'
]);

$router->post('/releasing/(?P<id>\d+)/complete', [App\Controllers\ReleasingController::class, 'complete'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':officer'
]);


$router->post('/statistics/report', [App\Controllers\StatisticsController::class, 'generateReport'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':officer,staff'
]);
$router->get('/statistics/report/status', [App\Controllers\StatisticsController::class, 'getReportStatus'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':officer,staff'
]);
$router->post('/statistics/report/cancel', [App\Controllers\StatisticsController::class, 'cancelReport'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':officer,staff'
]);
$router->get('/statistics/report/download/(?P<jobId>[a-zA-Z0-9_-]+)', [App\Controllers\StatisticsController::class, 'downloadReport'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':officer,staff'
]);

// Chart API Routes
$router->get('/api/statistics/current-load', [App\Controllers\StatisticsController::class, 'getCurrentLoadData'], [
    App\Middleware\AuthMiddleware::class
]);
$router->get('/api/statistics/throughput', [App\Controllers\StatisticsController::class, 'getThroughputData'], [
    App\Middleware\AuthMiddleware::class
]);
$router->get('/api/statistics/avg-processing-time', [App\Controllers\StatisticsController::class, 'getAverageProcessingTimeData'], [
    App\Middleware\AuthMiddleware::class
]);
$router->get('/api/statistics/past-reports', [App\Controllers\StatisticsController::class, 'getPastReports'], [
    App\Middleware\AuthMiddleware::class
]);

// Staff Routes (Officers also have access to tasks)
$router->get('/tasks', [App\Controllers\DashboardController::class, 'staffTasks'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':staff,officer'
]);

$router->post('/tasks/(?P<id>\d+)/complete', [App\Controllers\TaskController::class, 'complete'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':staff,officer'
]);

// Global Scan Receiver (For both staff and officers)
$router->post('/documents/scan', [App\Controllers\DocumentController::class, 'scan'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\RoleMiddleware::class . ':staff,officer'
]);

// ---------------------------


// Dispatch the Request
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$router->dispatch($uri, $method);

// Log Navigation Performance (debug only: a synchronous file append on every request
// doesn't scale; in production use the nginx access log instead)
if ($debug) {
    $appEndTime = microtime(true);
    $durationMs = round(($appEndTime - $appStartTime) * 1000);
    $referer = $_SERVER['HTTP_REFERER'] ?? 'direct';
    $refererPath = $referer !== 'direct' ? (parse_url($referer, PHP_URL_PATH) ?? '/') : 'direct';
    $currentPath = parse_url($uri, PHP_URL_PATH) ?? '/';

    $logDir = BASE_PATH . '/storage/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0770, true);
    }
    $logMessage = sprintf("[%s] %s to %s - %dms\n", date('Y-m-d H:i:s'), $refererPath, $currentPath, $durationMs);
    file_put_contents($logDir . '/navigation.log', $logMessage, FILE_APPEND);
}
