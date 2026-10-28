<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesBulkActions;
use App\Http\Controllers\Admin\Concerns\HandlesPagination;
use App\Http\Controllers\Controller;
use App\Models\ContactSubmission;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class ContactSubmissionController extends Controller
{
    use HandlesBulkActions;
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
            ->with('success', __('admin.contacts.moved_to_inbox'));
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

    /** Delete the messages ticked in the list; the only bulk action a message has. */
    public function bulk(Request $request, string $locale)
    {
        $validated = $this->validatedBulkAction($request, 'contact_submissions', 'contacts');
        abort_unless($validated['action'] === 'delete', 422);

        $deleted = ContactSubmission::query()->whereIn('id', $validated['ids'])->delete();
        ActivityLogger::log('bulk_deleted', null, __('admin.contacts.activity_bulk_deleted'), [
            'model' => ContactSubmission::class,
            'ids' => $validated['ids'],
            'count' => $deleted,
        ]);

        return back()->with('success', __('admin.contacts.bulk_deleted', ['count' => $deleted]));
    }
}
