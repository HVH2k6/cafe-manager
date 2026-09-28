<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $module = fake()->randomElement(array_keys(Permission::MODULE_LABELS));
        $action = fake()->unique()->lexify('action_????');

        return [
            'name' => "Quyền {$module} {$action}",
            'module' => $module,
            'action' => $action,
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * A permission with the given key, e.g. "orders.view".
     */
    public function key(string $key): static
    {
        [$module, $action] = explode('.', $key, 2);

        return $this->state(fn (array $attributes) => [
            'name' => "Quyền {$key}",
            'module' => $module,
            'action' => $action,
        ]);
    }

    /**
     * Indicate that the permission is switched off.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
