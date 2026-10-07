<?php

return [
    'common' => [
        'reference' => 'Error code',
        'reference_hint' => 'Send this code to the website administrator to look it up.',
        'back_to_home' => 'Back to Home',
        'go_to_dashboard' => 'Go to Dashboard',
        'go_back' => 'Go Back',
        'reload_page' => 'Reload Page',
        'contact_support' => 'Contact Support',
        'helpful_links' => 'Helpful links:',
        'system_status' => 'System Status',
        'operational' => 'Operational',
        'error_reference' => 'Error Reference ID:',
        'technical_details' => 'Technical Details',
        'copyright' => 'Copyright',
        'all_rights_reserved' => 'All rights reserved.',
    ],

    '403' => [
        'title' => '403 - Access Forbidden',
        'badge' => 'Error 403 • Access Forbidden',
        'heading' => 'Access Denied',
        'message' => 'Sorry, you do not have permission or sufficient privileges to access this page or resource. Please contact an administrator if you believe this is a mistake.',
        'tip' => 'Hint: Check your login credentials or contact your system administrator to request access.',
    ],

    '404' => [
        'title' => '404 - Page Not Found',
        'badge' => 'Error 404 • Page Not Found',
        'heading' => 'Oops! Page not found',
        'message' => 'The page you are looking for might have been removed, had its name changed, or is temporarily unavailable. Please check the URL or return home.',
        'tip' => 'Hint: Check the URL for typing errors or use the navigation buttons below.',
    ],

    '419' => [
        'title' => '419 - Page Expired',
        'badge' => 'Error 419 • Page Expired',
        'heading' => 'Your session has expired',
        'message' => 'Your session or CSRF security token has expired due to inactivity. This protects your account and data from unauthorized access.',
        'tip' => 'Hint: Click the "Reload Page" button below to refresh your secure session and try again.',
    ],

    '429' => [
        'title' => '429 - Too Many Requests',
        'badge' => 'Error 429 • Too Many Requests',
        'heading' => 'Too Many Requests',
        'message' => 'We have detected too many requests sent from your IP address or network in a short period of time. Requests are temporarily limited to maintain system stability.',
        'tip' => 'Hint: Please wait 30–60 seconds, then reload the page or retry your action.',
    ],

    '500' => [
        'title' => '500 - Internal Server Error',
        'badge' => 'Error 500 • Internal Server Error',
        'heading' => 'Internal Server Error',
        'message' => 'The server encountered an unexpected error while processing your request. Our technical team has been notified and is working to resolve it promptly.',
        'tip' => 'Hint: You can try reloading the page after a few moments or return to the previous page.',
    ],

    '503' => [
        'title' => '503 - Service Unavailable',
        'badge' => 'Error 503 • Service Unavailable',
        'heading' => 'Service Under Maintenance',
        'message' => 'The service is temporarily unavailable due to scheduled maintenance or system upgrades. We will be back online shortly!',
        'tip' => 'Hint: Please check back in a few minutes or click the reload button below to check status.',
    ],

    '505' => [
        'title' => '505 - HTTP Version Not Supported',
        'badge' => 'Error 505 • HTTP Version Not Supported',
        'heading' => 'HTTP Version Not Supported',
        'message' => 'The server does not support or refuses to support the HTTP protocol version used in the request message.',
        'tip' => 'Hint: Try updating your web browser or checking your network and proxy settings.',
    ],
];
