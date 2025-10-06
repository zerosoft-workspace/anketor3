<?php
return [
    'db' => [
        'host' => 'srv1776.hstgr.io',
        'port' => 3306,
        'database' => 'u111878875_anketor3',
        'username' => 'u111878875_anketor3',
        'password' => 'Anketor3.',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name' => 'Anketor',
        'base_url' => '',
        'timezone' => 'Europe/Istanbul',
    ],
    'mail' => [
        'from_email' => 'iletisim@kurumadi.com',
        'from_name' => 'Kurumsal Iletisim Ekibi',
    ],
    'security' => [
        'token_salt' => 'please-change-this-secret',
    ],
    'ai' => [
        'provider' => 'openai',
        'api_key' => '',
        'model' => 'gpt-4o-mini',
        'base_url' => 'https://api.openai.com/v1',
        'deployment' => '',
        'azure_api_version' => '2024-02-15-preview',
    ],
    'openai' => [
        'api_key' => '',
        'model' => 'gpt-4o-mini',
    ],
];

