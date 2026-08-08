<?php

declare(strict_types=1);

namespace VerbumStudio\Integrations\Elementor;

final class ElementorIntegration
{
    public function register(): void
    {
        add_action('elementor/widgets/register', function ($widgetsManager): void {
            if (! class_exists(VerbumAppWidget::class)) {
                return;
            }

            $widgetsManager->register(new VerbumAppWidget());
        });
    }

    public function isAvailable(): bool
    {
        return did_action('elementor/loaded') || class_exists('Elementor\\Plugin');
    }

    public function statusMessage(): string
    {
        return $this->isAvailable() ? 'Elementor encontrado' : 'Elementor não encontrado';
    }
}
