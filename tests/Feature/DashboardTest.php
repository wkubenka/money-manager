<?php

use App\Enums\AccountCategory;
use App\Enums\SpendingCategory;
use App\Models\NetWorthAccount;
use App\Models\RichLifeVision;
use App\Models\SpendingPlan;
use App\Models\SpendingPlanItem;
use App\Models\User;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard displays net worth', function () {
    $user = User::factory()->create();

    NetWorthAccount::factory()->create([
        'user_id' => $user->id,
        'category' => AccountCategory::Assets,
        'balance' => 50000000, // $500,000
    ]);
    NetWorthAccount::factory()->create([
        'user_id' => $user->id,
        'category' => AccountCategory::Debt,
        'balance' => 20000000, // $200,000
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Net Worth')
        ->assertSee('$300,000')
        ->assertSee('Assets')
        ->assertSee('Debt');
});

test('dashboard shows zero net worth with no accounts', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('$0');
});

test('dashboard has manage accounts link', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSeeHtml(route('net-worth.index'));
});

test('dashboard shows current spending plan', function () {
    $user = User::factory()->create();
    $plan = SpendingPlan::factory()->current()->create([
        'user_id' => $user->id,
        'name' => 'My Active Plan',
        'monthly_income' => 500000,
    ]);
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'amount' => 250000,
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Current Spending Plan')
        ->assertSee('Fixed Costs');
});

test('dashboard shows prompt when no current plan', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('No current spending plan')
        ->assertSee('Choose a Plan');
});

