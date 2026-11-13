<?php

return [
    'already_paid' => 'This order is already paid.',
    'unavailable' => 'This payment method is not available right now.',
    'failed' => 'We could not reach the bank. Please try again or contact us.',
    'below_minimum' => 'This method is available from :amount.',
    'started' => 'Online payment started: :driver',
    'paid_via' => 'The bank confirmed the payment: :driver',
    'refused' => 'The bank did not confirm the payment: :driver',
    'start_failed' => 'The payment could not be started — :reason',
    'credo_signed' => 'Credo: the contract is signed, waiting for the transfer.',
    'redirecting' => 'Redirecting to the bank…',
    'redirecting_to' => 'Taking you to :name…',
    'continue' => 'Continue',
    'online' => 'Online',

    'flitt_description' => 'ELIO.GE order :number',

    'drivers' => [
        'flitt' => 'Flitt — card',
        'bog-installment' => 'Bank of Georgia — instalments',
        'tbc-installment' => 'TBC — instalments',
        'credo-installment' => 'Credo — instalments',
        'credo-split' => 'Credo — split',
    ],

    'statuses' => [
        'pending' => 'Unpaid',
        'processing' => 'At the bank',
        'paid' => 'Paid',
        'failed' => 'Failed',
        'refunded' => 'Refunded',
    ],

    'check' => [
        'no_transaction' => 'No online payment was started for this order.',
        'no_driver' => ':driver is turned off on this site.',
        'unreachable' => 'The bank did not answer — try again later.',
        'confirmed' => 'The bank confirmed the payment.',
        'refused' => 'The application was declined or cancelled.',
        'not_yet' => 'The bank has not confirmed the payment yet.',
    ],

    'order' => [
        'title' => 'Order #:number',
        'thanks' => 'Thank you! Your order is in.',
        'status' => 'Order status',
        'payment' => 'Payment',
        'payment_status' => 'Payment status',
        'items' => 'Products',
        'subtotal' => 'Products',
        'discount' => 'Discount',
        'shipping' => 'Delivery',
        'free' => 'Free',
        'total' => 'Total',
        'pay_now' => 'Pay :amount',
        'apply' => 'Apply online',
        'pay_hint' => 'You will pay on the bank\'s secure page.',
        'paid' => 'Paid — thank you!',
        'processing' => 'We are waiting for the bank. This page refreshes in a few minutes.',
        'return_failed' => 'The payment was not completed. You can try again or ask the operator for another method.',
        'offline' => 'An operator will call you to confirm the order.',
        'questions' => 'Questions? Call us',
        'continue' => 'Continue shopping',
    ],
];
