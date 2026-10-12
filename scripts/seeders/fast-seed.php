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

    /**
     * Load name dataset from JSON file and pre-calculate total weights for sampling.
     * Uses try/catch to ensure robust execution if file or JSON parsing fails.
     */
    public function __construct(string $jsonPath) {
        if (!file_exists($jsonPath)) {
            return;
        }

        try {
            $content = file_get_contents($jsonPath);
            if ($content === false) {
                return;
            }
            $data = json_decode($content, true);
            if (!is_array($data)) {
                return;
            }

            $this->firstNames = $data['first_names'] ?? [];
            $this->lastNames = $data['last_names'] ?? [];

            foreach ($this->firstNames as $fn) {
                $this->totalFirstWeight += (int)($fn['weight'] ?? 0);
            }
            foreach ($this->lastNames as $ln) {
                $this->totalLastWeight += (int)($ln['weight'] ?? 0);
            }
        } catch (\Throwable $e) {
            error_log("WeightedNameGenerator failed to parse name data: " . $e->getMessage());
        }
    }

    /**
     * Check if a generated name contains any forbidden blacklisted phrases.
     */
    private function isBlacklisted(string $name): bool {
        $lower = strtolower($name);
        foreach ($this->blacklist as $phrase) {
            if (str_contains($lower, $phrase)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Draw an item from a list of weighted items using random number generation within accumulated weight boundaries.
     */
    private function drawWeightedItem(array $items, int $totalWeight): string {
        if (empty($items) || $totalWeight <= 0) {
            return 'Guest';
        }

        $rand = mt_rand(1, $totalWeight);
        $current = 0;
        foreach ($items as $item) {
            $current += $item['weight'];
            if ($rand <= $current) {
                return $item['name'];
            }
        }

        return $items[0]['name'];
    }

    /**
     * Draw a single candidate full name based on multi-word first name and weighted last name sampling.
     */
    private function drawCandidateFullName(): string {
        // Probability distribution: 80% 1 first name word, 18% 2 words, 2% 3 words
        $p = mt_rand(1, 100);
        $wordCount = 1;
        if ($p > 98) {
            $wordCount = 3;
        } elseif ($p > 80) {
            $wordCount = 2;
        }

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

    /**
     * Generate a realistic full name with weighted multi-word first names and a last name,
     * re-sampling up to 10 attempts if the candidate matches a blacklisted phrase.
     */
    public function getRandomFullName(): string {
        if (empty($this->firstNames) || empty($this->lastNames)) {
            return 'Seeded Guest ' . rand(100, 999);
        }

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = $this->drawCandidateFullName();
            if (!$this->isBlacklisted($candidate)) {
                return $candidate;
            }
        }

        return $this->drawCandidateFullName();
    }

    /**
     * Sanitize string for email handles (remove accents, non-alphanumeric characters, convert to lowercase).
     */
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
        $str = preg_replace('/[^a-z0-9]/', '', $str);
        return $str;
    }

    /**
     * Draw a believable email domain (gmail, yahoo, deped.gov.ph, outlook, hotmail).
     */
    private function getRandomDomain(): string {
        $domains = [
            'gmail.com' => 45,
            'yahoo.com' => 25,
            'deped.gov.ph' => 15,
            'outlook.com' => 8,
            'hotmail.com' => 7
        ];
        $rand = mt_rand(1, 100);
        $curr = 0;
        foreach ($domains as $domain => $weight) {
            $curr += $weight;
            if ($rand <= $curr) return $domain;
        }
        return 'gmail.com';
    }

    /**
     * Generate a realistic person profile containing full name, believable email, and phone number.
     * Uses getRandomFullName() to ensure generated names respect blacklist re-sampling rules.
     */
    public function getRandomPerson(): array {
        $fullName = $this->getRandomFullName();

        // Split fullName into first names array and last name to derive email handles
        $nameParts = explode(' ', $fullName);
        $lastName = count($nameParts) > 1 ? array_pop($nameParts) : 'Guest';
        $selectedFirstNames = !empty($nameParts) ? $nameParts : ['Seeded'];

        // Take up to first 2 first names for email handle construction
        $emailFirstNames = array_slice($selectedFirstNames, 0, 2);
        $cleanFirsts = array_map([$this, 'sanitizeForEmail'], $emailFirstNames);
        $cleanLast = $this->sanitizeForEmail($lastName);

        // Prepare birthdate variations
        $months = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];
        $monthShorts = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
        $mIdx = rand(0, 11);
        $monthName = $months[$mIdx];
        $monthShort = $monthShorts[$mIdx];
        $monthNum = sprintf('%02d', $mIdx + 1);

        $birthYearFull = (string)rand(1970, 2005);
        $birthYearShort = substr($birthYearFull, 2);

        $bdayFormats = [
            $monthName . $birthYearFull,       // march1979
            $monthShort . $birthYearFull,      // mar1979
            $monthNum . $birthYearFull,        // 031979
            $birthYearFull,                    // 1979
            $birthYearShort                    // 79
        ];
        $bdaySuffix = $bdayFormats[array_rand($bdayFormats)];

        $firstUnderscore = implode('_', $cleanFirsts);
        $firstDot = implode('.', $cleanFirsts);
        $firstConcat = implode('', $cleanFirsts);
        $firstCamel = implode('', array_map('ucfirst', $cleanFirsts));

        $lastCamel = ucfirst($cleanLast);

        $patterns = [
            // 1. firstname_lastname / firstname.lastname
            $firstUnderscore . '_' . $cleanLast,
            $firstDot . '.' . $cleanLast,
            // 2. lastname_firstname / lastname.firstname
            $cleanLast . '_' . $firstUnderscore,
            $cleanLast . '.' . $firstUnderscore,
            // 3. firstnameLastname
            $firstCamel . $lastCamel,
            $firstConcat . $cleanLast,
            // 4. lastnameBirthdate
            $cleanLast . $bdaySuffix,
            $cleanLast . '_' . $bdaySuffix,
            // 5. firstnamebirthdate
            $firstConcat . $bdaySuffix,
            $firstUnderscore . '_' . $bdaySuffix,
            // 6. firstname_lastname + birthdate
            $firstUnderscore . '_' . $cleanLast . $bdaySuffix,
            $firstConcat . '_' . $cleanLast . $birthYearShort
        ];

        $handle = $patterns[array_rand($patterns)];
        $domain = $this->getRandomDomain();
        $email = $handle . '@' . $domain;

        // Generate realistic 11-digit Philippine mobile phone number (09xxxxxxxx)
        $prefixes = ['0917', '0918', '0920', '0922', '0927', '0939', '0956', '0977', '0998', '0999'];
        $prefix = $prefixes[array_rand($prefixes)];
        $phone = $prefix . sprintf('%07d', rand(0, 9999999));

        return [
            'fullName' => $fullName,
            'email' => $email,
            'phone' => $phone
        ];
    }
}

