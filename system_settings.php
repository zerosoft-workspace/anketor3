<?php
require __DIR__ . '/includes/bootstrap.php';
require_super_admin();

/** @var SettingsService $settingsService */
$settingsService = $GLOBALS['settingsService'] ?? new SettingsService($db);

$aiProviders = [
    'openai' => 'OpenAI',
    'azure_openai' => 'Azure OpenAI',
    'google_gemini' => 'Google Gemini',
    'mock' => 'Test / Yerel Motor',
];

$timezoneOptions = [
    'Europe/Istanbul' => 'Europe/Istanbul',
    'UTC' => 'UTC',
    'Europe/Berlin' => 'Europe/Berlin',
    'Asia/Dubai' => 'Asia/Dubai',
    'America/New_York' => 'America/New_York',
];

$current = [
    'app_name' => config('app.name', 'Anketor'),
    'app_timezone' => config('app.timezone', 'Europe/Istanbul'),
    'mail_from_name' => config('mail.from_name', ''),
    'mail_from_email' => config('mail.from_email', ''),
    'ai_provider' => config('ai.provider', config('openai.provider', 'openai')),
    'ai_model' => config('ai.model', 'gpt-4o-mini'),
    'ai_api_key' => config('ai.api_key', ''),
    'ai_base_url' => config('ai.base_url', 'https://api.openai.com/v1'),
    'ai_deployment' => config('ai.deployment', ''),
    'ai_azure_api_version' => config('ai.azure_api_version', '2024-02-15-preview'),
];

$errors = [];

if (is_post()) {
    guard_csrf();

    $appName = trim($_POST['app_name'] ?? '');
    $timezone = trim($_POST['app_timezone'] ?? 'UTC');
    $fromName = trim($_POST['mail_from_name'] ?? '');
    $fromEmail = trim($_POST['mail_from_email'] ?? '');
    $provider = $_POST['ai_provider'] ?? 'openai';
    $model = trim($_POST['ai_model'] ?? '');
    $apiKey = trim($_POST['ai_api_key'] ?? '');
    $baseUrl = trim($_POST['ai_base_url'] ?? '');
    $deployment = trim($_POST['ai_deployment'] ?? '');
    $apiVersion = trim($_POST['ai_azure_api_version'] ?? '');

    if ($appName === '') {
        $errors[] = 'Platform adı boş olamaz.';
    }

    if ($fromEmail !== '' && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Geçerli bir destek e-posta adresi girin.';
    }

    if (!array_key_exists($provider, $aiProviders)) {
        $provider = 'openai';
    }

    if (!array_key_exists($timezone, $timezoneOptions)) {
        $timezone = 'UTC';
    }

    if (empty($model)) {
        $errors[] = 'Bir yapay zeka modeli seçin.';
    }

    if (empty($errors)) {
        $settingsService->setMany([
            'app.name' => $appName,
            'app.timezone' => $timezone,
            'mail.from_name' => $fromName,
            'mail.from_email' => $fromEmail,
            'ai.provider' => $provider,
            'ai.model' => $model,
            'ai.api_key' => $apiKey,
            'ai.base_url' => $baseUrl,
            'ai.deployment' => $deployment,
            'ai.azure_api_version' => $apiVersion,
        ]);

        $settingsService->reload();
        $merged = array_replace_recursive($config, $settingsService->asConfig());
        $GLOBALS['config'] = $config = $merged;
        date_default_timezone_set($config['app']['timezone'] ?? 'UTC');

        set_flash('success', 'Sistem ayarları güncellendi.');
        redirect('system_settings.php');
    } else {
        $current = array_merge($current, [
            'app_name' => $appName,
            'app_timezone' => $timezone,
            'mail_from_name' => $fromName,
            'mail_from_email' => $fromEmail,
            'ai_provider' => $provider,
            'ai_model' => $model,
            'ai_api_key' => $apiKey,
            'ai_base_url' => $baseUrl,
            'ai_deployment' => $deployment,
            'ai_azure_api_version' => $apiVersion,
        ]);
    }
}

