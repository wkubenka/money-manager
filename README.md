# Astute Money

A personal-finance desktop app inspired by Ramit Sethi's *I Will Teach You To Be Rich*. Track your net worth, build a conscious spending plan, and model your way out of debt — all locally on your machine.

## Features

### Net Worth Tracker
Track your total net worth across four account categories:
- **Assets** (e.g. home, car)
- **Investments** (e.g. 401k, brokerage)
- **Savings** (e.g. emergency fund, high-yield savings)
- **Debt** (e.g. mortgage, student loans)

Net worth = Assets + Investments + Savings − Debt.

### Conscious Spending Plans
Allocate take-home income across four buckets with ideal percentage ranges:

| Category | Ideal Range |
|---|---|
| Fixed Costs | 50–60% |
| Investments | 10% |
| Savings | 5–10% |
| Guilt-Free Spending | 20–35% |

Plans support gross income and pre-tax investment tracking. One plan can be marked as **current** and is surfaced on the dashboard.

### Expense Tracking
Track daily expenses across multiple accounts (e.g. checking, credit cards):
- Add, edit, and delete individual expenses
- **CSV Import** — upload bank/credit-card exports; auto-detects date, merchant, and amount columns, filters duplicates, and auto-categorizes known merchants
- **Smart Categorization** — re-entering a known merchant auto-fills its category; categorizing from the uncategorized tab prompts to bulk-categorize all matching merchant expenses
- **Uncategorized Tab** — dedicated view for triaging imported expenses

### Debt Payoff Planner
Model multiple payoff strategies side-by-side:
- **Avalanche** (highest APR first) or **Snowball** (smallest balance first)
- Add extra monthly payments or one-time lump sums
- See projected payoff date, total interest paid, and savings vs. baseline
- Save and compare scenarios across sessions

### Dashboard
A unified view showing:
- **Critical Numbers** — runway, retirement projection, monthly spending vs. plan, debt-free date
- **Rich Life Vision** — categorized goals to remind you what you're saving for
- **Windfall Plan** — pre-set how to split unexpected income across savings, investments, debt, and guilt-free
- Net worth summary, current spending plan, and recent expenses

## Architecture

- **Pages** are inline Livewire components (`new class extends Component`) in `⚡`-prefixed Blade files
- **Services** (`app/Services/`) handle complex multi-step operations (e.g. CSV import, debt payoff calculations)
- **Actions** (`app/Actions/`) are single invokable classes for reusable business logic
- **Helpers** (`app/helpers.php`) provide global utilities like `format_cents()` and `sanitize_money_input()`
- All monetary values are stored as **cents** (integers) in the database
- The app is single-user — no auth layer; user-level fields live on a single-row `Profile` table accessed via `Profile::instance()`

## Tech Stack

- **Backend:** Laravel 12, PHP 8.3+
- **Frontend:** Livewire 4, Flux UI (Free), Tailwind CSS 4
- **Database:** SQLite (local, managed by NativePHP)
- **Desktop runtime:** NativePHP Electron
- **Testing:** Pest 3
- **Marketing site:** Plain HTML + Tailwind CDN in `site/`, deployed to S3/CloudFront

## Setup

```bash
git clone <repo-url> money-manager
cd money-manager
composer run setup
```

Installs dependencies, creates `.env`, generates an app key, runs migrations, and builds frontend assets.

## Development

### Web target
```bash
composer run dev
```
Starts the Laravel dev server, queue worker, log viewer, and Vite dev server concurrently.

### Desktop target
```bash
npm run build
php artisan native:serve
```
Boots the NativePHP Electron shell pointing at the app.

## Testing

```bash
php artisan test --compact
```

## Code Style

```bash
vendor/bin/pint
```

## Releasing

Tag a commit and push — GitHub Actions builds `.dmg` (macOS) and `.exe` (Windows) and publishes a GitHub Release:

```bash
git tag v1.4.0 && git push origin v1.4.0
```

## Marketing Site Deploy

```bash
S3_BUCKET=... CF_DISTRIBUTION_ID=... ./site/deploy.sh
```