// Load config or bootstrap needed stuff
$docsToCreate = isset($argv[1]) ? (int)$argv[1] : 10000;
$chunkSize = 2500; // Safe for MySQL max_allowed_packet to prevent locking

echo "🚀 Starting High-Speed Direct Database Seeder (Hybrid)...\n";
echo "Target: " . number_format($docsToCreate) . " documents.\n";

$db = Database::getInstance();
$conn = $db->getConnection();
@$conn->exec("SET GLOBAL max_allowed_packet = 67108864");
$conn->exec("SET SESSION wait_timeout = 28800");
$conn->exec("SET SESSION net_write_timeout = 3600");
$conn->exec("SET SESSION net_read_timeout = 3600");
$conn->exec("SET SESSION innodb_lock_wait_timeout = 3600");

// --- Truncate & Flush Active Sessions ---
echo "🧹 Cleaning tables, active session files, and response caches...\n";
$savePath = session_save_path() ?: sys_get_temp_dir();
if ($savePath && is_dir($savePath)) {
    $sessFiles = glob($savePath . '/sess_*');
    if ($sessFiles) {
        foreach ($sessFiles as $sf) {
            @unlink($sf);
        }
    }
}
$htmlCacheFiles = glob(BASE_PATH . '/cache/responses/*.html');
if ($htmlCacheFiles) {
    foreach ($htmlCacheFiles as $cf) {
        @unlink($cf);
    }
}

