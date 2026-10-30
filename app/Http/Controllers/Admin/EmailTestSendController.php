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
                $subject = $this->deliver($email, $to, $shop, $request);
                $results[] = [$email, true, $subject];
            } catch (Throwable $e) {
                $results[] = [$email, false, class_basename($e).': '.mb_substr($e->getMessage(), 0, 300)];
            }
        }

        return back()->withInput()->with('results', $results);
    }

    /**
     * Sends the real mailable (not pre-rendered HTML), so the logo is embedded exactly as in a
     * live mail. Returns the subject.
     */
    private function deliver(string $email, array $to, string $shop, Request $request): string
    {
        if ($email === 'reset_password') {
            $message = (new QueuedResetPassword('thu-email'))->toMail($request->user());
            Mail::send($message->view, $message->viewData, fn ($mail) => $mail->to($to)->subject('[THỬ] '.$message->subject));

            return (string) $message->subject;
        }

        $mail = match (true) {
            str_starts_with($email, 'order_') => new OrderStatusMail(EmailPreviewSamples::order($email), $email),
            $email === 'owner_new_order' => new OwnerNewOrderMail(EmailPreviewSamples::order('order_placed')),
            $email === 'contact' => new ContactInquiryMail(EmailPreviewSamples::contact()),
            default => new MailSettingsTestMail($shop, null, null, ['via' => app(MailSettings::class)->usesAdminSettings() ? 'own_smtp' : 'relay', 'to' => $to]),
        };
        $subject = (string) $mail->envelope()->subject;
        $mail->withSymfonyMessage(fn ($message) => $message->subject('[THỬ] '.$subject));
        Mail::to($to)->sendNow($mail);

        return $subject;
    }
}
