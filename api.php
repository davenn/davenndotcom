<?php
// ============================================================
// davenn.com — Unified API v3
// Apps: Meeting Cost Timer · Track Timer · Toolshare
// Upload to GoDaddy hosting root. Fill in credentials below.
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // lock to 'https://davenn.com' in production
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token, X-BG-Token');

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
// Expand category to TEXT to support multiple comma-separated tags
try { $pdo->exec("ALTER TABLE tb_tools MODIFY COLUMN category TEXT DEFAULT NULL"); } catch(PDOException $e) {}

// tb_sessions — one row per logged-in device, so signing in on a second device
// doesn't invalidate the first (tb_users.token used to be a single column, which
// meant only the most-recently-logged-in device stayed authenticated).
$pdo->exec("CREATE TABLE IF NOT EXISTS tb_sessions (
    token      VARCHAR(64) PRIMARY KEY,
    user_id    INT         NOT NULL,
    created_at DATETIME    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES tb_users(id) ON DELETE CASCADE
)");
// Carry over any still-active legacy single-column token so existing sessions aren't logged out.
try { $pdo->exec("INSERT IGNORE INTO tb_sessions (token, user_id) SELECT token, id FROM tb_users WHERE token IS NOT NULL"); } catch(PDOException $e) {}

$pdo->exec("CREATE TABLE IF NOT EXISTS reaction_scores (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(60)  NOT NULL,
    avg_ms     INT          NOT NULL,
    week_key   DATE         NOT NULL,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_week (week_key, avg_ms)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS fb_scores (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(32)  NOT NULL,
    score      INT          NOT NULL,
    week_start DATE         NOT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_week_score (week_start, score)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS dt_scores (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(60)  NOT NULL,
    task_count    INT          NOT NULL,
    total_seconds INT          NOT NULL,
    week_key      DATE         NOT NULL,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_week (week_key, task_count, total_seconds)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS dt_tasks (
    user_id     INT          NOT NULL,
    date_key    DATE         NOT NULL,
    tasks_json  LONGTEXT     NOT NULL,
    next_id     INT          NOT NULL DEFAULT 1,
    updated_at  DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, date_key),
    FOREIGN KEY (user_id) REFERENCES tb_users(id) ON DELETE CASCADE
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS tb_friendships (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_a     INT NOT NULL,
    user_b     INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_pair (user_a, user_b),
    FOREIGN KEY (user_a) REFERENCES tb_users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_b) REFERENCES tb_users(id) ON DELETE CASCADE
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS subscribers (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    contact_type  ENUM('email','phone') NOT NULL,
    contact_value VARCHAR(120) NOT NULL,
    unsub_token   VARCHAR(64)  NOT NULL,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_contact (contact_type, contact_value)
)");

// One row per accepted sign-up form submission, kept for a day. Exists only to
// rate-limit the public form: per IP, and per number for the confirmation text.
$pdo->exec("CREATE TABLE IF NOT EXISTS subscribe_attempts (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    ip            VARCHAR(45)  NOT NULL,
    contact_value VARCHAR(120) NOT NULL,
    texted        TINYINT(1)   NOT NULL DEFAULT 0,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ip (ip, created_at),
    KEY idx_contact (contact_value, created_at)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS bg_readings (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    reading_at DATETIME     NOT NULL,
    mgdl       SMALLINT     NOT NULL,
    trend      VARCHAR(20)  DEFAULT NULL,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_reading_at (reading_at)
)");

// reading_at is snapped to the 5-minute grid so the unique key collapses the
// duplicates Share sends; observed_at keeps the reading's real time, which is
// what freshness and chart positions must use. Older rows have NULL here and
// fall back to the snapped value.
$bg_cols = $pdo->query(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bg_readings'"
)->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('observed_at', $bg_cols, true)) {
    $pdo->exec("ALTER TABLE bg_readings ADD COLUMN observed_at DATETIME NULL AFTER reading_at");
}

// Human annotations on the glucose record: why a stretch looked the way it did.
// Kept apart from bg_readings because the two differ in every way that matters —
// readings are machine-written and rewritten by the poller every few minutes,
// events are hand-written and edited. An event also spans a range rather than a
// row, carries more than one per period, and most usefully is logged BEFORE the
// excursion it explains (the pizza, not the high), when no reading exists to
// hang it on.
//
// No kind column: high/low is derivable from the readings in the range, and
// storing it lets it drift from the data it describes. No foreign key either —
// the link is time overlap, computed at render, so an event stays valid across
// sensor gaps, which is exactly when knowing what happened matters most.
$pdo->exec("CREATE TABLE IF NOT EXISTS bg_events (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    start_at   DATETIME     NOT NULL,
    end_at     DATETIME     DEFAULT NULL,
    tag        VARCHAR(32)  NOT NULL,
    note       VARCHAR(500) DEFAULT NULL,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_start (start_at)
)");

// WildcatsXC. A runner is identified by match_key — name and school,
// normalised — so the same athlete on two sheets is one row. Results hang off
// a meet and an athlete; one result per athlete per meet. One shared team
// record behind a PIN, so no owner column.
$pdo->exec("CREATE TABLE IF NOT EXISTS xc_meets (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(160) NOT NULL,
    meet_date  DATE         NOT NULL,
    location   VARCHAR(160) DEFAULT NULL,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_date (meet_date)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS xc_athletes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(80)  NOT NULL,
    school     VARCHAR(120) NOT NULL,
    match_key  VARCHAR(210) NOT NULL,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_athlete (match_key)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS xc_results (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    meet_id    INT          NOT NULL,
    athlete_id INT          NOT NULL,
    race       VARCHAR(80)  NOT NULL,
    distance_m SMALLINT     DEFAULT NULL,
    place      SMALLINT     DEFAULT NULL,
    grade      TINYINT      DEFAULT NULL,
    time_ms    INT          NOT NULL,
    UNIQUE KEY uniq_run (meet_id, athlete_id),
    INDEX idx_athlete (athlete_id),
    FOREIGN KEY (meet_id)    REFERENCES xc_meets(id)    ON DELETE CASCADE,
    FOREIGN KEY (athlete_id) REFERENCES xc_athletes(id) ON DELETE CASCADE
)");

// WildcatsXC first shipped with Toolshare accounts and a user_id owner column
// on meets and athletes. It is now one shared record behind a PIN, so tables
// created by that first deploy lose the column — keeping their rows, which
// simply join the shared record. A no-op once the column is gone.
//
// Caught, because this block runs for every app: if it cannot apply (two
// accounts saved the same runner, so match_key is no longer unique), WildcatsXC
// saves fail until that is fixed by hand, but the rest of the site stays up.
try {
    $xc_cols = $pdo->query(
        "SELECT TABLE_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('xc_meets','xc_athletes') AND COLUMN_NAME = 'user_id'"
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach ($xc_cols as $xc_table) {
        $fks = $pdo->prepare(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'user_id' AND REFERENCED_TABLE_NAME IS NOT NULL"
        );
        $fks->execute([$xc_table]);
        foreach ($fks->fetchAll(PDO::FETCH_COLUMN) as $fk) $pdo->exec("ALTER TABLE `$xc_table` DROP FOREIGN KEY `$fk`");
        if ($xc_table === 'xc_meets') {
            $pdo->exec("ALTER TABLE xc_meets DROP INDEX idx_user_date, DROP COLUMN user_id, ADD INDEX idx_date (meet_date)");
        } else {
            $pdo->exec("ALTER TABLE xc_athletes DROP INDEX uniq_athlete, DROP COLUMN user_id, ADD UNIQUE KEY uniq_athlete (match_key)");
        }
    }
} catch (PDOException $e) {}

// One row per wrong WildcatsXC PIN, kept for a day. A four-digit PIN is only
// 10,000 guesses, so this is the thing actually protecting it.
$pdo->exec("CREATE TABLE IF NOT EXISTS xc_pin_attempts (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    ip         VARCHAR(45) NOT NULL,
    created_at DATETIME    DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ip (ip, created_at)
)");

// ── HELPERS ────────────────────────────────────────────────────────────────
function authUser(PDO $pdo): ?array {
    $token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    if (!$token) return null;
    $stmt = $pdo->prepare("SELECT u.* FROM tb_sessions s JOIN tb_users u ON u.id = s.user_id WHERE s.token = ?");
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
    // The From stays a send-only address, but replies are pointed at a mailbox
    // somebody actually reads. A support channel that silently swallows replies
    // is worse than none, and messaging compliance expects a working one.
    $reply_to = $_ENV['MAIL_REPLY_TO'] ?? 'support@davenn.com';

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$from_name} <{$from}>\r\n";
    $headers .= "Reply-To: {$reply_to}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    @mail($to, $subject, $body_html, $headers);
}

/**
 * The update-notification opt-in confirmation, word for word as filed on the
 * A2P campaign. Sent from both opt-in routes — the web form and the START
 * keyword — so it lives in one place and the two cannot drift apart.
 */
function optInMessage(): string {
    return "davenn.com Update Notifications: you're signed up. "
         . "Expect a text when a new app or feature ships, typically no more than "
         . "a few messages per month. Message and data rates may apply. "
         . "Reply HELP for help, STOP to cancel.";
}

/**
 * One Twilio send, keeping the outcome instead of throwing it away.
 * Returns ['ok' => bool, 'http' => int, 'sid' => ?string, 'status' => ?string, 'error' => ?string].
 */
function twilioSend(string $to, string $body): array {
    $sid   = $_ENV['TWILIO_ACCOUNT_SID'] ?? '';
    $token = $_ENV['TWILIO_AUTH_TOKEN']  ?? '';
    $from  = $_ENV['TWILIO_FROM_NUMBER'] ?? '';
    if (!$sid || !$token || !$from) {
        return ['ok' => false, 'http' => 0, 'sid' => null, 'status' => null,
                'error' => 'Twilio account SID, auth token or from-number missing from .env'];
    }

    $ch = curl_init("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_USERPWD        => "{$sid}:{$token}",
        CURLOPT_POSTFIELDS     => http_build_query(['To' => $to, 'From' => $from, 'Body' => $body]),
        CURLOPT_TIMEOUT        => 15,
    ]);
    $res  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    $j  = is_string($res) ? (json_decode($res, true) ?: []) : [];
    $ok = $code >= 200 && $code < 300;
    return [
        'ok'     => $ok,
        'http'   => $code,
        'sid'    => $j['sid']    ?? null,
        'status' => $j['status'] ?? null,
        // Twilio's own error code and text, e.g. "21610 Attempt to send to
        // unsubscribed recipient" — the thing worth knowing when a text never
        // turns up.
        'error'  => $ok ? null : trim(($j['code'] ?? '') . ' ' . ($j['message'] ?? ($cerr ?: 'HTTP ' . $code))),
    ];
}

function sendSms(string $to, string $body): bool {
    $r = twilioSend($to, $body);
    // Failures used to be discarded outright, which is how a missing opt-in
    // confirmation went unexplained. They now land in the PHP error log.
    if (!$r['ok']) error_log('sendSms to ' . substr($to, 0, 5) . '... failed: ' . $r['error']);
    return $r['ok'];
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

// GET ?action=week&week_key=YYYY-MM-DD — one week's meetings, dearest first.
// Cost is the entire point of the app, so the ranking leads with it.
if ($method === 'GET' && $action === 'week') {
    $week_key = $_GET['week_key'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_key)) {
        http_response_code(400); echo json_encode(['error' => 'Invalid week_key.']); exit;
    }
    $stmt = $pdo->prepare("SELECT * FROM meetings WHERE week_key = ? ORDER BY cost DESC");
    $stmt->execute([$week_key]);
    echo json_encode($stmt->fetchAll()); exit;
}

// POST ?action=save  body: { title, cost, seconds, week_key }
// Title, cost and length must all be present and positive. A meeting with no
// cost is a timer that was never really started, and keeping it only adds noise
// to the week.
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

// DELETE ?action=clear_week&week_key=YYYY-MM-DD — drop one week's meetings.
// Answers with the number of rows removed, so the caller can tell a cleared
// week from a week_key that matched nothing.
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

// GET ?action=track_sessions — every saved session, newest first.
// Athletes ride along as a JSON blob and are decoded on the way out: the
// roster changes every session, so there is nothing stable to make columns of.
if ($method === 'GET' && $action === 'track_sessions') {
    $stmt = $pdo->query("SELECT * FROM track_sessions ORDER BY created_at DESC");
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) { $row['athletes'] = json_decode($row['athletes'], true); $row['duration'] = (int)$row['duration']; }
    echo json_encode($rows); exit;
}

// POST ?action=save_track_session  body: { name, duration, athletes[] }
// Athletes are stored as given — the app owns their shape, not the database.
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

// DELETE ?action=delete_track_session&id=X — remove a single session.
if ($method === 'DELETE' && $action === 'delete_track_session') {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'Invalid id.']); exit; }
    $stmt = $pdo->prepare("DELETE FROM track_sessions WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]); exit;
}

// DELETE ?action=clear_track_sessions — remove every saved session.
// Not scoped to a week or a user: it empties the table.
if ($method === 'DELETE' && $action === 'clear_track_sessions') {
    $pdo->exec("DELETE FROM track_sessions");
    echo json_encode(['success' => true]); exit;
}

// ══════════════════════════════════════════════════════════════
// Toolshare — AUTH
// ══════════════════════════════════════════════════════════════

// POST ?action=tb_register  body: { username, display_name, email, password }
// Signs the new account straight in and returns its token — registering and
// then being asked to log in is a step with no purpose. A taken username is the
// only way the insert can fail, so that is what the conflict reports.
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
        $stmt = $pdo->prepare("INSERT INTO tb_users (username, password_hash, display_name, email) VALUES (?,?,?,?)");
        $stmt->execute([$uname, $hash, $dname, $email]);
        $id = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO tb_sessions (token, user_id) VALUES (?, ?)")->execute([$token, $id]);
        echo json_encode(['success' => true, 'token' => $token, 'user' => ['id' => (int)$id, 'username' => $uname, 'display_name' => $dname, 'email' => $email]]);
    } catch (PDOException $e) {
        // 1062 is MySQL's duplicate-key error; anything else is a real failure
        // and must not be reported to the user as a naming problem.
        if (($e->errorInfo[1] ?? 0) == 1062) {
            http_response_code(409); echo json_encode(['error' => 'Username already taken.']);
        } else {
            http_response_code(500); echo json_encode(['error' => 'Could not create account. Please try again.']);
        }
    }
    exit;
}

// POST ?action=tb_login  body: { username, password }
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
    // A new session per login — does not invalidate tokens issued to other devices.
    $pdo->prepare("INSERT INTO tb_sessions (token, user_id) VALUES (?, ?)")->execute([$token, $u['id']]);
    echo json_encode(['success' => true, 'token' => $token, 'user' => ['id' => $u['id'], 'username' => $u['username'], 'display_name' => $u['display_name'], 'email' => $u['email']]]);
    exit;
}

// POST ?action=tb_logout — ends only this device's session, other devices stay signed in
if ($method === 'POST' && $action === 'tb_logout') {
    requireAuth($pdo);
    $token = trim($_SERVER['HTTP_X_AUTH_TOKEN'] ?? '');
    $pdo->prepare("DELETE FROM tb_sessions WHERE token = ?")->execute([$token]);
    echo json_encode(['success' => true]); exit;
}

// ══════════════════════════════════════════════════════════════
// Toolshare — TOOLS
// ══════════════════════════════════════════════════════════════

// Helper: validate and store the uploaded $_FILES['photo'], returning its public
// URL. Ends the request with a 400/500 itself, so callers only see success.
function tbSavePhoto(string $upload_dir, string $upload_url): string {
    $ext   = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    $allow = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($ext, $allow)) { http_response_code(400); echo json_encode(['error' => 'Invalid image type.']); exit; }
    if ($_FILES['photo']['size'] > 5 * 1024 * 1024) { http_response_code(400); echo json_encode(['error' => 'Photo must be under 5 MB.']); exit; }
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $fname = uniqid('tool_', true) . '.' . $ext;
    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $fname)) {
        http_response_code(500); echo json_encode(['error' => 'Could not save photo.']); exit;
    }
    return $upload_url . $fname;
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
        $photo_url = tbSavePhoto($upload_dir, $upload_url);
    }

    $stmt = $pdo->prepare("INSERT INTO tb_tools (owner_id, name, brand, category, notes, photo_url) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$me['id'], $name, $brand ?: null, $category ?: null, $notes ?: null, $photo_url]);
    $id = $pdo->lastInsertId();

    $row = $pdo->prepare("SELECT * FROM tb_tools WHERE id = ?");
    $row->execute([$id]);
    echo json_encode(['success' => true, 'tool' => $row->fetch()]); exit;
}

