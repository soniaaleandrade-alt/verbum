<?php
declare(strict_types=1);
namespace VerbumStudio\Exceptions;
final class AuthenticationError extends VerbumException { protected string $errorCode='unauthorized'; protected int $status=401; }
