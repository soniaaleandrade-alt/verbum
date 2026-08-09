<?php
declare(strict_types=1);

namespace VerbumStudio\Core;

final class Container
{
    /** @var array<string, callable|object> */
    private array $entries = [];

    public function set(string $id, $entry): void
    {
        $this->entries[$id] = $entry;
    }

    public function get(string $id)
    {
        if (! isset($this->entries[$id])) {
            throw new \InvalidArgumentException("Service not registered: {$id}");
        }
        if (is_callable($this->entries[$id])) {
            $this->entries[$id] = $this->entries[$id]($this);
        }
        return $this->entries[$id];
    }
}
