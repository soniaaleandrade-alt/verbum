<?php

declare(strict_types=1);

namespace VerbumStudio\Core;

use VerbumStudio\Api\AuthController;
use VerbumStudio\Api\FoundationIntentionController;
use VerbumStudio\Api\FoundationLetterSoulController;
use VerbumStudio\Api\FoundationReaderResultController;
use VerbumStudio\Api\IdentificationInitialController;
use VerbumStudio\Api\LibraryController;
use VerbumStudio\Api\ResponseFactory;
use VerbumStudio\Api\RestController;
use VerbumStudio\Api\WorkAuditController;
use VerbumStudio\Api\WorkChapterPreparationController;
use VerbumStudio\Api\WorkChapterResearchController;
use VerbumStudio\Api\WorkChapterRevisionController;
use VerbumStudio\Api\WorkChapterWritingController;
use VerbumStudio\Api\WorkDevelopmentController;
use VerbumStudio\Api\WorkEditorialDeskController;
use VerbumStudio\Api\WorkGeneralReviewController;
use VerbumStudio\Api\WorkLayoutController;
use VerbumStudio\Api\WorkLegalController;
use VerbumStudio\Api\WorkPlanningController;
use VerbumStudio\Api\WorkProjectController;
use VerbumStudio\Api\WorkPublicationController;
use VerbumStudio\Api\WorkVersionsController;
use VerbumStudio\Auth\Capabilities;
use VerbumStudio\Integrations\Elementor\ElementorIntegration;
use VerbumStudio\Integrations\Supabase\SupabaseClient;
use VerbumStudio\Integrations\Supabase\SupabaseConfig;
use VerbumStudio\Library\FoundationLetterSoulRepository;
use VerbumStudio\Library\FoundationIntentionRepository;
use VerbumStudio\Library\FoundationReaderResultRepository;
use VerbumStudio\Library\LibraryPostTypes;
use VerbumStudio\Library\LibraryRepository;
use VerbumStudio\Library\WorkAuditRepository;
use VerbumStudio\Library\WorkChapterPreparationRepository;
use VerbumStudio\Library\WorkChapterResearchRepository;
use VerbumStudio\Library\WorkChapterRevisionRepository;
use VerbumStudio\Library\WorkChapterWritingRepository;
use VerbumStudio\Library\WorkDevelopmentRepository;
use VerbumStudio\Library\WorkEditorialDeskRepository;
use VerbumStudio\Library\WorkGeneralReviewRepository;
use VerbumStudio\Library\WorkLayoutRepository;
use VerbumStudio\Library\WorkLegalRepository;
use VerbumStudio\Library\WorkPlanningRepository;
use VerbumStudio\Library\WorkProjectRepository;
use VerbumStudio\Library\WorkPublicationRepository;
use VerbumStudio\Library\WorkVersionsRepository;
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
        foreach ([
            RestController::class, AuthController::class, LibraryController::class, IdentificationInitialController::class, FoundationLetterSoulController::class, FoundationIntentionController::class, FoundationReaderResultController::class, WorkProjectController::class,
            WorkPlanningController::class, WorkDevelopmentController::class, WorkChapterPreparationController::class,
            WorkChapterResearchController::class, WorkChapterWritingController::class, WorkChapterRevisionController::class,
            WorkGeneralReviewController::class, WorkVersionsController::class, WorkAuditController::class,
            WorkEditorialDeskController::class, WorkLayoutController::class, WorkLegalController::class,
            WorkPublicationController::class, FrontendAssets::class,
        ] as $service) $this->container->get($service)->register();

        add_action('init', function (): void {
            $this->container->get(LibraryPostTypes::class)->register();
            $this->container->get(Capabilities::class)->add();
        });

        add_action('admin_init', function (): void {
            $capabilities = $this->container->get(Capabilities::class);
            $doingAjax = function_exists('wp_doing_ajax') && wp_doing_ajax();
            if ($capabilities->currentUserCanAccess() && ! $capabilities->currentUserIsAdmin() && ! $doingAjax) { wp_safe_redirect(home_url('/')); exit; }
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

    public function container(): Container { return $this->container; }

    private function registerServices(): void
    {
        $this->container->set(Config::class, $this->config);
        $this->container->set(Logger::class, static fn (): Logger => new Logger());
        $this->container->set(Capabilities::class, static fn (): Capabilities => new Capabilities());
        $this->container->set(ResponseFactory::class, static fn (Container $c): ResponseFactory => new ResponseFactory($c->get(Config::class)));
        $this->container->set(RestController::class, static fn (Container $c): RestController => new RestController($c->get(Config::class), $c->get(ResponseFactory::class), $c->get(Capabilities::class)));
        $this->container->set(AuthController::class, static fn (Container $c): AuthController => new AuthController($c->get(Config::class), $c->get(ResponseFactory::class), $c->get(Capabilities::class)));
        $this->container->set(LibraryPostTypes::class, static fn (): LibraryPostTypes => new LibraryPostTypes());
        $this->container->set(LibraryRepository::class, static fn (): LibraryRepository => new LibraryRepository());
        $this->container->set(FoundationLetterSoulRepository::class, static fn (): FoundationLetterSoulRepository => new FoundationLetterSoulRepository());
        $this->container->set(FoundationIntentionRepository::class, static fn (): FoundationIntentionRepository => new FoundationIntentionRepository());
        $this->container->set(FoundationReaderResultRepository::class, static fn (): FoundationReaderResultRepository => new FoundationReaderResultRepository());
        $this->container->set(WorkProjectRepository::class, static fn (): WorkProjectRepository => new WorkProjectRepository());
        $this->container->set(WorkPlanningRepository::class, static fn (): WorkPlanningRepository => new WorkPlanningRepository());
        $this->container->set(WorkDevelopmentRepository::class, static fn (): WorkDevelopmentRepository => new WorkDevelopmentRepository());
        $this->container->set(WorkChapterPreparationRepository::class, static fn (): WorkChapterPreparationRepository => new WorkChapterPreparationRepository());
        $this->container->set(WorkChapterResearchRepository::class, static fn (): WorkChapterResearchRepository => new WorkChapterResearchRepository());
        $this->container->set(WorkChapterWritingRepository::class, static fn (): WorkChapterWritingRepository => new WorkChapterWritingRepository());
        $this->container->set(WorkChapterRevisionRepository::class, static fn (): WorkChapterRevisionRepository => new WorkChapterRevisionRepository());
        $this->container->set(WorkGeneralReviewRepository::class, static fn (): WorkGeneralReviewRepository => new WorkGeneralReviewRepository());
        $this->container->set(WorkVersionsRepository::class, static fn (): WorkVersionsRepository => new WorkVersionsRepository());
        $this->container->set(WorkAuditRepository::class, static fn (): WorkAuditRepository => new WorkAuditRepository());
        $this->container->set(WorkEditorialDeskRepository::class, static fn (): WorkEditorialDeskRepository => new WorkEditorialDeskRepository());
        $this->container->set(WorkLayoutRepository::class, static fn (): WorkLayoutRepository => new WorkLayoutRepository());
        $this->container->set(WorkLegalRepository::class, static fn (): WorkLegalRepository => new WorkLegalRepository());
        $this->container->set(WorkPublicationRepository::class, static fn (): WorkPublicationRepository => new WorkPublicationRepository());

        $this->container->set(LibraryController::class, static fn (Container $c): LibraryController => new LibraryController($c->get(Config::class), $c->get(ResponseFactory::class), $c->get(Capabilities::class), $c->get(LibraryRepository::class)));
        $this->container->set(IdentificationInitialController::class, static fn (Container $c): IdentificationInitialController => new IdentificationInitialController($c->get(Config::class), $c->get(ResponseFactory::class), $c->get(Capabilities::class), $c->get(LibraryRepository::class)));
        $this->container->set(FoundationLetterSoulController::class, static fn (Container $c): FoundationLetterSoulController => new FoundationLetterSoulController($c->get(Config::class), $c->get(ResponseFactory::class), $c->get(Capabilities::class), $c->get(LibraryRepository::class), $c->get(FoundationLetterSoulRepository::class)));
        $this->container->set(FoundationIntentionController::class, static fn (Container $c): FoundationIntentionController => new FoundationIntentionController($c->get(Config::class), $c->get(ResponseFactory::class), $c->get(Capabilities::class), $c->get(LibraryRepository::class), $c->get(FoundationIntentionRepository::class), $c->get(FoundationLetterSoulRepository::class)));
        $this->container->set(FoundationReaderResultController::class, static fn (Container $c): FoundationReaderResultController => new FoundationReaderResultController($c->get(Config::class), $c->get(ResponseFactory::class), $c->get(Capabilities::class), $c->get(LibraryRepository::class), $c->get(FoundationReaderResultRepository::class), $c->get(FoundationLetterSoulRepository::class), $c->get(FoundationIntentionRepository::class)));
        $this->container->set(WorkProjectController::class, static fn (Container $c): WorkProjectController => new WorkProjectController($c->get(Config::class), $c->get(ResponseFactory::class), $c->get(Capabilities::class), $c->get(LibraryRepository::class), $c->get(WorkProjectRepository::class)));
        $this->container->set(WorkPlanningController::class, static fn (Container $c): WorkPlanningController => new WorkPlanningController($c->get(Config::class), $c->get(ResponseFactory::class), $c->get(Capabilities::class), $c->get(LibraryRepository::class), $c->get(WorkPlanningRepository::class)));
        $this->container->set(WorkDevelopmentController::class, static fn (Container $c): WorkDevelopmentController => new WorkDevelopmentController($c->get(Config::class), $c->get(ResponseFactory::class), $c->get(Capabilities::class), $c->get(LibraryRepository::class), $c->get(WorkDevelopmentRepository::class)));
        $this->container->set(WorkChapterPreparationController::class, static fn (Container $c): WorkChapterPreparationController => new WorkChapterPreparationController($c->get(Config::class), $c->get(ResponseFactory::class), $c->get(Capabilities::class), $c->get(LibraryRepository::class), $c->get(WorkDevelopmentRepository::class), $c->get(WorkChapterPreparationRepository::class)));
        $this->container->set(WorkChapterResearchController::class, static fn (Container $c): WorkChapterResearchController => new WorkChapterResearchController($c->get(Config::class), $c->get(ResponseFactory::class), $c->get(Capabilities::class), $c->get(LibraryRepository::class), $c->get(WorkDevelopmentRepository::class), $c->get(WorkChapterResearchRepository::class)));
        $this->container->set(WorkChapterWritingController::class, static fn (Container $c): WorkChapterWritingController => new WorkChapterWritingController($c->get(Config::class), $c->get(ResponseFactory::class), $c->get(Capabilities::class), $c->get(LibraryRepository::class), $c->get(WorkDevelopmentRepository::class), $c->get(WorkChapterWritingRepository::class)));
        $this->container->set(WorkChapterRevisionController::class, static fn (Container $c): WorkChapterRevisionController => new WorkChapterRevisionController($c->get(Config::class), $c->get(ResponseFactory::class), $c->get(Capabilities::class), $c->get(LibraryRepository::class), $c->get(WorkDevelopmentRepository::class), $c->get(WorkChapterRevisionRepository::class)));
        $this->container->set(WorkGeneralReviewController::class, static fn (Container $c): WorkGeneralReviewController => new WorkGeneralReviewController($c->get(Config::class), $c->get(ResponseFactory::class), $c->get(Capabilities::class), $c->get(LibraryRepository::class), $c->get(WorkGeneralReviewRepository::class)));
        $this->container->set(WorkVersionsController::class, static fn (Container $c): WorkVersionsController => new WorkVersionsController($c->get(Config::class), $c->get(ResponseFactory::class), $c->get(Capabilities::class), $c->get(LibraryRepository::class), $c->get(WorkVersionsRepository::class)));
        $this->container->set(WorkAuditController::class, static fn (Container $c): WorkAuditController => new WorkAuditController($c->get(Config::class), $c->get(ResponseFactory::class), $c->get(Capabilities::class), $c->get(LibraryRepository::class), $c->get(WorkAuditRepository::class)));
        $this->container->set(WorkEditorialDeskController::class, static fn (Container $c): WorkEditorialDeskController => new WorkEditorialDeskController($c->get(Config::class), $c->get(ResponseFactory::class), $c->get(Capabilities::class), $c->get(LibraryRepository::class), $c->get(WorkEditorialDeskRepository::class)));
        $this->container->set(WorkLayoutController::class, static fn (Container $c): WorkLayoutController => new WorkLayoutController($c->get(Config::class), $c->get(ResponseFactory::class), $c->get(Capabilities::class), $c->get(LibraryRepository::class), $c->get(WorkLayoutRepository::class)));
        $this->container->set(WorkLegalController::class, static fn (Container $c): WorkLegalController => new WorkLegalController($c->get(Config::class), $c->get(ResponseFactory::class), $c->get(Capabilities::class), $c->get(LibraryRepository::class), $c->get(WorkLegalRepository::class)));
        $this->container->set(WorkPublicationController::class, static fn (Container $c): WorkPublicationController => new WorkPublicationController($c->get(Config::class), $c->get(ResponseFactory::class), $c->get(Capabilities::class), $c->get(LibraryRepository::class), $c->get(WorkPublicationRepository::class)));
        $this->container->set(FrontendAssets::class, static fn (): FrontendAssets => new FrontendAssets());
        $this->container->set(SupabaseConfig::class, static fn (Container $c) => new SupabaseConfig($c->get(Config::class)));
        $this->container->set(SupabaseClient::class, static fn (Container $c) => new SupabaseClient($c->get(SupabaseConfig::class)));
        $this->container->set(ElementorIntegration::class, static fn (): ElementorIntegration => new ElementorIntegration());
    }
}