// POST ?action=tb_edit_tool&id=X
// Multipart, owner only. A new photo replaces the old one and the old file is
// deleted; sending no photo keeps the current one.
if ($method === 'POST' && $action === 'tb_edit_tool') {
    $me    = requireAuth($pdo);
    $id    = intval($_POST['id'] ?? $_GET['id'] ?? 0);
    $check = $pdo->prepare("SELECT * FROM tb_tools WHERE id = ? AND owner_id = ?");
    $check->execute([$id, $me['id']]);
    $existing = $check->fetch();
    if (!$existing) { http_response_code(403); echo json_encode(['error' => 'Not your tool.']); exit; }

    $name     = trim($_POST['name']     ?? '');
    $brand    = trim($_POST['brand']    ?? '');
    $category = trim($_POST['category'] ?? '');
    $notes    = trim($_POST['notes']    ?? '');
    if (!$name) { http_response_code(400); echo json_encode(['error' => 'Tool name required.']); exit; }

    if (!empty($_FILES['photo']['tmp_name'])) {
        $photo_url = tbSavePhoto($upload_dir, $upload_url);
        $pdo->prepare("UPDATE tb_tools SET name=?,brand=?,category=?,notes=?,photo_url=? WHERE id=?")
            ->execute([$name, $brand ?: null, $category ?: null, $notes ?: null, $photo_url, $id]);
        // The replaced photo is referenced by nothing once the row points at the new one.
        if ($existing['photo_url']) @unlink($upload_dir . basename($existing['photo_url']));
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

    $prompt = 'Analyse this image. Respond with ONLY a JSON object (no markdown, no explanation) with these exact keys:
"appropriate": true if this is a photo of a real tool or equipment suitable for a tool-sharing app, false if it contains people, nudity, explicit content, offensive material, or is clearly not a tool
"name": the specific tool name (e.g. "Circular Saw", "Cordless Drill", "Tape Measure"), or "" if not appropriate
"brand": the brand/manufacturer if clearly visible (e.g. "DeWalt", "Milwaukee", "Makita"), or "" if not visible or not appropriate
"tags": a JSON array of 1-4 short relevant tags (e.g. ["Power Tools", "Cordless", "Drilling"]), or [] if not appropriate

Example (tool): {"appropriate":true,"name":"Cordless Drill","brand":"DeWalt","tags":["Power Tools","Cordless","Drilling"]}
Example (not a tool): {"appropriate":false,"name":"","brand":"","tags":[]}';

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

    $data = json_decode($response, true);
    $text = trim($data['content'][0]['text'] ?? '');
    // Strip markdown code fences if Claude wrapped the JSON
    $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
    $text = preg_replace('/\s*```$/m', '', $text);
    $text = trim($text);
    $result = json_decode($text, true);

    if (!$result || !array_key_exists('appropriate', $result)) {
        http_response_code(502); echo json_encode(['error' => 'Could not identify tool.']); exit;
    }

    $tags = array_values(array_filter(array_map('trim', (array)($result['tags'] ?? []))));
    echo json_encode(['success' => true, 'tool' => [
        'appropriate' => (bool)$result['appropriate'],
        'name'        => $result['name']  ?? '',
        'brand'       => $result['brand'] ?? '',
        'tags'        => $tags,
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
// Only friends of the owner may ask. A stranger's tool answers exactly like a
// missing one, so sequential ids cannot be walked to email every owner.
if ($method === 'POST' && $action === 'tb_request') {
    $me   = requireAuth($pdo);
    $body = json_decode(file_get_contents('php://input'), true);
    $tool_id = intval($body['tool_id'] ?? 0);
    $message = mb_substr(trim($body['message'] ?? ''), 0, 1000);

    // fetch tool + owner
    $stmt = $pdo->prepare("SELECT t.*, u.id AS uid, u.display_name, u.email FROM tb_tools t JOIN tb_users u ON u.id = t.owner_id WHERE t.id = ?");
    $stmt->execute([$tool_id]);
    $tool = $stmt->fetch();
    if ($tool && $tool['owner_id'] == $me['id']) { http_response_code(400); echo json_encode(['error' => "You can't borrow your own tool."]); exit; }
    if (!$tool || !areFriends($pdo, (int)$me['id'], (int)$tool['owner_id'])) {
        http_response_code(404); echo json_encode(['error' => 'Tool not found.']); exit;
    }
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

    // Verify ownership and apply the change in one transaction, locking the
    // request and tool rows so two taps (or two devices) cannot both approve,
    // and a tool that is already out cannot be lent a second time.
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT r.*, t.name AS tool_name, t.status AS tool_status FROM tb_requests r JOIN tb_tools t ON t.id = r.tool_id WHERE r.id = ? AND r.owner_id = ? AND r.status = 'pending' FOR UPDATE");
    $stmt->execute([$req_id, $me['id']]);
    $req = $stmt->fetch();
    if (!$req) { $pdo->rollBack(); http_response_code(404); echo json_encode(['error' => 'Request not found or already actioned.']); exit; }
    if ($status === 'approved' && $req['tool_status'] === 'borrowed') {
        $pdo->rollBack(); http_response_code(409); echo json_encode(['error' => 'That tool is already lent out.']); exit;
    }

    $pdo->prepare("UPDATE tb_requests SET status = ? WHERE id = ?")->execute([$status, $req_id]);

    if ($status === 'approved') {
        // mark tool borrowed and decline all other pending requests for same tool
        $pdo->prepare("UPDATE tb_tools SET status = 'borrowed' WHERE id = ?")->execute([$req['tool_id']]);
        $pdo->prepare("UPDATE tb_requests SET status = 'declined' WHERE tool_id = ? AND id != ? AND status = 'pending'")
            ->execute([$req['tool_id'], $req_id]);
    }
    $pdo->commit();

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
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT * FROM tb_requests WHERE id = ? AND status = 'approved' AND (owner_id = ? OR requester_id = ?) FOR UPDATE");
    $stmt->execute([$req_id, $me['id'], $me['id']]);
    $req = $stmt->fetch();
    if (!$req) { $pdo->rollBack(); http_response_code(404); echo json_encode(['error' => 'Active loan not found.']); exit; }
    $pdo->prepare("UPDATE tb_requests SET status = 'returned' WHERE id = ?")->execute([$req_id]);
    $pdo->prepare("UPDATE tb_tools SET status = 'available' WHERE id = ?")->execute([$req['tool_id']]);
    $pdo->commit();
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

// GET ?action=tb_tag_suggestions — unique tags from visible tools
if ($method === 'GET' && $action === 'tb_tag_suggestions') {
    $me = requireAuth($pdo);
    $stmt = $pdo->prepare("
        SELECT category FROM tb_tools
        WHERE category IS NOT NULL AND category != ''
          AND (owner_id = :me OR owner_id IN (
              SELECT IF(user_a = :me2, user_b, user_a) FROM tb_friendships WHERE user_a = :me3 OR user_b = :me4
          ))
    ");
    $stmt->execute([':me'=>$me['id'],':me2'=>$me['id'],':me3'=>$me['id'],':me4'=>$me['id']]);
    $tags = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $row) {
        foreach (explode(',', $row) as $tag) {
            $tag = trim($tag);
            if ($tag) $tags[$tag] = true;
        }
    }
    echo json_encode(array_values(array_keys($tags))); exit;
}

// GET ?action=tb_my_invite — this user's invite code, minted on first request.
// Codes are created lazily, so an account that never shares anything never
// carries one.
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

// GET ?action=tb_friends — everyone this user shares with, and how many tools
// each of them has. Friendships are stored undirected, so the query checks both
// columns and takes whichever side is not you.
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

// ══════════════════════════════════════════════════════════════
// FACE BREAKER — LEADERBOARD
// ══════════════════════════════════════════════════════════════

// GET ?action=fb_leaderboard — top 10 for the current ISO week (no auth)
if ($method === 'GET' && $action === 'fb_leaderboard') {
    $now = new DateTime();
    $now->setISODate((int)$now->format('o'), (int)$now->format('W'));
    $weekStart = $now->format('Y-m-d');
    $stmt = $pdo->prepare("SELECT name, score FROM fb_scores WHERE week_start = ? ORDER BY score DESC LIMIT 10");
    $stmt->execute([$weekStart]);
    echo json_encode(['success' => true, 'scores' => $stmt->fetchAll()]); exit;
}

// POST ?action=fb_save_score  body: { name, score } — no auth required
if ($method === 'POST' && $action === 'fb_save_score') {
    $body  = json_decode(file_get_contents('php://input'), true);
    $name  = mb_substr(strip_tags(trim($body['name'] ?? '')), 0, 32);
    $score = intval($body['score'] ?? 0);
    if (!$name || $score <= 0) {
        http_response_code(400); echo json_encode(['error' => 'Invalid input.']); exit;
    }
    $now = new DateTime();
    $now->setISODate((int)$now->format('o'), (int)$now->format('W'));
    $weekStart = $now->format('Y-m-d');
    // Count how many scores strictly beat this one this week
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM fb_scores WHERE week_start = ? AND score > ?");
    $stmt->execute([$weekStart, $score]);
    $above = (int)$stmt->fetchColumn();
    if ($above >= 10) {
        echo json_encode(['success' => false, 'message' => 'Score did not make top 10.']); exit;
    }
    $pdo->prepare("INSERT INTO fb_scores (name, score, week_start) VALUES (?, ?, ?)")
        ->execute([$name, $score, $weekStart]);
    echo json_encode(['success' => true, 'rank' => $above + 1]); exit;
}

// ══════════════════════════════════════════════════════════════
// REACTION TEST — LEADERBOARD
// ══════════════════════════════════════════════════════════════

// GET ?action=reaction_week&week_key=YYYY-MM-DD — the week's ten fastest.
if ($method === 'GET' && $action === 'reaction_week') {
    $week_key = $_GET['week_key'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_key)) {
        http_response_code(400); echo json_encode(['error' => 'Invalid week_key.']); exit;
    }
    $stmt = $pdo->prepare("SELECT id, name, avg_ms, created_at FROM reaction_scores WHERE week_key = ? ORDER BY avg_ms ASC LIMIT 10");
    $stmt->execute([$week_key]);
    echo json_encode($stmt->fetchAll()); exit;
}

// POST ?action=save_reaction  body: { name, avg_ms, week_key }
// Only a top-ten time is kept. A slower one comes back as success:false with a
// 200, not an error — missing the board is an ordinary outcome of playing, and
// the app should be able to say so without dressing it up as a failure.
if ($method === 'POST' && $action === 'save_reaction') {
    $body     = json_decode(file_get_contents('php://input'), true);
    $name     = mb_substr(strip_tags(trim($body['name'] ?? '')), 0, 60);
    $avg_ms   = intval($body['avg_ms']   ?? 0);
    $week_key = trim($body['week_key'] ?? '');
    if (!$name || $avg_ms <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_key)) {
        http_response_code(400); echo json_encode(['error' => 'Missing or invalid fields.']); exit;
    }
    // Only allow save if score is top 10 for the week
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reaction_scores WHERE week_key = ? AND avg_ms < ?");
    $stmt->execute([$week_key, $avg_ms]);
    $above = (int)$stmt->fetchColumn();
    if ($above >= 10) {
        echo json_encode(['success' => false, 'message' => 'Score did not make top 10.']); exit;
    }
    $pdo->prepare("INSERT INTO reaction_scores (name, avg_ms, week_key) VALUES (?, ?, ?)")
        ->execute([$name, $avg_ms, $week_key]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]); exit;
}

// DELETE ?action=clear_reaction_week&week_key=YYYY-MM-DD — reset one week.
if ($method === 'DELETE' && $action === 'clear_reaction_week') {
    $week_key = $_GET['week_key'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_key)) {
        http_response_code(400); echo json_encode(['error' => 'Invalid week_key.']); exit;
    }
    $stmt = $pdo->prepare("DELETE FROM reaction_scores WHERE week_key = ?");
    $stmt->execute([$week_key]);
    echo json_encode(['success' => true, 'deleted' => $stmt->rowCount()]); exit;
}

// ══════════════════════════════════════════════════════════════
// FLIGHT TRACKER
// ══════════════════════════════════════════════════════════════

$pdo->exec("CREATE TABLE IF NOT EXISTS ft_users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(60)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name  VARCHAR(80)  NOT NULL,
    token         VARCHAR(64)  DEFAULT NULL,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS ft_flights (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT          NOT NULL,
    from_code      VARCHAR(4)   NOT NULL,
    to_code        VARCHAR(4)   NOT NULL,
    from_city      VARCHAR(100) NOT NULL,
    to_city        VARCHAR(100) NOT NULL,
    flight_date    DATE         NOT NULL,
    airline        VARCHAR(80)  DEFAULT NULL,
    seat_class     VARCHAR(30)  DEFAULT NULL,
    flight_number  VARCHAR(20)  DEFAULT NULL,
    created_at     DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES ft_users(id) ON DELETE CASCADE
)");

function ftAuthUser(PDO $pdo): ?array {
    $token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    if (!$token) return null;
    $stmt = $pdo->prepare("SELECT * FROM ft_users WHERE token = ?");
    $stmt->execute([trim($token)]);
    return $stmt->fetch() ?: null;
}

function ftRequireAuth(PDO $pdo): array {
    $user = ftAuthUser($pdo);
    if (!$user) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
    return $user;
}

// POST ?action=ft_register  body: { username, display_name, password }
// The token lives on the user row rather than in a sessions table, unlike
// Toolshare — one signed-in device at a time is all this app has needed.
if ($method === 'POST' && $action === 'ft_register') {
    $body  = json_decode(file_get_contents('php://input'), true);
    $uname = trim($body['username']     ?? '');
    $dname = trim($body['display_name'] ?? '');
    $pw    = $body['password'] ?? '';
    if (!$uname || !$dname || !$pw) {
        http_response_code(400); echo json_encode(['error' => 'All fields required.']); exit;
    }
    if (strlen($pw) < 6) {
        http_response_code(400); echo json_encode(['error' => 'Password must be at least 6 characters.']); exit;
    }
    $hash  = password_hash($pw, PASSWORD_DEFAULT);
    $token = bin2hex(random_bytes(32));
    try {
        $stmt = $pdo->prepare("INSERT INTO ft_users (username, password_hash, display_name, token) VALUES (?,?,?,?)");
        $stmt->execute([$uname, $hash, $dname, $token]);
        $id = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'token' => $token, 'user' => ['id' => $id, 'username' => $uname, 'display_name' => $dname]]);
    } catch (PDOException $e) {
        http_response_code(409); echo json_encode(['error' => 'Username already taken.']);
    }
    exit;
}

// POST ?action=ft_login  body: { username, password }
// Issues a fresh token over the top of the old one, so signing in here signs
// out whichever device was signed in before.
if ($method === 'POST' && $action === 'ft_login') {
    $body  = json_decode(file_get_contents('php://input'), true);
    $uname = trim($body['username'] ?? '');
    $pw    = $body['password'] ?? '';
    $stmt  = $pdo->prepare("SELECT * FROM ft_users WHERE username = ?");
    $stmt->execute([$uname]);
    $u = $stmt->fetch();
    if (!$u || !password_verify($pw, $u['password_hash'])) {
        http_response_code(401); echo json_encode(['error' => 'Invalid username or password.']); exit;
    }
    $token = bin2hex(random_bytes(32));
    $pdo->prepare("UPDATE ft_users SET token = ? WHERE id = ?")->execute([$token, $u['id']]);
    echo json_encode(['success' => true, 'token' => $token, 'user' => ['id' => $u['id'], 'username' => $u['username'], 'display_name' => $u['display_name']]]);
    exit;
}

// POST ?action=ft_logout — clears the stored token.
if ($method === 'POST' && $action === 'ft_logout') {
    $user = ftRequireAuth($pdo);
    $pdo->prepare("UPDATE ft_users SET token = NULL WHERE id = ?")->execute([$user['id']]);
    echo json_encode(['success' => true]); exit;
}

// GET ?action=ft_flights — this user's flights, most recent first.
if ($method === 'GET' && $action === 'ft_flights') {
    $me   = ftRequireAuth($pdo);
    $stmt = $pdo->prepare("SELECT * FROM ft_flights WHERE user_id = ? ORDER BY flight_date DESC, created_at DESC");
    $stmt->execute([$me['id']]);
    echo json_encode(['success' => true, 'flights' => $stmt->fetchAll()]); exit;
}

// POST ?action=ft_add_flight
// body: { from_code, to_code, from_city, to_city, flight_date,
//         airline?, seat_class?, flight_number? }
// Airport codes are upper-cased on the way in, and a flight that lands where it
// started is refused rather than stored as a curiosity.
if ($method === 'POST' && $action === 'ft_add_flight') {
    $me   = ftRequireAuth($pdo);
    $body = json_decode(file_get_contents('php://input'), true);
    $from_code = strtoupper(trim($body['from_code'] ?? ''));
    $to_code   = strtoupper(trim($body['to_code']   ?? ''));
    $from_city = trim($body['from_city'] ?? '');
    $to_city   = trim($body['to_city']   ?? '');
    $date      = trim($body['flight_date'] ?? '');
    if (!$from_code || !$to_code || !$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        http_response_code(400); echo json_encode(['error' => 'Missing required fields.']); exit;
    }
    if ($from_code === $to_code) {
        http_response_code(400); echo json_encode(['error' => 'Departure and arrival must differ.']); exit;
    }
    $airline       = trim($body['airline']       ?? '') ?: null;
    $seat_class    = trim($body['seat_class']    ?? '') ?: null;
    $flight_number = trim($body['flight_number'] ?? '') ?: null;
    $stmt = $pdo->prepare("INSERT INTO ft_flights (user_id,from_code,to_code,from_city,to_city,flight_date,airline,seat_class,flight_number) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$me['id'],$from_code,$to_code,$from_city,$to_city,$date,$airline,$seat_class,$flight_number]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]); exit;
}

// POST ?action=ft_add_flights  body: { flights: [ … ] } — bulk import.
// Rows that fail validation are skipped instead of failing the batch, and the
// reply counts what landed. An import of fifty flights is still worth keeping
// when two lines are malformed, and the caller can see the shortfall.
if ($method === 'POST' && $action === 'ft_add_flights') {
    $me   = ftRequireAuth($pdo);
    $body = json_decode(file_get_contents('php://input'), true);
    $rows = $body['flights'] ?? [];
    if (!is_array($rows) || empty($rows)) {
        http_response_code(400); echo json_encode(['error' => 'No flights provided.']); exit;
    }
    $stmt = $pdo->prepare("INSERT INTO ft_flights (user_id,from_code,to_code,from_city,to_city,flight_date,airline,flight_number) VALUES (?,?,?,?,?,?,?,?)");
    $inserted = 0;
    foreach ($rows as $f) {
        $from  = strtoupper(trim($f['from_code'] ?? ''));
        $to    = strtoupper(trim($f['to_code']   ?? ''));
        $date  = trim($f['flight_date'] ?? '');
        if (!$from || !$to || $from === $to || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) continue;
        $stmt->execute([
            $me['id'], $from, $to,
            trim($f['from_city'] ?? ''), trim($f['to_city'] ?? ''),
            $date,
            trim($f['airline']       ?? '') ?: null,
            trim($f['flight_number'] ?? '') ?: null,
        ]);
        $inserted++;
    }
    echo json_encode(['success' => true, 'inserted' => $inserted]); exit;
}

// DELETE ?action=ft_delete_flight&id=X — remove one of this user's flights.
// Ownership is part of the WHERE clause, so a guessed id belonging to someone
// else matches nothing and answers 404 rather than deleting their row.
if ($method === 'DELETE' && $action === 'ft_delete_flight') {
    $me = ftRequireAuth($pdo);
    $id = intval($_GET['id'] ?? 0);
    if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id.']); exit; }
    $stmt = $pdo->prepare("DELETE FROM ft_flights WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $me['id']]);
    if (!$stmt->rowCount()) { http_response_code(404); echo json_encode(['error' => 'Not found.']); exit; }
    echo json_encode(['success' => true]); exit;
}

// ══════════════════════════════════════════════════════════════
// DAILY TASKS — LEADERBOARD
// ══════════════════════════════════════════════════════════════

// GET ?action=dt_week&week_key=YYYY-MM-DD — the week's leaderboard.
// Ranked by tasks finished, then by time taken: doing more wins, and doing the
// same amount faster settles the tie.
if ($method === 'GET' && $action === 'dt_week') {
    $week_key = $_GET['week_key'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_key)) {
        http_response_code(400); echo json_encode(['error' => 'Invalid week_key.']); exit;
    }
    $stmt = $pdo->prepare("SELECT * FROM dt_scores WHERE week_key = ? ORDER BY task_count DESC, total_seconds ASC");
    $stmt->execute([$week_key]);
    echo json_encode($stmt->fetchAll()); exit;
}

// POST ?action=dt_save  body: { name, task_count, total_seconds, week_key }
if ($method === 'POST' && $action === 'dt_save') {
    $body          = json_decode(file_get_contents('php://input'), true);
    $name          = mb_substr(strip_tags(trim($body['name'] ?? '')), 0, 60);
    $task_count    = intval($body['task_count']    ?? 0);
    $total_seconds = intval($body['total_seconds'] ?? 0);
    $week_key      = trim($body['week_key'] ?? '');
    if (!$name || $task_count <= 0 || $total_seconds <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_key)) {
        http_response_code(400); echo json_encode(['error' => 'Missing or invalid fields.']); exit;
    }
    $stmt = $pdo->prepare("INSERT INTO dt_scores (name, task_count, total_seconds, week_key) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $task_count, $total_seconds, $week_key]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]); exit;
}

// ══════════════════════════════════════════════════════════════
// DAILY TASKS — CROSS-DEVICE SYNC (optional login, shared tb_users accounts)
// ══════════════════════════════════════════════════════════════

// GET ?action=dt_get_tasks&date=YYYY-MM-DD — load this user's task list for a given day
if ($method === 'GET' && $action === 'dt_get_tasks') {
    $me   = requireAuth($pdo);
    $date = $_GET['date'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        http_response_code(400); echo json_encode(['error' => 'Invalid date.']); exit;
    }
    $stmt = $pdo->prepare("SELECT tasks_json, next_id, updated_at FROM dt_tasks WHERE user_id = ? AND date_key = ?");
    $stmt->execute([$me['id'], $date]);
    $row = $stmt->fetch();
    if (!$row) { echo json_encode(['success' => true, 'found' => false]); exit; }
    echo json_encode([
        'success'    => true,
        'found'      => true,
        'tasks'      => json_decode($row['tasks_json'], true) ?: [],
        'next_id'    => (int)$row['next_id'],
        'updated_at' => $row['updated_at'],
    ]); exit;
}

// POST ?action=dt_save_tasks  body: { date, tasks, next_id } — upsert this user's task list for a given day
if ($method === 'POST' && $action === 'dt_save_tasks') {
    $me   = requireAuth($pdo);
    $body = json_decode(file_get_contents('php://input'), true);
    $date = trim($body['date'] ?? '');
    $tasks = $body['tasks'] ?? null;
    $next_id = intval($body['next_id'] ?? 1);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !is_array($tasks)) {
        http_response_code(400); echo json_encode(['error' => 'Missing or invalid fields.']); exit;
    }
    $stmt = $pdo->prepare("
        INSERT INTO dt_tasks (user_id, date_key, tasks_json, next_id) VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE tasks_json = VALUES(tasks_json), next_id = VALUES(next_id)
    ");
    $stmt->execute([$me['id'], $date, json_encode($tasks), $next_id]);
    echo json_encode(['success' => true]); exit;
}

// ══════════════════════════════════════════════════════════════
// UPDATE NOTIFICATIONS — SUBSCRIBERS
// ══════════════════════════════════════════════════════════════

// POST ?action=subscribe  body: { contact_type: 'email'|'phone', contact_value, website? }
// "website" is an invisible honeypot field — real users never fill it in.
if ($method === 'POST' && $action === 'subscribe') {
    $body = json_decode(file_get_contents('php://input'), true);

    if (!empty($body['website'])) { echo json_encode(['success' => true]); exit; } // bot — pretend success

    $type = $body['contact_type']  ?? '';
    $raw  = trim($body['contact_value'] ?? '');

    if ($type === 'email') {
        if (!filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400); echo json_encode(['error' => 'Enter a valid email address.']); exit;
        }
        $value = strtolower($raw);
    } elseif ($type === 'phone') {
        $digits = preg_replace('/[^\d+]/', '', $raw);
        if (!str_starts_with($digits, '+')) {
            $digits = (strlen($digits) === 10) ? '+1' . $digits : '+' . $digits;
        }
        if (!preg_match('/^\+[1-9]\d{7,14}$/', $digits)) {
            http_response_code(400); echo json_encode(['error' => 'Enter a valid phone number.']); exit;
        }
        // US and Canada only. The A2P 10DLC campaign covers US messaging, and a
        // public form that will text any international number is exactly what
        // SMS-pumping bots look for. NANP shape: area code and exchange both
        // start 2-9. +1 also covers Caribbean nations, so Twilio's Messaging
        // Geo Permissions stay the backstop for those.
        if (!preg_match('/^\+1[2-9]\d{2}[2-9]\d{6}$/', $digits)) {
            http_response_code(400); echo json_encode(['error' => 'Texts are available to US and Canadian numbers only.']); exit;
        }
        $value = $digits;
    } else {
        http_response_code(400); echo json_encode(['error' => 'contact_type must be email or phone.']); exit;
    }

    // At most 5 sign-ups an hour from one IP. A per-number cooldown alone does
    // nothing against a bot cycling through fresh numbers. REMOTE_ADDR, not
    // X-Forwarded-For, because the header is whatever the client says it is.
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $pdo->exec("DELETE FROM subscribe_attempts WHERE created_at < NOW() - INTERVAL 1 DAY");
    $st = $pdo->prepare("SELECT COUNT(*) FROM subscribe_attempts WHERE ip = ? AND created_at > NOW() - INTERVAL 1 HOUR");
    $st->execute([$ip]);
    if ((int)$st->fetchColumn() >= 5) {
        http_response_code(429); echo json_encode(['error' => 'Too many sign-ups from this connection. Try again in an hour.']); exit;
    }

    $token = bin2hex(random_bytes(16));
    try {
        $stmt = $pdo->prepare("INSERT INTO subscribers (contact_type, contact_value, unsub_token) VALUES (?,?,?)");
        $stmt->execute([$type, $value, $token]);
    } catch (PDOException $e) {
        // already subscribed — treat as success, no need to leak that to the client
    }

    // Confirming an opt-in by text is what the A2P campaign registration
    // declares happens, so it has to actually happen. Word for word the same
    // message filed as the campaign's opt-in message — a reviewer may compare.
    //
    // Sent on every opt-in, not just a new number, matching the START keyword:
    // someone re-submitting the form is asking whether they are signed up, and
    // the confirmation is the answer. It also means the response looks the
    // same either way, so it still does not reveal who is on the list.
    //
    // At most once per number per 10 minutes, so the form cannot be used to
    // flood one phone. A skipped send still reports success, for the same
    // reason.
    $texted = false;
    if ($type === 'phone') {
        $st = $pdo->prepare("SELECT 1 FROM subscribe_attempts WHERE contact_value = ? AND texted = 1 AND created_at > NOW() - INTERVAL 10 MINUTE LIMIT 1");
        $st->execute([$value]);
        if (!$st->fetchColumn()) {
            sendSms($value, optInMessage());
            $texted = true;
        }
    }
    $pdo->prepare("INSERT INTO subscribe_attempts (ip, contact_value, texted) VALUES (?,?,?)")
        ->execute([$ip, $value, $texted ? 1 : 0]);

    echo json_encode(['success' => true]); exit;
}

// GET ?action=unsubscribe&token=xxx — clicked from an email/SMS, so it renders HTML, not JSON
if ($method === 'GET' && $action === 'unsubscribe') {
    $token = trim($_GET['token'] ?? '');
    $stmt  = $pdo->prepare("DELETE FROM subscribers WHERE unsub_token = ?");
    $stmt->execute([$token]);
    $msg = $stmt->rowCount()
        ? "You've been unsubscribed from davenn.com updates."
        : "This unsubscribe link is invalid or already used.";
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Unsubscribed — davenn.com</title>
    <style>body{font-family:sans-serif;max-width:480px;margin:80px auto;text-align:center;color:#111;padding:0 20px;}
    a{color:#111;}</style></head><body><h2>{$msg}</h2><p><a href='/'>Return to davenn.com</a></p></body></html>";
    exit;
}

// GET ?action=sms_diag&to=…  header: X-Admin-Secret
// Why did a text not arrive? Reports whether this server has Twilio configured
// and whether the number is on the subscriber list, which between them explain
// most silent failures. Sends nothing. Admin-gated because it reports on the
// host's configuration.
if ($method === 'GET' && $action === 'sms_diag') {
    $admin_secret = $_ENV['ADMIN_SECRET'] ?? '';
    if (!$admin_secret || !hash_equals($admin_secret, $_SERVER['HTTP_X_ADMIN_SECRET'] ?? '')) {
        http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit;
    }
    $to   = cpNormalisePhone((string)($_GET['to'] ?? ''));
    $from = (string)($_ENV['TWILIO_FROM_NUMBER'] ?? '');
    $sub  = null;
    if ($to !== '') {
        $st = $pdo->prepare("SELECT created_at FROM subscribers WHERE contact_type = 'phone' AND contact_value = ?");
        $st->execute([$to]);
        $sub = $st->fetchColumn() ?: null;
    }
    echo json_encode([
        'success' => true,
        'env'     => [
            'account_sid' => !empty($_ENV['TWILIO_ACCOUNT_SID']),
            'auth_token'  => !empty($_ENV['TWILIO_AUTH_TOKEN']),
            'from_number' => $from === '' ? null : substr($from, 0, 5) . '...' . substr($from, -4),
        ],
        'to'          => $to ?: null,
        'subscribed'  => $sub !== null,
        'subscribed_at' => $sub,
        'curl'        => function_exists('curl_init'),
    ]);
    exit;
}

// POST ?action=sms_diag&to=…  header: X-Admin-Secret
// Sends the opt-in confirmation to one number and returns Twilio's verdict
// verbatim — HTTP status, message SID and error code. The only way to see why
// a send failed without reading the host's error log. Costs one message.
if ($method === 'POST' && $action === 'sms_diag') {
    $admin_secret = $_ENV['ADMIN_SECRET'] ?? '';
    if (!$admin_secret || !hash_equals($admin_secret, $_SERVER['HTTP_X_ADMIN_SECRET'] ?? '')) {
        http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit;
    }
    $to = cpNormalisePhone((string)($_GET['to'] ?? ''));
    if ($to === '') { http_response_code(400); echo json_encode(['error' => 'Pass ?to=']); exit; }
    echo json_encode(['success' => true, 'to' => $to, 'twilio' => twilioSend($to, optInMessage())]);
    exit;
}

// POST ?action=notify_subscribers  header: X-Admin-Secret  body: { message }
// Called by the GitHub Actions deploy workflow when CHANGELOG.md changes.
if ($method === 'POST' && $action === 'notify_subscribers') {
    $admin_secret = $_ENV['ADMIN_SECRET'] ?? '';
    $given        = $_SERVER['HTTP_X_ADMIN_SECRET'] ?? '';
    if (!$admin_secret || !hash_equals($admin_secret, $given)) {
        http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit;
    }

    $body    = json_decode(file_get_contents('php://input'), true);
    $message = trim($body['message'] ?? '');
    if (!$message) { http_response_code(400); echo json_encode(['error' => 'Missing message.']); exit; }

    $subs    = $pdo->query("SELECT * FROM subscribers")->fetchAll();
    $emailed = 0; $texted = 0; $failed = [];

    foreach ($subs as $sub) {
        $unsub_url = "https://davenn.com/api.php?action=unsubscribe&token=" . $sub['unsub_token'];
        if ($sub['contact_type'] === 'email') {
            $html = "<div style='font-family:sans-serif;max-width:520px;margin:0 auto;padding:24px;'>
              <h2 style='margin:0 0 16px;'>New on davenn.com</h2>
              <p style='white-space:pre-line;'>" . nl2br(htmlspecialchars($message)) . "</p>
              <p style='margin-top:24px;'>
                <a href='https://davenn.com' style='background:#111;color:#fff;padding:10px 20px;
                   border-radius:6px;text-decoration:none;font-weight:bold;'>Check it out</a>
              </p>
              <p style='margin-top:24px;font-size:12px;color:#999;'><a href='{$unsub_url}' style='color:#999;'>Unsubscribe</a></p>
            </div>";
            sendEmail($sub['contact_value'], '', 'davenn.com — new update', $html, $mail_from, 'davenn.com Updates');
            $emailed++;
        } elseif ($sub['contact_type'] === 'phone') {
            // Plain hyphen, not an em dash: anything outside GSM-7 forces the
            // whole message to UCS-2, which drops a segment from 160
            // characters to 70 and roughly doubles the cost. Keep CHANGELOG
            // entries ASCII for the same reason — they are the body here.
            $ok = sendSms($sub['contact_value'], $message . "\n\ndavenn.com - Reply STOP to unsubscribe.");
            if ($ok) $texted++; else $failed[] = $sub['contact_value'];
        }
    }

    echo json_encode(['success' => true, 'emailed' => $emailed, 'texted' => $texted, 'failed' => $failed]); exit;
}

// POST ?action=delete_subscriber  header: X-Admin-Secret  body: { contact_value }
// Removes one contact from the update list, e.g. to test the sign-up flow from
// a clean slate. Normalizes the value the same way subscribe does, so "555 010 0199"
// finds the row stored as "+15550100199". Admin-gated because it removes
// someone else's consent record and confirms whether a contact is on the list.
if ($method === 'POST' && $action === 'delete_subscriber') {
    $admin_secret = $_ENV['ADMIN_SECRET'] ?? '';
    $given        = $_SERVER['HTTP_X_ADMIN_SECRET'] ?? '';
    if (!$admin_secret || !hash_equals($admin_secret, $given)) {
        http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit;
    }

    $body = json_decode(file_get_contents('php://input'), true);
    $raw  = trim($body['contact_value'] ?? '');
    if ($raw === '') { http_response_code(400); echo json_encode(['error' => 'Missing contact_value.']); exit; }

    if (str_contains($raw, '@')) {
        $value = strtolower($raw);
    } else {
        $value = preg_replace('/[^\d+]/', '', $raw);
        if (!str_starts_with($value, '+')) {
            $value = (strlen($value) === 10) ? '+1' . $value : '+' . $value;
        }
    }

    $stmt = $pdo->prepare("DELETE FROM subscribers WHERE contact_value = ?");
    $stmt->execute([$value]);
    // Its rate-limit history too, or a re-test inside 10 minutes gets no text.
    $pdo->prepare("DELETE FROM subscribe_attempts WHERE contact_value = ?")->execute([$value]);
    echo json_encode(['success' => true, 'contact_value' => $value, 'deleted' => $stmt->rowCount()]); exit;
}

// GET ?action=resetTestPhone
// Removes the operator's own test phone from the list so sign-up can be tested
// from a clean slate. Takes no input and needs no secret, so it
// can be tapped from a phone browser. Safe to leave open only because it can
// touch exactly one row: the number comes from server configuration, never
// from the request, and the repo is public so it is not written here. With no
// test phone configured it does nothing.
if ($method === 'GET' && $action === 'resetTestPhone') {
    $phone = $_ENV['TEST_PHONE'] ?? '';
    if ($phone === '') {
        http_response_code(404); echo json_encode(['error' => 'No test phone configured.']); exit;
    }
    $stmt = $pdo->prepare("DELETE FROM subscribers WHERE contact_type = 'phone' AND contact_value = ?");
    $stmt->execute([$phone]);
    // Its rate-limit history too, so the cooldown and the per-IP cap do not
    // block the next test.
    $pdo->prepare("DELETE FROM subscribe_attempts WHERE contact_value = ?")->execute([$phone]);
    echo json_encode(['success' => true, 'deleted' => $stmt->rowCount()]); exit;
}

// ══════════════════════════════════════════════════════════════
// GLUCOSE (CGM)
// ══════════════════════════════════════════════════════════════
// Dexcom's Share API only ever serves a rolling 24h window, so nothing older
// than a day exists unless we capture it. The davenn-mcp poller pushes recent
// readings here; everything downstream (dashboards, wall displays, streaks)
// reads from bg_readings rather than touching Dexcom.
//
// reading_at is always stored in UTC.

// Reads are gated by a low-privilege, read-only token so wall displays and
// microcontrollers never hold the admin secret or the Dexcom credentials.
function bgReadAuth(): bool {
    $token = $_ENV['BG_READ_TOKEN'] ?? '';
    if (!$token) return false;
    $given = $_SERVER['HTTP_X_BG_TOKEN'] ?? ($_GET['token'] ?? '');
    if (!is_string($given) || $given === '') return false;
    return hash_equals($token, $given);
}

function bgRequireRead(): void {
    if (!bgReadAuth()) {
        http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit;
    }
}

// Local-day grouping for the daily rollup. Computed from a timezone name so it
// follows DST, using today's offset across the whole window — a day either side
// of a DST change can land in the neighbouring bucket, which is fine for a
// dashboard and avoids depending on MySQL's tz tables being loaded.
function bgTzOffsetMinutes(): int {
    $tz = $_ENV['BG_TIMEZONE'] ?? 'America/New_York';
    try {
        $zone = new DateTimeZone($tz);
        return intdiv($zone->getOffset(new DateTime('now', new DateTimeZone('UTC'))), 60);
    } catch (Exception $e) {
        return 0;
    }
}

// POST ?action=bg_ingest  header: X-Admin-Secret
// body: { readings: [ { at: ISO8601, mgdl: int, trend: string|null } ] }
// Idempotent — the poller deliberately re-sends an overlapping window, and
// replaying it just refreshes rows already stored.
if ($method === 'POST' && $action === 'bg_ingest') {
    $admin_secret = $_ENV['ADMIN_SECRET'] ?? '';
    $given        = $_SERVER['HTTP_X_ADMIN_SECRET'] ?? '';
    if (!$admin_secret || !hash_equals($admin_secret, $given)) {
        http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit;
    }

    $body     = json_decode(file_get_contents('php://input'), true);
    $readings = $body['readings'] ?? null;
    if (!is_array($readings)) {
        http_response_code(400); echo json_encode(['error' => 'Missing readings array.']); exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO bg_readings (reading_at, observed_at, mgdl, trend) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE observed_at = VALUES(observed_at), mgdl = VALUES(mgdl), trend = VALUES(trend)"
    );

    $stored = 0; $skipped = 0;
    foreach ($readings as $r) {
        if (!is_array($r)) { $skipped++; continue; }
        $ts   = is_string($r['at'] ?? null) ? strtotime($r['at']) : false;
        $mgdl = intval($r['mgdl'] ?? 0);
        // Dexcom pins readings to 40 and 400 at the sensor's floor and ceiling;
        // anything outside this window is not a plausible reading.
        if ($ts === false || $mgdl < 20 || $mgdl > 600) { $skipped++; continue; }
        // Share can deliver one reading twice, seconds apart. Snapping to the
        // 5-minute grid the sensor samples on lets the unique key collapse them,
        // and keeps a complete day at exactly 288 rows, which coverage_pct assumes.
        // The unsnapped time is kept too — flooring it made fresh readings look
        // up to 5 minutes old, so a refresh appeared to do nothing.
        $observed = gmdate('Y-m-d H:i:s', $ts);
        $ts = intdiv($ts, 300) * 300;
        $trend = isset($r['trend']) && is_string($r['trend']) ? substr($r['trend'], 0, 20) : null;
        $stmt->execute([gmdate('Y-m-d H:i:s', $ts), $observed, $mgdl, $trend]);
        $stored++;
    }

    // The poller sizes its next window from this, so a restart or an outage
    // asks Dexcom for exactly the gap rather than a fixed guess.
    $latest = $pdo->query('SELECT MAX(reading_at) FROM bg_readings')->fetchColumn();

    echo json_encode([
        'success'       => true,
        'stored'        => $stored,
        'skipped'       => $skipped,
        'latest_stored' => $latest ? gmdate('c', strtotime($latest . ' UTC')) : null,
    ]); exit;
}

// POST ?action=bg_refresh&token=…
// Asks the poller to pull from Dexcom now, so a manual refresh reflects live
// data rather than whatever was last stored. The admin secret stays here on the
// server — the page only ever holds the read-only token, which is why this is a
// proxy rather than the browser calling the poller directly.
if ($method === 'POST' && $action === 'bg_refresh') {
    bgRequireRead();

    $poll_url = $_ENV['BG_POLL_URL']  ?? '';
    $secret   = $_ENV['ADMIN_SECRET'] ?? '';
    if (!$poll_url || !$secret) {
        http_response_code(503);
        echo json_encode(['error' => 'Refresh is not configured.']); exit;
    }

    // Dexcom's Share API is unofficial, and a button is easy to lean on — one
    // open tab per family member would be enough to turn this into a stream.
    // Within the cooldown we report success without calling out: a poll that
    // recent has already fetched everything this one would.
    $cooldown = 45;
    $stamp    = sys_get_temp_dir() . '/bg_refresh_last';
    $last     = is_readable($stamp) ? (int)file_get_contents($stamp) : 0;
    $age      = time() - $last;
    if ($age < $cooldown) {
        echo json_encode([
            'triggered'   => false,
            'reason'      => 'cooling down',
            'retry_after' => $cooldown - $age,
        ]); exit;
    }
    @file_put_contents($stamp, (string)time());

    $ch = curl_init($poll_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => '',
        CURLOPT_HTTPHEADER     => ['X-Admin-Secret: ' . $secret],
        // Generous, because a suspended instance has to wake before it answers.
        CURLOPT_TIMEOUT        => 25,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // A timeout is not proof of failure — the poll may well have run on the
    // other end. The caller re-reads regardless, so report what we saw.
    echo json_encode([
        'triggered' => $code >= 200 && $code < 300,
        'status'    => $code,
        'poller'    => $body ? json_decode($body, true) : null,
    ]); exit;
}

// GET ?action=bg_latest&token=…  → the newest stored reading.
// minutes_ago is what a display should use to decide it has gone stale: show
// the age, and never present an old number as though it were current.
if ($method === 'GET' && $action === 'bg_latest') {
    bgRequireRead();
    $row = $pdo->query("SELECT reading_at, observed_at, mgdl, trend FROM bg_readings ORDER BY reading_at DESC LIMIT 1")->fetch();
    if (!$row) { echo json_encode(['reading' => null]); exit; }
    $ts = strtotime(($row['observed_at'] ?: $row['reading_at']) . ' UTC');
    echo json_encode(['reading' => [
        'at'          => gmdate('c', $ts),
        'mgdl'        => (int)$row['mgdl'],
        'trend'       => $row['trend'],
        'minutes_ago' => (int)floor((time() - $ts) / 60),
    ]]); exit;
}

// GET ?action=bg_embed&token=…[&spark=N][&low=70][&high=180]
//
// Plain text for microcontrollers: no JSON parser, no heap allocation, a fixed
// buffer and sscanf will do. Reads the stored rows only — a display never
// reaches Dexcom, so hanging more of them on the wall costs the API nothing.
//
//   line 1   mgdl,trend,minutes_ago,in_range        e.g. 84,4,2,1
//   line 2   with spark=N: the last N mg/dL values, oldest first
//
// mgdl 0 means nothing is stored yet. Trend follows Dexcom's own ordering, so a
// device can index an arrow glyph straight off it:
//   0 unknown · 1 up-up · 2 up · 3 up-45 · 4 flat · 5 down-45 · 6 down · 7 down-down
if ($method === 'GET' && $action === 'bg_embed') {
    bgRequireRead();
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');

    $codes = [
        'DoubleUp'      => 1, 'SingleUp'   => 2, 'FortyFiveUp'   => 3, 'Flat' => 4,
        'FortyFiveDown' => 5, 'SingleDown' => 6, 'DoubleDown'    => 7,
    ];

    $row = $pdo->query(
        "SELECT reading_at, observed_at, mgdl, trend FROM bg_readings ORDER BY reading_at DESC LIMIT 1"
    )->fetch();

    if (!$row) { echo "0,0,-1,0\n"; exit; }

    $low  = intval($_GET['low']  ?? 70);
    $high = intval($_GET['high'] ?? 180);

    // observed_at is the real reading time; reading_at is snapped to the grid and
    // would make a fresh reading look up to 5 minutes stale.
    $ts   = strtotime(($row['observed_at'] ?: $row['reading_at']) . ' UTC');
    $mgdl = (int)$row['mgdl'];
    $mins = (int)floor((time() - $ts) / 60);
    $code = $codes[$row['trend']] ?? 0;
    $in   = ($mgdl >= $low && $mgdl <= $high) ? 1 : 0;

    echo "{$mgdl},{$code},{$mins},{$in}\n";

    // Optional sparkline: the last N readings, oldest first. Capped so the
    // response stays inside a small fixed buffer on the device.
    $spark = intval($_GET['spark'] ?? 0);
    if ($spark > 0) {
        $spark = min($spark, 60);
        $vals = $pdo->query(
            "SELECT mgdl FROM bg_readings ORDER BY reading_at DESC LIMIT {$spark}"
        )->fetchAll(PDO::FETCH_COLUMN);
        echo implode(',', array_map('intval', array_reverse($vals))) . "\n";
    }
    exit;
}

// GET ?action=bg_history&token=…&hours=24&low=70&high=180 → raw readings + summary.
if ($method === 'GET' && $action === 'bg_history') {
    bgRequireRead();
    $hours = intval($_GET['hours'] ?? 24);
    if ($hours < 1 || $hours > 168) {
        http_response_code(400); echo json_encode(['error' => 'hours must be 1-168.']); exit;
    }
    $low  = intval($_GET['low']  ?? 70);
    $high = intval($_GET['high'] ?? 180);

    $since = gmdate('Y-m-d H:i:s', time() - $hours * 3600);
    $stmt  = $pdo->prepare("SELECT reading_at, observed_at, mgdl, trend FROM bg_readings WHERE reading_at >= ? ORDER BY reading_at");
    $stmt->execute([$since]);

    $readings = []; $values = [];
    foreach ($stmt->fetchAll() as $row) {
        $values[]   = (int)$row['mgdl'];
        $readings[] = [
            'at'    => gmdate('c', strtotime(($row['observed_at'] ?: $row['reading_at']) . ' UTC')),
            'mgdl'  => (int)$row['mgdl'],
            'trend' => $row['trend'],
        ];
    }

    $summary = null;
    if ($values) {
        $count   = count($values);
        $lowCnt  = count(array_filter($values, fn($v) => $v < $low));
        $highCnt = count(array_filter($values, fn($v) => $v > $high));
        $summary = [
            'readings'       => $count,
            'avg_mgdl'       => (int)round(array_sum($values) / $count),
            'min_mgdl'       => min($values),
            'max_mgdl'       => max($values),
            'low_count'      => $lowCnt,
            'high_count'     => $highCnt,
            'in_range_pct'   => (int)round((($count - $lowCnt - $highCnt) / $count) * 100),
            'low_threshold'  => $low,
            'high_threshold' => $high,
        ];
    }

    echo json_encode(['hours' => $hours, 'summary' => $summary, 'readings' => $readings]); exit;
}

// GET ?action=bg_daily&token=…&days=30&low=70&high=180 → one row per local day.
// This is the shape the streak calendar and time-in-range bars want; the
// aggregation happens in SQL so a year of history stays a small response.
if ($method === 'GET' && $action === 'bg_daily') {
    bgRequireRead();
    $days = intval($_GET['days'] ?? 30);
    if ($days < 1 || $days > 365) {
        http_response_code(400); echo json_encode(['error' => 'days must be 1-365.']); exit;
    }
    $low    = intval($_GET['low']  ?? 70);
    $high   = intval($_GET['high'] ?? 180);
    $offset = bgTzOffsetMinutes();
    $since  = gmdate('Y-m-d H:i:s', time() - $days * 86400);

    $stmt = $pdo->prepare(
        "SELECT DATE(COALESCE(observed_at, reading_at) + INTERVAL $offset MINUTE) AS day,
                COUNT(*)         AS readings,
                ROUND(AVG(mgdl)) AS avg_mgdl,
                MIN(mgdl)        AS min_mgdl,
                MAX(mgdl)        AS max_mgdl,
                SUM(mgdl < $low) AS low_count,
                SUM(mgdl > $high) AS high_count
         FROM bg_readings
         WHERE reading_at >= ?
         GROUP BY day
         ORDER BY day"
    );
    $stmt->execute([$since]);

    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $count = (int)$row['readings'];
        $lowC  = (int)$row['low_count'];
        $highC = (int)$row['high_count'];
        $out[] = [
            'day'          => $row['day'],
            'readings'     => $count,
            'avg_mgdl'     => (int)$row['avg_mgdl'],
            'min_mgdl'     => (int)$row['min_mgdl'],
            'max_mgdl'     => (int)$row['max_mgdl'],
            'low_count'    => $lowC,
            'high_count'   => $highC,
            'in_range_pct' => (int)round((($count - $lowC - $highC) / $count) * 100),
            // 288 readings is a complete day at one per 5 minutes; well under
            // that means sensor gaps, so the day's stats are only partial.
            'coverage_pct' => (int)round(min($count / 288, 1) * 100),
        ];
    }

    echo json_encode([
        'days'           => $days,
        'timezone'       => $_ENV['BG_TIMEZONE'] ?? 'America/New_York',
        'low_threshold'  => $low,
        'high_threshold' => $high,
        'daily'          => $out,
    ]); exit;
}

// ══════════════════════════════════════════════════════════════
// GLUCOSE EVENTS
// ══════════════════════════════════════════════════════════════
// Human annotations: why a stretch of readings looked the way it did.
//
// These are the only endpoints here that accept input from a browser. They use
// the same read token as everything else: one credential to manage, and the
// validation below — a closed tag set, bounded times, capped notes — is what
// actually keeps bad input out.

// A closed set. An open text field would be unqueryable ("what causes his
// highs?" is the whole point), and a whitelist is also the primary input
// validation on a write path.
function bgEventTags(): array {
    return ['carbs', 'missed_dose', 'dose_timing', 'exercise', 'illness', 'stress', 'sensor', 'sleep', 'other'];
}


// Accepts anything strtotime understands, but only within a plausible window —
// a typo or a bad client clock should be rejected, not stored forever.
function bgParseEventTime($value, bool $required = true) {
    if ($value === null || $value === '') {
        if ($required) return false;
        return null;
    }
    if (!is_string($value)) return false;
    $ts = strtotime($value);
    if ($ts === false) return false;
    if ($ts < 1577836800 || $ts > time() + 86400) return false; // 2020-01-01 .. tomorrow
    return $ts;
}

// GET ?action=bg_events&token=…&hours=24 → annotations overlapping the window.
// Read token: anything that may read the readings may read what explains them.
if ($method === 'GET' && $action === 'bg_events') {
    bgRequireRead();

    $hours = intval($_GET['hours'] ?? 24);
    if ($hours < 1 || $hours > 8760) {
        http_response_code(400); echo json_encode(['error' => 'hours must be 1-8760.']); exit;
    }
    $since = gmdate('Y-m-d H:i:s', time() - $hours * 3600);

    // An event is in the window if it starts inside it, or started earlier and
    // runs into it — a long excursion annotated last night still belongs today.
    $stmt = $pdo->prepare(
        "SELECT id, start_at, end_at, tag, note FROM bg_events
         WHERE start_at >= ? OR (end_at IS NOT NULL AND end_at >= ?)
         ORDER BY start_at"
    );
    $stmt->execute([$since, $since]);

    $events = [];
    foreach ($stmt->fetchAll() as $row) {
        $events[] = [
            'id'       => (int)$row['id'],
            'start_at' => gmdate('c', strtotime($row['start_at'] . ' UTC')),
            'end_at'   => $row['end_at'] ? gmdate('c', strtotime($row['end_at'] . ' UTC')) : null,
            'tag'      => $row['tag'],
            'note'     => $row['note'],
        ];
    }

    echo json_encode(['hours' => $hours, 'tags' => bgEventTags(), 'events' => $events]); exit;
}

// POST ?action=bg_event_save&token=…
// body: { id?, start_at, end_at?, tag, note? }
// Creates, or updates when id is given. Returns the stored row.
if ($method === 'POST' && $action === 'bg_event_save') {
    bgRequireRead();

    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        http_response_code(400); echo json_encode(['error' => 'Expected a JSON object.']); exit;
    }

    $tag = is_string($body['tag'] ?? null) ? $body['tag'] : '';
    if (!in_array($tag, bgEventTags(), true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Unknown tag.', 'tags' => bgEventTags()]); exit;
    }

    $start = bgParseEventTime($body['start_at'] ?? null, true);
    if ($start === false) {
        http_response_code(400); echo json_encode(['error' => 'start_at is missing or out of range.']); exit;
    }

    $end = bgParseEventTime($body['end_at'] ?? null, false);
    if ($end === false) {
        http_response_code(400); echo json_encode(['error' => 'end_at is not a valid time.']); exit;
    }
    if ($end !== null && $end < $start) {
        http_response_code(400); echo json_encode(['error' => 'end_at is before start_at.']); exit;
    }
    // A span longer than a day is a mistake, not an annotation.
    if ($end !== null && ($end - $start) > 86400) {
        http_response_code(400); echo json_encode(['error' => 'end_at is more than 24h after start_at.']); exit;
    }

    $note = null;
    if (isset($body['note']) && is_string($body['note'])) {
        // Strip control characters, keeping newlines and tabs; the column is
        // VARCHAR(500) so cut to length rather than letting MySQL truncate.
        $note = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $body['note']);
        $note = mb_substr(trim($note), 0, 500);
        if ($note === '') $note = null;
    }

    $start_sql = gmdate('Y-m-d H:i:s', $start);
    $end_sql   = $end === null ? null : gmdate('Y-m-d H:i:s', $end);
    $id        = intval($body['id'] ?? 0);

    if ($id > 0) {
        $stmt = $pdo->prepare(
            "UPDATE bg_events SET start_at = ?, end_at = ?, tag = ?, note = ? WHERE id = ?"
        );
        $stmt->execute([$start_sql, $end_sql, $tag, $note, $id]);
        if ($stmt->rowCount() === 0) {
            // Either it is gone, or nothing changed — say which.
            $exists = $pdo->prepare("SELECT 1 FROM bg_events WHERE id = ?");
            $exists->execute([$id]);
            if (!$exists->fetchColumn()) {
                http_response_code(404); echo json_encode(['error' => 'No event with that id.']); exit;
            }
        }
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO bg_events (start_at, end_at, tag, note) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$start_sql, $end_sql, $tag, $note]);
        $id = (int)$pdo->lastInsertId();
    }

    echo json_encode(['success' => true, 'event' => [
        'id'       => $id,
        'start_at' => gmdate('c', $start),
        'end_at'   => $end === null ? null : gmdate('c', $end),
        'tag'      => $tag,
        'note'     => $note,
    ]]); exit;
}

