<?php

return [
    'title' => 'My account',
    'home' => 'Home',
    'logout' => 'Sign out',
    'hello' => 'Hello, :name',

    'tabs' => [
        'overview' => 'Overview',
        'orders' => 'Orders',
        'wishlist' => 'Favorites',
        'addresses' => 'Addresses',
        'profile' => 'Personal details',
    ],

    'overview' => [
        'recent_orders' => 'Recent orders',
        'all' => 'All',
        'delivery_address' => 'Delivery address',
        'change' => 'Change',
        'no_address' => 'No address added yet',
        'add_address' => 'Add',
    ],

    'orders' => [
        'title' => 'My orders',
        'empty' => 'You have no orders yet',
        'empty_text' => 'Once you place an order, its status and history will appear here.',
        'start' => 'Start shopping',
        'items' => '{1} :count item|[2,*] :count items',
        'statuses' => [
            'new' => 'Received',
            'confirmed' => 'Confirmed',
            'shipped' => 'On the way',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
        ],
    ],

    'wishlist' => [
        'title' => 'Favorite products',
        'empty' => 'Your favorites list is empty',
        'browse' => 'Browse products',
        'monthly' => 'From :amount per month',
        'add' => 'Add to cart',
        'remove' => 'Remove',
    ],

    'addresses' => [
        'title' => 'Addresses',
        'default' => 'Default',
        'make_default' => 'Set as default',
        'delete' => 'Delete',
        'new' => 'New address',
        'title_label' => 'Name',
        'title_placeholder' => 'Name, e.g. Office',
        'line_label' => 'Address',
        'line_placeholder' => 'City, street, building number',
        'add' => 'Add',
        'added' => 'Address added',
        'deleted' => 'Address deleted',
    ],

    'profile' => [
        'title' => 'Personal details',
        'full_name' => 'Full name',
        'phone' => 'Phone',
        'email' => 'Email',
        'save' => 'Save',
        'saved' => 'Saved',
        'password_title' => 'Change password',
        'current_password' => 'Current password',
        'new_password' => 'New password',
        'update_password' => 'Update password',
        'password_updated' => 'Password updated',
        'notifications' => 'Notifications',
        'channels' => [
            'sms' => ['label' => 'SMS notifications', 'text' => 'Order status and courier updates'],
            'mail' => ['label' => 'Email', 'text' => 'Invoices and confirmations'],
            'promo' => ['label' => 'Deals and discounts', 'text' => 'Personal offers'],
        ],
    ],
];
