<?php
// ============================================================
// davenn.com — Unified API v3
// Apps: Meeting Cost Timer · Track Timer · Toolshare
// Upload to GoDaddy hosting root. Fill in credentials below.
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // lock to 'https://davenn.com' in production
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ── LOAD .env ──────────────────────────────────────────────────────────────
$env_file = __DIR__ . '/.env';
if (file_exists($env_file)) {
    foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v);
    }
}

// ── DB CONFIG ──────────────────────────────────────────────────────────────
$host = $_ENV['DB_HOST'] ?? 'localhost';
$db   = $_ENV['DB_NAME'] ?? '';
$user = $_ENV['DB_USER'] ?? '';
$pass = $_ENV['DB_PASS'] ?? '';

// ── UPLOAD CONFIG ──────────────────────────────────────────────────────────
$upload_dir = __DIR__ . ($_ENV['UPLOAD_DIR'] ?? '/uploads/tools/');
$upload_url = $_ENV['UPLOAD_URL'] ?? '';

// ── EMAIL CONFIG ───────────────────────────────────────────────────────────
$mail_from      = $_ENV['MAIL_FROM']      ?? '';
$mail_from_name = $_ENV['MAIL_FROM_NAME'] ?? '';
$app_url        = $_ENV['APP_URL']        ?? '';
// ──────────────────────────────────────────────────────────────────────────

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]);
    exit;
}

// ── CREATE TABLES ──────────────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS meetings (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    title      VARCHAR(120)  NOT NULL,
    cost       DECIMAL(12,2) NOT NULL,
    seconds    INT           NOT NULL,
    week_key   DATE          NOT NULL,
    created_at DATETIME      DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS track_sessions (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(120)  NOT NULL,
    duration   BIGINT        NOT NULL,
    athletes   LONGTEXT      NOT NULL,
    created_at DATETIME      DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS tb_users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(60)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name  VARCHAR(80)  NOT NULL,
    email         VARCHAR(120) NOT NULL,
    token         VARCHAR(64)  DEFAULT NULL,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS tb_tools (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    owner_id    INT           NOT NULL,
    name        VARCHAR(120)  NOT NULL,
    brand       VARCHAR(80)   DEFAULT NULL,
    category    VARCHAR(60)   DEFAULT NULL,
    notes       TEXT          DEFAULT NULL,
    photo_url   VARCHAR(255)  DEFAULT NULL,
    status      ENUM('available','borrowed') DEFAULT 'available',
    created_at  DATETIME      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES tb_users(id) ON DELETE CASCADE
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS tb_requests (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    tool_id      INT           NOT NULL,
    requester_id INT           NOT NULL,
    owner_id     INT           NOT NULL,
    status       ENUM('pending','approved','declined','returned') DEFAULT 'pending',
    message      TEXT          DEFAULT NULL,
    created_at   DATETIME      DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tool_id)      REFERENCES tb_tools(id)   ON DELETE CASCADE,
    FOREIGN KEY (requester_id) REFERENCES tb_users(id)   ON DELETE CASCADE,
    FOREIGN KEY (owner_id)     REFERENCES tb_users(id)   ON DELETE CASCADE
)");

// Add invite_code column if not already present (safe migration — runs harmlessly)
try { $pdo->exec("ALTER TABLE tb_users ADD COLUMN invite_code VARCHAR(16) UNIQUE DEFAULT NULL"); } catch(PDOException $e) {}

$pdo->exec("CREATE TABLE IF NOT EXISTS tb_friendships (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_a     INT NOT NULL,
    user_b     INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_pair (user_a, user_b),
    FOREIGN KEY (user_a) REFERENCES tb_users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_b) REFERENCES tb_users(id) ON DELETE CASCADE
)");

// ── HELPERS ────────────────────────────────────────────────────────────────
function authUser(PDO $pdo): ?array {
    $token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    if (!$token) return null;
    $stmt = $pdo->prepare("SELECT * FROM tb_users WHERE token = ?");
    $stmt->execute([trim($token)]);
    return $stmt->fetch() ?: null;
}

function requireAuth(PDO $pdo): array {
    $user = authUser($pdo);
    if (!$user) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
    return $user;
}

