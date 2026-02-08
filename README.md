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
);

echo $vite->tags('resources/js/app.tsx');
```

## Что умеет

- dev‑сервер: подключает `@vite/client` и entrypoint.
- build‑режим: читает `manifest.json`, подключает JS/CSS, CSS imported chunks
  и `modulepreload` для статических imports.
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
- `isDev()` — `true`, если окружение не `prod`;
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
