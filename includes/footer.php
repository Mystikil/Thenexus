</main>
<footer class="site-footer">
    <div class="container">
        <p>&copy; <?= date('Y'); ?> <?= htmlspecialchars(defined('SITE_NAME') ? SITE_NAME : 'Site', ENT_QUOTES, 'UTF-8'); ?>. All rights reserved.</p>
    </div>
</footer>
<?php if (!empty($additionalScripts)): ?>
    <?php foreach ($additionalScripts as $scriptSrc): ?>
        <script src="<?= htmlspecialchars($scriptSrc, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
