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

function ensureSchemaUpToDate(PDO $pdo, array &$logs, array &$errors): void
{
    try {
        $tableExists = $pdo->query("SHOW TABLES LIKE 'survey_questions'")->fetch();
        if (!$tableExists) {
            return;
        }

        $columnExists = $pdo->query("SHOW COLUMNS FROM survey_questions LIKE 'category_key'")->fetch();
        if (!$columnExists) {
            $pdo->exec("ALTER TABLE survey_questions ADD COLUMN category_key VARCHAR(100) NULL DEFAULT NULL AFTER question_type");
            $logs[] = 'survey_questions tablosuna category_key alani eklendi.';
        } else {
            $logs[] = 'survey_questions tablosundaki category_key alani guncel.';
        }

        $libraryExists = $pdo->query("SHOW TABLES LIKE 'question_library'")->fetch();
        if (!$libraryExists) {
            $pdo->exec(<<<SQL
CREATE TABLE question_library (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_text TEXT NOT NULL,
    question_type ENUM('multiple_choice','rating','text') NOT NULL,
    category_key VARCHAR(120) NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    max_length INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL);
            $logs[] = 'question_library tablosu olusturuldu.';
        } else {
            $logs[] = 'question_library tablosu mevcut.';
        }

        $libraryOptionsExists = $pdo->query("SHOW TABLES LIKE 'question_library_options'")->fetch();
        if (!$libraryOptionsExists) {
            $pdo->exec(<<<SQL
CREATE TABLE question_library_options (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    library_question_id INT UNSIGNED NOT NULL,
    option_text VARCHAR(255) NOT NULL,
    option_value VARCHAR(100) NULL,
    order_index INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_library_options_question FOREIGN KEY (library_question_id) REFERENCES question_library(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL);
            $logs[] = 'question_library_options tablosu olusturuldu.';
        } else {
            $logs[] = 'question_library_options tablosu mevcut.';
        }

        if (!$libraryExists) {
            $questions = $pdo->query('SELECT * FROM survey_questions ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
            if ($questions) {
                $insertQuestion = $pdo->prepare(
                    'INSERT INTO question_library (question_text, question_type, category_key, is_required, max_length, created_at)
                     VALUES (?, ?, ?, ?, ?, NOW())'
                );
                $insertOption = $pdo->prepare(
                    'INSERT INTO question_library_options (library_question_id, option_text, option_value, order_index)
                     VALUES (?, ?, ?, ?)'
                );
                $selectOptions = $pdo->prepare('SELECT * FROM question_options WHERE question_id = ? ORDER BY order_index ASC, id ASC');

                foreach ($questions as $question) {
                    $insertQuestion->execute([
                        $question['question_text'],
                        $question['question_type'],
                        $question['category_key'] ?? null,
                        $question['is_required'] ?? 0,
                        $question['max_length'] ?? null,
                    ]);
                    $libraryId = (int)$pdo->lastInsertId();

                    if ($question['question_type'] === 'multiple_choice') {
                        $selectOptions->execute([$question['id']]);
                        $order = 0;
                        foreach ($selectOptions->fetchAll(PDO::FETCH_ASSOC) as $option) {
                            $insertOption->execute([
                                $libraryId,
                                $option['option_text'],
                                $option['option_value'] ?? null,
                                $order++,
                            ]);
                        }
                        $selectOptions->closeCursor();
                    }
                }

                $logs[] = 'Mevcut sorular soru havuzuna kopyalandi.';
            }
        }

        $responseAnswersExists = $pdo->query("SHOW TABLES LIKE 'response_answers'")->fetch();
        if (!$responseAnswersExists) {
            $pdo->exec(<<<SQL
CREATE TABLE response_answers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    response_id INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    option_id INT UNSIGNED NULL,
    type ENUM('multiple_choice','rating','text') NULL,
    answer_text TEXT NULL,
    numeric_value DECIMAL(10,4) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_answers_response FOREIGN KEY (response_id) REFERENCES survey_responses(id) ON DELETE CASCADE,
    CONSTRAINT fk_answers_question FOREIGN KEY (question_id) REFERENCES survey_questions(id) ON DELETE CASCADE,
    CONSTRAINT fk_answers_option FOREIGN KEY (option_id) REFERENCES question_options(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL);
            $logs[] = 'response_answers tablosu olusturuldu.';
        } else {
            $typeColumn = $pdo->query("SHOW COLUMNS FROM response_answers LIKE 'type'")->fetch();
            if (!$typeColumn) {
                $pdo->exec("ALTER TABLE response_answers ADD COLUMN type ENUM('multiple_choice','rating','text') NULL DEFAULT NULL AFTER option_id");
                $logs[] = 'response_answers tablosuna type alani eklendi.';
            } else {
                $logs[] = 'response_answers tablosundaki type alani guncel.';
            }
        }
    } catch (Throwable $e) {
        $errors[] = 'Sema guncelleme hatasi: ' . $e->getMessage();
    }
}

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

$needsInstall = false;
$hasTables = false;
$pdo = null;

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

if ($hasTables && empty($errors)) {
    try {
        if (!$pdo) {
            $dsnWithDb = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $dbConfig['host'] ?? 'localhost',
                $dbConfig['port'] ?? 3306,
                $databaseName,
                $charset
            );
            $pdo = new PDO($dsnWithDb, $dbConfig['username'] ?? '', $dbConfig['password'] ?? '', $options);
        } else {
            $pdo->exec('USE `' . str_replace('`', '``', $databaseName) . '`');
        }
    } catch (Throwable $e) {
        $errors[] = 'Kurulum sonrasi baglanti hatasi: ' . $e->getMessage();
    }

    if ($pdo && empty($errors)) {
        ensureSchemaUpToDate($pdo, $logs, $errors);
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


