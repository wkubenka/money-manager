<?php

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('appearance settings page is accessible', function () {
    $this->get(route('appearance.edit'))
        ->assertOk();
});

test('settings redirects to appearance', function () {
    $this->get('/settings')
        ->assertRedirect('/settings/appearance');
});

test('appearance page shows theme options', function () {
    $this->get(route('appearance.edit'))
        ->assertSee('Light')
        ->assertSee('Dark')
        ->assertSee('System');
});
