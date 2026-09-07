<?php

declare(strict_types=1);

/**
 * One-time database installer.
 *
 * Deploy flow:
 *   1. Upload config/config.php (real DB credentials + an `install_token`) and this whole
 *      database/ folder to the server.
 *   2. curl "https://sofiago.eu/database/install.php?token=YOUR_INSTALL_TOKEN"
 *   3. Delete database/install.php from the server (or at least the token from config.php)
 *      once you see "Schema installed successfully." — the script also self-locks via
 *      database/.installed so a repeat request/curl retry can't run it twice.
 */

header('Content-Type: text/plain; charset=utf-8');

$root = dirname(__DIR__);
$lockFile = __DIR__ . '/.installed';
$configFile = $root . '/config/config.php';

if (is_file($lockFile)) {
    http_response_code(403);
    exit("Already installed on " . trim((string) file_get_contents($lockFile)) . "\n"
        . "Delete database/.installed manually if you really need to re-run this.\n");
}

if (!is_file($configFile)) {
    http_response_code(500);
    exit("Missing config/config.php. Copy config/config.example.php, fill in real values, upload it, then retry.\n");
}

$config = require $configFile;

$expectedToken = $config['install_token'] ?? null;
$givenToken = $_GET['token'] ?? ($_SERVER['HTTP_X_INSTALL_TOKEN'] ?? '');

if (!is_string($expectedToken) || $expectedToken === '' || !hash_equals($expectedToken, (string) $givenToken)) {
    http_response_code(403);
    exit("Invalid or missing install token.\n");
}

$db = $config['db'] ?? null;
if (!is_array($db)) {
    http_response_code(500);
    exit("config.php has no 'db' section.\n");
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'] ?? 3306, $db['name']),
        $db['user'],
        $db['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Throwable $e) {
    http_response_code(500);
    exit('DB connection failed: ' . $e->getMessage() . "\n");
}

$sqlFile = __DIR__ . '/schema.sql';
if (!is_file($sqlFile)) {
    http_response_code(500);
    exit("schema.sql not found next to install.php.\n");
}

$sql = (string) file_get_contents($sqlFile);

// Strip full-line "-- ..." comments *before* splitting into statements. Without this, a
// comment sitting right above a statement (no blank semicolon between them) gets glued to
// that statement into one chunk, and a naive "skip chunks starting with --" check throws the
// whole chunk away — silently dropping the real SQL after the comment.
$sql = (string) preg_replace('/^--.*$/m', '', $sql);

// Statements are one-per-line-ending-in-";" — split on ";" followed by a newline. None of our
// DDL/seed values contain a literal ";\n" inside a string.
$statements = preg_split('/;\s*\r?\n/', $sql) ?: [];

// No PDO transaction here on purpose: MySQL/MariaDB implicitly commits on every DDL statement
// (CREATE TABLE, etc.), so wrapping this loop in beginTransaction()/rollBack() can't actually
// undo anything once a CREATE TABLE has run — it only throws a confusing second error
// ("There is no active transaction") on top of whatever really failed. Schema.sql uses
// `CREATE TABLE IF NOT EXISTS` and `ON DUPLICATE KEY UPDATE`, so re-running after a partial
// failure is safe: fix the reported statement and hit the install URL again.
foreach ($statements as $index => $statement) {
    $statement = trim($statement);
    if ($statement === '' || str_starts_with($statement, '--')) {
        continue;
    }

    try {
        $pdo->exec($statement);
    } catch (Throwable $e) {
        http_response_code(500);
        exit(sprintf(
            "Migration failed on statement #%d: %s\n\n%s\n",
            $index + 1,
            $e->getMessage(),
            $statement
        ));
    }
}

echo "Schema installed successfully.\n";

// Optional: create/repromote the first admin from config, so there is a way to log into
// the moderation area without hand-editing the database.
$bootstrapAdmin = $config['bootstrap_admin'] ?? null;
if (is_array($bootstrapAdmin) && !empty($bootstrapAdmin['email']) && !empty($bootstrapAdmin['password'])) {
    $stmt = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, role, status, email_verified_at, created_at, updated_at)
         VALUES (:name, :email, :hash, "admin", "active", NOW(), NOW(), NOW())
         ON DUPLICATE KEY UPDATE role = "admin", status = "active", password_hash = VALUES(password_hash)'
    );
    $stmt->execute([
        'name' => $bootstrapAdmin['name'] ?? 'Admin',
        'email' => mb_strtolower(trim((string) $bootstrapAdmin['email'])),
        'hash' => password_hash((string) $bootstrapAdmin['password'], PASSWORD_BCRYPT),
    ]);
    echo 'Admin user ready: ' . $bootstrapAdmin['email'] . "\n";
}

file_put_contents($lockFile, date('c') . ' installed from ' . ($_SERVER['REMOTE_ADDR'] ?? 'cli') . "\n");

echo "Done. Please delete database/install.php from the server now (or rotate install_token).\n";
