<?php

declare(strict_types=1);

namespace VerbumStudio\Integrations\Elementor;

use VerbumStudio\Services\FrontendAssets;

if (class_exists('Elementor\\Widget_Base')) {
    final class VerbumAppWidget extends \Elementor\Widget_Base
    {
        public function get_name(): string
        {
            return 'verbum_app';
        }

        public function get_title(): string
        {
            return esc_html__('Verbum App', 'verbum-studio');
        }

        public function get_icon(): string
        {
            return 'eicon-code';
        }

        /** @return string[] */
        public function get_categories(): array
        {
            return ['general'];
        }

        protected function render(): void
        {
            (new FrontendAssets())->enqueue();
            echo '<div class="verbum-app" data-verbum-app></div>';
        }
    }
}
