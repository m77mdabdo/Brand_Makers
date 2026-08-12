<?php

namespace Tests\Unit;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The client portal has exactly one secret, and this is it. If a tracking
 * code is guessable, recoverable from the database, or accepted in a
 * weaker form than it was issued in, then every client's project status is
 * readable by anyone.
 */
class TrackingCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_code_is_issued_once_and_never_stored_in_plaintext(): void
    {
        $order = Order::factory()->create();
        $code = $order->trackingCode();

        $this->assertNotNull($code);
        $this->assertSame(64, strlen($code), '32 bytes of entropy, hex-encoded.');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $code);

        // The plaintext appears in no column of the row.
        $row = (array) DB::table('orders')->where('id', $order->id)->first();

        foreach ($row as $column => $value) {
            $this->assertNotSame($code, (string) $value, "The plaintext code leaked into orders.{$column}.");
        }

        $this->assertSame(hash('sha256', $code), $order->fresh()->tracking_code_hash);
    }

    public function test_a_model_read_back_from_the_database_cannot_reveal_the_code(): void
    {
        $order = Order::factory()->create();

        $this->assertNotNull($order->trackingCode());
        $this->assertNull(
            Order::find($order->id)->trackingCode(),
            'A code recoverable after the fact would defeat storing only the hash.'
        );
    }

    public function test_codes_are_unguessable_and_never_repeat(): void
    {
        $codes = [];

        for ($i = 0; $i < 200; $i++) {
            $codes[] = Order::factory()->create()->trackingCode();
        }

        $this->assertCount(200, array_unique($codes), 'Two orders sharing a code would open each other.');

        // A CSPRNG produces roughly uniform hex. A generator that fell back
        // to something seeded or sequential would show a collapsed
        // character set or a shared prefix, and both are caught here.
        $characters = count_chars(implode('', $codes), 3);
        $this->assertSame(16, strlen($characters), 'All 16 hex characters should appear across 200 codes.');

        $prefixes = array_unique(array_map(fn ($code) => substr($code, 0, 8), $codes));
        $this->assertCount(200, $prefixes, 'Shared prefixes would mean the codes are not independently random.');
    }

    public function test_lookup_resolves_a_valid_code_and_rejects_everything_else(): void
    {
        $order = Order::factory()->create();
        $code = $order->trackingCode();

        $this->assertTrue(Order::findByTrackingCode($code)->is($order));

        // Case-insensitive: the code is hex, and a client retyping it from
        // an email should not be defeated by capitalisation.
        $this->assertTrue(Order::findByTrackingCode(strtoupper($code))->is($order));
        $this->assertTrue(Order::findByTrackingCode(' '.$code.' ')->is($order));

        $this->assertNull(Order::findByTrackingCode(null));
        $this->assertNull(Order::findByTrackingCode(''));
        $this->assertNull(Order::findByTrackingCode('short'));
        $this->assertNull(Order::findByTrackingCode(str_repeat('z', 64)), 'Non-hex is not a code.');
        $this->assertNull(Order::findByTrackingCode(substr($code, 0, 63)), 'A truncated code must not match.');
        $this->assertNull(Order::findByTrackingCode($order->tracking_code_hash), 'The hash is not the code.');
    }

    public function test_the_reference_is_not_a_credential(): void
    {
        $order = Order::factory()->create();

        $this->assertNull(
            Order::findByTrackingCode($order->reference),
            'The reference is quoted on invoices and read aloud; if it opened an order the portal would be enumerable.'
        );
    }

    public function test_a_malformed_code_is_rejected_without_touching_the_database(): void
    {
        Order::factory()->create();

        DB::enableQueryLog();
        Order::findByTrackingCode('obviously-not-a-code');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $queries, 'A lookup that cannot possibly match should not cost a query.');
    }

    public function test_a_valid_code_costs_exactly_one_indexed_query(): void
    {
        $order = Order::factory()->create();

        DB::enableQueryLog();
        Order::findByTrackingCode($order->trackingCode());
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(1, $queries);
        $this->assertStringContainsString('tracking_code_hash', $queries[0]['query']);
        $this->assertStringNotContainsString('like', strtolower($queries[0]['query']), 'A scan would leak timing.');
    }

    public function test_reissuing_a_code_invalidates_the_previous_one(): void
    {
        $order = Order::factory()->create();
        $leaked = $order->trackingCode();

        $replacement = $order->issueTrackingCode();
        $order->save();

        $this->assertNotSame($leaked, $replacement);
        $this->assertNull(Order::findByTrackingCode($leaked), 'Revoking a leaked link is the point of reissuing.');
        $this->assertTrue(Order::findByTrackingCode($replacement)->is($order));
    }

    public function test_references_are_unique_and_carry_no_ambiguous_characters(): void
    {
        $references = [];

        for ($i = 0; $i < 50; $i++) {
            $references[] = Order::factory()->create()->reference;
        }

        $this->assertCount(50, array_unique($references));

        foreach ($references as $reference) {
            $this->assertMatchesRegularExpression('/^CM-\d{4}-[2-9A-HJ-NP-Z]{6}$/', $reference);
            // I, O, 0 and 1 are the pairs people mistype when reading a
            // reference off an invoice.
            $this->assertDoesNotMatchRegularExpression('/[IO01]/', substr($reference, 8));
        }
    }
}
