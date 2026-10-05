<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AiGateway\GatewayUnavailable;
use App\Support\CoreManifest;
use App\Support\CoreUpdate\CoreUpdater;
use App\Support\CoreUpdate\CoreUpdateService;
use App\Support\CoreUpdate\UpdateProblems;
use Illuminate\Http\Request;

/**
 * Superadmin page: the core version, what the update service offers, and the buttons. A click
 * only queues the work (requested.json); cron runs it within a minute — an update never runs
 * inside a web request.
 */
class CoreUpdateController extends Controller
{
    public function __construct(
        private readonly CoreUpdateService $service,
        private readonly CoreManifest $manifest,
        private readonly CoreUpdater $updater,
    ) {}

    public function index()
    {
        $historyFile = $this->manifest->base().'/storage/app/core/history.jsonl';
        $history = is_file($historyFile)
            ? array_reverse(array_map(fn ($l) => (array) json_decode($l, true), array_slice(file($historyFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), -10)))
            : [];

        $progress = $this->service->progress();

        return view('admin.core-update.index', [
            'progress' => $progress,
            'problems' => UpdateProblems::explain($progress['last']['error'] ?? null),
            'version' => $this->manifest->version(),
            'enabled' => $this->service->enabled(),
            'drift' => $this->manifest->drift(),
            'available' => $this->service->available(),
            'requested' => $this->service->requested(),
            'state' => $this->updater->state(),
            'history' => $history,
            'canRollback' => glob($this->manifest->base().'/storage/app/core/backups/*/meta.json') !== [],
        ]);
    }

    /** Polled by the update page while a request is queued or running. */
    public function status()
    {
        $progress = $this->service->progress();

        return response()->json($progress + ['problems' => UpdateProblems::explain($progress['last']['error'] ?? null)])
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Record the core files as installed when the website has no record at all (a site set up
     * before deploy.py recorded it) — never over an existing record, which would hide edits.
     */
    public function baseline()
    {
        if ($this->manifest->installed() !== null) {
            return back()->with('error', __('admin.core_update.baseline_exists'));
        }
        $this->manifest->recordInstalled();

        return back()->with('success', __('admin.core_update.baseline_done'));
    }

    public function check()
    {
        try {
            $offer = $this->service->offer();
        } catch (GatewayUnavailable $e) {
            return back()->with('error', __('admin.core_update.gateway_error', ['reason' => $e->reason]));
        }

        return back()->with('success', $offer === null
            ? __('admin.core_update.up_to_date')
            : __('admin.core_update.found', ['version' => $offer['version']]));
    }

    public function update(Request $request)
    {
        $data = $request->validate(['version' => ['required', 'string', 'max:50'], 'backup_done' => ['sometimes', 'boolean']]);

        $offered = $this->service->available()['update'] ?? null;
        if (! is_array($offered) || $offered['version'] !== $data['version']) {
            return back()->with('error', __('admin.core_update.not_offered'));
        }
        if (($offered['database']['backup_required'] ?? false) && ! $request->boolean('backup_done')) {
            return back()->with('error', __('admin.core_update.backup_first'));
        }

        $this->service->request(['action' => 'update', 'version' => $data['version'], 'backup_done' => $request->boolean('backup_done'), 'by' => (string) $request->user()?->email]);

        return back()->with('success', __('admin.core_update.queued'));
    }

    public function rollback(Request $request)
    {
        $this->service->request(['action' => 'rollback', 'by' => (string) $request->user()?->email]);

        return back()->with('success', __('admin.core_update.rollback_queued'));
    }
}
