<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Product;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Display a listing of reviews.
     */
    public function index(Request $request)
    {
        $query = Review::query()
            ->with(['product', 'user']);

        // Filter by product
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        // Filter by rating
        if ($request->filled('rating')) {
            $query->where('rating', $request->input('rating'));
        }

        // Filter by visibility
        if ($request->filled('is_visible')) {
            $query->where('is_visible', $request->input('is_visible'));
        }

        // Search reviewer name, email, or comment
        if ($request->filled('q')) {
            $keyword = $request->input('q');
            $query->where(function ($q) use ($keyword) {
                $q->where('customer_name', 'like', "%{$keyword}%")
                  ->orWhere('customer_email', 'like', "%{$keyword}%")
                  ->orWhere('comment', 'like', "%{$keyword}%");
            });
        }

        $reviews = $query->latest()
            ->paginate(15)
            ->withQueryString();

        // Get products with reviews to populate the filter dropdown
        $products = Product::query()
            ->whereHas('reviews')
            ->get();

        return view('admin.reviews.index', compact('reviews', 'products'));
    }

    /**
     * Update the specified review.
     */
    public function update(Request $request, string $locale, Review $review)
    {
        $validated = $request->validate([
            'comment' => 'nullable|string',
            'is_visible' => 'nullable|boolean',
        ]);

        $review->update([
            'comment' => $validated['comment'] ?? $review->comment,
            'is_visible' => $request->has('is_visible') ? (bool) $request->input('is_visible') : $review->is_visible,
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => __('admin.reviews.updated'),
                'review' => $review
            ]);
        }

        return redirect()
            ->route('admin.reviews.index')
            ->with('success', __('admin.reviews.updated'));
    }

    /**
     * Toggle visibility status of the specified review via AJAX.
     */
    public function toggleVisibility(Request $request, string $locale, Review $review)
    {
        $review->update([
            'is_visible' => !$review->is_visible,
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => __('admin.reviews.updated'),
                'is_visible' => $review->is_visible
            ]);
        }

        return redirect()->route('admin.reviews.index');
    }

    /**
     * Remove the specified review from storage.
     */
    public function destroy(Request $request, string $locale, Review $review)
    {
        $review->delete();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => __('admin.reviews.deleted')
            ]);
        }

        return redirect()
            ->route('admin.reviews.index')
            ->with('success', __('admin.reviews.deleted'));
    }
}
