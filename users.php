<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$pageTitle = 'Kullanıcılar - ' . config('app.name', 'Anketor');
$errors = [];
$roleLabels = [
    'super_admin' => 'Süper Yönetici',
    'admin' => 'Yönetici',
    'analyst' => 'Analist',
];

if (is_post()) {
    guard_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $availableRoles = is_super_admin() ? ['super_admin', 'admin', 'analyst'] : ['admin', 'analyst'];
    $role = $_POST['role'] ?? 'admin';
    if (!in_array($role, $availableRoles, true)) {
        $role = 'admin';
    }
    if (!is_super_admin()) {
        $role = $role === 'analyst' ? 'analyst' : 'admin';
    }

    if ($name === '') {
        $errors[] = 'İsim alanı boş olamaz.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Geçerli bir e-posta adresi girin.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Parola en az 8 karakter olmalı.';
    }

    if (empty($errors)) {
        $exists = $db->fetch('SELECT id FROM users WHERE email = ?', [$email]);
        if ($exists) {
            $errors[] = 'Bu e-posta için zaten kayıt var.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $db->insert(
                'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)',
                [$name, $email, $hash, $role]
            );
            set_flash('success', 'Yeni kullanıcı eklendi.');
            redirect('users.php');
        }
    }
}

$users = $db->fetchAll('SELECT id, name, email, role, last_login_at, created_at FROM users ORDER BY created_at DESC');
$flash = get_flash();
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';
?>
<main class="container">
    <header class="page-header">
        <div>
            <p class="eyebrow">Takım</p>
            <h1>Kayıtlı Kullanıcılar</h1>
            <p class="page-subtitle">Yönetim paneline erişimi olan ekip üyelerini buradan yönetin.</p>
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

    <section class="panel">
        <div class="panel-header">
            <h2>Yeni Kullanıcı</h2>
        </div>
        <div class="panel-body">
            <form method="POST" class="form-grid">
                <input type="hidden" name="_token" value="<?php echo csrf_token(); ?>">
                <div class="form-group">
                    <label for="name">İsim Soyisim</label>
                    <input type="text" id="name" name="name" required value="<?php echo h($_POST['name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="email">E-posta</label>
                    <input type="email" id="email" name="email" required value="<?php echo h($_POST['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="password">Parola</label>
                    <input type="password" id="password" name="password" required placeholder="En az 8 karakter">
                </div>
                <?php if (is_super_admin()): ?>
                    <div class="form-group">
                        <label for="role">Rol</label>
                        <select id="role" name="role">
                            <?php foreach (['super_admin', 'admin', 'analyst'] as $roleOption): ?>
                                <option value="<?php echo h($roleOption); ?>" <?php echo (($_POST['role'] ?? 'admin') === $roleOption) ? 'selected' : ''; ?>><?php echo h($roleLabels[$roleOption]); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="role" value="<?php echo h($_POST['role'] ?? 'admin'); ?>">
                <?php endif; ?>
                <div class="form-actions">
                    <button type="submit" class="button-primary">Kullanıcı Ekle</button>
                </div>
            </form>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <h2>Mevcut Kullanıcılar</h2>
        </div>
        <div class="panel-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>İsim</th>
                        <th>E-posta</th>
                        <th>Rol</th>
                        <th>Son Giriş</th>
                        <th>Kayıt Tarihi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo (int)$user['id']; ?></td>
                            <td><?php echo h($user['name']); ?></td>
                            <td><?php echo h($user['email']); ?></td>
                            <td><?php echo h($roleLabels[$user['role']] ?? strtoupper($user['role'])); ?></td>
                            <td><?php echo $user['last_login_at'] ? h(format_date($user['last_login_at'], 'd.m.Y H:i')) : '-'; ?></td>
                            <td><?php echo $user['created_at'] ? h(format_date($user['created_at'], 'd.m.Y')) : '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
