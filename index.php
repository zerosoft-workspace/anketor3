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
        <section class="auth-visual" aria-labelledby="auth-showcase-title">
            <div class="auth-visual__content">
                <span class="auth-badge">Anket Yönetim Merkezi</span>
                <div class="auth-heading">
                    <h1 id="auth-showcase-title">Verilerinizi tek panelden yönetin.</h1>
                    <p>Etkinliklere ait fotoğraf, video ve anket içgörülerini merkezi bir deneyimde buluşturun. Yapay zekâ destekli analizlerle saniyeler içinde aksiyona geçin.</p>
                </div>
                <ul class="auth-benefits" aria-label="Platform avantajları">
                    <li>Seçtiğiniz yapay zekâ sağlayıcısıyla uyumlu raporlar oluşturun.</li>
                    <li>Paylaşılabilir linklerle takımınızla aynı ekranda buluşun.</li>
                    <li>Katılımcı deneyimini izleyip anında geribildirim alın.</li>
                </ul>
            </div>
            <div class="auth-support">
                <span>Kurulum desteği mi lazım?</span>
                <a href="mailto:support@anketor.com">support@anketor.com</a>
            </div>
        </section>
        <aside class="auth-card" aria-labelledby="auth-title">
            <div class="auth-card-header">
                <h2 id="auth-title">Panele Giriş Yapın</h2>
                <p>Yönetim hesabınızla oturum açarak süper admin araçlarına erişin.</p>
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
                <p>Güvenlik için paylaşılan cihazlarda oturumu kapatmayı unutmayın.</p>
            </div>
        </aside>
    </div>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