// DELETE ?action=bg_event_delete&id=…&token=…
if ($method === 'DELETE' && $action === 'bg_event_delete') {
    bgRequireRead();

    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400); echo json_encode(['error' => 'Invalid id.']); exit;
    }

    $stmt = $pdo->prepare("DELETE FROM bg_events WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        http_response_code(404); echo json_encode(['error' => 'No event with that id.']); exit;
    }

    echo json_encode(['success' => true, 'deleted' => $id]); exit;
}

// =============================================================
// CONFIDENCE POOL (nflpool.html) — actions prefixed cp_
//
// A week is defined by the first sheet photo uploaded for it: Claude reads the
// matchups AND the printed spreads off the image, they get confirmed by hand,
// and that becomes the week. Every later sheet is matched against those games.
// Scoring deliberately uses the spread stored here, never a live line — the
// sheet is printed once and the real line keeps moving after that.
// =============================================================

$pdo->exec("CREATE TABLE IF NOT EXISTS cp_weeks (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    season            INT          NOT NULL,
    week              INT          NOT NULL,
    push_rule         ENUM('award','void') DEFAULT 'void',
    scores_fetched_at DATETIME     DEFAULT NULL,
    created_at        DATETIME     DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_season_week (season, week)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS cp_games (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    week_id    INT          NOT NULL,
    sort_order INT          NOT NULL,
    away_team  VARCHAR(8)   NOT NULL,
    home_team  VARCHAR(8)   NOT NULL,
    favorite   ENUM('home','away') NOT NULL,
    spread     DECIMAL(4,1) NOT NULL,
    espn_id    VARCHAR(24)  DEFAULT NULL,
    away_score INT          DEFAULT 0,
    home_score INT          DEFAULT 0,
    state      ENUM('pre','in','post') DEFAULT 'pre',
    detail     VARCHAR(60)  DEFAULT NULL,
    kickoff    DATETIME     DEFAULT NULL,
    UNIQUE KEY uk_week_order (week_id, sort_order),
    FOREIGN KEY (week_id) REFERENCES cp_weeks(id) ON DELETE CASCADE
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS cp_entries (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    week_id     INT          NOT NULL,
    player_name VARCHAR(60)  NOT NULL,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_week_player (week_id, player_name),
    FOREIGN KEY (week_id) REFERENCES cp_weeks(id) ON DELETE CASCADE
)");

// uk_entry_conf is what enforces "each confidence value once per week" at the
// storage layer, so a bad sheet read can never quietly create a 16-and-16 entry.
$pdo->exec("CREATE TABLE IF NOT EXISTS cp_picks (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    entry_id   INT NOT NULL,
    game_id    INT NOT NULL,
    pick       ENUM('home','away') NOT NULL,
    confidence INT NOT NULL,
    UNIQUE KEY uk_entry_game (entry_id, game_id),
    UNIQUE KEY uk_entry_conf (entry_id, confidence),
    FOREIGN KEY (entry_id) REFERENCES cp_entries(id) ON DELETE CASCADE,
    FOREIGN KEY (game_id)  REFERENCES cp_games(id)   ON DELETE CASCADE
)");

// Added after cp_weeks shipped, so it needs the safe-migration treatment the
// other tables in this file use rather than a change to the CREATE above.
try { $pdo->exec("ALTER TABLE cp_weeks ADD COLUMN scores_error VARCHAR(200) DEFAULT NULL"); } catch (PDOException $e) {}

// Sheet photos are no longer kept, so the column that pointed at them goes too.
// Succeeds once on an existing install, then fails harmlessly forever after.
try { $pdo->exec("ALTER TABLE cp_entries DROP COLUMN photo_url"); } catch (PDOException $e) {}

/** City + nickname per team, keyed by the abbreviation ESPN uses. */
function cpTeams(): array {
    static $t = [
        'ARI' => ['Arizona', 'Cardinals'],    'ATL' => ['Atlanta', 'Falcons'],
        'BAL' => ['Baltimore', 'Ravens'],     'BUF' => ['Buffalo', 'Bills'],
        'CAR' => ['Carolina', 'Panthers'],    'CHI' => ['Chicago', 'Bears'],
        'CIN' => ['Cincinnati', 'Bengals'],   'CLE' => ['Cleveland', 'Browns'],
        'DAL' => ['Dallas', 'Cowboys'],       'DEN' => ['Denver', 'Broncos'],
        'DET' => ['Detroit', 'Lions'],        'GB'  => ['Green Bay', 'Packers'],
        'HOU' => ['Houston', 'Texans'],       'IND' => ['Indianapolis', 'Colts'],
        'JAX' => ['Jacksonville', 'Jaguars'], 'KC'  => ['Kansas City', 'Chiefs'],
        'LV'  => ['Las Vegas', 'Raiders'],    'LAC' => ['Los Angeles', 'Chargers'],
        'LAR' => ['Los Angeles', 'Rams'],     'MIA' => ['Miami', 'Dolphins'],
        'MIN' => ['Minnesota', 'Vikings'],    'NE'  => ['New England', 'Patriots'],
        'NO'  => ['New Orleans', 'Saints'],   'NYG' => ['New York', 'Giants'],
        'NYJ' => ['New York', 'Jets'],        'PHI' => ['Philadelphia', 'Eagles'],
        'PIT' => ['Pittsburgh', 'Steelers'],  'SEA' => ['Seattle', 'Seahawks'],
        'SF'  => ['San Francisco', '49ers'],  'TB'  => ['Tampa Bay', 'Buccaneers'],
        'TEN' => ['Tennessee', 'Titans'],     'WSH' => ['Washington', 'Commanders'],
    ];
    return $t;
}

/**
 * Free text from a pick sheet ("K.C.", "Chiefs", "Kansas City") to an ESPN
 * abbreviation, or '' when nothing matches. Nicknames are registered before
 * cities because the two Los Angeles and two New York teams share a city.
 */
function cpTeamAbbr(string $raw): string {
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (cpTeams() as $abbr => $pair) {
            foreach ([$abbr, $pair[1], $pair[0] . $pair[1]] as $alias) {
                $map[preg_replace('/[^a-z0-9]/', '', strtolower($alias))] = $abbr;
            }
        }
        // Cities last so they never overwrite a nickname key, plus the
        // abbreviations and old city names people still write by hand.
        $extra = [
            'arizona' => 'ARI', 'arz' => 'ARI', 'atlanta' => 'ATL', 'baltimore' => 'BAL',
            'buffalo' => 'BUF', 'carolina' => 'CAR', 'chicago' => 'CHI', 'cincinnati' => 'CIN',
            'cincy' => 'CIN', 'cleveland' => 'CLE', 'dallas' => 'DAL', 'denver' => 'DEN',
            'detroit' => 'DET', 'greenbay' => 'GB', 'gnb' => 'GB', 'houston' => 'HOU',
            'indianapolis' => 'IND', 'indy' => 'IND', 'jacksonville' => 'JAX', 'jac' => 'JAX',
            'kansascity' => 'KC', 'kan' => 'KC', 'lasvegas' => 'LV', 'lvr' => 'LV',
            'oakland' => 'LV', 'oak' => 'LV', 'sandiego' => 'LAC', 'sd' => 'LAC', 'sdg' => 'LAC',
            'stlouis' => 'LAR', 'stl' => 'LAR', 'la' => 'LAR', 'miami' => 'MIA',
            'minnesota' => 'MIN', 'newengland' => 'NE', 'nwe' => 'NE', 'neworleans' => 'NO',
            'nor' => 'NO', 'philadelphia' => 'PHI', 'philly' => 'PHI', 'pittsburgh' => 'PIT',
            'seattle' => 'SEA', 'sanfrancisco' => 'SF', 'sfo' => 'SF', 'niners' => 'SF',
            'tampabay' => 'TB', 'tampa' => 'TB', 'tam' => 'TB', 'bucs' => 'TB',
            'tennessee' => 'TEN', 'washington' => 'WSH', 'was' => 'WSH',
        ];
        foreach ($extra as $k => $v) { if (!isset($map[$k])) $map[$k] = $v; }
    }
    $k = preg_replace('/[^a-z0-9]/', '', strtolower($raw));
    if ($k === '') return '';
    if (isset($map[$k])) return $map[$k];
    // Last resort: longest alias contained in the string, so "at Buffalo Bills"
    // still resolves. Longest wins to keep "la" from beating "chargers".
    $best = ''; $len = 0;
    foreach ($map as $alias => $abbr) {
        if (strlen($alias) > $len && strlen($alias) >= 4 && str_contains($k, $alias)) {
            $best = $abbr; $len = strlen($alias);
        }
    }
    return $best;
}

/**
 * Which side covered, given the spread printed on the sheet.
 * Returns 'home' | 'away' | 'push', or null before the game has any score.
 * It runs on in-progress games too — that is what makes the board live.
 */
function cpCover(array $g): ?string {
    if ($g['state'] === 'pre') return null;
    $margin = (int)$g['home_score'] - (int)$g['away_score'];   // positive: home ahead
    $edge   = $g['favorite'] === 'home'
            ? $margin - (float)$g['spread']
            : -$margin - (float)$g['spread'];
    if (abs($edge) < 0.001) return 'push';
    $dog = $g['favorite'] === 'home' ? 'away' : 'home';
    return $edge > 0 ? $g['favorite'] : $dog;
}

/** ESPN's public scoreboard. Returns null on any failure — scores just stay stale. */
/**
 * GET a URL, keeping the reason on failure instead of discarding it.
 *
 * Shared hosting is fussy about outbound requests, so this does not assume one
 * transport works: some hosts ship curl disabled but allow_url_fopen on, or the
 * reverse, and an edge filter in front of a public feed may turn away a request
 * whose user agent is not in the conventional form. CURLOPT_FOLLOWLOCATION is
 * gone because ESPN does not redirect, not because it is known to be a problem.
 *
 * Returns ['body' => ?string, 'error' => ?string].
 */
/** One GET attempt. Always reports the status, and the body even on failure. */
function cpHttpTry(string $url, string $ua): array {
    if (function_exists('curl_init')) {
        $headers = ['Accept: application/json'];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        if ($ua !== '') curl_setopt($ch, CURLOPT_USERAGENT, $ua);
        $res  = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        return [
            'body'  => is_string($res) ? $res : null,
            'code'  => $code,
            'error' => ($res !== false && $code === 200) ? null : ('curl: ' . ($err ?: 'HTTP ' . $code)),
            'via'   => 'curl',
        ];
    }

    if (ini_get('allow_url_fopen')) {
        $hdr = "Accept: application/json\r\n" . ($ua !== '' ? "User-Agent: {$ua}\r\n" : '');
        // ignore_errors keeps the body of a 4xx so it can be reported, but the
        // status still has to be read back from $http_response_header —
        // otherwise a block page is indistinguishable from a real response.
        $ctx = stream_context_create(['http' => [
            'timeout' => 15, 'ignore_errors' => true, 'header' => $hdr,
        ]]);
        $res  = @file_get_contents($url, false, $ctx);
        $code = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $code = (int)$m[1];
        }
        return [
            'body'  => $res === false ? null : $res,
            'code'  => $code,
            'error' => ($res !== false && $code === 200) ? null : ('stream: HTTP ' . $code),
            'via'   => 'stream',
        ];
    }

    return ['body' => null, 'code' => 0, 'error' => 'no outbound transport available', 'via' => 'none'];
}

