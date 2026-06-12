<?php

namespace App\Services\Billing;

use Akaunting\Module\Facade as Module;

class CreditActionRegistry
{
    /**
     * @return array<int, array{action: string, name: string, category: string, default_cost: int, help: string, module: string, sort_order: int}>
     */
    public function definitions(): array
    {
        $byAction = [];

        foreach (config('credit-actions.actions', []) as $definition) {
            $byAction[$definition['action']] = $this->normalizeDefinition($definition);
        }

        foreach (Module::all() as $module) {
            $actions = $module->get('cost_per_action');
            if (! is_array($actions)) {
                continue;
            }

            foreach ($actions as $definition) {
                if (! isset($definition['action'])) {
                    continue;
                }

                $action = $definition['action'];
                $byAction[$action] = $this->normalizeDefinition(array_merge(
                    $byAction[$action] ?? [],
                    $definition,
                    ['module' => $module->get('alias') ?? 'unknown'],
                ));
            }
        }

        uasort($byAction, fn (array $a, array $b) => ($a['sort_order'] <=> $b['sort_order']) ?: strcmp($a['name'], $b['name']));

        return array_values($byAction);
    }

    public function hasAction(string $action): bool
    {
        foreach ($this->definitions() as $definition) {
            if ($definition['action'] === $action) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{action: string, name: string, category: string, default_cost: int, help: string, module: string, sort_order: int}|null
     */
    public function find(string $action): ?array
    {
        foreach ($this->definitions() as $definition) {
            if ($definition['action'] === $action) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array{action: string, name: string, category: string, default_cost: int, help: string, module: string, sort_order: int}
     */
    private function normalizeDefinition(array $definition): array
    {
        return [
            'action' => (string) $definition['action'],
            'name' => (string) ($definition['name'] ?? $definition['action']),
            'category' => (string) ($definition['category'] ?? 'other'),
            'default_cost' => (int) ($definition['default_cost'] ?? $definition['cost'] ?? 1),
            'help' => (string) ($definition['help'] ?? ''),
            'module' => (string) ($definition['module'] ?? 'core'),
            'sort_order' => (int) ($definition['sort_order'] ?? 100),
        ];
    }
}
