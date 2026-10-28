<?php

// Guided tours of the admin (App\Support\AdminTours). Plain text: it is escaped before display.
return [
    'form' => [
        'save' => ['title' => 'Save changes', 'description' => 'The save button stays at the bottom of the screen. Nothing is stored until you click it.'],
    ],

    'layout' => [
        'sidebar' => ['title' => 'Admin menu', 'description' => 'Every area lives here: products, orders, content, customers and settings. You only see what you are allowed to use.'],
        'search' => ['title' => 'Quick search', 'description' => 'Find products, orders, posts or admin pages by keyword without opening each menu.'],
        'notifications' => ['title' => 'Notifications', 'description' => 'New orders, plan limit warnings and things to handle show up here.'],
        'help' => ['title' => 'Replay the guide', 'description' => 'Click this button at any time to see the guide for the current page again.'],
    ],

    'ui' => [
        'start' => 'Show the guide for this page',
        'next' => 'Next →',
        'prev' => '← Back',
        'done' => 'Done',
        'skip' => 'Skip',
        'progress' => 'Step {{current}} of {{total}}',
    ],

    'products_index' => [
        'intro' => ['title' => 'Products', 'description' => 'Manage every product sold on the website: price, stock, variants and visibility.'],
        'create' => ['title' => 'Add a product', 'description' => 'Open the form to create a new product.'],
        'import' => ['title' => 'Import from CSV', 'description' => 'Import many products at once from a CSV file, in the standard or WordPress format. The import box has buttons to download a template.'],
        'export' => ['title' => 'Export to a file', 'description' => 'Download the product list as a CSV file, using the current filters.'],
        'filters' => ['title' => 'Filter and search', 'description' => 'Search by name, SKU or slug; filter by category, brand and status.'],
        'select_all' => ['title' => 'Bulk actions', 'description' => 'Tick several products to show, hide or delete them at once.'],
        'table' => ['title' => 'The list', 'description' => 'Each row has view, edit and delete buttons. When no filter is applied, drag the handle at the start of a row to change the display order.'],
    ],

    'products_form' => [
        'intro' => ['title' => 'Product details', 'description' => 'Fill it in from top to bottom. Only the name and price are required; the rest can be added later.'],
        'languages' => ['title' => 'Content per language', 'description' => 'Each tab is one website language. The name in the default language is required; a blank slug is generated from the name.'],
        'description' => ['title' => 'Description', 'description' => 'Write the full description in the editor. The Expand button opens it full screen.'],
        'seo' => ['title' => 'SEO', 'description' => 'SEO score, focus keyword and the checks to fix; Google preview, what shows when shared, canonical and whether Google may index it.'],
        'media' => ['title' => 'Image gallery', 'description' => 'Add extra images shown in the gallery of the product page.'],
        'variants' => ['title' => 'Variants and SKUs', 'description' => 'Turn on Has variants and declare groups such as Colour or Size. On save, any missing SKU combinations are created automatically.'],
        'pricing' => ['title' => 'Price', 'description' => 'Selling price, compare-at (struck-through) price and cost. The price customers pay after promotions is always computed by the system.'],
        'side' => ['title' => 'Thumbnail and status', 'description' => 'Pick the product thumbnail and choose whether it is shown or hidden on the website.'],
        'details' => ['title' => 'Classification and stock', 'description' => 'Category, brand, SKU, stock quantity, stock tracking and the featured flag.'],
    ],

    'products_show' => [
        'intro' => ['title' => 'Product overview', 'description' => 'An overview of one product and the place to manage its variant SKUs.'],
        'actions' => ['title' => 'Back and edit', 'description' => 'Return to the product list or open the edit form.'],
        'summary' => ['title' => 'Image and status', 'description' => 'Thumbnail, visibility, featured badge and short description.'],
        'stats' => ['title' => 'Price, stock, variants', 'description' => 'Selling price, total stock (summed over SKUs when there are variants) and the number of variants.'],
        'details' => ['title' => 'Classification', 'description' => 'Category, brand, SKU and publish date.'],
        'description' => ['title' => 'Description', 'description' => 'The full description shown on the website.'],
        'options' => ['title' => 'Attributes', 'description' => 'Declare attribute groups such as Colour or Size and their values.'],
        'generate' => ['title' => 'Generate combinations', 'description' => 'Create every missing SKU from the attribute values. Existing SKUs are kept.'],
        'create_variant' => ['title' => 'Add a SKU', 'description' => 'Create one SKU by hand for a specific attribute combination.'],
        'variants' => ['title' => 'SKU list', 'description' => 'Price, stock and status of each SKU, with edit and delete buttons on each row.'],
    ],

    'products_options' => [
        'intro' => ['title' => 'Variant attributes', 'description' => 'Declare attribute groups and values first, then generate the SKU combinations on the product page.'],
        'back' => ['title' => 'Back to the product', 'description' => 'Return to the product page without saving.'],
        'languages' => ['title' => 'Languages', 'description' => 'Switch language to enter group and value names for each website language.'],
        'groups' => ['title' => 'Groups and values', 'description' => 'Each group has a name, a display type (list, colour, image) and values. A value used by a SKU cannot be deleted; turn it off instead.'],
        'add_group' => ['title' => 'Add a group', 'description' => 'Add a new attribute group, for example Size or Capacity.'],
    ],

    'variants_form' => [
        'intro' => ['title' => 'Variant SKU', 'description' => 'A SKU is one specific attribute combination of the product, with its own price and stock.'],
        'no_options' => ['title' => 'No attributes yet', 'description' => 'The product needs attributes before a SKU can be created. Click the button to set them up.'],
        'combination' => ['title' => 'Attribute combination', 'description' => 'Pick one value per group. Two SKUs cannot share the same combination.'],
        'name' => ['title' => 'Display name', 'description' => 'An optional name per language. Leave it blank to use the combination.'],
        'sku' => ['title' => 'SKU code', 'description' => 'The variant’s own code, required. A barcode field sits next to it.'],
        'price' => ['title' => 'SKU price', 'description' => 'Leave the price blank to use the product price. Compare-at and cost prices are also available.'],
        'stock' => ['title' => 'Stock and weight', 'description' => 'The stock of this SKU alone and its weight in grams.'],
        'image' => ['title' => 'Image and order', 'description' => 'The image URL of this SKU and its display order.'],
        'status' => ['title' => 'Status', 'description' => 'Tick On sale to sell this SKU. A product has one default SKU; marking this one unmarks the others.'],
    ],

    'categories_index' => [
        'intro' => ['title' => 'Product categories', 'description' => 'Group products, with nested sub-categories, so customers find them easily.'],
        'create' => ['title' => 'Add a category', 'description' => 'Create a new category, optionally as a sub-category.'],
        'filters' => ['title' => 'Filter categories', 'description' => 'Search by name, filter by parent, status and draft. While filtered, the list is flat and drag-and-drop is off.'],
        'select_all' => ['title' => 'Bulk actions', 'description' => 'Tick several categories to show, hide or delete them at once.'],
        'table' => ['title' => 'Drag to reorder', 'description' => 'Sub-categories sit under their parent. Drag the handle at the start of a row to change the order on the website.'],
        'row_actions' => ['title' => 'Quick edit, edit, delete', 'description' => 'The menu on each row: Quick edit opens an editor on this page, Edit opens the full form.'],
    ],

    'categories_form' => [
        'intro' => ['title' => 'Category details', 'description' => 'Set the name, description, parent and image of the category.'],
        'languages' => ['title' => 'Content per language', 'description' => 'Each tab is one website language. The name in the default language is required.'],
        'name' => ['title' => 'Name and slug', 'description' => 'The category name; a blank slug next to it is generated from the name.'],
        'description' => ['title' => 'Description', 'description' => 'A short introduction for the category.'],
        'parent' => ['title' => 'Parent category', 'description' => 'Pick a parent to make this a sub-category; leave None for a top-level one.'],
        'image' => ['title' => 'Category image', 'description' => 'The category’s picture.'],
        'visible' => ['title' => 'Visible', 'description' => 'Untick to hide the category from the website.'],
        'draft' => ['title' => 'Draft', 'description' => 'Marks unfinished content. It does not affect visibility.'],
    ],

    'brands_index' => [
        'intro' => ['title' => 'Brands', 'description' => 'Brands to assign to products and to filter products by.'],
        'create' => ['title' => 'Add a brand', 'description' => 'Create a new brand.'],
        'filters' => ['title' => 'Find a brand', 'description' => 'Search by brand name.'],
        'select_all' => ['title' => 'Bulk actions', 'description' => 'Tick several brands to show, hide or delete them at once.'],
        'table' => ['title' => 'Brand list', 'description' => 'Status and product count of each brand. Drag the handle at the start of a row to reorder.'],
        'row_actions' => ['title' => 'Quick edit, edit, delete', 'description' => 'The menu on each row: Quick edit opens an editor on this page, Edit opens the full form.'],
    ],

    'brands_form' => [
        'intro' => ['title' => 'Brand details', 'description' => 'Set the brand’s name, description and logo.'],
        'languages' => ['title' => 'Content per language', 'description' => 'Each tab is one website language. The name in the default language is required.'],
        'name' => ['title' => 'Name and slug', 'description' => 'The brand name; a blank slug next to it is generated from the name.'],
        'description' => ['title' => 'Description', 'description' => 'A short introduction to the brand.'],
        'logo' => ['title' => 'Logo', 'description' => 'The brand’s picture.'],
        'draft' => ['title' => 'Draft', 'description' => 'Tick Draft to hide the brand from the website until you untick it.'],
    ],

    'reviews_index' => [
        'intro' => ['title' => 'Product reviews', 'description' => 'Reviews sent by customers from the website. New reviews show straight away; you can hide, edit or delete them.'],
        'filters' => ['title' => 'Filter reviews', 'description' => 'Search by name, email or text; filter by product, stars and status.'],
        'table' => ['title' => 'Review list', 'description' => 'Product, reviewer (member or guest), stars, text and date.'],
        'visibility' => ['title' => 'Show or hide', 'description' => 'Flip the switch to show or hide the review on the website. It saves immediately.'],
        'row_actions' => ['title' => 'Edit and delete', 'description' => 'Edit the review text or delete the review.'],
    ],

    'promotions_index' => [
        'intro' => ['title' => 'Promotions and flash sales', 'description' => 'Automatic discounts by product or SKU; customers enter no code.'],
        'create' => ['title' => 'Create a campaign', 'description' => 'Create a new promotion or flash sale.'],
        'filters' => ['title' => 'Filter campaigns', 'description' => 'Search by name and filter by type: automatic promotion or flash sale.'],
        'table' => ['title' => 'Campaign list', 'description' => 'Discount, scope, schedule, units used out of the limit, and whether it is running or stopped.'],
        'row_actions' => ['title' => 'Edit and delete', 'description' => 'Edit or delete the campaign on each row.'],
    ],

    'promotions_form' => [
        'intro' => ['title' => 'Promotion', 'description' => 'The discounted price is applied to products automatically. A voucher, if used, is then applied on top of the promotional price.'],
        'languages' => ['title' => 'Name and description', 'description' => 'The campaign name and description per language. The name in the default language is required.'],
        'kind' => ['title' => 'Campaign type', 'description' => 'Automatic promotion or flash sale, used to label and filter the list.'],
        'discount' => ['title' => 'Discount and value', 'description' => 'A percentage off, a fixed amount off, or a fixed price per SKU. Enter the value in the next field.'],
        'priority' => ['title' => 'Priority', 'description' => 'When several campaigns cover one SKU, the higher priority wins; on a tie the lowest discounted price wins. Next to it is the minimum quantity.'],
        'limits' => ['title' => 'Quantity limits', 'description' => 'The total units sold at the promotional price, and a separate limit for each selected product or SKU. Blank means unlimited.'],
        'schedule' => ['title' => 'Schedule', 'description' => 'Start and end date and time. A blank start runs now; a blank end never expires.'],
        'scope' => ['title' => 'Scope', 'description' => 'Apply to all products, or only to the selected products and SKUs. Picking a product covers all of its SKUs.'],
        'active' => ['title' => 'Active', 'description' => 'Untick to pause the campaign without deleting it.'],
    ],

    'vouchers_index' => [
        'intro' => ['title' => 'Vouchers', 'description' => 'Create codes that customers enter at checkout.'],
        'create' => ['title' => 'Create a code', 'description' => 'Set the discount, minimum order, number of uses and validity period.'],
        'filters' => ['title' => 'Filter codes', 'description' => 'Search by code or name and filter by status.'],
        'select_all' => ['title' => 'Bulk actions', 'description' => 'Tick several codes to enable, disable or delete them at once.'],
        'table' => ['title' => 'Code list', 'description' => 'Discount, minimum order, uses so far, validity and state: running, upcoming, expired or used up.'],
        'row_actions' => ['title' => 'Edit and delete', 'description' => 'Edit or delete the code on each row.'],
    ],

    'vouchers_form' => [
        'intro' => ['title' => 'Voucher details', 'description' => 'Set the code, the discount and the conditions that apply at checkout.'],
        'code' => ['title' => 'Code', 'description' => 'The code customers type at checkout. It is saved in upper case and must be unique.'],
        'languages' => ['title' => 'Name and description', 'description' => 'Name and description per language. The name in the default language is required.'],
        'type' => ['title' => 'Type and value', 'description' => 'A percentage (up to 100%) or a fixed amount; enter the value in the next field.'],
        'min_order' => ['title' => 'Minimum order', 'description' => 'The minimum order value to use the code. For a percentage code, the next field caps the discount.'],
        'quantity' => ['title' => 'Total uses', 'description' => 'The maximum number of uses. Blank means unlimited.'],
        'per_user' => ['title' => 'Uses per customer', 'description' => 'How many times one customer may use the code, matched by account, email or phone. Blank means unlimited.'],
        'active' => ['title' => 'Active', 'description' => 'Turn off to suspend the code without deleting it.'],
        'schedule' => ['title' => 'Validity', 'description' => 'Start and end date and time. Blank means no time limit.'],
    ],

    'orders_index' => [
        'intro' => ['title' => 'Orders', 'description' => 'Every order from the website and every order created by hand is here, newest first.'],
        'create' => ['title' => 'Create an order', 'description' => 'Create an order for a customer, for example one placed by phone.'],
        'search' => ['title' => 'Find an order', 'description' => 'Search by order number, customer name, phone or email.'],
        'filters' => ['title' => 'Filter orders', 'description' => 'Filter by order status, payment status and shipping status, then click the search button.'],
        'table' => ['title' => 'Order list', 'description' => 'Each row shows the customer, order date, total, payment, shipping and order status.'],
        'details' => ['title' => 'View details', 'description' => 'Open the order to change its status, record a refund, create a shipment or print it.'],
    ],

    'orders_show' => [
        'intro' => ['title' => 'Order details', 'description' => 'Process one order from receipt to delivery.'],
        'toolbar' => ['title' => 'Back and print', 'description' => 'Return to the list or print the order sheet.'],
        'status' => ['title' => 'Update the status', 'description' => 'Change the order and payment status, then click update. The customer gets an email when the order status changes.'],
        'note' => ['title' => 'Internal note', 'description' => 'A note saved with this update in the order history. The customer does not see it.'],
        'refunds' => ['title' => 'Refunds', 'description' => 'Record a full refund, or a partial one per item, for a paid order. Refunded items go back into stock.'],
        'history' => ['title' => 'Order history', 'description' => 'Every status change: the new status, who made it, when, and the note.'],
        'sheet' => ['title' => 'Order sheet', 'description' => 'Customer details, delivery address, payment method and shipping status. This is the part that gets printed.'],
        'ship' => ['title' => 'Create a shipment', 'description' => 'Send the order to GHTK or GHN to get a tracking number. The button shows only when a carrier is enabled.'],
        'items' => ['title' => 'Items and totals', 'description' => 'The items bought, unit price, quantity and line total; below are discounts, shipping fee and grand total.'],
    ],

    'orders_create' => [
        'intro' => ['title' => 'Create an order by hand', 'description' => 'Create an order for a customer, for example one placed by phone. Product prices are worked out by the system.'],
        'customer' => ['title' => 'Customer details', 'description' => 'Type 2 or more characters in the name, email or phone to find a past customer and fill in their details.'],
        'address' => ['title' => 'Delivery address', 'description' => 'Where the customer receives the order. Required.'],
        'payment' => ['title' => 'Payment method', 'description' => 'Only the methods turned on in the payment settings are listed.'],
        'discount' => ['title' => 'Manual discount', 'description' => 'An extra amount off, applied after any running promotion. It cannot exceed the item total.'],
        'shipping_fee' => ['title' => 'Shipping fee', 'description' => 'The delivery fee added to the order, entered by hand.'],
        'items' => ['title' => 'Items', 'description' => 'On each row pick a category (and a brand if you like) to narrow the list, then the product, variant and quantity.'],
        'add_item' => ['title' => 'Add an item', 'description' => 'Add another item row. The × at the end of a row removes it.'],
        'submit' => ['title' => 'Create the order', 'description' => 'Save the order. Stock is checked and deducted when the order is created.'],
    ],

    'customers_index' => [
        'intro' => ['title' => 'Customers', 'description' => 'People who placed orders, grouped by the email on their orders.'],
        'count' => ['title' => 'Total customers', 'description' => 'How many people have ordered, for the current filter.'],
        'export' => ['title' => 'Export the list', 'description' => 'Download the customer list as a CSV file, using the current filter.'],
        'search' => ['title' => 'Find a customer', 'description' => 'Search by name, email or phone number.'],
        'table' => ['title' => 'Customer list', 'description' => 'Orders, completed orders, total spent and last order of each customer. Total spent counts completed orders only.'],
        'account' => ['title' => 'Account', 'description' => 'Shows whether the customer has a website account or only ordered as a guest.'],
        'details' => ['title' => 'Customer profile', 'description' => 'Open the profile: contact details, purchase figures and all of the customer\'s orders.'],
    ],

    'customers_show' => [
        'intro' => ['title' => 'Customer profile', 'description' => 'Everything about one customer, gathered from the orders with their email.'],
        'account' => ['title' => 'Account', 'description' => 'Shows whether the customer has a website account.'],
        'contact' => ['title' => 'Contact details', 'description' => 'Email, phone number, and the dates of the first and latest orders.'],
        'metrics' => ['title' => 'Purchase overview', 'description' => 'Number of orders, completion rate, total spent and average value per completed order.'],
        'orders' => ['title' => 'Order history', 'description' => 'All of the customer\'s orders, newest first, with order and payment status.'],
        'order_details' => ['title' => 'Open an order', 'description' => 'Open an order to process it.'],
    ],

    'payment_methods_index' => [
        'intro' => ['title' => 'Payment settings', 'description' => 'Choose how customers can pay when they order.'],
        'create' => ['title' => 'Add a method', 'description' => 'Add your own offline payment method: just a name and instructions.'],
        'table' => ['title' => 'Payment methods', 'description' => 'The built-in methods and the ones you added.'],
        'type' => ['title' => 'Type', 'description' => 'API connected means an online payment gateway; offline means you collect the money yourself.'],
        'status' => ['title' => 'Turn on or off', 'description' => 'Flip the switch to show or hide this method to customers. Bank transfer and SePay must be set up before they can be turned on.'],
        'actions' => ['title' => 'Set up and edit', 'description' => 'Set up each method\'s connection. Methods you added can also be edited and deleted.'],
    ],

    'payment_methods_form' => [
        'intro' => ['title' => 'Offline payment method', 'description' => 'A way to pay where you collect the money yourself, such as an e-wallet or your own transfer.'],
        'name' => ['title' => 'Method name', 'description' => 'The name customers see when choosing how to pay. Required.'],
        'description' => ['title' => 'Description and instructions', 'description' => 'Tell customers how to pay, up to 1000 characters. Required.'],
        'submit' => ['title' => 'Save', 'description' => 'Save the method. A new method starts turned off; turn it on in the list.'],
        'back' => ['title' => 'Back', 'description' => 'Return to the list without saving.'],
    ],

    'shipping_partners_index' => [
        'intro' => ['title' => 'Shipping partners', 'description' => 'Your delivery options: API-connected carriers and partners you deliver with yourself.'],
        'create' => ['title' => 'Add a self-delivery partner', 'description' => 'Add a self-delivery partner with a flat fee.'],
        'table' => ['title' => 'Partner list', 'description' => 'Code, name, contact details, type and status of each partner.'],
        'type' => ['title' => 'Type', 'description' => 'API connected means a carrier such as GHTK or GHN; self delivery is a partner you manage yourself.'],
        'status' => ['title' => 'Turn on or off', 'description' => 'Flip the switch to turn a partner on. API carriers must be fully set up before they can be turned on.'],
        'actions' => ['title' => 'Set up and edit', 'description' => 'API carriers have a connection setup; self-delivery partners can be edited and deleted.'],
    ],

    'shipping_partners_form' => [
        'intro' => ['title' => 'Self-delivery partner', 'description' => 'A delivery partner you manage yourself, with no API connection.'],
        'name' => ['title' => 'Partner name', 'description' => 'The name of the delivery partner. Required.'],
        'fee' => ['title' => 'Flat fee', 'description' => 'The partner\'s fixed delivery fee, in VND. Required.'],
        'phone' => ['title' => 'Phone number', 'description' => 'The driver\'s or hotline number. Optional.'],
        'note' => ['title' => 'Description, notes', 'description' => 'Extra notes such as the delivery area or account details. Shown in the details column of the list.'],
        'submit' => ['title' => 'Save', 'description' => 'Save the partner. A new partner starts turned off; turn it on in the list.'],
        'back' => ['title' => 'Back', 'description' => 'Return to the list without saving.'],
    ],

    'redirects_index' => [
        'intro' => ['title' => 'Redirects & 404 errors', 'description' => 'Old links (for example from a previous WordPress site) redirect 301 to their new page so Google keeps their rankings. They apply only when nothing else answers the old link.'],
        'tabs' => ['title' => 'Redirects and 404 errors', 'description' => 'The 404 tab lists links visitors or Google opened that have no page, most requested first. Click "Create redirect" to handle each one.'],
        'add' => ['title' => 'Add a redirect', 'description' => 'Enter the old link, the new link and the type: 301 (moved for good, keeps rankings), 302/307 (temporary) or 410 (gone for good).'],
        'import' => ['title' => 'CSV import and export', 'description' => 'Import many redirects at once from a CSV file (old link, new link, code), or download the current list.'],
        'search' => ['title' => 'Search and filter', 'description' => 'Search by old or new link; filter by origin: made by hand, WordPress import or from a 404.'],
        'table' => ['title' => 'The list', 'description' => 'Redirects made by the WordPress import follow the new page, even if you change its slug later. Hits show how often the old link is still requested.'],
    ],

    'contact_submissions_index' => [
        'intro' => ['title' => 'Contact messages', 'description' => 'Messages sent through the website contact form, newest first.'],
        'tabs' => ['title' => 'Inbox and Spam', 'description' => 'Messages caught by the spam filter go to the Spam tab: they skip the inbox and send no email.'],
        'search' => ['title' => 'Find a message', 'description' => 'Search by name, phone, email or message text.'],
        'read_filter' => ['title' => 'Read filter', 'description' => 'Show only unread or read messages.'],
        'table' => ['title' => 'Message list', 'description' => 'Unread messages are in bold with a blue dot. Click one to read the whole message, the phone, email and extra details.'],
        'select_all' => ['title' => 'Select several to delete', 'description' => 'Tick the box on each message, or "Chọn tất cả" (select all) for the whole page, then click "Xóa đã chọn" (delete selected) on the bar that appears.'],
        'read' => ['title' => 'Read / unread', 'description' => 'Opening a message marks it read. Use this button to mark it unread again.'],
        'actions' => ['title' => 'Delete and release', 'description' => 'Delete a message. In the Spam tab, click "Không phải spam" (not spam) to move a real customer\'s message back to the inbox. The Call, Zalo and Email buttons above reach the customer right away.'],
    ],

    'notifications_index' => [
        'intro' => ['title' => 'System notifications', 'description' => 'All notifications: new orders, product reviews and new member sign-ups.'],
        'search' => ['title' => 'Find a notification', 'description' => 'Search by order number, customer name, phone, email or review text.'],
        'type' => ['title' => 'Notification type', 'description' => 'Show only new orders, reviews or new members.'],
        'list' => ['title' => 'Notification list', 'description' => 'Newest first. Click one to open that order, review or member. You only see the kinds you have permission to view.'],
    ],

    'notification_settings' => [
        'intro' => ['title' => 'Notification settings', 'description' => 'Choose how you hear about new orders. The page saves by itself when you change a field or flip a switch; there is no save button.'],
        'zalo_oa' => ['title' => 'Zalo Official Account', 'description' => 'Where the Zalo OA connection details are stored: App ID, Template ID, Secret Key and tokens.'],
        'zalo_personal' => ['title' => 'Personal Zalo', 'description' => 'Sends a new-order message to your Zalo through a Zalo Bot. Needs a Bot Token and Chat ID before it can be turned on.'],
        'chat_id' => ['title' => 'Get the Chat ID', 'description' => 'Enter the Bot Token, then click this to pick the Zalo account that receives notifications.'],
        'smtp' => ['title' => 'Email (SMTP)', 'description' => 'Turn on to email new orders to the notification address. Needs the username, app password and receiving email.'],
        'mail_source' => ['title' => 'Mail source', 'description' => 'Shows which configuration the website is using to send email.'],
        'smtp_advanced' => ['title' => 'Advanced settings', 'description' => 'Open to change the SMTP server, port, encryption and sender name and address.'],
        'dashboard' => ['title' => 'Admin notifications', 'description' => 'Turn admin notifications, the alert sound and auto refresh on new orders on or off. The speaker button plays a test sound.'],
    ],

    'posts_index' => [
        'intro' => ['title' => 'Posts', 'description' => 'News and blog posts of the website. From here you write new posts, import posts from WordPress and manage existing ones.'],
        'create' => ['title' => 'New post', 'description' => 'Open the post editor: title, content, featured image, category and SEO.'],
        'ai_draft' => ['title' => 'AI draft', 'description' => 'Give the key points and AI writes a draft for you to edit and publish.'],
        'import_wordpress' => ['title' => 'Import from WordPress', 'description' => 'Upload an XML export from WordPress (up to 20MB). Images in posts are downloaded to the Media library; posts with an existing slug can be skipped or updated.'],
        'filters' => ['title' => 'Filter posts', 'description' => 'Search by title or slug, filter by category and status.'],
        'select_all' => ['title' => 'Bulk actions', 'description' => 'Tick several posts to show, hide or delete them at once.'],
        'table' => ['title' => 'Post list', 'description' => 'Each row shows the category, publish date, SEO score and status. Click the title or the edit button to open a post, the bin to delete it.'],
    ],

    'posts_form' => [
        'intro' => ['title' => 'Write a post', 'description' => 'The left column holds content and SEO, the right column the image, status, category and SEO score.'],
        'general' => ['title' => 'Content per language', 'description' => 'Each tab is one language with its own title, slug and summary. The main language needs a title and content.'],
        'editor' => ['title' => 'Content editor', 'description' => 'Write with headings, bold, lists, links and images. You can paste from another website; foreign styling is removed.'],
        'seo_meta' => ['title' => 'Google preview', 'description' => 'How the post shows on Google. Click Edit SEO title & description to change them for the language in view; keep the title under 60 characters.'],
        'seo' => ['title' => 'SEO settings', 'description' => 'Focus keyword, canonical link, whether Google may index the post and follow its links, plus a search result preview.'],
        'thumbnail' => ['title' => 'Featured image', 'description' => 'Click the box to pick an image from the Media library or upload a new one.'],
        'status' => ['title' => 'Status', 'description' => 'Choose whether the post is shown on the website or hidden.'],
        'category' => ['title' => 'Category', 'description' => 'Put the post in a category so readers find it easily. It can be left empty.'],
        'seo_analysis' => ['title' => 'SEO analysis', 'description' => 'The SEO score and checklist update as you type, based on the focus keyword. The score is saved with the post.'],
    ],

    'post_categories_index' => [
        'intro' => ['title' => 'Post categories', 'description' => 'Group posts by topic, with optional subcategories.'],
        'create' => ['title' => 'Add a category', 'description' => 'Create a category, optionally under a parent category.'],
        'search' => ['title' => 'Find a category', 'description' => 'Search top-level categories by name or slug; their subcategories show right below.'],
        'table' => ['title' => 'Drag to reorder', 'description' => 'Drag the handle at the start of a row to change the order. A subcategory only moves within its own group.'],
        'row_actions' => ['title' => 'Row actions', 'description' => 'Open this menu to quick-edit in a small window, edit in full or delete the category.'],
    ],

    'post_categories_form' => [
        'intro' => ['title' => 'Category details', 'description' => 'Set the name, description and place of the category in the category tree.'],
        'languages' => ['title' => 'Languages', 'description' => 'Each tab is one website language. The name in the main language is required.'],
        'content' => ['title' => 'Name, slug and description', 'description' => 'An empty slug is generated from the name. On other language tabs, the Translate button (if you have the permission) fills in a translation of the main language.'],
        'parent' => ['title' => 'Parent category', 'description' => 'Pick a parent to make this a subcategory, or leave it empty.'],
        'draft' => ['title' => 'Save as draft', 'description' => 'Tick this box to hide the category on the website for now.'],
    ],

    'pages_index' => [
        'intro' => ['title' => 'Policy pages', 'description' => 'Pages such as returns, privacy, shipping and payment. Each page lives at /chinh-sach/slug on the website.'],
        'create' => ['title' => 'Create a page', 'description' => 'Create a new policy page with a title, content and SEO.'],
        'filters' => ['title' => 'Filter pages', 'description' => 'Search by title or slug, filter by published or draft.'],
        'table' => ['title' => 'Page list', 'description' => 'Each row has edit and delete buttons. Deleted pages go to the trash.'],
        'view' => ['title' => 'View and edit on the site', 'description' => 'Open a published page on the website. With the permission, click Edit inline in the admin bar to change the text on the page itself, then Save.'],
    ],

    'pages_form' => [
        'intro' => ['title' => 'Edit a policy page', 'description' => 'Enter the title, content and SEO for each language. The content can also be edited on the website with the Edit inline button.'],
        'toolbar' => ['title' => 'Shortcuts', 'description' => 'Go back to the list, view the page on the website (once published) or save right away.'],
        'languages' => ['title' => 'Languages', 'description' => 'Each tab is one language. The title in the main language is required.'],
        'content' => ['title' => 'Content', 'description' => 'Write the page content. If you do not touch this box, the saved content is kept as is when you save the title or SEO.'],
        'seo' => ['title' => 'SEO', 'description' => 'SEO score, focus keyword and the checks to fix; Google preview, what shows when shared, canonical and whether Google may index it.'],
        'status' => ['title' => 'Status', 'description' => 'A draft is not on the website yet; choose Publish to make it visible.'],
        'save' => ['title' => 'Save the page', 'description' => 'Click to save all changes. Nothing is stored until you click save.'],
        'revisions' => ['title' => 'Recent versions', 'description' => 'Earlier saved versions of the page, up to the latest 20. Click Restore to bring the page back to that version.'],
    ],

    'partners_index' => [
        'intro' => ['title' => 'Partners', 'description' => 'Customer and partner logos shown in the logo strips of the website. This is the one place to edit them.'],
        'create' => ['title' => 'Add a partner', 'description' => 'Pick a logo, enter a name and choose a group to put a new logo on the website.'],
        'groups' => ['title' => 'Partner groups', 'description' => 'Each group is one logo strip of the website. Create, rename or delete groups here.'],
        'tabs' => ['title' => 'View by group', 'description' => 'Click a group to see only its logos. Inside a group, drag the handle at the start of a row to change the display order.'],
        'filters' => ['title' => 'Search and filter', 'description' => 'Search by partner name, filter shown or hidden logos.'],
        'select_all' => ['title' => 'Bulk actions', 'description' => 'Tick several partners to show, hide or delete them at once.'],
        'table' => ['title' => 'Partner list', 'description' => 'Logo, name, group and status of each partner. The switch in the status column shows or hides a logo at once.'],
    ],

    'partners_form' => [
        'intro' => ['title' => 'Partner details', 'description' => 'A partner is one logo in a logo strip of the website.'],
        'logo' => ['title' => 'Logo', 'description' => 'Click to pick a picture from the Media library or upload a new one. A PNG with a transparent background looks best.'],
        'name' => ['title' => 'Partner name', 'description' => 'Shown when the picture cannot load, and helps Google understand it.'],
        'group' => ['title' => 'Group', 'description' => 'Choose the logo strip this partner belongs to.'],
        'url' => ['title' => 'Link', 'description' => 'Optional. With an address, clicking the logo opens the partner site.'],
        'status' => ['title' => 'Show on the website', 'description' => 'Turn off to hide the logo for a while without deleting it.'],
    ],

    'partner_groups_index' => [
        'intro' => ['title' => 'Partner groups', 'description' => 'Each group is one logo strip of the website, e.g. Featured customers or Strategic partners.'],
        'create' => ['title' => 'Add a group', 'description' => 'Create a new group. The website theme needs its key to show that logo strip.'],
        'table' => ['title' => 'Group list', 'description' => 'Name, key and number of partners of each group. A group that still has partners cannot be deleted.'],
    ],

    'partner_groups_form' => [
        'intro' => ['title' => 'Group details', 'description' => 'Give the group a name you will recognise in the admin.'],
        'name' => ['title' => 'Group name', 'description' => 'Only shown in the admin.'],
        'key' => ['title' => 'Group key', 'description' => 'The website theme fetches the logos with this key. Left empty, it is built from the name. It cannot change once saved.'],
    ],

    'banners_index' => [
        'intro' => ['title' => 'Banners', 'description' => 'Promotional images shown in places on the website, such as the home page slider.'],
        'create' => ['title' => 'Add a banner', 'description' => 'Upload a banner image and choose where it appears.'],
        'filters' => ['title' => 'Filter banners', 'description' => 'Filter by display position and status.'],
        'select_all' => ['title' => 'Bulk actions', 'description' => 'Tick several banners to show, hide or delete them at once.'],
        'table' => ['title' => 'Banner list', 'description' => 'Image, link, position and order of each banner, sorted by order. Each row has edit and delete buttons.'],
    ],

    'banners_form' => [
        'intro' => ['title' => 'Banner details', 'description' => 'Pick the image, add a link and choose where the banner appears.'],
        'image' => ['title' => 'Banner image', 'description' => 'Pick an image from the Media library or upload a JPG, PNG or WEBP up to 2MB. When editing, leave it empty to keep the current image.'],
        'title' => ['title' => 'Title', 'description' => 'A title or short description, optional.'],
        'link' => ['title' => 'Link', 'description' => 'Where customers go when they click the banner, optional.'],
        'position' => ['title' => 'Position', 'description' => 'Home page slider, home page sidebar or the promotional area.'],
        'sort_order' => ['title' => 'Order', 'description' => 'Lower numbers come first.'],
        'status' => ['title' => 'On or off', 'description' => 'Turn it off to hide the banner without deleting it.'],
        'save' => ['title' => 'Save the banner', 'description' => 'Click to save. Nothing is stored until you click this button.'],
    ],

    'menus_index' => [
        'intro' => ['title' => 'Navigation menus', 'description' => 'The menus shown on the website, such as the main menu or the footer menu.'],
        'create' => ['title' => 'Add a menu', 'description' => 'Create a new menu, then add its items.'],
        'table' => ['title' => 'Menu list', 'description' => 'Name, the key the theme uses to load the menu, number of items and status.'],
        'items' => ['title' => 'Manage menu items', 'description' => 'Add, edit and reorder the links inside the menu.'],
        'edit' => ['title' => 'Edit the menu', 'description' => 'Change the name or key, or switch the whole menu on or off.'],
    ],

    'menus_form' => [
        'intro' => ['title' => 'Menu details', 'description' => 'A menu is a group of links. Links are added on the Manage menu items page.'],
        'name' => ['title' => 'Menu name', 'description' => 'A name to recognise the menu in the admin, for example Main menu.'],
        'key' => ['title' => 'Menu key', 'description' => 'The key the theme uses to show the right menu, for example primary or footer. Lowercase letters, digits and hyphens only.'],
        'active' => ['title' => 'Shown', 'description' => 'A hidden menu does not appear on the website.'],
        'items' => ['title' => 'Manage menu items', 'description' => 'Go to the list of links in this menu.'],
    ],

    'menu_items_index' => [
        'intro' => ['title' => 'Menu items', 'description' => 'The links in the menu, arranged as parents and children.'],
        'create' => ['title' => 'Add an item', 'description' => 'Add a link to a policy page, product category, post category or a custom path.'],
        'tree' => ['title' => 'Menu tree', 'description' => 'Each item shows its link type and address. A red badge means the linked target has been deleted.'],
        'drag' => ['title' => 'Drag to reorder', 'description' => 'Drag this handle to change the order within the same level. To change the parent, edit the item.'],
        'edit' => ['title' => 'Edit and delete', 'description' => 'Edit an item, or delete it together with the items under it.'],
    ],

    'menu_items_form' => [
        'intro' => ['title' => 'Menu item', 'description' => 'One link in the menu: its label, destination and place in the tree.'],
        'label' => ['title' => 'Label', 'description' => 'The text shown in the menu, entered for each language. The main language is required.'],
        'type' => ['title' => 'Links to', 'description' => 'Choose the destination type: policy page, product category, post category or a custom path.'],
        'parent' => ['title' => 'Parent item', 'description' => 'Put this item under another one to make a submenu, or keep it at the top level.'],
        'target' => ['title' => 'Pick the destination', 'description' => 'This field changes with the link type. A custom path must start with / or be an http/https link.'],
        'new_tab' => ['title' => 'Open in a new tab', 'description' => 'Turn on when the link goes to another website.'],
        'active' => ['title' => 'Shown', 'description' => 'Turn off to hide this item on the website for now.'],
    ],

    'media_index' => [
        'intro' => ['title' => 'Media library', 'description' => 'Where images, PDFs and videos used by products, posts, banners and pages are stored.'],
        'upload' => ['title' => 'Upload files', 'description' => 'Drag and drop or pick several files at once. Images and PDFs up to 10MB, MP4 videos up to 50MB. Files go into the selected folder.'],
        'folders' => ['title' => 'Folders', 'description' => 'Browse files by folder, such as posts or banners, or see them all.'],
        'storage' => ['title' => 'Storage', 'description' => 'Storage used against the limit, when the figures are available.'],
        'cloud' => ['title' => 'Where files are stored', 'description' => 'Shows whether files are on Cloudinary or on the server. Admins with settings access can connect Cloudinary here.'],
        'files' => ['title' => 'File list', 'description' => 'Each file has a copy link button and a delete button.'],
        'select_all' => ['title' => 'Select several files', 'description' => 'Select all, or tick single files, to delete them at once.'],
        'view' => ['title' => 'Sort and view', 'description' => 'Sort by date or name, and switch between grid and list.'],
    ],

    'languages_index' => [
        'intro' => ['title' => 'Content languages', 'description' => 'The website languages, used for URLs, content and API data.'],
        'preferences' => ['title' => 'Content priority', 'description' => 'Choose the main language for entering content and the fallback language.'],
        'default' => ['title' => 'Default language', 'description' => 'The main language for content; required form fields belong to this language.'],
        'fallback' => ['title' => 'Fallback language', 'description' => 'Used when the translation in view is empty. It must differ from the default language.'],
        'save_preferences' => ['title' => 'Save priority', 'description' => 'Click to apply the chosen default and fallback languages.'],
        'add' => ['title' => 'Add a language', 'description' => 'Enter a code of 2 or 3 lowercase letters (for example zh), the English name and the native name. No database change is needed.'],
        'list' => ['title' => 'Current languages', 'description' => 'Edit the name, order and flag path, and switch each language on or off. The default and fallback languages cannot be switched off.'],
        'row_save' => ['title' => 'Save one row', 'description' => 'Each language has its own save button; only that row is updated.'],
    ],

    'dashboard' => [
        'intro' => ['title' => 'Welcome to your admin', 'description' => 'This short guide shows the main parts. Use Next/Back or the arrow keys to move, Skip to close.'],
        'welcome' => ['title' => 'Shortcuts', 'description' => 'Jump to the areas used most: products, orders and customers.'],
        'package' => ['title' => 'Plan limits', 'description' => 'What you have used against your service plan, such as products and posts, banners. Click Refresh for current figures.'],
        'analytics' => ['title' => 'Website traffic', 'description' => 'Google Analytics figures for the period you pick on the right. Needs a Property ID in Settings, Google Analytics tab.'],
        'stats' => ['title' => 'Sales figures', 'description' => 'This month\'s revenue from completed orders, and the number of pending, processing and completed orders.'],
        'charts' => ['title' => 'Charts', 'description' => 'Revenue and orders for each day of this week, and the share of orders by status.'],
        'recent_orders' => ['title' => 'Recent orders', 'description' => 'The latest orders. Click "View all" to open the order list.'],
    ],

    'settings' => [
        'intro' => ['title' => 'Website settings', 'description' => 'Shop-wide information: name, logo, contact details, embed codes and the Google connection.'],
        'tabs' => ['title' => 'Setting groups', 'description' => 'Settings are split into tabs. Click a tab to open that group.'],
        'general' => ['title' => 'General', 'description' => 'Website name (required), logo, favicon, and the default SEO title and description shown on Google.'],
        'tab_contact' => ['title' => 'Contact', 'description' => 'Phone, email, address and map of the head office, other branches, and social media links.'],
        'tab_multilingual' => ['title' => 'Multilingual', 'description' => 'Turn multilingual on and choose how to translate: by hand per content or the GTranslate widget. Only superadmins see this tab.'],
        'tab_embed' => ['title' => 'Embed codes', 'description' => 'Code added to the website\'s header and footer, such as tracking scripts. Only superadmins can edit it.'],
        'tab_analytics' => ['title' => 'Google Analytics', 'description' => 'Enter the Measurement ID, Property ID and Search Console address to see traffic on the dashboard and use This week\'s SEO work.'],
        'save' => ['title' => 'Save settings', 'description' => 'One save button for every tab. Click it to apply the changes to the website.'],
    ],

    'users_index' => [
        'intro' => ['title' => 'Staff accounts', 'description' => 'Accounts of the staff who sign in to the admin. Shoppers are under Customers.'],
        'create' => ['title' => 'Add an account', 'description' => 'Create an account for a staff member and give it a role.'],
        'filters' => ['title' => 'Search and filter', 'description' => 'Search by name or email, filter by role and by active or suspended.'],
        'table' => ['title' => 'Account list', 'description' => 'Role, status and last sign-in of each account.'],
        'row_actions' => ['title' => 'Row actions', 'description' => 'Lock or unlock, edit and delete the account. Signing in as that account is for superadmins only.'],
    ],

    'users_form' => [
        'intro' => ['title' => 'Account details', 'description' => 'Create or edit a staff account. Fields marked * are required.'],
        'account' => ['title' => 'Name and email', 'description' => 'Full name and the email used to sign in. Each email can belong to one account only.'],
        'password' => ['title' => 'Password', 'description' => 'At least 12 characters with upper and lower case letters, a number and a symbol. When editing, leave it empty to keep it.'],
        'avatar' => ['title' => 'Avatar', 'description' => 'Click the round picture to choose an image, up to 2MB.'],
        'role' => ['title' => 'Role', 'description' => 'The role decides which features the account can use. Roles are managed under Roles.'],
        'status' => ['title' => 'Active', 'description' => 'Untick it and the account cannot sign in for now.'],
        'overrides' => ['title' => 'Account permissions', 'description' => 'Exceptions to the role: Grant adds a permission the role lacks, Revoke removes one it has. Leave Follow role when not needed.'],
    ],

    'roles_index' => [
        'intro' => ['title' => 'Roles and permissions', 'description' => 'Each role is a set of permissions. Give an account a role to decide what it can do.'],
        'create' => ['title' => 'Add a role', 'description' => 'Create a role and choose its permissions.'],
        'table' => ['title' => 'Role list', 'description' => 'Each role\'s name and how many accounts use it.'],
        'permissions' => ['title' => 'Role permissions', 'description' => 'The permissions the role holds, grouped by area.'],
        'row_actions' => ['title' => 'Edit and delete', 'description' => 'Edit or delete the role. A role still used by accounts cannot be deleted; system roles have no buttons here.'],
    ],

    'roles_form' => [
        'intro' => ['title' => 'Set up a role', 'description' => 'Name the role, then switch on the permissions it gets.'],
        'name' => ['title' => 'Role name', 'description' => 'For example: Sales staff. It must differ from the other roles\' names.'],
        'permissions' => ['title' => 'Choose permissions', 'description' => 'Each row is a feature with View, Create, Edit, Delete and Use. You can only grant permissions you hold yourself.'],
        'group_toggle' => ['title' => 'Whole group', 'description' => 'Switch on to tick every permission in the group, off to clear them.'],
        'other_actions' => ['title' => 'Other permissions', 'description' => 'Permissions outside the five columns, such as applying AI suggestions.'],
    ],

    'activity_logs' => [
        'intro' => ['title' => 'Activity log', 'description' => 'A record of what admin accounts did: who, what and when.'],
        'delete_all' => ['title' => 'Delete the log', 'description' => 'Deletes every entry matching the current filter, or all of them with no filter. It cannot be undone; superadmins only.'],
        'filters' => ['title' => 'Filter the log', 'description' => 'Filter by who did it, the kind of action and the affected item.'],
        'dates' => ['title' => 'Date range', 'description' => 'Pick a start and an end date, then click Filter.'],
        'select_all' => ['title' => 'Select entries', 'description' => 'Tick entries on this page to delete them at once. Superadmins only.'],
        'table' => ['title' => 'Activity list', 'description' => 'Time, who, action, item and description of each entry.'],
        'changes' => ['title' => 'Changes', 'description' => 'The values that action changed, when they were recorded.'],
    ],

    'features' => [
        'intro' => ['title' => 'Feature settings', 'description' => 'Turn each website feature on or off and see the service plan\'s limits. Only superadmins can open this page.'],
        'sync' => ['title' => 'Sync the plan', 'description' => 'Fetch the service plan from the internal tool again to apply its latest features and limits.'],
        'package' => ['title' => 'Service plan', 'description' => 'The linked plan and when it was last synced. With no plan linked, features are switched by hand.'],
        'limits' => ['title' => 'Limits', 'description' => 'Usage against the plan\'s limits. Read-only: raising a limit means upgrading the plan.'],
        'tabs' => ['title' => 'Feature groups', 'description' => 'Features come in two groups: e-commerce website and non-commerce website.'],
        'group_switch' => ['title' => 'Whole group', 'description' => 'Turn every feature in the group on or off in one click.'],
        'card' => ['title' => 'Each feature', 'description' => 'Flip the switch to turn the feature on or off. The change is saved at once.'],
        'source' => ['title' => 'Package or override', 'description' => 'Package: the state comes from the service plan. Override: changed by hand; click Follow package to restore it.'],
    ],

    'extensions' => [
        'intro' => ['title' => 'Extensions', 'description' => 'The extensions in the website\'s extensions/ folder and the state of each. Only superadmins can open this page.'],
        'stats' => ['title' => 'Overview', 'description' => 'How many extensions in total, running, needing attention and disabled.'],
        'list' => ['title' => 'Extension list', 'description' => 'Name, version, required core version and status. A failing extension shows a reason or a reference code.'],
        'core' => ['title' => 'Core version', 'description' => 'The running core version, used to check whether an extension is compatible.'],
        'about' => ['title' => 'About', 'description' => 'This page is read-only; extensions are not switched on or off here.'],
        'commands' => ['title' => 'Commands', 'description' => 'Enable, disable or check extensions with php artisan commands on the server, even when an extension breaks the admin.'],
    ],

    'core_update' => [
        'intro' => ['title' => 'Version updates', 'description' => 'See the running version and install new releases published by Mat Bao.'],
        'check' => ['title' => 'Check for updates', 'description' => 'Ask the update server whether a new release is available for the website.'],
        'current' => ['title' => 'Running version', 'description' => 'The current version number and the state of the core files. Core files edited by hand block updates.'],
        'offer' => ['title' => 'Available update', 'description' => 'A new release with its notes and the update button. The website is in maintenance for about 1–2 minutes while it installs and returns to the running version if it fails.'],
        'rollback' => ['title' => 'Roll back', 'description' => 'Returns the code to the version before the last update; data is unchanged. Superadmins only.'],
        'access' => ['title' => 'Who may update', 'description' => 'Allow the website\'s admin to update, or not. Only superadmins see this switch.'],
        'history' => ['title' => 'History', 'description' => 'Recent installs, failures and rollbacks.'],
    ],

    'ai_bulk_index' => [
        'intro' => ['title' => 'Bulk SEO', 'description' => 'AI handles many items at once. Results wait for your review and are not saved automatically.'],
        'locale' => ['title' => 'Language', 'description' => 'Choose the language of the content to work on. Shown only when the website has several languages.'],
        'kind_meta' => ['title' => 'SEO titles and descriptions', 'description' => 'AI writes SEO titles and descriptions for products, posts and policy pages that have none. Up to 100 items per run.'],
        'kind_seo_fix' => ['title' => 'Fix by SEO score', 'description' => 'Posts with a focus keyword that fail rules on the title, description or intro.'],
        'kind_alt' => ['title' => 'Images without alt', 'description' => 'AI looks at each image and writes alt text for images in your content that lack it. Up to 20 per run.'],
        'estimate' => ['title' => 'Estimate, then run', 'description' => 'Click the estimate button to see how many AI credits it needs. If you have enough, the Run AI button appears.'],
        'history' => ['title' => 'Recent runs', 'description' => 'Click View on a run to review and apply its results.'],
    ],

    'ai_bulk_show' => [
        'intro' => ['title' => 'Review AI results', 'description' => 'The results of one bulk SEO run. Edit them if needed, then apply.'],
        'summary' => ['title' => 'Run details', 'description' => 'Kind, status, number of items and estimated AI credits.'],
        'running' => ['title' => 'Still running', 'description' => 'AI runs in the background. Reload the page in a few minutes to see the results.'],
        'select_all' => ['title' => 'Select all', 'description' => 'Every item starts ticked. Untick here to clear them all.'],
        'item' => ['title' => 'Each result', 'description' => 'Edit the text right in the box if needed, untick items you do not want.'],
        'apply' => ['title' => 'Apply', 'description' => 'Writes the ticked items to your content. Content changed after the run is left as it is.'],
    ],

    'ai_post_draft' => [
        'intro' => ['title' => 'Write a post with AI', 'description' => 'AI writes a draft from what you provide and saves it unpublished. You read it, edit it and publish it yourself.'],
        'notice' => ['title' => 'Good to know', 'description' => 'AI does not make up prices, figures or promises; missing details are marked for you to fill in.'],
        'tab_brief' => ['title' => 'From business details', 'description' => 'Fill in the topic, readers and key points and AI writes the post (4 AI credits).'],
        'keyword' => ['title' => 'Main keyword', 'description' => '2–5 words people type into Google, for example: upvc pipe. Not a sentence.'],
        'business' => ['title' => 'Business details', 'description' => 'Who you are, what you sell, where, what sets you apart. Remembered for the next draft.'],
        'key_points' => ['title' => 'Key points', 'description' => 'One per line, only what you know to be true.'],
        'style' => ['title' => 'Tone and length', 'description' => 'Choose the tone and the target word count.'],
        'submit' => ['title' => 'Write the draft', 'description' => 'AI takes about 30–50 seconds, then the draft opens in the post editor.'],
        'tab_notes' => ['title' => 'From notes', 'description' => 'Paste notes, bullet points or a transcript and AI turns them into a post (3 AI credits per 1,500 words).'],
    ],

    'ai_research' => [
        'intro' => ['title' => 'Keywords and competitors', 'description' => 'See which Google keywords competitors rank for that your website lacks, pick topics to write about and track your rankings weekly.'],
        'competitors' => ['title' => 'Competitors', 'description' => 'Your competitor websites and the data status of each.'],
        'suggest' => ['title' => 'Suggest competitors', 'description' => 'Find websites that rank on Google for the same keywords as you.'],
        'add_competitor' => ['title' => 'Add a competitor', 'description' => 'Enter a domain such as rival.com. Their keyword list arrives in about a minute.'],
        'gaps' => ['title' => 'Keyword gaps', 'description' => 'Keywords competitors have and you do not, with search volume and difficulty. Click Track to follow your ranking for one.'],
        'topics' => ['title' => 'Topic ideas', 'description' => 'AI groups the keywords into post topics (2 AI credits).'],
        'ranks' => ['title' => 'Rank tracking', 'description' => 'Your Google position for each keyword, checked every Monday morning.'],
        'add_keywords' => ['title' => 'Add keywords', 'description' => 'Enter one keyword per line to start tracking them.'],
    ],

    'ai_seo_tasks' => [
        'intro' => ['title' => 'This week\'s SEO work', 'description' => 'The pages most worth improving, ranked by the clicks they could gain, based on Google Search Console.'],
        'setup' => ['title' => 'Connect Search Console', 'description' => 'This page only has data once the website is connected to Google Search Console.'],
        'connect' => ['title' => 'Open Settings', 'description' => 'Go to the Google Analytics tab in Settings to enter the Search Console address.'],
        'stats' => ['title' => '28-day overview', 'description' => 'Clicks, impressions, click rate and average position, compared with the 28 days before.'],
        'task' => ['title' => 'A task', 'description' => 'Each card is one page, with the kind of problem, its figures and what people searched. Click the edit button to fix the content.'],
        'advice' => ['title' => 'AI advice', 'description' => 'AI explains the problem and suggests a fix, using 1 AI credit each time.'],
        'mark' => ['title' => 'Done or dismiss', 'description' => 'Done hides the task; it returns after 4 weeks if Google still sees the problem. Dismiss hides it for 3 months.'],
    ],
];