$pageTitle = 'Sistem Ayarları - ' . config('app.name', 'Anketor');
$flash = get_flash();

include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';
?>
<main class="container">
    <header class="page-header">
        <div>
            <p class="eyebrow">Yönetim</p>
            <h1>Sistem Ayarları</h1>
            <p class="page-subtitle">Platform kimliğini, bildirim gönderen adresleri ve yapay zeka motorunu tek bir yerden yönetin.</p>
        </div>
    </header>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo h($flash['type']); ?>"><?php echo h($flash['message']); ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <div><?php echo h($error); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="settings-grid">
        <input type="hidden" name="_token" value="<?php echo csrf_token(); ?>">

        <section class="panel">
            <div class="panel-header">
                <h2>Genel Bilgiler</h2>
            </div>
            <div class="panel-body form-grid">
                <div class="form-group">
                    <label for="app_name">Platform Adı</label>
                    <input type="text" id="app_name" name="app_name" required value="<?php echo h($current['app_name']); ?>">
                </div>
                <div class="form-group">
                    <label for="app_timezone">Saat Dilimi</label>
                    <select id="app_timezone" name="app_timezone">
                        <?php foreach ($timezoneOptions as $key => $label): ?>
                            <option value="<?php echo h($key); ?>" <?php echo $current['app_timezone'] === $key ? 'selected' : ''; ?>><?php echo h($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="mail_from_name">Bildirim Gönderen Adı</label>
                    <input type="text" id="mail_from_name" name="mail_from_name" value="<?php echo h($current['mail_from_name']); ?>">
                </div>
                <div class="form-group">
                    <label for="mail_from_email">Bildirim Gönderen E-posta</label>
                    <input type="email" id="mail_from_email" name="mail_from_email" value="<?php echo h($current['mail_from_email']); ?>">
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2>Yapay Zeka Motoru</h2>
                <p class="panel-subtitle">Analiz ve raporlar için kullanılacak sağlayıcı ve erişim bilgilerini seçin.</p>
            </div>
            <div class="panel-body form-grid">
                <div class="form-group">
                    <label for="ai_provider">Sağlayıcı</label>
                    <select id="ai_provider" name="ai_provider">
                        <?php foreach ($aiProviders as $key => $label): ?>
                            <option value="<?php echo h($key); ?>" <?php echo $current['ai_provider'] === $key ? 'selected' : ''; ?>><?php echo h($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="ai_model">Model / Deployment</label>
                    <input type="text" id="ai_model" name="ai_model" required value="<?php echo h($current['ai_model']); ?>" placeholder="gpt-4o-mini veya benzeri">
                </div>
                <div class="form-group">
                    <label for="ai_api_key">API Anahtarı</label>
                    <input type="text" id="ai_api_key" name="ai_api_key" value="<?php echo h($current['ai_api_key']); ?>" placeholder="Gizli anahtarı buraya girin">
                </div>
                <div class="form-group">
                    <label for="ai_base_url">Temel API URL</label>
                    <input type="text" id="ai_base_url" name="ai_base_url" value="<?php echo h($current['ai_base_url']); ?>" placeholder="https://api.openai.com/v1">
                </div>
                <div class="form-group">
                    <label for="ai_deployment">Azure Deployment Adı (Opsiyonel)</label>
                    <input type="text" id="ai_deployment" name="ai_deployment" value="<?php echo h($current['ai_deployment']); ?>" placeholder="Azure için deployment adı">
                </div>
                <div class="form-group">
                    <label for="ai_azure_api_version">Azure API Versiyonu (Opsiyonel)</label>
                    <input type="text" id="ai_azure_api_version" name="ai_azure_api_version" value="<?php echo h($current['ai_azure_api_version']); ?>" placeholder="2024-02-15-preview">
                </div>
            </div>
        </section>

        <div class="form-actions form-actions--sticky">
            <button type="submit" class="button-primary">Ayarları Kaydet</button>
        </div>
    </form>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
