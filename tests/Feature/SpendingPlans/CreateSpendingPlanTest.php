<?php

use App\Models\SpendingPlan;
use App\Models\User;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('create page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('spending-plans.create'))
        ->assertOk();
});

test('user can create a spending plan', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::spending-plans.create')
        ->set('name', 'My Plan')
        ->set('monthly_income', '5000.00')
        ->call('createPlan')
        ->assertHasNoErrors();

    expect(SpendingPlan::where('user_id', $user->id)->count())->toBe(1);
    expect(SpendingPlan::first()->name)->toBe('My Plan');
});

test('name is required', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::spending-plans.create')
        ->set('name', '')
        ->set('monthly_income', '5000.00')
        ->call('createPlan')
        ->assertHasErrors(['name' => 'required']);
});

test('monthly income is required', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::spending-plans.create')
        ->set('name', 'My Plan')
        ->set('monthly_income', '')
        ->call('createPlan')
        ->assertHasErrors(['monthly_income' => 'required']);
});

test('monthly income must be positive', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::spending-plans.create')
        ->set('name', 'My Plan')
        ->set('monthly_income', '0')
        ->call('createPlan')
        ->assertHasErrors(['monthly_income' => 'min']);
});

test('income is stored as cents', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::spending-plans.create')
        ->set('name', 'My Plan')
        ->set('monthly_income', '5000.00')
        ->call('createPlan');

    expect(SpendingPlan::first()->monthly_income)->toBe(500000);
});
