<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesBulkActions;
use App\Http\Controllers\Admin\Concerns\HandlesPagination;
use App\Http\Controllers\Controller;
use App\Models\NotFoundLog;
use App\Models\Redirect;
use App\Services\ActivityLogger;
use App\Services\Seo\RedirectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * admin/redirects: old URLs and where they now go (RedirectService serves them), and the
 * storefront URLs that answered 404, each one click away from a redirect.
 */
class RedirectController extends Controller
{
    use HandlesBulkActions;
    use HandlesPagination;

    private const IMPORT_MAX_ROWS = 5000;

    public function __construct(private readonly RedirectService $redirects) {}

    public function index(Request $request)
    {
        $tab = $request->query('tab') === 'not-found' ? 'not-found' : 'redirects';
        $search = trim((string) $request->query('q'));

        $redirectList = Redirect::query()->with('redirectable')
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('source_path', 'like', '%'.mb_strtolower($search).'%')
                ->orWhere('target_url', 'like', '%'.$search.'%')))
            ->when(in_array($request->query('origin'), [Redirect::ORIGIN_MANUAL, Redirect::ORIGIN_WORDPRESS, Redirect::ORIGIN_NOT_FOUND], true),
                fn ($query) => $query->where('origin', $request->query('origin')))
            ->latest('id')
            ->paginate($this->perPage(25))->withQueryString();

        $notFoundList = NotFoundLog::query()
            ->when($search !== '', fn ($query) => $query->where('path', 'like', '%'.mb_strtolower($search).'%'))
            ->orderByDesc('hits')->orderByDesc('last_seen_at')
            ->paginate($this->perPage(25))->withQueryString();

        return view('admin.redirects.index', [
            'tab' => $tab,
            'redirectList' => $redirectList,
            'notFoundList' => $notFoundList,
            'redirectCount' => Redirect::query()->count(),
            'notFoundCount' => NotFoundLog::query()->count(),
            'targets' => $redirectList->getCollection()->mapWithKeys(fn (Redirect $redirect) => [$redirect->id => $this->redirects->targetFor($redirect)]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $redirect = Redirect::query()->create($data + ['origin' => Redirect::ORIGIN_MANUAL]);

        // Made from the 404 log: that URL is handled now.
        if ($request->filled('not_found_id')) {
            NotFoundLog::query()->whereKey((int) $request->input('not_found_id'))->delete();
            $redirect->update(['origin' => Redirect::ORIGIN_NOT_FOUND]);
        }
        NotFoundLog::query()->where('path', $redirect->source_path)->delete();

        ActivityLogger::log('created', $redirect, __('redirects.log.created', ['path' => $redirect->source_path]), ['target' => $redirect->target_url, 'status' => $redirect->status_code]);

        return redirect()->route('admin.redirects.index')->with('success', __('redirects.flash.created'));
    }

    public function update(Request $request, string $locale, Redirect $redirect): RedirectResponse
    {
        $data = $this->validated($request, $redirect);
        // A target typed by hand replaces the record the import pointed at.
        if ($data['target_url'] !== $this->redirects->targetFor($redirect)) {
            $data += ['redirectable_type' => null, 'redirectable_id' => null];
        }
        $redirect->update($data + ['origin' => Redirect::ORIGIN_MANUAL]);

        ActivityLogger::log('updated', $redirect, __('redirects.log.updated', ['path' => $redirect->source_path]), ['target' => $redirect->target_url, 'status' => $redirect->status_code]);

        return redirect()->route('admin.redirects.index')->with('success', __('redirects.flash.updated'));
    }

    public function destroy(string $locale, Redirect $redirect): RedirectResponse
    {
        $redirect->delete();
        ActivityLogger::log('deleted', $redirect, __('redirects.log.deleted', ['path' => $redirect->source_path]));

        return back()->with('success', __('redirects.flash.deleted'));
    }

    public function bulk(Request $request): RedirectResponse
    {
        $validated = $this->validatedBulkAction($request, 'redirects', 'redirects');
        $ids = $validated['ids'];

        if ($validated['action'] === 'delete') {
            $deleted = Redirect::query()->whereKey($ids)->delete();
            ActivityLogger::log('bulk_deleted', null, __('redirects.log.bulk_deleted'), ['model' => Redirect::class, 'ids' => $ids, 'count' => $deleted]);

            return back()->with('success', __('redirects.flash.bulk_deleted', ['count' => $deleted]));
        }

        $active = $validated['action'] === 'activate';
        $updated = Redirect::query()->whereKey($ids)->update(['is_active' => $active]);
        ActivityLogger::log('bulk_status_changed', null, __('redirects.log.bulk_status'), ['model' => Redirect::class, 'ids' => $ids, 'count' => $updated, 'is_active' => $active]);

        return back()->with('success', __($active ? 'redirects.flash.bulk_activated' : 'redirects.flash.bulk_deactivated', ['count' => $updated]));
    }

    public function destroyNotFound(string $locale, NotFoundLog $notFoundLog): RedirectResponse
    {
        $notFoundLog->delete();

        return back()->with('success', __('redirects.flash.not_found_deleted'));
    }

    public function clearNotFound(): RedirectResponse
    {
        $deleted = NotFoundLog::query()->delete();
        ActivityLogger::log('bulk_deleted', null, __('redirects.log.not_found_cleared'), ['count' => $deleted]);

        return redirect()->route('admin.redirects.index', ['tab' => 'not-found'])->with('success', __('redirects.flash.not_found_cleared'));
    }

    /**
     * CSV rows "old URL, new URL[, status]", e.g. exported from another site or the
     * Redirection plugin. A header row is skipped; rows an admin already made win.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $counts = ['saved' => 0, 'skipped' => 0];
        $line = 0;
        while ($handle && ($row = fgetcsv($handle, 4096, ',', '"', '\\')) !== false && $line < self::IMPORT_MAX_ROWS) {
            $line++;
            $source = trim(ltrim((string) ($row[0] ?? ''), "\xEF\xBB\xBF"));
            $target = trim((string) ($row[1] ?? ''));
            $status = (int) ($row[2] ?? 301);
            if ($line === 1 && ! str_starts_with($source, '/') && ! preg_match('#^https?://#i', $source)) {
                continue; // header
            }

            $normalized = RedirectService::normalize($source);
            $status = in_array($status, Redirect::STATUS_CODES, true) ? $status : 301;
            if ($normalized === '/' || mb_strlen($normalized) > 500 || ($status !== 410 && ! $this->validTarget($target))
                || Redirect::query()->where('source_path', $normalized)->where('origin', Redirect::ORIGIN_MANUAL)->exists()) {
                $counts['skipped']++;

                continue;
            }

            Redirect::query()->updateOrCreate(['source_path' => $normalized], [
                'target_url' => $status === 410 ? null : $target,
                'redirectable_type' => null,
                'redirectable_id' => null,
                'status_code' => $status,
                'origin' => Redirect::ORIGIN_MANUAL,
                'is_active' => true,
            ]);
            $counts['saved']++;
        }
        if ($handle) {
            fclose($handle);
        }

        ActivityLogger::log('imported', null, __('redirects.log.imported'), $counts);

        return redirect()->route('admin.redirects.index')->with('success', __('redirects.flash.imported', $counts));
    }

    public function export(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['source', 'target', 'status', 'hits'], ',', '"', '\\');
            Redirect::query()->with('redirectable')->orderBy('id')->lazy(500)->each(function (Redirect $redirect) use ($out): void {
                fputcsv($out, [$redirect->source_path, (string) $this->redirects->targetFor($redirect), $redirect->status_code, $redirect->hits], ',', '"', '\\');
            });
            fclose($out);
        }, 'redirects-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{source_path: string, target_url: ?string, status_code: int, is_active: bool} */
    private function validated(Request $request, ?Redirect $redirect = null): array
    {
        $request->validate([
            'source' => ['required', 'string', 'max:500'],
            'target' => ['nullable', 'required_unless:status_code,410', 'string', 'max:2048'],
            'status_code' => ['required', 'integer', Rule::in(Redirect::STATUS_CODES)],
        ]);

        $source = RedirectService::normalize((string) $request->input('source'));
        $status = (int) $request->input('status_code');
        $target = $status === 410 ? null : trim((string) $request->input('target'));

        $errors = [];
        if ($source === '/') {
            $errors['source'] = __('redirects.validation.source_root');
        } elseif (Redirect::query()->where('source_path', $source)->when($redirect, fn ($query) => $query->whereKeyNot($redirect->id))->exists()) {
            $errors['source'] = __('redirects.validation.source_taken');
        }
        if ($target !== null && ! $this->validTarget($target)) {
            $errors['target'] = __('redirects.validation.target_format');
        } elseif ($target !== null && str_starts_with($target, '/') && strtok(RedirectService::normalize($target), '?') === strtok($source, '?')) {
            $errors['target'] = __('redirects.validation.target_loop');
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'source_path' => $source,
            'target_url' => $target,
            'status_code' => $status,
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    /** A path on this site ("/bai-viet/x") or a full http(s) address. */
    private function validTarget(string $target): bool
    {
        if (str_starts_with($target, '/')) {
            return ! str_starts_with($target, '//') && ! preg_match('/\s/', $target);
        }

        return filter_var($target, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($target, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
