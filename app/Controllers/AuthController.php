<?php

declare(strict_types=1);

namespace Sofiago\Controllers;

use Sofiago\Core\SignedToken;
use Sofiago\Middleware\Guards;
use Sofiago\Models\User;

final class AuthController
{
    private const LOGIN_MAX_ATTEMPTS = 5;
    private const LOGIN_WINDOW_MINUTES = 15;
    private const RESET_TTL_HOURS = 1;

    // -------------------------------------------------------------------------------------
    // Registration
    // -------------------------------------------------------------------------------------

    public function showRegister(array $params): void
    {
        Guards::requireGuest();
        echo view('auth/sign-up.tpl.php', ['title' => t('page.sign_up_title')]);
    }

    public function register(array $params): void
    {
        Guards::requireGuest();
        Guards::verifyCsrf();

        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirmation'] ?? '');

        $errors = [];
        if (mb_strlen($name) < 2) {
            $errors[] = t('validation.name_length');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = t('validation.email_invalid');
        } elseif (User::emailExists($email)) {
            $errors[] = t('validation.email_taken');
        }
        if (mb_strlen($password) < 8) {
            $errors[] = t('validation.password_length');
        } elseif ($password !== $confirm) {
            $errors[] = t('validation.password_mismatch');
        }

        if ($errors !== []) {
            flash_errors($errors);
            app()->session->keepOld(['name' => $name, 'email' => $email]);
            redirect('/sign-up');
        }

        $userId = User::create($name, $email, $password);
        $user = User::findById((int) $userId);
        auth()->login($user);
        app()->session->regenerateId();

        User::sendVerificationEmail($user);

        app()->session->flash('notice', t('flash.welcome'));
        redirect('/dashboard');
    }

    // -------------------------------------------------------------------------------------
    // Login / logout
    // -------------------------------------------------------------------------------------

    public function showLogin(array $params): void
    {
        Guards::requireGuest();
        echo view('auth/sign-in.tpl.php', ['title' => t('page.sign_in_title')]);
    }

    public function login(array $params): void
    {
        Guards::requireGuest();
        Guards::verifyCsrf();

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        if ($this->tooManyAttempts($email, $ip)) {
            flash_errors([t('validation.too_many_attempts', ['minutes' => self::LOGIN_WINDOW_MINUTES])]);
            redirect('/sign-in');
        }

        $token = auth()->attempt($email, $password);
        $this->recordAttempt($email, $ip, $token !== null);

        if ($token === null) {
            flash_errors([t('validation.invalid_credentials')]);
            app()->session->keepOld(['email' => $email]);
            redirect('/sign-in');
        }

        app()->session->regenerateId();
        redirect('/dashboard');
    }

    public function logout(array $params): void
    {
        Guards::verifyCsrf();
        auth()->logout();
        redirect('/');
    }

    // -------------------------------------------------------------------------------------
    // Email verification
    // -------------------------------------------------------------------------------------

