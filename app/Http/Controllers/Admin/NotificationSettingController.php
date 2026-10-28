<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\MailSettingsTestMail;
use App\Models\FeatureSetting;
use App\Models\ProjectSetting;
use App\Services\MailSettings;
use App\Services\SiteBranding;
use App\Support\SecretMask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class NotificationSettingController extends Controller
{
    /** Each address is one more mail per notification, counted on the package's monthly quota. */
    private const MAX_OWNER_EMAILS = 5;

    /**
     * Display the notification settings page.
     */
    public function index()
    {
        $settingsRecord = ProjectSetting::query()->where('setting_key', 'notification_settings')->first();
        $settings = $settingsRecord ? $settingsRecord->setting_value : [];

        // Define default settings structures
        $defaults = [
            'zalo_oa' => [
                'enabled' => false,
                'app_id' => '',
                'secret_key' => '',
                'access_token' => '',
                'refresh_token' => '',
                'template_id' => '',
            ],
            'zalo_personal' => [
                'enabled' => false,
                'bot_token' => '',
                'chat_id' => '',
            ],
            'smtp' => [
                'enabled' => false,
                'host' => 'smtp.mailtrap.io',
                'port' => 2525,
                'encryption' => 'tls',
                'username' => '',
                'password' => '',
                'from_email' => 'noreply@yourdomain.com',
                'from_name' => 'Laravel Shop',
                'owner_email' => 'admin@yourdomain.com',
            ],
            'dashboard' => [
                'enabled' => true,
                'play_sound' => true,
                'auto_refresh' => true,
            ],
        ];

        // Merge saved settings with defaults to avoid missing keys in view
        $settings = array_merge($defaults, is_array($settings) ? $settings : []);
        foreach ($defaults as $section => $fields) {
            $settings[$section] = array_merge($fields, is_array($settings[$section] ?? null) ? $settings[$section] : []);
        }

        if (! auth()->user()->can('settings.update')) {
            $settings = SecretMask::apply($settings, ['zalo_oa.secret_key', 'zalo_oa.access_token', 'zalo_oa.refresh_token', 'zalo_personal.bot_token', 'smtp.password']);
        }

        $zaloFeatureEnabled = FeatureSetting::query()
            ->where('feature_code', 'zalo_oa')
            ->where('is_enabled', true)
            ->exists();

        $mailSettings = app(MailSettings::class);
        $mailSource = $mailSettings->source();
        $mailSourceDomain = $mailSource === MailSettings::SOURCE_REMOTE
            ? (string) substr(strrchr((string) $mailSettings->sharedSender(), '@') ?: '@mail.matbaows.com', 1)
            : null;

        return view('admin.notification_settings.index', compact('settings', 'zaloFeatureEnabled', 'mailSource', 'mailSourceDomain'));
    }

    /**
     * Update the notification settings.
     */
    public function update(Request $request)
    {
        $zaloFeatureEnabled = FeatureSetting::query()
            ->where('feature_code', 'zalo_oa')
            ->where('is_enabled', true)
            ->exists();

        if (! $zaloFeatureEnabled) {
            $tryingToEnable = filter_var($request->input('zalo_oa.enabled'), FILTER_VALIDATE_BOOLEAN)
                || filter_var($request->input('zalo_personal.enabled'), FILTER_VALIDATE_BOOLEAN);

            if ($tryingToEnable) {
                $errorMessage = __('admin.notification_settings.no_package');
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => $errorMessage,
                    ], 403);
                }

                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', $errorMessage);
            }
        }

        $this->normalizeOwnerEmails($request);

        $validated = $request->validate([
            // Zalo OA
            'zalo_oa.enabled' => 'nullable|boolean',
            'zalo_oa.app_id' => 'required_if:zalo_oa.enabled,1,true|nullable|string|max:255',
            'zalo_oa.secret_key' => 'required_if:zalo_oa.enabled,1,true|nullable|string|max:255',
            'zalo_oa.access_token' => 'required_if:zalo_oa.enabled,1,true|nullable|string|max:1000',
            'zalo_oa.refresh_token' => 'required_if:zalo_oa.enabled,1,true|nullable|string|max:1000',
            'zalo_oa.template_id' => 'required_if:zalo_oa.enabled,1,true|nullable|string|max:255',

            // Zalo Personal
            'zalo_personal.enabled' => 'nullable|boolean',
            'zalo_personal.bot_token' => 'required_if:zalo_personal.enabled,1,true|nullable|string|max:1000',
            'zalo_personal.chat_id' => 'required_if:zalo_personal.enabled,1,true|nullable|string|max:255',

            // SMTP
            ...$this->smtpRules(),

            // Dashboard
            'dashboard.enabled' => 'nullable|boolean',
            'dashboard.play_sound' => 'nullable|boolean',
            'dashboard.auto_refresh' => 'nullable|boolean',
        ]);

        // Standardize switches to boolean (since form checkboxes only send values if checked)
        $settingsData = [
            'zalo_oa' => [
                'enabled' => (bool) ($validated['zalo_oa']['enabled'] ?? false),
                'app_id' => $validated['zalo_oa']['app_id'] ?? '',
                'secret_key' => $validated['zalo_oa']['secret_key'] ?? '',
                'access_token' => $validated['zalo_oa']['access_token'] ?? '',
                'refresh_token' => $validated['zalo_oa']['refresh_token'] ?? '',
                'template_id' => $validated['zalo_oa']['template_id'] ?? '',
            ],
            'zalo_personal' => [
                'enabled' => (bool) ($validated['zalo_personal']['enabled'] ?? false),
                'bot_token' => $validated['zalo_personal']['bot_token'] ?? '',
                'chat_id' => $validated['zalo_personal']['chat_id'] ?? '',
            ],
            'smtp' => [
                'enabled' => (bool) ($validated['smtp']['enabled'] ?? false),
                'host' => $validated['smtp']['host'] ?? '',
                'port' => isset($validated['smtp']['port']) ? (int) $validated['smtp']['port'] : null,
                'encryption' => $validated['smtp']['encryption'] ?? 'none',
                'username' => $validated['smtp']['username'] ?? '',
                'password' => $validated['smtp']['password'] ?? '',
                'from_email' => $validated['smtp']['from_email'] ?? '',
                'from_name' => $validated['smtp']['from_name'] ?? '',
                'owner_email' => $validated['smtp']['owner_email'] ?? '',
            ],
            'dashboard' => [
                'enabled' => (bool) ($validated['dashboard']['enabled'] ?? false),
                'play_sound' => (bool) ($validated['dashboard']['play_sound'] ?? false),
                'auto_refresh' => (bool) ($validated['dashboard']['auto_refresh'] ?? false),
            ],
        ];

        ProjectSetting::updateOrCreate(
            ['setting_key' => 'notification_settings'],
            ['setting_value' => $settingsData]
        );

        // The resolved SMTP config is cached; without this the shop keeps sending
        // through the old credentials for up to five minutes after saving.
        MailSettings::flush();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => __('admin.notification_settings.save_success'),
            ]);
        }

        return redirect()
            ->route('admin.notification-settings.index')
            ->with('success', __('admin.notification_settings.save_success'));
    }

    /**
     * Get Zalo Chat ID automatically using Zalo Bot getUpdates API.
     */
    public function getZaloChatId(Request $request)
    {
        $validated = $request->validate([
            'bot_token' => 'required|string|max:1000',
        ]);

        $botToken = preg_replace('/^zbot:/i', '', trim($validated['bot_token']));

        try {
            $response = Http::post("https://bot-api.zaloplatforms.com/bot{$botToken}/getUpdates", [
                'timeout' => 5,
            ]);

            if (! $response->successful() || ! $response->json('ok')) {
                return response()->json([
                    'success' => false,
                    'message' => __('admin.notification_settings.zalo_personal.get_chat_id_error'),
                ], 400);
            }

            $updates = $response->json('result') ?? [];
            $chats = [];

            foreach ($updates as $update) {
                $chatId = data_get($update, 'message.chat.id');
                $displayName = data_get($update, 'message.from.display_name') ?? data_get($update, 'message.from.username') ?? 'Zalo User';

                if ($chatId) {
                    $chats[$chatId] = [
                        'chat_id' => $chatId,
                        'display_name' => $displayName,
                    ];
                }
            }

            $chatsList = array_values($chats);

            if (empty($chatsList)) {
                return response()->json([
                    'success' => true,
                    'chats' => [],
                    'message' => __('admin.notification_settings.zalo_personal.get_chat_id_empty'),
                ]);
            }

            return response()->json([
                'success' => true,
                'chats' => $chatsList,
            ]);

        } catch (\Exception $e) {
            // The HTTP client's message holds the request URL, and the bot token is part of that URL.
            Log::warning('Zalo getUpdates failed.', ['exception' => $e::class]);

            return response()->json([
                'success' => false,
                'message' => __('admin.notification_settings.zalo_personal.get_chat_id_error'),
            ], 500);
        }
    }

    /**
     * Send one test email with the settings as they are on the form, saved or not: the
     * admin's own SMTP account when it is switched on, otherwise the mailer in use now.
     */
    public function sendTestEmail(Request $request): JsonResponse
    {
        $this->normalizeOwnerEmails($request);
        $validated = $request->validate($this->smtpRules());
        $smtp = (array) ($validated['smtp'] ?? []);

        $recipients = MailSettings::splitEmails($smtp['owner_email'] ?? '') ?: array_filter([(string) $request->user()?->email]);
        if (! $recipients) {
            return response()->json(['success' => false, 'message' => __('admin.notification_settings.smtp.test_no_recipient')], 422);
        }

        $ownSmtp = filter_var($smtp['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (! $ownSmtp && in_array(config('mail.mailers.'.config('mail.default').'.transport'), ['log', 'array'], true)) {
            return response()->json(['success' => false, 'message' => __('admin.notification_settings.smtp.test_log_only')], 422);
        }

        try {
            $mailer = $ownSmtp
                ? Mail::build(MailSettings::smtpMailer([
                    'host' => $smtp['host'],
                    'port' => (int) $smtp['port'],
                    'encryption' => $smtp['encryption'],
                    'username' => $smtp['username'],
                    'password' => $smtp['password'],
                ]) + ['timeout' => 15])
                : Mail::mailer();

            $mailer->to($recipients)->send(new MailSettingsTestMail(
                (string) app(SiteBranding::class)->current()['name'],
                $ownSmtp ? $smtp['from_email'] : null,
                $ownSmtp ? $smtp['from_name'] : null,
            ));
        } catch (\Throwable $e) {
            // The SMTP server's own words ("535 Authentication failed") are what the admin needs; the
            // transport never puts the password in them.
            Log::warning('Test email failed.', ['exception' => $e::class]);

            return response()->json([
                'success' => false,
                'message' => __('admin.notification_settings.smtp.test_failed', ['error' => Str::limit(trim($e->getMessage()), 300)]),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('admin.notification_settings.smtp.test_sent', ['emails' => implode(', ', $recipients)]),
        ]);
    }

    /**
     * Several recipients, separated by commas (or semicolons, spaces, new lines), kept as "a@x.vn, b@y.vn".
     */
    private function normalizeOwnerEmails(Request $request): void
    {
        if ($request->has('smtp.owner_email')) {
            $request->merge(['smtp' => array_merge((array) $request->input('smtp'), [
                'owner_email' => implode(', ', MailSettings::splitEmails($request->input('smtp.owner_email'))),
            ])]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function smtpRules(): array
    {
        return [
            'smtp.enabled' => 'nullable|boolean',
            'smtp.host' => 'required_if:smtp.enabled,1,true|nullable|string|max:255',
            'smtp.port' => 'required_if:smtp.enabled,1,true|nullable|integer|min:1|max:65535',
            'smtp.encryption' => 'required_if:smtp.enabled,1,true|nullable|string|in:ssl,tls,none',
            'smtp.username' => 'required_if:smtp.enabled,1,true|nullable|string|max:255',
            'smtp.password' => 'required_if:smtp.enabled,1,true|nullable|string|max:255',
            'smtp.from_email' => 'required_if:smtp.enabled,1,true|nullable|email|max:255',
            'smtp.from_name' => 'required_if:smtp.enabled,1,true|nullable|string|max:255',
            'smtp.owner_email' => ['required_if:smtp.enabled,1,true', 'nullable', 'string', 'max:1000', function (string $attribute, mixed $value, \Closure $fail): void {
                $emails = MailSettings::splitEmails($value);
                if (count($emails) > self::MAX_OWNER_EMAILS) {
                    $fail(__('admin.notification_settings.smtp.owner_email_too_many', ['max' => self::MAX_OWNER_EMAILS]));
                }
                foreach ($emails as $email) {
                    if (validator(['email' => $email], ['email' => 'email'])->fails()) {
                        $fail(__('admin.notification_settings.smtp.owner_email_invalid', ['email' => $email]));
                    }
                }
            }],
        ];
    }
}
