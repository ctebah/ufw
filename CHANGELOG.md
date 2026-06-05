# Changelog

All notable changes to this project will be documented in this file.

## [unreleased]

## [1.0.1] - 2026-06-05
### Fixed
- **JsOut:** remove semicolon-collapse regex from `_sanitize()` that could corrupt `jsInline` payloads (e.g. `json_encode` data with `;;` inside string literals), causing JShrink to throw during minify.

## [1.0.0]
### Added
- Initial public release of UFW.
