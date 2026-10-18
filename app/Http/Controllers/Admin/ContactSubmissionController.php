<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesPagination;
use App\Http\Controllers\Controller;
use App\Models\ContactSubmission;
use Illuminate\Http\Request;

class ContactSubmissionController extends Controller
{
    use HandlesPagination;

    public function index(Request $request)
    {
        // Spam stays out of the inbox; the "spam" box shows it for releasing false positives.
        $spamBox = $request->input('box') === 'spam';
        $query = ContactSubmission::query()->where('is_spam', $spamBox);

        if ($request->filled('is_read')) {
            $query->where('is_read', $request->boolean('is_read'));
        }

        if ($request->filled('q')) {
            $keyword = $request->input('q');
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhere('message', 'like', "%{$keyword}%");
            });
        }

        $submissions = $query->latest()
            ->paginate($this->perPage(15))
            ->withQueryString();

        $spamCount = ContactSubmission::query()->where('is_spam', true)->count();

        return view('admin.contact-submissions.index', compact('submissions', 'spamBox', 'spamCount'));
    }

    public function toggleRead(Request $request, string $locale, ContactSubmission $contactSubmission)
    {
        $contactSubmission->update(['is_read' => ! $contactSubmission->is_read]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'is_read' => $contactSubmission->is_read,
            ]);
        }

        return redirect()->route('admin.contact-submissions.index');
    }

    /** A real inquiry the filter caught: back to the inbox. The mail it never sent is not sent now. */
    public function notSpam(string $locale, ContactSubmission $contactSubmission)
    {
        $contactSubmission->update(['is_spam' => false, 'spam_reason' => null]);

        return redirect()
            ->route('admin.contact-submissions.index', ['box' => 'spam'])
            ->with('success', 'Đã chuyển liên hệ về hộp thư.');
    }

    public function destroy(Request $request, string $locale, ContactSubmission $contactSubmission)
    {
        $contactSubmission->delete();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => __('controller.admin.contact_deleted')]);
        }

        return redirect()
            ->route('admin.contact-submissions.index')
            ->with('success', __('controller.admin.contact_deleted'));
    }
}
