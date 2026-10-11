<?php

// Guided tours of the admin (App\Support\AdminTours). Plain text: it is escaped before display.
return [
    'ui' => [
        'start' => 'Show the guide for this page',
        'next' => 'Next →',
        'prev' => '← Back',
        'done' => 'Done',
        'skip' => 'Skip',
        'progress' => 'Step {{current}} of {{total}}',
    ],

    'layout' => [
        'sidebar' => ['title' => 'Admin menu', 'description' => 'Every area lives here: products, orders, content, customers and settings. You only see what you are allowed to use.'],
        'search' => ['title' => 'Quick search', 'description' => 'Find products, orders, posts or admin pages by keyword without opening each menu.'],
        'notifications' => ['title' => 'Notifications', 'description' => 'New orders, plan limit warnings and things to handle show up here.'],
        'help' => ['title' => 'Replay the guide', 'description' => 'Click this button at any time to see the guide for the current page again.'],
    ],

    'form' => [
        'save' => ['title' => 'Save changes', 'description' => 'The save button stays at the bottom of the screen. Nothing is stored until you click it.'],
    ],

    'dashboard' => [
        'intro' => ['title' => 'Welcome to your admin', 'description' => 'This short guide shows the main parts. Use Next/Back or the arrow keys to move, Skip to close.'],
        'welcome' => ['title' => 'Shortcuts', 'description' => 'Jump to the areas used most: products, orders and customers.'],
        'stats' => ['title' => 'Sales figures', 'description' => 'Revenue and order counts by status, from real orders.'],
        'charts' => ['title' => 'Charts', 'description' => 'Revenue over time and the share of orders by status.'],
        'recent_orders' => ['title' => 'Recent orders', 'description' => 'The latest orders. Click "View all" to open the order list.'],
    ],

    'products_index' => [
        'intro' => ['title' => 'Products', 'description' => 'Manage every product sold on the website.'],
        'create' => ['title' => 'Add a product', 'description' => 'Create a new product. Next to it you can import and export products from a file.'],
        'filters' => ['title' => 'Filter and search', 'description' => 'Search by name or code, filter by category, brand and status.'],
        'select_all' => ['title' => 'Bulk actions', 'description' => 'Tick several products to enable, disable or delete them at once.'],
        'table' => ['title' => 'The list', 'description' => 'Each row has buttons to view, edit and delete the product.'],
    ],

    'products_form' => [
        'intro' => ['title' => 'Product details', 'description' => 'Fill it in from top to bottom. Anything missing can be added later.'],
        'languages' => ['title' => 'Content per language', 'description' => 'Name, description and SEO are entered separately for each website language.'],
        'media' => ['title' => 'Images', 'description' => 'Add product images from the Media library or upload new ones.'],
        'variants' => ['title' => 'Variants', 'description' => 'Colours, sizes…? Declare attributes to get one SKU per combination with its own price and stock.'],
        'pricing' => ['title' => 'Price', 'description' => 'Selling price, compare-at (struck-through) price and cost. The final price customers pay, after promotions, is always computed by the system.'],
        'side' => ['title' => 'Status and classification', 'description' => 'Thumbnail, shown or hidden, category, brand and SKU.'],
    ],

    'orders_index' => [
        'intro' => ['title' => 'Orders', 'description' => 'Every order from the website and every order created by hand is here.'],
        'create' => ['title' => 'Create an order', 'description' => 'Create an order for a customer, for example one placed by phone.'],
        'filters' => ['title' => 'Filter orders', 'description' => 'Filter by order, payment and shipping status, or search by order number and customer details.'],
        'table' => ['title' => 'Order list', 'description' => 'Click "View" on a row to open and process the order.'],
    ],

    'orders_show' => [
        'intro' => ['title' => 'Order details', 'description' => 'Process one order from receipt to delivery.'],
        'toolbar' => ['title' => 'Back and print', 'description' => 'Return to the list or print the order sheet.'],
        'status' => ['title' => 'Update the status', 'description' => 'Change the order and payment status. The customer gets an email when the status changes.'],
        'ship' => ['title' => 'Create a shipment', 'description' => 'Send the order to a connected carrier to get a tracking number.'],
        'history' => ['title' => 'Order history', 'description' => 'Every change to the order: who and when.'],
        'sheet' => ['title' => 'Order sheet', 'description' => 'Customer details, items and totals of the order.'],
    ],

    'posts_index' => [
        'intro' => ['title' => 'Posts', 'description' => 'News and blog posts of the website.'],
        'create' => ['title' => 'New post', 'description' => 'Write a new post.'],
        'ai_draft' => ['title' => 'AI draft', 'description' => 'Give the key points and AI writes a draft for you to edit and publish.'],
        'filters' => ['title' => 'Filter posts', 'description' => 'Search by title, filter by category and status.'],
        'select_all' => ['title' => 'Bulk actions', 'description' => 'Tick several posts to publish, hide or delete them at once.'],
        'table' => ['title' => 'Post list', 'description' => 'Each row has buttons to edit and delete the post.'],
    ],

    'categories_index' => [
        'intro' => ['title' => 'Product categories', 'description' => 'Group products so customers find them easily.'],
        'create' => ['title' => 'Add a category', 'description' => 'Create a category, optionally under a parent category.'],
        'filters' => ['title' => 'Filter categories', 'description' => 'Search by name, filter by parent and status.'],
        'select_all' => ['title' => 'Bulk actions', 'description' => 'Tick several categories to enable, disable or delete them at once.'],
        'table' => ['title' => 'Drag to reorder', 'description' => 'Drag rows to change the order shown on the website.'],
    ],

    'customers_index' => [
        'intro' => ['title' => 'Customers', 'description' => 'People who placed orders, grouped by the details on their orders; those with a website account are marked.'],
        'count' => ['title' => 'Total customers', 'description' => 'How many people have ordered.'],
        'export' => ['title' => 'Export the list', 'description' => 'Download the customer list as a file, using the current filter.'],
        'search' => ['title' => 'Find a customer', 'description' => 'Search by name, email or phone number.'],
        'table' => ['title' => 'Customer list', 'description' => 'Orders, completed orders, total spent and last order of each customer.'],
    ],

    'vouchers_index' => [
        'intro' => ['title' => 'Vouchers', 'description' => 'Create codes customers enter at checkout.'],
        'create' => ['title' => 'Create a voucher', 'description' => 'Set the discount, minimum order, number of uses and validity.'],
        'filters' => ['title' => 'Filter vouchers', 'description' => 'Search by code and filter by status.'],
        'select_all' => ['title' => 'Bulk actions', 'description' => 'Tick several vouchers to enable, disable or delete them at once.'],
        'table' => ['title' => 'Voucher list', 'description' => 'Uses so far and validity of each code.'],
    ],

    'settings' => [
        'intro' => ['title' => 'Website settings', 'description' => 'General information about the shop.'],
        'tabs' => ['title' => 'Setting groups', 'description' => 'Switch between general, contact, embed codes and Analytics.'],
        'general' => ['title' => 'General', 'description' => 'Website name, logo, favicon and the description shown on Google.'],
        'save' => ['title' => 'Save settings', 'description' => 'Click save to apply the changes to the website.'],
    ],
];
