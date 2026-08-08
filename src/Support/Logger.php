<?php
declare(strict_types=1);

namespace VerbumStudio\Support;

final class Logger
{
    public const INFO='INFO'; public const WARNING='WARNING'; public const ERROR='ERROR'; public const CRITICAL='CRITICAL';
    private const SENSITIVE = ['password','senha','token','api_key','apikey','service_key','secret','credential','authorization'];

    public function log(string $level, string $message, array $context = []): void
    {
        $level = in_array($level, [self::INFO,self::WARNING,self::ERROR,self::CRITICAL], true) ? $level : self::INFO;
        $safe = $this->redact($context);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Verbum Studio] ' . $level . ' ' . sanitize_text_field($message) . ' ' . wp_json_encode($safe));
        }
    }
    public function info(string $m,array $c=[]):void{$this->log(self::INFO,$m,$c);} public function warning(string $m,array $c=[]):void{$this->log(self::WARNING,$m,$c);} public function error(string $m,array $c=[]):void{$this->log(self::ERROR,$m,$c);} public function critical(string $m,array $c=[]):void{$this->log(self::CRITICAL,$m,$c);}
    public function redact(array $context): array
    {
        foreach ($context as $key => $value) {
            foreach (self::SENSITIVE as $needle) {
                if (stripos((string)$key, $needle) !== false) { $context[$key] = '[redacted]'; continue 2; }
            }
            if (is_array($value)) { $context[$key] = $this->redact($value); }
        }
        return $context;
    }
}
