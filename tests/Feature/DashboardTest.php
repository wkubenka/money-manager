<?php

use App\Enums\AccountCategory;
use App\Enums\SpendingCategory;
use App\Models\Expense;
use App\Models\ExpenseAccount;
use App\Models\NetWorthAccount;
use App\Models\Profile;
use App\Models\RichLifeVision;
use App\Models\RichLifeVisionCategory;
use App\Models\SpendingPlan;
use App\Models\SpendingPlanItem;
use App\Models\WindfallPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('dashboard is accessible', function () {
    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard displays net worth', function () {
    NetWorthAccount::factory()->create([
        'category' => AccountCategory::Assets,
        'balance' => 50000000, // $500,000
    ]);
    NetWorthAccount::factory()->create([
        'category' => AccountCategory::Debt,
        'balance' => 20000000, // $200,000
    ]);

    Livewire::test('pages::dashboard')
        ->assertSee('Net Worth')
        ->assertSee('$300,000')
        ->assertSee('Assets')
        ->assertSee('Debt');
});

test('dashboard shows zero net worth with no accounts', function () {
    Livewire::test('pages::dashboard')
        ->assertSee('$0');
});

test('dashboard has manage accounts link', function () {
    Livewire::test('pages::dashboard')
        ->assertSeeHtml(route('net-worth.index'));
});

test('dashboard shows current spending plan', function () {
    $plan = SpendingPlan::factory()->current()->create([
        'name' => 'My Active Plan',
        'monthly_income' => 500000,
    ]);
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'amount' => 250000,
    ]);

    Livewire::test('pages::dashboard')
        ->assertSee('Spending Plan')
        ->assertSee('Fixed Costs');
});

test('dashboard shows create prompt when no plans exist', function () {
    Livewire::test('pages::dashboard')
        ->assertSee('Create your spending plan')
        ->assertSee('Get Started');
});

test('dashboard shows choose prompt when plans exist but none is current', function () {
    SpendingPlan::factory()->create([
        'is_current' => false,
    ]);

    Livewire::test('pages::dashboard')
        ->assertSee('No current spending plan')
        ->assertSee('Choose a Plan');
});

test('dashboard shows negative guilt-free spending in red', function () {
    $plan = SpendingPlan::factory()->current()->create([
        'monthly_income' => 500000, // $5,000
    ]);
    // Overspend: $3,000 + $1,500 + $1,000 = $5,500 > $5,000
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'amount' => 300000,
    ]);
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::Investments,
        'amount' => 150000,
    ]);
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::Savings,
        'amount' => 100000,
    ]);

    Livewire::test('pages::dashboard')
        ->assertSee('Guilt-Free')
        ->assertSeeHtml('text-vault-red');
});

test('dashboard renders with zero monthly income plan', function () {
    SpendingPlan::factory()->current()->create([
        'monthly_income' => 0,
    ]);

    Livewire::test('pages::dashboard')
        ->assertOk()
        ->assertSee('Spending Plan')
        ->assertSee('0%');
});

test('dashboard shows rounded percentages', function () {
    $plan = SpendingPlan::factory()->current()->create([
        'monthly_income' => 300000, // $3,000
        'fixed_costs_misc_percent' => 15,
    ]);
    // $1,000 items + $150 misc (15%) = $1,150 / $3,000 = 38.333...% → rounds to 38%
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'amount' => 100000,
    ]);

    Livewire::test('pages::dashboard')
        ->assertSee('38%');
});

test('dashboard does not show non-current plans', function () {
    SpendingPlan::factory()->create([
        'name' => 'Not Current Plan',
    ]);

    Livewire::test('pages::dashboard')
        ->assertDontSee('Not Current Plan')
        ->assertSee('No current spending plan');
});

test('dashboard shows emergency fund card', function () {
    $ef = NetWorthAccount::factory()->create([
        'name' => 'Emergency Fund',
        'category' => AccountCategory::Savings,
        'is_emergency_fund' => true,
        'balance' => 1500000, // $15,000
    ]);

    Livewire::test('pages::dashboard')
        ->assertSee('Emergency Fund')
        ->assertSee('$15,000');
});

