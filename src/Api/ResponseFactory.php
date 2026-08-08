<?php

declare(strict_types=1);

namespace VerbumStudio\Api;

use VerbumStudio\Core\Config;
use VerbumStudio\Exceptions\VerbumException;

final class ResponseFactory
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function success(array $data, int $status = 200): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => true,
            'data' => $data,
        ], $status);
    }

    public function error(\Throwable $exception): \WP_REST_Response
    {
        $status = $exception instanceof VerbumException ? $exception->status() : 500;
        $code = $exception instanceof VerbumException ? $exception->errorCode() : 'internal_error';
        $message = $exception->getMessage() ?: 'Erro interno.';

        if ($this->config->isProduction() && $status >= 500) {
            $message = 'Erro interno.';
        }

        return new \WP_REST_Response([
            'success' => false,
            'error' => [
                'code' => sanitize_key($code),
                'message' => sanitize_text_field($message),
            ],
        ], $status);
    }
}
