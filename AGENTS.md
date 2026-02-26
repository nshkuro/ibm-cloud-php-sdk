# Repository Guidelines

Use this guide to onboard quickly to the IBM Cloud PHP SDK. Keep changes minimal, consistent, and well-tested.

## Project Structure & Module Organization
- `src/` uses the `IBMCloud\` PSR-4 namespace; core configuration lives in `Configuration/`, HTTP stack and middleware in `Transport/`, and contracts/interfaces in `Contracts/`. Keep new services under a logical namespace (e.g., `IBMCloud\Services\ObjectStorage`).
- `tests/` mirrors the source layout with `Unit/` and `Integration/`, plus reusable `Fixtures/`. Add new tests beside the code they cover.
- `docs/` holds design and usage notes; `examples/` shows runnable usage patterns. Update both when adding user-facing features.
- `phpunit.xml` defines defaults (bootstrap, coverage filters). Respect its directories when adding suites.

## Build, Test, and Development Commands
- Install deps once: `composer install` (requires PHP 8.1+).
- Unit/integration tests: `composer test`.
- Coverage report (HTML in `coverage/`): `composer test-coverage`.
- Static analysis: `composer analyse` (PHPStan); run before reviews.
- Coding standard check/fix: `composer cs-check` (dry-run) and `composer cs-fix`.
- Optional: `composer psalm` if the Psalm binary is available locally.

## Coding Style & Naming Conventions
- Follow PSR-12: 4-space indents, one class per file, and `declare(strict_types=1);` at the top of new PHP files.
- Classes/traits/interfaces: `PascalCase`; methods/properties: `camelCase`; constants: `UPPER_SNAKE_CASE`.
- Tests end with `*Test.php` and use the `IBMCloud\Tests\` namespace. Prefer explicit return types and typed properties.
- Keep public APIs well-typed and documented with PHPDoc where generics or arrays need clarification.

## Testing Guidelines
- Default to PHPUnit; use Mockery for doubles. Place fast checks in `Unit/` and network-sensitive scenarios in `Integration/`.
- Name tests after behavior (e.g., `itRetriesAfterThrottle`); group related assertions in data providers where helpful.
- Before submitting, run `composer test` and `composer analyse`; add `composer test-coverage` when modifying core transport or auth.

## Commit & Pull Request Guidelines
- Commit messages follow a conventional style observed in history (`feat:`, `refactor:`, `chore:`). Use imperative mood and keep subject lines concise.
- PRs should include: summary of change, steps to reproduce/verify, any breaking changes, and links to related issues. Mention config or docs updates when relevant.
- Add screenshots only when altering developer-facing docs or example outputs; otherwise provide short CLI logs for key commands.

## Security & Configuration Tips
- Do not commit secrets; use `.env` (see `.env.example`) and `vlucas/phpdotenv` for local configuration. Prefer environment variables in CI.
- When adding new service credentials, document the required keys in `docs/` and reference them from examples without embedding real values.
