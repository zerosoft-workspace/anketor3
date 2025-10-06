<?php
$flash = $flash ?? get_flash();
$pageTitle = $pageTitle ?? config('app.name', 'Anketor');
?><!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Anketor ile modern anketler oluşturun, katılımcı yanıtlarını analiz edin ve kişisel raporlar üretin.">
    <meta name="color-scheme" content="light">
    <title><?php echo h($pageTitle); ?></title>
    <link rel="stylesheet" href="<?php echo asset_url('css/style.css'); ?>">
</head>
<body class="<?php echo h($bodyClass ?? 'app-body'); ?>">
