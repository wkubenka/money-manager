<?php

if (! function_exists('format_cents')) {
    /**
     * Format a cents value as a dollar string (without the $ sign).
     */
    function format_cents(int $cents, int $decimals = 0): string
    {
        return number_format($cents / 100, $decimals);
    }
}

if (! function_exists('sanitize_money_input')) {
    /**
     * Strip common formatting characters from a monetary input string.
     */
    function sanitize_money_input(string $value): string
    {
        return str_replace([',', '$', ' '], '', $value);
    }
}
