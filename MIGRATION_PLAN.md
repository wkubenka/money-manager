# Migration Plan: NativePHP Desktop App + Static Marketing Site

## Overview

Migrate Astute Money from a multi-user web app on AWS Elastic Beanstalk to a single-user NativePHP Electron desktop app. Create a static marketing site hosted on S3/CloudFront that links to the GitHub Releases page. Use git tags and a GitHub Actions workflow to build and publish .dmg/.exe releases.

---

## Phase 1: Remove Elastic Beanstalk Infrastructure

### Files to Delete

| Path | Purpose |
|------|---------|
| `.ebextensions/01-efs-mount.config` | EFS mount configuration |
| `.ebextensions/02-document-root.config` | Nginx document root |
| `.ebextensions/03-https.config` | HTTPS/SSL listener |
| `.ebextensions/04-efs-logs.config` | Log collection from EFS |
| `.platform/hooks/postdeploy/01_laravel.sh` | Post-deploy: migrations, caches, permissions |
| `.platform/confighooks/postdeploy/01_cache.sh` | Cache rebuild after `eb setenv` |
| `.platform/nginx/conf.d/elasticbeanstalk/laravel.conf` | Laravel routing rewrite |
| `.platform/nginx/conf.d/elasticbeanstalk/https_redirect.conf` | HTTP-to-HTTPS redirect |
| `.platform/nginx/conf.d/elasticbeanstalk/static_cache.conf` | Asset caching rules |
| `.ebignore` | EB deploy ignore rules |
| `.elasticbeanstalk/` | EB CLI configuration (entire directory) |

### Files to Modify

**`bootstrap/app.php`**
- Remove `$middleware->trustProxies(at: '*')` (EB proxy trust)
- Remove `admin` middleware alias and `EnsureUserIsAdmin` import

**`app/Providers/AppServiceProvider.php`**
- Remove `User::observe(UserObserver::class)` and imports
- Remove entire production HTTPS/CloudFront proxy block (lines 41-57)
- Remove `Password::defaults(...)` (no auth)
- Keep `Date::use(CarbonImmutable::class)` and `DB::prohibitDestructiveCommands()`

**`.gitignore`** — Remove Elastic Beanstalk section

**`.env.example`** — Remove `ADMIN_EMAILS`, `EFS_ID`, mail variables, `AWS_*` variables

---

## Phase 2: Remove Auth/User System

### 2A: Remove Fortify Package

```bash
composer remove laravel/fortify
```

### 2B: Files to Delete

**Auth infrastructure:**

| Path | Purpose |
|------|---------|
| `app/Providers/FortifyServiceProvider.php` | Fortify service provider |
| `app/Actions/Fortify/CreateNewUser.php` | User registration action |
| `app/Actions/Fortify/ResetUserPassword.php` | Password reset action |
| `app/Concerns/PasswordValidationRules.php` | Password validation rules |
| `app/Concerns/ProfileValidationRules.php` | Profile validation rules |
| `app/Http/Middleware/EnsureUserIsAdmin.php` | Admin gate middleware |
| `app/Livewire/Actions/Logout.php` | Logout action |
| `app/Observers/UserObserver.php` | User observer |
| `app/Models/User.php` | User model |
| `database/factories/UserFactory.php` | User factory |
| `config/admin.php` | Admin email list config |
| `config/fortify.php` | Fortify config |

**Auth views (all under `resources/views/`):**

| Path |
|------|
| `pages/auth/` (entire directory: login, register, verify-email, etc.) |
| `layouts/auth.blade.php` |
| `layouts/auth/card.blade.php` |
| `layouts/auth/simple.blade.php` |
| `layouts/auth/split.blade.php` |
| `components/auth-header.blade.php` |
| `components/auth-session-status.blade.php` |
| `components/desktop-user-menu.blade.php` |

**Settings pages to delete:**

| Path |
|------|
| `pages/settings/⚡profile.blade.php` |
| `pages/settings/⚡password.blade.php` |
| `pages/settings/⚡delete-user-form.blade.php` |

**Admin:**

| Path |
|------|
| `pages/admin/⚡dashboard.blade.php` |
| `routes/admin.php` |

**Welcome page (moves to static site):**

