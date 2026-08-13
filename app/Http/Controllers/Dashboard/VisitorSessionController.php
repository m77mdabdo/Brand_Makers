<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\LeadSource;
use App\Models\VisitorSession;
use App\Services\Analytics\UserAgentParser;
use App\Services\Analytics\VisitorSessionReportService;
use App\Services\Analytics\VisitorTrackingGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VisitorSessionController extends Controller
{
    /**
     * A filtered export has to be bounded. "Export everything" against a
     * table with a 90-day window is a request that can hold a worker for a
     * long time and hand back a file nothing will open. The index says so
     * in as many words, so a truncated export is never mistaken for a
     * complete one.
     */
    private const EXPORT_LIMIT = 1000;

    public function __construct(
        private readonly VisitorSessionReportService $report,
        private readonly VisitorTrackingGate $gate,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->report->filtersFrom($request->all());

        return view('dashboard.visitor-sessions.index', [
            'sessions' => $this->report->query($filters)->paginate(25)->withQueryString(),
            'filters' => $filters,
            // The canonical source list rather than a DISTINCT over a JSON
            // column: lead_sources already IS the set of channels this
            // business recognises, and it is a handful of rows.
            'sources' => LeadSource::ordered()->get(),
            'devices' => [
                UserAgentParser::DEVICE_DESKTOP,
                UserAgentParser::DEVICE_MOBILE,
                UserAgentParser::DEVICE_TABLET,
            ],
            // Surfaced so an admin looking at an empty table is told why
            // it is empty, rather than assuming the feature is broken.
            'trackingEnabled' => $this->gate->enabled(),
            'retentionDays' => $this->gate->retentionDays(),
            'exportLimit' => self::EXPORT_LIMIT,
        ]);
    }

    public function show(VisitorSession $visitorSession): View
    {
        $visitorSession->load('lead');

        return view('dashboard.visitor-sessions.show', [
            'session' => $visitorSession,
            'journey' => $visitorSession->journey(),
        ]);
    }

    /**
     * The filtered set, as a download.
     *
     * Built from the SAME query the index uses, so the file always matches
     * the table it was exported from.
     */
    public function export(Request $request): JsonResponse
    {
        $filters = $this->report->filtersFrom($request->all());

        $sessions = $this->report->query($filters)->limit(self::EXPORT_LIMIT)->get();

        return $this->download(
            $this->report->export($sessions, $filters),
            $this->report->exportFilename()
        );
    }

    public function exportOne(VisitorSession $visitorSession): JsonResponse
    {
        $visitorSession->load('lead');

        return $this->download(
            $this->report->export(collect([$visitorSession])),
            $this->report->exportFilename($visitorSession)
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function download(array $payload, string $filename): JsonResponse
    {
        return response()->json($payload, 200, [
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ],
            // Pretty-printed, with unescaped slashes and unicode: this is
            // a file a person opens and reads, not a wire format. Escaped
            // Arabic (أ…) would make an export of an Arabic-language
            // site unreadable in a text editor.
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }
}
