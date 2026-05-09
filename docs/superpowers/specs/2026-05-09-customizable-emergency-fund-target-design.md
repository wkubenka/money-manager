# Customizable Emergency Fund Target

## Problem

The Emergency Fund target on the dashboard is hardcoded to 6 months. Users following the IWTYTBR methodology may want a longer or shorter runway depending on job stability, household risk tolerance, or life stage.

## Goal

Let the user set the Emergency Fund target in months, defaulting to 6, with the input living directly on the Emergency Fund card on the dashboard. Also fix the runway calculation to use **monthly fixed costs** instead of monthly income — fixed costs are the genuine "must keep paying" floor an emergency fund needs to cover.

## Storage

Add `emergency_fund_months` to the `profile` table:

- Type: integer, unsigned
- Default: `6`
- Allowed range (validated, not enforced at DB level): `1`–`24`

The `Profile` model already exists as a single-row singleton accessed via `Profile::instance()`. Add the column to `$fillable` and cast to `integer` in `casts()`.

## UI: inline click-to-edit on the dashboard

Location: `resources/views/pages/⚡dashboard.blade.php`, inside the Emergency Fund block (currently lines ~689–718).

Today the card shows two strings containing "6":

- Top-right runway badge: `1.2 of 6 months`
- Below the dollar amount: `Goal: 6 months expenses`

Behavior:

- The runway badge stays read-only and reflects the configured target.
- The "6" inside `Goal: 6 months fixed costs` becomes the click-to-edit control. The label changes from "expenses" to "fixed costs" to match the new denominator. Default state renders the number as a subtle button-styled element (text color matches surrounding muted text, with a hover affordance — underline or slight background tint — to signal interactivity).
- On click, the number swaps to a small inline `<input type="number" min="1" max="24">` bound via `wire:model.lazy` to a public `emergencyFundMonths` property on the dashboard component.
- On blur (or Enter), the value persists. Invalid values revert to the previously saved value.
- An `aria-label` such as "Edit emergency fund target in months" is required since the trigger is a non-text control.

Visual ergonomics:

- The input width should fit 2 digits without layout shift relative to the static state.
- No save button; persistence is implicit on blur. This matches the inline-edit pattern used elsewhere in the app.

## Component changes

In the dashboard's inline `new class extends Component`:

1. Add a public `emergencyFundMonths` property, initialized in `mount()` from `Profile::instance()->emergency_fund_months`.
2. Add `updatedEmergencyFundMonths()` which:
   - Validates the value is an integer between 1 and 24.
   - On valid input: persists to `Profile::instance()` via `update(['emergency_fund_months' => …])`.
   - On invalid input: resets the property to the persisted value (no toast, no banner — just a silent revert, matching the low-friction UX).
3. Replace the runway denominator: instead of `$plan->monthly_income`, use `$plan->categoryTotal(\App\Enums\SpendingCategory::FixedCosts)`. Surface this as a local `$monthlyFixedCosts` variable. Runway is `$monthlyFixedCosts > 0 ? $efBal / $monthlyFixedCosts : null`. The progress-bar denominator becomes `$monthlyFixedCosts * $this->emergencyFundMonths`.
4. Replace the three hardcoded `6` literals in the Blade template (the `of 6 months` runway text, the goal label, and the progress-bar denominator multiplier) with `$this->emergencyFundMonths`. Update the goal label string from `"Goal: 6 months expenses"` to `"Goal: N months fixed costs"`.
5. If the spending plan has no fixed-costs items (`$monthlyFixedCosts === 0`), the runway badge and progress bar hide as they do today when income is 0. The card still renders with the balance and the editable goal target.

## Migration

A new migration adds the column with default `6`. Existing single-row profiles auto-receive the default; no data backfill needed beyond the column default.

## Tests

Update `tests/Feature/DashboardTest.php`:

- The Emergency Fund card uses 6 months when the profile has no override (default).
- Setting `emergency_fund_months = 3` on the profile changes the runway text (e.g., `… of 3 months`) and the goal label (`Goal: 3 months fixed costs`).
- Runway is computed against the active spending plan's Fixed Costs total, not `monthly_income`. A plan with $5,000 income and $2,000 in fixed-cost items yields a runway based on $2,000/month.
- When the spending plan has no fixed-costs items, the runway badge and progress bar are hidden but the goal target remains editable.
- Updating `emergencyFundMonths` on the Livewire component to a valid value persists it to the profile.
- Updating to an out-of-range value (`0`, `25`) reverts to the persisted value and does not write.

## Out of scope

- No exposure on the Net Worth page or Settings — this lives only on the dashboard for now.
- No per-account targets; the setting is global.
