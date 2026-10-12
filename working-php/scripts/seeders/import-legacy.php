<?php

define('BASE_PATH', dirname(dirname(__DIR__)));
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\IntegrityManager;

class WeightedNameGenerator {
    private array $firstNames = [];
    private array $lastNames = [];
    private int $totalFirstWeight = 0;
    private int $totalLastWeight = 0;
    private array $blacklist = [
        'mama mary', 'mary mama',
        'papa jesus', 'jesus papa',
        'jesus christ', 'christ jesus',
        'santa claus'
    ];

    public function __construct(string $jsonPath) {
        if (!file_exists($jsonPath)) return;
        try {
            $content = file_get_contents($jsonPath);
            if ($content === false) return;
            $data = json_decode($content, true);
            if (!is_array($data)) return;

            $this->firstNames = $data['first_names'] ?? [];
            $this->lastNames = $data['last_names'] ?? [];

            foreach ($this->firstNames as $fn) $this->totalFirstWeight += (int)($fn['weight'] ?? 0);
            foreach ($this->lastNames as $ln) $this->totalLastWeight += (int)($ln['weight'] ?? 0);
        } catch (\Throwable $e) {}
    }

    private function isBlacklisted(string $name): bool {
        $lower = strtolower($name);
        foreach ($this->blacklist as $phrase) {
            if (str_contains($lower, $phrase)) return true;
        }
        return false;
    }

    private function drawWeightedItem(array $items, int $totalWeight): string {
        if (empty($items) || $totalWeight <= 0) return 'Guest';
        $rand = mt_rand(1, $totalWeight);
        $current = 0;
        foreach ($items as $item) {
            $current += $item['weight'];
            if ($rand <= $current) return $item['name'];
        }
        return $items[0]['name'];
    }

    private function drawCandidateFullName(): string {
        $p = mt_rand(1, 100);
        $wordCount = ($p > 98) ? 3 : (($p > 80) ? 2 : 1);
        $selectedFirstNames = [];
        for ($w = 0; $w < $wordCount; $w++) {
            $fn = $this->drawWeightedItem($this->firstNames, $this->totalFirstWeight);
            if ($w > 0 && in_array($fn, $selectedFirstNames, true)) {
                $fn = $this->drawWeightedItem($this->firstNames, $this->totalFirstWeight);
            }
            $selectedFirstNames[] = $fn;
        }
        $lastName = $this->drawWeightedItem($this->lastNames, $this->totalLastWeight);
        return implode(' ', $selectedFirstNames) . ' ' . $lastName;
    }

    public function getRandomFullName(): string {
        if (empty($this->firstNames) || empty($this->lastNames)) return 'Seeded Guest ' . rand(100, 999);
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = $this->drawCandidateFullName();
            if (!$this->isBlacklisted($candidate)) return $candidate;
        }
        return $this->drawCandidateFullName();
    }

    private function sanitizeForEmail(string $str): string {
        $str = mb_strtolower($str, 'UTF-8');
        $charMap = [
            'á'=>'a', 'à'=>'a', 'â'=>'a', 'ä'=>'a', 'ã'=>'a', 'å'=>'a',
            'é'=>'e', 'è'=>'e', 'ê'=>'e', 'ë'=>'e',
            'í'=>'i', 'ì'=>'i', 'î'=>'i', 'ï'=>'i',
            'ó'=>'o', 'ò'=>'o', 'ô'=>'o', 'ö'=>'o', 'õ'=>'o',
            'ú'=>'u', 'ù'=>'u', 'û'=>'u', 'ü'=>'u',
            'ñ'=>'n', 'ç'=>'c'
        ];
        $str = strtr($str, $charMap);
        return preg_replace('/[^a-z0-9]/', '', $str);
    }

    private function getRandomDomain(): string {
        $domains = ['gmail.com' => 45, 'yahoo.com' => 25, 'deped.gov.ph' => 15, 'outlook.com' => 8, 'hotmail.com' => 7];
        $rand = mt_rand(1, 100);
        $curr = 0;
        foreach ($domains as $domain => $weight) {
            $curr += $weight;
            if ($rand <= $curr) return $domain;
        }
        return 'gmail.com';
    }

    public function getRandomPerson(): array {
        $fullName = $this->getRandomFullName();
        $nameParts = explode(' ', $fullName);
        $lastName = count($nameParts) > 1 ? array_pop($nameParts) : 'Guest';
        $selectedFirstNames = !empty($nameParts) ? $nameParts : ['Seeded'];
        $emailFirstNames = array_slice($selectedFirstNames, 0, 2);
        $cleanFirsts = array_map([$this, 'sanitizeForEmail'], $emailFirstNames);
        $cleanLast = $this->sanitizeForEmail($lastName);
        $handle = implode('_', $cleanFirsts) . '_' . $cleanLast . substr((string)rand(1970, 2005), 2);
        $email = $handle . '@' . $this->getRandomDomain();
        $prefixes = ['0917', '0918', '0920', '0922', '0927', '0939', '0956', '0977', '0998', '0999'];
        $phone = $prefixes[array_rand($prefixes)] . sprintf('%07d', rand(0, 9999999));
        return ['fullName' => $fullName, 'email' => $email, 'phone' => $phone];
    }
}