| Path |
|------|
| `resources/views/welcome.blade.php` |

**Auth tests:**

| Path |
|------|
| `tests/Feature/Auth/` (entire directory) |
| `tests/Feature/Admin/AdminDashboardTest.php` |
| `tests/Feature/Settings/PasswordUpdateTest.php` |
| `tests/Feature/Settings/ProfileUpdateTest.php` |

**Remove from `bootstrap/providers.php`:**
- `App\Providers\FortifyServiceProvider::class`

### 2C: Create Profile Model (replaces user-level fields)

User-level fields (`date_of_birth`, `retirement_age`, `expected_return`, `withdrawal_rate`) move to a single-row `profile` table.

**New migration: `create_profile_table.php`**

```php
Schema::create('profile', function (Blueprint $table) {
    $table->id();
    $table->date('date_of_birth')->nullable();
    $table->unsignedSmallInteger('retirement_age')->nullable()->default(65);
    $table->decimal('expected_return', 4, 1)->nullable()->default(7.0);
    $table->decimal('withdrawal_rate', 4, 1)->nullable()->default(4.0);
    $table->timestamps();
});
```

**New model: `app/Models/Profile.php`**

```php
class Profile extends Model
{
    protected $table = 'profile';

    protected $fillable = ['date_of_birth', 'retirement_age', 'expected_return', 'withdrawal_rate'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'retirement_age' => 'integer',
            'expected_return' => 'decimal:1',
            'withdrawal_rate' => 'decimal:1',
        ];
    }

    public static function instance(): static
    {
        return static::firstOrCreate([]);
    }

    public function age(): ?int
    {
        return $this->date_of_birth?->age;
    }
}
```

**New factory: `database/factories/ProfileFactory.php`**

### 2D: Migration to Remove Auth Tables and user_id Columns

**New migration: `remove_auth_and_user_scoping.php`**

Drop tables:
- `users`
- `password_reset_tokens`
- `sessions`

Drop `user_id` column from:
- `spending_plans`
- `net_worth_accounts`
- `rich_life_visions`
- `expense_accounts`
- `expenses` (also drop the composite `['user_id', 'date']` index)

### 2E: Update Models (remove user relationships)

All models below: remove `user()` BelongsTo relationship, remove `user_id` from `$fillable`.

| Model | Additional Changes |
|-------|-------------------|
| `app/Models/SpendingPlan.php` | `markCurrentIfOnly()`: replace `$this->user->spendingPlans()` with `SpendingPlan::query()`. `ensureCurrentPlanForUser(User $user)` becomes `ensureCurrentPlan()` using `SpendingPlan::query()`. |
| `app/Models/NetWorthAccount.php` | Remove user relationship only |
| `app/Models/RichLifeVision.php` | Remove user relationship only |
| `app/Models/ExpenseAccount.php` | Remove user relationship only |
| `app/Models/Expense.php` | Remove user relationship only |

### 2F: Update Services and Actions

**`app/Actions/CopySpendingPlan.php`**
- Remove `User $user` parameter from `__invoke()`
- Remove `abort_unless($plan->user_id === $user->id, 403)` ownership check
- Remove `'user_id' => $user->id` from create array
- Replace `$user->spendingPlans()->count()` with `SpendingPlan::count()`

**`app/Services/CsvExpenseImporter.php`**
- Remove `int $userId` parameter from `parse()` and `import()`
- Replace `Expense::where('user_id', $userId)` with `Expense::query()`
- Remove `abort_unless($account->user_id === $userId, 403)` checks
- Remove `'user_id' => $userId` from create calls
- Remove `$userId` from `lookupMerchantCategory()`

### 2G: Update Livewire Page Components

Every `Auth::user()->relationship()` becomes a direct `Model::query()` call.
Every `abort_unless($model->user_id === Auth::id(), 403)` is removed.
Every `Auth::user()->update(...)` for profile fields becomes `Profile::instance()->update(...)`.

