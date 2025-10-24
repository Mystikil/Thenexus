<?php
declare(strict_types=1);

$loginErrors = [];
$registerErrors = [];
$passwordErrors = [];
$loginEmail = '';
$registerEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? null;

    if ($action === 'login') {
        $loginEmail = trim((string) ($_POST['email'] ?? ''));
    }

    if ($action === 'register') {
        $registerEmail = trim((string) ($_POST['email'] ?? ''));
    }

    if (!csrf_validate($token)) {
        if ($action === 'login') {
            $loginErrors[] = 'Invalid request. Please try again.';
        } elseif ($action === 'register') {
            $registerErrors[] = 'Invalid request. Please try again.';
        } elseif ($action === 'password') {
            $passwordErrors[] = 'Invalid request. Please try again.';
        } elseif ($action === 'logout') {
            flash('error', 'Invalid request. Please try again.');
            redirect('?p=account');
        }
    } else {
        switch ($action) {
            case 'login':
                $password = (string) ($_POST['password'] ?? '');
                $result = login($loginEmail, $password);

                if ($result['success'] ?? false) {
                    flash('success', 'You are now logged in.');
                    redirect('?p=account');
                }

                $loginErrors = $result['errors'] ?? ['Unable to log in.'];
                break;

            case 'register':
                $password = (string) ($_POST['password'] ?? '');
                $confirm = (string) ($_POST['confirm_password'] ?? '');

                if ($password !== $confirm) {
                    $registerErrors[] = 'Passwords do not match.';
                    break;
                }

                $result = register($registerEmail, $password);

                if ($result['success'] ?? false) {
                    flash('success', 'Your account has been created.');
                    redirect('?p=account');
                }

                $registerErrors = $result['errors'] ?? ['Unable to register at this time.'];
                break;

            case 'logout':
                logout();
                flash('success', 'You have been logged out.');
                redirect('?p=account');
                break;

            case 'password':
                if (!is_logged_in()) {
                    flash('error', 'You must be logged in to change your password.');
                    redirect('?p=account');
                }

                $currentPassword = (string) ($_POST['current_password'] ?? '');
                $newPassword = (string) ($_POST['new_password'] ?? '');
                $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

                if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                    $passwordErrors[] = 'All password fields are required.';
                }

                if ($newPassword !== $confirmPassword) {
                    $passwordErrors[] = 'New password and confirmation do not match.';
                }

                if ($newPassword !== '' && strlen($newPassword) < 8) {
                    $passwordErrors[] = 'New password must be at least 8 characters long.';
                }

                if ($passwordErrors === []) {
                    $user = current_user();

                    if (!$user || !password_verify($currentPassword, $user['pass_hash'])) {
                        $passwordErrors[] = 'Current password is incorrect.';
                    } else {
                        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                        $stmt = db()->prepare('UPDATE website_users SET pass_hash = :pass_hash WHERE id = :id');
                        $stmt->execute([
                            'pass_hash' => $hash,
                            'id' => $user['id'],
                        ]);

                        audit_log((int) $user['id'], 'password_change');

                        flash('success', 'Your password has been updated.');
                        redirect('?p=account');
                    }
                }

                break;
        }
    }
}

$csrfToken = csrf_token();
$successMessage = take_flash('success');
$errorMessage = take_flash('error');
$user = current_user();
?>
<section class="page page--account">
    <h2>Account</h2>

    <?php if ($errorMessage): ?>
        <div class="alert alert--error"><?php echo sanitize($errorMessage); ?></div>
    <?php endif; ?>

    <?php if ($successMessage): ?>
        <div class="alert alert--success"><?php echo sanitize($successMessage); ?></div>
    <?php endif; ?>

    <?php if (!$user): ?>
        <div class="account-forms" id="login">
            <form class="account-form" method="post" action="?p=account">
                <h3>Login</h3>

                <?php if ($loginErrors): ?>
                    <ul class="form-errors">
                        <?php foreach ($loginErrors as $error): ?>
                            <li><?php echo sanitize($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">

                <div class="form-group">
                    <label for="login-email">Email</label>
                    <input type="email" id="login-email" name="email" value="<?php echo sanitize($loginEmail); ?>" required>
                </div>

                <div class="form-group">
                    <label for="login-password">Password</label>
                    <input type="password" id="login-password" name="password" required>
                </div>

                <div class="form-actions">
                    <button type="submit">Login</button>
                </div>
            </form>

            <form class="account-form" method="post" action="?p=account" id="register">
                <h3>Register</h3>

                <?php if ($registerErrors): ?>
                    <ul class="form-errors">
                        <?php foreach ($registerErrors as $error): ?>
                            <li><?php echo sanitize($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <input type="hidden" name="action" value="register">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">

                <div class="form-group">
                    <label for="register-email">Email</label>
                    <input type="email" id="register-email" name="email" value="<?php echo sanitize($registerEmail); ?>" required>
                </div>

                <div class="form-group">
                    <label for="register-password">Password</label>
                    <input type="password" id="register-password" name="password" required>
                </div>

                <div class="form-group">
                    <label for="register-confirm">Confirm Password</label>
                    <input type="password" id="register-confirm" name="confirm_password" required>
                </div>

                <div class="form-actions">
                    <button type="submit">Create Account</button>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="account-profile">
            <h3>Your Profile</h3>
            <dl>
                <dt>Email</dt>
                <dd><?php echo sanitize($user['email']); ?></dd>
                <dt>Role</dt>
                <dd><?php echo sanitize($user['role']); ?></dd>
            </dl>
        </div>

        <form class="account-form" method="post" action="?p=account">
            <h3>Change Password</h3>

            <?php if ($passwordErrors): ?>
                <ul class="form-errors">
                    <?php foreach ($passwordErrors as $error): ?>
                        <li><?php echo sanitize($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <input type="hidden" name="action" value="password">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">

            <div class="form-group">
                <label for="current-password">Current Password</label>
                <input type="password" id="current-password" name="current_password" required>
            </div>

            <div class="form-group">
                <label for="new-password">New Password</label>
                <input type="password" id="new-password" name="new_password" required>
            </div>

            <div class="form-group">
                <label for="confirm-password">Confirm New Password</label>
                <input type="password" id="confirm-password" name="confirm_password" required>
            </div>

            <div class="form-actions">
                <button type="submit">Update Password</button>
            </div>
        </form>

        <div class="account-theme-placeholder">
            <h3>Theme Preference</h3>
            <div class="form-group">
                <label for="theme-preference">Preferred Theme</label>
                <select id="theme-preference" disabled>
                    <option value="default">Default</option>
                    <option value="dark">Dark</option>
                    <option value="light">Light</option>
                </select>
            </div>
            <p>Theme selection is coming soon.</p>
        </div>
    <?php endif; ?>
</section>
