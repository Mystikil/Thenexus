<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

$current = $_GET['p'] ?? 'home';
$user = current_user();

function nx_active($slug, $current)
{
    return $slug === $current ? ' aria-current="page"' : '';
}
?>
<nav class="nx-nav" aria-label="Main">
  <div class="nx-nav__inner">
    <a class="nx-brand" href="index.php">
      <img src="/assets/img/logo.png" alt="Devnexus Online" class="nx-brand__logo">
      <span class="nx-brand__name">Devnexus Online</span>
    </a>

    <button class="nx-burger" aria-expanded="false" aria-controls="nx-menu">
      <span class="sr-only">Toggle menu</span>
      <span class="nx-burger__bar"></span>
      <span class="nx-burger__bar"></span>
      <span class="nx-burger__bar"></span>
    </button>

    <ul id="nx-menu" class="nx-menu" role="menubar">
      <li class="nx-item"><a role="menuitem" href="?p=home"<?= nx_active('home', $current) ?>>Home</a></li>

      <li class="nx-item nx-has-submenu">
        <button class="nx-link" aria-haspopup="true" aria-expanded="false">Game</button>
        <ul class="nx-submenu" role="menu">
          <li><a role="menuitem" href="?p=news"<?= nx_active('news', $current) ?>>News</a></li>
          <li><a role="menuitem" href="?p=bestiary"<?= nx_active('bestiary', $current) ?>>Bestiary</a></li>
          <li><a role="menuitem" href="?p=spells"<?= nx_active('spells', $current) ?>>Spells</a></li>
          <li><a role="menuitem" href="?p=guilds"<?= nx_active('guilds', $current) ?>>Guilds</a></li>
          <li><a role="menuitem" href="?p=highscores"<?= nx_active('highscores', $current) ?>>Highscores</a></li>
          <li><a role="menuitem" href="?p=whoisonline"<?= nx_active('whoisonline', $current) ?>>Who’s Online</a></li>
          <li><a role="menuitem" href="?p=deaths"<?= nx_active('deaths', $current) ?>>Deaths</a></li>
          <li><a role="menuitem" href="?p=market"<?= nx_active('market', $current) ?>>Market</a></li>
        </ul>
      </li>

      <li class="nx-item nx-has-submenu">
        <button class="nx-link" aria-haspopup="true" aria-expanded="false">Community</button>
        <ul class="nx-submenu" role="menu">
          <li><a role="menuitem" href="?p=tickets"<?= nx_active('tickets', $current) ?>>Support</a></li>
          <li><a role="menuitem" href="?p=downloads"<?= nx_active('downloads', $current) ?>>Downloads</a></li>
          <li><a role="menuitem" href="?p=rules"<?= nx_active('rules', $current) ?>>Rules</a></li>
          <li><a role="menuitem" href="?p=about"<?= nx_active('about', $current) ?>>About</a></li>
        </ul>
      </li>

<?php if ($user): ?>
      <li class="nx-item nx-right nx-has-submenu">
        <button class="nx-link" aria-haspopup="true" aria-expanded="false">
          <?= htmlspecialchars($user['email'] ?? $user['account_name'] ?? 'Account', ENT_QUOTES, 'UTF-8') ?>
        </button>
        <ul class="nx-submenu" role="menu">
          <li><a role="menuitem" href="?p=account"<?= nx_active('account', $current) ?>>Dashboard</a></li>
          <li><a role="menuitem" href="?p=characters"<?= nx_active('characters', $current) ?>>My Characters</a></li>
          <li><a role="menuitem" href="?p=shop"<?= nx_active('shop', $current) ?>>Shop</a></li>
<?php if (is_role('admin')): ?>
          <li class="nx-sep" aria-hidden="true"></li>
          <li><a role="menuitem" href="/admin/index.php">Admin Panel</a></li>
<?php endif; ?>
          <li class="nx-sep" aria-hidden="true"></li>
          <li><a role="menuitem" href="?p=account&action=logout">Logout</a></li>
        </ul>
      </li>
<?php else: ?>
      <li class="nx-item nx-right nx-has-submenu">
        <button class="nx-link" aria-haspopup="true" aria-expanded="false">Account</button>
        <ul class="nx-submenu" role="menu">
          <li><a role="menuitem" href="?p=account"<?= nx_active('account', $current) ?>>Login / Register</a></li>
          <li><a role="menuitem" href="?p=recover"<?= nx_active('recover', $current) ?>>Recover</a></li>
        </ul>
      </li>
<?php endif; ?>
    </ul>
  </div>
</nav>
