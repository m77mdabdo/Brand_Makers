<?php

namespace Tests\Unit;

use App\Models\Comment;
use App\Models\VisitorSession;
use App\Services\Analytics\IpAnonymizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IpAnonymizerTest extends TestCase
{
    use RefreshDatabase;

    private IpAnonymizer $anonymizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->anonymizer = new IpAnonymizer;
    }

    /**
     * @return array<string, array{?string, ?string}>
     */
    public static function addresses(): array
    {
        return [
            'ipv4 drops the last octet' => ['203.0.113.42', '203.0.113.0'],
            'ipv4 already truncated' => ['203.0.113.0', '203.0.113.0'],
            'ipv4 boundary' => ['255.255.255.255', '255.255.255.0'],
            'ipv6 keeps 48 bits' => ['2001:0db8:1234:5678:9abc:def0:1234:5678', '2001:db8:1234::'],
            'ipv6 compressed notation normalises the same way' => ['2001:db8:1234::1', '2001:db8:1234::'],
            'ipv6 loopback' => ['::1', '::'],
            'null stays null' => [null, null],
            'empty stays null' => ['', null],
            'whitespace stays null' => ['   ', null],
            'a spoofed header is dropped' => ['not-an-ip', null],
            'the literal "unknown" some proxies send is dropped' => ['unknown', null],
            'a path is dropped' => ['/var/run/socket', null],
        ];
    }

    #[DataProvider('addresses')]
    public function test_it_truncates_or_drops(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, $this->anonymizer->anonymize($input));
    }

    public function test_two_addresses_in_the_same_subnet_collapse_to_one_prefix(): void
    {
        // This is the property the column exists for: noticing that many
        // "visitors" came from one office.
        $this->assertSame(
            $this->anonymizer->anonymize('198.51.100.7'),
            $this->anonymizer->anonymize('198.51.100.204')
        );
    }

    public function test_different_subnets_stay_distinguishable(): void
    {
        $this->assertNotSame(
            $this->anonymizer->anonymize('198.51.100.7'),
            $this->anonymizer->anonymize('198.51.101.7')
        );
    }

    /**
     * The point of putting this in a model mutator rather than at the call
     * site: there is no code path — controller, service, seeder, factory,
     * tinker — that can store a raw address by forgetting a step.
     */
    public function test_a_visitor_session_cannot_store_a_raw_address_even_when_handed_one(): void
    {
        $session = VisitorSession::factory()->create(['ip_prefix' => '198.51.100.204']);

        $this->assertSame('198.51.100.0', $session->ip_prefix);
        $this->assertSame('198.51.100.0', $session->fresh()->ip_prefix);
        $this->assertDatabaseMissing('visitor_sessions', ['ip_prefix' => '198.51.100.204']);
    }

    public function test_a_comment_cannot_store_a_raw_address_either(): void
    {
        $comment = Comment::factory()->create(['ip_address' => '2001:db8:1234:5678::1']);

        $this->assertSame('2001:db8:1234::', $comment->fresh()->ip_address);
    }

    public function test_direct_assignment_is_anonymised_too_not_just_mass_assignment(): void
    {
        $session = VisitorSession::factory()->create();
        $session->ip_prefix = '203.0.113.99';
        $session->save();

        $this->assertSame('203.0.113.0', $session->fresh()->ip_prefix);
    }
}
