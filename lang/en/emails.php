<?php

return [
    // Defaults for each email (App\Services\EmailAppearance); a shop overrides them in
    // Admin › Email design. {ten_khach}-style variables are filled in when sending.
    'appearance' => [
        'order_placed' => [
            'subject' => 'We received order #{ma_don} – thank you!',
            'heading' => 'Thank you, {ten_khach}! We received your order.',
            'intro' => 'We will contact you shortly to confirm it, then start preparing your items. You will get an email like this every time your order moves on.',
        ],
        'order_preparing' => [
            'subject' => 'Order #{ma_don} is confirmed',
            'heading' => 'We are preparing your order',
            'intro' => 'Your order is confirmed and being packed. We will let you know when it is on its way.',
        ],
        'order_shipping' => [
            'subject' => 'Order #{ma_don} is on its way',
            'heading' => 'Your order has left our warehouse',
            'intro' => '{don_vi_van_chuyen} is delivering your order. Tracking number: {ma_van_don}.',
        ],
        'order_completed' => [
            'subject' => 'Order #{ma_don} was delivered',
            'heading' => 'Your order has arrived',
            'intro' => 'Thank you for choosing {ten_shop}. If anything is wrong with your items, reply to this email and we will help.',
        ],
        'order_cancelled' => [
            'subject' => 'Order #{ma_don} was cancelled',
            'heading' => 'Order #{ma_don} was cancelled',
            'intro' => 'This order will not be processed. If you did not ask to cancel it or want to order again, contact us or reply to this email.',
        ],
        'owner_new_order' => [
            'subject' => 'New order #{ma_don} · {tong_tien}',
            'heading' => '{tong_tien} from {ten_khach}',
            'intro' => 'The customer also received an order confirmation. Calling to confirm early cuts down on refused deliveries.',
        ],
        'contact' => [
            'subject' => 'New enquiry: {ten_nguoi_gui}',
            'heading' => '{ten_nguoi_gui} needs your advice',
            'intro' => 'Reply in your mailbox to answer the customer directly. This enquiry is also saved in Admin › Contacts.',
        ],
        'reset_password' => [
            'subject' => 'Reset your {ten_shop} password',
            'heading' => 'Reset your password',
            'intro' => 'We received a request to reset the password of your account. Press the button below to choose a new one.',
        ],
        'settings_test' => [
            'subject' => '[{ten_shop}] Test email',
            'heading' => 'Email sending works',
            'intro' => 'This is a test email from the {ten_shop} admin. Order emails to customers, new order and enquiry emails to you go out with the settings below.',
        ],
    ],

    'ui' => [
        'eyebrow' => [
            'order_placed' => 'Order received', 'order_preparing' => 'Confirmed', 'order_shipping' => 'On its way',
            'order_completed' => 'Delivered', 'order_cancelled' => 'Cancelled',
            'owner_new_order' => 'New order', 'contact' => 'New enquiry from the website',
        ],
        'steps' => ['Ordered', 'Preparing', 'Shipping', 'Delivered'],
        'order_number' => 'Order', 'placed_at' => 'Placed', 'items' => 'Items', 'qty' => 'Qty',
        'subtotal' => 'Subtotal', 'voucher' => 'Voucher', 'promotion' => 'Promotion', 'shipping_fee' => 'Shipping',
        'free' => 'Free', 'total' => 'Total', 'customer_pays' => 'Customer pays',
        'ship_to' => 'Ship to', 'payment_shipping' => 'Payment & delivery', 'notes' => 'Notes',
        'tracking' => 'Tracking number', 'track' => 'Track parcel', 'tracking_later' => 'Tracking number follows when shipping starts',
        'refund_title' => 'Paid in advance?',
        'refund_default' => 'We will contact you to refund your payment.',
        'help' => 'Need to change the address or ask about your order? Reply to this email or call :phone.',
        'help_no_phone' => 'Need to change the address or ask about your order? Reply to this email.',
        'open_order' => 'Open the order in admin', 'call_customer' => 'Call the customer', 'call_back' => 'Call back', 'reply' => 'Reply by email',
        'name' => 'Name', 'phone' => 'Phone', 'email' => 'Email', 'sent_at' => 'Sent :time',
        'reset_button' => 'Choose a new password', 'reset_expires' => 'The link works once and expires in :minutes minutes.',
        'reset_fallback' => 'Button not working? Paste this link into your browser:',
        'reset_ignore' => 'Did not ask for this? Ignore this email; your password stays the same.',
        'test_via' => 'Sent via', 'test_own_smtp' => "The shop's own SMTP", 'test_relay' => 'MatBao mail relay', 'test_default' => "The server's default mailer",
        'test_host' => 'Server', 'test_encryption' => 'Encryption', 'test_from' => 'From', 'test_to' => 'Notifications to',
        'test_spam' => 'Found this in Spam? Mark it "Not spam" and add the sender to your contacts so later emails reach the inbox.',
        'hotline' => 'Hotline',
        'footer_customer' => 'You received this email because you ordered from :shop.',
        'footer_owner' => 'Internal email to the owner of :shop.',
        'footer_admin' => 'Security email to your account at :shop.',
    ],
];
