<?php

namespace App\Http\Controllers;

use App\Exports\MigraineCsv;
use App\Http\Requests\StoreMigraineExportRequest;
use App\Models\MigraineExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class MigraineExportController extends Controller
{
    /**
     * List the user's past exports alongside the form to create a new one.
     */
    public function index(Request $request): Response
    {
        $exports = $request->user()
            ->migraineExports()
            ->latest()
            ->latest('id')
            ->get()
            ->map(fn (MigraineExport $export): array => [
                'id' => $export->id,
                'from_date' => $export->from_date->toDateString(),
                'to_date' => $export->to_date->toDateString(),
                'include_ad_hoc' => $export->include_ad_hoc,
                'include_regular' => $export->include_regular,
                'file_name' => $export->fileName(),
                'created_at' => $export->created_at?->toIso8601String(),
            ]);

        return Inertia::render('MedicationExports', [
            'exports' => $exports,
            'defaultFromDate' => now()->startOfYear()->toDateString(),
            'defaultToDate' => now()->toDateString(),
        ]);
    }

    /**
     * Record a new export and hand the browser its download link.
     */
    public function store(StoreMigraineExportRequest $request): RedirectResponse
    {
        $export = $request->user()->migraineExports()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Export created.')]);
        Inertia::flash('download', route('medication-exports.download', $export));

        return to_route('medication-exports');
    }

    /**
     * Download an export as a CSV file.
     */
    public function download(Request $request, MigraineExport $migraineExport): HttpResponse
    {
        abort_unless($request->user()->is($migraineExport->user), 403);

        return response()->streamDownload(
            function () use ($migraineExport): void {
                echo (new MigraineCsv($migraineExport))->toString();
            },
            $migraineExport->fileName(),
            ['Content-Type' => 'text/csv'],
        );
    }
}
