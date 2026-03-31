# Repository Guidelines

## Project Structure & Module Organization

This repository is a Yaf-based PHP application. Core backend code lives in `application/`:

- `application/controllers/`: request handlers such as `Report.php` and `Menu.php`
- `application/models/`: DB-backed models
- `application/library/`: shared framework code under `GG/`, app code under `MY/`
- `application/helpers/`: global helper functions and parameter parsing
- `application/views/`: Yaf templates for `report/`, `open/`, `login/`, etc.

Frontend assets live in `public/` and `public/open/`, with AngularJS controllers in `public/js/controllers/` and `public/open/js/controllers/`. Docker runtime files are under `docker/`. CLI entry scripts live in `script/`.

## Build, Test, and Development Commands

- `docker compose -f docker/compose.yml up --build`: build and start MySQL, PHP-FPM, and Caddy
- `docker compose -f docker/compose.yml logs -f ddy`: watch PHP container logs
- `php -l application/controllers/Report.php`: syntax check a PHP file
- `node -c public/js/controllers/MenuFormController.js`: syntax check a frontend controller
- `docker compose -f docker/compose.yml run --rm ddy composer install`: install PHP dependencies in the app container

Prefer targeted checks after each change rather than broad commands with noisy output.

## Coding Style & Naming Conventions

Use 4-space indentation in PHP and JavaScript. Keep existing Yaf naming patterns:

- controllers: `FooController`
- models: `FooModel`
- helpers: snake_case functions
- frontend controllers: `SomethingController.js`

Match the surrounding style before refactoring. Favor small compatibility fixes over large rewrites in legacy code. Do not edit `vendor/` directly.

## Testing Guidelines

There is no formal unit test suite in this repository today. Minimum verification for changes:

- run `php -l` on touched PHP files
- run `node -c` on touched JS files
- verify the affected page or API manually through the local Docker stack

When fixing editor or report flows, test the exact endpoint, for example `/report/syntaxCheck`.

## Commit & Pull Request Guidelines

Recent history uses very short commits like `up`, but contributors should prefer clear imperative messages, for example `fix php8 parameter globals` or `debounce syntaxCheck requests`.

PRs should include:

- a short problem statement
- the files or flows changed
- local verification steps
- screenshots or curl examples for UI/API changes

## Security & Configuration Tips

Keep credentials in local config only. Avoid committing database dumps or changes under `docker/dbdata3/`. Treat `docker/initdb/` and runtime config edits as high-impact changes and review them carefully.