$conn->exec('SET FOREIGN_KEY_CHECKS = 0');
$conn->exec('TRUNCATE TABLE document_logs');
$conn->exec('TRUNCATE TABLE documents');
$conn->exec('TRUNCATE TABLE daily_department_metrics');
$conn->exec('TRUNCATE TABLE notifications');
$conn->exec('TRUNCATE TABLE report_jobs');
$conn->exec('TRUNCATE TABLE jobs');
$conn->exec('TRUNCATE TABLE failed_jobs');
$conn->exec('TRUNCATE TABLE database_metrics');
$conn->exec('TRUNCATE TABLE integrity_checks');
$conn->exec('TRUNCATE TABLE cache');
$conn->exec('TRUNCATE TABLE cache_locks');
$conn->exec('TRUNCATE TABLE user_public_key_histories');
$conn->exec("UPDATE users SET public_key = NULL, private_key = NULL, security_key_set_at = NULL");
// $conn->exec('SET FOREIGN_KEY_CHECKS = 1'); // Keep disabled during massive inserts

// --- Preload Data ---
echo "🔐 Ensuring user digital keys exist...\n";
require_once dirname(__DIR__) . '/generate-keys.php';

$departments = $conn->query("SELECT id, name FROM departments")->fetchAll(PDO::FETCH_ASSOC);
$purposes = $conn->query("SELECT id, name, is_official, suggested_route FROM purposes")->fetchAll(PDO::FETCH_ASSOC);
$users = $conn->query("SELECT id, department_id, role, private_key FROM users WHERE private_key IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);

if (empty($departments) || empty($users)) {
    die("Error: No departments or users found. Run node seed.js or migrations first to create departments and users.\n");
}

$recordsOfficer = null;
foreach ($users as $u) {
    if ($u['role'] === 'officer') {
        $recordsOfficer = $u;
        break;
    }
}
if (!$recordsOfficer) $recordsOfficer = $users[0];

// --- Decrypt Keys in Memory (The Secret to Speed) ---
echo "🔐 Decrypting user private keys into RAM for high-speed signing...\n";
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
    } else {
        continue;
    }
    $decryptedPriv = openssl_decrypt($encryptedPriv, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    if ($decryptedPriv !== false) {
        $decryptedKeys[$user['id']] = $decryptedPriv;
    }
}
echo "✅ Cached " . count($decryptedKeys) . " private keys.\n";

$districts = ['East I District', 'East II District', 'South I District', 'South II District', 'West I District', 'North I District', 'North II District', 'North III District', 'City Central District'];

date_default_timezone_set('Asia/Manila');

$totalProcessed = 0;
$startTime = microtime(true);

// Metrics helper using streaming O(1) memory aggregation
$hourlyMetrics = [];
function addMetric(&$hourlyMetrics, $timestamp, $isPeak) {
    $hourKey = date('Y-m-d H:00:00', $timestamp);
    if (!isset($hourlyMetrics[$hourKey])) {
        $hourlyMetrics[$hourKey] = ['conn_sum' => 0, 'conn_count' => 0, 'avg_sum' => 0, 'avg_count' => 0, 'slow' => 0];
    }
    $isBusiness = (date('H', $timestamp) >= 8 && date('H', $timestamp) < 17 && date('N', $timestamp) < 6);
    $base = $isBusiness ? rand(10, 50) : rand(2, 10);
    $conns = $isPeak ? $base + rand(20, 50) : $base;
    $avg = $isPeak ? rand(50, 200)/10 : rand(5, 50)/10;
    
    $hourlyMetrics[$hourKey]['conn_sum'] += $conns;
    $hourlyMetrics[$hourKey]['conn_count']++;
    $hourlyMetrics[$hourKey]['avg_sum'] += $avg;
    $hourlyMetrics[$hourKey]['avg_count']++;
    $hourlyMetrics[$hourKey]['slow'] += $isPeak ? rand(0, 3) : 0;
}

