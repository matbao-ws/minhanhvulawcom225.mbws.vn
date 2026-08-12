<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactSubmission;
use Illuminate\Http\Request;

class ContactSubmissionController extends Controller
{
    public function index(Request $request)
    {
        $query = ContactSubmission::query();

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
            ->paginate(15)
            ->withQueryString();

        return view('admin.contact-submissions.index', compact('submissions'));
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

    public function destroy(Request $request, string $locale, ContactSubmission $contactSubmission)
    {
        $contactSubmission->delete();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Đã xóa liên hệ.']);
        }

        return redirect()
            ->route('admin.contact-submissions.index')
            ->with('success', 'Đã xóa liên hệ.');
    }
}
