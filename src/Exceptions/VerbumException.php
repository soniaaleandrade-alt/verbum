<?php
declare(strict_types=1);
namespace VerbumStudio\Exceptions;
abstract class VerbumException extends \RuntimeException { protected string $errorCode='internal_error'; protected int $status=500; public function errorCode():string{return $this->errorCode;} public function status():int{return $this->status;} }
