<?php
require __DIR__ . '/includes/bootstrap.php';

if (current_user_id()) {
    redirect('dashboard.php');
}

$pageTitle = 'Yönetim Paneline Giriş';
$bodyClass = 'app-body auth-body';
$showFooter = false;

$message = '';

if (is_post()) {
    guard_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    save_old(['email' => $email]);

    if (attempt_login($db, $email, $password)) {
        clear_old();
        redirect('dashboard.php');
    } else {
        $message = 'E-posta veya sifre hatali.';
    }
}

$flash = $message ? ['type' => 'danger', 'message' => $message] : get_flash();
include __DIR__ . '/templates/header.php';
?>
<main class="auth-wrapper">
    <div class="auth-panel">
        <section class="auth-hero">
            <span class="auth-badge">Yönetim Portalı</span>
            <h1>Verilerinizi güçlü anket deneyimine taşıyın.</h1>
            <p>Katılımcı yanıtlarını tek bir merkezden toplayın, yapay zekâ destekli öngörülerle raporlayın ve ekip arkadaşlarınıza kolayca paylaşın.</p>
            <ul class="auth-features">
                <li>Gerçek zamanlı raporlar ve paylaşıma hazır içgörüler</li>
                <li>Yapay zekâ sağlayıcılarını tek panelden yönetin</li>
                <li>Takımınızla güvenli ve hızlı işbirliği yapın</li>
            </ul>
        </section>
        <section class="auth-card" aria-labelledby="auth-title">
            <div class="auth-card-header">
                <h2 id="auth-title">Hesabınıza Giriş Yapın</h2>
                <p>Anket yönetim merkezine erişmek için bilgilerinizi girin.</p>
            </div>
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo h($flash['type']); ?>"><?php echo h($flash['message']); ?></div>
            <?php endif; ?>
            <form method="POST" action="" class="auth-form">
                <input type="hidden" name="_token" value="<?php echo csrf_token(); ?>">
                <div class="form-field">
                    <label for="email">E-posta adresi</label>
                    <input id="email" type="email" name="email" placeholder="ornek@anketor.com" required value="<?php echo old('email'); ?>">
                </div>
                <div class="form-field">
                    <div class="field-label">
                        <label for="password">Şifre</label>
                        <a class="field-action" href="#" aria-disabled="true">Şifremi unuttum</a>
                    </div>
                    <input id="password" type="password" name="password" placeholder="••••••••" required>
                </div>
                <button type="submit" class="button-primary button-block">Panele Giriş Yap</button>
            </form>
        </section>
    </div>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