function flushMetricsToDb($conn, &$hourlyMetrics) {
    if (empty($hourlyMetrics)) return;
    
    $metricValues = [];
    $metricParams = [];
    foreach ($hourlyMetrics as $hourKey => $data) {
        $c = $data['conn_count'] > 0 ? $data['conn_sum'] / $data['conn_count'] : 0;
        $a = $data['avg_count'] > 0 ? $data['avg_sum'] / $data['avg_count'] : 0;
        $s = $data['slow'];
        $metricValues[] = '(?,?,?,?)';
        array_push($metricParams, $c, $a, $s, $hourKey);
    }
    
    if (!empty($metricValues)) {
        $mChunks = array_chunk($metricValues, 1000);
        $pChunks = array_chunk($metricParams, 4000);
        foreach ($mChunks as $idx => $vChunk) {
            $sql = "INSERT INTO database_metrics (connections, avg_query_time_ms, slow_queries, created_at) VALUES " . implode(',', $vChunk) . " ON DUPLICATE KEY UPDATE connections = VALUES(connections), avg_query_time_ms = VALUES(avg_query_time_ms), slow_queries = slow_queries + VALUES(slow_queries)";
            $stmt = $conn->prepare($sql);
            $stmt->execute($pChunks[$idx]);
        }
    }
    $hourlyMetrics = []; // Reset RAM to 0 bytes
}

function getWeightedPeakHour(): int {
    $weightedHours = [
        8 => 5,   // 8 AM (off-peak)
        9 => 25,  // 9 AM (peak)
        10 => 25, // 10 AM (peak)
        11 => 5,  // 11 AM (off-peak)
        12 => 5,  // 12 PM (off-peak)
        13 => 20, // 1 PM (peak)
        14 => 5,  // 2 PM (off-peak)
        15 => 20, // 3 PM (peak)
        16 => 15  // 4 PM (peak)
    ];
    $rand = rand(1, 125);
    $current = 0;
    foreach ($weightedHours as $hour => $weight) {
        $current += $weight;
        if ($rand <= $current) return $hour;
    }
    return 9;
}

function skipNonWorkingDays(&$ts, $forward = true) {
    // 1 = Monday, 2 = Tuesday, 3 = Wednesday, 4 = Thursday, 5 = Friday, 6 = Saturday, 7 = Sunday
    $day = (int)date('N', $ts);
    if ($day >= 6) { // Saturday, Sunday
        if ($forward) {
            $daysToAdd = (8 - $day); // Sat(6)->+2, Sun(7)->+1 => Monday
            $ts = strtotime("+{$daysToAdd} days", $ts);
        } else {
            $daysToSub = ($day - 5); // Sat(6)->-1, Sun(7)->-2 => Friday
            $ts = strtotime("-{$daysToSub} days", $ts);
        }
        $ts = strtotime(date('Y-m-d', $ts) . ' ' . sprintf('%02d', getWeightedPeakHour()) . ':' . sprintf('%02d', rand(0, 59)) . ':' . sprintf('%02d', rand(0, 59)));
    }
    
    $hour = (int)date('H', $ts);
    if ($hour < 8) {
        $ts = strtotime(date('Y-m-d', $ts) . ' ' . sprintf('%02d', getWeightedPeakHour()) . ':' . sprintf('%02d', rand(0, 59)) . ':' . sprintf('%02d', rand(0, 59)));
    } elseif ($hour >= 17) {
        $ts = strtotime("+1 day", $ts);
        $ts = strtotime(date('Y-m-d', $ts) . ' ' . sprintf('%02d', getWeightedPeakHour()) . ':' . sprintf('%02d', rand(0, 59)) . ':' . sprintf('%02d', rand(0, 59)));
        $day = (int)date('N', $ts);
        if ($day >= 6) {
            $daysToAdd = (8 - $day);
            $ts = strtotime("+{$daysToAdd} days", $ts);
            $ts = strtotime(date('Y-m-d', $ts) . ' ' . sprintf('%02d', getWeightedPeakHour()) . ':' . sprintf('%02d', rand(0, 59)) . ':' . sprintf('%02d', rand(0, 59)));
        }
    }
}

$globalDocIndex = 0;
$nameGenerator = new WeightedNameGenerator(__DIR__ . '/names_data.json');

$titlesDataPath = __DIR__ . '/titles_data.json';
$titlesData = file_exists($titlesDataPath) ? (json_decode(file_get_contents($titlesDataPath), true) ?: []) : [];

