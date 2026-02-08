<?php

declare(strict_types=1);

namespace PhpSoftBox\Vite\Tests;

use PhpSoftBox\Vite\Vite;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function json_encode;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const JSON_THROW_ON_ERROR;

#[CoversClass(Vite::class)]
#[CoversMethod(Vite::class, 'tags')]
#[CoversMethod(Vite::class, 'reactRefreshPreamble')]
#[CoversMethod(Vite::class, 'devServerUrl')]
#[CoversMethod(Vite::class, 'version')]
#[CoversMethod(Vite::class, 'isDev')]
#[CoversMethod(Vite::class, 'ssrEnabled')]
#[CoversMethod(Vite::class, 'ssrUrl')]
#[CoversMethod(Vite::class, 'ssrEntry')]
#[CoversMethod(Vite::class, 'ssrTimeout')]
final class ViteTest extends TestCase
{
    /**
     * Проверяем генерацию dev-server тегов и React Refresh preamble.
     *
     * @see Vite::tags()
     * @see Vite::reactRefreshPreamble()
     * @see Vite::devServerUrl()
     */
    #[Test]
    public function testTagsFromDevServer(): void
    {
        $hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
        file_put_contents($hotFile, 'https://vite.local');

        $vite = new Vite(
            manifestPath: $hotFile . '.manifest',
            hotFile: $hotFile,
            devServer: null,
            environment: 'dev',
            buildBase: '/build',
        );

        $tags     = $vite->tags('resources/js/app.tsx');
        $preamble = $vite->reactRefreshPreamble();

        $this->assertStringContainsString('https://vite.local/@vite/client', $tags);
        $this->assertStringContainsString('https://vite.local/resources/js/app.tsx', $tags);
        $this->assertStringContainsString('@react-refresh', $preamble);
        $this->assertStringContainsString('__vite_plugin_react_preamble_installed__', $preamble);

        unlink($hotFile);
    }

    /**
     * Проверяем генерацию build-тегов из manifest.
     *
     * @see Vite::tags()
     * @see Vite::version()
     */
    #[Test]
    public function testTagsFromManifest(): void
    {
        $manifestFile = tempnam(sys_get_temp_dir(), 'vite-manifest-');
        $manifest     = [
            'resources/js/app.tsx' => [
                'file' => 'assets/app.123.js',
                'css'  => ['assets/app.123.css'],
            ],
        ];
        file_put_contents($manifestFile, json_encode($manifest, JSON_THROW_ON_ERROR));

        $vite = new Vite(
            manifestPath: $manifestFile,
            hotFile: $manifestFile . '.hot',
            devServer: null,
            environment: 'prod',
            buildBase: '/build',
        );

        $tags = $vite->tags('resources/js/app.tsx');

        $this->assertStringContainsString('/build/assets/app.123.css', $tags);
        $this->assertStringContainsString('/build/assets/app.123.js', $tags);

        unlink($manifestFile);
    }

    /**
     * Проверяем, что build-теги включают CSS и modulepreload для imported chunks.
     *
     * @see Vite::tags()
     */
    #[Test]
    public function testTagsFromManifestImports(): void
    {
        $manifestFile = tempnam(sys_get_temp_dir(), 'vite-manifest-');
        $manifest     = [
            'resources/js/app.tsx' => [
                'file'    => 'assets/app.123.js',
                'css'     => ['assets/app.123.css'],
                'imports' => ['_vendor.456.js'],
            ],
            '_vendor.456.js' => [
                'file'    => 'assets/vendor.456.js',
                'css'     => ['assets/vendor.456.css'],
                'imports' => ['_shared.789.js'],
            ],
            '_shared.789.js' => [
                'file' => 'assets/shared.789.js',
                'css'  => ['assets/shared.789.css'],
            ],
        ];
        file_put_contents($manifestFile, json_encode($manifest, JSON_THROW_ON_ERROR));

        $vite = new Vite(
            manifestPath: $manifestFile,
            hotFile: $manifestFile . '.hot',
            devServer: null,
            environment: 'prod',
            buildBase: '/build',
        );

        $tags = $vite->tags('resources/js/app.tsx');

        $this->assertStringContainsString('/build/assets/app.123.css', $tags);
        $this->assertStringContainsString('/build/assets/vendor.456.css', $tags);
        $this->assertStringContainsString('/build/assets/shared.789.css', $tags);
        $this->assertStringContainsString('rel="modulepreload" href="/build/assets/vendor.456.js"', $tags);
        $this->assertStringContainsString('rel="modulepreload" href="/build/assets/shared.789.js"', $tags);
        $this->assertStringContainsString('type="module" src="/build/assets/app.123.js"', $tags);

        unlink($manifestFile);
    }

    /**
     * Проверяем чтение SSR runtime-настроек компонента Vite.
     *
     * @see Vite::isDev()
     * @see Vite::ssrEnabled()
     * @see Vite::ssrUrl()
     * @see Vite::ssrEntry()
     * @see Vite::ssrTimeout()
     */
    #[Test]
    public function testSsrRuntimeConfig(): void
    {
        $vite = new Vite(
            manifestPath: '/app/public/build/manifest.json',
            hotFile: '/app/public/hot',
            devServer: 'https://vite.local',
            environment: 'dev',
            buildBase: '/build',
            ssrUrl: 'http://node:13714/render/',
            ssrEntry: '/resources/js/ssr.tsx',
            ssrTimeout: 3.5,
        );

        $this->assertTrue($vite->isDev());
        $this->assertTrue($vite->ssrEnabled());
        $this->assertSame('http://node:13714/render', $vite->ssrUrl());
        $this->assertSame('resources/js/ssr.tsx', $vite->ssrEntry());
        $this->assertSame(3.5, $vite->ssrTimeout());
    }
}
