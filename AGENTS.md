# Verbum Studio Agent Guide

## Objetivo
Verbum Studio é um Sistema Operacional para Escritores sobre WordPress. O Sprint 01 implementa somente o Core: plugin, REST API, autenticação/permissões WordPress, infraestrutura Supabase, shortcode, integração Elementor e app React diagnóstico.

## Stack
PHP 7.4+, WordPress REST API, TypeScript, React, npm, Composer/PSR-4, Supabase preparado no backend.

## Arquitetura
- `verbum-studio.php`: entrada enxuta do plugin.
- `src/Core`: bootstrap, plugin, configuração e container simples.
- `src/Api`: controllers e padrão de resposta.
- `src/Auth`: capacidades.
- `src/Integrations`: Supabase e Elementor.
- `src/Services`: assets/shortcodes.
- `frontend/app`: React TypeScript.

## Comandos
- `composer install`
- `composer test`
- `npm install --prefix frontend/app`
- `npm run build`
- `npm --prefix frontend/app run test`

## Segurança
Nunca commitar `.env` ou segredos. Nunca enviar service keys Supabase ao React, HTML, JavaScript ou respostas REST. Sanitize entradas, escape saídas e use capabilities no backend.

## Regras de produto
Não avançar para Sprint 2. Não implementar obras, dashboard real, capítulos, pesquisa, redação, revisão, publicação, colaboração ou IA avançada neste sprint.
