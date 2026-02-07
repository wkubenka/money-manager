# Money Manager

A personal finance app for tracking net worth and building conscious spending plans. Built with Laravel 12, Livewire 4, and Flux UI.

## Features

### Net Worth Tracker
Track your total net worth across four account categories:
- **Assets** (e.g. home, car)
- **Investments** (e.g. 401k, brokerage)
- **Savings** (e.g. emergency fund, high-yield savings)
- **Debt** (e.g. mortgage, student loans)

Net worth is calculated as Assets + Investments + Savings - Debt.

### Conscious Spending Plans
Create spending plans that allocate your take-home income into four buckets, each with ideal percentage ranges:

| Category | Ideal Range |
|---|---|
| Fixed Costs | 50-60% |
| Investments | 10% |
| Savings | 5-10% |
| Guilt-Free Spending | 20-35% |

Plans support gross income and pre-tax investment tracking. One plan can be marked as **current** and is displayed on the dashboard.

### Dashboard
A unified view showing:
- Net worth summary with category breakdown
- Current spending plan with category progress bars and item details

## Tech Stack

- **Backend:** Laravel 12, PHP 8.2+
- **Frontend:** Livewire 4, Flux UI, Tailwind CSS 4
- **Database:** SQLite
- **Testing:** Pest 3
- **Auth:** Laravel Fortify

## Setup

```bash
git clone <repo-url> money-manager
cd money-manager
composer run setup
```

This installs dependencies, creates your `.env`, generates an app key, runs migrations, and builds frontend assets.

## Development

```bash
composer run dev
```

Starts the Laravel dev server, queue worker, log viewer, and Vite dev server concurrently.

## Testing

```bash
php artisan test
```

## Code Style

```bash
vendor/bin/pint
```
