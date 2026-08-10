<?php

declare(strict_types=1);

namespace VerbumStudio\Api;

use VerbumStudio\Auth\Capabilities;
use VerbumStudio\Core\Config;
use VerbumStudio\Exceptions\AuthenticationError;
use VerbumStudio\Exceptions\AuthorizationError;

final class RestController
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
            register_rest_route($this->config->get('api_namespace'), '/health', [
                'methods' => 'GET',
                'callback' => [$this, 'health'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route($this->config->get('api_namespace'), '/me', [
                'methods' => 'GET',
                'callback' => [$this, 'me'],
                'permission_callback' => '__return_true',
            ]);
        });
    }

    public function health(): \WP_REST_Response
    {
        return $this->responses->success([
            'status' => 'ok',
            'version' => $this->config->get('version'),
        ]);
    }

    public function me(): \WP_REST_Response
    {
        try {
            if (! is_user_logged_in()) {
                throw new AuthenticationError('Acesso não autorizado.');
            }

            if (! $this->capabilities->currentUserCanAccess()) {
                throw new AuthorizationError('Permissão insuficiente.');
            }

            $user = wp_get_current_user();
            $firstName = sanitize_text_field((string) $user->first_name);
            $lastName = sanitize_text_field((string) $user->last_name);
            $fullName = trim($firstName . ' ' . $lastName);
            $displayName = $fullName !== '' ? $fullName : sanitize_text_field($user->display_name);
            $avatarId = (int) get_user_meta((int) $user->ID, '_verbum_avatar_id', true);
            $avatarUrl = $avatarId > 0 ? wp_get_attachment_image_url($avatarId, 'thumbnail') : '';

            return $this->responses->success([
                'id' => (string) $user->ID,
                'name' => $displayName,
                'email' => sanitize_email($user->user_email),
                'avatarUrl' => $avatarUrl ? esc_url_raw($avatarUrl) : '',
                'emailVerified' => (string) get_user_meta((int) $user->ID, '_verbum_email_verified', true) === '1',
            ]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }
}