function getRandomDocumentTitle(string $purposeName, int $index, array $titlesData): string {
    if (!empty($purposeName) && !empty($titlesData[$purposeName])) {
        $titles = $titlesData[$purposeName];
        return $titles[array_rand($titles)];
    }
    return "Fast Seeded Doc $index";
}

// Pre-calculate date-grouped intra-day sorted timestamps for all documents
echo "📅 Pre-calculating date-grouped intra-day timestamps...\n";
$docTimestamps = [];
$timeSpanSeconds = 365 * 3 * 86400;
$startBaseline = time() - $timeSpanSeconds;

$docsByDate = [];
for ($idx = 1; $idx <= $docsToCreate; $idx++) {
    $progress = ($idx - 1) / max(1, $docsToCreate - 1);
    $rawTs = (int)($startBaseline + ($progress * $timeSpanSeconds));
    $rawTs = min(time(), $rawTs);
    skipNonWorkingDays($rawTs, false);
    $dateStr = date('Y-m-d', $rawTs);
    $docsByDate[$dateStr][] = $idx;
}

foreach ($docsByDate as $dateStr => $indices) {
    $dayTs = [];
    foreach ($indices as $iVal) {
        $h = getWeightedPeakHour();
        $m = rand(0, 59);
        $s = rand(0, 59);
        $dayTs[] = strtotime("{$dateStr} {$h}:{$m}:{$s}");
    }
    sort($dayTs);
    foreach ($indices as $k => $iVal) {
        $docTimestamps[$iVal] = $dayTs[$k];
    }
}