function sendEmail(string $to, string $to_name, string $subject, string $body_html,
                   string $from, string $from_name): void {
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$from_name} <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    @mail($to, $subject, $body_html, $headers);
}

function borrowRequestEmail(array $owner, array $requester, array $tool, array $request,
                             string $app_url, string $from, string $from_name): void {
    $subject = "📦 {$requester['display_name']} wants to borrow your {$tool['name']}";
    $msg     = $request['message'] ? "<p><strong>Message:</strong> " . htmlspecialchars($request['message']) . "</p>" : '';
    $body = "
    <div style='font-family:sans-serif;max-width:520px;margin:0 auto;padding:24px;'>
      <h2 style='margin:0 0 16px;'>New Borrow Request</h2>
      <p><strong>{$requester['display_name']}</strong> wants to borrow your
         <strong>" . htmlspecialchars($tool['name']) . "</strong>.</p>
      {$msg}
      <p style='margin-top:24px;'>
        <a href='{$app_url}' style='background:#111;color:#fff;padding:10px 20px;
           border-radius:6px;text-decoration:none;font-weight:bold;'>
          Review Request in Toolshare
        </a>
      </p>
      <p style='margin-top:24px;font-size:12px;color:#999;'>davenn.com Toolshare</p>
    </div>";
    sendEmail($owner['email'], $owner['display_name'], $subject, $body, $from, $from_name);
}

function requestUpdateEmail(array $requester, array $owner, array $tool, string $status,
                             string $app_url, string $from, string $from_name): void {
    $verb    = $status === 'approved' ? 'approved ✅' : 'declined ❌';
    $subject = "{$owner['display_name']} {$verb} your request for {$tool['name']}";
    $body = "
    <div style='font-family:sans-serif;max-width:520px;margin:0 auto;padding:24px;'>
      <h2 style='margin:0 0 16px;'>Request Update</h2>
      <p><strong>{$owner['display_name']}</strong> has <strong>{$verb}</strong> your request
         to borrow <strong>" . htmlspecialchars($tool['name']) . "</strong>.</p>
      <p style='margin-top:24px;'>
        <a href='{$app_url}' style='background:#111;color:#fff;padding:10px 20px;
           border-radius:6px;text-decoration:none;font-weight:bold;'>
          Open Toolshare
        </a>
      </p>
      <p style='margin-top:24px;font-size:12px;color:#999;'>davenndotcom Toolshare</p>
    </div>";
    sendEmail($requester['email'], $requester['display_name'], $subject, $body, $from, $from_name);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ══════════════════════════════════════════════════════════════
// MEETING TIMER
// ══════════════════════════════════════════════════════════════

if ($method === 'GET' && $action === 'week') {
    $week_key = $_GET['week_key'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_key)) {
        http_response_code(400); echo json_encode(['error' => 'Invalid week_key.']); exit;
    }
    $stmt = $pdo->prepare("SELECT * FROM meetings WHERE week_key = ? ORDER BY cost DESC");
    $stmt->execute([$week_key]);
    echo json_encode($stmt->fetchAll()); exit;
}

if ($method === 'POST' && $action === 'save') {
    $body     = json_decode(file_get_contents('php://input'), true);
    $title    = trim($body['title']    ?? '');
    $cost     = floatval($body['cost']    ?? 0);
    $seconds  = intval($body['seconds']  ?? 0);
    $week_key = trim($body['week_key'] ?? '');
    if (!$title || $cost <= 0 || $seconds <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_key)) {
        http_response_code(400); echo json_encode(['error' => 'Missing or invalid fields.']); exit;
    }
    $stmt = $pdo->prepare("INSERT INTO meetings (title, cost, seconds, week_key) VALUES (?, ?, ?, ?)");
    $stmt->execute([$title, $cost, $seconds, $week_key]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]); exit;
}

if ($method === 'DELETE' && $action === 'clear_week') {
    $week_key = $_GET['week_key'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_key)) {
        http_response_code(400); echo json_encode(['error' => 'Invalid week_key.']); exit;
    }
    $stmt = $pdo->prepare("DELETE FROM meetings WHERE week_key = ?");
    $stmt->execute([$week_key]);
    echo json_encode(['success' => true, 'deleted' => $stmt->rowCount()]); exit;
}

// ══════════════════════════════════════════════════════════════
// TRACK TIMER
// ══════════════════════════════════════════════════════════════

