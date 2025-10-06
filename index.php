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
        <section class="auth-showcase" aria-labelledby="auth-showcase-title">
            <div class="auth-showcase__header">
                <span class="auth-eyebrow">Anket Yönetim Portalı</span>
                <h1 id="auth-showcase-title">Verilerinizi sezgisel bir yönetim deneyimine taşıyın.</h1>
                <p>Anketlerinizi tasarlayın, paylaşıma hazır raporlar üretin ve yapay zekâ destekli analizlerle karar süreçlerinizi hızlandırın.</p>
            </div>
            <div class="auth-showcase__cards" role="presentation">
                <article class="auth-insight">
                    <span class="auth-insight__value">3×</span>
                    <h3 class="auth-insight__title">Daha hızlı raporlama</h3>
                    <p class="auth-insight__text">Yapay zekâ destekli özetlerle karar alma sürenizi kısaltın.</p>
                </article>
                <article class="auth-insight">
                    <span class="auth-insight__value">%99</span>
                    <h3 class="auth-insight__title">Veri güvenliği</h3>
                    <p class="auth-insight__text">Rol tabanlı erişim ile hassas verileriniz güvende kalsın.</p>
                </article>
                <article class="auth-insight">
                    <span class="auth-insight__value">24/7</span>
                    <h3 class="auth-insight__title">Canlı takip</h3>
                    <p class="auth-insight__text">Katılımcı yanıtlarını gerçek zamanlı olarak izleyin.</p>
                </article>
            </div>
            <ul class="auth-feature-chips" aria-label="Temel özellikler">
                <li>Çok sağlayıcılı yapay zekâ entegrasyonu</li>
                <li>Paylaşılabilir rapor şablonları</li>
                <li>Takım bazlı çalışma alanları</li>
            </ul>
        </section>
        <aside class="auth-sidebar" aria-labelledby="auth-title">
            <div class="auth-card">
                <div class="auth-card-header">
                    <h2 id="auth-title">Hesabınıza Giriş Yapın</h2>
                    <p>Yönetim paneline erişmek için kurumsal bilgilerinizi doğrulayın.</p>
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
                <div class="auth-card-footer">
                    <p>Destek mi gerekiyor? <a href="mailto:support@anketor.com">Destek ekibiyle iletişime geçin</a>.</p>
                </div>
            </div>
        </aside>
    </div>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
