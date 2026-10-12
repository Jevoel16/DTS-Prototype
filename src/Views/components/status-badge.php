<?php
/**
 * @var string $status
 */
$baseClasses = 'px-3 py-1 inline-flex text-xs leading-5 font-bold rounded-full uppercase tracking-wider border';

$status = strtolower($status ?? '');

switch ($status) {
    case 'pending':
        $colorClasses = 'bg-yellow-100 text-yellow-800 border-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-300 dark:border-yellow-700/50';
        break;
    case 'in_transit':
        $colorClasses = 'bg-orange-100 text-orange-800 border-orange-200 dark:bg-orange-900/30 dark:text-orange-300 dark:border-orange-700/50';
        break;
    case 'processing':
        $colorClasses = 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700/50';
        break;
    case 'ready_for_release':
        $colorClasses = 'bg-accent-1-light/30 text-accent-1-active border-accent-1-light dark:bg-indigo-900/30 dark:text-accent-1-light dark:border-indigo-700/50';
        break;
    case 'completed':
        $colorClasses = 'bg-success-light text-success-active border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700/50';
        break;
    case 'declined':
        $colorClasses = 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-700/50';
        break;
    case 'frozen':
        $colorClasses = 'bg-cyan-100 text-cyan-800 border-cyan-200 dark:bg-cyan-900/30 dark:text-cyan-300 dark:border-cyan-700/50';
        break;
    default:
        $colorClasses = 'bg-gray-100 text-gray-800 border-gray-200 dark:bg-gray-900/30 dark:text-gray-300 dark:border-gray-700/50';
        break;
}

$displayStatus = str_replace('_', ' ', $status);
?>

<span class="<?= $baseClasses ?> <?= $colorClasses ?>">
    <?= htmlspecialchars($displayStatus) ?>
</span>
