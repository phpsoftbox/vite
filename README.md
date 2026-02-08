# Vite

Минимальный адаптер Vite для генерации тегов скриптов и стилей.

## Использование

```php
$vite = new \PhpSoftBox\Vite\Vite(
    manifestPath: __DIR__ . '/public/build/manifest.json',
    hotFile: __DIR__ . '/public/hot',
    devServer: 'https://vite.domain.local',
    environment: 'dev',
    buildBase: '/build',
);

echo $vite->tags('resources/js/app.tsx');
```

## Что умеет

- dev‑сервер: подключает `@vite/client` и entrypoint.
- build‑режим: читает `manifest.json` и подключает JS/CSS.
