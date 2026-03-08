<?php

use App\Enums\SpendingCategory;
use App\Models\SpendingPlan;
use App\Models\SpendingPlanItem;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('edit page is accessible', function () {
    $plan = SpendingPlan::factory()->create();

    $this->get(route('spending-plans.edit', $plan))
        ->assertOk();
});

test('user can update plan name and income', function () {
    $plan = SpendingPlan::factory()->create([
        'name' => 'Old Name',
        'monthly_income' => 500000,
    ]);

    Livewire::test('pages::spending-plans.edit', ['spendingPlan' => $plan])
        ->set('name', 'New Name')
        ->set('monthly_income', '6000.00')
        ->call('updatePlan')
        ->assertHasNoErrors();

    $plan->refresh();
    expect($plan->name)->toBe('New Name');
    expect($plan->monthly_income)->toBe(600000);
});

test('user can update gross income and pre-tax investments', function () {
    $plan = SpendingPlan::factory()->create([
        'gross_monthly_income' => 0,
        'pre_tax_investments' => 0,
    ]);

    Livewire::test('pages::spending-plans.edit', ['spendingPlan' => $plan])
        ->set('gross_monthly_income', '8000.00')
        ->set('pre_tax_investments', '750.00')
        ->call('updatePlan')
        ->assertHasNoErrors();

    $plan->refresh();
    expect($plan->gross_monthly_income)->toBe(800000);
    expect($plan->pre_tax_investments)->toBe(75000);
});

test('user can update miscellaneous percentage', function () {
    $plan = SpendingPlan::factory()->create([
        'fixed_costs_misc_percent' => 15,
    ]);

    Livewire::test('pages::spending-plans.edit', ['spendingPlan' => $plan])
        ->set('fixed_costs_misc_percent', '10')
        ->call('updatePlan')
        ->assertHasNoErrors();

    $plan->refresh();
    expect($plan->fixed_costs_misc_percent)->toBe(10);
});

test('miscellaneous percentage can be set to zero', function () {
    $plan = SpendingPlan::factory()->create([
        'fixed_costs_misc_percent' => 15,
    ]);

    Livewire::test('pages::spending-plans.edit', ['spendingPlan' => $plan])
        ->set('fixed_costs_misc_percent', '0')
        ->call('updatePlan')
        ->assertHasNoErrors();

    $plan->refresh();
    expect($plan->fixed_costs_misc_percent)->toBe(0);
});

test('miscellaneous percentage cannot exceed 30', function () {
    $plan = SpendingPlan::factory()->create([
        'fixed_costs_misc_percent' => 15,
    ]);

    Livewire::test('pages::spending-plans.edit', ['spendingPlan' => $plan])
        ->set('fixed_costs_misc_percent', '31')
        ->call('updatePlan')
        ->assertHasErrors(['fixed_costs_misc_percent' => 'max']);

    $plan->refresh();
    expect($plan->fixed_costs_misc_percent)->toBe(15);
});

test('user can add a line item', function () {
    $plan = SpendingPlan::factory()->create();

    Livewire::test('pages::spending-plans.edit', ['spendingPlan' => $plan])
        ->set('newItemNames.fixed_costs', 'Rent')
        ->set('newItemAmounts.fixed_costs', '1500.00')
        ->call('addItem', 'fixed_costs')
        ->assertHasNoErrors();

    $plan->refresh();
    expect($plan->items()->count())->toBe(1);

    $item = $plan->items->first();
    expect($item->name)->toBe('Rent');
    expect($item->amount)->toBe(150000);
    expect($item->category)->toBe(SpendingCategory::FixedCosts);
});

test('user can update a line item', function () {
    $plan = SpendingPlan::factory()->create();
    $item = SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'name' => 'Old Item',
        'amount' => 100000,
    ]);

    Livewire::test('pages::spending-plans.edit', ['spendingPlan' => $plan])
        ->call('editItem', $item->id)
        ->set('editingItemName', 'Updated Item')
        ->set('editingItemAmount', '2000.00')
        ->call('updateItem')
        ->assertHasNoErrors();

    $item->refresh();
    expect($item->name)->toBe('Updated Item');
    expect($item->amount)->toBe(200000);
});

test('user can remove a line item', function () {
    $plan = SpendingPlan::factory()->create();
    $item = SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
    ]);

    Livewire::test('pages::spending-plans.edit', ['spendingPlan' => $plan])
        ->call('removeItem', $item->id)
        ->assertHasNoErrors();

    expect(SpendingPlanItem::find($item->id))->toBeNull();
});

test('deleting a plan cascades to its items', function () {
    $plan = SpendingPlan::factory()->create();
    SpendingPlanItem::factory()->count(3)->create(['spending_plan_id' => $plan->id]);

    expect(SpendingPlanItem::where('spending_plan_id', $plan->id)->count())->toBe(3);

    $plan->delete();

    expect(SpendingPlanItem::where('spending_plan_id', $plan->id)->count())->toBe(0);
});

