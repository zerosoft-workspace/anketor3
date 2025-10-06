<?php if (!isset($showFooter) || $showFooter): ?>
<footer class="site-footer">
    <p>&copy; <?php echo date('Y'); ?> <?php echo h(config('app.name', 'Anketor')); ?>. Tüm hakları saklıdır.</p>
    <p class="help-text">Katılımcı odaklı içgörüler üretmek için tasarlandı.</p>
</footer>
<?php endif; ?>
<script src="<?php echo asset_url('js/app.js'); ?>"></script>
</body>
</html>
