<nav class="site-nav">
    <ul>
        <li><a href="?p=home">Home</a></li>
        <li><a href="?p=news">News</a></li>
        <li><a href="?p=changelog">Changelog</a></li>
        <li><a href="?p=highscores">Highscores</a></li>
        <li><a href="?p=whoisonline">Who is Online</a></li>
        <li><a href="?p=shop">Shop</a></li>
        <li><a href="?p=downloads">Downloads</a></li>
        <li><a href="?p=about">About</a></li>
        <?php if (is_logged_in()): ?>
            <li><a href="?p=account">Account</a></li>
            <li>
                <form class="site-nav__logout-form" method="post" action="?p=account">
                    <input type="hidden" name="action" value="logout">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize(csrf_token()); ?>">
                    <button type="submit" class="site-nav__logout-button">Logout</button>
                </form>
            </li>
        <?php else: ?>
            <li><a href="?p=account#login">Login</a></li>
            <li><a href="?p=account#register">Register</a></li>
        <?php endif; ?>
    </ul>
</nav>
