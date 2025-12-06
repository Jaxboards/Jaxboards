<?php

use Jax\IPAddress;
use Jax\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\UnitTestCase;

class IPAddressTest extends UnitTestCase {
    const TESTIP = '192.168.1.1';

    public function setUp(): void
    {
        parent::setUp();
    }

    private function getIPAddress(string $ipAddress = self::TESTIP): IPAddress
    {
        $this->container->set(Request::class, new Request(
            server: ['REMOTE_ADDR' => $ipAddress]
        ));
        return $this->container->get(IPAddress::class);
    }

    public function testBinaryConversions()
    {
        $ipAddress = $this->getIPAddress();

        $this->assertEquals(
            $ipAddress->asHumanReadable($ipAddress->asBinary()),
            self::TESTIP
        );
    }

    public function testBanUnban() {
        $ipAddress = $this->getIPAddress();

        $ipAddress->ban(self::TESTIP);

        $this->assertTrue($ipAddress->isBanned());

        $ipAddress->unBan(self::TESTIP);

        $this->assertFalse($ipAddress->isBanned());
    }

    public static function localHostDataProvider(): array
    {
        return [
            ['127.0.0.1', true],
            ['::1', true],
            [self::TESTIP, false],
        ];
    }

    #[DataProvider('localHostDataProvider')]
    public function testIsLocalHost(string $ipHumanReadable, bool $isLocalHost)
    {
        $ipAddress = $this->getIPAddress($ipHumanReadable);

        $this->assertEquals($ipAddress->isLocalHost(), $isLocalHost);
    }
}