**`resources/views/pages/⚡dashboard.blade.php`** (~30 Auth references)
- `mount()`: Use `Profile::instance()` for retirement fields
- `visions()`: `RichLifeVision::query()->orderBy(...)->get()`
- `addVision()`: `RichLifeVision::create([...])` (no user_id)
- All vision/account mutations: remove ownership checks
- `accounts()`: `NetWorthAccount::query()->get()`
- `currentPlan()`: `SpendingPlan::where('is_current', true)->first()?->load('items')`
- `monthlyExpenseTotals()`: `Expense::query()->whereNotNull(...)...`
- `saveRetirementSettings()`: `Profile::instance()->update([...])`
- Blade: Replace `Auth::user()->spendingPlans()->exists()` with `SpendingPlan::exists()`

**`resources/views/pages/expenses/⚡index.blade.php`** (~30 Auth references)
- `accounts()`: `ExpenseAccount::query()->orderBy('name')->get()`
- All query methods: `Expense::query()` instead of `Auth::user()->expenses()`
- All CRUD: remove ownership checks, remove `user_id` from creates
- Account CRUD: `ExpenseAccount::create([...])` (no user_id)
- CSV import: remove `Auth::id()` from `$importer->parse()` and `$importer->import()`
- Auto/bulk categorization: `Expense::query()` instead of `Auth::user()->expenses()`

**`resources/views/pages/spending-plans/⚡dashboard.blade.php`**
- `plans()`: `SpendingPlan::oldest()->with('items')->get()`
- `copyPlan()`: `app(CopySpendingPlan::class)($plan)` (no user param)
- `markAsCurrent()`: remove ownership check, use `SpendingPlan::where(...)` directly

**`resources/views/pages/spending-plans/⚡create.blade.php`**
- `createPlan()`: `SpendingPlan::count()` and `SpendingPlan::create(...)` (no user_id)

**`resources/views/pages/spending-plans/⚡show.blade.php`**
- Remove `abort_unless` ownership check in `mount()`
- `deletePlan()`: `SpendingPlan::count()`, `SpendingPlan::ensureCurrentPlan()`
- `copyPlan()`: no user param

**`resources/views/pages/spending-plans/⚡edit.blade.php`**
- Remove ownership checks in `mount()` and item mutations

**`resources/views/pages/net-worth/⚡index.blade.php`**
- `accounts()`: `NetWorthAccount::query()->orderBy(...)->get()`
- All CRUD: remove ownership checks, remove user_id

### 2H: Update Layout and Navigation

**`resources/views/layouts/app/sidebar.blade.php`**
- Remove admin section (conditional admin link)
- Remove `<x-desktop-user-menu>` component
- Remove mobile user menu dropdown (name, email, logout)
- Add a simple "Settings" nav item linking to appearance
- Remove all `auth()->user()` references

**`resources/views/pages/settings/layout.blade.php`**
- Remove Profile and Password navlist items
- Keep only Appearance

**`resources/views/pages/settings/⚡appearance.blade.php`**
- Update heading/subtitle to remove "profile and account" language

### 2I: Update Routes

**`routes/web.php`**

```php
Route::redirect('/', '/dashboard');
Route::view('/privacy', 'privacy')->name('privacy');
Route::get('/offline', fn () => view('offline'));
Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
require __DIR__.'/settings.php';
require __DIR__.'/spending-plans.php';
require __DIR__.'/net-worth.php';
require __DIR__.'/expenses.php';
```

- Remove: welcome route, dev/login, guest middleware, auth/verified middleware, admin require

**`routes/spending-plans.php`** — Remove `['auth', 'verified']` middleware wrapper

**`routes/net-worth.php`** — Remove `['auth', 'verified']` middleware wrapper

**`routes/expenses.php`** — Remove `['auth', 'verified']` middleware wrapper

**`routes/settings.php`** — Remove auth middleware, remove profile/password routes, keep only appearance

**Delete `routes/admin.php`**

### 2J: Update Factories and Seeder

Remove `'user_id' => User::factory()` from:
- `database/factories/SpendingPlanFactory.php`
- `database/factories/NetWorthAccountFactory.php`
- `database/factories/ExpenseFactory.php`
- `database/factories/ExpenseAccountFactory.php`
- `database/factories/RichLifeVisionFactory.php`

**`database/seeders/DatabaseSeeder.php`** — Remove User creation, remove `$user->id` from all creates.

### 2K: Update Tests

