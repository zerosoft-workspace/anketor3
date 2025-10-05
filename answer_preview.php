<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$surveyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
redirect('answer.php?id=' . $surveyId . '&preview=1');