if ($method === 'GET' && $action === 'track_sessions') {
    $stmt = $pdo->query("SELECT * FROM track_sessions ORDER BY created_at DESC");
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) { $row['athletes'] = json_decode($row['athletes'], true); $row['duration'] = (int)$row['duration']; }
    echo json_encode($rows); exit;
}

if ($method === 'POST' && $action === 'save_track_session') {
    $body     = json_decode(file_get_contents('php://input'), true);
    $name     = trim($body['name']     ?? '');
    $duration = intval($body['duration'] ?? 0);
    $athletes = $body['athletes'] ?? [];
    if (!$name || $duration <= 0 || !is_array($athletes)) {
        http_response_code(400); echo json_encode(['error' => 'Missing fields.']); exit;
    }
    $stmt = $pdo->prepare("INSERT INTO track_sessions (name, duration, athletes) VALUES (?, ?, ?)");
    $stmt->execute([$name, $duration, json_encode($athletes)]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]); exit;
}

if ($method === 'DELETE' && $action === 'delete_track_session') {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'Invalid id.']); exit; }
    $stmt = $pdo->prepare("DELETE FROM track_sessions WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]); exit;
}

if ($method === 'DELETE' && $action === 'clear_track_sessions') {
    $pdo->exec("DELETE FROM track_sessions");
    echo json_encode(['success' => true]); exit;
}

// ══════════════════════════════════════════════════════════════
// Toolshare — AUTH
// ══════════════════════════════════════════════════════════════

// POST ?action=tb_register
if ($method === 'POST' && $action === 'tb_register') {
    $body  = json_decode(file_get_contents('php://input'), true);
    $uname = trim($body['username']     ?? '');
    $dname = trim($body['display_name'] ?? '');
    $email = trim($body['email']        ?? '');
    $pw    = $body['password'] ?? '';
    if (!$uname || !$dname || !$email || !$pw) {
        http_response_code(400); echo json_encode(['error' => 'All fields required.']); exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400); echo json_encode(['error' => 'Invalid email.']); exit;
    }
    $hash  = password_hash($pw, PASSWORD_DEFAULT);
    $token = bin2hex(random_bytes(32));
    try {
        $stmt = $pdo->prepare("INSERT INTO tb_users (username, password_hash, display_name, email, token) VALUES (?,?,?,?,?)");
        $stmt->execute([$uname, $hash, $dname, $email, $token]);
        $id = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'token' => $token, 'user' => ['id' => $id, 'username' => $uname, 'display_name' => $dname, 'email' => $email]]);
    } catch (PDOException $e) {
        http_response_code(409); echo json_encode(['error' => 'Username already taken.']);
    }
    exit;
}

// POST ?action=tb_login
if ($method === 'POST' && $action === 'tb_login') {
    $body  = json_decode(file_get_contents('php://input'), true);
    $uname = trim($body['username'] ?? '');
    $pw    = $body['password'] ?? '';
    $stmt  = $pdo->prepare("SELECT * FROM tb_users WHERE username = ?");
    $stmt->execute([$uname]);
    $u = $stmt->fetch();
    if (!$u || !password_verify($pw, $u['password_hash'])) {
        http_response_code(401); echo json_encode(['error' => 'Invalid username or password.']); exit;
    }
    $token = bin2hex(random_bytes(32));
    $pdo->prepare("UPDATE tb_users SET token = ? WHERE id = ?")->execute([$token, $u['id']]);
    echo json_encode(['success' => true, 'token' => $token, 'user' => ['id' => $u['id'], 'username' => $u['username'], 'display_name' => $u['display_name'], 'email' => $u['email']]]);
    exit;
}

// POST ?action=tb_logout
if ($method === 'POST' && $action === 'tb_logout') {
    $user = requireAuth($pdo);
    $pdo->prepare("UPDATE tb_users SET token = NULL WHERE id = ?")->execute([$user['id']]);
    echo json_encode(['success' => true]); exit;
}

// ══════════════════════════════════════════════════════════════
// Toolshare — TOOLS
// ══════════════════════════════════════════════════════════════

