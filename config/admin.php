<?php

return [
    'emails' => array_filter(array_map('trim', explode(',', env('ADMIN_EMAILS', '')))),
];
