<?php

namespace App\ImportExport;

use App\ImportExport\Contracts\ImportDefinition;
use App\ImportExport\Enums\ImportMode;
use InvalidArgumentException;

/**
 * Resolves a stable entity key (e.g. "customers") to its ImportDefinition.
 * The mapping lives in config/import-export.php so new entities are added
 * without touching application code.
 */
class ImportRegistry
{
    /** @var array<string, ImportDefinition> */
    private array $definitions = [];

    public function has(string $key): bool
    {
        return $this->resolveClass($key) !== null;
    }

    public function definitionFor(string $key): ImportDefinition
    {
        if (isset($this->definitions[$key])) {
            return $this->definitions[$key];
        }

        $class = $this->resolveClass($key);
        if ($class === null) {
            throw new InvalidArgumentException("Unknown import/export entity '{$key}'.");
        }

        $definition = app($class);

        if (! $definition instanceof ImportDefinition) {
            throw new InvalidArgumentException("Entity '{$key}' does not implement ".ImportDefinition::class.'.');
        }

        return $this->definitions[$key] = $definition;
    }

    /**
     * Resolve a definition by its class name instead of its registry key. Used
     * for private reference entities (e.g. Vehicles, Employees) that are only
     * needed as relationship targets and are never top-level entities in the
     * hub — so their key must not leak into URLs or the UI.
     */
    public function definitionForClass(string $class): ImportDefinition
    {
        foreach (array_keys(config('import-export.entities', [])) as $key) {
            if (config("import-export.entities.{$key}") === $class) {
                return $this->definitionFor($key);
            }
        }

        if (! is_string($class) || ! class_exists($class)) {
            throw new InvalidArgumentException("Unknown related entity '{$class}'.");
        }

        $definition = app($class);

        if (! $definition instanceof ImportDefinition) {
            throw new InvalidArgumentException("Entity '{$class}' does not implement ".ImportDefinition::class.'.');
        }

        return $this->definitions[$class] = $definition;
    }

    /**
     * @return array<int, array{key: string, label: string, description: ?string}>
     */
    public function available(): array
    {
        $entities = config('import-export.entities', []);

        $result = [];
        foreach (array_keys($entities) as $key) {
            $definition = $this->definitionFor($key);
            $result[] = [
                'key' => $key,
                'label' => $definition->label(),
                'description' => $definition->description(),
            ];
        }

        return $result;
    }

    /**
     * Per-entity detail the hub renders to explain the import format without
     * making the user guess: required columns, matching keys and modes.
     *
     * @return array<string, mixed>
     */
    public function details(string $key): array
    {
        $definition = $this->definitionFor($key);

        return [
            'key' => $key,
            'label' => $definition->label(),
            'description' => $definition->description(),
            'required_columns' => collect($definition->columns())
                ->filter(fn (array $spec) => ! empty($spec['required']))
                ->pluck('header')
                ->values()
                ->all(),
            'import_keys' => $definition->importKeys(),
            'mode_descriptions' => array_map(
                fn (ImportMode $mode) => ['value' => $mode->value, 'label' => $mode->label(), 'description' => $mode->description()],
                ImportMode::cases(),
            ),
        ];
    }

    private function resolveClass(string $key): ?string
    {
        $class = config("import-export.entities.{$key}");

        return is_string($class) && class_exists($class) ? $class : null;
    }
}