test('dashboard shows negative guilt-free spending in red', function () {
    $user = User::factory()->create();
    $plan = SpendingPlan::factory()->current()->create([
        'user_id' => $user->id,
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

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Guilt-Free Spending')
        ->assertSeeHtml('text-red-600');
});

test('dashboard renders with zero monthly income plan', function () {
    $user = User::factory()->create();
    SpendingPlan::factory()->current()->create([
        'user_id' => $user->id,
        'monthly_income' => 0,
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertOk()
        ->assertSee('Current Spending Plan')
        ->assertSee('0%');
});

test('dashboard shows rounded percentages', function () {
    $user = User::factory()->create();
    $plan = SpendingPlan::factory()->current()->create([
        'user_id' => $user->id,
        'monthly_income' => 300000, // $3,000
        'fixed_costs_misc_percent' => 15,
    ]);
    // $1,000 items + $150 misc (15%) = $1,150 / $3,000 = 38.333...% → rounds to 38.3%
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'amount' => 100000,
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('38.3%');
});

test('deleting a user cascades to spending plans and items', function () {
    $user = User::factory()->create();
    $plan = SpendingPlan::factory()->create(['user_id' => $user->id]);
    SpendingPlanItem::factory()->count(2)->create(['spending_plan_id' => $plan->id]);

    $userId = $user->id;
    $planId = $plan->id;

    $user->delete();

    expect(SpendingPlan::where('user_id', $userId)->count())->toBe(0);
    expect(SpendingPlanItem::where('spending_plan_id', $planId)->count())->toBe(0);
});

test('dashboard does not show non-current plans', function () {
    $user = User::factory()->create();
    SpendingPlan::factory()->create([
        'user_id' => $user->id,
        'name' => 'Not Current Plan',
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertDontSee('Not Current Plan')
        ->assertSee('No current spending plan');
});

test('dashboard shows emergency fund card', function () {
    $user = User::factory()->create();
    $ef = $user->emergencyFund();
    $ef->update(['balance' => 1500000]); // $15,000

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Emergency Fund')
        ->assertSee('$15,000');
});

test('dashboard shows emergency fund coverage months with current plan', function () {
    $user = User::factory()->create();
    $ef = $user->emergencyFund();
    $ef->update(['balance' => 1500000]); // $15,000

    $plan = SpendingPlan::factory()->current()->create([
        'user_id' => $user->id,
        'monthly_income' => 500000, // $5,000
        'fixed_costs_misc_percent' => 0,
    ]);
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'amount' => 250000, // $2,500
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Months of total spending')
        ->assertSee('3') // $15,000 / $5,000 = 3
        ->assertSee('Months of fixed costs')
        ->assertSee('6'); // $15,000 / $2,500 = 6
});

test('dashboard shows months of fixed costs using all savings', function () {
    $user = User::factory()->create();
    $ef = $user->emergencyFund();
    $ef->update(['balance' => 1500000]); // $15,000

    // Add another savings account
    NetWorthAccount::factory()->create([
        'user_id' => $user->id,
        'category' => AccountCategory::Savings,
        'balance' => 500000, // $5,000
    ]);

    $plan = SpendingPlan::factory()->current()->create([
        'user_id' => $user->id,
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
    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Months of total spending (all savings)')
        ->assertSee('4')
        ->assertSee('Months of fixed costs (all savings)')
        ->assertSee('8');
});

test('dashboard shows weeks when emergency fund covers less than 2 months', function () {
    $user = User::factory()->create();
    $ef = $user->emergencyFund();
    $ef->update(['balance' => 300000]); // $3,000

    $plan = SpendingPlan::factory()->current()->create([
        'user_id' => $user->id,
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
    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Weeks of total spending')
        ->assertSeeInOrder(['Weeks of total spending', '2'])
        ->assertSee('Weeks of fixed costs')
        ->assertSeeInOrder(['Weeks of fixed costs', '5']);
});

test('emergency fund weeks are not zero when fund covers less than one month', function () {
    $user = User::factory()->create();
    $ef = $user->emergencyFund();
    $ef->update(['balance' => 230000]); // $2,300

    SpendingPlan::factory()->current()->create([
        'user_id' => $user->id,
        'monthly_income' => 540000, // $5,400
        'fixed_costs_misc_percent' => 0,
    ]);

    // $2,300 / $5,400 = 0.43 months → floor(0.43 * 52/12) = 1 week (not 0)
    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Weeks of total spending')
        ->assertSeeInOrder(['Weeks of total spending', '1']);
});

test('dashboard shows prompt when no current plan for emergency fund', function () {
    $user = User::factory()->create();
    $ef = $user->emergencyFund();
    $ef->update(['balance' => 1000000]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Emergency Fund')
        ->assertSee('Set a current spending plan to see coverage months');
});

test('dashboard shows n/a for fixed costs when zero', function () {
    $user = User::factory()->create();
    $ef = $user->emergencyFund();
    $ef->update(['balance' => 1000000]);

    SpendingPlan::factory()->current()->create([
        'user_id' => $user->id,
        'monthly_income' => 500000,
        'fixed_costs_misc_percent' => 0,
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Months of total spending')
        ->assertSee('2') // $10,000 / $5,000 = 2
        ->assertSee('N/A'); // no fixed costs items
});

// Rich Life Vision tests

test('user can add a vision item', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->set('newVisionText', 'Travel the world')
        ->call('addVision')
        ->assertHasNoErrors()
        ->assertSet('newVisionText', '');

    $vision = RichLifeVision::where('user_id', $user->id)->first();
    expect($vision)->not->toBeNull();
    expect($vision->text)->toBe('Travel the world');
});

test('vision text is required', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->set('newVisionText', '')
        ->call('addVision')
        ->assertHasErrors(['newVisionText' => 'required']);
});

test('user can edit a vision item', function () {
    $user = User::factory()->create();
    $vision = RichLifeVision::factory()->create([
        'user_id' => $user->id,
        'text' => 'Old vision',
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->call('editVision', $vision->id)
        ->set('editingVisionText', 'Updated vision')
        ->call('updateVision')
        ->assertHasNoErrors();

    expect($vision->refresh()->text)->toBe('Updated vision');
});

test('user can remove a vision item', function () {
    $user = User::factory()->create();
    $vision = RichLifeVision::factory()->create([
        'user_id' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->call('removeVision', $vision->id)
        ->assertHasNoErrors();

    expect(RichLifeVision::find($vision->id))->toBeNull();
});

test('user cannot edit another users vision', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $vision = RichLifeVision::factory()->create([
        'user_id' => $otherUser->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->call('editVision', $vision->id)
        ->assertForbidden();
});

test('user cannot remove another users vision', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $vision = RichLifeVision::factory()->create([
        'user_id' => $otherUser->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->call('removeVision', $vision->id)
        ->assertForbidden();

    expect(RichLifeVision::find($vision->id))->not->toBeNull();
});

test('user can reorder vision items', function () {
    $user = User::factory()->create();
    $a = RichLifeVision::factory()->create(['user_id' => $user->id, 'text' => 'First', 'sort_order' => 0]);
    $b = RichLifeVision::factory()->create(['user_id' => $user->id, 'text' => 'Second', 'sort_order' => 1]);
    $c = RichLifeVision::factory()->create(['user_id' => $user->id, 'text' => 'Third', 'sort_order' => 2]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->call('reorderVisions', [$c->id, $a->id, $b->id])
        ->assertHasNoErrors();

    expect($c->refresh()->sort_order)->toBe(0);
    expect($a->refresh()->sort_order)->toBe(1);
    expect($b->refresh()->sort_order)->toBe(2);
});

test('user cannot reorder another users visions', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $vision = RichLifeVision::factory()->create(['user_id' => $otherUser->id, 'sort_order' => 0]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->call('reorderVisions', [$vision->id])
        ->assertForbidden();
});

test('dashboard displays vision items', function () {
    $user = User::factory()->create();
    RichLifeVision::factory()->create([
        'user_id' => $user->id,
        'text' => 'Own a beach house',
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Rich Life Vision')
        ->assertSee('Own a beach house');
});

test('vision list is locked by default and hides editing controls', function () {
    $user = User::factory()->create();
    RichLifeVision::factory()->create([
        'user_id' => $user->id,
        'text' => 'My vision',
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSet('visionEditing', false)
        ->assertSee('My vision')
        ->assertDontSeeHtml('wire:click="addVision"')
        ->assertDontSeeHtml('wire:click="editVision');
});

test('unlocking vision list shows editing controls', function () {
    $user = User::factory()->create();
    RichLifeVision::factory()->create([
        'user_id' => $user->id,
        'text' => 'My vision',
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->toggle('visionEditing')
        ->assertSet('visionEditing', true)
        ->assertSeeHtml('wire:click="addVision"')
        ->assertSeeHtml('wire:click="editVision');
});

// Retirement Projection tests

test('dashboard shows retirement projection with known values', function () {
    $user = User::factory()->create([
        'date_of_birth' => now()->subYears(30)->format('Y-m-d'),
        'retirement_age' => 65,
        'expected_return' => 7.0,
        'withdrawal_rate' => 4.0,
    ]);

    NetWorthAccount::factory()->create([
        'user_id' => $user->id,
        'category' => AccountCategory::Investments,
        'balance' => 5000000, // $50,000
    ]);

    $plan = SpendingPlan::factory()->current()->create([
        'user_id' => $user->id,
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

    Livewire::actingAs($user)
        ->test('pages::dashboard')
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
    $user = User::factory()->create([
        'date_of_birth' => null,
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Investments at Retirement')
        ->assertSee('Birthday')
        ->assertDontSee('Years until retirement');
});

test('user can save retirement settings from dashboard', function () {
    $user = User::factory()->create([
        'date_of_birth' => null,
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->set('dateOfBirth', '1998-06-15')
        ->set('retirementAge', 60)
        ->set('expectedReturn', 8.0)
        ->set('withdrawalRate', 3.5)
        ->call('saveRetirementSettings')
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->date_of_birth->format('Y-m-d'))->toBe('1998-06-15');
    expect($user->retirement_age)->toBe(60);
    expect((float) $user->expected_return)->toBe(8.0);
    expect((float) $user->withdrawal_rate)->toBe(3.5);
});

test('retirement projection includes pre-tax investments', function () {
    $user = User::factory()->create([
        'date_of_birth' => now()->subYears(30)->format('Y-m-d'),
        'retirement_age' => 65,
        'expected_return' => 0.0, // 0% return for simple math
    ]);

    NetWorthAccount::factory()->create([
        'user_id' => $user->id,
        'category' => AccountCategory::Investments,
        'balance' => 0,
    ]);

    $plan = SpendingPlan::factory()->current()->create([
        'user_id' => $user->id,
        'monthly_income' => 500000,
        'pre_tax_investments' => 100000, // $1,000/mo pre-tax
    ]);
    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::Investments,
        'amount' => 50000, // $500/mo post-tax
    ]);

    // 0% return: FV = 0 + $1,500/mo * 420 months = $630,000
    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('$630,000');
});
