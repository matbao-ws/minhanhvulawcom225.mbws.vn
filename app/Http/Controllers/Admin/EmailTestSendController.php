<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ContactInquiryMail;
use App\Mail\EmailPreviewSamples;
use App\Mail\MailSettingsTestMail;
use App\Mail\OrderStatusMail;
use App\Mail\OwnerNewOrderMail;
use App\Notifications\QueuedResetPassword;
use App\Services\EmailAppearance;
use App\Services\MailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * TEMPORARY test page (branch test/email-send-page): sends the chosen email templates, with the
 * design preview's sample data, to the addresses typed in, through this website's real mail route.
 * Delete this file, resources/views/admin/email_test_send.blade.php and its two routes when done.
 */
class EmailTestSendController extends Controller
{
    public function index(): View
    {
        return view('admin.email_test_send', [
            'emails' => array_keys(EmailAppearance::EMAILS),
            'route' => app(MailSettings::class)->source(),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'to' => ['required', 'string', 'max:500'],
            'emails' => ['required', 'array', 'min:1'],
            'emails.*' => [Rule::in(array_keys(EmailAppearance::EMAILS))],
        ]);
        $to = MailSettings::splitEmails($data['to']);
        if ($to === [] || count($to) > 5) {
            return back()->withInput()->withErrors(['to' => 'Nhập 1 đến 5 email hợp lệ, cách nhau bằng dấu phẩy.']);
        }

        $shop = app(EmailAppearance::class)->look()['shop'];
        $results = [];
        foreach ($data['emails'] as $email) {
            try {
                [$subject, $html] = $this->render($email, $to, $shop, $request);
                Mail::html($html, fn ($message) => $message->to($to)->subject('[THỬ] '.$subject));
                $results[] = [$email, true, $subject];
            } catch (Throwable $e) {
                $results[] = [$email, false, class_basename($e).': '.mb_substr($e->getMessage(), 0, 300)];
            }
        }

        return back()->withInput()->with('results', $results);
    }

    /** @return array{0: string, 1: string} subject and HTML */
    private function render(string $email, array $to, string $shop, Request $request): array
    {
        if (str_starts_with($email, 'order_')) {
            $mail = new OrderStatusMail(EmailPreviewSamples::order($email), $email);

            return [$mail->envelope()->subject, $mail->render()];
        }

        return match ($email) {
            'owner_new_order' => (function () {
                $mail = new OwnerNewOrderMail(EmailPreviewSamples::order('order_placed'));

                return [$mail->envelope()->subject, $mail->render()];
            })(),
            'contact' => (function () {
                $mail = new ContactInquiryMail(EmailPreviewSamples::contact());

                return [$mail->envelope()->subject, $mail->render()];
            })(),
            'reset_password' => (function () use ($request) {
                $message = (new QueuedResetPassword('thu-email'))->toMail($request->user());

                return [(string) $message->subject, (string) $message->render()];
            })(),
            default => (function () use ($to, $shop) {
                $mail = new MailSettingsTestMail($shop, null, null, ['via' => app(MailSettings::class)->usesAdminSettings() ? 'own_smtp' : 'relay', 'to' => $to]);

                return [$mail->envelope()->subject, $mail->render()];
            })(),
        };
    }
}
