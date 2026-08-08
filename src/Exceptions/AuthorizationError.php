<?php
declare(strict_types=1);
namespace VerbumStudio\Exceptions;
final class AuthorizationError extends VerbumException { protected string $errorCode='forbidden'; protected int $status=403; }
