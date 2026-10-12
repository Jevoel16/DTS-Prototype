<?php
// We expect $document to be an array containing document data and $document['logs'] to contain all logs
$guestInfo = json_decode($document['guest_info'], true);
$status = $document['status'];
$finalizedRoute = $document['finalized_route'] ? json_decode($document['finalized_route'], true) : [];
$currentStep = $document['current_step'] ?? 0;

$statusColors = [
    'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-300 dark:border-yellow-700/50',
    'processing' => 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700/50',
    'in_transit' => 'bg-purple-100 text-purple-800 border-purple-200 dark:bg-purple-900/30 dark:text-purple-300 dark:border-purple-700/50',
    'ready_for_release' => 'bg-teal-100 text-teal-800 border-teal-200 dark:bg-teal-900/30 dark:text-teal-300 dark:border-teal-700/50',
    'completed' => 'bg-success-light text-success-active border-green-200 dark:bg-success-active/30 dark:text-success-light dark:border-success-active',
    'declined' => 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-700/50',
    'frozen' => 'bg-gray-100 text-gray-800 border-gray-200 dark:bg-gray-900/30 dark:text-gray-300 dark:border-gray-700/50'
];

$statusColorClass = $statusColors[$status] ?? 'bg-gray-100 text-gray-800 border-gray-200 dark:bg-gray-900/30 dark:text-gray-300 dark:border-gray-700/50';

$logs = $document['logs'] ?? [];
$lastLog = $logs[0] ?? null;
$wasJustRerouted = $lastLog && in_array($lastLog['action'], ['Rerouted', 'Returned from Releasing']);
$isFinalTransit = $status == 'in_transit' && $currentStep > count($finalizedRoute);

// Generate display route objects
$displayRoute = [];
$displayRoute[] = ['name' => 'Intake', 'type' => 'intake', 'timestamp' => null];
foreach ($finalizedRoute as $routeItem) {
    $displayRoute[] = ['name' => $routeItem['name'], 'type' => 'processing', 'timestamp' => null];
}
$displayRoute[] = ['name' => 'Releasing', 'type' => 'releasing', 'timestamp' => null];

// Get timestamps from logs (logs are ordered by created_at DESC)
$stepTimestamps = [];
foreach (array_reverse($logs) as $log) {
    if ($log['action'] === 'Accepted and Document Routing finalized') {
        $stepTimestamps['Intake'] = date('M d, Y h:i A', strtotime($log['created_at']));
    }
    if ($log['action'] === 'Processing Complete' && preg_match('/processed by (.+?)\./', $log['remarks'], $matches)) {
        $departmentName = trim($matches[1]);
        $stepTimestamps[$departmentName] = date('M d, Y h:i A', strtotime($log['created_at']));
    }
    if ($log['action'] === 'Ready for Releasing' || $log['action'] === 'Document Released') {
        if ($log['action'] === 'Document Released') {
            $stepTimestamps['Releasing'] = date('M d, Y h:i A', strtotime($log['created_at']));
        } elseif (!isset($stepTimestamps['Releasing'])) {
            $stepTimestamps['Releasing'] = date('M d, Y h:i A', strtotime($log['created_at']));
        }
    }
}

foreach ($displayRoute as &$step) {
    if (isset($stepTimestamps[$step['name']])) {
        $step['timestamp'] = $stepTimestamps[$step['name']];
    }
}
unset($step);

// Calculate display current step (1-indexed for the UI map)
$displayCurrentStep = null;
if ($status === 'pending') {
    $displayCurrentStep = 1;
} elseif ($status === 'processing' || $status === 'in_transit') {
    $displayCurrentStep = $currentStep + 1;
} elseif ($status === 'ready_for_release') {
    $displayCurrentStep = count($displayRoute);
} elseif ($status === 'completed') {
    $displayCurrentStep = count($displayRoute) + 1;
}
?>

