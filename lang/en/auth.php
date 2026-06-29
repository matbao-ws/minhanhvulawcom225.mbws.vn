<?php

return [
    'failed' => 'These credentials do not match our records or the account is inactive.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
    'login' => [
        'title' => 'Login',
        'heading' => 'Admin sign in',
        'email' => 'Email Address',
        'email_placeholder' => 'Enter your email',
        'password' => 'Password',
        'password_placeholder' => 'Enter your password',
        'forgot_password' => 'Forgot password?',
        'remember' => 'Keep me logged in',
        'submit' => 'Sign In',
        'processing' => 'Signing in...',
        'success' => 'Login successful.',
        'request_failed' => 'Could not submit the login request. Please try again.',
        'email_not_found' => 'This email does not exist in the system.',
        'inactive' => 'This account is locked or has not been activated.',
        'password_incorrect' => 'The password is incorrect.',
        'unauthorized' => 'Your account does not have permission to access the administration panel.',

        'slides' => [
            [
                'title' => 'Central ecommerce admin',
                'description' => 'Manage package state, enabled features, and operational settings from one admin area.',
            ],
            [
                'title' => 'Module-ready foundation',
                'description' => 'Gate features by service package without changing core architecture.',
            ],
            [
                'title' => 'Prepared for client APIs',
                'description' => 'Laravel core serves Blade admin screens and REST APIs for storefront clients.',
            ],
        ],
    ],
    'forgot' => [
        'title' => 'Forgot Password',
        'description' => 'Enter the email address associated with your account. We will email you a link to reset your password.',
        'submit' => 'Send Password Reset Link',
        'processing' => 'Sending...',
        'back_to_login' => 'Back to Login',
        'request_failed' => 'Could not submit the password reset request. Please try again.',
        'slides' => [
            ['title' => 'Protect admin access'],
            ['title' => 'Reset passwords by email'],
            ['title' => 'Continue managing ecommerce core'],
        ],
    ],
    'reset' => [
        'title' => 'Reset Password',
        'heading' => 'Reset Password',
        'password' => 'New Password',
        'password_confirmation' => 'Confirm Password',
        'submit' => 'Update Password',
    ],
    'logout' => [
        'success' => 'Logout successful.',
    ],
];
