<?php

declare(strict_types=1);

use Jax\IPAddress;
use Jax\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\UnitTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class IPAddressTest extends UnitTestCase
{
    public const TESTIP = '192.168.1.1';

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testBinaryConversions(): void
    {
        $ipAddress = $this->getIPAddress();

        $this->assertEquals(
            $ipAddress->asHumanReadable($ipAddress->asBinary()),
            self::TESTIP,
        );
    }

    public function testBanUnban(): void
    {
        $ipAddress = $this->getIPAddress();

        $ipAddress->ban(self::TESTIP);

        $this->assertTrue($ipAddress->isBanned());

        $ipAddress->unBan(self::TESTIP);

        $this->assertFalse($ipAddress->isBanned());
    }

    #[DataProvider('localHostDataProvider')]
    public function testIsLocalHost(
        string $ipHumanReadable,
        bool $isLocalHost,
    ): void {
        $ipAddress = $this->getIPAddress($ipHumanReadable);

        $this->assertEquals($ipAddress->isLocalHost(), $isLocalHost);
    }

    public static function localHostDataProvider(): array
    {
        return [
            ['127.0.0.1', true],
            ['::1', true],
            [self::TESTIP, false],
        ];
    }

    private function getIPAddress(string $ipAddress = self::TESTIP): IPAddress
    {
        $this->container->set(Request::class, new Request(
            server: ['REMOTE_ADDR' => $ipAddress],
        ));

        return $this->container->get(IPAddress::class);
    }
}
