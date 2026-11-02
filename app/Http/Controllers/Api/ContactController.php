<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ContactInquiryMail;
use App\Models\ContactSubmission;
use App\Services\Contact\ContactSpamGuard;
use App\Services\EmailAppearance;
use App\Services\MailSettings;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

/**
 * POST /api/public/contact — the storefront's contact / quote form. Spam gets the same answer as a
 * real inquiry (a bot must not learn what tripped it) but is kept flagged, without the email.
 */
class ContactController extends Controller
{
    private const SENT = 'Yêu cầu của bạn đã được gửi thành công. Chúng tôi sẽ liên hệ lại sớm nhất!';

    public function store(Request $request, ContactSpamGuard $guard): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            // 8–15 digits, written with the usual separators.
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9 .()\-]+$/', function ($attribute, $value, $fail) {
                $digits = strlen(preg_replace('/\D/', '', (string) $value));
                if ($digits < 8 || $digits > 15) {
                    $fail('Số điện thoại không hợp lệ.');
                }
            }],
            'email' => 'nullable|email|max:255',
            'message' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $inquiry = $request->only(['name', 'phone', 'email', 'message']);
        $meta = $this->extraFields($request->except(['name', 'phone', 'email', 'message', ...ContactSpamGuard::CONTROL_FIELDS]));
        $spam = $guard->reason($request, $inquiry);

        if ($spam === ContactSpamGuard::DUPLICATE) {
            return response()->json(['success' => true, 'message' => self::SENT]);
        }

        ContactSubmission::query()->create([
            ...$inquiry,
            'meta' => $meta !== [] ? $meta : null,
            'is_spam' => $spam !== null,
            'spam_reason' => $spam,
            'ip' => $request->ip(),
        ]);

        if ($spam !== null) {
            return response()->json(['success' => true, 'message' => self::SENT]);
        }

        // Saved above either way; the shop may switch the email off (Quản trị › Giao diện email).
        if (! app(EmailAppearance::class)->enabled('contact')) {
            return response()->json(['success' => true, 'message' => self::SENT]);
        }

        try {
            // The shop owner's address set in the admin (Cài đặt thông báo), else MAIL_SELLER.
            Mail::to(app(MailSettings::class)->ownerEmails())->send(new ContactInquiryMail($inquiry, $meta));
        } catch (Exception $e) {
            Log::error('Contact form email failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Không thể gửi yêu cầu vào lúc này. Vui lòng thử lại sau.',
            ], 500);
        }

        return response()->json(['success' => true, 'message' => self::SENT]);
    }

    /**
     * Extra fields a theme's form adds (company, budget…) are kept as meta, but only
     * in the shape a form produces: a bounded number of short, flat text values.
     * Anything else was stored verbatim, so one request could park megabytes of
     * JSON in the table and in every row of the admin list.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, string>
     */
    private function extraFields(array $fields): array
    {
        $kept = [];
        foreach ($fields as $key => $value) {
            if (count($kept) >= 20) {
                break;
            }
            if (is_string($key) && preg_match('/^[a-z0-9_]{1,40}$/i', $key) && (is_string($value) || is_numeric($value))) {
                $kept[$key] = mb_substr((string) $value, 0, 1000);
            }
        }

        return $kept;
    }
}
