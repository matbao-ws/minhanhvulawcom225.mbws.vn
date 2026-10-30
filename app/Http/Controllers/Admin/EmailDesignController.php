<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ContactInquiryMail;
use App\Mail\EmailPreviewSamples;
use App\Mail\MailSettingsTestMail;
use App\Mail\OrderStatusMail;
use App\Mail\OwnerNewOrderMail;
use App\Notifications\QueuedResetPassword;
use App\Services\ActivityLogger;
use App\Services\EmailAppearance;
use App\Services\MailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Quản trị › Giao diện email: how the shop's emails look and read, and which are sent, with a live
 * preview of each email rendered from the form as it stands (nothing saved until "Lưu").
 */
class EmailDesignController extends Controller
{
    public function __construct(private readonly EmailAppearance $appearance) {}

    public function index(): View
    {
        return view('admin.email_design.index', [
            'settings' => $this->appearance->settings(),
            'look' => $this->appearance->look(),
            'emails' => EmailAppearance::EMAILS,
            // The default wording, shown as each field's placeholder.
            'defaults' => collect(EmailAppearance::EMAILS)->map(fn ($meta, $email) => collect(EmailAppearance::TEXT_FIELDS)
                ->mapWithKeys(fn ($field) => [$field => (string) __("emails.appearance.{$email}.{$field}")])->all())->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate($this->rules());
        $saved = $this->appearance->save($this->input($request));

        ActivityLogger::log('email_design.updated', null, __('admin.email_design.saved'), ['layout' => $saved['layout'], 'accent' => $saved['accent']]);

        return back()->with('success', __('admin.email_design.saved'));
    }

    /** One email rendered with the form's unsaved settings and a sample order; nothing is stored. */
    public function preview(Request $request): Response
    {
        $request->validate($this->rules() + ['email' => ['required', Rule::in(array_keys(EmailAppearance::EMAILS))]]);
        app()->instance(EmailAppearance::class, $this->appearance->using($this->input($request)));

        $email = (string) $request->input('email');
        $order = EmailPreviewSamples::order($email);

        $html = match ($email) {
            'owner_new_order' => (new OwnerNewOrderMail($order))->render(),
            'contact' => (new ContactInquiryMail(EmailPreviewSamples::contact()))->render(),
            'reset_password' => (string) (new QueuedResetPassword('xem-truoc'))->toMail($request->user())->render(),
            'settings_test' => $this->settingsTest(),
            default => (new OrderStatusMail($order, $email))->render(),
        };

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'layout' => ['nullable', Rule::in(EmailAppearance::LAYOUTS)],
            'accent' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['nullable', 'string', 'max:500'],
            'logo_align' => ['nullable', Rule::in(EmailAppearance::ALIGNS)],
            'banner' => ['nullable', 'string', 'max:500'],
            'signature' => ['nullable', 'string', 'max:500'],
            'return_policy' => ['nullable', 'string', 'max:500'],
            'refund_note' => ['nullable', 'string', 'max:500'],
            'socials' => ['nullable', 'array'],
            'socials.*' => ['nullable', 'url:https', 'max:300'],
            'emails' => ['nullable', 'array'],
            'emails.*.enabled' => ['nullable', 'boolean'],
            'emails.*.subject' => ['nullable', 'string', 'max:150'],
            'emails.*.heading' => ['nullable', 'string', 'max:150'],
            'emails.*.intro' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, mixed> the design fields only, for EmailAppearance::sanitize() */
    private function input(Request $request): array
    {
        return $request->only(['layout', 'accent', 'logo', 'logo_align', 'banner', 'signature', 'return_policy', 'refund_note', 'socials', 'emails']);
    }

    private function settingsTest(): string
    {
        $mail = app(MailSettings::class);
        $smtp = $mail->stored();
        $own = $mail->usesAdminSettings();

        return (new MailSettingsTestMail(
            $this->appearance->look()['shop'],
            $own ? (data_get($smtp, 'from_email') ?: null) : null,
            $own ? (data_get($smtp, 'from_name') ?: null) : null,
            [
                'via' => $own ? 'own_smtp' : ($mail->source() === MailSettings::SOURCE_REMOTE ? 'relay' : 'default'),
                'host' => $own ? data_get($smtp, 'host') : null,
                'port' => $own ? data_get($smtp, 'port') : null,
                'encryption' => $own ? data_get($smtp, 'encryption') : null,
                'to' => $mail->ownerEmails(),
            ],
        ))->render();
    }
}