test('dashboard shows emergency fund coverage months based on fixed costs', function () {
    NetWorthAccount::where('is_emergency_fund', true)->update(['balance' => 1500000]); // $15,000

    $plan = SpendingPlan::factory()->current()->create([
        'monthly_income' => 1000000, // $10,000 (deliberately different from fixed costs)
        'fixed_costs_misc_percent' => 0,
    ]);
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'amount' => 500000, // $5,000
    ]);

    // $15,000 / $5,000 = 3 months
    Livewire::test('pages::dashboard')
        ->assertSee('Emergency Fund')
        ->assertSee('3.0 months fixed costs')
        ->assertSeeInOrder(['Goal:', '6', 'months']);
});

test('dashboard hides emergency fund runway when plan has no fixed costs', function () {
    NetWorthAccount::where('is_emergency_fund', true)->update(['balance' => 1500000]);

    SpendingPlan::factory()->current()->create([
        'monthly_income' => 500000,
        'fixed_costs_misc_percent' => 0,
    ]);

    Livewire::test('pages::dashboard')
        ->assertSee('Emergency Fund')
        ->assertDontSee('months fixed costs');
});

test('dashboard emergency fund balance reflects only the dedicated fund', function () {
    NetWorthAccount::where('is_emergency_fund', true)->update(['balance' => 1500000]);

    // Other savings account that should NOT count toward the emergency fund card
    NetWorthAccount::factory()->create([
        'category' => AccountCategory::Savings,
        'balance' => 500000,
    ]);

    Livewire::test('pages::dashboard')
        ->assertSee('Emergency Fund')
        ->assertSee('$15,000');
});

test('dashboard shows months runway when emergency fund covers less than 2 months', function () {
    NetWorthAccount::where('is_emergency_fund', true)->update(['balance' => 300000]); // $3,000

    $plan = SpendingPlan::factory()->current()->create([
        'monthly_income' => 500000,
        'fixed_costs_misc_percent' => 0,
    ]);
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'amount' => 500000, // $5,000
    ]);

    // $3,000 / $5,000 = 0.6 months
    Livewire::test('pages::dashboard')
        ->assertSee('0.6 months fixed costs');
});

test('emergency fund runway uses one decimal even when small', function () {
    NetWorthAccount::where('is_emergency_fund', true)->update(['balance' => 230000]); // $2,300

    $plan = SpendingPlan::factory()->current()->create([
        'monthly_income' => 540000,
        'fixed_costs_misc_percent' => 0,
    ]);
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'amount' => 540000, // $5,400
    ]);

    // $2,300 / $5,400 ≈ 0.43 → renders as 0.4
    Livewire::test('pages::dashboard')
        ->assertSee('0.4 months fixed costs');
});

test('dashboard hides runway when no current plan exists', function () {
    NetWorthAccount::factory()->create([
        'name' => 'Emergency Fund',
        'category' => AccountCategory::Savings,
        'is_emergency_fund' => true,
        'balance' => 1000000,
    ]);

    Livewire::test('pages::dashboard')
        ->assertSee('Emergency Fund')
        ->assertDontSee('months fixed costs');
});

test('dashboard runway includes fixed-costs misc percentage', function () {
    NetWorthAccount::where('is_emergency_fund', true)->update(['balance' => 1100000]); // $11,000

    $plan = SpendingPlan::factory()->current()->create([
        'monthly_income' => 500000,
        'fixed_costs_misc_percent' => 10, // adds 10% on top of items
    ]);
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'amount' => 500000, // $5,000 → $5,500 with misc
    ]);

    // $11,000 / $5,500 = 2.0 months
    Livewire::test('pages::dashboard')
        ->assertSee('2.0 months fixed costs');
});

test('emergency fund target defaults to 6 months', function () {
    expect(Profile::instance()->emergency_fund_months)->toBe(6);
});

test('dashboard hydrates emergency fund target from profile', function () {
    Profile::instance()->update(['emergency_fund_months' => 9]);

    Livewire::test('pages::dashboard')
        ->assertSet('emergencyFundMonths', 9);
});

test('user can customize emergency fund target months', function () {
    NetWorthAccount::where('is_emergency_fund', true)->update(['balance' => 600000]); // $6,000

    $plan = SpendingPlan::factory()->current()->create([
        'monthly_income' => 500000,
        'fixed_costs_misc_percent' => 0,
    ]);
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'amount' => 200000, // $2,000
    ]);

    Profile::instance()->update(['emergency_fund_months' => 3]);

    // $6,000 / $2,000 = 3 → "3.0 months fixed costs"
    Livewire::test('pages::dashboard')
        ->assertSee('3.0 months fixed costs')
        ->assertSeeInOrder(['Goal:', '3', 'months']);
});

