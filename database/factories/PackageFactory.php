<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    protected $model = Package::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'slug' => $this->faker->unique()->slug(),
            'name' => ['ar' => 'باقة '.$name, 'en' => ucfirst($name)],
            'description' => ['ar' => 'وصف الباقة', 'en' => $this->faker->sentence()],
            'price' => $this->faker->randomFloat(2, 1500, 60000),
            'currency' => 'SAR',
            // A list of per-locale maps, not a per-locale list — see the
            // Package docblock for why.
            'features' => [
                ['ar' => 'تصميم شعار', 'en' => 'Logo design'],
                ['ar' => 'دليل الهوية', 'en' => 'Brand guidelines'],
                ['ar' => 'ملفات مفتوحة المصدر', 'en' => 'Source files'],
            ],
            'is_popular' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function popular(): static
    {
        return $this->state(fn () => ['is_popular' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * NULL price means "on request" — distinct from a price of 0.00, which
     * would mean the package is free.
     */
    public function priceOnRequest(): static
    {
        return $this->state(fn () => ['price' => null]);
    }
}