**Transform pattern for all remaining test files:**
- Remove `User::factory()->create()` and `$this->actingAs($user)`
- Remove `'user_id' => $user->id` from factory calls
- Remove "guests redirected to login" tests
- Remove "user cannot access another user's data" tests
- For dashboard: assert on `Profile::instance()` instead of `$user->refresh()`

**Test files to modify:**

| File |
|------|
| `tests/Feature/DashboardTest.php` (heaviest — ~45 user refs) |
| `tests/Feature/SpendingPlans/CreateSpendingPlanTest.php` |
| `tests/Feature/SpendingPlans/EditSpendingPlanTest.php` |
| `tests/Feature/SpendingPlans/ShowSpendingPlanTest.php` |
| `tests/Feature/SpendingPlans/SpendingPlanDashboardTest.php` |
| `tests/Feature/SpendingPlans/CopySpendingPlanTest.php` |
| `tests/Feature/SpendingPlans/CurrentSpendingPlanTest.php` |
| `tests/Feature/NetWorth/ManageNetWorthAccountsTest.php` |
| `tests/Feature/NetWorth/NetWorthCalculationTest.php` |
| `tests/Feature/Expenses/ManageExpensesTest.php` |
| `tests/Feature/Expenses/ManageExpenseAccountsTest.php` |
| `tests/Feature/Expenses/ImportExpensesTest.php` |

---

## Phase 3: Install and Configure NativePHP Electron

### 3A: Install

```bash
composer require nativephp/electron
php artisan native:install
```

This publishes `config/nativephp.php` and creates `app/Providers/NativeAppServiceProvider.php`.

### 3B: Configure NativeAppServiceProvider

```php
use Native\Laravel\Facades\Window;

public function boot(): void
{
    Window::open()
        ->title('Astute Money')
        ->width(1200)
        ->height(800)
        ->minWidth(900)
        ->minHeight(600);
}
```

### 3C: Configure `config/nativephp.php`

- App name: `Astute Money`
- SQLite database: NativePHP automatically places it in the app's data directory

### 3D: Environment Configuration

Desktop defaults:
- `SESSION_DRIVER=file`
- `CACHE_STORE=file`
- `QUEUE_CONNECTION=sync`
- `APP_ENV=local` for dev, `production` for builds

### 3E: Verify Locally

```bash
npm run build
php artisan native:serve
```

---

## Phase 4: Static Marketing Site (S3/CloudFront)

### 4A: Create `site/` Directory

```
site/
  index.html          # Marketing page (converted from welcome.blade.php)
  privacy.html        # Privacy policy (converted from privacy.blade.php)
  favicon.svg         # Copied from public/
  apple-touch-icon.png
```

### 4B: `site/index.html`

Convert `welcome.blade.php` to plain HTML:
- Use Tailwind CDN (`<script src="https://cdn.tailwindcss.com">`) — no build step for a 2-page site
- Replace `<flux:*>` components with equivalent HTML + Tailwind classes
- Replace Blade directives with hardcoded strings
- Inline the SVG logo
- Keep the dark theme, all sections, same copy
- Replace login/register CTAs with a single download link pointing to the GitHub Releases page:

```html
<div class="mt-8">
    <a href="https://github.com/OWNER/REPO/releases/latest"
       class="rounded-lg bg-emerald-500 px-6 py-2.5 text-base font-medium text-white">
        Download Latest Release
    </a>
</div>
```

### 4C: `site/privacy.html`

Convert `privacy.blade.php` to plain HTML. Update language for desktop:
- "Your data stays on your computer" (not "stored on our servers")
- Remove account deletion section (no accounts)
- Emphasize local-first, no-cloud architecture

### 4D: S3/CloudFront Setup

| Resource | Configuration |
|----------|--------------|
| S3 Bucket | Static site files only |
| CloudFront | Origin: S3 with OAC. Default root object: `index.html` |
| ACM Certificate | For custom domain (optional) |

---

## Phase 5: Release Workflow (GitHub Actions + Git Tags)

### 5A: Release Process

1. Tag a commit: `git tag v1.0.0 && git push origin v1.0.0`
2. GitHub Actions builds .dmg and .exe on platform-specific runners
3. Workflow creates a GitHub Release with the binaries attached
4. The static site links to `https://github.com/OWNER/REPO/releases/latest`

### 5B: `.github/workflows/release.yml`

