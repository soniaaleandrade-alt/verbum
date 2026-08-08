# Verbum Studio

Verbum Studio é a base de um Sistema Operacional para Escritores construído sobre WordPress. Este repositório contém exclusivamente o Sprint 01 — Core.

## Requisitos
- WordPress 6.0+
- PHP 7.4+
- Node.js/npm para o app React
- Composer para autoload e testes PHP

## Instalação
1. Copie o diretório para `wp-content/plugins/verbum-studio`.
2. Execute `composer install` para gerar o autoload Composer.
3. Execute `npm run build` para gerar os assets do aplicativo inicial. Quando houver acesso ao registry, `npm install --prefix frontend/app` pode ser usado para instalar a toolchain React/Vite completa.
4. Ative o plugin no painel do WordPress.

## Configuração de ambiente
Defina constantes no `wp-config.php` ou variáveis de ambiente:

```php
define('VERBUM_ENV', 'development'); // development, staging, production
define('VERBUM_SUPABASE_URL', 'https://example.supabase.co');
define('VERBUM_SUPABASE_ANON_KEY', 'placeholder');
define('VERBUM_SUPABASE_SERVICE_KEY', 'placeholder');
define('VERBUM_OPENAI_API_KEY', 'placeholder');
```

Use `.env.example` apenas como referência. Não commite credenciais reais.

## REST API
- `GET /wp-json/verbum/v1/health`
- `GET /wp-json/verbum/v1/me`

Todas as respostas usam `{ success, data }` ou `{ success, error }`.

## React
Use `[verbum_app]` para carregar a tela de diagnóstico. O React consome `/health` e `/me` por uma camada centralizada em `frontend/app/src/services`.

## Elementor
Quando Elementor está ativo, o widget `Verbum App` é registrado. A ausência do Elementor não causa erro fatal.

## Supabase
A integração inicial fica em `src/Integrations/Supabase`. A service key é usada somente no backend para teste interno de conexão e nunca é exposta ao frontend.

## Testes e build
- `composer test`
- `npm --prefix frontend/app run test`
- `npm run build`

## Escopo
Este sprint não inclui dashboard real, obras, capítulos, editor, publicação, colaboração ou IA avançada.
