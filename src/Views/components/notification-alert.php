<?php
// Inputs: $notification (array with title, message, type, created_at, etc)

$type = $notification['type'] ?? 'info';
$title = htmlspecialchars($notification['title']);
$message = htmlspecialchars($notification['message']);

$borderColor = 'border-blue-500';
$bgColor = 'bg-blue-50 dark:bg-blue-900';
$iconColor = 'text-blue-700 dark:text-blue-400';
$titleColor = 'text-blue-800 dark:text-blue-300';
$textColor = 'text-blue-700 dark:text-blue-400';
$iconSvg = '<path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />';

if ($type === 'success') {
    $borderColor = 'border-green-500';
    $bgColor = 'bg-green-50 dark:bg-green-900';
    $iconColor = 'text-green-700 dark:text-green-400';
    $titleColor = 'text-green-800 dark:text-green-300';
    $textColor = 'text-green-700 dark:text-green-400';
    $iconSvg = '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />';
} elseif ($type === 'error') {
    $borderColor = 'border-red-500';
    $bgColor = 'bg-red-50 dark:bg-red-900';
    $iconColor = 'text-red-700 dark:text-red-400';
    $titleColor = 'text-red-800 dark:text-red-300';
    $textColor = 'text-red-700 dark:text-red-400';
    $iconSvg = '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />';
} elseif ($type === 'warning') {
    $borderColor = 'border-yellow-500';
    $bgColor = 'bg-yellow-50 dark:bg-yellow-900';
    $iconColor = 'text-yellow-700 dark:text-yellow-400';
    $titleColor = 'text-yellow-800 dark:text-yellow-300';
    $textColor = 'text-yellow-700 dark:text-yellow-400';
    $iconSvg = '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />';
}
$toastClasses = isset($notification['is_toast']) && $notification['is_toast'] ? 'toast-message pointer-events-auto animate-slide-in-right' : '';
?>
<div role="alert" class="rounded-md border <?= $borderColor ?> <?= $bgColor ?> p-4 shadow-sm mb-3 relative <?= $toastClasses ?>">
    <div class="flex items-start gap-4">
        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="-mt-0.5 w-6 h-6 <?= $iconColor ?>">
            <?= $iconSvg ?>
        </svg>

        <div class="flex-1 pr-6">
            <strong class="block leading-tight font-medium <?= $titleColor ?>"> <?= $title ?> </strong>
            <p class="mt-0.5 text-sm <?= $textColor ?>">
                <?= $message ?>
            </p>
            <?php if (isset($notification['created_at'])): ?>
                <p class="mt-2 text-xs <?= $textColor ?> opacity-75">
                    <?= date('M j, Y h:i A', strtotime($notification['created_at'])) ?>
                </p>
            <?php endif; ?>
        </div>
        
        <button class="<?= isset($notification['id']) ? 'mark-notification-read' : 'dismiss-alert' ?> absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition" <?= isset($notification['id']) ? 'data-id="'.$notification['id'].'"' : '' ?> title="Dismiss">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>
</div>
