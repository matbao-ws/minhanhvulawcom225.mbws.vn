<?php

return [
    'auth' => [
        'invalid_credentials' => 'The email or password is incorrect.',
        'reset_link_sent' => 'If the email exists, a password reset link has been sent.',
        'invalid_reset_token' => 'The password reset token is invalid or has expired.',
    ],
    'api' => [
        'invalid_data' => 'The provided data is invalid.',
        'email_unavailable' => 'The email is invalid or already in use.',
        'registered' => 'Your account has been registered successfully.',
        'logged_in' => 'You have signed in successfully.',
        'logged_out' => 'You have signed out successfully.',
        'password_reset' => 'Your password has been reset successfully.',
        'address_created' => 'The address has been added successfully.',
        'address_updated' => 'The address has been updated successfully.',
        'address_deleted' => 'The address has been deleted successfully.',
        'address_defaulted' => 'The default address has been updated successfully.',
        'address_not_editable' => 'The address was not found or you cannot edit it.',
        'address_not_deletable' => 'The address was not found or you cannot delete it.',
        'address_not_actionable' => 'The address was not found or you cannot perform this action on it.',
    ],
    'admin' => [
        'contact_deleted' => 'The contact submission has been deleted.',
        'promotion_created' => 'The promotion has been created.',
        'promotion_updated' => 'The promotion has been updated.',
        'promotion_deleted' => 'The promotion has been deleted.',
        'promotion_used' => 'A promotion with recorded usage cannot be deleted. Disable it instead.',
        'promotion_variant_missing' => 'One or more SKUs no longer exist.',
        'promotion_target_used' => 'A SKU with flash-sale usage cannot be removed. Disable the promotion or keep that SKU.',
    ],
];
