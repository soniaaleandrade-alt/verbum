<?php
declare(strict_types=1);
namespace VerbumStudio\Exceptions;
final class InternalError extends VerbumException { protected string $errorCode='internal_error'; protected int $status=500; }
