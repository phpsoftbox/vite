<?php

declare(strict_types=1);

namespace PhpSoftBox\Vite;

use RuntimeException;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_values;
use function file_get_contents;
use function htmlspecialchars;
use function implode;
use function is_array;
use function is_file;
use function is_string;
use function json_decode;
use function json_encode;
use function ltrim;
use function md5_file;
use function rtrim;
use function trim;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

final class Vite
{
    private ?array $manifest = null;

    public function __construct(
        private readonly string $manifestPath,
        private readonly string $hotFile,
        private readonly ?string $devServer = null,
        private readonly string $environment = 'prod',
        private readonly string $buildBase = '/build',
    ) {
    }

    /**
     * @param string|array<int, string> $entrypoints
     */
    public function tags(array|string $entrypoints): string
    {
        $entrypoints = is_array($entrypoints) ? $entrypoints : [$entrypoints];
        $entrypoints = array_values(array_filter(array_map(static function ($entry): string {
            return is_string($entry) ? trim($entry) : '';
        }, $entrypoints), static fn (string $entry): bool => $entry !== ''));

        if ($entrypoints === []) {
            return '';
        }

        $devServer = $this->devServerUrl();
        if ($devServer !== null) {
            $tags = [$this->scriptTag($devServer . '/@vite/client')];

            foreach ($entrypoints as $entry) {
                $tags[] = $this->scriptTag($devServer . '/' . ltrim($entry, '/'));
            }

            return implode("\n", $tags);
        }

        $manifest = $this->loadManifest();
        $styles   = [];
        $scripts  = [];

        foreach ($entrypoints as $entry) {
            $entry = ltrim($entry, '/');

            if (!array_key_exists($entry, $manifest) || !is_array($manifest[$entry])) {
                throw new RuntimeException("Vite entry '{$entry}' not found in manifest.");
            }

            $data = $manifest[$entry];

            if (isset($data['css']) && is_array($data['css'])) {
                foreach ($data['css'] as $css) {
                    if (is_string($css) && $css !== '') {
                        $styles[$css] = true;
                    }
                }
            }

            if (isset($data['file']) && is_string($data['file']) && $data['file'] !== '') {
                $scripts[$data['file']] = true;
            }
        }

        $tags = [];
        foreach (array_keys($styles) as $file) {
            $tags[] = $this->styleTag($this->assetUrl($file));
        }

        foreach (array_keys($scripts) as $file) {
            $tags[] = $this->scriptTag($this->assetUrl($file));
        }

        return implode("\n", $tags);
    }

    public function version(): string
    {
        if ($this->devServerUrl() !== null) {
            return 'dev';
        }

        if (is_file($this->manifestPath)) {
            $hash = md5_file($this->manifestPath);
            if (is_string($hash) && $hash !== '') {
                return $hash;
            }
        }

        return 'dev';
    }

    public function reactRefreshPreamble(): string
    {
        $devServer = $this->devServerUrl();
        if ($devServer === null) {
            return '';
        }

        $refreshUrl   = $devServer . '/@react-refresh';
        $refreshUrlJs = json_encode($refreshUrl, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return '<script type="module">' .
            'import RefreshRuntime from ' . $refreshUrlJs . ';' .
            'RefreshRuntime.injectIntoGlobalHook(window);' .
            'window.$RefreshReg$ = () => {};' .
            'window.$RefreshSig$ = () => (type) => type;' .
            'window.__vite_plugin_react_preamble_installed__ = true;' .
            '</script>';
    }

    private function devServerUrl(): ?string
    {
        if ($this->environment === 'prod') {
            return null;
        }

        if (is_string($this->devServer) && $this->devServer !== '') {
            return rtrim($this->devServer, '/');
        }

        if (!is_file($this->hotFile)) {
            return null;
        }

        $url = trim((string) file_get_contents($this->hotFile));
        if ($url === '') {
            return null;
        }

        return rtrim($url, '/');
    }

    private function assetUrl(string $file): string
    {
        $base = '/' . trim($this->buildBase, '/');

        return $base . '/' . ltrim($file, '/');
    }

    private function loadManifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        if (!is_file($this->manifestPath)) {
            throw new RuntimeException('Vite manifest not found: ' . $this->manifestPath);
        }

        $contents = file_get_contents($this->manifestPath);
        if ($contents === false) {
            throw new RuntimeException('Failed to read Vite manifest: ' . $this->manifestPath);
        }

        $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid Vite manifest structure.');
        }

        $this->manifest = $data;

        return $data;
    }

    private function scriptTag(string $src): string
    {
        return '<script type="module" src="' . $this->escape($src) . '"></script>';
    }

    private function styleTag(string $href): string
    {
        return '<link rel="stylesheet" href="' . $this->escape($href) . '">';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
