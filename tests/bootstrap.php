<?php

/**
 * This checkout lives in a git worktree, and vendor/ is a symlink to a
 * physical vendor/ directory shared across all worktrees (so dependencies
 * aren't reinstalled per worktree). That breaks two things Laravel/Composer
 * compute from the *physical* location of files inside vendor/composer/:
 *
 *   1. Illuminate\Foundation\Application::inferBasePath() derives the app's
 *      base path from the realpath of the registered Composer ClassLoader,
 *      which is always the shared vendor's physical parent directory.
 *   2. vendor/composer/autoload_psr4.php (and the classmap) compute
 *      $baseDir as dirname(realpath of vendor/composer)) too — so `App\`,
 *      `Tests\`, etc. always resolve to the shared checkout's app/, tests/,
 *      etc., no matter which worktree ran `composer dump-autoload`. There
 *      is no composer flag that changes this: the formula is baked in
 *      relative to vendor/composer's own physical directory.
 *
 * Left unfixed, running tests from a worktree silently boots the
 * application and loads App\/Tests\/Database\ classes from whichever
 * checkout physically owns vendor/ — not this one.
 *
 * __DIR__ here is safe: this file itself is a normal tracked file (only
 * vendor/ is symlinked), so PHP resolves it to this checkout's own real
 * path, not the shared vendor's.
 */
$root = dirname(__DIR__);

require $root.'/vendor/autoload.php';

// Prepend a second loader for exactly this checkout's own namespaces, so
// they are tried (and matched) before composer's generated autoloader gets
// a chance to resolve them against the wrong (shared) checkout.
$worktreeLoader = new \Composer\Autoload\ClassLoader();
$worktreeLoader->setPsr4('App\\', [$root.'/app']);
$worktreeLoader->setPsr4('Database\\Factories\\', [$root.'/database/factories']);
$worktreeLoader->setPsr4('Database\\Seeders\\', [$root.'/database/seeders']);
$worktreeLoader->setPsr4('Tests\\', [$root.'/tests']);
$worktreeLoader->register(true);

// php.ini's variables_order here doesn't include "E", so shell-exported
// env vars never populate $_ENV — only getenv() sees them. Application::
// inferBasePath() specifically checks $_ENV, so it must be set directly.
$_ENV['APP_BASE_PATH'] = $root;
