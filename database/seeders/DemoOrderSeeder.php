<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\Order;
use App\Models\Package;
use Illuminate\Database\Seeder;

/**
 * FABRICATED client-portal orders and their status timelines. Loaded only
 * via DemoDataSeeder.
 *
 * NOTE ON TRACKING CODES. Each order still mints a real code from
 * random_bytes() and stores only its hash, exactly as production does —
 * the demo seeder does not get a shortcut, because a shortcut here would
 * mean the code path that has to be unguessable is never the one being
 * exercised in development.
 *
 * The plaintext codes are printed to the console once, at seed time, since
 * that is the only moment they exist. They cannot be recovered afterwards,
 * from the database or from anywhere else. Re-run this seeder against a
 * fresh database if you need them again.
 *
 * Idempotent on `reference`… which is impossible, because references are
 * random. So it keys on the sample client email instead, and skips an
 * order that already exists — which also means re-running does not
 * silently rotate a code someone is using.
 */
class DemoOrderSeeder extends Seeder
{
    public function run(): void
    {
        $packages = Package::orderBy('sort_order')->get();

        if ($packages->isEmpty()) {
            $this->command?->warn('No packages found — run the production seed first.');

            return;
        }

        $issued = [];

        foreach ($this->orders() as $index => $definition) {
            $existing = Order::where('client_email', $definition['email'])->first();

            if ($existing) {
                continue;
            }

            $order = Order::create([
                'package_id' => $packages[$index % $packages->count()]->id,
                'lead_id' => Lead::where('email', $definition['email'])->value('id'),
                'client_name' => $definition['name'].' '.DemoDataSeeder::SAMPLE_MARKER,
                'client_email' => $definition['email'],
                'client_phone' => '+96650000'.str_pad((string) (2000 + $index), 4, '0', STR_PAD_LEFT),
                'total' => $definition['total'],
                'currency' => 'SAR',
                'notes' => 'بيانات عينة — طلب تجريبي. '.DemoDataSeeder::SAMPLE_MARKER,
                'placed_at' => now()->subDays($definition['placed_days_ago']),
            ]);

            // The plaintext exists only on this in-memory instance.
            $issued[$order->reference] = $order->trackingCode();

            foreach ($definition['timeline'] as $step) {
                $order->transitionTo($step['status'], $step['note'], $step['progress'] ?? null);
            }
        }

        $this->reportIssuedCodes($issued);
    }

    /**
     * @param  array<string, string|null>  $issued
     */
    private function reportIssuedCodes(array $issued): void
    {
        if ($issued === [] || $this->command === null) {
            return;
        }

        $this->command->newLine();
        $this->command->warn('Sample order tracking codes — shown ONCE, only the hash is stored:');

        foreach ($issued as $reference => $code) {
            $this->command->line("  {$reference}  {$code}");
        }

        $this->command->newLine();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function orders(): array
    {
        return [
            [
                'name' => 'نورة العتيبي',
                'email' => 'noura.sample@example.test',
                'total' => 38000.00,
                'placed_days_ago' => 18,
                'timeline' => [
                    ['status' => Order::STATUS_PENDING, 'progress' => 0, 'note' => ['ar' => 'تم استلام الطلب. '.DemoDataSeeder::SAMPLE_MARKER, 'en' => 'Order received. '.DemoDataSeeder::SAMPLE_MARKER]],
                    ['status' => Order::STATUS_IN_PROGRESS, 'progress' => 35, 'note' => ['ar' => 'انتهت جلسة الاكتشاف، بدأ العمل على الاستراتيجية.', 'en' => 'Discovery session complete, strategy work under way.']],
                    ['status' => Order::STATUS_IN_REVIEW, 'progress' => 70, 'note' => ['ar' => 'الاتجاهات البصرية جاهزة للمراجعة.', 'en' => 'Visual directions ready for review.']],
                ],
            ],
            [
                'name' => 'فيصل الدوسري',
                'email' => 'faisal.sample@example.test',
                'total' => 21500.00,
                'placed_days_ago' => 40,
                'timeline' => [
                    ['status' => Order::STATUS_PENDING, 'progress' => 0, 'note' => ['ar' => 'تم استلام الطلب. '.DemoDataSeeder::SAMPLE_MARKER, 'en' => 'Order received. '.DemoDataSeeder::SAMPLE_MARKER]],
                    ['status' => Order::STATUS_IN_PROGRESS, 'progress' => 60, 'note' => ['ar' => 'تطوير الاتجاه المعتمد.', 'en' => 'Developing the approved direction.']],
                    ['status' => Order::STATUS_COMPLETED, 'note' => ['ar' => 'تم تسليم السجل التجاري وكامل التراخيص.', 'en' => 'Commercial Registration and all licences delivered.']],
                ],
            ],
            [
                'name' => 'هيا الزهراني',
                'email' => 'haya.sample@example.test',
                'total' => 9800.00,
                'placed_days_ago' => 3,
                'timeline' => [
                    ['status' => Order::STATUS_PENDING, 'progress' => 0, 'note' => ['ar' => 'تم استلام الطلب، بانتظار تأكيد النطاق. '.DemoDataSeeder::SAMPLE_MARKER, 'en' => 'Order received, awaiting scope confirmation. '.DemoDataSeeder::SAMPLE_MARKER]],
                ],
            ],
        ];
    }
}
