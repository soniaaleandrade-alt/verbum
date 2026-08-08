<?php
declare(strict_types=1);
namespace VerbumStudio\Exceptions;
final class IntegrationError extends VerbumException { protected string $errorCode='integration_error'; protected int $status=502; }
