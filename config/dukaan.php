<?php

return [
    'trial_days' => 14,
    'invitation_days' => 7,
    'business_types' => ['retail' => 'General retail', 'grocery' => 'Grocery', 'clothing' => 'Clothing', 'restaurant' => 'Restaurant'],
    'roles' => ['owner' => 'Owner', 'manager' => 'Manager', 'cashier' => 'Cashier', 'inventory' => 'Inventory staff'],
    'modules' => ['sales' => 'Sales', 'inventory' => 'Inventory', 'restaurant' => 'Restaurant', 'clothing' => 'Clothing'],
    'type_modules' => [
        'retail' => ['sales', 'inventory'],
        'grocery' => ['sales', 'inventory'],
        'clothing' => ['sales', 'inventory', 'clothing'],
        'restaurant' => ['sales', 'inventory', 'restaurant'],
    ],
];
