<?php
declare(strict_types=1);
namespace VerbumStudio\Exceptions;
final class NotFoundError extends VerbumException { protected string $errorCode='not_found'; protected int $status=404; }
