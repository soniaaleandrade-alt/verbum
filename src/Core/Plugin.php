<?php

declare(strict_types=1);

namespace VerbumStudio\Core;

use VerbumStudio\Api\LibraryController;
use VerbumStudio\Api\ResponseFactory;
use VerbumStudio\Api\RestController;
use VerbumStudio\Api\WorkProjectController;
use VerbumStudio\Auth\Capabilities;
use VerbumStudio\Integrations\Elementor\ElementorIntegration;
use VerbumStudio\Integrations\Supabase\SupabaseClient;
use VerbumStudio\Integrations\Supabase\SupabaseConfig;
use VerbumStudio\Library\LibraryPostTypes;
use VerbumStudio\Library\LibraryRepository;
use VerbumStudio\Library\WorkProjectRepository;
use VerbumStudio\Services\FrontendAssets;
use VerbumStudio\Support\Logger;

final class Plugin
{
    private Container $container;
    private Config $config;

    public function __construct(Container $container, Config $config)
    {
        $this->container = $container;
        $this->config = $config;

        $this->registerServices();
    }

    public function register(): void
    {
        $this->container->get(RestController::class)->register();
        $this->container->get(LibraryController::class)->register();
        $this->container->get(WorkProjectController::class)->register();
        $this->container->get(FrontendAssets::class)->register();

        add_action('init', function (): void {
            $this->container->get(LibraryPostTypes::class)->register();
            $this->container->get(Capabilities::class)->add();
        });

        add_action('plugins_loaded', function (): void {
            $elementor = $this->container->get(ElementorIntegration::class);
            $this->container->get(Logger::class)->info($elementor->statusMessage());

            if ($elementor->isAvailable()) {
                $elementor->register();
            }
        }, 20);
    }

    public function container(): Container
    {
        return $this->container;
    }

    private function registerServices(): void
    {
        $this->container->set(Config::class, $this->config);
        $this->container->set(Logger::class, static fn (): Logger => new Logger());
        $this->container->set(Capabilities::class, static fn (): Capabilities => new Capabilities());
        $this->container->set(ResponseFactory::class, static fn (Container $container): ResponseFactory => new ResponseFactory($container->get(Config::class)));
        $this->container->set(RestController::class, static fn (Container $container): RestController => new RestController(
            $container->get(Config::class),
            $container->get(ResponseFactory::class),
            $container->get(Capabilities::class)
        ));
        $this->container->set(LibraryPostTypes::class, static fn (): LibraryPostTypes => new LibraryPostTypes());
        $this->container->set(LibraryRepository::class, static fn (): LibraryRepository => new LibraryRepository());
        $this->container->set(WorkProjectRepository::class, static fn (): WorkProjectRepository => new WorkProjectRepository());
        $this->container->set(LibraryController::class, static fn (Container $container): LibraryController => new LibraryController(
            $container->get(Config::class),
            $container->get(ResponseFactory::class),
            $container->get(Capabilities::class),
            $container->get(LibraryRepository::class)
        ));
        $this->container->set(WorkProjectController::class, static fn (Container $container): WorkProjectController => new WorkProjectController(
            $container->get(Config::class),
            $container->get(ResponseFactory::class),
            $container->get(Capabilities::class),
            $container->get(LibraryRepository::class),
            $container->get(WorkProjectRepository::class)
        ));
        $this->container->set(FrontendAssets::class, static fn (): FrontendAssets => new FrontendAssets());
        $this->container->set(SupabaseConfig::class, static fn (Container $container): SupabaseConfig => new SupabaseConfig($container->get(Config::class)));
        $this->container->set(SupabaseClient::class, static fn (Container $container): SupabaseClient => new SupabaseClient($container->get(SupabaseConfig::class)));
        $this->container->set(ElementorIntegration::class, static fn (): ElementorIntegration => new ElementorIntegration());
    }
}