test('user can save emergency fund target via livewire', function () {
    Livewire::test('pages::dashboard')
        ->assertSet('emergencyFundMonths', 6)
        ->set('emergencyFundMonths', 9);

    expect(Profile::instance()->fresh()->emergency_fund_months)->toBe(9);
});

test('out-of-range emergency fund target reverts to saved value', function (int $invalid) {
    Profile::instance()->update(['emergency_fund_months' => 6]);

    Livewire::test('pages::dashboard')
        ->set('emergencyFundMonths', $invalid)
        ->assertSet('emergencyFundMonths', 6);

    expect(Profile::instance()->fresh()->emergency_fund_months)->toBe(6);
})->with([
    'below minimum' => 2,
    'zero' => 0,
    'above maximum' => 25,
]);

// Rich Life Vision tests

test('user can add a vision item', function () {
    Livewire::test('pages::dashboard')
        ->set('newVisionText', 'Travel the world')
        ->call('addVision')
        ->assertHasNoErrors()
        ->assertSet('newVisionText', '');

    $vision = RichLifeVision::first();
    expect($vision)->not->toBeNull();
    expect($vision->text)->toBe('Travel the world');
});

test('vision text is required', function () {
    Livewire::test('pages::dashboard')
        ->set('newVisionText', '')
        ->call('addVision')
        ->assertHasErrors(['newVisionText' => 'required']);
});

test('user can edit a vision item', function () {
    $vision = RichLifeVision::factory()->create([
        'text' => 'Old vision',
    ]);

    Livewire::test('pages::dashboard')
        ->call('editVision', $vision->id)
        ->set('editingVisionText', 'Updated vision')
        ->call('updateVision')
        ->assertHasNoErrors();

    expect($vision->refresh()->text)->toBe('Updated vision');
});

test('user can remove a vision item', function () {
    $vision = RichLifeVision::factory()->create();

    Livewire::test('pages::dashboard')
        ->call('removeVision', $vision->id)
        ->assertHasNoErrors();

    expect(RichLifeVision::find($vision->id))->toBeNull();
});

test('user can reorder vision items', function () {
    $a = RichLifeVision::factory()->create(['text' => 'First', 'sort_order' => 0]);
    $b = RichLifeVision::factory()->create(['text' => 'Second', 'sort_order' => 1]);
    $c = RichLifeVision::factory()->create(['text' => 'Third', 'sort_order' => 2]);

    Livewire::test('pages::dashboard')
        ->call('reorderVisions', [$c->id, $a->id, $b->id])
        ->assertHasNoErrors();

    expect($c->refresh()->sort_order)->toBe(0);
    expect($a->refresh()->sort_order)->toBe(1);
    expect($b->refresh()->sort_order)->toBe(2);
});

test('dashboard displays vision items', function () {
    RichLifeVision::factory()->create([
        'text' => 'Own a beach house',
    ]);

    Livewire::test('pages::dashboard')
        ->assertSee('Rich Life Vision')
        ->assertSee('Own a beach house');
});

test('vision list is locked by default and hides editing controls', function () {
    RichLifeVision::factory()->create([
        'text' => 'My vision',
    ]);

    Livewire::test('pages::dashboard')
        ->assertSet('visionEditing', false)
        ->assertSee('My vision')
        ->assertDontSeeHtml('wire:click="addVision(null)"')
        ->assertDontSeeHtml('wire:click="editVision');
});

test('unlocking vision list shows editing controls', function () {
    RichLifeVision::factory()->create([
        'text' => 'My vision',
    ]);

    Livewire::test('pages::dashboard')
        ->toggle('visionEditing')
        ->assertSet('visionEditing', true)
        ->assertSeeHtml('wire:click="$set(\'addVisionToCategoryId\', 0)"')
        ->assertSeeHtml('wire:click="editVision');
});

// Retirement Projection tests

