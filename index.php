<?php
require __DIR__ . '/includes/bootstrap.php';

if (current_user_id()) {
    redirect('dashboard.php');
}

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
<div class="layout-centered">
    <div class="card card-login">
        <h1 class="title">Anketor Giris</h1>
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo h($flash['type']); ?>"><?php echo h($flash['message']); ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <input type="hidden" name="_token" value="<?php echo csrf_token(); ?>">
            <div class="form-group">
                <label for="email">E-posta</label>
                <input id="email" type="email" name="email" required value="<?php echo old('email'); ?>">
            </div>
            <div class="form-group">
                <label for="password">Sifre</label>
                <input id="password" type="password" name="password" required>
            </div>
            <button type="submit" class="button-primary">Giris Yap</button>
        </form>
    </div>
</div>
<?php include __DIR__ . '/templates/footer.php'; ?>
