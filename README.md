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
    ssrUrl: 'http://node:13714/render',
    ssrEntry: 'resources/js/ssr.tsx',
    ssrTimeout: 2.0,
    dev: null, // явный флаг dev-режима; null — определить по environment
);

echo $vite->tags('resources/js/app.tsx');
```

## Dev-режим и production

Dev-режим (теги dev-server, `@vite/client`, React Refresh) включается так:

1. если передан `dev: true|false` — используется он (рекомендуется: например,
   `dev: !$applicationEnvironment->isProductionLike()` или отдельный ключ конфигурации);
2. иначе dev-режим включён только для окружений из белого списка `Vite::DEV_ENVIRONMENTS`:
   `dev`, `development`, `local`, `test`, `testing` (без учёта регистра).
   **Любое другое окружение** (`prod`, `production`, `staging`, ...) — build-режим.

В dev-режиме URL dev-server берётся из `devServer`, а если он не задан — из hot-файла.
Если ни то ни другое не задано, используется build-режим (manifest).

> До 1.0 dev-режимом считалось любое окружение, кроме `prod`, поэтому на `production`/`staging`
> выводились теги dev-server.

## Что умеет

- dev‑сервер: подключает `@vite/client` и entrypoint.
- build‑режим: читает `manifest.json`, подключает JS/CSS, CSS imported chunks
  и `modulepreload` для статических imports.
- CSS-entrypoint (`.css`, `.scss`, `.less`, `.styl`, `.pcss`, ...) и в dev, и в build-режиме
  подключается как `<link rel="stylesheet">`, а не как `<script type="module">`.
- `version()` — md5 от `manifest.json` для Inertia asset versioning; вычисляется один раз
  на экземпляр `Vite` (в worker'е manifest меняется только при деплое с перезапуском).
  В dev-режиме возвращает `dev`.
- `reactRefreshPreamble()` — inline-скрипт React Refresh; URL dev-server экранируется
  (`JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`) для безопасной вставки в `<script>`.
- SSR runtime: хранит endpoint SSR-сервера, SSR entrypoint и timeout.

## Production manifest

В build-режиме компонент читает manifest, который генерирует Vite.
Для каждого entrypoint подключается основной JS-файл, его CSS, CSS
статически импортированных chunks и `modulepreload` для этих chunks.

```php
echo $vite->tags('resources/js/app.tsx');
```

Если entrypoint импортирует общий bundle, итоговый HTML будет включать
примерно такой набор тегов:

```html
<link rel="stylesheet" href="/build/assets/app.123.css">
<link rel="stylesheet" href="/build/assets/vendor.456.css">
<link rel="modulepreload" href="/build/assets/vendor.456.js">
<script type="module" src="/build/assets/app.123.js"></script>
```

CSS-entrypoint, собранный отдельно (`input: ['resources/js/app.tsx', 'resources/css/admin.css']`):

```php
echo $vite->tags(['resources/js/app.tsx', 'resources/css/admin.css']);
// ...
// <link rel="stylesheet" href="/build/assets/admin.789.css">
```

## SSR runtime

Компонент Vite не рендерит Inertia сам. Он хранит runtime-настройки,
которые приложение может передать SSR renderer-у:

```php
if ($vite->ssrEnabled()) {
    $renderer = new HttpSsrRenderer(
        url: $vite->ssrUrl(),
        timeout: $vite->ssrTimeout(),
    );
}
```

Доступные методы:

- `devServerUrl()` — URL Vite dev-server в dev-режиме или `null`;
- `isDev()` — явный флаг `dev` или окружение из `Vite::DEV_ENVIRONMENTS`;
- `ssrEnabled()` — `true`, если задан SSR endpoint;
- `ssrUrl()` — нормализованный URL SSR endpoint;
- `ssrEntry()` — entrypoint SSR bundle;
- `ssrTimeout()` — timeout HTTP SSR запроса.

## Recipe: React + Inertia + ReactSoftBox

Рекомендуемая структура frontend entrypoint:

```text
resources/js/app.tsx
resources/js/styles.css
resources/js/Pages/Web/Home.tsx
resources/js/Pages/Admin/Dashboard.tsx
resources/js/Layouts/WebLayout.tsx
resources/js/Layouts/AdminLayout.tsx
```

`resources/js/app.tsx`:

```tsx
import React from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { initTheme } from '@phpsoftbox/react-softbox';
import type { ComponentType } from 'react';
import '@phpsoftbox/react-softbox/foundations/index.css';
import './styles.css';

initTheme({ defaultMode: 'light' });

const pages = import.meta.glob('./Pages/**/*.tsx', { eager: true }) as Record<
  string,
  { default: ComponentType }
>;

createInertiaApp({
  resolve: (name) => {
    const page = pages[`./Pages/${name}.tsx`];
    if (!page) {
      throw new Error(`Page "${name}" not found.`);
    }

    return page.default;
  },
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />);
  },
});
```

`vite.config.js` должен указывать этот entrypoint:

```js
export default defineConfig({
  plugins: [react()],
  build: {
    manifest: true,
    rollupOptions: {
      input: 'resources/js/app.tsx',
    },
  },
});
```

Для разделения публичной части и админки используйте Inertia areas:
`Web/*` и `Admin/*` остаются в одном frontend entrypoint, а сервер выбирает
area по host/path через `InertiaAreaConfig`.
