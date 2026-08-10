<?php

declare(strict_types=1);

namespace VerbumStudio\Core;

use VerbumStudio\Api\AuthController;
use VerbumStudio\Api\LibraryController;
use VerbumStudio\Api\ResponseFactory;
use VerbumStudio\Api\RestController;
use VerbumStudio\Api\WorkChapterPreparationController;
use VerbumStudio\Api\WorkChapterResearchController;
use VerbumStudio\Api\WorkChapterRevisionController;
use VerbumStudio\Api\WorkChapterWritingController;
use VerbumStudio\Api\WorkDevelopmentController;
use VerbumStudio\Api\WorkGeneralReviewController;
use VerbumStudio\Api\WorkPlanningController;
use VerbumStudio\Api\WorkProjectController;
use VerbumStudio\Auth\Capabilities;
use VerbumStudio\Integrations\Elementor\ElementorIntegration;
use VerbumStudio\Integrations\Supabase\SupabaseClient;
use VerbumStudio\Integrations\Supabase\SupabaseConfig;
use VerbumStudio\Library\LibraryPostTypes;
use VerbumStudio\Library\LibraryRepository;
use VerbumStudio\Library\WorkChapterPreparationRepository;
use VerbumStudio\Library\WorkChapterResearchRepository;
use VerbumStudio\Library\WorkChapterRevisionRepository;
use VerbumStudio\Library\WorkChapterWritingRepository;
use VerbumStudio\Library\WorkDevelopmentRepository;
use VerbumStudio\Library\WorkGeneralReviewRepository;
use VerbumStudio\Library\WorkPlanningRepository;
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
        $this->container->get(AuthController::class)->register();
        $this->container->get(LibraryController::class)->register();
        $this->container->get(WorkProjectController::class)->register();
        $this->container->get(WorkPlanningController::class)->register();
        $this->container->get(WorkDevelopmentController::class)->register();
        $this->container->get(WorkChapterPreparationController::class)->register();
        $this->container->get(WorkChapterResearchController::class)->register();
        $this->container->get(WorkChapterWritingController::class)->register();
        $this->container->get(WorkChapterRevisionController::class)->register();
        $this->container->get(WorkGeneralReviewController::class)->register();
        $this->container->get(FrontendAssets::class)->register();

        add_action('init', function (): void {
            $this->container->get(LibraryPostTypes::class)->register();
            $this->container->get(Capabilities::class)->add();
        });

        add_action('admin_init', function (): void {
            $capabilities = $this->container->get(Capabilities::class);
            $doingAjax = function_exists('wp_doing_ajax') && wp_doing_ajax();
            if ($capabilities->currentUserCanAccess() && ! $capabilities->currentUserIsAdmin() && ! $doingAjax) {
                wp_safe_redirect(home_url('/'));
                exit;
            }
        });

        add_filter('show_admin_bar', function ($show) {
            $capabilities = $this->container->get(Capabilities::class);
            if ($capabilities->currentUserCanAccess() && ! $capabilities->currentUserIsAdmin()) return false;
            return $show;
        });

        add_action('plugins_loaded', function (): void {
            $elementor = $this->container->get(ElementorIntegration::class);
            $this->container->get(Logger::class)->info($elementor->statusMessage());
            if ($elementor->isAvailable()) $elementor->register();
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
        $this->container->set(RestController::class, static fn (Container $container): RestController => new RestController($container->get(Config::class), $container->get(ResponseFactory::class), $container->get(Capabilities::class)));
        $this->container->set(AuthController::class, static fn (Container $container): AuthController => new AuthController($container->get(Config::class), $container->get(ResponseFactory::class), $container->get(Capabilities::class)));
        $this->container->set(LibraryPostTypes::class, static fn (): LibraryPostTypes => new LibraryPostTypes());
        $this->container->set(LibraryRepository::class, static fn (): LibraryRepository => new LibraryRepository());
        $this->container->set(WorkProjectRepository::class, static fn (): WorkProjectRepository => new WorkProjectRepository());
        $this->container->set(WorkPlanningRepository::class, static fn (): WorkPlanningRepository => new WorkPlanningRepository());
        $this->container->set(WorkDevelopmentRepository::class, static fn (): WorkDevelopmentRepository => new WorkDevelopmentRepository());
        $this->container->set(WorkChapterPreparationRepository::class, static fn (): WorkChapterPreparationRepository => new WorkChapterPreparationRepository());
        $this->container->set(WorkChapterResearchRepository::class, static fn (): WorkChapterResearchRepository => new WorkChapterResearchRepository());
        $this->container->set(WorkChapterWritingRepository::class, static fn (): WorkChapterWritingRepository => new WorkChapterWritingRepository());
        $this->container->set(WorkChapterRevisionRepository::class, static fn (): WorkChapterRevisionRepository => new WorkChapterRevisionRepository());
        $this->container->set(WorkGeneralReviewRepository::class, static fn (): WorkGeneralReviewRepository => new WorkGeneralReviewRepository());
        $this->container->set(LibraryController::class, static fn (Container $container): LibraryController => new LibraryController($container->get(Config::class), $container->get(ResponseFactory::class), $container->get(Capabilities::class), $container->get(LibraryRepository::class)));
        $this->container->set(WorkProjectController::class, static fn (Container $container): WorkProjectController => new WorkProjectController($container->get(Config::class), $container->get(ResponseFactory::class), $container->get(Capabilities::class), $container->get(LibraryRepository::class), $container->get(WorkProjectRepository::class)));
        $this->container->set(WorkPlanningController::class, static fn (Container $container): WorkPlanningController => new WorkPlanningController($container->get(Config::class), $container->get(ResponseFactory::class), $container->get(Capabilities::class), $container->get(LibraryRepository::class), $container->get(WorkPlanningRepository::class)));
        $this->container->set(WorkDevelopmentController::class, static fn (Container $container): WorkDevelopmentController => new WorkDevelopmentController($container->get(Config::class), $container->get(ResponseFactory::class), $container->get(Capabilities::class), $container->get(LibraryRepository::class), $container->get(WorkDevelopmentRepository::class)));
        $this->container->set(WorkChapterPreparationController::class, static fn (Container $container): WorkChapterPreparationController => new WorkChapterPreparationController($container->get(Config::class), $container->get(ResponseFactory::class), $container->get(Capabilities::class), $container->get(LibraryRepository::class), $container->get(WorkDevelopmentRepository::class), $container->get(WorkChapterPreparationRepository::class)));
        $this->container->set(WorkChapterResearchController::class, static fn (Container $container): WorkChapterResearchController => new WorkChapterResearchController($container->get(Config::class), $container->get(ResponseFactory::class), $container->get(Capabilities::class), $container->get(LibraryRepository::class), $container->get(WorkDevelopmentRepository::class), $container->get(WorkChapterResearchRepository::class)));
        $this->container->set(WorkChapterWritingController::class, static fn (Container $container): WorkChapterWritingController => new WorkChapterWritingController($container->get(Config::class), $container->get(ResponseFactory::class), $container->get(Capabilities::class), $container->get(LibraryRepository::class), $container->get(WorkDevelopmentRepository::class), $container->get(WorkChapterWritingRepository::class)));
        $this->container->set(WorkChapterRevisionController::class, static fn (Container $container): WorkChapterRevisionController => new WorkChapterRevisionController($container->get(Config::class), $container->get(ResponseFactory::class), $container->get(Capabilities::class), $container->get(LibraryRepository::class), $container->get(WorkDevelopmentRepository::class), $container->get(WorkChapterRevisionRepository::class)));
        $this->container->set(WorkGeneralReviewController::class, static fn (Container $container): WorkGeneralReviewController => new WorkGeneralReviewController($container->get(Config::class), $container->get(ResponseFactory::class), $container->get(Capabilities::class), $container->get(LibraryRepository::class), $container->get(WorkGeneralReviewRepository::class)));
        $this->container->set(FrontendAssets::class, static fn (): FrontendAssets => new FrontendAssets());
        $this->container->set(SupabaseConfig::class, static fn (Container $container): SupabaseConfig => new SupabaseConfig($container->get(Config::class)));
        $this->container->set(SupabaseClient::class, static fn (Container $container): SupabaseClient => new SupabaseClient($container->get(SupabaseConfig::class)));
        $this->container->set(ElementorIntegration::class, static fn (): ElementorIntegration => new ElementorIntegration());
    }
}