echo "🚀 Starting Legacy Data Import Seeder (Dynamic Routes)...\n";

$csvPath = BASE_PATH . '/../from_dts/week_2026_07_27_to_07_30.csv';
if (!file_exists($csvPath)) {
    die("CSV not found at $csvPath\n");
}

$db = Database::getInstance();
$conn = $db->getConnection();
@$conn->exec("SET GLOBAL max_allowed_packet = 67108864");
$conn->exec("SET SESSION wait_timeout = 28800");

// --- Truncate ---
echo "🧹 Cleaning tables...\n";
$conn->exec('SET FOREIGN_KEY_CHECKS = 0');
$conn->exec('TRUNCATE TABLE document_logs');
$conn->exec('TRUNCATE TABLE documents');
$conn->exec('TRUNCATE TABLE daily_department_metrics');
// We do NOT truncate purposes here because we want to preserve the official ones from setup-production-db.js

echo "🔐 Preloading data and keys...\n";
require_once dirname(__DIR__) . '/generate-keys.php';

$departments = $conn->query("SELECT id, name FROM departments")->fetchAll(PDO::FETCH_ASSOC);
$users = $conn->query("SELECT id, department_id, role, private_key FROM users WHERE private_key IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);

$recordsOfficer = null;
foreach ($users as $u) {
    if ($u['role'] === 'officer') {
        $recordsOfficer = $u;
        break;
    }
}
if (!$recordsOfficer) $recordsOfficer = $users[0];

function getUserForDept($users, $deptId, $fallback) {
    foreach ($users as $u) {
        if ($u['department_id'] == $deptId) return $u;
    }
    return $fallback;
}

// Decrypt keys
$decryptedKeys = [];
$pin = 'password';
foreach ($users as $user) {
    $decoded = base64_decode($user['private_key']);
    if (strlen($decoded) === 64) {
        $key = substr(hash('sha256', $pin), 0, 32);
        $iv = str_repeat('0', 16);
        $encryptedPriv = $decoded;
    } elseif (strlen($decoded) >= 112) {
        $salt = substr($decoded, 0, SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $iv = substr($decoded, SODIUM_CRYPTO_PWHASH_SALTBYTES, 16);
        $encryptedPriv = substr($decoded, SODIUM_CRYPTO_PWHASH_SALTBYTES + 16);
        $key = sodium_crypto_pwhash(32, $pin, $salt, SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE, SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
    } else continue;
    $decryptedPriv = openssl_decrypt($encryptedPriv, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    if ($decryptedPriv !== false) $decryptedKeys[$user['id']] = $decryptedPriv;
}

$districts = ['East I District', 'East II District', 'South I District', 'South II District', 'West I District', 'North I District', 'North II District', 'North III District', 'City Central District'];

$nameGenerator = new WeightedNameGenerator(__DIR__ . '/names_data.json');
$titlesDataPath = __DIR__ . '/titles_data.json';
$titlesData = file_exists($titlesDataPath) ? (json_decode(file_get_contents($titlesDataPath), true) ?: []) : [];

function getRandomDocumentTitle(string $purposeName, int $index, array $titlesData, bool $isLowercase = false): string {
    if (!empty($purposeName) && !empty($titlesData[$purposeName])) {
        $titles = $titlesData[$purposeName];
        $title = $titles[array_rand($titles)];
    } else {
        $allCategories = array_keys($titlesData);
        if (!empty($allCategories)) {
            $randomCategory = $allCategories[array_rand($allCategories)];
            $titles = $titlesData[$randomCategory];
            $title = $titles[array_rand($titles)];
        } else {
            $title = "Legacy Document " . str_pad((string)$index, 4, '0', STR_PAD_LEFT);
        }
    }
    
    if ($isLowercase) {
        $title = strtolower($title);
    }
    return $title;
}

// Read CSV
echo "📖 Reading CSV data...\n";
$file = fopen($csvPath, 'r');
$headers = fgetcsv($file);
$docs = [];
while ($row = fgetcsv($file)) {
    $rowData = array_combine($headers, $row);
    $docId = $rowData['document_id'];
    if (!isset($docs[$docId])) $docs[$docId] = [];
    $docs[$docId][] = $rowData;
}
fclose($file);

// Sort actions chronologically
foreach ($docs as &$actions) {
    usort($actions, function($a, $b) {
        return strtotime($a['datetime_forwarded']) - strtotime($b['datetime_forwarded']);
    });
}
unset($actions);

// Helper for caching DB lookups
$purposeCache = [];
$purposes = $conn->query("SELECT id, name, suggested_route FROM purposes")->fetchAll(PDO::FETCH_ASSOC);
foreach ($purposes as $p) {
    $purposeCache[strtolower($p['name'])] = $p;
}

function getOrCreatePurpose($conn, $name, &$purposeCache) {
    $isOfficial = 1;
    $lookupName = strtolower(trim($name));
    
    if ($lookupName === 'others' || empty($lookupName)) {
        if (rand(0, 1) === 0) {
            $name = 'Recruitment';
            $isOfficial = 0;
        } else {
            $name = 'Document submission';
            $isOfficial = 1;
        }
        $lookupName = strtolower($name);
    }

    if (isset($purposeCache[$lookupName])) return $purposeCache[$lookupName]['id'];
    
    $conn->prepare("INSERT INTO purposes (name, is_official) VALUES (?, ?)")->execute([$name, $isOfficial]);
    $id = $conn->lastInsertId();
    $purposeCache[$lookupName] = ['id' => $id, 'name' => $name, 'suggested_route' => null];
    return $id;
}

$deptCache = [];
foreach ($departments as $d) $deptCache[$d['name']] = $d['id'];
function getOrCreateDepartment($conn, $name, &$deptCache) {
    if (empty($name)) $name = 'Unknown Department';
    if (isset($deptCache[$name])) return $deptCache[$name];
    $conn->prepare("INSERT INTO departments (name) VALUES (?)")->execute([$name]);
    $id = $conn->lastInsertId();
    $deptCache[$name] = $id;
    return $id;
}

function getPurposeRoute($purposeName, $purposesCache) {
    $lower = strtolower(trim($purposeName));
    
    // Custom mapping as requested by user
    if (str_contains($lower, 'recruitment') || str_contains($lower, 'job application')) {
        return ['Personnel Unit'];
    }
    if (str_contains($lower, 'approval') || str_contains($lower, 'signature')) {
        $pool = ['Personnel Unit', 'School Governance and Operations Division', 'Assistant Schools Division Superintendent Office', 'Schools Division Superintendent Office'];
        shuffle($pool);
        return [$pool[0], $pool[1]];
    }
    
    // Check if purpose exists and has official suggested_route
    if (isset($purposesCache[$lower]) && !empty($purposesCache[$lower]['suggested_route'])) {
        $r = json_decode($purposesCache[$lower]['suggested_route'], true);
        if (is_array($r) && !empty($r)) {
             return array_map(function($x) { return is_string($x) ? $x : $x['name']; }, $r);
        }
    }
    
    // Fallback dynamic route
    $pool = ['Administrative Unit', 'Legal Unit', 'Accounting Unit'];
    shuffle($pool);
    return [$pool[0]];
}

echo "🔄 Parsing and Inserting Documents with Dynamic Processing...\n";
$docsToInsert = [];
$allLogTemplates = [];

$globalDocIndex = 0;

$conn->beginTransaction();

foreach ($docs as $legacyDocId => $actions) {
    $globalDocIndex++;
    $firstAction = $actions[0];
    
    $legacyPurpose = trim($firstAction['route_purpose']);
    $purposeId = getOrCreatePurpose($conn, $legacyPurpose, $purposeCache);
    
    // If it was mapped from "others", we look up the mapped name to get the correct route
    foreach ($purposeCache as $k => $p) {
        if ($p['id'] == $purposeId) {
            $mappedPurposeName = $p['name'];
            break;
        }
    }
    
    $isLowercase = (rand(1, 100) <= 20);
    $docTitle = getRandomDocumentTitle($mappedPurposeName ?? $legacyPurpose, $globalDocIndex, $titlesData, $isLowercase);
    $person = $nameGenerator->getRandomPerson();
    
    $guestName = $person['fullName'];
    if ($isLowercase) {
        $guestName = strtolower($guestName);
    }
    $guestInfo = json_encode(['name' => $guestName, 'email' => $person['email'], 'phone' => $person['phone']]);
    $district = $districts[array_rand($districts)];
    
    $initialDeptName = $firstAction['route_fromsection'] ?: 'Client';
    if ($initialDeptName === 'Client /Customer' || $initialDeptName === 'Client') {
        $initialDeptName = $departments[array_rand($departments)]['name'];
    }
    
    // GET DYNAMIC ROUTE
    $routeNames = getPurposeRoute($mappedPurposeName ?? $legacyPurpose, $purposeCache);
    // Ensure all departments in route exist in DB
    foreach ($routeNames as $deptName) {
        getOrCreateDepartment($conn, $deptName, $deptCache);
    }
    $finalizedRoute = json_encode(array_map(function($n) { return ['name' => $n, 'type' => 'initial']; }, $routeNames));
    
    $trackingCode = 'DEPED-' . strtoupper(substr(sha1(uniqid('', true) . microtime()), 0, 10));
    
    $createdAtTs = strtotime($firstAction['datetime_forwarded']);
    if (!$createdAtTs) $createdAtTs = time();
    $createdAt = date('Y-m-d H:i:s', $createdAtTs);
    
    // Determine dynamic fate
    $fate = mt_rand() / mt_getrandmax();
    $status = 'completed';
    $aimForReleased = false;
    $aimForReadyForRelease = false;
    
    if ($fate < 0.05) { 
        $status = 'completed';
        $aimForReleased = true;
    } elseif ($fate < 0.15) {
        $status = 'ready_for_release';
        $aimForReadyForRelease = true;
    } elseif ($fate < 0.55) {
        $status = 'in_transit';
    } else { 
        $status = 'processing';
    }

    $stepsToProcess = 0;
    if ($aimForReleased || $aimForReadyForRelease) {
        $stepsToProcess = count($routeNames);
    } else {
        $stepsToProcess = rand(1, count($routeNames));
    }
    
    $currentDeptId = null;
    $currentStep = 0;
    $releasedAt = null;
    $releasedByUserId = null;
    
    if ($status === 'in_transit') {
        $currentStep = $stepsToProcess + 1;
        if ($currentStep <= count($routeNames)) {
            $currentDeptId = $deptCache[$routeNames[$currentStep - 1]];
        } else {
            $currentDeptId = $recordsOfficer['department_id'];
        }
    } elseif ($status === 'processing') {
        $currentStep = $stepsToProcess;
        $currentDeptId = $deptCache[$routeNames[$currentStep - 1]];
    } elseif ($status === 'ready_for_release') {
        $currentStep = count($routeNames) + 1;
        $currentDeptId = $recordsOfficer['department_id'];
    } elseif ($status === 'completed') {
        $currentStep = count($routeNames) + 1;
        $releasedByUserId = $recordsOfficer['id'];
    }
    
    // Hash Chain Logic
    $docLogTemplates = [];
    $docDataForHash = [
        'tracking_code' => $trackingCode,
        'title' => $docTitle,
        'guest_info' => $guestInfo,
        'district' => $district,
        'department' => $initialDeptName,
        'purpose_id' => (string)$purposeId,
        'finalized_route' => $finalizedRoute 
    ];
    
    $prevHash = 'genesis_hash';
    
    $addLogTemplate = function($action, $remarks, $userId, $logTs) use (&$docLogTemplates, &$prevHash, $docDataForHash, $decryptedKeys) {
        $stateHash = IntegrityManager::calculateStateHash($docDataForHash);
        $sqlDate = date('Y-m-d H:i:s', $logTs);
        $isoDate = date('c', $logTs);
        
        $signature = base64_encode("SYSTEM_SIG:{$action}|{$stateHash}");
        if ($userId && isset($decryptedKeys[$userId])) {
            $signatureBytes = sodium_crypto_sign_detached($action . '|' . $stateHash, $decryptedKeys[$userId]);
            $signature = base64_encode($signatureBytes);
        }
        
        $docLogTemplates[] = [
            'user_id' => $userId,
            'action' => $action,
            'remarks' => $remarks,
            'prev_hash' => $prevHash,
            'state_hash' => $stateHash,
            'signature' => $signature,
            'snapshot' => json_encode($docDataForHash),
            'sql_date' => $sqlDate,
            'iso_date' => $isoDate
        ];
    };
    
    $ts = $createdAtTs;
    $addLogTemplate('Submitted', 'Document submitted by guest via the public portal.', null, $ts);
    $ts += 1;
    
    if ($status !== 'pending') {
        $firstDepartmentName = $routeNames[0];
        $addLogTemplate('Accepted and Document Routing finalized', "Route finalized. In transit to {$firstDepartmentName}.", $recordsOfficer['id'], $ts);
        
        $legacyStepCount = count($actions);
        
        for ($s = 0; $s < $stepsToProcess; $s++) {
            $deptName = $routeNames[$s];
            $deptId = $deptCache[$deptName];
            $stepUser = getUserForDept($users, $deptId, $recordsOfficer);
            
            // Try to use legacy timestamp if available for this step index to keep it realistic
            $actTs = ($s < $legacyStepCount) ? strtotime($actions[$s]['datetime_route_accepted']) : 0;
            if (!$actTs || $actTs <= $ts) {
                $ts += rand(10, 60) * 60; // Keep it within a few hours to stay on same day
            } else {
                $ts = $actTs;
            }
            
            $addLogTemplate('Received', "Document received by {$deptName}.", $stepUser['id'], $ts);
            
            if ($status === 'processing' && $s === ($stepsToProcess - 1)) {
                break;
            }
            
            $doneTs = ($s < $legacyStepCount) ? strtotime($actions[$s]['actions_datetime']) : 0;
            if (!$doneTs || $doneTs <= $ts) {
                $ts += rand(10, 60) * 60; 
            } else {
                $ts = $doneTs;
            }
            
            $remarks = "Step processed by {$deptName}.";
            if ($s + 1 >= count($routeNames)) {
                $remarks .= " In transit to Records Unit for releasing.";
            } else {
                $nextDeptName = $routeNames[$s + 1];
                $remarks .= " In transit to {$nextDeptName}.";
            }
            $addLogTemplate('Processing Complete', $remarks, $stepUser['id'], $ts);
        }
        
        if ($aimForReleased || $aimForReadyForRelease) {
            $ts += rand(10, 30) * 60;
            $addLogTemplate('Ready for Releasing', 'All processing steps completed. Document received by Records Unit for final releasing.', $recordsOfficer['id'], $ts);
            if ($aimForReleased) {
                $ts += rand(10, 30) * 60;
                $addLogTemplate('Document Released', 'The document has been released to the client.', $recordsOfficer['id'], $ts);
                $releasedAt = date('Y-m-d H:i:s', $ts);
            }
        }
    }
    
    $updatedAt = date('Y-m-d H:i:s', $ts);
    
    $docsToInsert[] = [
        'tracking_code' => $trackingCode,
        'title' => $docTitle,
        'guest_info' => $guestInfo,
        'district' => $district,
        'department' => $initialDeptName,
        'purpose_id' => $purposeId,
        'status' => $status,
        'current_department_id' => $currentDeptId,
        'current_step' => $currentStep,
        'released_at' => $releasedAt,
        'released_by_user_id' => $releasedByUserId,
        'declined_at' => null,
        'decline_reason' => null,
        'finalized_route' => $finalizedRoute,
        'created_at' => $createdAt,
        'updated_at' => $updatedAt
    ];
    
    $allLogTemplates[] = $docLogTemplates;
}

// Bulk Insert Documents
if (!empty($docsToInsert)) {
    echo "💾 Inserting " . count($docsToInsert) . " documents...\n";
    $chunks = array_chunk($docsToInsert, 1000);
    $firstInsertId = null;
    
    foreach ($chunks as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'));
        $sql = "INSERT INTO documents (tracking_code, title, guest_info, district, department, purpose_id, status, current_department_id, current_step, released_at, released_by_user_id, declined_at, decline_reason, finalized_route, created_at, updated_at) VALUES $placeholders";
        $stmt = $conn->prepare($sql);
        $flatParams = [];
        foreach ($chunk as $d) {
            array_push($flatParams, $d['tracking_code'], $d['title'], $d['guest_info'], $d['district'], $d['department'], $d['purpose_id'], $d['status'], $d['current_department_id'], $d['current_step'], $d['released_at'], $d['released_by_user_id'], $d['declined_at'], $d['decline_reason'], $d['finalized_route'], $d['created_at'], $d['updated_at']);
        }
        $stmt->execute($flatParams);
        if ($firstInsertId === null) $firstInsertId = $conn->lastInsertId();
    }
    
    echo "💾 Inserting logs...\n";
    $logValues = [];
    $logParams = [];
    
    foreach ($allLogTemplates as $i => $templates) {
        $docId = $firstInsertId + $i;
        $prevHash = 'genesis_hash';
        
        foreach ($templates as $tmpl) {
            $uId = $tmpl['user_id'] !== null ? (int)$tmpl['user_id'] : '';
            $dataForLogHash = [$docId, $uId, $tmpl['action'], $tmpl['iso_date'], $prevHash, $tmpl['state_hash'], $tmpl['signature']];
            $newHash = hash('sha256', json_encode($dataForLogHash, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            
            $logValues[] = '(?,?,?,?,?,?,?,?,?,?,?)';
            array_push($logParams, $docId, $tmpl['user_id'], $tmpl['action'], $tmpl['remarks'], $prevHash, $newHash, $tmpl['state_hash'], $tmpl['signature'], $tmpl['snapshot'], $tmpl['sql_date'], $tmpl['sql_date']);
            
            $prevHash = $newHash;
        }
    }
    
    $logChunks = array_chunk($logValues, 2000); 
    $paramChunks = array_chunk($logParams, 2000 * 11);
    
    foreach ($logChunks as $idx => $vChunk) {
        $pChunk = $paramChunks[$idx];
        $sql = "INSERT INTO document_logs (document_id, user_id, action, remarks, previous_hash, hash, document_state_hash, signature, document_snapshot, created_at, updated_at) VALUES " . implode(',', $vChunk);
        $stmt = $conn->prepare($sql);
        $stmt->execute($pChunk);
    }
}

$conn->commit();
$conn->exec('SET FOREIGN_KEY_CHECKS = 1');
echo "🎉 Done! Seeded legacy data successfully.\n";