<div class="bg-white dark:bg-gray-800 shadow-md rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 document-card mb-6" data-tracking-code="<?= htmlspecialchars($document['tracking_code']) ?>" data-status="<?= htmlspecialchars($status) ?>" data-current-step="<?= htmlspecialchars($currentStep) ?>">
    <div class="bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 p-4 text-center">
        <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100">Document Status: <?= htmlspecialchars($document['tracking_code']) ?></h2>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <p class="text-gray-600 dark:text-gray-400 mb-1"><strong class="text-gray-800 dark:text-gray-200">Submitter:</strong> <?= htmlspecialchars($guestInfo['name'] ?? 'Unknown') ?></p>
                <p class="text-gray-600 dark:text-gray-400"><strong class="text-gray-800 dark:text-gray-200">Purpose:</strong> <?= htmlspecialchars($document['purpose_name']) ?></p>
            </div>
            <div>
                <p class="text-gray-600 dark:text-gray-400 mb-2">
                    <strong class="text-gray-800 dark:text-gray-200">Status:</strong> 
                    <?php $status = $document['status']; require BASE_PATH . '/src/Views/components/status-badge.php'; ?>
                </p>
                <p class="text-gray-600 dark:text-gray-400"><strong class="text-gray-800 dark:text-gray-200">Submitted On:</strong> <?= date('M d, Y h:i A', strtotime($document['created_at'])) ?></p>
            </div>
        </div>

        <hr class="border-gray-200 dark:border-gray-700 my-6">

        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Tracking History</h3>

        <?php if ($wasJustRerouted): ?>
            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4 rounded-r">
                <p class="text-sm text-blue-700 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>
                    This document's route was just updated for additional processing and is now in transit.
                </p>
            </div>
        <?php endif; ?>

        <div class="py-4">
            <?php if ($status == 'pending'): ?>
                <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded text-center">
                    This document has been submitted and is waiting to be accepted by a Records Officer. The route will be displayed here once it is finalized.
                </div>
            <?php elseif ($status == 'declined'): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded text-center">
                    <h4 class="font-bold mb-1">Document Declined</h4>
                    <p><strong>Reason:</strong> <?= htmlspecialchars($document['decline_reason'] ?? 'No reason provided.') ?></p>
                    <p class="text-sm mt-2 opacity-80">For more information and to retrieve your document, please visit the Records Section.</p>
                </div>
            <?php elseif ($isFinalTransit): ?>
                <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded text-center">
                    <h4 class="font-bold mb-1">Processing Finished</h4>
                    <p>All processing steps are complete. The document is now in transit back to the Records Department to be ready for releasing.</p>
                </div>
            <?php elseif ($status == 'ready_for_release'): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded text-center">
                    <h4 class="font-bold mb-1">Processing Complete!</h4>
                    <p>Your document has finished internal processing and is now ready for release at the Records Department.</p>
                </div>
            <?php else: ?>
                <!-- Tracker Map -->
                <div class="relative py-4 px-2">
                    <?php
                        // Group displayRoute into rows of 4 for snaking design
                        $chunks = [];
                        $slotsPerRow = 4;
                        $totalSteps = count($displayRoute);

                        for ($i = 0; $i < $totalSteps; $i += $slotsPerRow) {
                            $chunk = array_slice($displayRoute, $i, $slotsPerRow);
                            foreach ($chunk as $idx => &$step) {
                                $step['original_index'] = $i + $idx;
                            }
                            unset($step);
                            
                            $isL2R = (($i / $slotsPerRow) % 2 === 0);
                            
                            if ($isL2R) {
                                while (count($chunk) < $slotsPerRow) {
                                    array_push($chunk, null);
                                }
                            } else {
                                $chunk = array_reverse($chunk);
                                while (count($chunk) < $slotsPerRow) {
                                    array_unshift($chunk, null);
                                }
                            }
                            $chunks[] = ['isL2R' => $isL2R, 'steps' => $chunk];
                        }
                    ?>

                    <?php foreach ($chunks as $rowIndex => $chunkData): ?>
                        <div class="relative mb-10 last:mb-0">
                            <!-- Vertical connector to NEXT row -->
                            <?php if ($rowIndex < count($chunks) - 1): ?>
                                <?php 
                                    $lastStep = $chunkData['steps'][$chunkData['isL2R'] ? 3 : 0];
                                    $lastStepNum = $lastStep['original_index'] + 1;
                                    $lineColorClass = ($displayCurrentStep > $lastStepNum) ? 'border-green-500' : 'border-gray-300 dark:border-gray-600';
                                ?>
                                <?php if ($chunkData['isL2R']): ?>
                                    <div class="absolute border-solid border-t-4 border-r-4 border-b-4 rounded-r-xl <?= $lineColorClass ?> z-0" style="top: 22px; right: -1rem; width: calc(12.5% + 1rem); height: calc(100% + 2.5rem + 4px);"></div>
                                <?php else: ?>
                                    <div class="absolute border-solid border-t-4 border-l-4 border-b-4 rounded-l-xl <?= $lineColorClass ?> z-0" style="top: 22px; left: -1rem; width: calc(12.5% + 1rem); height: calc(100% + 2.5rem + 4px);"></div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <!-- The 4 Columns -->
                            <div class="grid text-center relative z-10" style="grid-template-columns: repeat(4, minmax(0, 1fr));">
                                <!-- Row 1: Nodes and Horizontal Lines -->
                                <?php foreach ($chunkData['steps'] as $colIndex => $step): ?>
                                    <div class="flex justify-center relative w-full h-8">
                                        <?php if ($step): ?>
                                            <?php 
                                                $stepNum = $step['original_index'] + 1;
                                                $isCompleted = $displayCurrentStep > $stepNum;
                                                $isCurrent = $displayCurrentStep == $stepNum;
                                                
                                                $dotColor = $isCompleted ? 'bg-green-500' : ($isCurrent ? 'bg-blue-500' : 'bg-gray-300 dark:bg-accent-2');
                                                
                                                $drawHorizontalLine = false;
                                                if ($chunkData['isL2R'] && $colIndex < 3 && isset($chunkData['steps'][$colIndex + 1])) {
                                                    $drawHorizontalLine = true;
                                                    $lineCompleted = $displayCurrentStep > $stepNum;
                                                } elseif (!$chunkData['isL2R'] && $colIndex > 0 && isset($chunkData['steps'][$colIndex - 1])) {
                                                    $drawHorizontalLine = true;
                                                    $lineCompleted = $displayCurrentStep > $stepNum; 
                                                }
                                            ?>
                                            
                                            <?php if ($drawHorizontalLine): ?>
                                                <?php if ($chunkData['isL2R']): ?>
                                                    <div class="absolute left-1/2 w-full h-1 -translate-y-1/2 <?= $lineCompleted ? 'bg-green-500' : 'bg-gray-300 dark:bg-accent-2' ?> z-0" style="top: 75%;"></div>
                                                <?php else: ?>
                                                    <div class="absolute right-1/2 w-full h-1 -translate-y-1/2 <?= $lineCompleted ? 'bg-green-500' : 'bg-gray-300 dark:bg-accent-2' ?> z-0" style="top: 75%;"></div>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <!-- Tail for Step 1 -->
                                            <?php if ($step['original_index'] === 0): ?>
                                                <?php $tailColor = ($displayCurrentStep >= 1) ? 'bg-green-500' : 'bg-gray-300 dark:bg-accent-2'; ?>
                                                <div class="absolute right-1/2 w-8 h-1 -translate-y-1/2 <?= $tailColor ?> z-0" style="top: 75%;"></div>
                                            <?php endif; ?>

                                            <!-- Head for Last Step -->
                                            <?php if ($step['original_index'] === count($displayRoute) - 1): ?>
                                                <?php 
                                                    $headBgColor = ($displayCurrentStep > $stepNum) ? 'bg-green-500' : 'bg-gray-300 dark:bg-accent-2';
                                                    $headBorderColor = ($displayCurrentStep > $stepNum) ? 'border-green-500' : 'border-gray-300 dark:border-gray-600';
                                                ?>
                                                <?php if ($chunkData['isL2R']): ?>
                                                    <div class="absolute left-1/2 w-8 h-1 -translate-y-1/2 <?= $headBgColor ?> z-0 flex items-center justify-end" style="top: 75%;">
                                                        <div class="border-solid border-t-2 border-r-2 <?= $headBorderColor ?> transform rotate-45" style="width: 10px; height: 10px; margin-right: -2px;"></div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="absolute right-1/2 w-8 h-1 -translate-y-1/2 <?= $headBgColor ?> z-0 flex items-center justify-start" style="top: 75%;">
                                                        <div class="border-solid border-t-2 border-l-2 <?= $headBorderColor ?> transform -rotate-45" style="width: 10px; height: 10px; margin-left: -2px;"></div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <div class="relative z-10 w-8 h-8 rounded-full flex items-center justify-center <?= $dotColor ?> text-white font-bold text-sm shadow">
                                                <?php if ($isCompleted): ?>
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                <?php else: ?>
                                                    <?= $stepNum ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>

                                <!-- Row 2: Department Names -->
                                <?php foreach ($chunkData['steps'] as $step): ?>
                                    <div class="flex flex-col items-center pt-2">
                                        <?php if ($step): ?>
                                            <?php 
                                                $stepNum = $step['original_index'] + 1;
                                                $isCompleted = $displayCurrentStep > $stepNum;
                                                $isCurrent = $displayCurrentStep == $stepNum;
                                                $textColor = $isCompleted ? 'text-green-600 dark:text-green-400' : ($isCurrent ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 dark:text-gray-400');
                                            ?>
                                            <div class="text-sm font-semibold <?= $textColor ?> leading-tight px-1 break-words">
                                                <?= htmlspecialchars($step['name']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>

                                <!-- Row 3: Timestamps -->
                                <?php foreach ($chunkData['steps'] as $step): ?>
                                    <div class="flex flex-col items-center">
                                        <?php if ($step && $step['timestamp']): ?>
                                            <div class="text-xs text-gray-400 dark:text-gray-500 mt-1 px-1 text-center">
                                                <?= htmlspecialchars($step['timestamp']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
