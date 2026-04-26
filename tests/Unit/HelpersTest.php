<?php

// format_cents

test('format_cents formats zero', function () {
    expect(format_cents(0))->toBe('0');
});

test('format_cents formats whole dollars', function () {
    expect(format_cents(50000))->toBe('500');
});

test('format_cents formats with thousands separator', function () {
    expect(format_cents(1234500))->toBe('12,345');
});

test('format_cents formats with decimal places', function () {
    expect(format_cents(12345, 2))->toBe('123.45');
});

test('format_cents rounds to specified decimals', function () {
    expect(format_cents(9999, 1))->toBe('100.0');
});

test('format_cents handles single cent', function () {
    expect(format_cents(1, 2))->toBe('0.01');
});

test('format_cents handles negative values', function () {
    expect(format_cents(-50000))->toBe('-500');
});

test('format_cents handles large values', function () {
    expect(format_cents(100000000))->toBe('1,000,000');
});

// sanitize_money_input

test('sanitize_money_input strips dollar sign', function () {
    expect(sanitize_money_input('$100'))->toBe('100');
});

test('sanitize_money_input strips commas', function () {
    expect(sanitize_money_input('1,000'))->toBe('1000');
});

test('sanitize_money_input strips spaces', function () {
    expect(sanitize_money_input('1 000'))->toBe('1000');
});

test('sanitize_money_input strips all formatting at once', function () {
    expect(sanitize_money_input('$1,234,567.89'))->toBe('1234567.89');
});

test('sanitize_money_input preserves decimal point', function () {
    expect(sanitize_money_input('100.50'))->toBe('100.50');
});

test('sanitize_money_input handles empty string', function () {
    expect(sanitize_money_input(''))->toBe('');
});

test('sanitize_money_input handles plain number', function () {
    expect(sanitize_money_input('500'))->toBe('500');
});