/**
 * GET a URL, keeping the reason on failure instead of discarding it.
 *
 * The polite identifying user agent is tried first. Public sports feeds sit
 * behind edge filters that turn away unfamiliar agents from datacentre IPs, so
 * a plain browser agent is the fallback rather than the default.
 *
 * Returns ['body' => ?string, 'error' => ?string, 'code' => int].
 */
function cpHttpGet(string $url): array {
    foreach (cpUserAgents() as $ua) {
        $r = cpHttpTry($url, $ua);
        if ($r['error'] === null) return $r;
        $last = $r;
    }
    return ['body' => null, 'error' => $last['error'] ?? 'request failed', 'code' => $last['code'] ?? 0];
}

function cpUserAgents(): array {
    return [
        'Mozilla/5.0 (compatible; davenn.com/1.0; +https://davenn.com)',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
    ];
}

/** The scoreboard, from either endpoint ESPN publishes it on. */
function cpEspnSources(int $season, int $week): array {
    return [
        "https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard?dates={$season}&seasontype=2&week={$week}",
        "https://cdn.espn.com/core/nfl/scoreboard?xhr=1&year={$season}&seasontype=2&week={$week}",
    ];
}

/** Both endpoints carry the same event objects, just at different depths. */
function cpEventsFrom(?array $data): ?array {
    $events = $data['events'] ?? ($data['content']['sbData']['events'] ?? null);
    return is_array($events) && $events ? $events : null;
}

/** Returns ['events' => ?array, 'error' => ?string]. */
function cpFetchEspn(int $season, int $week): array {
    $err = null;
    foreach (cpEspnSources($season, $week) as $url) {
        $r = cpHttpGet($url);
        if ($r['body'] === null) { $err = $err ?? $r['error']; continue; }
        $events = cpEventsFrom(json_decode($r['body'], true));
        if ($events) return ['events' => $events, 'error' => null];
        // Say what came back instead, so "unexpected" is actionable.
        $err = $err ?? ('unexpected response: HTTP ' . $r['code'] . ', ' . strlen($r['body']) . ' bytes');
    }
    return ['events' => null, 'error' => $err ?: 'no data from ESPN'];
}

/** Record (or clear) why the last score refresh did not land. */
function cpNoteScoreError(PDO $pdo, $week_id, ?string $err): void {
    $pdo->prepare("UPDATE cp_weeks SET scores_error = ? WHERE id = ?")
        ->execute([$err === null ? null : mb_substr($err, 0, 200), $week_id]);
}

/**
 * Pull live scores into cp_games. The fetch timestamp is written *before* the
 * network call, so a burst of family members polling at once produces one ESPN
 * request rather than one per phone.
 */
function cpRefreshScores(PDO $pdo, array $week, bool $force = false): void {
    $last = $week['scores_fetched_at'] ? strtotime($week['scores_fetched_at'] . ' UTC') : 0;
    if (!$force && $last && (time() - $last) < 25) return;

    $pdo->prepare("UPDATE cp_weeks SET scores_fetched_at = UTC_TIMESTAMP() WHERE id = ?")
        ->execute([$week['id']]);

    $fetch = cpFetchEspn((int)$week['season'], (int)$week['week']);
    if ($fetch['events'] === null) { cpNoteScoreError($pdo, $week['id'], $fetch['error']); return; }

    $live = [];
    foreach ($fetch['events'] as $ev) {
        $comp = $ev['competitions'][0] ?? null;
        if (!$comp) continue;
        $row = ['id' => (string)($ev['id'] ?? '')];
        foreach ($comp['competitors'] ?? [] as $c) {
            $side = ($c['homeAway'] ?? '') === 'home' ? 'home' : 'away';
            $row[$side]           = cpTeamAbbr((string)($c['team']['abbreviation'] ?? ''));
            $row[$side . 'Score'] = (int)($c['score'] ?? 0);
        }
        $state = $ev['status']['type']['state'] ?? 'pre';
        $row['state']   = in_array($state, ['pre', 'in', 'post'], true) ? $state : 'pre';
        $row['detail']  = mb_substr((string)($ev['status']['type']['shortDetail'] ?? ''), 0, 60);
        $row['kickoff'] = isset($ev['date']) ? gmdate('Y-m-d H:i:s', strtotime($ev['date'])) : null;
        if (!empty($row['home']) && !empty($row['away'])) {
            $live[$row['away'] . '@' . $row['home']] = $row;
        }
    }
    if (!$live) { cpNoteScoreError($pdo, $week['id'], 'ESPN returned no usable games'); return; }

    $up = $pdo->prepare("UPDATE cp_games SET espn_id=?, away_score=?, home_score=?, state=?, detail=?, kickoff=? WHERE id=?");
    $gs = $pdo->prepare("SELECT * FROM cp_games WHERE week_id = ?");
    $gs->execute([$week['id']]);

    $matched = 0; $missed = [];
    foreach ($gs->fetchAll() as $g) {
        $m = $live[$g['away_team'] . '@' . $g['home_team']] ?? null;
        if (!$m) { $missed[] = $g['away_team'] . '@' . $g['home_team']; continue; }
        $up->execute([$m['id'], $m['awayScore'], $m['homeScore'], $m['state'], $m['detail'], $m['kickoff'], $g['id']]);
        $matched++;
    }

    // A week where nothing lines up is usually the wrong week number on the
    // sheet, which looks exactly like "no games have started" unless it is said.
    cpNoteScoreError($pdo, $week['id'], $matched === 0
        ? 'None of these games are in ESPN\'s week ' . (int)$week['week'] . ' schedule'
        : ($missed ? count($missed) . ' game(s) not found at ESPN: ' . implode(', ', array_slice($missed, 0, 4)) : null));
}

