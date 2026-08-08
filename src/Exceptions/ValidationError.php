<?php
declare(strict_types=1);
namespace VerbumStudio\Exceptions;
final class ValidationError extends VerbumException { protected string $errorCode='validation_error'; protected int $status=400; }