    public function verifyEmail(array $params): void
    {
        $raw = (string) ($_GET['token'] ?? '');
        $row = $this->findValidToken('email_verification_tokens', $raw);

        if (!$row) {
            app()->session->flash('error', t('validation.verify_link_invalid'));
            redirect(auth()->check() ? '/dashboard' : '/sign-in');
        }

        db()->update('email_verification_tokens', ['used_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $row['id']]);
        User::markEmailVerified((int) $row['user_id']);

        if (!auth()->check()) {
            $user = User::findById((int) $row['user_id']);
            if ($user) {
                auth()->login($user);
            }
        }

        app()->session->flash('notice', t('flash.email_verified'));
        redirect('/dashboard');
    }

    public function resendVerification(array $params): void
    {
        Guards::requireAuth();
        Guards::verifyCsrf();

        $user = auth()->user();

        if (auth()->isVerified()) {
            app()->session->flash('notice', t('flash.email_already_verified'));
            redirect('/dashboard');
        }

        User::sendVerificationEmail($user);
        app()->session->flash('notice', t('flash.email_resent'));
        redirect('/dashboard');
    }

    // -------------------------------------------------------------------------------------
    // Forgot / reset password
    // -------------------------------------------------------------------------------------

    public function showForgotPassword(array $params): void
    {
        Guards::requireGuest();
        echo view('auth/forgot-password.tpl.php', ['title' => t('page.forgot_password_title')]);
    }

    public function sendResetLink(array $params): void
    {
        Guards::requireGuest();
        Guards::verifyCsrf();

        $email = trim((string) ($_POST['email'] ?? ''));

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $user = User::findByEmail($email);

            if ($user) {
                db()->delete('password_reset_tokens', 'user_id = ? AND used_at IS NULL', [$user['id']]);
                [$raw, $hash] = SignedToken::generate();
                db()->insert('password_reset_tokens', [
                    'user_id' => $user['id'],
                    'token_hash' => $hash,
                    'expires_at' => date('Y-m-d H:i:s', time() + self::RESET_TTL_HOURS * 3600),
                ]);

                $locale = (string) ($user['locale'] ?? 'en');

                app()->mailer->send(
                    $user['email'],
                    $user['name'],
                    t_for($locale, 'email.reset.subject'),
                    render('emails/reset-password.tpl.php', ['name' => $user['name'], 'link' => url('/reset-password?token=' . $raw), 'locale' => $locale])
                );
            }
        }

        // Same message whether or not the address is registered — don't leak who has an account.
        app()->session->flash('notice', t('flash.reset_link_sent'));
        redirect('/forgot-password');
    }

    public function showResetPassword(array $params): void
    {
        Guards::requireGuest();

        $raw = (string) ($_GET['token'] ?? '');
        if (!$this->findValidToken('password_reset_tokens', $raw)) {
            app()->session->flash('error', t('validation.reset_link_invalid'));
            redirect('/forgot-password');
        }

        echo view('auth/reset-password.tpl.php', ['title' => t('page.reset_password_title'), 'token' => $raw]);
    }

    public function resetPassword(array $params): void
    {
        Guards::requireGuest();
        Guards::verifyCsrf();

        $raw = (string) ($_POST['token'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirmation'] ?? '');

        $row = $this->findValidToken('password_reset_tokens', $raw);
        if (!$row) {
            app()->session->flash('error', t('validation.reset_link_invalid'));
            redirect('/forgot-password');
        }

        if (mb_strlen($password) < 8 || $password !== $confirm) {
            flash_errors([t('validation.reset_password_length')]);
            redirect('/reset-password?token=' . urlencode($raw));
        }

        User::updatePassword((int) $row['user_id'], $password);
        db()->update('password_reset_tokens', ['used_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $row['id']]);
        // Force re-login everywhere — whoever knew the old password shouldn't stay signed in.
        db()->delete('sessions', 'user_id = ?', [$row['user_id']]);

        app()->session->flash('notice', t('flash.password_updated'));
        redirect('/sign-in');
    }

    // -------------------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------------------

    /** @return array<string, mixed>|null */
    private function findValidToken(string $table, string $raw): ?array
    {
        if ($raw === '') {
            return null;
        }

        // $table is always one of two literals we pass ourselves above, never request input.
        return db()->one(
            "SELECT * FROM {$table} WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()",
            [SignedToken::hash($raw)]
        );
    }

    private function tooManyAttempts(string $email, string $ip): bool
    {
        $count = db()->value(
            'SELECT COUNT(*) FROM login_attempts
             WHERE (email = ? OR ip = ?) AND succeeded = 0 AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
            [mb_strtolower($email), $ip, self::LOGIN_WINDOW_MINUTES]
        );

        return (int) $count >= self::LOGIN_MAX_ATTEMPTS;
    }

    private function recordAttempt(string $email, string $ip, bool $succeeded): void
    {
        db()->insert('login_attempts', [
            'email' => mb_strtolower($email),
            'ip' => $ip,
            'succeeded' => $succeeded ? 1 : 0,
        ]);
    }
}