// GET ?action=tb_tools  — all tools with owner info + active borrow info
if ($method === 'GET' && $action === 'tb_tools') {
    requireAuth($pdo);
    $stmt = $pdo->query("
        SELECT t.*,
               u.display_name  AS owner_name,
               u.username      AS owner_username,
               r.id            AS active_request_id,
               r.requester_id  AS borrower_id,
               bu.display_name AS borrower_name
        FROM   tb_tools t
        JOIN   tb_users u  ON u.id = t.owner_id
        LEFT JOIN tb_requests r  ON r.tool_id = t.id AND r.status = 'approved'
        LEFT JOIN tb_users    bu ON bu.id = r.requester_id
        ORDER  BY u.display_name, t.name
    ");
    echo json_encode($stmt->fetchAll()); exit;
}

// GET ?action=tb_my_tools — current user's tools only
if ($method === 'GET' && $action === 'tb_my_tools') {
    $me = requireAuth($pdo);
    $stmt = $pdo->prepare("
        SELECT t.*,
               r.id            AS active_request_id,
               r.requester_id  AS borrower_id,
               bu.display_name AS borrower_name
        FROM   tb_tools t
        LEFT JOIN tb_requests r  ON r.tool_id = t.id AND r.status = 'approved'
        LEFT JOIN tb_users    bu ON bu.id = r.requester_id
        WHERE  t.owner_id = ?
        ORDER  BY t.name
    ");
    $stmt->execute([$me['id']]);
    echo json_encode($stmt->fetchAll()); exit;
}

// POST ?action=tb_add_tool  (multipart for photo upload OR JSON)
if ($method === 'POST' && $action === 'tb_add_tool') {
    $me        = requireAuth($pdo);
    $name      = trim($_POST['name']     ?? '');
    $brand     = trim($_POST['brand']    ?? '');
    $category  = trim($_POST['category'] ?? '');
    $notes     = trim($_POST['notes']    ?? '');
    $photo_url = null;

    if (!$name) { http_response_code(400); echo json_encode(['error' => 'Tool name required.']); exit; }

    if (!empty($_FILES['photo']['tmp_name'])) {
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $ext   = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allow = ['jpg','jpeg','png','gif','webp'];
        if (!in_array($ext, $allow)) { http_response_code(400); echo json_encode(['error' => 'Invalid image type.']); exit; }
        if ($_FILES['photo']['size'] > 5 * 1024 * 1024) { http_response_code(400); echo json_encode(['error' => 'Photo must be under 5 MB.']); exit; }
        $fname     = uniqid('tool_', true) . '.' . $ext;
        move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $fname);
        $photo_url = $upload_url . $fname;
    }

    $stmt = $pdo->prepare("INSERT INTO tb_tools (owner_id, name, brand, category, notes, photo_url) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$me['id'], $name, $brand ?: null, $category ?: null, $notes ?: null, $photo_url]);
    $id = $pdo->lastInsertId();

    $row = $pdo->prepare("SELECT * FROM tb_tools WHERE id = ?");
    $row->execute([$id]);
    echo json_encode(['success' => true, 'tool' => $row->fetch()]); exit;
}

// PUT ?action=tb_edit_tool&id=X
if ($method === 'POST' && $action === 'tb_edit_tool') {
    $me    = requireAuth($pdo);
    $id    = intval($_POST['id'] ?? $_GET['id'] ?? 0);
    $check = $pdo->prepare("SELECT * FROM tb_tools WHERE id = ? AND owner_id = ?");
    $check->execute([$id, $me['id']]);
    if (!$check->fetch()) { http_response_code(403); echo json_encode(['error' => 'Not your tool.']); exit; }

    $name     = trim($_POST['name']     ?? '');
    $brand    = trim($_POST['brand']    ?? '');
    $category = trim($_POST['category'] ?? '');
    $notes    = trim($_POST['notes']    ?? '');
    if (!$name) { http_response_code(400); echo json_encode(['error' => 'Tool name required.']); exit; }

    $photo_url = null;
    if (!empty($_FILES['photo']['tmp_name'])) {
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $ext   = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allow = ['jpg','jpeg','png','gif','webp'];
        if (!in_array($ext, $allow)) { http_response_code(400); echo json_encode(['error' => 'Invalid image type.']); exit; }
        $fname     = uniqid('tool_', true) . '.' . $ext;
        move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $fname);
        $photo_url = $upload_url . $fname;
        $pdo->prepare("UPDATE tb_tools SET name=?,brand=?,category=?,notes=?,photo_url=? WHERE id=?")
            ->execute([$name, $brand ?: null, $category ?: null, $notes ?: null, $photo_url, $id]);
    } else {
        $pdo->prepare("UPDATE tb_tools SET name=?,brand=?,category=?,notes=? WHERE id=?")
            ->execute([$name, $brand ?: null, $category ?: null, $notes ?: null, $id]);
    }
    $row = $pdo->prepare("SELECT * FROM tb_tools WHERE id = ?");
    $row->execute([$id]);
    echo json_encode(['success' => true, 'tool' => $row->fetch()]); exit;
}

// POST ?action=tb_identify_tool — vision-based auto-fill via Claude
if ($method === 'POST' && $action === 'tb_identify_tool') {
    requireAuth($pdo);

    $api_key = $_ENV['ANTHROPIC_API_KEY'] ?? '';
    if (!$api_key) { http_response_code(500); echo json_encode(['error' => 'Vision API not configured.']); exit; }

    if (empty($_FILES['photo']['tmp_name'])) {
        http_response_code(400); echo json_encode(['error' => 'No image provided.']); exit;
    }

    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    $mime_map = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
    if (!isset($mime_map[$ext])) { http_response_code(400); echo json_encode(['error' => 'Invalid image type.']); exit; }
    if ($_FILES['photo']['size'] > 5 * 1024 * 1024) { http_response_code(400); echo json_encode(['error' => 'Photo must be under 5 MB.']); exit; }

    $image_data = base64_encode(file_get_contents($_FILES['photo']['tmp_name']));
    $media_type = $mime_map[$ext];

    $prompt = 'Identify this tool. Respond with ONLY a JSON object (no markdown, no explanation) with these exact keys:
"name": the specific tool name (e.g. "Circular Saw", "Cordless Drill", "Tape Measure")
"brand": the brand/manufacturer if clearly visible (e.g. "DeWalt", "Milwaukee", "Makita"), or "" if not visible
"category": exactly one of: Power Tools, Hand Tools, Garden, Measuring, Fastening, Cutting, Automotive, Plumbing, Electrical, Ladders & Access, Other

Example: {"name":"Cordless Drill","brand":"DeWalt","category":"Power Tools"}';

    $payload = json_encode([
        'model'      => 'claude-haiku-4-5-20251001',
        'max_tokens' => 150,
        'messages'   => [[
            'role'    => 'user',
            'content' => [
                ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $media_type, 'data' => $image_data]],
                ['type' => 'text',  'text'   => $prompt],
            ],
        ]],
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'x-api-key: ' . $api_key,
            'anthropic-version: 2023-06-01',
            'content-type: application/json',
        ],
        CURLOPT_TIMEOUT => 20,
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$response || $http_code !== 200) {
        http_response_code(502); echo json_encode(['error' => 'Vision API error.']); exit;
    }

    $data   = json_decode($response, true);
    $text   = trim($data['content'][0]['text'] ?? '');
    $result = json_decode($text, true);

    if (!$result || !isset($result['name'])) {
        http_response_code(502); echo json_encode(['error' => 'Could not identify tool.']); exit;
    }

    echo json_encode(['success' => true, 'tool' => [
        'name'     => $result['name']     ?? '',
        'brand'    => $result['brand']    ?? '',
        'category' => $result['category'] ?? '',
    ]]);
    exit;
}

