<?php

declare(strict_types=1);

namespace Tests\Unit\Jax;

use Jax\BBCode;
use Jax\FileSystem;
use Jax\OpenGraph;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;
use Tests\UnitTestCase;

/**
 * @internal
 */
#[CoversClass(OpenGraph::class)]
final class OpenGraphTest extends UnitTestCase
{
    use MatchesSnapshots;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[DataProvider('fetchDataProvider')]
    public function testFetch(string $url, string $htmlInput): void
    {
        $fileSystemStub = $this->createStub(FileSystem::class);
        $fileSystemStub->method('getContents')
            ->willReturn($htmlInput)
        ;

        $this->container->set(FileSystem::class, $fileSystemStub);
        $this->container->set(BBCode::class, $this->createStub(BBCode::class));

        $openGraph = $this->container->get(OpenGraph::class);

        $this->assertMatchesSnapshot($openGraph->fetch($url));
    }

    /**
     * @returns array<string,string,array<string,string>>
     */
    public static function fetchDataProvider(): array
    {
        return [
            [
                'http://dev.null',
                <<<'HTML'
                    <html>
                    	<head>
                    		<meta property="og:title" content="Dick Van Dyke is turning 100. Here’s what he’s been up to lately">
                    		<meta property="og:type" content="website">
                    		<meta property="og:url" content="https://ogp.me/">
                    		<meta property="og:image" content="https://ogp.me/logo.png">
                    		<meta property="og:image:type" content="image/png">
                    		<meta property="og:image:width" content="300">
                    		<meta property="og:image:height" content="300">
                    		<meta property="og:image:alt" content="The Open Graph logo">
                    		<!-- Some sites incorrectly use "name" attribute -->
                    		<meta name="og:description" content="description">
                    	</head>
                    </html>
                    HTML,
            ],
            [
                'https://github.com/Jaxboards/Jaxboards/releases/tag/3.0',
                <<<'HTML'
                    <html>
                    	<head>
                    		<meta property="og:image" content="https://opengraph.githubassets.com/9e42864e715305b7fb43886e68810c7fa1ad5b45a971b6de80b1605b1983951b/Jaxboards/Jaxboards/releases/tag/3.0">
                    		<meta property="og:image:alt" content="What&#39;s Changed

                    			New bots added to BotDetector (thanks @VinnyVideo )
                    			OpenGraph post embeddings (link unrolling) for posts
                    			Guest session view for moderators in ModCP
                    			GeoIP information is back, modera...">
                    		<meta property="og:image:width" content="1200">
                    		<meta property="og:image:height" content="600">
                    		<meta property="og:site_name" content="GitHub">
                    		<meta property="og:type" content="object">
                    		<meta property="og:title" content="Release 3.0 · Jaxboards/Jaxboards">
                    		<meta property="og:url" content="/Jaxboards/Jaxboards/releases/tag/3.0">
                    		<meta property="og:description" content="What&#39;s Changed

                    			New bots added to BotDetector (thanks @VinnyVideo )
                    			OpenGraph post embeddings (link unrolling) for posts
                    			Guest session view for moderators in ModCP
                    			GeoIP information is back, modera...">
                    	</head>
                    </html>
                    HTML,
            ],
        ];
    }
}