while ($totalProcessed < $docsToCreate) {
    $currentChunk = min($chunkSize, $docsToCreate - $totalProcessed);
    
    $logValues = [];
    $logParams = [];
    
    $docsToInsert = [];
    $chunkLogTemplates = [];
    
    $conn->beginTransaction();
    
    // 1. GENERATE DOCUMENTS AND LOG TIMELINES IN MEMORY
    for ($i = 0; $i < $currentChunk; $i++) {
        $globalDocIndex++;
        $purpose = $purposes[array_rand($purposes)];
        $district = $districts[array_rand($districts)];
        $dept = $departments[array_rand($departments)];
        
        $hexIndex = strtoupper(dechex($globalDocIndex));
        $randomPad = strtoupper(substr(sha1(uniqid('', true)), 0, 10 - strlen($hexIndex)));
        $trackingCode = 'DEPED-' . $randomPad . $hexIndex;
        $person = $nameGenerator->getRandomPerson();
        $guestInfo = json_encode(['name' => $person['fullName'], 'email' => $person['email'], 'phone' => $person['phone']]);
        
        $ts = $docTimestamps[$globalDocIndex] ?? time();
        $createdAt = date('Y-m-d H:i:s', $ts);
        
        $routeNames = [];
        if ($purpose['is_official'] && $purpose['suggested_route']) {
            $parsed = json_decode($purpose['suggested_route'], true);
            if (is_array($parsed)) {
                $routeNames = array_map(function($r) { return is_string($r) ? $r : $r['name']; }, $parsed);
            }
        }
        if (empty($routeNames)) {
            $keys = array_rand($departments, rand(2, 4));
            if (!is_array($keys)) $keys = [$keys];
            foreach ($keys as $k) $routeNames[] = $departments[$k]['name'];
        }
        
        $finalizedRoute = json_encode(array_map(function($n) { return ['name' => $n, 'type' => 'initial']; }, $routeNames));
        
        $fate = mt_rand()/mt_getrandmax();
        $status = 'completed';
        $aimForReleased = true;
        $aimForReadyForRelease = false;
        
        if ($fate < 0.10) { 
            $status = 'pending'; 
            $aimForReleased = false; 
        } elseif ($fate < 0.15) { 
            $status = 'declined'; 
            $aimForReleased = false; 
        } elseif ($fate < 0.30) { 
            $status = 'processing'; 
            $aimForReleased = false; 
        } elseif ($fate < 0.40) { 
            $status = 'ready_for_release'; 
            $aimForReleased = false; 
            $aimForReadyForRelease = true; 
        }
        
        $currentDeptId = null;
        $currentStep = 0;
        $releasedAt = null;
        $releasedByUserId = null;
        $declinedAt = null;
        $declineReason = null;
        
        if ($status == 'processing') {
            $randIdx = array_rand($routeNames);
            $deptName = $routeNames[$randIdx];
            foreach ($departments as $dep) { if ($dep['name'] === $deptName) { $currentDeptId = $dep['id']; break; } }
            $currentStep = $randIdx + 1; // 1-based index
        } elseif ($status == 'ready_for_release') {
            $currentStep = count($routeNames) + 1;
            foreach ($departments as $dep) { if ($dep['name'] === 'Records Unit') { $currentDeptId = $dep['id']; break; } }
            if (!$currentDeptId) $currentDeptId = $recordsOfficer['department_id'] ?? null;
        } elseif ($status == 'completed') {
            $currentStep = count($routeNames) + 1;
            $releasedByUserId = $recordsOfficer['id'];
        } elseif ($status == 'declined') {
            $declineReason = 'Requirements not met.';
        }
        
        $docTitle = getRandomDocumentTitle($purpose['name'] ?? '', $globalDocIndex, $titlesData);
        
        // Generate Log Events & Advance Timestamp
        $docLogTemplates = [];
        $docDataForHash = [
            'tracking_code' => $trackingCode,
            'title' => $docTitle,
            'guest_info' => $guestInfo,
            'district' => $district,
            'department' => $dept['name'],
            'purpose_id' => $purpose['id'],
            'finalized_route' => $finalizedRoute 
        ];
        
        $prevHash = 'genesis_hash';
        
        $addLogTemplate = function($action, $remarks, $userId, $isPeak = false) use (&$docLogTemplates, &$prevHash, &$ts, $docDataForHash, &$hourlyMetrics, $decryptedKeys) {
            $stateHash = IntegrityManager::calculateStateHash($docDataForHash);
            $sqlDate = date('Y-m-d H:i:s', $ts);
            $isoDate = date('c', strtotime($sqlDate));
            
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
            
            addMetric($hourlyMetrics, $ts, $isPeak);
        };
        
        $addLogTemplate('Submitted', 'Document submitted by guest via the public portal.', null);
        
        if ($status === 'pending') {
            $updatedAt = $createdAt;
        } elseif ($status === 'declined') {
            $ts += rand(5, 30) * 60;
            skipNonWorkingDays($ts, true);
            $addLogTemplate('Declined', 'Requirements not met.', $recordsOfficer['id'], true);
            $declinedAt = date('Y-m-d H:i:s', $ts);
            $updatedAt = $declinedAt;
        } else { // processing, ready_for_release, or completed
            $ts += rand(5, 60) * 60;
            skipNonWorkingDays($ts, true);
            $firstDepartmentName = $routeNames[0] ?? 'Unknown';
            $addLogTemplate('Accepted and Document Routing finalized', "Route finalized. In transit to {$firstDepartmentName}.", $recordsOfficer['id']);
            
            $steps = ($aimForReleased || $aimForReadyForRelease) ? count($routeNames) : rand(1, count($routeNames));
            
            for ($s = 0; $s < $steps; $s++) {
                $deptName = $routeNames[$s];
                $deptId = null;
                foreach ($departments as $dep) { if ($dep['name'] === $deptName) { $deptId = $dep['id']; break; } }
                
                $stepUser = null;
                foreach ($users as $u) { if ($u['department_id'] == $deptId) { $stepUser = $u; break; } }
                if (!$stepUser) $stepUser = $recordsOfficer;
                
                $ts += rand(10, 180) * 60;
                skipNonWorkingDays($ts, true);
                $addLogTemplate('Received', "Document received by {$deptName}.", $stepUser['id']);
                
                if ($status === 'processing' && $currentStep !== null && $s === ($currentStep - 1)) {
                    break;
                }
                
                $ts += rand(30, 360) * 60;
                skipNonWorkingDays($ts, true);
                if ($s + 1 < count($routeNames)) {
                    $nextDeptName = $routeNames[$s + 1];
                    $logRemarks = "Step processed by {$deptName}. In transit to {$nextDeptName}.";
                } else {
                    $logRemarks = "Step processed by {$deptName}. In transit to Records Unit for releasing.";
                }
                $addLogTemplate('Processing Complete', $logRemarks, $stepUser['id']);
            }
            
            if (($aimForReleased || $aimForReadyForRelease) && $s === count($routeNames)) {
                $ts += rand(10, 60) * 60;
                skipNonWorkingDays($ts, true);
                $addLogTemplate('Ready for Releasing', 'All processing steps completed. Document received by Records Unit for final releasing.', $recordsOfficer['id']);
                
                if ($aimForReleased) {
                    $ts += rand(10, 60) * 60;
                    skipNonWorkingDays($ts, true);
                    $addLogTemplate('Document Released', 'The document has been released to the client.', $recordsOfficer['id']);
                    $releasedAt = date('Y-m-d H:i:s', $ts);
                }
            }
            
            $updatedAt = date('Y-m-d H:i:s', $ts);
        }
        
        $docsToInsert[] = [
            'tracking_code' => $trackingCode,
            'title' => $docTitle,
            'guest_info' => $guestInfo,
            'district' => $district,
            'department' => $dept['name'],
            'purpose_id' => $purpose['id'],
            'status' => $status,
            'current_department_id' => $currentDeptId,
            'current_step' => $currentStep,
            'released_at' => $releasedAt,
            'released_by_user_id' => $releasedByUserId,
            'declined_at' => $declinedAt,
            'decline_reason' => $declineReason,
            'finalized_route' => $finalizedRoute,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt
        ];
        
        $chunkLogTemplates[$i] = $docLogTemplates;
    }
    
    // BULK INSERT DOCUMENTS
    $placeholders = implode(',', array_fill(0, count($docsToInsert), '(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'));
    $sql = "INSERT INTO documents (tracking_code, title, guest_info, district, department, purpose_id, status, current_department_id, current_step, released_at, released_by_user_id, declined_at, decline_reason, finalized_route, created_at, updated_at) VALUES $placeholders";
    $stmt = $conn->prepare($sql);
    $flatParams = [];
    foreach ($docsToInsert as $d) {
        array_push($flatParams, $d['tracking_code'], $d['title'], $d['guest_info'], $d['district'], $d['department'], $d['purpose_id'], $d['status'], $d['current_department_id'], $d['current_step'], $d['released_at'], $d['released_by_user_id'], $d['declined_at'], $d['decline_reason'], $d['finalized_route'], $d['created_at'], $d['updated_at']);
    }
    $stmt->execute($flatParams);
    
    $firstInsertId = $conn->lastInsertId();
    
    // 2. BIND DOC IDS AND BUILD BULK INSERT LOGS
    for ($i = 0; $i < $currentChunk; $i++) {
        $docId = $firstInsertId + $i;
        $templates = $chunkLogTemplates[$i];
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
    
    // BULK INSERT LOGS
    if (!empty($logValues)) {
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
    
    $totalProcessed += $currentChunk;
    
    // Periodically flush metrics every 50,000 documents to keep RAM at O(1) constant footprint
    if ($totalProcessed % 50000 === 0) {
        $conn->beginTransaction();
        flushMetricsToDb($conn, $hourlyMetrics);
        $conn->commit();
    }

    $mem = round(memory_get_usage() / 1024 / 1024);
    echo "\r   ⏳ Progress: " . number_format($totalProcessed) . " / " . number_format($docsToCreate) . " documents. | 🧠 RAM: {$mem} MB   ";
}

echo "\n📊 Flushing metrics...\n";
$conn->beginTransaction();
flushMetricsToDb($conn, $hourlyMetrics);
$conn->commit();

echo "\n🔒 Resetting all digital signatures for first-time login...\n";
$conn->exec("UPDATE user_public_key_histories SET activated_at = '2000-01-01 00:00:00', deactivated_at = '2038-01-01 00:00:00', updated_at = NOW() WHERE deactivated_at IS NULL OR deactivated_at > '2000-01-01 00:00:00'");
$conn->exec("UPDATE users SET public_key = NULL, private_key = NULL, security_key_set_at = NULL");

$conn->exec('SET FOREIGN_KEY_CHECKS = 1');

echo "\n📈 Backfilling daily departmental metrics...\n";
passthru('php ' . escapeshellarg(BASE_PATH . '/scripts/backfill-metrics.php'));

$elapsed = round(microtime(true) - $startTime, 2);
echo "🎉 Done! Seeded " . number_format($docsToCreate) . " documents in {$elapsed}s.\n";
