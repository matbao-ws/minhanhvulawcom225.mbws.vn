<?php

return [
    'order' => [
        'subject' => 'Order #:number status update',
        'heading' => 'ORDER STATUS UPDATE',
        'order_number' => 'Order number',
        'hello' => 'Hello :name,',
        'intro' => 'Your order status has just been updated:',
        'status' => [
            'pending' => ['label' => 'Pending', 'message' => 'We have received your order and it is awaiting confirmation.'],
            'processing' => ['label' => 'Processing', 'message' => 'Your order is being prepared and packed for shipment.'],
            'completed' => ['label' => 'Completed', 'message' => 'Your order was delivered successfully. Thank you for shopping with us!'],
            'cancelled' => ['label' => 'Cancelled', 'message' => 'Your order has been cancelled.'],
        ],
        'shipping_info' => 'Shipping information',
        'recipient' => 'Recipient', 'phone' => 'Phone', 'address' => 'Shipping address', 'notes' => 'Notes',
        'items' => 'Order items', 'product' => 'Product', 'quantity' => 'Qty', 'price' => 'Price', 'variant' => 'Variant',
        'subtotal' => 'Subtotal', 'discount' => 'Discount', 'total' => 'Total',
        'copyright' => 'E-commerce Core - Copyright © :year',
        'automatic' => 'This is an automated email. Please do not reply.',
    ],
];