/** Week row plus games, entries and standings — the whole app state in one shape. */
function cpWeekPayload(PDO $pdo, array $week): array {
    $teams = cpTeams();

    $gs = $pdo->prepare("SELECT * FROM cp_games WHERE week_id = ? ORDER BY sort_order");
    $gs->execute([$week['id']]);
    $games = $gs->fetchAll();

    $cover = []; $state_of = []; $order_of = []; $out_games = [];
    foreach ($games as $g) {
        $c = cpCover($g);
        $cover[$g['id']]    = $c;
        $state_of[$g['id']] = $g['state'];
        $order_of[$g['id']] = (int)$g['sort_order'];
        $out_games[] = [
            'id'         => (int)$g['id'],
            'sort_order' => (int)$g['sort_order'],
            'away'       => $g['away_team'],
            'home'       => $g['home_team'],
            'away_name'  => $teams[$g['away_team']][1] ?? $g['away_team'],
            'home_name'  => $teams[$g['home_team']][1] ?? $g['home_team'],
            'favorite'   => $g['favorite'],
            'spread'     => (float)$g['spread'],
            'away_score' => (int)$g['away_score'],
            'home_score' => (int)$g['home_score'],
            'state'      => $g['state'],
            'detail'     => $g['detail'],
            'kickoff'    => $g['kickoff'] ? gmdate('c', strtotime($g['kickoff'] . ' UTC')) : null,
            'cover'      => $c,
        ];
    }

    $es = $pdo->prepare("SELECT * FROM cp_entries WHERE week_id = ? ORDER BY player_name");
    $es->execute([$week['id']]);
    $entries = $es->fetchAll();

    $ps = $pdo->prepare("SELECT p.* FROM cp_picks p JOIN cp_entries e ON e.id = p.entry_id WHERE e.week_id = ?");
    $ps->execute([$week['id']]);
    $by_entry = [];
    foreach ($ps->fetchAll() as $p) $by_entry[$p['entry_id']][] = $p;

    // A push pays nobody by default: the result landed exactly on the printed
    // number, so the pick was neither right nor wrong.
    $award_push = $week['push_rule'] === 'award';
    $standings  = [];

    foreach ($entries as $e) {
        $locked = 0; $live = 0; $pending = 0; $hits = 0; $decided = 0; $rows = [];
        foreach ($by_entry[$e['id']] ?? [] as $p) {
            $gid   = (int)$p['game_id'];
            $conf  = (int)$p['confidence'];
            $c     = $cover[$gid] ?? null;
            $final = ($state_of[$gid] ?? 'pre') === 'post';
            $won   = $c === null ? null : ($c === 'push' ? $award_push : $c === $p['pick']);
            $pts   = $won ? $conf : 0;
            // A push that pays nobody stays out of the win/loss record too,
            // rather than being counted against the player as a miss.
            $void  = $c === 'push' && !$award_push;

            if ($final) {
                $locked += $pts; $live += $pts;
                if (!$void) { $decided++; if ($won) $hits++; }
            } else {
                // Undecided. It counts towards the live score only while it is
                // currently covering, but its full value goes in the winnable
                // pile either way — which is why the ceiling has to be built on
                // the locked score, never on the live one. Adding $pending to
                // $live would count a covering game's points twice.
                $live    += $pts;
                $pending += $conf;
            }
            $rows[] = [
                'game_id'    => $gid,
                'pick'       => $p['pick'],
                'confidence' => $conf,
                'result'     => $c === null ? 'pending' : ($c === 'push' ? 'push' : ($won ? 'win' : 'loss')),
                'final'      => $final,
            ];
        }
        // Sheet order, not confidence order: someone checking their entry is
        // reading down the paper in their hand, row by row.
        usort($rows, fn($a, $b) => ($order_of[$a['game_id']] ?? 0) <=> ($order_of[$b['game_id']] ?? 0));
        $standings[] = [
            'id'          => (int)$e['id'],
            'player_name' => $e['player_name'],
            'points'      => $locked,             // games that are final
            'live_points' => $live,               // final + currently covering
            'max_points'  => $locked + $pending,  // if every undecided pick lands
            'correct'     => $hits,
            'decided'     => $decided,
            'picks'       => $rows,
        ];
    }
    usort($standings, fn($a, $b) => [$b['live_points'], $b['max_points'], $a['player_name']]
                                <=> [$a['live_points'], $a['max_points'], $b['player_name']]);

    return [
        'week' => [
            'id'           => (int)$week['id'],
            'season'       => (int)$week['season'],
            'week'         => (int)$week['week'],
            'push_rule'    => $week['push_rule'],
            'locked'       => count($entries) > 0,
            // Surfaced so a scoreboard that has quietly stopped updating says so
            // instead of looking like a slate that has not kicked off yet.
            'scores_error' => $week['scores_error'] ?? null,
            'scores_at'    => $week['scores_fetched_at']
                              ? gmdate('c', strtotime($week['scores_fetched_at'] . ' UTC')) : null,
        ],
        'games'     => $out_games,
        'standings' => $standings,
    ];
}

// ── Reading a sheet ──────────────────────────────────────────────────────
// Split out of the cp_scan action so the texting path reads a sheet through
// exactly the same code the web uploader does. One reader, one prompt, one set
// of quirks to reason about.

/**
 * Downscale to the 1568px long edge the model actually uses. A phone photo is
 * far larger, and the extra pixels are resized away server-side anyway.
 * Returns [bytes, mediaType].
 */
function cpPrepareImage(string $bytes, string $media_type): array {
    if (!function_exists('imagecreatefromstring')) return [$bytes, $media_type];
    $img = @imagecreatefromstring($bytes);
    if ($img === false) return [$bytes, $media_type];

    $w = imagesx($img); $h = imagesy($img);
    if (max($w, $h) > 1568) {
        $scale  = 1568 / max($w, $h);
        $scaled = imagescale($img, (int)round($w * $scale), (int)round($h * $scale));
        if ($scaled !== false) {
            ob_start(); imagejpeg($scaled, null, 90); $bytes = ob_get_clean();
            $media_type = 'image/jpeg';
            imagedestroy($scaled);
        }
    }
    imagedestroy($img);
    return [$bytes, $media_type];
}