```yaml
name: Release

on:
  push:
    tags:
      - 'v*'

permissions:
  contents: write

jobs:
  build:
    strategy:
      matrix:
        include:
          - os: macos-latest
            platform: mac
            artifact: '*.dmg'
          - os: windows-latest
            platform: win
            artifact: '*.exe'

    runs-on: ${{ matrix.os }}

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'

      - name: Install dependencies
        run: |
          composer install --no-interaction --prefer-dist
          npm ci

      - name: Build frontend assets
        run: npm run build

      - name: Build native app
        run: php artisan native:build ${{ matrix.platform }}

      - name: Upload build artifact
        uses: actions/upload-artifact@v4
        with:
          name: build-${{ matrix.platform }}
          path: dist/${{ matrix.artifact }}

  release:
    needs: build
    runs-on: ubuntu-latest

    steps:
      - name: Download all artifacts
        uses: actions/download-artifact@v4
        with:
          path: artifacts
          merge-multiple: true

      - name: Create GitHub Release
        uses: softprops/action-gh-release@v2
        with:
          generate_release_notes: true
          files: artifacts/*
```

macOS .dmg requires a macOS runner (code signing needs Apple tools). Windows .exe is most reliable on a Windows runner.

### 5C: Static Site Deploy Script

```bash
#!/usr/bin/env bash
set -euo pipefail

S3_BUCKET="${S3_BUCKET:?Set S3_BUCKET env var}"
CF_DISTRIBUTION_ID="${CF_DISTRIBUTION_ID:?Set CF_DISTRIBUTION_ID env var}"

echo "=== Deploying static site ==="
aws s3 sync site/ "s3://${S3_BUCKET}/" \
    --delete \
    --cache-control "max-age=3600"

echo "=== Invalidating CloudFront cache ==="
aws cloudfront create-invalidation \
    --distribution-id "${CF_DISTRIBUTION_ID}" \
    --paths "/*"

echo "=== Site deploy complete ==="
```

This script only handles the static marketing site. Binary releases are fully managed by the GitHub Actions workflow above.

---

## Phase 6: Cleanup

### 6A: Update CLAUDE.md
- Remove all Elastic Beanstalk documentation
- Remove auth-related patterns and gotchas
- Add NativePHP development patterns
- Document `Profile::instance()` pattern
- Update deploy instructions

### 6B: Delete Obsolete Files
- `ORACLE_MIGRATION_PLAN.md`

### 6C: Final Verification

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
php artisan native:serve  # manual smoke test
```

---

## Implementation Order

| Step | Phase | Scope | Est. Files |
|------|-------|-------|-----------|
| 1 | Phase 1 | Delete EB files, clean bootstrap/provider | ~15 |
| 2 | Phase 2A-2B | Remove Fortify, delete auth files | ~25 |
| 3 | Phase 2C-2D | Create Profile model, auth removal migration | 3 new |
| 4 | Phase 2E-2F | Update models and services | 7 |
| 5 | Phase 2G-2H | Update Livewire pages and layouts | ~10 |
| 6 | Phase 2I-2J | Update routes, factories, seeder | ~10 |
| 7 | Phase 2K | Update tests | ~12 |
| 8 | Phase 3 | Install NativePHP, configure | ~4 |
| 9 | Phase 4 | Create static site, S3/CloudFront setup | 4 new |
| 10 | Phase 5 | GitHub Actions release workflow | 2 new |
| 11 | Phase 6 | Docs, cleanup, final test | ~3 |

---

## Risks and Considerations

1. **NativePHP + Livewire** — NativePHP runs a local PHP server so Livewire should work normally. File uploads (CSV import) may need testing for temp file paths in Electron.

2. **SQLite path** — NativePHP manages the storage directory. Verify `database_path()` resolves correctly in the packaged app, or use NativePHP's storage API.

3. **No automatic data migration** — Moving from web (EFS SQLite) to desktop (local SQLite) has no automatic path. Consider adding a one-time SQLite import tool if existing data matters.

4. **Cross-platform fonts** — Font preloads use `Vite::asset()` which should work in the packaged build, but test on both platforms.

5. **NativePHP maturity** — Actively developed but relatively young. Pin to a specific version and test thoroughly.
