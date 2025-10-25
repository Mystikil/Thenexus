<?php
$currentPage = $_GET['p'] ?? 'home';
$currentPage = strtolower(trim((string) $currentPage));
$currentPage = preg_replace('/[^a-z0-9_]/', '', $currentPage);

$primaryLinks = [
    'home' => ['label' => 'Home', 'href' => '?p=home'],
    'news' => ['label' => 'News', 'href' => '?p=news'],
    'changelog' => ['label' => 'Changelog', 'href' => '?p=changelog'],
    'highscores' => ['label' => 'Highscores', 'href' => '?p=highscores'],
    'whoisonline' => ['label' => 'Who is Online', 'href' => '?p=whoisonline'],
    'shop' => ['label' => 'Shop', 'href' => '?p=shop'],
    'downloads' => ['label' => 'Downloads', 'href' => '?p=downloads'],
    'about' => ['label' => 'About', 'href' => '?p=about'],
];

?>
<nav class="site-nav" aria-label="Main">
    <ul class="site-nav__list">
        <?php foreach ($primaryLinks as $slug => $link): ?>
            <?php $isActive = $currentPage === $slug; ?>
            <li class="site-nav__item">
                <a class="site-nav__link<?php echo $isActive ? ' is-active' : ''; ?>" href="<?php echo sanitize($link['href']); ?>"<?php echo $isActive ? ' aria-current="page"' : ''; ?>>
                    <?php echo sanitize($link['label']); ?>
                </a>
            </li>
        <?php endforeach; ?>

        <?php if (is_logged_in()): ?>
            <?php $accountActive = $currentPage === 'account'; ?>
            <li class="site-nav__item">
                <a class="site-nav__link<?php echo $accountActive ? ' is-active' : ''; ?>" href="?p=account"<?php echo $accountActive ? ' aria-current="page"' : ''; ?>>
                    Account
                </a>
            </li>
            <li class="site-nav__item">
                <form class="site-nav__logout-form" method="post" action="?p=account">
                    <input type="hidden" name="action" value="logout">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize(csrf_token()); ?>">
                    <button type="submit" class="site-nav__logout-button">Logout</button>
                </form>
            </li>
        <?php else: ?>
            <li class="site-nav__item">
                <a class="site-nav__link<?php echo $currentPage === 'account' ? ' is-active' : ''; ?>" href="?p=account#login">
                    Login
                </a>
            </li>
            <li class="site-nav__item">
                <a class="site-nav__link" href="?p=account#register">Register</a>
            </li>
        <?php endif; ?>
    </ul>
</nav>
