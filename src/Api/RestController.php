<?php

declare(strict_types=1);

namespace VerbumStudio\Api;

use VerbumStudio\Auth\Capabilities;
use VerbumStudio\Core\Config;
use VerbumStudio\Exceptions\AuthenticationError;
use VerbumStudio\Exceptions\AuthorizationError;
use VerbumStudio\Exceptions\NotFoundError;
use VerbumStudio\Library\LibraryPostTypes;

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
            $namespace = $this->config->get('api_namespace');

            register_rest_route($namespace, '/health', [
                'methods' => 'GET',
                'callback' => [$this, 'health'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route($namespace, '/me', [
                'methods' => 'GET',
                'callback' => [$this, 'me'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route($namespace, '/books/cover-positions', [
                'methods' => 'GET',
                'callback' => [$this, 'coverPositions'],
                'permission_callback' => [$this, 'canAccess'],
            ]);

            register_rest_route($namespace, '/books/(?P<id>\d+)/cover-position', [
                'methods' => 'PATCH',
                'callback' => [$this, 'saveCoverPosition'],
                'permission_callback' => [$this, 'canAccess'],
            ]);

            register_rest_route($namespace, '/books/(?P<id>\d+)', [
                'methods' => 'DELETE',
                'callback' => [$this, 'deleteBook'],
                'permission_callback' => [$this, 'canAccess'],
            ]);

            register_rest_route($namespace, '/books/(?P<id>\d+)/trash/restore', [
                'methods' => 'POST',
                'callback' => [$this, 'restoreDeletedBook'],
                'permission_callback' => [$this, 'canAccess'],
            ]);

            register_rest_route($namespace, '/books/(?P<id>\d+)/permanent', [
                'methods' => 'DELETE',
                'callback' => [$this, 'permanentlyDeleteBook'],
                'permission_callback' => [$this, 'canAccess'],
            ]);
        });
    }

    public function canAccess(): bool
    {
        return $this->capabilities->currentUserCanAccess();
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

    public function coverPositions(): \WP_REST_Response
    {
        try {
            $userId = get_current_user_id();
            $query = new \WP_Query([
                'post_type' => LibraryPostTypes::BOOK,
                'post_status' => 'publish',
                'author' => $userId,
                'posts_per_page' => -1,
                'fields' => 'ids',
                'no_found_rows' => true,
            ]);

            $positions = [];
            foreach ($query->posts as $bookId) {
                $id = (int) $bookId;
                $positions[(string) $id] = $this->coverPositionData($id);
            }

            return $this->responses->success($positions);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function saveCoverPosition(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->ownedBook(get_current_user_id(), $bookId);
            $payload = $request->get_json_params();
            $payload = is_array($payload) ? $payload : [];

            $x = $this->clampFloat($payload['x'] ?? 50, 0, 100);
            $y = $this->clampFloat($payload['y'] ?? 50, 0, 100);
            $zoom = $this->clampFloat($payload['zoom'] ?? 1, 1, 2.5);

            update_post_meta($bookId, '_verbum_cover_position_x', $x);
            update_post_meta($bookId, '_verbum_cover_position_y', $y);
            update_post_meta($bookId, '_verbum_cover_zoom', $zoom);

            return $this->responses->success([
                'id' => (string) $bookId,
                'x' => $x,
                'y' => $y,
                'zoom' => $zoom,
            ]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function deleteBook(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $userId = get_current_user_id();
            $bookId = (int) $request['id'];
            $this->ownedBook($userId, $bookId);
            $published=get_post_meta($bookId,'_verbum_published_editions',true);
            if(is_array($published)&&$published!==[]) throw new AuthorizationError('Uma obra publicada não pode ser excluída como obra comum. Arquive-a ou registre uma solicitação administrativa.');
            update_post_meta($bookId, '_verbum_status_before_trash', (string) (get_post_meta($bookId, '_verbum_status', true) ?: 'active'));
            update_post_meta($bookId, '_verbum_trashed_at', gmdate('c'));
            $history=get_post_meta($bookId,'_verbum_library_history',true);$history=is_array($history)?$history:[];$history[]=['label'=>'Obra enviada para a lixeira','userId'=>$userId,'at'=>gmdate('c')];update_post_meta($bookId,'_verbum_library_history',$history);
            $deleted = wp_trash_post($bookId);
            if (! $deleted instanceof \WP_Post) {
                throw new \RuntimeException('Não foi possível excluir a obra.');
            }

            return $this->responses->success([
                'id' => (string) $bookId,
                'deleted' => true,
                'recoverable' => true,
            ]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function restoreDeletedBook(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $userId = get_current_user_id();
            $bookId = (int) $request['id'];
            $book = $this->ownedBook($userId, $bookId);
            if ($book->post_status !== 'trash') {
                throw new \RuntimeException('Esta obra não está na lixeira.');
            }
            $restored = wp_untrash_post($bookId);
            if (! $restored instanceof \WP_Post) {
                throw new \RuntimeException('Não foi possível restaurar a obra.');
            }
            $previous = sanitize_key((string) get_post_meta($bookId, '_verbum_status_before_trash', true));
            update_post_meta($bookId, '_verbum_status', in_array($previous, ['active', 'archived'], true) ? $previous : 'active');
            delete_post_meta($bookId, '_verbum_trashed_at');
            $this->appendHistory($bookId, $userId, 'Obra restaurada da lixeira');
            return $this->responses->success(['id' => (string) $bookId, 'restored' => true]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function permanentlyDeleteBook(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $userId = get_current_user_id();
            $bookId = (int) $request['id'];
            $book = $this->ownedBook($userId, $bookId);
            if ($book->post_status !== 'trash') {
                throw new \RuntimeException('Envie a obra para a lixeira antes da exclusão definitiva.');
            }
            $published = get_post_meta($bookId, '_verbum_published_editions', true);
            if (is_array($published) && $published !== []) {
                throw new AuthorizationError('Uma edição publicada não pode ser excluída definitivamente.');
            }
            $related = new \WP_Query([
                'post_type' => array_values(get_post_types([], 'names')), 'post_status' => 'any', 'author' => $userId,
                'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true,
                'meta_key' => '_verbum_book_id', 'meta_value' => $bookId,
            ]);
            foreach ((array) $related->posts as $relatedId) {
                wp_delete_post((int) $relatedId, true);
            }
            $deleted = wp_delete_post($bookId, true);
            if (! $deleted instanceof \WP_Post) {
                throw new \RuntimeException('Não foi possível excluir definitivamente a obra.');
            }
            return $this->responses->success(['id' => (string) $bookId, 'permanentlyDeleted' => true]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    private function appendHistory(int $bookId, int $userId, string $label): void
    {
        $history = get_post_meta($bookId, '_verbum_library_history', true);
        $history = is_array($history) ? $history : [];
        $history[] = ['label' => $label, 'userId' => $userId, 'at' => gmdate('c')];
        update_post_meta($bookId, '_verbum_library_history', $history);
    }

    private function ownedBook(int $userId, int $bookId): \WP_Post
    {
        $book = get_post($bookId);
        if (! $book instanceof \WP_Post || $book->post_type !== LibraryPostTypes::BOOK || (int) $book->post_author !== $userId) {
            throw new NotFoundError('Obra não encontrada.');
        }

        return $book;
    }

    /** @return array{x: float, y: float, zoom: float} */
    private function coverPositionData(int $bookId): array
    {
        $xRaw = get_post_meta($bookId, '_verbum_cover_position_x', true);
        $yRaw = get_post_meta($bookId, '_verbum_cover_position_y', true);
        $zoomRaw = get_post_meta($bookId, '_verbum_cover_zoom', true);

        return [
            'x' => $xRaw === '' ? 50.0 : $this->clampFloat($xRaw, 0, 100),
            'y' => $yRaw === '' ? 50.0 : $this->clampFloat($yRaw, 0, 100),
            'zoom' => $zoomRaw === '' ? 1.0 : $this->clampFloat($zoomRaw, 1, 2.5),
        ];
    }

    private function clampFloat($value, float $minimum, float $maximum): float
    {
        $number = is_numeric($value) ? (float) $value : $minimum;
        return max($minimum, min($maximum, $number));
    }
}
