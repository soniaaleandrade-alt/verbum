<?php

declare(strict_types=1);

namespace VerbumStudio\Api;

use VerbumStudio\Auth\Capabilities;
use VerbumStudio\Core\Config;

final class AuthController
{
    private Config $config;
    private ResponseFactory $responses;
    private Capabilities $capabilities;

    public function __construct(Config $config, ResponseFactory $responses, Capabilities $capabilities)
    {
        $this->config = $config;
        $this->responses = $responses;
        $this->capabilities = $capabilities;
    }

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            $namespace = $this->config->get('api_namespace');

            register_rest_route($namespace, '/auth/login', [
                'methods' => 'POST',
                'callback' => [$this, 'login'],
                'permission_callback' => '__return_true',
            ]);
            register_rest_route($namespace, '/auth/register', [
                'methods' => 'POST',
                'callback' => [$this, 'createAccount'],
                'permission_callback' => '__return_true',
            ]);
            register_rest_route($namespace, '/auth/logout', [
                'methods' => 'POST',
                'callback' => [$this, 'logout'],
                'permission_callback' => '__return_true',
            ]);
            register_rest_route($namespace, '/auth/forgot-password', [
                'methods' => 'POST',
                'callback' => [$this, 'forgotPassword'],
                'permission_callback' => '__return_true',
            ]);
            register_rest_route($namespace, '/auth/reset-password', [
                'methods' => 'POST',
                'callback' => [$this, 'resetPassword'],
                'permission_callback' => '__return_true',
            ]);
            register_rest_route($namespace, '/auth/verify-email', [
                'methods' => 'POST',
                'callback' => [$this, 'verifyEmail'],
                'permission_callback' => '__return_true',
            ]);
            register_rest_route($namespace, '/auth/resend-verification', [
                'methods' => 'POST',
                'callback' => [$this, 'resendVerification'],
                'permission_callback' => '__return_true',
            ]);
            register_rest_route($namespace, '/profile', [
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'profile'],
                    'permission_callback' => '__return_true',
                ],
                [
                    'methods' => 'PATCH',
                    'callback' => [$this, 'updateProfile'],
                    'permission_callback' => '__return_true',
                ],
            ]);
            register_rest_route($namespace, '/profile/avatar', [
                'methods' => 'POST',
                'callback' => [$this, 'uploadAvatar'],
                'permission_callback' => '__return_true',
            ]);
        });
    }

    public function login(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        $identifier = sanitize_text_field((string) ($data['email'] ?? $data['identifier'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $remember = ! empty($data['remember']);

        if ($identifier === '' || $password === '') {
            return $this->fail('invalid_login', 'Informe seu e-mail e sua senha.', 400);
        }

        $user = wp_authenticate($identifier, $password);
        if (is_wp_error($user)) {
            return $this->fail('invalid_credentials', 'E-mail ou senha inválidos.', 401);
        }

        if (! user_can($user, Capabilities::ACCESS)) {
            return $this->fail('access_denied', 'Esta conta ainda não possui acesso ao Verbum Studio.', 403);
        }

        wp_set_current_user((int) $user->ID);
        wp_set_auth_cookie((int) $user->ID, $remember, is_ssl());
        do_action('wp_login', (string) $user->user_login, $user);

        return $this->responses->success([
            'user' => $this->userData($user),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }

    public function createAccount(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        $firstName = sanitize_text_field((string) ($data['first_name'] ?? ''));
        $lastName = sanitize_text_field((string) ($data['last_name'] ?? ''));
        $email = sanitize_email((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $accepted = ! empty($data['accepted_terms']);

        if ($firstName === '' || $lastName === '' || ! is_email($email)) {
            return $this->fail('invalid_registration', 'Informe nome, sobrenome e um e-mail válido.', 400);
        }
        if (strlen($password) < 8) {
            return $this->fail('weak_password', 'A senha deve ter pelo menos 8 caracteres.', 400);
        }
        if (! $accepted) {
            return $this->fail('terms_required', 'É necessário aceitar os Termos de Uso e a Política de Privacidade.', 400);
        }
        if (email_exists($email)) {
            return $this->fail('email_exists', 'Já existe uma conta com este e-mail.', 409);
        }

        $base = sanitize_user((string) strstr($email, '@', true), true);
        $base = $base !== '' ? $base : 'verbum';
        $username = $base;
        $suffix = 1;
        while (username_exists($username)) {
            $username = $base . $suffix;
            $suffix++;
        }

        $userId = wp_create_user($username, $password, $email);
        if (is_wp_error($userId)) {
            return $this->fail('registration_failed', 'Não foi possível criar sua conta.', 400);
        }

        wp_update_user([
            'ID' => (int) $userId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => trim($firstName . ' ' . $lastName),
        ]);

        $user = get_user_by('id', (int) $userId);
        if (! $user) {
            return $this->fail('registration_failed', 'Não foi possível preparar sua conta.', 500);
        }
        $user->set_role(Capabilities::WRITER_ROLE);
        update_user_meta((int) $userId, '_verbum_email_verified', '0');
        $this->sendVerification($user);

        wp_set_current_user((int) $userId);
        wp_set_auth_cookie((int) $userId, true, is_ssl());
        do_action('wp_login', (string) $user->user_login, $user);

        return $this->responses->success([
            'user' => $this->userData($user),
            'nonce' => wp_create_nonce('wp_rest'),
        ], 201);
    }

    public function logout(): \WP_REST_Response
    {
        if (is_user_logged_in()) {
            wp_logout();
        }

        return $this->responses->success([
            'loggedOut' => true,
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }

    public function forgotPassword(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        $email = sanitize_email((string) ($data['email'] ?? ''));
        $user = $email !== '' ? get_user_by('email', $email) : false;

        if ($user) {
            $key = get_password_reset_key($user);
            if (! is_wp_error($key)) {
                $url = add_query_arg([
                    'verbum_reset' => '1',
                    'key' => rawurlencode((string) $key),
                    'login' => rawurlencode((string) $user->user_login),
                ], home_url('/'));
                $subject = 'Redefina sua senha do Verbum Studio';
                $message = "Olá,\n\nRecebemos uma solicitação para redefinir sua senha do Verbum Studio.\n\nAcesse: {$url}\n\nSe você não fez esta solicitação, ignore esta mensagem.";
                wp_mail((string) $user->user_email, $subject, $message);
            }
        }

        return $this->responses->success([
            'message' => 'Se o e-mail estiver cadastrado, você receberá as instruções para redefinir sua senha.',
        ]);
    }

    public function resetPassword(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        $login = sanitize_user((string) ($data['login'] ?? ''), true);
        $key = sanitize_text_field((string) ($data['key'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($login === '' || $key === '' || strlen($password) < 8) {
            return $this->fail('invalid_reset', 'O link é inválido ou a nova senha não atende aos requisitos.', 400);
        }

        $user = check_password_reset_key($key, $login);
        if (is_wp_error($user)) {
            return $this->fail('invalid_reset', 'Este link de redefinição expirou ou já foi utilizado.', 400);
        }

        reset_password($user, $password);

        return $this->responses->success(['message' => 'Senha alterada. Você já pode entrar no Verbum Studio.']);
    }

    public function verifyEmail(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        $email = sanitize_email((string) ($data['email'] ?? ''));
        $token = sanitize_text_field((string) ($data['token'] ?? ''));
        $user = $email !== '' ? get_user_by('email', $email) : false;

        if (! $user || $token === '') {
            return $this->fail('invalid_verification', 'Link de verificação inválido.', 400);
        }

        $stored = (string) get_user_meta((int) $user->ID, '_verbum_email_token', true);
        if ($stored === '' || ! hash_equals($stored, hash('sha256', $token))) {
            return $this->fail('invalid_verification', 'Link de verificação inválido ou expirado.', 400);
        }

        update_user_meta((int) $user->ID, '_verbum_email_verified', '1');
        delete_user_meta((int) $user->ID, '_verbum_email_token');

        return $this->responses->success(['message' => 'E-mail confirmado com sucesso.']);
    }

    public function resendVerification(): \WP_REST_Response
    {
        $user = $this->requireUser();
        if (! $user) {
            return $this->fail('unauthorized', 'Faça login para continuar.', 401);
        }

        if ($this->emailVerified($user)) {
            return $this->responses->success(['message' => 'Seu e-mail já está confirmado.']);
        }

        $this->sendVerification($user);
        return $this->responses->success(['message' => 'Enviamos uma nova mensagem de verificação.']);
    }

    public function profile(): \WP_REST_Response
    {
        $user = $this->requireUser();
        if (! $user) {
            return $this->fail('unauthorized', 'Faça login para continuar.', 401);
        }

        return $this->responses->success($this->profileData($user));
    }

    public function updateProfile(\WP_REST_Request $request): \WP_REST_Response
    {
        $user = $this->requireUser();
        if (! $user) {
            return $this->fail('unauthorized', 'Faça login para continuar.', 401);
        }

        $data = $request->get_json_params();
        $firstName = sanitize_text_field((string) ($data['first_name'] ?? $user->first_name));
        $lastName = sanitize_text_field((string) ($data['last_name'] ?? $user->last_name));
        $displayName = sanitize_text_field((string) ($data['display_name'] ?? trim($firstName . ' ' . $lastName)));
        $bio = sanitize_textarea_field((string) ($data['bio'] ?? ''));

        wp_update_user([
            'ID' => (int) $user->ID,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => $displayName !== '' ? $displayName : trim($firstName . ' ' . $lastName),
            'description' => $bio,
        ]);

        update_user_meta((int) $user->ID, '_verbum_phone', sanitize_text_field((string) ($data['phone'] ?? '')));
        update_user_meta((int) $user->ID, '_verbum_country', sanitize_text_field((string) ($data['country'] ?? 'Brasil')));
        update_user_meta((int) $user->ID, '_verbum_language', sanitize_text_field((string) ($data['language'] ?? 'pt_BR')));
        update_user_meta((int) $user->ID, '_verbum_timezone', sanitize_text_field((string) ($data['timezone'] ?? 'America/Sao_Paulo')));

        $fresh = get_user_by('id', (int) $user->ID);
        return $this->responses->success($this->profileData($fresh ?: $user));
    }

    public function uploadAvatar(): \WP_REST_Response
    {
        $user = $this->requireUser();
        if (! $user) {
            return $this->fail('unauthorized', 'Faça login para continuar.', 401);
        }

        if (empty($_FILES['avatar']) || ! is_array($_FILES['avatar'])) {
            return $this->fail('avatar_required', 'Selecione uma imagem.', 400);
        }

        $file = $_FILES['avatar'];
        if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
            return $this->fail('avatar_too_large', 'A foto deve ter no máximo 5 MB.', 400);
        }
        $type = (string) ($file['type'] ?? '');
        if (strpos($type, 'image/') !== 0) {
            return $this->fail('invalid_avatar', 'Envie um arquivo de imagem válido.', 400);
        }

        if (! function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $attachmentId = media_handle_upload('avatar', 0, [], ['test_form' => false]);
        if (is_wp_error($attachmentId)) {
            return $this->fail('avatar_upload_failed', 'Não foi possível enviar a foto.', 400);
        }

        update_user_meta((int) $user->ID, '_verbum_avatar_id', (int) $attachmentId);
        $url = wp_get_attachment_image_url((int) $attachmentId, 'thumbnail');

        return $this->responses->success(['avatarUrl' => $url ? esc_url_raw($url) : '']);
    }

    /** @return \WP_User|false */
    private function requireUser()
    {
        if (! is_user_logged_in() || ! $this->capabilities->currentUserCanAccess()) {
            return false;
        }
        return wp_get_current_user();
    }

    private function profileData($user): array
    {
        return array_merge($this->userData($user), [
            'firstName' => sanitize_text_field((string) $user->first_name),
            'lastName' => sanitize_text_field((string) $user->last_name),
            'displayName' => sanitize_text_field((string) $user->display_name),
            'phone' => sanitize_text_field((string) get_user_meta((int) $user->ID, '_verbum_phone', true)),
            'country' => sanitize_text_field((string) (get_user_meta((int) $user->ID, '_verbum_country', true) ?: 'Brasil')),
            'language' => sanitize_text_field((string) (get_user_meta((int) $user->ID, '_verbum_language', true) ?: 'pt_BR')),
            'timezone' => sanitize_text_field((string) (get_user_meta((int) $user->ID, '_verbum_timezone', true) ?: 'America/Sao_Paulo')),
            'bio' => sanitize_textarea_field((string) $user->description),
        ]);
    }

    private function userData($user): array
    {
        $firstName = sanitize_text_field((string) $user->first_name);
        $lastName = sanitize_text_field((string) $user->last_name);
        $fullName = trim($firstName . ' ' . $lastName);
        $avatarId = (int) get_user_meta((int) $user->ID, '_verbum_avatar_id', true);
        $avatarUrl = $avatarId > 0 ? wp_get_attachment_image_url($avatarId, 'thumbnail') : '';

        return [
            'id' => (string) $user->ID,
            'name' => $fullName !== '' ? $fullName : sanitize_text_field((string) $user->display_name),
            'email' => sanitize_email((string) $user->user_email),
            'avatarUrl' => $avatarUrl ? esc_url_raw($avatarUrl) : '',
            'emailVerified' => $this->emailVerified($user),
        ];
    }

    private function emailVerified($user): bool
    {
        return (string) get_user_meta((int) $user->ID, '_verbum_email_verified', true) === '1';
    }

    private function sendVerification($user): void
    {
        try {
            $token = bin2hex(random_bytes(24));
        } catch (\Throwable $exception) {
            $token = wp_generate_password(48, false, false);
        }
        update_user_meta((int) $user->ID, '_verbum_email_token', hash('sha256', $token));
        $url = add_query_arg([
            'verbum_verify' => '1',
            'email' => rawurlencode((string) $user->user_email),
            'token' => rawurlencode($token),
        ], home_url('/'));
        wp_mail((string) $user->user_email, 'Confirme seu e-mail no Verbum Studio', "Olá,\n\nConfirme seu e-mail acessando:\n{$url}\n\nVerbum Studio");
    }

    private function fail(string $code, string $message, int $status): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => false,
            'error' => [
                'code' => sanitize_key($code),
                'message' => sanitize_text_field($message),
            ],
        ], $status);
    }
}
