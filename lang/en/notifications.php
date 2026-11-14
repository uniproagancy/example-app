<?php

return [
    'placed' => [
        'subject' => 'Order :number received — :site',
        'greeting' => 'Hello, :name!',
        'intro' => 'Thank you for your order. We have received order :number:',
        'outro' => 'An operator will contact you shortly to arrange delivery.',
    ],
    'paid' => [
        'subject' => 'Payment received — :number',
        'intro' => 'We have received the payment for order :number (:amount).',
        'outro' => 'We are preparing your order and will contact you soon.',
    ],
    'staff' => [
        'placed_subject' => 'New order :number — :amount',
        'placed_greeting' => 'New order :number',
        'paid_subject' => 'Paid :number — :amount',
        'paid_greeting' => 'Order :number is paid',
        'customer' => 'Customer: :name, :phone',
        'email' => 'Email: :email',
        'quick' => 'Quick order — call to confirm the address.',
        'note' => 'Note: :note',
        'open' => 'Open in admin',
    ],
    'total' => 'Total: :amount',
    'payment' => 'Payment: :method',
    'address' => 'Address: :address',
    'view_order' => 'View order',
    'no_reply' => 'This email was sent automatically, please do not reply.',
    'no_reply_phone' => 'This email was sent automatically, please do not reply. For questions, call us: :phone',
    'salutation' => 'Regards, :site',
    'sms' => [
        'placed' => 'Order :number received. Total :amount. We will contact you soon. :site',
        'staff' => 'New order :number, :amount. :name :phone',
        'price_drop' => ':site: ":product" is now cheaper — :price. :url',
        'status' => [
            'received' => 'Order :number is confirmed and being prepared. :site',
            'shipped' => 'Order :number is with the courier and will arrive soon. To pay: :amount. :site',
            'completed' => 'Order :number has been delivered. Thank you for choosing :site!',
            'cancelled' => 'Order :number was cancelled. Questions: :phone. :site',
        ],
    ],

    'price_drop' => [
        'subject' => ':product is now cheaper — :site',
        'greeting' => 'Hello!',
        'intro' => 'A product you were watching — :product — is now cheaper.',
        'prices' => 'It was :old, now :new.',
        'action' => 'View product',
        'outro' => 'This is a one-time alert.',
    ],

    'transfer' => [
        'intro' => 'Please pay :amount by bank transfer. Our accounts:',
        'purpose' => 'Payment reference: :purpose',
        'invoice' => 'Open your order with the button below to download the invoice.',
    ],
];