test('dashboard shows retirement projection with known values', function () {
    Profile::instance()->update([
        'date_of_birth' => now()->subYears(30)->format('Y-m-d'),
        'retirement_age' => 65,
        'expected_return' => 7.0,
        'withdrawal_rate' => 4.0,
    ]);

    NetWorthAccount::factory()->create([
        'category' => AccountCategory::Investments,
        'balance' => 5000000, // $50,000
    ]);

    $plan = SpendingPlan::factory()->current()->create([
        'monthly_income' => 500000,
        'pre_tax_investments' => 50000, // $500/mo pre-tax
    ]);
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::Investments,
        'amount' => 50000, // $500/mo post-tax
    ]);

    // PV = 5,000,000 cents, PMT = 100,000 cents/mo
    // monthly rate = (1.07)^(1/12) - 1, n = 35*12 = 420 months
    // FV ≈ 224,524,270 cents → $2,245,243

    Livewire::test('pages::dashboard')
        ->assertSee('Est. at Retirement')
        ->assertSee('$2,245,243')
        ->assertSee('at age 65')
        ->assertSee('$7,484'); // $2,245,243 * 4% / 12 = $7,484
});

test('dashboard shows retirement card without projection when birthday not set', function () {
    Livewire::test('pages::dashboard')
        ->assertSee('Est. at Retirement')
        ->assertSee('Birthday')
        ->assertSee('Set your birthday and retirement age below.');
});

test('user can save retirement settings from dashboard', function () {
    Livewire::test('pages::dashboard')
        ->set('dateOfBirth', '1998-06-15')
        ->set('retirementAge', 60)
        ->set('expectedReturn', 8.0)
        ->set('withdrawalRate', 3.5)
        ->call('saveRetirementSettings')
        ->assertHasNoErrors();
});

test('retirement projection includes pre-tax investments', function () {
    Profile::instance()->update([
        'date_of_birth' => now()->subYears(30)->format('Y-m-d'),
        'retirement_age' => 65,
        'expected_return' => 0.0,
        'withdrawal_rate' => 4.0,
    ]);

    NetWorthAccount::factory()->create([
        'category' => AccountCategory::Investments,
        'balance' => 0,
    ]);

    $plan = SpendingPlan::factory()->current()->create([
        'monthly_income' => 500000,
        'pre_tax_investments' => 100000, // $1,000/mo pre-tax
    ]);
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::Investments,
        'amount' => 50000, // $500/mo post-tax
    ]);

    // 0% return: FV = 0 + $1,500/mo * 420 months = $630,000
    Livewire::test('pages::dashboard')
        ->assertSee('$630,000');
});

// Planned vs Actual Spending tests

test('dashboard shows actual spending vs planned for current month', function () {
    $plan = SpendingPlan::factory()->current()->create([
        'monthly_income' => 500000, // $5,000
        'fixed_costs_misc_percent' => 0,
    ]);
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'amount' => 250000, // $2,500
    ]);

    $account = ExpenseAccount::factory()->create();
    Expense::factory()->create([
        'expense_account_id' => $account->id,
        'category' => SpendingCategory::FixedCosts,
        'amount' => 100000, // $1,000
        'date' => now(),
    ]);

    Livewire::test('pages::dashboard')
        ->assertSeeInOrder(['$1,000 / $2,500', '$1,500 left']);
});

test('dashboard shows over spending in current month', function () {
    $plan = SpendingPlan::factory()->current()->create([
        'monthly_income' => 500000,
        'fixed_costs_misc_percent' => 0,
    ]);
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'amount' => 100000, // $1,000
    ]);

    $account = ExpenseAccount::factory()->create();
    Expense::factory()->create([
        'expense_account_id' => $account->id,
        'category' => SpendingCategory::FixedCosts,
        'amount' => 150000, // $1,500
        'date' => now(),
    ]);

    Livewire::test('pages::dashboard')
        ->assertSeeInOrder(['$500', 'over']);
});

test('dashboard excludes expenses from other months', function () {
    $plan = SpendingPlan::factory()->current()->create([
        'monthly_income' => 500000,
        'fixed_costs_misc_percent' => 0,
    ]);
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'amount' => 250000,
    ]);

    $account = ExpenseAccount::factory()->create();

    // Last month expense — should not be counted
    Expense::factory()->create([
        'expense_account_id' => $account->id,
        'category' => SpendingCategory::FixedCosts,
        'amount' => 200000,
        'date' => now()->subMonth(),
    ]);

    Livewire::test('pages::dashboard')
        ->assertSeeInOrder(['$0 / $2,500', '$2,500 left']);
});

// Vision Category tests

test('user can add a vision category', function () {
    Livewire::test('pages::dashboard')
        ->set('newCategoryName', 'Health & Wellness')
        ->call('addCategory')
        ->assertHasNoErrors()
        ->assertSet('newCategoryName', '');

    $category = RichLifeVisionCategory::first();
    expect($category)->not->toBeNull();
    expect($category->name)->toBe('Health & Wellness');
});

