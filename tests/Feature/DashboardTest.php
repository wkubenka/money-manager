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
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

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
        ->assertSee('Current Spending Plan')
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
        ->assertSeeHtml('text-red-600');
});

test('dashboard renders with zero monthly income plan', function () {
    SpendingPlan::factory()->current()->create([
        'monthly_income' => 0,
    ]);

    Livewire::test('pages::dashboard')
        ->assertOk()
        ->assertSee('Current Spending Plan')
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

test('dashboard shows emergency fund coverage months with current plan', function () {
    $ef = NetWorthAccount::factory()->create([
        'name' => 'Emergency Fund',
        'category' => AccountCategory::Savings,
        'is_emergency_fund' => true,
        'balance' => 1500000, // $15,000
    ]);

    $plan = SpendingPlan::factory()->current()->create([
        'monthly_income' => 500000, // $5,000
        'fixed_costs_misc_percent' => 0,
    ]);
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'amount' => 250000, // $2,500
    ]);

    Livewire::test('pages::dashboard')
        ->assertSee('Months of total spending')
        ->assertSee('3') // $15,000 / $5,000 = 3
        ->assertSee('Months of fixed costs')
        ->assertSee('6'); // $15,000 / $2,500 = 6
});

test('dashboard shows months of fixed costs using all savings', function () {
    $ef = NetWorthAccount::factory()->create([
        'name' => 'Emergency Fund',
        'category' => AccountCategory::Savings,
        'is_emergency_fund' => true,
        'balance' => 1500000, // $15,000
    ]);

    // Add another savings account
    NetWorthAccount::factory()->create([
        'category' => AccountCategory::Savings,
        'balance' => 500000, // $5,000
    ]);

    $plan = SpendingPlan::factory()->current()->create([
        'monthly_income' => 500000,
        'fixed_costs_misc_percent' => 0,
    ]);
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'amount' => 250000, // $2,500
    ]);

    // Total savings: $15,000 + $5,000 = $20,000
    // $20,000 / $5,000 monthly income = 4 months total spending
    // $20,000 / $2,500 fixed costs = 8 months fixed costs
    Livewire::test('pages::dashboard')
        ->assertSee('Months of total spending (all savings)')
        ->assertSee('4')
        ->assertSee('Months of fixed costs (all savings)')
        ->assertSee('8');
});

test('dashboard shows weeks when emergency fund covers less than 2 months', function () {
    $ef = NetWorthAccount::factory()->create([
        'name' => 'Emergency Fund',
        'category' => AccountCategory::Savings,
        'is_emergency_fund' => true,
        'balance' => 300000, // $3,000
    ]);

    $plan = SpendingPlan::factory()->current()->create([
        'monthly_income' => 500000, // $5,000
        'fixed_costs_misc_percent' => 0,
    ]);
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'amount' => 250000, // $2,500
    ]);

    // $3,000 / $5,000 = 0.6 months total spending → floor(0.6 * 52/12) = 2 weeks
    // $3,000 / $2,500 = 1.2 months fixed costs → floor(1.2 * 52/12) = 5 weeks
    Livewire::test('pages::dashboard')
        ->assertSee('Weeks of total spending')
        ->assertSeeInOrder(['Weeks of total spending', '2'])
        ->assertSee('Weeks of fixed costs')
        ->assertSeeInOrder(['Weeks of fixed costs', '5']);
});

test('emergency fund weeks are not zero when fund covers less than one month', function () {
    $ef = NetWorthAccount::factory()->create([
        'name' => 'Emergency Fund',
        'category' => AccountCategory::Savings,
        'is_emergency_fund' => true,
        'balance' => 230000, // $2,300
    ]);

    SpendingPlan::factory()->current()->create([
        'monthly_income' => 540000, // $5,400
        'fixed_costs_misc_percent' => 0,
    ]);

    // $2,300 / $5,400 = 0.43 months → floor(0.43 * 52/12) = 1 week (not 0)
    Livewire::test('pages::dashboard')
        ->assertSee('Weeks of total spending')
        ->assertSeeInOrder(['Weeks of total spending', '1']);
});

test('dashboard shows prompt when no current plan for emergency fund', function () {
    $ef = NetWorthAccount::factory()->create([
        'name' => 'Emergency Fund',
        'category' => AccountCategory::Savings,
        'is_emergency_fund' => true,
        'balance' => 1000000,
    ]);

    Livewire::test('pages::dashboard')
        ->assertSee('Emergency Fund')
        ->assertSee('Set a current spending plan to see coverage months');
});

test('dashboard shows n/a for fixed costs when zero', function () {
    $ef = NetWorthAccount::factory()->create([
        'name' => 'Emergency Fund',
        'category' => AccountCategory::Savings,
        'is_emergency_fund' => true,
        'balance' => 1000000,
    ]);

    SpendingPlan::factory()->current()->create([
        'monthly_income' => 500000,
        'fixed_costs_misc_percent' => 0,
    ]);

    Livewire::test('pages::dashboard')
        ->assertSee('Months of total spending')
        ->assertSee('2') // $10,000 / $5,000 = 2
        ->assertSee('N/A'); // no fixed costs items
});

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
        ->assertSeeHtml('wire:click="addVision(null)"')
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
        ->assertSee('Est. Investments at Retirement')
        ->assertSee('$2,245,243')
        ->assertSee('Current investments')
        ->assertSee('$50,000')
        ->assertSee('Monthly contributions')
        ->assertSee('$1,000')
        ->assertSee('Years until retirement')
        ->assertSeeInOrder(['Years until retirement', '35'])
        ->assertSee('Safe monthly withdrawal')
        ->assertSee('$7,484'); // $2,245,243 * 4% / 12 = $7,484
});

test('dashboard shows retirement card without projection when birthday not set', function () {
    Livewire::test('pages::dashboard')
        ->assertSee('Investments at Retirement')
        ->assertSee('Birthday')
        ->assertDontSee('Years until retirement');
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
        ->assertSee('Spent')
        ->assertSeeInOrder(['Spent: $1,000', '$1,500', 'left']);
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
        ->assertSee('Spent: $0')
        ->assertSee('$2,500')
        ->assertSee('left');
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
        ->assertSee('Debt Payoff')
        ->assertSee('Total debt')
        ->assertSee('$5,000')
        ->assertSee('Monthly payment')
        ->assertSee('Months remaining');
});

test('dashboard hides debt payoff card when no debts', function () {
    Livewire::test('pages::dashboard')
        ->assertDontSee('Debt Payoff');
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
        ->assertSee('Debt Payoff')
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