// DELETE ?action=tb_delete_tool&id=X
if ($method === 'DELETE' && $action === 'tb_delete_tool') {
    $me = requireAuth($pdo);
    $id = intval($_GET['id'] ?? 0);
    $check = $pdo->prepare("SELECT * FROM tb_tools WHERE id = ? AND owner_id = ?");
    $check->execute([$id, $me['id']]);
    $tool = $check->fetch();
    if (!$tool) { http_response_code(403); echo json_encode(['error' => 'Not your tool.']); exit; }
    // delete photo file if exists
    if ($tool['photo_url']) {
        $fname = basename($tool['photo_url']);
        @unlink($upload_dir . $fname);
    }
    $pdo->prepare("DELETE FROM tb_tools WHERE id = ?")->execute([$id]);
    echo json_encode(['success' => true]); exit;
}

// ══════════════════════════════════════════════════════════════
// Toolshare — BORROW REQUESTS
// ══════════════════════════════════════════════════════════════

// POST ?action=tb_request  — create borrow request + email owner
if ($method === 'POST' && $action === 'tb_request') {
    $me   = requireAuth($pdo);
    $body = json_decode(file_get_contents('php://input'), true);
    $tool_id = intval($body['tool_id'] ?? 0);
    $message = trim($body['message'] ?? '');

    // fetch tool + owner
    $stmt = $pdo->prepare("SELECT t.*, u.id AS uid, u.display_name, u.email FROM tb_tools t JOIN tb_users u ON u.id = t.owner_id WHERE t.id = ?");
    $stmt->execute([$tool_id]);
    $tool = $stmt->fetch();
    if (!$tool) { http_response_code(404); echo json_encode(['error' => 'Tool not found.']); exit; }
    if ($tool['owner_id'] == $me['id']) { http_response_code(400); echo json_encode(['error' => "You can't borrow your own tool."]); exit; }
    if ($tool['status'] === 'borrowed') { http_response_code(409); echo json_encode(['error' => 'Tool is already borrowed.']); exit; }

    // check no open pending request from this user
    $dup = $pdo->prepare("SELECT id FROM tb_requests WHERE tool_id=? AND requester_id=? AND status='pending'");
    $dup->execute([$tool_id, $me['id']]);
    if ($dup->fetch()) { http_response_code(409); echo json_encode(['error' => 'You already have a pending request for this tool.']); exit; }

    $ins = $pdo->prepare("INSERT INTO tb_requests (tool_id, requester_id, owner_id, message) VALUES (?,?,?,?)");
    $ins->execute([$tool_id, $me['id'], $tool['owner_id'], $message ?: null]);
    $req_id = $pdo->lastInsertId();

    // email owner
    $owner     = ['email' => $tool['email'], 'display_name' => $tool['display_name']];
    $tool_info = ['name' => $tool['name']];
    $req_info  = ['message' => $message];
    borrowRequestEmail($owner, $me, $tool_info, $req_info, $app_url, $mail_from, $mail_from_name);

    echo json_encode(['success' => true, 'request_id' => $req_id]); exit;
}