/** Ask Claude to read the sheet. Returns the decoded object, or null. */
function cpReadSheet(string $bytes, string $media_type, string $api_key): ?array {
    $prompt = 'This is a photo of a filled-in NFL confidence pool pick sheet.

Each row is one game with a point spread. The player marks the team they pick — circled, ticked, boxed, highlighted or otherwise marked — and writes a confidence number for that row. Confidence numbers run from 1 up to the number of games, and each number is used exactly once.

For every game row, report:
- away_team and home_team as written on the sheet. The away team is normally listed first, or is the one with "@" or "at" before the home team.
- favorite: "home" or "away" — which team the spread favours. The favourite is the team the negative number sits beside, so "Bills -3.5" means Buffalo is the favourite.
- spread: the size of the spread as a positive number, so 3.5 for "-3.5". Use 0 for a pick-em.
- pick: "home" or "away" for the team this player marked, or "" if the row is not marked.
- confidence: the number written for that row, or 0 if you cannot read one.

Also report player_name: the name written on the sheet, as your best reading even when the handwriting is unclear. It is shown to a person to check and correct, so a best guess is more useful than a blank. If you are unsure of it, say so in note. Use "" only if no name is written at all.

For the picks, read carefully and do not guess. A wrong confidence number is worse than a 0, so use 0 whenever a digit is ambiguous and say so in note. List the games in the order they appear on the sheet. Set readable to false only if this is not a pick sheet or is too unclear to read at all.';

    $schema = [
        'type'       => 'object',
        'properties' => [
            'readable'    => ['type' => 'boolean'],
            'player_name' => ['type' => 'string'],
            'note'        => ['type' => 'string', 'description' => 'Anything unclear, or "" if the read was clean.'],
            'games'       => [
                'type'  => 'array',
                'items' => [
                    'type'       => 'object',
                    'properties' => [
                        'away_team'  => ['type' => 'string'],
                        'home_team'  => ['type' => 'string'],
                        'favorite'   => ['type' => 'string', 'enum' => ['home', 'away']],
                        'spread'     => ['type' => 'number'],
                        'pick'       => ['type' => 'string', 'enum' => ['home', 'away', '']],
                        'confidence' => ['type' => 'integer'],
                    ],
                    'required'             => ['away_team', 'home_team', 'favorite', 'spread', 'pick', 'confidence'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required'             => ['readable', 'player_name', 'note', 'games'],
        'additionalProperties' => false,
    ];

    $payload = json_encode([
        'model'         => 'claude-opus-5',
        'max_tokens'    => 8000,
        'output_config' => ['format' => ['type' => 'json_schema', 'schema' => $schema]],
        'messages'      => [[
            'role'    => 'user',
            'content' => [
                ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $media_type, 'data' => base64_encode($bytes)]],
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
        CURLOPT_TIMEOUT => 180,
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!$response || $http_code !== 200) return null;

    $data = json_decode($response, true);
    $text = '';
    foreach ($data['content'] ?? [] as $blk) {
        if (($blk['type'] ?? '') === 'text') { $text = trim($blk['text']); break; }
    }
    $result = json_decode($text, true);
    return is_array($result) ? $result : null;
}

/**
 * Turn what the model read into rows, matched against the week when one
 * exists. Returns ['rows' => [...], 'warnings' => [...]].
 */
function cpMatchRows(PDO $pdo, array $result, ?array $week_row): array {
    $existing = [];
    if ($week_row) {
        $gs = $pdo->prepare("SELECT * FROM cp_games WHERE week_id = ? ORDER BY sort_order");
        $gs->execute([$week_row['id']]);
        foreach ($gs->fetchAll() as $g) $existing[$g['away_team'] . '@' . $g['home_team']] = $g;
    }

    $teams = cpTeams();
    $rows = []; $warnings = []; $seen_conf = [];
    foreach ($result['games'] as $i => $g) {
        $away = cpTeamAbbr((string)($g['away_team'] ?? ''));
        $home = cpTeamAbbr((string)($g['home_team'] ?? ''));
        if (!$away || !$home) {
            $warnings[] = 'Row ' . ($i + 1) . ': could not recognise "'
                        . trim(($g['away_team'] ?? '') . ' vs ' . ($g['home_team'] ?? '')) . '".';
        }
        $conf = (int)($g['confidence'] ?? 0);
        if ($conf > 0) {
            if (isset($seen_conf[$conf])) $warnings[] = 'Confidence ' . $conf . ' was read on more than one row.';
            $seen_conf[$conf] = true;
        }
        $match = ($away && $home) ? ($existing[$away . '@' . $home] ?? null) : null;
        if ($existing && $away && $home && !$match) {
            $warnings[] = ($teams[$away][1] ?? $away) . ' at ' . ($teams[$home][1] ?? $home) . ' is not a game in this week.';
        }
        $rows[] = [
            'game_id'    => $match ? (int)$match['id'] : null,
            'away'       => $away,
            'home'       => $home,
            'away_name'  => $teams[$away][1] ?? (string)($g['away_team'] ?? ''),
            'home_name'  => $teams[$home][1] ?? (string)($g['home_team'] ?? ''),
            // An established week's own spread always beats a fresh read of it.
            'favorite'   => $match ? $match['favorite']      : (($g['favorite'] ?? 'home') === 'away' ? 'away' : 'home'),
            'spread'     => $match ? (float)$match['spread'] : round(abs((float)($g['spread'] ?? 0)) * 2) / 2,
            'pick'       => in_array($g['pick'] ?? '', ['home', 'away'], true) ? $g['pick'] : '',
            'confidence' => $conf,
        ];
    }
    $n = count($rows);
    foreach ($rows as $r) {
        if ($r['confidence'] > $n) { $warnings[] = 'A confidence above ' . $n . ' was read — check the numbers.'; break; }
    }
    return ['rows' => $rows, 'warnings' => array_values(array_unique($warnings))];
}

// POST ?action=cp_scan  (multipart: photo, optional season + week)
// Reads a filled-in sheet. Saves no picks — the app shows the result for
// correction first, because a misread confidence number costs more than a
// misread team name and both happen.
if ($method === 'POST' && $action === 'cp_scan') {
    $api_key = $_ENV['ANTHROPIC_API_KEY'] ?? '';
    if (!$api_key) { http_response_code(500); echo json_encode(['error' => 'Sheet reading is not configured on the server.']); exit; }

    if (empty($_FILES['photo']['tmp_name'])) {
        http_response_code(400); echo json_encode(['error' => 'No image provided.']); exit;
    }
    $ext      = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    $mime_map = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    if (!isset($mime_map[$ext])) { http_response_code(400); echo json_encode(['error' => 'Use a JPG, PNG or WebP photo.']); exit; }
    if ($_FILES['photo']['size'] > 10 * 1024 * 1024) { http_response_code(400); echo json_encode(['error' => 'Photo must be under 10 MB.']); exit; }

    // The sheet photo is deliberately never written to disk. It is read from
    // PHP's upload temp file, which is discarded when this request ends.
    [$bytes, $media_type] = cpPrepareImage(file_get_contents($_FILES['photo']['tmp_name']), $mime_map[$ext]);

    $result = cpReadSheet($bytes, $media_type, $api_key);
    if ($result === null) {
        http_response_code(502); echo json_encode(['error' => 'Could not read the sheet.']); exit;
    }
    if (empty($result['readable']) || !is_array($result['games'] ?? null) || !count($result['games'])) {
        echo json_encode([
            'success'  => true,
            'readable' => false,
            'note'     => (string)($result['note'] ?? '') ?: 'No games could be read from that photo.',
        ]); exit;
    }

    $season   = intval($_POST['season'] ?? $_GET['season'] ?? 0);
    $week_n   = intval($_POST['week']   ?? $_GET['week']   ?? 0);
    $week_row = null;
    if ($season && $week_n) {
        $st = $pdo->prepare("SELECT * FROM cp_weeks WHERE season = ? AND week = ?");
        $st->execute([$season, $week_n]);
        $week_row = $st->fetch() ?: null;
    }

    $matched = cpMatchRows($pdo, $result, $week_row);

    echo json_encode([
        'success'     => true,
        'readable'    => true,
        'player_name' => trim((string)($result['player_name'] ?? '')),
        'note'        => (string)($result['note'] ?? ''),
        'week_exists' => (bool)$week_row,
        'rows'        => $matched['rows'],
        'warnings'    => $matched['warnings'],
    ]);
    exit;
}

// POST ?action=cp_save_week  {season, week, push_rule, games:[{away,home,favorite,spread}]}
// Creates the week, or updates the spreads on one that already has entries.
if ($method === 'POST' && $action === 'cp_save_week') {
    $body   = json_decode(file_get_contents('php://input'), true);
    $season = intval($body['season'] ?? 0);
    $week_n = intval($body['week']   ?? 0);
    $games  = is_array($body['games'] ?? null) ? $body['games'] : [];
    $push   = ($body['push_rule'] ?? 'void') === 'award' ? 'award' : 'void';

    if ($season < 2000 || $week_n < 1 || $week_n > 22) {
        http_response_code(400); echo json_encode(['error' => 'Season and week are out of range.']); exit;
    }
    if (count($games) < 1 || count($games) > 20) {
        http_response_code(400); echo json_encode(['error' => 'A week needs between 1 and 20 games.']); exit;
    }

    $clean = []; $seen = [];
    foreach ($games as $i => $g) {
        $away = cpTeamAbbr((string)($g['away'] ?? ''));
        $home = cpTeamAbbr((string)($g['home'] ?? ''));
        if (!$away || !$home || $away === $home) {
            http_response_code(400); echo json_encode(['error' => 'Game ' . ($i + 1) . ' needs two different teams.']); exit;
        }
        foreach ([$away, $home] as $t) {
            if (isset($seen[$t])) {
                http_response_code(400);
                echo json_encode(['error' => (cpTeams()[$t][1] ?? $t) . ' appears in more than one game.']); exit;
            }
            $seen[$t] = true;
        }
        $clean[] = [
            'away'     => $away,
            'home'     => $home,
            'favorite' => ($g['favorite'] ?? 'home') === 'away' ? 'away' : 'home',
            'spread'   => max(0, min(60, round(abs((float)($g['spread'] ?? 0)) * 2) / 2)),
        ];
    }

    $st = $pdo->prepare("SELECT * FROM cp_weeks WHERE season = ? AND week = ?");
    $st->execute([$season, $week_n]);
    $week = $st->fetch();

    $pdo->beginTransaction();
    try {
        if (!$week) {
            $pdo->prepare("INSERT INTO cp_weeks (season, week, push_rule) VALUES (?,?,?)")
                ->execute([$season, $week_n, $push]);
            $week_id = (int)$pdo->lastInsertId();
        } else {
            $week_id = (int)$week['id'];
            $pdo->prepare("UPDATE cp_weeks SET push_rule = ? WHERE id = ?")->execute([$push, $week_id]);

            $cnt = $pdo->prepare("SELECT COUNT(*) FROM cp_entries WHERE week_id = ?");
            $cnt->execute([$week_id]);
            if ((int)$cnt->fetchColumn() > 0) {
                // Picks point at game rows, so replacing the list would orphan
                // them. Once anyone has entered, only the lines stay editable.
                $gs = $pdo->prepare("SELECT * FROM cp_games WHERE week_id = ? ORDER BY sort_order");
                $gs->execute([$week_id]);
                $current = $gs->fetchAll();
                if (count($current) !== count($clean)) {
                    $pdo->rollBack(); http_response_code(409);
                    echo json_encode(['error' => 'Picks are already in for this week, so games cannot be added or removed.']); exit;
                }
                $up = $pdo->prepare("UPDATE cp_games SET favorite = ?, spread = ? WHERE id = ?");
                foreach ($current as $i => $g) {
                    if ($g['away_team'] !== $clean[$i]['away'] || $g['home_team'] !== $clean[$i]['home']) {
                        $pdo->rollBack(); http_response_code(409);
                        echo json_encode(['error' => 'Picks are already in, so the matchups cannot change — only the spreads.']); exit;
                    }
                    $up->execute([$clean[$i]['favorite'], $clean[$i]['spread'], $g['id']]);
                }
                $pdo->commit();
                echo json_encode(['success' => true, 'week_id' => $week_id, 'updated' => 'spreads']); exit;
            }
            $pdo->prepare("DELETE FROM cp_games WHERE week_id = ?")->execute([$week_id]);
        }

        $ins = $pdo->prepare("INSERT INTO cp_games (week_id, sort_order, away_team, home_team, favorite, spread) VALUES (?,?,?,?,?,?)");
        foreach ($clean as $i => $g) {
            $ins->execute([$week_id, $i, $g['away'], $g['home'], $g['favorite'], $g['spread']]);
        }
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500); echo json_encode(['error' => 'Could not save the week.']); exit;
    }

    echo json_encode(['success' => true, 'week_id' => $week_id]);
    exit;
}

// POST ?action=cp_save_entry  {season, week, player_name, picks:[{game_id,pick,confidence}]}
if ($method === 'POST' && $action === 'cp_save_entry') {
    $body   = json_decode(file_get_contents('php://input'), true);
    $season = intval($body['season'] ?? 0);
    $week_n = intval($body['week']   ?? 0);
    $name   = trim((string)($body['player_name'] ?? ''));
    $picks  = is_array($body['picks'] ?? null) ? $body['picks'] : [];

    if ($name === '' || mb_strlen($name) > 60) {
        http_response_code(400); echo json_encode(['error' => 'Enter a name for this sheet.']); exit;
    }

    $st = $pdo->prepare("SELECT * FROM cp_weeks WHERE season = ? AND week = ?");
    $st->execute([$season, $week_n]);
    $week = $st->fetch();
    if (!$week) { http_response_code(404); echo json_encode(['error' => 'That week has not been set up yet.']); exit; }

    $gs = $pdo->prepare("SELECT id FROM cp_games WHERE week_id = ?");
    $gs->execute([$week['id']]);
    $valid = array_map('intval', $gs->fetchAll(PDO::FETCH_COLUMN));
    $n     = count($valid);

    $clean = []; $used_conf = []; $used_game = [];
    foreach ($picks as $p) {
        $gid  = intval($p['game_id'] ?? 0);
        $conf = intval($p['confidence'] ?? 0);
        $side = ($p['pick'] ?? '') === 'away' ? 'away' : (($p['pick'] ?? '') === 'home' ? 'home' : '');
        if ($conf === 0 || $side === '') continue;              // a deliberately blank row
        if (!in_array($gid, $valid, true)) {
            http_response_code(400); echo json_encode(['error' => 'A pick refers to a game that is not in this week.']); exit;
        }
        if ($conf < 1 || $conf > $n) {
            http_response_code(400); echo json_encode(['error' => 'Confidence values must be between 1 and ' . $n . '.']); exit;
        }
        if (isset($used_game[$gid])) {
            http_response_code(400); echo json_encode(['error' => 'One game has two picks on it.']); exit;
        }
        if (isset($used_conf[$conf])) {
            http_response_code(400); echo json_encode(['error' => 'Confidence ' . $conf . ' is used twice — each value can only be used once.']); exit;
        }
        $used_game[$gid] = true; $used_conf[$conf] = true;
        $clean[] = ['game_id' => $gid, 'pick' => $side, 'confidence' => $conf];
    }
    if (!$clean) { http_response_code(400); echo json_encode(['error' => 'No picks to save.']); exit; }

    $pdo->beginTransaction();
    try {
        $find = $pdo->prepare("SELECT id FROM cp_entries WHERE week_id = ? AND player_name = ?");
        $find->execute([$week['id'], $name]);
        $entry_id = $find->fetchColumn();
        if ($entry_id) {
            // Re-uploading a sheet replaces that player's picks rather than
            // stacking a second entry beside the first.
            $entry_id = (int)$entry_id;
            $pdo->prepare("DELETE FROM cp_picks WHERE entry_id = ?")->execute([$entry_id]);
        } else {
            $pdo->prepare("INSERT INTO cp_entries (week_id, player_name) VALUES (?,?)")
                ->execute([$week['id'], $name]);
            $entry_id = (int)$pdo->lastInsertId();
        }
        $ins = $pdo->prepare("INSERT INTO cp_picks (entry_id, game_id, pick, confidence) VALUES (?,?,?,?)");
        foreach ($clean as $p) $ins->execute([$entry_id, $p['game_id'], $p['pick'], $p['confidence']]);
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500); echo json_encode(['error' => 'Could not save these picks.']); exit;
    }

    echo json_encode(['success' => true, 'entry_id' => $entry_id, 'saved' => count($clean)]);
    exit;
}

// GET ?action=cp_week&season=&week=
if ($method === 'GET' && $action === 'cp_week') {
    $season = intval($_GET['season'] ?? 0);
    $week_n = intval($_GET['week']   ?? 0);
    $st = $pdo->prepare("SELECT * FROM cp_weeks WHERE season = ? AND week = ?");
    $st->execute([$season, $week_n]);
    $week = $st->fetch();
    if (!$week) { echo json_encode(['success' => true, 'exists' => false]); exit; }

    cpRefreshScores($pdo, $week, !empty($_GET['force']));
    $st->execute([$season, $week_n]);
    $week = $st->fetch();

    echo json_encode(['success' => true, 'exists' => true] + cpWeekPayload($pdo, $week));
    exit;
}

// =============================================================
// TEXTING A SHEET IN — actions prefixed cp_sms / cp_pending / cp_roster
//
// The number a sheet arrives from is the identity. That is strictly better
// than the name someone types into the web form: no typos splitting one player
// into two, and nobody entering picks under somebody else's name.
//
// Nothing a text produces is ever filed straight into the standings. The read
// is staged and a link comes back, because a misread confidence number is
// silent and corrupts everyone's score, not just the sender's.
// =============================================================

$pdo->exec("CREATE TABLE IF NOT EXISTS cp_players (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    phone       VARCHAR(20)  NOT NULL UNIQUE,
    player_name VARCHAR(60)  NOT NULL,
    opted_out   TINYINT(1)   DEFAULT 0,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// A sheet read from a text, waiting for its sender to confirm it. The token is
// the only thing that opens it, so it is long, random, and short-lived.
$pdo->exec("CREATE TABLE IF NOT EXISTS cp_pending (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    token       VARCHAR(40)  NOT NULL UNIQUE,
    week_id     INT          NOT NULL,
    player_name VARCHAR(60)  NOT NULL,
    phone       VARCHAR(20)  DEFAULT NULL,
    rows_json   LONGTEXT     NOT NULL,
    note        VARCHAR(255) DEFAULT NULL,
    claimed_at  DATETIME     DEFAULT NULL,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_week (week_id),
    FOREIGN KEY (week_id) REFERENCES cp_weeks(id) ON DELETE CASCADE
)");

/**
 * Twilio signs every webhook: HMAC-SHA1 over the exact URL it called plus each
 * POST field in key order, keyed by the account auth token.
 *
 * Without this the webhook is an open endpoint that files data into the pool
 * and spends Anthropic credits for anyone who finds the URL.
 */
function cpTwilioSignatureValid(string $auth_token, string $url, array $post, string $signature): bool {
    if ($auth_token === '' || $signature === '') return false;
    ksort($post);
    $data = $url;
    foreach ($post as $k => $v) {
        if (is_array($v)) continue;               // Twilio never sends these
        $data .= $k . $v;
    }
    $expected = base64_encode(hash_hmac('sha1', $data, $auth_token, true));
    return hash_equals($expected, $signature);
}

/**
 * The URL Twilio signed. Proxies routinely rewrite the scheme, and a mismatch
 * here fails every signature, so an explicit value wins when one is configured.
 */
function cpWebhookUrl(): string {
    if (!empty($_ENV['TWILIO_WEBHOOK_URL'])) {
        $base = $_ENV['TWILIO_WEBHOOK_URL'];
        $qs   = $_SERVER['QUERY_STRING'] ?? '';
        return $qs !== '' && !str_contains($base, '?') ? $base . '?' . $qs : $base;
    }
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'davenn.com';
    return $scheme . '://' . $host . ($_SERVER['REQUEST_URI'] ?? '');
}

/** A TwiML reply. Twilio speaks XML back on the webhook response. */
function cpTwiml(string $message): void {
    header('Content-Type: text/xml; charset=UTF-8');
    echo '<?xml version="1.0" encoding="UTF-8"?><Response><Message>'
       . htmlspecialchars($message, ENT_XML1 | ENT_QUOTES, 'UTF-8')
       . '</Message></Response>';
    exit;
}

/** No reply at all — the correct answer to a message we should stay quiet on. */
function cpTwimlSilent(): void {
    header('Content-Type: text/xml; charset=UTF-8');
    echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
    exit;
}

/**
 * A TwiML reply sent now, with the script carrying on after Twilio has it.
 *
 * Twilio abandons a messaging webhook after 15 seconds (error 11200 / 11203)
 * and the limit cannot be raised. Reading a sheet takes longer than that, so
 * the webhook answers straight away and the result goes out afterwards as a
 * fresh message through the REST API.
 *
 * fastcgi_finish_request() closes the connection cleanly under PHP-FPM; the
 * Content-Length / Connection: close fallback covers other handlers, and
 * Content-Encoding: none stops mod_deflate holding the body back to compress it.
 */
function cpTwimlAndContinue(string $message): void {
    ignore_user_abort(true);
    @set_time_limit(300);
    $xml = '<?xml version="1.0" encoding="UTF-8"?><Response><Message>'
         . htmlspecialchars($message, ENT_XML1 | ENT_QUOTES, 'UTF-8')
         . '</Message></Response>';
    header('Content-Type: text/xml; charset=UTF-8');
    header('Content-Length: ' . strlen($xml));
    header('Content-Encoding: none');
    header('Connection: close');
    echo $xml;
    if (function_exists('fastcgi_finish_request'))   { fastcgi_finish_request();   return; }
    if (function_exists('litespeed_finish_request')) { litespeed_finish_request(); return; }
    while (ob_get_level() > 0) ob_end_flush();
    flush();
}

/**
 * An absolute URL back into this site.
 *
 * Deliberately not built on APP_URL: that points at Toolshare's own page
 * because it is the call-to-action button in Toolshare's borrow emails, so
 * treating it as a base produced /toolbox.html/nflpool.html. The request host
 * is always right here and needs no configuration on the server.
 *
 * Scheme is fixed to https — the only consumer is a link inside a text
 * message, and the site is served over TLS.
 */
function cpSiteUrl(string $path): string {
    $host = $_SERVER['HTTP_HOST'] ?? 'davenn.com';
    return 'https://' . $host . '/' . ltrim($path, '/');
}

/** Best-effort E.164 tidy-up so the same phone is one row, not several. */
function cpNormalisePhone(string $raw): string {
    $digits = preg_replace('/[^0-9]/', '', $raw);
    if ($digits === '') return '';
    if (strlen($digits) === 10) return '+1' . $digits;                 // bare US
    if (strlen($digits) === 11 && $digits[0] === '1') return '+' . $digits;
    return '+' . $digits;
}

/** Fetch Twilio-hosted media. The URL needs the account credentials. */
function cpFetchTwilioMedia(string $url, string $sid, string $token): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERPWD        => $sid . ':' . $token,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['body' => ($body !== false && $code === 200) ? $body : null, 'code' => $code];
}

// POST ?action=cp_sms — Twilio inbound webhook.
if ($method === 'POST' && $action === 'cp_sms') {
    $sid   = $_ENV['TWILIO_ACCOUNT_SID'] ?? '';
    $token = $_ENV['TWILIO_AUTH_TOKEN']  ?? '';

    if (!cpTwilioSignatureValid($token, cpWebhookUrl(), $_POST, $_SERVER['HTTP_X_TWILIO_SIGNATURE'] ?? '')) {
        http_response_code(403);
        header('Content-Type: text/plain');
        echo 'Invalid signature';
        exit;
    }

    $from  = cpNormalisePhone((string)($_POST['From'] ?? ''));
    $body  = trim((string)($_POST['Body'] ?? ''));
    $count = intval($_POST['NumMedia'] ?? 0);
    if ($from === '') cpTwimlSilent();

    $find = $pdo->prepare("SELECT * FROM cp_players WHERE phone = ?");
    $find->execute([$from]);
    $player = $find->fetch() ?: null;

    // ── Keywords ─────────────────────────────────────────────────────────
    // Carriers act on STOP before it reaches us, but our own record has to
    // agree or we would keep the number linked after someone opted out.
    $word = strtoupper(preg_replace('/[^A-Za-z]/', '', $body));
    // The full set Twilio acts on, so the words filed with the campaign and
    // the words we honour are the same list. OPTOUT and REVOKE were missing.
    if (in_array($word, ['STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT', 'OPTOUT', 'REVOKE'], true)) {
        if ($player) $pdo->prepare("UPDATE cp_players SET opted_out = 1 WHERE id = ?")->execute([$player['id']]);
        cpTwimlSilent();   // the carrier sends its own confirmation
    }
    if (in_array($word, ['START', 'UNSTOP', 'YES'], true)) {
        // One number carries two programs, so START means different things
        // depending on who sends it. A pool player resuming after STOP gets the
        // pool back and nothing more — enrolling them in update alerts they
        // never asked for would be exactly the unsolicited messaging the
        // campaign forbids.
        if ($player) {
            $pdo->prepare("UPDATE cp_players SET opted_out = 0 WHERE id = ?")->execute([$player['id']]);
            // Doubles as the pool campaign's filed opt-in confirmation, so it has
            // to name the program, the rates, and both keyword routes.
            cpTwiml('davenn.com Confidence Pool: you are set up again. Text a photo of your pick sheet any time and I will reply with a link to check it. Message and data rates may apply. Reply HELP for help, STOP to opt out.');
        }

        // Anyone else texting START is opting in to update notifications — the
        // keyword route filed on the A2P campaign alongside the web form. Same
        // row the form creates, same confirmation it sends. A repeat START is
        // answered again rather than ignored: the person is asking whether they
        // are signed up, and the confirmation is the answer.
        try {
            $pdo->prepare("INSERT INTO subscribers (contact_type, contact_value, unsub_token) VALUES ('phone', ?, ?)")
                ->execute([$from, bin2hex(random_bytes(16))]);
        } catch (PDOException $e) {
            // already subscribed
        }
        cpTwiml(optInMessage());
    }
    if ($word === 'HELP' || $word === 'INFO') {
        // This webhook is only on the pool's number — update notifications go
        // out from a different one — so HELP answers for the pool alone.
        // Word for word the help message filed with the pool's own A2P
        // campaign; a reviewer may text the number and compare.
        cpTwiml('davenn.com Confidence Pool: text a photo of your filled-in pick sheet and I will read it and send back a link to check it. Message and data rates may apply. Reply STOP to opt out. Help: support@davenn.com');
    }
    if ($player && $player['opted_out']) cpTwimlSilent();

    // ── Registering a number ─────────────────────────────────────────────
    if (!$player) {
        // A plain bit of text from an unknown number is taken as their name.
        if ($count === 0 && $body !== '' && mb_strlen($body) <= 60) {
            $name = trim(preg_replace('/\s+/', ' ', $body));
            try {
                $pdo->prepare("INSERT INTO cp_players (phone, player_name) VALUES (?,?)")->execute([$from, $name]);
            } catch (PDOException $e) {
                cpTwiml('That name is already taken in the pool. Reply with a different one.');
            }
            cpTwiml('davenn.com Confidence Pool: thanks ' . $name . ', this number is now linked to your picks. Text a photo of your sheet whenever you are ready. Reply STOP to opt out.');
        }
        cpTwiml('I do not recognise this number yet. Reply with your name first, then text a photo of your sheet.');
    }

    if ($count === 0) {
        cpTwiml('Send a photo of your filled-in pick sheet and I will read it. Reply HELP for more.');
    }

    // ── A sheet ──────────────────────────────────────────────────────────
    $api_key = $_ENV['ANTHROPIC_API_KEY'] ?? '';
    if (!$api_key) cpTwiml('Sheet reading is offline right now - try the website instead: ' . cpSiteUrl('nflpool.html'));

    $media_url  = (string)($_POST['MediaUrl0'] ?? '');
    $media_type = (string)($_POST['MediaContentType0'] ?? '');
    if (!in_array($media_type, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        cpTwiml('That attachment is not a photo I can read (' . ($media_type ?: 'unknown type') . '). Send a JPG or PNG.');
    }

    // Stage it against the newest week that has been set up. A text carries no
    // week number, and guessing from the calendar would file a late sheet into
    // the wrong week. Checked before the read so a sheet with nowhere to go
    // costs nothing.
    $week_row = $pdo->query("SELECT * FROM cp_weeks ORDER BY season DESC, week DESC LIMIT 1")->fetch();
    if (!$week_row) cpTwiml('No week is set up yet. The first sheet has to be entered on the website: ' . cpSiteUrl('nflpool.html'));

    // Everything from here outlasts Twilio's 15-second webhook limit, so the
    // sender hears back now and every later answer is a new outbound text.
    cpTwimlAndContinue('davenn.com Confidence Pool: got your sheet, reading it now. I will text you back in about a minute.');
    $later = function (string $msg) use ($from): void { sendSms($from, $msg); exit; };

    $fetched = cpFetchTwilioMedia($media_url, $sid, $token);
    if ($fetched['body'] === null) $later('davenn.com Confidence Pool: I could not download that photo. Try sending it again.');

    [$bytes, $mt] = cpPrepareImage($fetched['body'], $media_type);
    $result = cpReadSheet($bytes, $mt, $api_key);
    if ($result === null || empty($result['readable']) || !is_array($result['games'] ?? null) || !count($result['games'])) {
        $later('davenn.com Confidence Pool: I could not read that sheet. Try again with the whole page in frame, flat, in even light. Reply HELP for help.');
    }

    $matched = cpMatchRows($pdo, $result, $week_row);
    $picked  = 0;
    foreach ($matched['rows'] as $r) if ($r['pick'] !== '' && $r['confidence'] > 0) $picked++;

    // The name written on the sheet wins over the one linked to the phone: one
    // person often texts in sheets for several players. It is only the default
    // in the review form, so a misread name is corrected there before saving.
    $sheet_name = mb_substr(trim(preg_replace('/\s+/', ' ', (string)($result['player_name'] ?? ''))), 0, 60);

    $token_str = bin2hex(random_bytes(16));
    $pdo->prepare("INSERT INTO cp_pending (token, week_id, player_name, phone, rows_json, note) VALUES (?,?,?,?,?,?)")
        ->execute([
            $token_str, $week_row['id'], $sheet_name !== '' ? $sheet_name : $player['player_name'], $from,
            json_encode($matched['rows']),
            mb_substr(trim((string)($result['note'] ?? '')), 0, 255) ?: null,
        ]);

    $link  = cpSiteUrl('nflpool.html?review=' . $token_str);
    // Leads with the brand because the A2P campaign filing requires every
    // sample message to identify who is texting, and this is one of them.
    // ASCII only — see the note on the broadcast suffix above.
    $reply = 'davenn.com Confidence Pool: read ' . $picked . ' of ' . count($matched['rows'])
           . ' picks for Week ' . (int)$week_row['week'] . '.';
    if ($matched['warnings']) $reply .= ' ' . count($matched['warnings']) . ' thing(s) to check.';
    $reply .= ' Nothing is saved yet - open this to confirm: ' . $link;
    $later($reply);
}

// GET ?action=cp_pending&token=… — collect a staged sheet for review.
if ($method === 'GET' && $action === 'cp_pending') {
    $t = (string)($_GET['token'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $t)) {
        http_response_code(400); echo json_encode(['error' => 'Bad link.']); exit;
    }
    $st = $pdo->prepare("SELECT p.*, w.season, w.week FROM cp_pending p JOIN cp_weeks w ON w.id = p.week_id WHERE p.token = ?");
    $st->execute([$t]);
    $row = $st->fetch();
    if (!$row) { http_response_code(404); echo json_encode(['error' => 'That link has expired or was already used.']); exit; }

    echo json_encode([
        'success'     => true,
        'season'      => (int)$row['season'],
        'week'        => (int)$row['week'],
        'player_name' => $row['player_name'],
        'note'        => $row['note'],
        'rows'        => json_decode($row['rows_json'], true) ?: [],
    ]);
    exit;
}

// DELETE ?action=cp_pending&token=… — drop a staged sheet once it is saved.
if ($method === 'DELETE' && $action === 'cp_pending') {
    $t = (string)($_GET['token'] ?? '');
    if (preg_match('/^[a-f0-9]{32}$/', $t)) {
        $pdo->prepare("DELETE FROM cp_pending WHERE token = ?")->execute([$t]);
    }
    echo json_encode(['success' => true]);
    exit;
}

// GET ?action=cp_roster  header: X-Admin-Secret — who is linked to what number.
// Admin-guarded because this is the one table that maps a person to a phone
// number, which is the most sensitive thing the pool holds.
if ($method === 'GET' && $action === 'cp_roster') {
    $admin_secret = $_ENV['ADMIN_SECRET'] ?? '';
    if (!$admin_secret || !hash_equals($admin_secret, $_SERVER['HTTP_X_ADMIN_SECRET'] ?? '')) {
        http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit;
    }
    $rows = $pdo->query("SELECT id, phone, player_name, opted_out, created_at FROM cp_players ORDER BY player_name")->fetchAll();
    echo json_encode(['success' => true, 'players' => array_map(fn($r) => [
        'id'          => (int)$r['id'],
        'phone'       => $r['phone'],
        'player_name' => $r['player_name'],
        'opted_out'   => (bool)$r['opted_out'],
        'created_at'  => $r['created_at'],
    ], $rows)]);
    exit;
}

// DELETE ?action=cp_roster&id=…  header: X-Admin-Secret — unlink a number.
if ($method === 'DELETE' && $action === 'cp_roster') {
    $admin_secret = $_ENV['ADMIN_SECRET'] ?? '';
    if (!$admin_secret || !hash_equals($admin_secret, $_SERVER['HTTP_X_ADMIN_SECRET'] ?? '')) {
        http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit;
    }
    $stmt = $pdo->prepare("DELETE FROM cp_players WHERE id = ?");
    $stmt->execute([intval($_GET['id'] ?? 0)]);
    echo json_encode(['success' => true, 'deleted' => $stmt->rowCount()]);
    exit;
}

// POST ?action=cp_purge_photos  header: X-Admin-Secret
// One-shot cleanup for sheet photos written before the app stopped keeping
// them. Nothing creates these files any more, so this should report 0 on every
// run after the first.
if ($method === 'POST' && $action === 'cp_purge_photos') {
    $admin_secret = $_ENV['ADMIN_SECRET'] ?? '';
    $given        = $_SERVER['HTTP_X_ADMIN_SECRET'] ?? '';
    if (!$admin_secret || !hash_equals($admin_secret, $given)) {
        http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit;
    }

    $dir     = rtrim($upload_dir, '/') . '/';
    $deleted = 0; $failed = [];
    // Only ever touches the sheet_ prefix this app used — Toolshare's photos
    // live in the same directory and are not ours to remove.
    foreach (glob($dir . 'sheet_*') ?: [] as $file) {
        if (is_file($file) && @unlink($file)) $deleted++;
        else $failed[] = basename($file);
    }

    echo json_encode([
        'success'   => true,
        'deleted'   => $deleted,
        'failed'    => $failed,
        'directory' => $dir,
    ]);
    exit;
}

// GET ?action=cp_diag&season=&week=  header: X-Admin-Secret — why are scores
// not updating? Probes the outbound path from the web host itself, which is
// the one thing that cannot be checked from a laptop.
//
// Admin-guarded despite holding no credentials or user data: the reply names
// the PHP and curl versions, open_basedir and the server's own address, and
// that combination is worth more to someone scanning for a way in than it is
// to anyone else. Nothing in the site calls this — it is run by hand.
if ($method === 'GET' && $action === 'cp_diag') {
    $admin_secret = $_ENV['ADMIN_SECRET'] ?? '';
    if (!$admin_secret || !hash_equals($admin_secret, $_SERVER['HTTP_X_ADMIN_SECRET'] ?? '')) {
        http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit;
    }
    $season = intval($_GET['season'] ?? 0);
    $week_n = intval($_GET['week']   ?? 0);
    // Every endpoint/agent pairing, each reporting what actually came back.
    // Whatever is turning the request away is visible in the body, so a slice
    // of it is included rather than being reduced to a byte count.
    $attempts = [];
    $names    = ['site.api', 'cdn.core'];
    foreach (cpEspnSources($season, $week_n) as $i => $url) {
        foreach (cpUserAgents() as $j => $ua) {
            $started = microtime(true);
            $r       = cpHttpTry($url, $ua);
            $body    = (string)($r['body'] ?? '');
            $events  = cpEventsFrom(json_decode($body, true));
            $attempts[] = [
                'source'     => $names[$i] ?? $i,
                'agent'      => $j === 0 ? 'identifying' : 'browser',
                'via'        => $r['via'],
                'code'       => $r['code'],
                'elapsed_ms' => (int)round((microtime(true) - $started) * 1000),
                'bytes'      => strlen($body),
                'events'     => $events ? count($events) : null,
                'error'      => $r['error'],
                'snippet'    => $events ? null : mb_substr(preg_replace('/\s+/', ' ', $body), 0, 320),
            ];
        }
    }
    echo json_encode([
        'success'         => true,
        'php'             => PHP_VERSION,
        'curl'            => function_exists('curl_init'),
        'curl_version'    => function_exists('curl_version') ? (curl_version()['version'] ?? null) : null,
        'allow_url_fopen' => (bool)ini_get('allow_url_fopen'),
        'open_basedir'    => ini_get('open_basedir') ?: null,
        'server_ip'       => $_SERVER['SERVER_ADDR'] ?? null,
        'attempts'        => $attempts,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

// GET ?action=cp_weeks — every week that has been set up, newest first.
if ($method === 'GET' && $action === 'cp_weeks') {
    $rows = $pdo->query("SELECT w.season, w.week,
                                (SELECT COUNT(*) FROM cp_games   g WHERE g.week_id = w.id) AS games,
                                (SELECT COUNT(*) FROM cp_entries e WHERE e.week_id = w.id) AS entries
                         FROM cp_weeks w ORDER BY w.season DESC, w.week DESC")->fetchAll();
    echo json_encode(['success' => true, 'weeks' => array_map(fn($r) => [
        'season'  => (int)$r['season'],
        'week'    => (int)$r['week'],
        'games'   => (int)$r['games'],
        'entries' => (int)$r['entries'],
    ], $rows)]);
    exit;
}

// DELETE ?action=cp_entry&id=X
if ($method === 'DELETE' && $action === 'cp_entry') {
    $stmt = $pdo->prepare("DELETE FROM cp_entries WHERE id = ?");
    $stmt->execute([intval($_GET['id'] ?? 0)]);
    if ($stmt->rowCount() === 0) { http_response_code(404); echo json_encode(['error' => 'No such entry.']); exit; }
    echo json_encode(['success' => true]);
    exit;
}

// DELETE ?action=cp_week&season=&week=
if ($method === 'DELETE' && $action === 'cp_week') {
    $stmt = $pdo->prepare("DELETE FROM cp_weeks WHERE season = ? AND week = ?");
    $stmt->execute([intval($_GET['season'] ?? 0), intval($_GET['week'] ?? 0)]);
    if ($stmt->rowCount() === 0) { http_response_code(404); echo json_encode(['error' => 'No such week.']); exit; }
    echo json_encode(['success' => true]);
    exit;
}

// =============================================================
// WILDCATSXC (wildcatsxc.html) — actions prefixed xc_
//
// Cross country meet results, read off a results sheet (photo or PDF) by
// Claude, checked by hand, then filed per meet. The point is the athlete over
// a season, so each runner is an xc_athletes row that every later meet joins
// back to — matched on name + school, normalised, never fuzzily. A near miss
// ("Jon" against "John") is offered to the coach as a suggestion rather than
// merged, because two real teammates can be one letter apart.
//
// One shared team record behind a PIN (xcRequirePin), not accounts: every
// coach who has the number sees and edits the same meets. Results name minors,
// so none of it is public, and the PIN lives in the server .env, never in this
// public repo.
// =============================================================

/** Lowercase, punctuation folded to single spaces. "O'Brien, Jr." → "o brien jr" */
function xcNorm(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    return trim($s);
}

/** A school with the suffixes sheets add or drop removed, so "Madison West HS" is "Madison West". */
function xcSchoolNorm(string $s): string {
    return trim(preg_replace('/\s+(high school|high|hs|h s|sr high|senior high)$/', '', xcNorm($s)));
}

function xcKey(string $name, string $school): string {
    return xcNorm($name) . '|' . xcSchoolNorm($school);
}

/**
 * "17:23.4", "17:23.45", "17:23" or "1:02:03.5" → milliseconds, or null.
 * Bounded to a minute either side of anything a cross country race produces,
 * which also catches a place number that landed in the time column.
 */
function xcParseTime(string $t): ?int {
    if (!preg_match('/^(?:(\d{1,2}):)?(\d{1,2}):(\d{2})(?:\.(\d{1,3}))?$/', trim($t), $m)) return null;
    $h = (int)($m[1] ?? 0); $min = (int)$m[2]; $sec = (int)$m[3];
    if ($sec > 59 || ($m[1] !== '' && $min > 59)) return null;
    $ms = (($h * 60 + $min) * 60 + $sec) * 1000 + (int)str_pad($m[4] ?? '', 3, '0');
    return ($ms >= 60000 && $ms <= 7200000) ? $ms : null;
}

/** Milliseconds back to the form sheets print: tenths or hundredths only when they were there. */
function xcFormatTime(int $ms): string {
    $s = intdiv($ms, 1000); $frac = $ms % 1000;
    $out = intdiv($s, 60) . ':' . str_pad((string)($s % 60), 2, '0', STR_PAD_LEFT);
    if ($frac === 0) return $out;
    return $out . '.' . ($frac % 100 === 0 ? (string)intdiv($frac, 100) : str_pad((string)intdiv($frac, 10), 2, '0', STR_PAD_LEFT));
}

/** Drop athletes no result points at any more, after a meet is rewritten or deleted. */
function xcPruneAthletes(PDO $pdo): void {
    $pdo->exec("DELETE a FROM xc_athletes a LEFT JOIN xc_results r ON r.athlete_id = a.id WHERE r.id IS NULL");
}

/**
 * Line freshly read or pasted rows up against the stored athletes. A school
 * that normalises to a stored one takes the stored spelling; an exact name +
 * school match is marked existing and takes the stored name; a new name close
 * to a stored teammate's carries that name as `similar`, for the coach to
 * accept or ignore. Unusable times are blanked so the row shows as needing one.
 * Returns ['rows' => [...], 'warnings' => [...]].
 */
function xcMatchRows(PDO $pdo, array $rows): array {
    $by_key = []; $by_school = []; $school_spelling = [];
    foreach ($pdo->query("SELECT name, school, match_key FROM xc_athletes")->fetchAll() as $a) {
        $by_key[$a['match_key']] = $a;
        $sn = xcSchoolNorm($a['school']);
        $by_school[$sn][] = $a;
        $school_spelling[$sn] = $a['school'];
    }
    $keys_in_batch = [];
    foreach ($rows as $r) $keys_in_batch[xcKey($r['name'], $r['school'])] = true;

    $warnings = []; $no_time = 0;
    foreach ($rows as &$r) {
        $sn = xcSchoolNorm($r['school']);
        if (isset($school_spelling[$sn])) $r['school'] = $school_spelling[$sn];
        $key = xcKey($r['name'], $r['school']);
        $r['status']  = 'new';
        $r['similar'] = null;
        if (isset($by_key[$key])) {
            $r['status'] = 'existing';
            $r['name']   = $by_key[$key]['name'];
        } else {
            // Suggest, never merge: a near miss is offered only when the
            // existing athlete is not also in this batch under their own name.
            $n = xcNorm($r['name']);
            foreach ($by_school[$sn] ?? [] as $a) {
                if (isset($keys_in_batch[$a['match_key']])) continue;
                $an = xcNorm($a['name']);
                if (strlen($n) >= 5 && $n[0] === ($an[0] ?? '') && levenshtein($n, $an) <= 2) { $r['similar'] = $a['name']; break; }
            }
        }
        if ($r['time'] !== '' && xcParseTime($r['time']) === null) {
            $warnings[] = $r['name'] . ': could not make sense of the time "' . $r['time'] . '".';
            $r['time'] = '';
        }
        if ($r['time'] === '') $no_time++;
    }
    unset($r);
    if ($no_time) $warnings[] = $no_time . ' runner(s) need a time entered.';
    return ['rows' => $rows, 'warnings' => $warnings];
}

/**
 * Gate for every WildcatsXC endpoint: the X-XC-PIN header must match XC_PIN
 * from .env. Unset, the app stays locked rather than open.
 *
 * Ten wrong guesses from one address in an hour locks that address out for
 * the rest of the hour, checked before the PIN is compared, so a locked-out
 * client learns nothing from further tries. At that rate, walking all 10,000
 * PINs from one address takes about six weeks.
 */
function xcRequirePin(PDO $pdo): void {
    $pin = $_ENV['XC_PIN'] ?? '';
    if ($pin === '') { http_response_code(503); echo json_encode(['error' => 'WildcatsXC is not set up on the server yet.']); exit; }

    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $pdo->exec("DELETE FROM xc_pin_attempts WHERE created_at < NOW() - INTERVAL 1 DAY");
    $st = $pdo->prepare("SELECT COUNT(*) FROM xc_pin_attempts WHERE ip = ? AND created_at > NOW() - INTERVAL 1 HOUR");
    $st->execute([$ip]);
    if ((int)$st->fetchColumn() >= 10) {
        http_response_code(429); echo json_encode(['error' => 'Too many wrong PINs. Try again in an hour.']); exit;
    }
    if (!hash_equals($pin, (string)($_SERVER['HTTP_X_XC_PIN'] ?? ''))) {
        $pdo->prepare("INSERT INTO xc_pin_attempts (ip) VALUES (?)")->execute([$ip]);
        http_response_code(401); echo json_encode(['error' => 'Wrong PIN.']); exit;
    }
}

/**
 * Ask Claude to read a results PDF. $docs is a list of [bytes, 'application/pdf'].
 * Returns ['result' => array] or ['error' => string].
 *
 * Streamed, unlike the pick-sheet reader: a big invitational is hundreds of
 * rows, which is minutes of output, and a non-streamed request that long sits
 * silent on the wire where any proxy along the way can drop it.
 *
 * $page > 0 reads only that page of a single PDF. A whole meet in one request
 * ran past what the host allows a PHP request (a 318-runner PDF died at 97s),
 * so the app reads a PDF a page per request; every reply carries page_count
 * so the app learns how many pages to ask for after the first. The PDF is
 * marked for prompt caching, so the pages after the first re-read it from the
 * cache rather than paying for it again.
 */
function xcReadResults(array $docs, string $schools, string $api_key, int $page = 0): array {
    $prompt = 'These are the results of a high school cross country meet, as a PDF from the meet or its timing company. There may be several pages and several races.

Report:
- meet_name, as printed.
- meet_date as YYYY-MM-DD, or "" if no date is printed. If the date has no year, it is ' . date('Y') . '.
- location: the course or host, or "" if not printed.
- races: one entry per race (for example "Varsity Girls" or "JV Boys"), each with its name as printed, distance_m (5000 for 5K, 3219 for 2 miles, 0 if not shown), and its individual finishers in finishing order.

For each finisher:
- place: the overall place in that race, or 0 if not printed.
- name: in First Last order. If the sheet prints "Last, First", reorder it.
- grade: 9 to 12, converting FR/SO/JR/SR to 9/10/11/12, or 0 if not printed.
- school: the full school name. When the sheet abbreviates schools and has a key or team score table naming them, use the full name from it; otherwise write it as printed.
- time: exactly as printed, for example "17:23.4".

Only individual results: skip team score tables. Leave out runners with no finishing time (DNF, DNS, DQ) and say in note how many were left out.

These times are used to track each runner across a season, so a misread digit does real harm. If a time is not clearly legible, give "" for it rather than a guess and say so in note. Set readable to false only if this is not a cross country results sheet or is too unclear to read at all.';

    if ($schools !== '') {
        $prompt .= "\n\nOnly include runners from these schools: " . $schools . '. Sheets often abbreviate school names, so include a runner when the abbreviation plainly means one of these schools. Leave everyone else out, but keep their place numbers as printed.';
    }
    $prompt .= "\n\nAlso report page_count: how many pages this PDF has.";
    if ($page > 0) {
        $prompt .= "\n\nRead ONLY page " . $page . ' of this PDF; the other pages are read separately. Take the meet name, date and location from wherever they appear in the document. If a race on this page continues from an earlier page without repeating its heading, use that race\'s name and distance from the earlier page. If this page has no individual results (a cover page or only team scores), return an empty races list with readable true.';
    }

    $schema = [
        'type'       => 'object',
        'properties' => [
            'readable'   => ['type' => 'boolean'],
            'page_count' => ['type' => 'integer'],
            'meet_name' => ['type' => 'string'],
            'meet_date' => ['type' => 'string'],
            'location'  => ['type' => 'string'],
            'note'      => ['type' => 'string', 'description' => 'Anything unclear or left out, or "" if the read was clean.'],
            'races'     => [
                'type'  => 'array',
                'items' => [
                    'type'       => 'object',
                    'properties' => [
                        'name'       => ['type' => 'string'],
                        'distance_m' => ['type' => 'integer'],
                        'results'    => [
                            'type'  => 'array',
                            'items' => [
                                'type'       => 'object',
                                'properties' => [
                                    'place'  => ['type' => 'integer'],
                                    'name'   => ['type' => 'string'],
                                    'grade'  => ['type' => 'integer'],
                                    'school' => ['type' => 'string'],
                                    'time'   => ['type' => 'string'],
                                ],
                                'required'             => ['place', 'name', 'grade', 'school', 'time'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                    'required'             => ['name', 'distance_m', 'results'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required'             => ['readable', 'page_count', 'meet_name', 'meet_date', 'location', 'note', 'races'],
        'additionalProperties' => false,
    ];

    $content = [];
    foreach ($docs as [$bytes, $media_type]) {
        $content[] = [
            'type'   => 'document',
            'source' => ['type' => 'base64', 'media_type' => $media_type, 'data' => base64_encode($bytes)],
        ];
    }
    // The document is the cache prefix; the page-specific prompt comes after
    // it, so every page request after the first is a cache read.
    if ($page > 0) $content[count($content) - 1]['cache_control'] = ['type' => 'ephemeral'];
    $content[] = ['type' => 'text', 'text' => $prompt];

    $payload = json_encode([
        'model'         => 'claude-opus-5-5',
        'max_tokens'    => 64000,
        'stream'        => true,
        // A declined request is retried server-side on the model Anthropic
        // recommends for that refusal category, instead of failing outright.
        'fallbacks'     => 'default',
        'output_config' => [
            'effort' => 'medium',
            'format' => ['type' => 'json_schema', 'schema' => $schema],
        ],
        'messages'      => [['role' => 'user', 'content' => $content]],
    ]);

    // Server-sent events, parsed as they arrive. Text is kept per content
    // block and only the last text block is used: if a fallback takes over
    // mid-stream, the declined model's partial text stays in the stream ahead
    // of the fallback's complete answer.
    $buf = ''; $raw = ''; $blocks = []; $last_text = null; $stop = null; $stream_err = null;
    $on_chunk = function ($ch, string $chunk) use (&$buf, &$raw, &$blocks, &$last_text, &$stop, &$stream_err): int {
        if (strlen($raw) < 4096) $raw .= $chunk;
        $buf .= $chunk;
        while (($nl = strpos($buf, "\n")) !== false) {
            $line = rtrim(substr($buf, 0, $nl), "\r");
            $buf  = substr($buf, $nl + 1);
            if (strncmp($line, 'data:', 5) !== 0) continue;
            $ev = json_decode(trim(substr($line, 5)), true);
            if (!is_array($ev)) continue;
            $type = $ev['type'] ?? '';
            if ($type === 'content_block_start') {
                $blocks[$ev['index']] = '';
                if (($ev['content_block']['type'] ?? '') === 'text') $last_text = $ev['index'];
            } elseif ($type === 'content_block_delta' && ($ev['delta']['type'] ?? '') === 'text_delta') {
                $blocks[$ev['index']] = ($blocks[$ev['index']] ?? '') . $ev['delta']['text'];
            } elseif ($type === 'message_delta') {
                $stop = $ev['delta']['stop_reason'] ?? $stop;
            } elseif ($type === 'error') {
                $stream_err = $ev['error']['message'] ?? 'The reader stopped partway.';
            }
        }
        return strlen($chunk);
    };

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'x-api-key: ' . $api_key,
            'anthropic-version: 2023-06-01',
            'anthropic-beta: server-side-fallback-2026-07-01',
            'content-type: application/json',
        ],
        CURLOPT_WRITEFUNCTION  => $on_chunk,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => 600,
    ]);
    $ok        = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$ok || $http_code !== 200) {
        $body = json_decode($raw, true);
        return ['error' => 'Could not read the results' . (isset($body['error']['message']) ? ': ' . $body['error']['message'] : '.')];
    }
    if ($stream_err)          return ['error' => 'Could not read the results: ' . $stream_err];
    if ($stop === 'refusal')  return ['error' => 'The reader declined this file.'];
    if ($stop === 'max_tokens') {
        return ['error' => 'Too many results to read in one go. Fill in "Only these schools", or upload fewer pages at a time.'];
    }
    $result = json_decode($last_text === null ? '' : trim($blocks[$last_text]), true);
    return is_array($result) ? ['result' => $result] : ['error' => 'Could not read the results.'];
}

// POST ?action=xc_scan  (multipart: files[] — PDFs; optional schools, page)
// Reads a results PDF. PDFs only, by the owner's choice: results come in as a
// PDF, a results link (xc_milesplit) or pasted text (xc_match), never a
// photo. Saves nothing — the app shows every row for checking
// first. Rows come back flat, each carrying its race, and already lined up
// against the stored athletes: an exact name + school match takes the stored
// spelling, and a new name close to an existing teammate's carries that name
// as `similar` for the coach to accept or ignore. The files are never written
// to disk. With `page` (and a single PDF), reads just that page — how the app
// reads a PDF, a page per request, so no request runs long enough for the
// host to kill it. Every reply carries page_count.
if ($method === 'POST' && $action === 'xc_scan') {
    // A PHP fatal here (a host time limit, a lost connection) otherwise ends
    // the request as a bare 500 with no body, which tells nobody anything.
    register_shutdown_function(function () {
        $e = error_get_last();
        if ($e && in_array($e['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            echo json_encode(['error' => 'Server error while reading: ' . $e['message']]);
        }
    });
    xcRequirePin($pdo);
    $api_key = $_ENV['ANTHROPIC_API_KEY'] ?? '';
    if (!$api_key) { http_response_code(500); echo json_encode(['error' => 'Results reading is not configured on the server.']); exit; }

    // Over post_max_size PHP drops the whole body, which would otherwise look
    // like nothing was sent at all.
    if (empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        http_response_code(413); echo json_encode(['error' => 'Those files are larger than the server accepts. Try fewer pages at a time.']); exit;
    }
    $f = $_FILES['files'] ?? null;
    if (!$f || empty($f['tmp_name'])) { http_response_code(400); echo json_encode(['error' => 'No file provided.']); exit; }
    $count = is_array($f['tmp_name']) ? count($f['tmp_name']) : 1;
    if ($count > 10) { http_response_code(400); echo json_encode(['error' => 'Upload at most 10 pages at a time.']); exit; }

    $docs = []; $total = 0;
    for ($i = 0; $i < $count; $i++) {
        $name = is_array($f['name'])     ? $f['name'][$i]     : $f['name'];
        $tmp  = is_array($f['tmp_name']) ? $f['tmp_name'][$i] : $f['tmp_name'];
        $err  = is_array($f['error'])    ? $f['error'][$i]    : $f['error'];
        $size = is_array($f['size'])     ? $f['size'][$i]     : $f['size'];
        if ($err !== UPLOAD_ERR_OK || !$tmp) {
            http_response_code(400); echo json_encode(['error' => $name . ' did not upload. It may be too large.']); exit;
        }
        // The extension is a hint; the bytes are the check, so a renamed
        // photo is turned away too.
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'pdf' || file_get_contents($tmp, false, null, 0, 5) !== '%PDF-') {
            http_response_code(400); echo json_encode(['error' => 'Only PDFs can be uploaded. For anything else, paste the results or a results link.']); exit;
        }
        $total += $size;
        if ($total > 20 * 1024 * 1024) { http_response_code(400); echo json_encode(['error' => 'Keep each upload under 20 MB in total.']); exit; }
        $docs[] = [file_get_contents($tmp), 'application/pdf'];
    }

    $page = max(0, min(200, (int)($_POST['page'] ?? 0)));
    if ($page && count($docs) !== 1) {
        http_response_code(400); echo json_encode(['error' => 'A page number only applies to a single PDF.']); exit;
    }

    @set_time_limit(600);
    $schools = mb_substr(trim((string)($_POST['schools'] ?? '')), 0, 300);
    $read = xcReadResults($docs, $schools, $api_key, $page);
    if (isset($read['error'])) { http_response_code(502); echo json_encode(['error' => $read['error']]); exit; }
    $result = $read['result'];

    $rows = [];
    foreach ($result['races'] ?? [] as $race) {
        foreach ($race['results'] ?? [] as $r) {
            $rows[] = [
                'race'       => trim((string)($race['name'] ?? '')) ?: 'Race',
                'distance_m' => max(0, (int)($race['distance_m'] ?? 0)),
                'place'      => max(0, (int)($r['place'] ?? 0)),
                'name'       => trim((string)($r['name'] ?? '')),
                'grade'      => (int)($r['grade'] ?? 0),
                'school'     => trim((string)($r['school'] ?? '')),
                'time'       => trim((string)($r['time'] ?? '')),
            ];
        }
    }
    $page_count = max(1, min(200, (int)($result['page_count'] ?? 1)));
    // One page of a PDF can legitimately hold no results (a cover, team
    // scores); only a whole read with nothing in it is unreadable.
    if (empty($result['readable']) || (!$rows && !$page)) {
        echo json_encode([
            'success'    => true,
            'readable'   => false,
            'page_count' => $page_count,
            'note'       => (string)($result['note'] ?? '') ?: 'No results could be read from that file.',
        ]); exit;
    }

    // The read can outlast MySQL's idle timeout on the connection opened at
    // the start of the request; reconnect rather than die on the first query.
    try { $pdo->query('SELECT 1'); } catch (PDOException $e) {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    $matched = $rows ? xcMatchRows($pdo, $rows) : ['rows' => [], 'warnings' => []];

    echo json_encode([
        'success'    => true,
        'readable'   => true,
        'page_count' => $page_count,
        'meet'       => [
            'name'     => trim((string)($result['meet_name'] ?? '')),
            'date'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($result['meet_date'] ?? '')) ? $result['meet_date'] : '',
            'location' => trim((string)($result['location'] ?? '')),
        ],
        'note'     => (string)($result['note'] ?? ''),
        'rows'     => $matched['rows'],
        'warnings' => $matched['warnings'],
    ]);
    exit;
}

// POST ?action=xc_match  {rows:[{race, distance_m, place, name, grade, school, time}]}
// Lines rows the app parsed itself — results pasted from a site such as
// MileSplit — up against the stored athletes, exactly as xc_scan does for a
// read sheet, so a paste gets the same known / new / "same as" tags. Saves
// nothing. The parsing stays in the browser: pasted text is regular enough
// that a model read would only add cost and minutes.
if ($method === 'POST' && $action === 'xc_match') {
    xcRequirePin($pdo);
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $in   = is_array($body['rows'] ?? null) ? $body['rows'] : [];
    if (count($in) < 1 || count($in) > 3000) { http_response_code(400); echo json_encode(['error' => 'Paste between 1 and 3000 results.']); exit; }

    $rows = [];
    foreach ($in as $r) {
        $rows[] = [
            'race'       => mb_substr(trim((string)($r['race'] ?? '')), 0, 80) ?: 'Race',
            'distance_m' => min(20000, max(0, (int)($r['distance_m'] ?? 0))),
            'place'      => min(5000, max(0, (int)($r['place'] ?? 0))),
            'name'       => mb_substr(trim((string)($r['name'] ?? '')), 0, 80),
            'grade'      => (int)($r['grade'] ?? 0),
            'school'     => mb_substr(trim((string)($r['school'] ?? '')), 0, 120),
            'time'       => mb_substr(trim((string)($r['time'] ?? '')), 0, 16),
        ];
    }
    $matched = xcMatchRows($pdo, $rows);
    echo json_encode(['success' => true, 'rows' => $matched['rows'], 'warnings' => $matched['warnings']]);
    exit;
}

/**
 * One GET to MileSplit. Returns [body, httpCode], or [null, 0] if the request
 * failed or a redirect tried to leave milesplit.com. Identifies itself as this
 * site rather than as a browser: if MileSplit chooses to refuse that, the
 * import fails and says so — nothing here works around it.
 */
function xcMilesplitGet(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_FOLLOWLOCATION  => true,
        CURLOPT_MAXREDIRS       => 3,
        CURLOPT_PROTOCOLS       => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_CONNECTTIMEOUT  => 10,
        CURLOPT_TIMEOUT         => 30,
        CURLOPT_USERAGENT       => 'davenn.com WildcatsXC results import (+https://davenn.com)',
        CURLOPT_HTTPHEADER      => ['Accept: application/json, text/html'],
    ]);
    $body  = curl_exec($ch);
    $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $final = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    if ($body === false || !preg_match('#^https://([a-z0-9-]+\.)?milesplit\.com/#', $final)) return [null, 0];
    return [$body, $code];
}

// POST ?action=xc_milesplit  {url}
// Imports one MileSplit meet's results from a link a coach pasted. Only ever
// runs because a person pasted a link and pressed Import — never on a
// schedule, never following links to other meets. Two requests: the results
// page (meet name, date, which results files exist) and the performances data
// that page itself loads. Files MileSplit marks as PRO-only are skipped, not
// fetched. The link's own event / gender / division filters choose the race;
// a link without them imports every race. Saves nothing — the rows come back
// in xc_scan's shape for checking.
if ($method === 'POST' && $action === 'xc_milesplit') {
    xcRequirePin($pdo);
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $u    = parse_url(trim((string)($body['url'] ?? '')));
    $host = strtolower($u['host'] ?? '');
    // The fetch URLs are rebuilt from the meet id, so a pasted link can only
    // ever choose which MileSplit meet is read — never an arbitrary address.
    if (!preg_match('/^([a-z]{2}\.|www\.)?milesplit\.com$/', $host)
        || !preg_match('#^/meets/(\d+)(-[a-z0-9-]*)?(/|$)#i', $u['path'] ?? '', $pm)) {
        http_response_code(400); echo json_encode(['error' => 'Paste a MileSplit meet results link, like https://wi.milesplit.com/meets/…/results']); exit;
    }
    $meet_id = $pm[1];
    $base    = 'https://' . $host;
    parse_str($u['query'] ?? '', $q);
    $want_event    = strtolower(trim((string)($q['event'] ?? '')));
    $want_division = strtolower(trim((string)($q['division'] ?? '')));
    $want_gender   = ['boys' => 'M', 'm' => 'M', 'men' => 'M', 'girls' => 'F', 'f' => 'F', 'women' => 'F'][strtolower(trim((string)($q['gender'] ?? '')))] ?? '';

    [$page, $code] = xcMilesplitGet($base . '/meets/' . $meet_id . ($pm[2] ?? '') . '/results');
    if ($page === null || $code !== 200) {
        http_response_code(502); echo json_encode(['error' => 'MileSplit did not return that meet' . ($code ? ' (HTTP ' . $code . ').' : '.')]); exit;
    }
    $date = preg_match("/startDate:\s*'(\d{4}-\d{2}-\d{2})'/", $page, $m) ? $m[1] : '';
    $files = preg_match('/meetResultFiles\s*=\s*(\[.*?\]);/', $page, $m) ? (json_decode($m[1], true) ?: []) : [];
    $location = preg_match('/<meta name="description" content="[^"]* in ([^".]+)\.\s*"/', $page, $m) ? html_entity_decode(trim($m[1])) : '';
    $title = preg_match('/<meta property="og:title"[^>]*content="([^"]+)"/', $page, $m) ? html_entity_decode(preg_replace('/\s*-\s*Results\s*$/', '', $m[1])) : '';
    if (!$files) { http_response_code(404); echo json_encode(['error' => 'That meet has no results posted on MileSplit yet.']); exit; }
    $free = array_values(array_filter($files, fn($f) => empty($f['isMeetPro'])));
    if (!$free) { http_response_code(403); echo json_encode(['error' => 'Those results are MileSplit PRO only, so they cannot be imported. Upload the official results file instead.']); exit; }

    $fields = 'meetName,firstName,lastName,teamName,gender,divisionName,eventCode,eventDistance,mark,place,gradYear,statusCode';
    $raw = [];
    foreach (array_slice($free, 0, 4) as $f) {
        [$json, $code] = xcMilesplitGet($base . '/api/v1/meets/' . $meet_id . '/performances?'
            . http_build_query(['isMeetPro' => 0, 'resultsId' => (int)$f['id'], 'fields' => $fields, 'teamScores' => 'team']));
        $data = $json !== null && $code === 200 ? json_decode($json, true) : null;
        if (!is_array($data['data'] ?? null)) {
            http_response_code(502); echo json_encode(['error' => 'MileSplit did not return the results data' . ($code ? ' (HTTP ' . $code . ').' : '.')]); exit;
        }
        array_push($raw, ...$data['data']);
    }

    // Grade from graduation year: a fall meet is in the school year that ends
    // the following June, so a 2029 graduate racing in September 2026 is a
    // sophomore.
    $year_end = $date ? (int)substr($date, 0, 4) + ((int)substr($date, 5, 2) >= 7 ? 1 : 0) : 0;
    $picked = []; $dropped = 0; $events = [];
    foreach ($raw as $r) {
        if ($want_event    !== '' && strtolower((string)($r['eventCode'] ?? '')) !== $want_event) continue;
        if ($want_division !== '' && strtolower(trim((string)($r['divisionName'] ?? ''))) !== $want_division) continue;
        if ($want_gender   !== '' && ($r['gender'] ?? '') !== $want_gender) continue;
        if (!empty($r['statusCode']) || xcParseTime((string)($r['mark'] ?? '')) === null) { $dropped++; continue; }
        $events[(string)($r['eventCode'] ?? '')] = true;
        $picked[] = $r;
    }
    if (!$picked) {
        http_response_code(404); echo json_encode(['error' => 'No finishers matched that link. Open the race on MileSplit and copy the link again.']); exit;
    }
    if (count($picked) > 3000) { http_response_code(400); echo json_encode(['error' => 'That is more than 3000 runners. Copy the link for one race instead of the whole meet.']); exit; }

    $rows = [];
    foreach ($picked as $r) {
        $sex   = ($r['gender'] ?? '') === 'F' ? 'Girls' : (($r['gender'] ?? '') === 'M' ? 'Boys' : '');
        $race  = trim(trim((string)($r['divisionName'] ?? '')) . ' ' . $sex);
        if (count($events) > 1) $race .= ' ' . $r['eventCode'];
        $grade = ($year_end && (int)($r['gradYear'] ?? 0)) ? 12 - ((int)$r['gradYear'] - $year_end) : 0;
        $rows[] = [
            'race'       => mb_substr($race ?: 'Race', 0, 80),
            'distance_m' => min(20000, max(0, (int)($r['eventDistance'] ?? 0))),
            'place'      => max(0, (int)($r['place'] ?? 0)),
            'name'       => mb_substr(trim(trim((string)($r['firstName'] ?? '')) . ' ' . trim((string)($r['lastName'] ?? ''))), 0, 80),
            'grade'      => ($grade >= 6 && $grade <= 12) ? $grade : 0,
            'school'     => mb_substr(trim((string)($r['teamName'] ?? '')), 0, 120),
            'time'       => trim((string)$r['mark']),
        ];
    }
    $matched = xcMatchRows($pdo, $rows);
    $note = 'Imported ' . count($rows) . ' finishers from MileSplit.';
    if ($dropped) $note .= ' ' . $dropped . ' with no finishing time (DNF, DNS, DQ) were left out.';

    echo json_encode([
        'success'  => true,
        'readable' => true,
        'meet'     => [
            'name'     => trim((string)($picked[0]['meetName'] ?? '')) ?: $title,
            'date'     => $date,
            'location' => $location,
        ],
        'note'     => $note,
        'rows'     => $matched['rows'],
        'warnings' => $matched['warnings'],
    ]);
    exit;
}

// POST ?action=xc_save_meet  {meet_id?, name, date, location, rows:[{race, distance_m, place, name, grade, school, time}]}
// With meet_id, the rows replace that meet's results — the edit path, where a
// renamed runner must not leave their old row behind. Without one, a meet with
// the same name and date is added to rather than duplicated, so a sheet's
// second page can be scanned later; a runner already in it is updated.
if ($method === 'POST' && $action === 'xc_save_meet') {
    xcRequirePin($pdo);
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $name     = trim((string)($body['name'] ?? ''));
    $date     = (string)($body['date'] ?? '');
    $location = trim((string)($body['location'] ?? '')) ?: null;
    $rows     = is_array($body['rows'] ?? null) ? $body['rows'] : [];

    if ($name === '' || mb_strlen($name) > 160) { http_response_code(400); echo json_encode(['error' => 'The meet needs a name.']); exit; }
    $d = DateTime::createFromFormat('!Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) { http_response_code(400); echo json_encode(['error' => 'The meet needs a date.']); exit; }
    if ($location !== null && mb_strlen($location) > 160) $location = mb_substr($location, 0, 160);
    if (count($rows) < 1 || count($rows) > 3000) { http_response_code(400); echo json_encode(['error' => 'A meet needs between 1 and 3000 results.']); exit; }

    $clean = []; $seen = [];
    foreach ($rows as $i => $r) {
        $rn = trim((string)($r['name'] ?? ''));
        $rs = trim((string)($r['school'] ?? ''));
        $label = $rn !== '' ? $rn : 'Row ' . ($i + 1);
        if ($rn === '' || mb_strlen($rn) > 80)  { http_response_code(400); echo json_encode(['error' => $label . ' needs a name.']); exit; }
        if ($rs === '' || mb_strlen($rs) > 120) { http_response_code(400); echo json_encode(['error' => $label . ' needs a school.']); exit; }
        $ms = xcParseTime((string)($r['time'] ?? ''));
        if ($ms === null) { http_response_code(400); echo json_encode(['error' => $label . ' needs a time like 17:23.4.']); exit; }
        $key = xcKey($rn, $rs);
        if (isset($seen[$key])) { http_response_code(400); echo json_encode(['error' => $rn . ' (' . $rs . ') is listed twice.']); exit; }
        $seen[$key] = true;
        $grade = (int)($r['grade'] ?? 0);
        $clean[] = [
            'name'       => $rn,
            'school'     => $rs,
            'key'        => $key,
            'race'       => mb_substr(trim((string)($r['race'] ?? '')) ?: 'Race', 0, 80),
            'distance_m' => min(20000, max(0, (int)($r['distance_m'] ?? 0))) ?: null,
            'place'      => min(5000, max(0, (int)($r['place'] ?? 0))) ?: null,
            'grade'      => ($grade >= 6 && $grade <= 12) ? $grade : null,
            'time_ms'    => $ms,
        ];
    }

    $pdo->beginTransaction();
    try {
        $meet_id = intval($body['meet_id'] ?? 0);
        if ($meet_id) {
            $found = $pdo->prepare("SELECT id FROM xc_meets WHERE id = ?");
            $found->execute([$meet_id]);
            if (!$found->fetch()) { $pdo->rollBack(); http_response_code(404); echo json_encode(['error' => 'No such meet.']); exit; }
            $pdo->prepare("UPDATE xc_meets SET name = ?, meet_date = ?, location = ? WHERE id = ?")
                ->execute([$name, $date, $location, $meet_id]);
            $pdo->prepare("DELETE FROM xc_results WHERE meet_id = ?")->execute([$meet_id]);
        } else {
            $st = $pdo->prepare("SELECT id FROM xc_meets WHERE name = ? AND meet_date = ?");
            $st->execute([$name, $date]);
            $meet_id = (int)($st->fetchColumn() ?: 0);
            if (!$meet_id) {
                $pdo->prepare("INSERT INTO xc_meets (name, meet_date, location) VALUES (?,?,?)")
                    ->execute([$name, $date, $location]);
                $meet_id = (int)$pdo->lastInsertId();
            } elseif ($location !== null) {
                $pdo->prepare("UPDATE xc_meets SET location = ? WHERE id = ?")->execute([$location, $meet_id]);
            }
        }

        // The latest spelling saved wins, so correcting a name once fixes it
        // on every meet that runner appears in.
        $ath = $pdo->prepare("INSERT INTO xc_athletes (name, school, match_key) VALUES (?,?,?)
                              ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), name = VALUES(name), school = VALUES(school)");
        $res = $pdo->prepare("INSERT INTO xc_results (meet_id, athlete_id, race, distance_m, place, grade, time_ms) VALUES (?,?,?,?,?,?,?)
                              ON DUPLICATE KEY UPDATE race = VALUES(race), distance_m = VALUES(distance_m), place = VALUES(place),
                                                      grade = VALUES(grade), time_ms = VALUES(time_ms)");
        foreach ($clean as $c) {
            $ath->execute([$c['name'], $c['school'], $c['key']]);
            $athlete_id = (int)$pdo->lastInsertId();
            $res->execute([$meet_id, $athlete_id, $c['race'], $c['distance_m'], $c['place'], $c['grade'], $c['time_ms']]);
        }
        xcPruneAthletes($pdo);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(500); echo json_encode(['error' => 'Could not save the meet.']); exit;
    }
    echo json_encode(['success' => true, 'meet_id' => $meet_id, 'saved' => count($clean)]);
    exit;
}

// GET ?action=xc_meets — every meet, newest first, with how many runners each
// holds. Also what the app calls to check a PIN when it is first entered.
if ($method === 'GET' && $action === 'xc_meets') {
    xcRequirePin($pdo);
    $st = $pdo->query("SELECT m.id, m.name, m.meet_date, m.location,
                              COUNT(r.id) AS runners, COUNT(DISTINCT r.race) AS races
                       FROM xc_meets m LEFT JOIN xc_results r ON r.meet_id = m.id
                       GROUP BY m.id ORDER BY m.meet_date DESC, m.id DESC");
    echo json_encode(['success' => true, 'meets' => array_map(fn($m) => [
        'id'       => (int)$m['id'],
        'name'     => $m['name'],
        'date'     => $m['meet_date'],
        'location' => $m['location'] ?? '',
        'runners'  => (int)$m['runners'],
        'races'    => (int)$m['races'],
    ], $st->fetchAll())]);
    exit;
}

// GET ?action=xc_schools — every school on record with its runner and result
// counts, most results first. Feeds the team picker on the Team tab.
if ($method === 'GET' && $action === 'xc_schools') {
    xcRequirePin($pdo);
    $st = $pdo->query("SELECT a.school, COUNT(DISTINCT a.id) AS athletes, COUNT(r.id) AS results
                       FROM xc_athletes a JOIN xc_results r ON r.athlete_id = a.id
                       GROUP BY a.school ORDER BY results DESC, a.school");
    echo json_encode(['success' => true, 'schools' => array_map(fn($s) => [
        'school'   => $s['school'],
        'athletes' => (int)$s['athletes'],
        'results'  => (int)$s['results'],
    ], $st->fetchAll())]);
    exit;
}

// GET ?action=xc_results&school=X — every result for one school, flat and in
// date order, for the Team tab to arrange. `school[]=A&school[]=B` (up to 40)
// returns several at once, each row carrying its school — what the
// Conference and Section tabs ask for. Flat rather than pre-arranged: how
// rows are grouped (boys and girls, which distance, top ten per school) is a
// view choice that belongs in the browser.
if ($method === 'GET' && $action === 'xc_results') {
    xcRequirePin($pdo);
    $schools = array_values(array_unique(array_filter(array_map(
        fn($s) => trim((string)$s), (array)($_GET['school'] ?? [])), fn($s) => $s !== '')));
    if (!$schools) { http_response_code(400); echo json_encode(['error' => 'Choose a school.']); exit; }
    if (count($schools) > 40) { http_response_code(400); echo json_encode(['error' => 'Ask for at most 40 schools at once.']); exit; }
    $in = implode(',', array_fill(0, count($schools), '?'));
    $st = $pdo->prepare("SELECT r.athlete_id, a.name, a.school, r.meet_id, m.name AS meet, m.meet_date,
                                r.race, r.distance_m, r.place, r.grade, r.time_ms
                         FROM xc_results r
                         JOIN xc_athletes a ON a.id = r.athlete_id
                         JOIN xc_meets m    ON m.id = r.meet_id
                         WHERE a.school IN ($in)
                         ORDER BY m.meet_date, m.id, r.time_ms");
    $st->execute($schools);
    echo json_encode(['success' => true, 'results' => array_map(fn($r) => [
        'athlete_id' => (int)$r['athlete_id'],
        'name'       => $r['name'],
        'school'     => $r['school'],
        'meet_id'    => (int)$r['meet_id'],
        'meet'       => $r['meet'],
        'date'       => $r['meet_date'],
        'race'       => $r['race'],
        'distance_m' => (int)$r['distance_m'],
        'place'      => (int)$r['place'],
        'grade'      => (int)$r['grade'],
        'time_ms'    => (int)$r['time_ms'],
    ], $st->fetchAll())]);
    exit;
}

// GET ?action=xc_meet&id=X — one meet with every result, in the same row shape
// xc_scan returns, so the app edits a saved meet with the grid it checks a scan in.
if ($method === 'GET' && $action === 'xc_meet') {
    xcRequirePin($pdo);
    $st = $pdo->prepare("SELECT * FROM xc_meets WHERE id = ?");
    $st->execute([intval($_GET['id'] ?? 0)]);
    $meet = $st->fetch();
    if (!$meet) { http_response_code(404); echo json_encode(['error' => 'No such meet.']); exit; }

    $st = $pdo->prepare("SELECT r.*, a.name, a.school FROM xc_results r JOIN xc_athletes a ON a.id = r.athlete_id
                         WHERE r.meet_id = ? ORDER BY r.race, r.place IS NULL, r.place, r.time_ms");
    $st->execute([$meet['id']]);
    echo json_encode([
        'success' => true,
        'meet'    => ['id' => (int)$meet['id'], 'name' => $meet['name'], 'date' => $meet['meet_date'], 'location' => $meet['location'] ?? ''],
        'rows'    => array_map(fn($r) => [
            'race'       => $r['race'],
            'distance_m' => (int)$r['distance_m'],
            'place'      => (int)$r['place'],
            'name'       => $r['name'],
            'grade'      => (int)$r['grade'],
            'school'     => $r['school'],
            'time'       => xcFormatTime((int)$r['time_ms']),
            'status'     => 'existing',
            'similar'    => null,
        ], $st->fetchAll()),
    ]);
    exit;
}

// DELETE ?action=xc_meet&id=X — the meet and its results; runners left with no
// results anywhere go with it.
if ($method === 'DELETE' && $action === 'xc_meet') {
    xcRequirePin($pdo);
    $st = $pdo->prepare("DELETE FROM xc_meets WHERE id = ?");
    $st->execute([intval($_GET['id'] ?? 0)]);
    if ($st->rowCount() === 0) { http_response_code(404); echo json_encode(['error' => 'No such meet.']); exit; }
    xcPruneAthletes($pdo);
    echo json_encode(['success' => true]);
    exit;
}

// =============================================================
http_response_code(404);
echo json_encode(['error' => 'Unknown action.']);