test('category name is required', function () {
    Livewire::test('pages::dashboard')
        ->set('newCategoryName', '')
        ->call('addCategory')
        ->assertHasErrors(['newCategoryName' => 'required']);
});

test('user can edit a vision category', function () {
    $category = RichLifeVisionCategory::factory()->create(['name' => 'Old Name']);

    Livewire::test('pages::dashboard')
        ->call('editCategory', $category->id)
        ->set('editingCategoryName', 'New Name')
        ->call('updateCategory')
        ->assertHasNoErrors();

    expect($category->refresh()->name)->toBe('New Name');
});

test('user can remove a vision category and visions become uncategorized', function () {
    $category = RichLifeVisionCategory::factory()->create();
    $vision = RichLifeVision::factory()->inCategory($category)->create();

    Livewire::test('pages::dashboard')
        ->call('removeCategory', $category->id)
        ->assertHasNoErrors();

    expect(RichLifeVisionCategory::find($category->id))->toBeNull();
    expect($vision->refresh()->rich_life_vision_category_id)->toBeNull();
});

test('user can reorder vision categories', function () {
    $a = RichLifeVisionCategory::factory()->create(['name' => 'First', 'sort_order' => 0]);
    $b = RichLifeVisionCategory::factory()->create(['name' => 'Second', 'sort_order' => 1]);
    $c = RichLifeVisionCategory::factory()->create(['name' => 'Third', 'sort_order' => 2]);

    Livewire::test('pages::dashboard')
        ->call('reorderCategories', [$c->id, $a->id, $b->id])
        ->assertHasNoErrors();

    expect($c->refresh()->sort_order)->toBe(0);
    expect($a->refresh()->sort_order)->toBe(1);
    expect($b->refresh()->sort_order)->toBe(2);
});

test('user can add a vision to a specific category', function () {
    $category = RichLifeVisionCategory::factory()->create();

    Livewire::test('pages::dashboard')
        ->set('newVisionText', 'Stay active')
        ->call('addVision', $category->id)
        ->assertHasNoErrors();

    $vision = RichLifeVision::first();
    expect($vision->text)->toBe('Stay active');
    expect($vision->rich_life_vision_category_id)->toBe($category->id);
});

test('user can add an uncategorized vision', function () {
    Livewire::test('pages::dashboard')
        ->set('newVisionText', 'Free spirit')
        ->call('addVision', null)
        ->assertHasNoErrors();

    $vision = RichLifeVision::first();
    expect($vision->text)->toBe('Free spirit');
    expect($vision->rich_life_vision_category_id)->toBeNull();
});

test('visions are grouped by category on display', function () {
    $cat = RichLifeVisionCategory::factory()->create(['name' => 'Travel & Experiences']);
    RichLifeVision::factory()->inCategory($cat)->create(['text' => 'Visit Japan']);
    RichLifeVision::factory()->create(['text' => 'Uncategorized vision']);

    Livewire::test('pages::dashboard')
        ->assertSee('Travel & Experiences')
        ->assertSee('Visit Japan')
        ->assertSee('Uncategorized vision');
});

test('deleting a category uncategorizes its visions', function () {
    $category = RichLifeVisionCategory::factory()->create();
    $v1 = RichLifeVision::factory()->inCategory($category)->create();
    $v2 = RichLifeVision::factory()->inCategory($category)->create();

    $category->delete();

    expect($v1->refresh()->rich_life_vision_category_id)->toBeNull();
    expect($v2->refresh()->rich_life_vision_category_id)->toBeNull();
});

// Debt Payoff tests

test('dashboard shows debt payoff card when debts and plan item exist', function () {
    NetWorthAccount::factory()->debt()->create([
        'name' => 'Credit Card',
        'balance' => 500000, // $5,000
        'minimum_payment' => 15000, // $150
        'interest_rate' => 20.0,
    ]);

    $plan = SpendingPlan::factory()->current()->create([
        'monthly_income' => 500000,
        'fixed_costs_misc_percent' => 0,
    ]);
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'name' => 'Debt Payments',
        'amount' => 50000, // $500/mo
    ]);

    Livewire::test('pages::dashboard')
        ->assertSee('Debt Free')
        ->assertSee('$5,000')
        ->assertSee('on current plan');
});

test('dashboard hides debt free card when no debts', function () {
    Livewire::test('pages::dashboard')
        ->assertDontSee('Debt Free');
});

