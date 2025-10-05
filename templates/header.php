<?php
$flash = $flash ?? get_flash();
?><!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h(config('app.name', 'Anketor')); ?></title>
    <link rel="stylesheet" href="<?php echo asset_url('css/style.css'); ?>">
</head>
<body>
