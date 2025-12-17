<?php

 namespace Tests\Unit\Jax;

use Jax\FileSystem;
use Jax\OpenGraph;
use Tests\UnitTestCase;

 class OpenGraphTest extends UnitTestCase {
    private OpenGraph $openGraph;

    public function setUp(): void
    {
        parent::setUp();

        $fileSystemStub = $this->createStub(FileSystem::class);
        $fileSystemStub->method('getContents')
            ->willReturn(<<<'HTML'
                <html>
                    <head>
                        <meta property="og:title" content="Open Graph protocol">
                        <meta property="og:type" content="website">
                        <meta property="og:url" content="https://ogp.me/">
                        <meta property="og:image" content="https://ogp.me/logo.png">
                        <meta property="og:image:type" content="image/png">
                        <meta property="og:image:width" content="300">
                        <meta property="og:image:height" content="300">
                        <meta property="og:image:alt" content="The Open Graph logo">
                        <meta property="og:description" content="description">
                    </head>
                </html>
                HTML);

        $this->container->set(FileSystem::class, $fileSystemStub);

        $this->openGraph = $this->container->get(OpenGraph::class);
    }

    public function testFetch(): void {
        $this->assertEqualsCanonicalizing(
            [
                'title' => 'Open Graph protocol',
                'type' => 'website',
                'url' => 'https://ogp.me/',
                'image' => 'https://ogp.me/logo.png',
                'image:type' => 'image/png',
                'image:width' => '300',
                'image:height' => '300',
                'image:alt' => 'The Open Graph logo',
                'description' => 'description'
            ],
            $this->openGraph->fetch('http://dev.null'),
        );
    }
 }