test('dashboard shows prompt when debts exist but no plan item', function () {
    NetWorthAccount::factory()->debt()->create([
        'name' => 'Student Loan',
        'balance' => 2000000,
        'minimum_payment' => 20000,
        'interest_rate' => 6.5,
    ]);

    $plan = SpendingPlan::factory()->current()->create([
        'monthly_income' => 500000,
    ]);

    Livewire::test('pages::dashboard')
        ->assertSee('Debt Free')
        ->assertSee('Debt Payments');
});

test('dashboard hides debt payoff when debt accounts lack interest rate', function () {
    NetWorthAccount::factory()->create([
        'category' => AccountCategory::Debt,
        'balance' => 500000,
        'minimum_payment' => null,
        'interest_rate' => null,
    ]);

    Livewire::test('pages::dashboard')
        ->assertDontSee('Debt Payoff');
});

// Windfall Plan tests

test('windfall plan instance creates a single row with defaults', function () {
    $plan = WindfallPlan::instance();

    expect($plan->savings_percent)->toBe(30);
    expect($plan->investments_percent)->toBe(50);
    expect($plan->guilt_free_percent)->toBe(10);
    expect($plan->debt_percent)->toBe(10);
    expect(WindfallPlan::count())->toBe(1);

    WindfallPlan::instance();
    expect(WindfallPlan::count())->toBe(1);
});

test('dashboard does not show windfall plan card', function () {
    Livewire::test('pages::dashboard')
        ->assertDontSee('Windfall Plan');
});

test('dashboard hydrates windfall properties from saved plan', function () {
    WindfallPlan::instance()->update([
        'savings_percent' => 25,
        'investments_percent' => 40,
        'guilt_free_percent' => 20,
        'debt_percent' => 15,
    ]);

    Livewire::test('pages::dashboard')
        ->assertSet('windfallSavings', 25)
        ->assertSet('windfallInvestments', 40)
        ->assertSet('windfallGuiltFree', 20)
        ->assertSet('windfallDebt', 15);
});

test('user can save valid windfall plan splits', function () {
    Livewire::test('pages::dashboard')
        ->set('windfallEditing', true)
        ->set('windfallSavings', 20)
        ->set('windfallInvestments', 60)
        ->set('windfallGuiltFree', 15)
        ->set('windfallDebt', 5)
        ->call('saveWindfallPlan')
        ->assertHasNoErrors()
        ->assertSet('windfallEditing', false)
        ->assertDispatched('windfall-saved');

    $plan = WindfallPlan::instance();
    expect($plan->savings_percent)->toBe(20);
    expect($plan->investments_percent)->toBe(60);
    expect($plan->guilt_free_percent)->toBe(15);
    expect($plan->debt_percent)->toBe(5);
});

test('windfall splits must add up to 100', function () {
    Livewire::test('pages::dashboard')
        ->set('windfallEditing', true)
        ->set('windfallSavings', 30)
        ->set('windfallInvestments', 30)
        ->set('windfallGuiltFree', 30)
        ->set('windfallDebt', 30)
        ->call('saveWindfallPlan')
        ->assertHasErrors('windfallSavings')
        ->assertSet('windfallEditing', true);

    expect(WindfallPlan::instance()->savings_percent)->toBe(30);
});

test('windfall split values must be between 0 and 100', function (string $field) {
    Livewire::test('pages::dashboard')
        ->set($field, 150)
        ->call('saveWindfallPlan')
        ->assertHasErrors([$field => 'max']);
})->with([
    'savings' => 'windfallSavings',
    'investments' => 'windfallInvestments',
    'guilt-free' => 'windfallGuiltFree',
    'debt' => 'windfallDebt',
]);

test('cancelling windfall edit reverts properties to saved values', function () {
    WindfallPlan::instance()->update([
        'savings_percent' => 25,
        'investments_percent' => 40,
        'guilt_free_percent' => 20,
        'debt_percent' => 15,
    ]);

    Livewire::test('pages::dashboard')
        ->set('windfallEditing', true)
        ->set('windfallSavings', 99)
        ->set('windfallInvestments', 1)
        ->set('windfallGuiltFree', 0)
        ->set('windfallDebt', 0)
        ->call('cancelWindfall')
        ->assertSet('windfallEditing', false)
        ->assertSet('windfallSavings', 25)
        ->assertSet('windfallInvestments', 40)
        ->assertSet('windfallGuiltFree', 20)
        ->assertSet('windfallDebt', 15);
});
