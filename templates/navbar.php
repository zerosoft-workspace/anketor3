<?php if (current_user_id()): ?>
<nav class="top-nav">
    <div class="brand"><?php echo h(config('app.name', 'Anketor')); ?></div>
    <ul class="nav-links">
        <li><a class="<?php echo active_nav('dashboard.php'); ?>" href="dashboard.php">Ana Sayfa</a></li>
        <li><a class="<?php echo active_nav('surveys.php'); ?>" href="surveys.php">Anketler</a></li>
        <li><a class="<?php echo active_nav('reports.php'); ?>" href="reports.php">Raporlar</a></li>
        <li><a class="<?php echo active_nav('users.php'); ?>" href="users.php">Kullanıcılar</a></li>
        <?php if (is_super_admin()): ?>
            <li><a class="<?php echo active_nav('system_settings.php'); ?>" href="system_settings.php">Sistem Ayarları</a></li>
        <?php endif; ?>
    </ul>
    <div class="user-meta">
        <span><?php echo h(current_user_name()); ?> · <?php echo h(strtoupper(str_replace('_', ' ', current_user_role()))); ?></span>
        <a class="button-secondary" href="logout.php">Çıkış</a>
    </div>
</nav>
<?php endif; ?>
