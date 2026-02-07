<?php

use App\Enums\SpendingCategory;
use App\Models\SpendingPlan;
use App\Models\SpendingPlanItem;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('owner can view their plan', function () {
    $user = User::factory()->create();
    $plan = SpendingPlan::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('spending-plans.show', $plan))
        ->assertOk();
});

test('user cannot view another users plan', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $plan = SpendingPlan::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($user)
        ->get(route('spending-plans.show', $plan))
        ->assertForbidden();
});

test('plan detail shows all categories', function () {
    $user = User::factory()->create();
    $plan = SpendingPlan::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)
        ->get(route('spending-plans.show', $plan));

    foreach (SpendingCategory::cases() as $category) {
        $response->assertSee($category->label());
    }
});

test('plan detail shows line items', function () {
    $user = User::factory()->create();
    $plan = SpendingPlan::factory()->create(['user_id' => $user->id]);

    SpendingPlanItem::factory()->create([
        'spending_plan_id' => $plan->id,
        'category' => SpendingCategory::FixedCosts,
        'name' => 'Monthly Rent',
        'amount' => 150000,
    ]);

    $this->actingAs($user)
        ->get(route('spending-plans.show', $plan))
        ->assertSee('Monthly Rent')
        ->assertSee('1,500');
});
