<?php

use App\Models\Expense;
use App\Models\ExpenseAccount;
use App\Models\SpendingPlan;
use App\Models\User;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

const ADMIN_EMAIL = 'admin@test.com';

beforeEach(function () {
    config(['admin.emails' => [ADMIN_EMAIL]]);
});

function createAdmin(): User
{
    return User::factory()->create(['email' => ADMIN_EMAIL]);
}

test('guests are redirected to the login page', function () {
    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));
});

test('non-admin users receive 403', function () {
    $user = User::factory()->create(['email' => 'regular@example.com']);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('admin user can access the dashboard', function () {
    $admin = createAdmin();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk();
});

test('admin dashboard displays user count', function () {
    $admin = createAdmin();
    User::factory()->count(3)->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.dashboard')
        ->assertSee('Users')
        ->assertSee('4');
});

test('admin dashboard displays expense metrics', function () {
    $admin = createAdmin();
    $account = ExpenseAccount::factory()->create(['user_id' => $admin->id]);

    Expense::factory()->count(2)->imported()->create([
        'user_id' => $admin->id,
        'expense_account_id' => $account->id,
    ]);
    Expense::factory()->create([
        'user_id' => $admin->id,
        'expense_account_id' => $account->id,
        'is_imported' => false,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.dashboard')
        ->assertSee('Expenses')
        ->assertSee('2 imported')
        ->assertSee('1 manual');
});

test('admin dashboard displays spending plan metrics', function () {
    $admin = createAdmin();
    SpendingPlan::factory()->count(3)->create(['user_id' => $admin->id]);
    SpendingPlan::factory()->current()->create(['user_id' => $admin->id]);

    Livewire::actingAs($admin)
        ->test('pages::admin.dashboard')
        ->assertSee('Spending Plans')
        ->assertSee('4')
        ->assertSee('1 marked current');
});

test('admin dashboard displays recent registrations', function () {
    $admin = createAdmin();
    User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    Livewire::actingAs($admin)
        ->test('pages::admin.dashboard')
        ->assertSee('Recent Registrations')
        ->assertSee('Jane Doe')
        ->assertSee('jane@example.com');
});

test('admin dashboard shows empty state for errors when log is missing', function () {
    $logPath = storage_path('logs/laravel.log');
    $backup = null;

    if (file_exists($logPath)) {
        $backup = file_get_contents($logPath);
        unlink($logPath);
    }

    try {
        $admin = createAdmin();

        Livewire::actingAs($admin)
            ->test('pages::admin.dashboard')
            ->assertSee('No recent errors.');
    } finally {
        if ($backup !== null) {
            file_put_contents($logPath, $backup);
        }
    }
});

test('admin link is visible in sidebar for admin user', function () {
    $admin = createAdmin();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertSee('Admin');
});

test('admin link is not visible in sidebar for regular user', function () {
    $user = User::factory()->create(['email' => 'regular@example.com']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertDontSee(route('admin.dashboard'));
});
