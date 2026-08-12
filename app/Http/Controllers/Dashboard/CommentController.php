<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Services\Cms\CommentModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Comment moderation.
 *
 * There is no create or edit: comments come from the public site, and an
 * admin's only powers over one are approve, reject and delete. Editing
 * someone else's words and then publishing them under their name is not a
 * feature worth having.
 */
class CommentController extends Controller
{
    public function __construct(private readonly CommentModerationService $moderation) {}

    public function index(Request $request): View
    {
        // Whitelisted, not taken from the request directly: an arbitrary
        // status would silently return an empty queue and look like "no
        // comments" rather than a bad filter.
        $status = in_array($request->query('status'), Comment::STATUSES, true)
            ? $request->query('status')
            : null;

        return view('dashboard.comments.index', [
            'comments' => Comment::query()
                ->when($status, fn ($query) => $query->where('status', $status))
                // withTrashed(): Article soft-deletes, and a comment on an
                // archived article must still show which article it is on
                // rather than rendering a blank row nobody can act on.
                ->with('article')
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'status' => $status,
            'counts' => [
                'all' => Comment::count(),
                Comment::STATUS_PENDING => Comment::pending()->count(),
                Comment::STATUS_APPROVED => Comment::approved()->count(),
                Comment::STATUS_REJECTED => Comment::where('status', Comment::STATUS_REJECTED)->count(),
            ],
        ]);
    }

    public function approve(Comment $comment): RedirectResponse
    {
        $this->moderation->approve($comment);

        return back()->with('status', __('dashboard.flash.approved'));
    }

    public function reject(Comment $comment): RedirectResponse
    {
        $this->moderation->reject($comment);

        return back()->with('status', __('dashboard.flash.rejected'));
    }

    public function destroy(Comment $comment): RedirectResponse
    {
        $this->moderation->delete($comment);

        return back()->with('status', __('dashboard.flash.deleted'));
    }
}
