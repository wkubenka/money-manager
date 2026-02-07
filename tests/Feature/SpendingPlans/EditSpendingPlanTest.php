<?php

use App\Enums\SpendingCategory;
use App\Models\SpendingPlan;
use App\Models\SpendingPlanItem;
use App\Models\User;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('owner can view edit page', function () {
    $user = User::factory()->create();
    $plan = SpendingPlan::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('spending-plans.edit', $plan))
        ->assertOk();
});

test('user cannot edit another users plan', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $plan = SpendingPlan::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($user)
        ->get(route('spending-plans.edit', $plan))
        ->assertForbidden();
});

test('user can update plan name and income', function () {
    $user = User::factory()->create();
    $plan = SpendingPlan::factory()->create([
        'user_id' => $user->id,
        'name' => 'Old Name',
        'monthly_income' => 500000,
    ]);

    Livewire::actingAs($user)
        ->test('pages::spending-plans.edit', ['spendingPlan' => $plan])
        ->set('name', 'New Name')
        ->set('monthly_income', '6000.00')
        ->call('updatePlan')
        ->assertHasNoErrors();

    $plan->refresh();
    expect($plan->name)->toBe('New Name');
    expect($plan->monthly_income)->toBe(600000);
});

test('user can add a line item', function () {
    $user = User::factory()->create();
    $plan = SpendingPlan::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::spending-plans.edit', ['spendingPlan' => $plan])
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
    $user = User::factory()->create();
    $plan = SpendingPlan::factory()->create(['user_id' => $user->id]);
    $item = SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'name' => 'Old Item',
        'amount' => 100000,
    ]);

    Livewire::actingAs($user)
        ->test('pages::spending-plans.edit', ['spendingPlan' => $plan])
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
    $user = User::factory()->create();
    $plan = SpendingPlan::factory()->create(['user_id' => $user->id]);
    $item = SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::spending-plans.edit', ['spendingPlan' => $plan])
        ->call('removeItem', $item->id)
        ->assertHasNoErrors();

    expect(SpendingPlanItem::find($item->id))->toBeNull();
});

test('user can delete a plan from dashboard', function () {
    $user = User::factory()->create();
    $plan = SpendingPlan::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::spending-plans.dashboard')
        ->call('confirmDelete', $plan->id)
        ->call('deletePlan');

    expect(SpendingPlan::find($plan->id))->toBeNull();
});
