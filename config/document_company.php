<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Printed Receipt / Invoice Company Header
    |--------------------------------------------------------------------------
    |
    | These fixed values are used on every receipt and invoice. The bundled
    | logo is also used as a safe cloud fallback when the storage symlink or
    | company logo file is unavailable.
    |
    */
    'name' => 'BASHIR AGRO',
    'short_name' => 'BA',
    'address' => "Bashir Master's Palace, Narsingdi, Dhaka",
    'phone' => '+880 1779-501104',
    // Text printed on receipts/invoices.
    'website' => 'https://bashiragro.com',
    // Direct canonical destination used for clicks. Using the apex domain avoids
    // the www-to-apex redirect that was causing an incomplete/blank hero load
    // when the URL was opened from Chrome's PDF viewer.
    'website_url' => 'https://bashiragro.com/?source=receipt',
    'logo_path' => 'images/receipts/bashir-agro-favicon.jpg',
];
