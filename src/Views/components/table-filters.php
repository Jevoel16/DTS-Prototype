<?php 
/**
 * Reusable Table Filters Component
 * 
 * Expected variables from parent view:
 * @var array $filterConfig - Array of filter definitions
 * 
 * Format:
 * [
 *     'name' => [
 *         'type' => 'text|select|date',
 *         'label' => 'Label Text',
 *         'placeholder' => 'Placeholder text...', // optional
 *         'options' => ['value' => 'Label'] // only for select
 *     ]
 * ]
 */
$filterConfig = $filterConfig ?? [];
?>

<form method="GET" action="" class="flex flex-row flex-wrap md:flex-nowrap items-end gap-3 pb-4 border-b border-gray-200 dark:border-gray-700 w-full mb-6">
    <?php foreach ($filterConfig as $name => $config): ?>
        <?php 
        $value = $_GET[$name] ?? ($config['type'] === 'select' ? 'all' : ''); 
        ?>
        <div class="flex-grow flex-shrink w-full md:w-auto" style="flex-basis: auto; min-width: 120px;">
            <label for="<?= htmlspecialchars($name) ?>-filter" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <?= htmlspecialchars($config['label']) ?>
            </label>
            
            <?php if ($config['type'] === 'text'): ?>
                <input type="text" name="<?= htmlspecialchars($name) ?>" id="<?= htmlspecialchars($name) ?>-filter" 
                       value="<?= htmlspecialchars($value) ?>" 
                       class="filter-input block w-full border-gray-300 dark:border-gray-600 rounded-md shadow-sm sm:text-sm p-2 border bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-accent-1 focus:border-accent-1" 
                       placeholder="<?= htmlspecialchars($config['placeholder'] ?? '') ?>">
                       
            <?php elseif ($config['type'] === 'date'): ?>
                <input type="date" name="<?= htmlspecialchars($name) ?>" id="<?= htmlspecialchars($name) ?>-filter" 
                       value="<?= htmlspecialchars($value) ?>" 
                       class="filter-input block w-full border-gray-300 dark:border-gray-600 rounded-md shadow-sm sm:text-sm p-2 border bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-accent-1 focus:border-accent-1">
                       
            <?php elseif ($config['type'] === 'select'): ?>
                <select name="<?= htmlspecialchars($name) ?>" id="<?= htmlspecialchars($name) ?>-filter" 
                        class="filter-input block w-full border-gray-300 dark:border-gray-600 rounded-md shadow-sm sm:text-sm p-2 border bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-accent-1 focus:border-accent-1">
                    <?php foreach ($config['options'] as $optValue => $optLabel): ?>
                        <option value="<?= htmlspecialchars($optValue) ?>" <?= $value === (string)$optValue ? 'selected' : '' ?>>
                            <?= htmlspecialchars($optLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?php if (!empty($filterConfig)): ?>
        <button type="submit" class="flex-shrink-0 inline-flex items-center px-4 py-2 bg-accent-1 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-accent-1-hover focus:bg-indigo-500 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-accent-1 focus:ring-offset-2 transition ease-in-out duration-150 shadow-sm h-[38px]">
            Filter
        </button>
        <a href="?" class="flex-shrink-0 inline-flex items-center px-4 py-2 bg-accent-2 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-accent-2-hover focus:bg-gray-500 active:bg-accent-2-active focus:outline-none focus:ring-2 focus:ring-accent-2 focus:ring-offset-2 transition ease-in-out duration-150 shadow-sm h-[38px]">
            Clear
        </a>
    <?php endif; ?>
</form>
