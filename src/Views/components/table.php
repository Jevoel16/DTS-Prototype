<?php
/**
 * Reusable Table Component
 * 
 * Expected variables:
 * @var array $tableConfig - Configuration array for the table
 * 
 * Format:
 * [
 *     'columns' => [
 *         [
 *             'key' => 'column_key', // The key in the data array
 *             'label' => 'Column Header',
 *             'width' => 'w-[20%]', // Tailwind width class
 *             'wrap' => true/false, // Whether text should wrap (max 2 lines) or nowrap
 *             'type' => 'text'|'tracking_link'|'status'|'date'|'action_view', // How to render the cell
 *         ]
 *     ],
 *     'data' => [ ... ], // Array of rows
 *     'empty_message' => 'No records found.'
 * ]
 */

$columns = $tableConfig['columns'] ?? [];
$data = $tableConfig['data'] ?? [];
$emptyMessage = $tableConfig['empty_message'] ?? 'No records found.';
$wrapperClasses = $tableConfig['wrapper_classes'] ?? 'overflow-x-auto';
?>

<div class="<?= htmlspecialchars($wrapperClasses) ?>">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 table-fixed border dark:border-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
                <?php foreach ($columns as $column): ?>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider <?= htmlspecialchars($column['width'] ?? '') ?>">
                        <?= htmlspecialchars($column['label']) ?>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <?php if (empty($data)): ?>
                <tr>
                    <td colspan="<?= count($columns) ?>" class="px-4 py-4 whitespace-nowrap text-center text-sm text-gray-500 dark:text-gray-400">
                        <?= htmlspecialchars($emptyMessage) ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($data as $row): ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition duration-150">
                        <?php foreach ($columns as $column): ?>
                            <?php 
                                $value = $row[$column['key']] ?? '';
                                $type = $column['type'] ?? 'text';
                                $isWrap = !empty($column['wrap']);
                            ?>
                            <td class="px-4 py-4 text-sm text-gray-900 dark:text-gray-100 <?= $isWrap ? 'whitespace-normal break-all' : 'whitespace-nowrap' ?>">
                                <?php if ($isWrap): ?><div class="line-clamp-2 overflow-hidden text-ellipsis break-all"><?php endif; ?>
                                
                                <?php if ($type === 'tracking_link'): ?>
                                    <div class="flex items-center space-x-2">
                                        <span class="font-mono font-bold text-accent-1">
                                            <a href="/documents/<?= htmlspecialchars($value) ?>" class="hover:underline">
                                                <?= htmlspecialchars($value) ?>
                                            </a>
                                        </span>
                                        <button onclick="const btn=this; btn.querySelector('.copy-icon').style.display='none'; btn.querySelector('.check-icon').style.display='block'; navigator.clipboard.writeText('<?= htmlspecialchars(addslashes($value)) ?>'); setTimeout(() => { btn.querySelector('.copy-icon').style.display='block'; btn.querySelector('.check-icon').style.display='none'; }, 2000);" class="text-gray-400 hover:text-accent-1 transition-colors focus:outline-none" title="Copy Tracking Code">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="copy-icon h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                            </svg>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="check-icon h-4 w-4 text-green-500" style="display: none;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </button>
                                    </div>
                                <?php elseif ($type === 'status'): ?>
                                    <?php $status = $value; require BASE_PATH . '/src/Views/components/status-badge.php'; ?>
                                <?php elseif ($type === 'date'): ?>
                                    <div class="flex flex-col text-gray-500 dark:text-gray-400">
                                        <?php if ($value): ?>
                                            <span><?= htmlspecialchars((new DateTime($value))->format('M d, Y')) ?></span>
                                            <span class="text-xs mt-0.5"><?= htmlspecialchars((new DateTime($value))->format('h:i A')) ?></span>
                                        <?php else: ?>
                                            <span>N/A</span>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($type === 'action_view'): ?>
                                    <div class="text-right">
                                        <a href="/documents/<?= htmlspecialchars($row['tracking_code'] ?? $value) ?>" class="text-accent-1 hover:text-accent-1-active">View</a>
                                    </div>
                                <?php elseif ($type === 'raw'): ?>
                                    <?= $value ?>
                                <?php else: ?>
                                    <span class="text-gray-500 dark:text-gray-400" title="<?= htmlspecialchars($value) ?>">
                                        <?= htmlspecialchars($value) ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($isWrap): ?></div><?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
