<?php
$config = require __DIR__ . '/config.php';

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

function html($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$dbConfig = $config['db'] ?? null;
if (!$dbConfig) {
    http_response_code(500);
    exit('Veritabani konfigurasyonu eksik.');
}

$logs = [];
$errors = [];
$databaseName = $dbConfig['database'] ?? '';
$charset = $dbConfig['charset'] ?? 'utf8mb4';
$collation = $dbConfig['collation'] ?? ($charset . '_unicode_ci');

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

$needsInstall = false;
$hasTables = false;

try {
    $dsnWithDb = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $dbConfig['host'] ?? 'localhost',
        $dbConfig['port'] ?? 3306,
        $databaseName,
        $charset
    );
    $pdo = new PDO($dsnWithDb, $dbConfig['username'] ?? '', $dbConfig['password'] ?? '', $options);
    $logs[] = 'Veritabanina baglanti saglandi.';

    $tableCheck = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
    if ($tableCheck) {
        $hasTables = true;
        $logs[] = 'Gerekli tablolar zaten mevcut.';
    } else {
        $needsInstall = true;
        $logs[] = 'Tablolar bulunamadi, kuruluma devam edilecek.';
    }
} catch (PDOException $e) {
    $errorCode = $e->errorInfo[1] ?? null;
    if ($errorCode === 1049) { // Unknown database
        $needsInstall = true;
        $logs[] = 'Veritabani bulunamadi. Kurulum baslatiliyor...';
    } else {
        $errors[] = 'Baglanti hatasi: ' . $e->getMessage();
    }
}

if ($needsInstall && empty($errors)) {
    try {
        $dsnWithoutDb = sprintf(
            'mysql:host=%s;port=%d;charset=%s',
            $dbConfig['host'] ?? 'localhost',
            $dbConfig['port'] ?? 3306,
            $charset
        );
        $pdo = new PDO($dsnWithoutDb, $dbConfig['username'] ?? '', $dbConfig['password'] ?? '', $options);

        $logs[] = 'Sunucuya baglanildi. Veritabani olusturuluyor...';
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $databaseName) . '` DEFAULT CHARACTER SET ' . $charset . ' COLLATE ' . $collation);
        $pdo->exec('USE `' . str_replace('`', '``', $databaseName) . '`');

        $sqlFile = __DIR__ . '/database.sql';
        if (!is_file($sqlFile)) {
            throw new RuntimeException('database.sql bulunamadi.');
        }

        $sqlContent = file_get_contents($sqlFile) ?: '';
        $statements = array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sqlContent)));

        foreach ($statements as $statement) {
            if ($statement === '' || str_starts_with($statement, '--')) {
                continue;
            }
            $normalized = ltrim(strtolower($statement));
            if (str_starts_with($normalized, 'create database') || str_starts_with($normalized, 'use ')) {
                $logs[] = 'Kurulum: CREATE DATABASE/USE komutlari atlandi.';
                continue;
            }
            $pdo->exec($statement);
        }

        $logs[] = 'Veritabani tablolar ve varsayilan verilerle kuruldu.';
        $hasTables = true;
    } catch (Throwable $e) {
        $errors[] = 'Kurulum hatasi: ' . $e->getMessage();
    }
}

$status = $hasTables && empty($errors) ? 'Kurulum tamamlandi.' : 'Kurulum tamamlanamadi.';
header('Content-Type: text/html; charset=UTF-8');

?><!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Kurulum - Anketor</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background: #eff2ff; }
        .install-wrapper { max-width: 720px; margin: 4rem auto; padding: 0 1.5rem; }
        .log-list { list-style: none; padding: 0; margin: 1.5rem 0; display: grid; gap: 0.6rem; }
        .log-list li { background: rgba(255,255,255,0.9); padding: 0.9rem 1.1rem; border-radius: 10px; box-shadow: 0 6px 18px rgba(60,109,240,0.1); }
        .status-success { color: #0f9d58; }
        .status-error { color: #d93025; }
        .actions { margin-top: 2rem; display: flex; gap: 1rem; flex-wrap: wrap; }
    </style>
</head>
<body>
<div class="install-wrapper">
    <section class="card">
        <h1>Anketor Kurulum</h1>
        <p>Hedef veritabani: <strong><?= html($databaseName) ?></strong></p>
        <p class="<?= empty($errors) ? 'status-success' : 'status-error' ?>"><?= html($status) ?></p>

        <?php if ($logs): ?>
            <h2>Islem Kaydi</h2>
            <ul class="log-list">
                <?php foreach ($logs as $log): ?>
                    <li><?= html($log) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <p><?= html($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="actions">
            <a class="button-primary" href="dashboard.php">Panele Don</a>
            <a class="button-secondary" href="install.php">Yeniden Dene</a>
        </div>
    </section>
</div>
</body>
</html>


