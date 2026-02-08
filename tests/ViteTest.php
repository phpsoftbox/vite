<?php

declare(strict_types=1);

namespace PhpSoftBox\Vite\Tests;

use PhpSoftBox\Vite\Vite;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function json_encode;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const JSON_THROW_ON_ERROR;

final class ViteTest extends TestCase
{
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
}
