<?php

namespace Database\Factories;

use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    public function definition(): array
    {
        $label = $this->faker->unique()->words(2, true);

        return [
            'parent_id' => null,
            // Null for admin-created items, which is what the unique index
            // on `key` is designed to allow any number of.
            'key' => null,
            'label' => ['ar' => 'عنصر '.$label, 'en' => ucfirst($label)],
            'link_type' => 'route',
            'route_name' => 'home',
            'url' => null,
            'target' => '_self',
            'is_visible' => true,
            'sort_order' => 0,
        ];
    }

    public function route(string $name): static
    {
        return $this->state(fn () => ['link_type' => 'route', 'route_name' => $name, 'url' => null]);
    }

    public function url(string $url, string $target = '_self'): static
    {
        return $this->state(fn () => ['link_type' => 'url', 'url' => $url, 'route_name' => null, 'target' => $target]);
    }

    /**
     * A parent that only opens a dropdown and has no destination itself.
     */
    public function dropdownParent(): static
    {
        return $this->state(fn () => ['link_type' => 'none', 'route_name' => null, 'url' => null]);
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['is_visible' => false]);
    }

    /**
     * is_system is guarded, so it is assigned rather than filled — the
     * same route real code has to take.
     */
    public function system(string $key): static
    {
        return $this->state(fn () => ['key' => $key])
            ->afterMaking(fn (MenuItem $item) => $item->is_system = true)
            ->afterCreating(function (MenuItem $item) {
                $item->is_system = true;
                $item->save();
            });
    }
}
