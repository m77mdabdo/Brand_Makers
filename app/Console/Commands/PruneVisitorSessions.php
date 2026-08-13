<?php

namespace App\Console\Commands;

use App\Models\VisitorSession;
use App\Services\Analytics\VisitorTrackingGate;
use Illuminate\Console\Command;

/**
 * Enforces the retention window on visitor_sessions.
 *
 * This is the half of the privacy promise that is easy to forget: consent
 * and anonymisation control what gets collected, and this controls how long
 * it survives. A retention policy nobody runs is not a retention policy, so
 * it is scheduled daily in routes/console.php rather than left as a manual
 * command.
 *
 * Deletes on started_at, not created_at. The two are written together
 * today, but started_at is the one that means "when this visitor was here"
 * — which is what a retention window is actually about — and it is the
 * indexed column.
 */
class PruneVisitorSessions extends Command
{
    protected $signature = 'visitor-sessions:prune
                            {--days= : Override the retention window from Settings}
                            {--dry-run : Report what would be deleted without deleting it}';

    protected $description = 'Delete visitor sessions older than the configured retention window';

    /**
     * Deleted in batches rather than in one statement.
     *
     * A single unbounded DELETE against a table holding months of sessions
     * holds row locks for its whole duration and produces one enormous
     * binlog entry. Batching keeps each transaction short, which matters
     * because this runs against the same database serving the site.
     */
    private const BATCH_SIZE = 1000;

    public function handle(VisitorTrackingGate $gate): int
    {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : $gate->retentionDays();

        // The floor exists because this number reaches a DELETE. A
        // retention of 0 or a negative would mean "delete everything,
        // including the session that is open right now".
        if ($days < 1) {
            $this->error('Retention must be at least 1 day; got '.$days.'.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);

        $query = VisitorSession::where('started_at', '<', $cutoff);
        $total = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Would delete {$total} session(s) started before {$cutoff->toDateTimeString()} (retention: {$days} days).");

            return self::SUCCESS;
        }

        $deleted = 0;

        while (($batch = (clone $query)->limit(self::BATCH_SIZE)->pluck('id'))->isNotEmpty()) {
            $deleted += VisitorSession::whereIn('id', $batch)->delete();
        }

        $this->info("Deleted {$deleted} visitor session(s) started before {$cutoff->toDateTimeString()} (retention: {$days} days).");

        return self::SUCCESS;
    }
}