test('negative gross income is rejected on create', function () {
    Livewire::test('pages::spending-plans.create')
        ->set('name', 'My Plan')
        ->set('monthly_income', '5000.00')
        ->set('gross_monthly_income', '-1000.00')
        ->call('createPlan')
        ->assertHasErrors(['gross_monthly_income' => 'min']);
});

test('negative pre-tax investments is rejected on create', function () {
    Livewire::test('pages::spending-plans.create')
        ->set('name', 'My Plan')
        ->set('monthly_income', '5000.00')
        ->set('pre_tax_investments', '-500.00')
        ->call('createPlan')
        ->assertHasErrors(['pre_tax_investments' => 'min']);
});

test('negative gross income is rejected on edit', function () {
    $plan = SpendingPlan::factory()->create();

    Livewire::test('pages::spending-plans.edit', ['spendingPlan' => $plan])
        ->set('name', 'Test')
        ->set('monthly_income', '5000.00')
        ->set('gross_monthly_income', '-1000.00')
        ->call('updatePlan')
        ->assertHasErrors(['gross_monthly_income' => 'min']);
});

test('negative pre-tax investments is rejected on edit', function () {
    $plan = SpendingPlan::factory()->create();

    Livewire::test('pages::spending-plans.edit', ['spendingPlan' => $plan])
        ->set('name', 'Test')
        ->set('monthly_income', '5000.00')
        ->set('pre_tax_investments', '-500.00')
        ->call('updatePlan')
        ->assertHasErrors(['pre_tax_investments' => 'min']);
});

test('new items get incrementing sort order within category', function () {
    $plan = SpendingPlan::factory()->create();

    $component = Livewire::test('pages::spending-plans.edit', ['spendingPlan' => $plan]);

    $component
        ->set('newItemNames.fixed_costs', 'Rent')
        ->set('newItemAmounts.fixed_costs', '1500.00')
        ->call('addItem', 'fixed_costs');

    $component
        ->set('newItemNames.fixed_costs', 'Utilities')
        ->set('newItemAmounts.fixed_costs', '200.00')
        ->call('addItem', 'fixed_costs');

    $component
        ->set('newItemNames.investments', '401k')
        ->set('newItemAmounts.investments', '500.00')
        ->call('addItem', 'investments');

    $plan->refresh();
    $fixedItems = $plan->items()->where('category', 'fixed_costs')->orderBy('sort_order')->get();
    expect($fixedItems[0]->sort_order)->toBe(0);
    expect($fixedItems[1]->sort_order)->toBe(1);

    $investmentItems = $plan->items()->where('category', 'investments')->get();
    expect($investmentItems[0]->sort_order)->toBe(0);
});

test('user can reorder items within a category', function () {
    $plan = SpendingPlan::factory()->create();

    $itemA = SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => 'fixed_costs',
        'name' => 'Rent',
        'sort_order' => 0,
    ]);
    $itemB = SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => 'fixed_costs',
        'name' => 'Utilities',
        'sort_order' => 1,
    ]);
    $itemC = SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => 'fixed_costs',
        'name' => 'Insurance',
        'sort_order' => 2,
    ]);

    Livewire::test('pages::spending-plans.edit', ['spendingPlan' => $plan])
        ->call('reorderItems', 'fixed_costs', [$itemC->id, $itemA->id, $itemB->id])
        ->assertHasNoErrors();

    expect($itemC->refresh()->sort_order)->toBe(0);
    expect($itemA->refresh()->sort_order)->toBe(1);
    expect($itemB->refresh()->sort_order)->toBe(2);
});

test('reorder rejects guilt-free category', function () {
    $plan = SpendingPlan::factory()->create();

    Livewire::test('pages::spending-plans.edit', ['spendingPlan' => $plan])
        ->call('reorderItems', 'guilt_free', [])
        ->assertStatus(422);
});

test('user cannot add more than max items per category', function () {
    $plan = SpendingPlan::factory()->create();

    SpendingPlanItem::factory()->count(SpendingPlan::MAX_ITEMS_PER_CATEGORY)->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
    ]);

    Livewire::test('pages::spending-plans.edit', ['spendingPlan' => $plan])
        ->set('newItemNames.fixed_costs', 'One Too Many')
        ->set('newItemAmounts.fixed_costs', '100.00')
        ->call('addItem', 'fixed_costs')
        ->assertStatus(422);

    expect($plan->items()->where('category', 'fixed_costs')->count())->toBe(SpendingPlan::MAX_ITEMS_PER_CATEGORY);
});

test('user can delete a plan from show page', function () {
    $plan = SpendingPlan::factory()->create();
    SpendingPlan::factory()->current()->create();

    Livewire::test('pages::spending-plans.show', ['spendingPlan' => $plan])
        ->set('confirmingDelete', true)
        ->call('deletePlan');

    expect(SpendingPlan::find($plan->id))->toBeNull();
});