// GET ?action=tb_requests — inbox (owner) + outbox (requester) for current user
if ($method === 'GET' && $action === 'tb_requests') {
    $me = requireAuth($pdo);
    // incoming
    $inc = $pdo->prepare("
        SELECT r.*, t.name AS tool_name, t.brand, t.category, t.photo_url,
               u.display_name AS requester_name, u.username AS requester_username
        FROM   tb_requests r
        JOIN   tb_tools t ON t.id = r.tool_id
        JOIN   tb_users u ON u.id = r.requester_id
        WHERE  r.owner_id = ? AND r.status = 'pending'
        ORDER  BY r.created_at DESC
    ");
    $inc->execute([$me['id']]);
    // outgoing
    $out = $pdo->prepare("
        SELECT r.*, t.name AS tool_name, t.brand, t.category, t.photo_url,
               u.display_name AS owner_name
        FROM   tb_requests r
        JOIN   tb_tools t ON t.id = r.tool_id
        JOIN   tb_users u ON u.id = r.owner_id
        WHERE  r.requester_id = ?
        ORDER  BY r.created_at DESC
    ");
    $out->execute([$me['id']]);
    echo json_encode(['incoming' => $inc->fetchAll(), 'outgoing' => $out->fetchAll()]); exit;
}

// GET ?action=tb_request_count — badge count of pending incoming requests
if ($method === 'GET' && $action === 'tb_request_count') {
    $me   = requireAuth($pdo);
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM tb_requests WHERE owner_id = ? AND status = 'pending'");
    $stmt->execute([$me['id']]);
    echo json_encode(['count' => (int)$stmt->fetch()['cnt']]); exit;
}

// POST ?action=tb_respond_request&id=X  body: { status: "approved"|"declined" }
if ($method === 'POST' && $action === 'tb_respond_request') {
    $me     = requireAuth($pdo);
    $req_id = intval($_GET['id'] ?? 0);
    $body   = json_decode(file_get_contents('php://input'), true);
    $status = $body['status'] ?? '';
    if (!in_array($status, ['approved', 'declined'])) {
        http_response_code(400); echo json_encode(['error' => 'status must be approved or declined.']); exit;
    }

    // verify ownership
    $stmt = $pdo->prepare("SELECT r.*, t.name AS tool_name FROM tb_requests r JOIN tb_tools t ON t.id = r.tool_id WHERE r.id = ? AND r.owner_id = ? AND r.status = 'pending'");
    $stmt->execute([$req_id, $me['id']]);
    $req = $stmt->fetch();
    if (!$req) { http_response_code(404); echo json_encode(['error' => 'Request not found or already actioned.']); exit; }

    $pdo->prepare("UPDATE tb_requests SET status = ? WHERE id = ?")->execute([$status, $req_id]);

    if ($status === 'approved') {
        // mark tool borrowed and decline all other pending requests for same tool
        $pdo->prepare("UPDATE tb_tools SET status = 'borrowed' WHERE id = ?")->execute([$req['tool_id']]);
        $pdo->prepare("UPDATE tb_requests SET status = 'declined' WHERE tool_id = ? AND id != ? AND status = 'pending'")
            ->execute([$req['tool_id'], $req_id]);
    }

    // email requester
    $req_user = $pdo->prepare("SELECT * FROM tb_users WHERE id = ?");
    $req_user->execute([$req['requester_id']]);
    $requester = $req_user->fetch();
    $tool_info = ['name' => $req['tool_name']];
    requestUpdateEmail($requester, $me, $tool_info, $status, $app_url, $mail_from, $mail_from_name);

    echo json_encode(['success' => true]); exit;
}

// POST ?action=tb_return_tool&id=X  (request id) — mark tool as returned
if ($method === 'POST' && $action === 'tb_return_tool') {
    $me     = requireAuth($pdo);
    $req_id = intval($_GET['id'] ?? 0);
    // owner or borrower can mark returned
    $stmt = $pdo->prepare("SELECT * FROM tb_requests WHERE id = ? AND status = 'approved' AND (owner_id = ? OR requester_id = ?)");
    $stmt->execute([$req_id, $me['id'], $me['id']]);
    $req = $stmt->fetch();
    if (!$req) { http_response_code(404); echo json_encode(['error' => 'Active loan not found.']); exit; }
    $pdo->prepare("UPDATE tb_requests SET status = 'returned' WHERE id = ?")->execute([$req_id]);
    $pdo->prepare("UPDATE tb_tools SET status = 'available' WHERE id = ?")->execute([$req['tool_id']]);
    echo json_encode(['success' => true]); exit;
}


// =============================================================
// Toolshare — FRIENDS
// =============================================================

// Helper: ensure a user has an invite code
function ensureInviteCode(PDO $pdo, int $user_id): string {
    $row = $pdo->prepare("SELECT invite_code FROM tb_users WHERE id = ?");
    $row->execute([$user_id]);
    $code = $row->fetchColumn();
    if (!$code) {
        $code = strtolower(bin2hex(random_bytes(8)));
        $pdo->prepare("UPDATE tb_users SET invite_code = ? WHERE id = ?")->execute([$code, $user_id]);
    }
    return $code;
}

// Helper: add friendship (always store with lower id as user_a)
function addFriendship(PDO $pdo, int $a, int $b): void {
    $lo = min($a,$b); $hi = max($a,$b);
    try { $pdo->prepare("INSERT IGNORE INTO tb_friendships (user_a, user_b) VALUES (?,?)")->execute([$lo,$hi]); }
    catch(PDOException $e) {}
}

// Helper: check friendship
function areFriends(PDO $pdo, int $a, int $b): bool {
    $lo = min($a,$b); $hi = max($a,$b);
    $stmt = $pdo->prepare("SELECT id FROM tb_friendships WHERE user_a = ? AND user_b = ?");
    $stmt->execute([$lo,$hi]);
    return (bool)$stmt->fetch();
}

// GET ?action=tb_my_invite
if ($method === 'GET' && $action === 'tb_my_invite') {
    $me   = requireAuth($pdo);
    $code = ensureInviteCode($pdo, $me['id']);
    echo json_encode(['success' => true, 'invite_code' => $code]); exit;
}

// GET ?action=tb_invite_preview&code=xxx  (no auth required)
if ($method === 'GET' && $action === 'tb_invite_preview') {
    $code = trim($_GET['code'] ?? '');
    if (!$code) { http_response_code(400); echo json_encode(['error' => 'Missing code.']); exit; }
    $stmt = $pdo->prepare("SELECT id, display_name, username FROM tb_users WHERE invite_code = ?");
    $stmt->execute([$code]);
    $inviter = $stmt->fetch();
    if (!$inviter) { http_response_code(404); echo json_encode(['error' => 'Invalid invite code.']); exit; }
    echo json_encode(['success' => true, 'inviter' => $inviter]); exit;
}

// POST ?action=tb_accept_invite  body: { invite_code }
if ($method === 'POST' && $action === 'tb_accept_invite') {
    $me   = requireAuth($pdo);
    $body = json_decode(file_get_contents('php://input'), true);
    $code = trim($body['invite_code'] ?? '');
    if (!$code) { http_response_code(400); echo json_encode(['error' => 'Missing invite_code.']); exit; }
    $stmt = $pdo->prepare("SELECT id, display_name FROM tb_users WHERE invite_code = ?");
    $stmt->execute([$code]);
    $inviter = $stmt->fetch();
    if (!$inviter) { http_response_code(404); echo json_encode(['error' => 'Invite code not found.']); exit; }
    if ($inviter['id'] == $me['id']) { http_response_code(400); echo json_encode(['error' => "You can't add yourself."]); exit; }
    if (areFriends($pdo, $me['id'], $inviter['id'])) {
        echo json_encode(['success' => true, 'already_friends' => true, 'friend' => $inviter]); exit;
    }
    addFriendship($pdo, $me['id'], $inviter['id']);
    echo json_encode(['success' => true, 'friend' => $inviter]); exit;
}

// GET ?action=tb_friends
if ($method === 'GET' && $action === 'tb_friends') {
    $me = requireAuth($pdo);
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.display_name,
               (SELECT COUNT(*) FROM tb_tools WHERE owner_id = u.id) AS tool_count
        FROM   tb_friendships f
        JOIN   tb_users u ON u.id = IF(f.user_a = :uid, f.user_b, f.user_a)
        WHERE  f.user_a = :uid2 OR f.user_b = :uid3
        ORDER  BY u.display_name
    ");
    $stmt->execute([':uid' => $me['id'], ':uid2' => $me['id'], ':uid3' => $me['id']]);
    echo json_encode($stmt->fetchAll()); exit;
}

// DELETE ?action=tb_remove_friend&id=X
if ($method === 'DELETE' && $action === 'tb_remove_friend') {
    $me  = requireAuth($pdo);
    $fid = intval($_GET['id'] ?? 0);
    if (!$fid) { http_response_code(400); echo json_encode(['error' => 'Missing friend id.']); exit; }
    $lo = min($me['id'],$fid); $hi = max($me['id'],$fid);
    $pdo->prepare("DELETE FROM tb_friendships WHERE user_a = ? AND user_b = ?")->execute([$lo,$hi]);
    echo json_encode(['success' => true]); exit;
}

// GET ?action=tb_tools — friends-gated community view
if ($method === 'GET' && $action === 'tb_tools') {
    $me = requireAuth($pdo);
    $stmt = $pdo->prepare("
        SELECT t.*,
               u.display_name  AS owner_name,
               u.username      AS owner_username,
               r.id            AS active_request_id,
               r.requester_id  AS borrower_id,
               bu.display_name AS borrower_name
        FROM   tb_tools t
        JOIN   tb_users u  ON u.id = t.owner_id
        LEFT JOIN tb_requests r  ON r.tool_id = t.id AND r.status = 'approved'
        LEFT JOIN tb_users    bu ON bu.id = r.requester_id
        WHERE  t.owner_id = :me
           OR  t.owner_id IN (
               SELECT IF(user_a = :me2, user_b, user_a)
               FROM   tb_friendships
               WHERE  user_a = :me3 OR user_b = :me4
           )
        ORDER  BY u.display_name, t.name
    ");
    $stmt->execute([':me'=>$me['id'],':me2'=>$me['id'],':me3'=>$me['id'],':me4'=>$me['id']]);
    echo json_encode($stmt->fetchAll()); exit;
}

// =============================================================
http_response_code(404);
echo json_encode(['error' => 'Unknown action.']);
