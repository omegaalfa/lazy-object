# Changelog

Todas as alterações relevantes deste projeto serão documentadas neste arquivo.

O formato segue [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e o projeto usa [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [Unreleased]

## [1.0.0] - 2026-07-25

### Added

- Fachada tipada `LazyObject::proxy()` para lazy proxies nativos.
- Fachada tipada `LazyObject::ghost()` para lazy ghosts nativos.
- Suporte a opções aceitas por `newLazyProxy()` e `newLazyGhost()`.
- Cache interno de `ReflectionClass` por nome de classe.
- Validações estruturais para classes inexistentes, interfaces, traits, enums e classes abstratas.
- Suíte PHPUnit para ciclo de vida, erros nativos, serialização, clonagem e inferência de tipos.
- Benchmark reproduzível do cache de Reflection.
- CI para PHP 8.4 com Composer, testes, PHPStan, estilo e cobertura.

[Unreleased]: https://github.com/omegaalfa/lazy-object/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/omegaalfa/lazy-object/releases/tag/v1.0.0
