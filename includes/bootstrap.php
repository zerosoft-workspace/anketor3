<?php
$config = require __DIR__ . '/../config.php';
$GLOBALS['config'] = $config;

if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
}

date_default_timezone_set($config['app']['timezone'] ?? 'UTC');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/analytics.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/ai_client.php';
require_once __DIR__ . '/services/SurveyService.php';

$db = Database::getInstance($config['db']);
$surveyService = new SurveyService($db, $config);
