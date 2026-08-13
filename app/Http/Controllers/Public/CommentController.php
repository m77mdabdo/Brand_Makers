<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreCommentRequest;
use App\Models\Article;
use App\Models\Comment;
use App\Services\Analytics\VisitorSessionService;
use App\Support\Honeypot;
use Illuminate\Http\RedirectResponse;

class CommentController extends Controller
{
    public function __construct(private readonly VisitorSessionService $visitorSessions) {}

    public function store(StoreCommentRequest $request, Article $article): RedirectResponse
    {
        /*
         * The SAME publication check the article's own show() action
         * makes. The form is never rendered for a draft, but a direct
         * POST does not go through the page that renders it — so without
         * this, a draft or scheduled article could collect comments.
         */
        abort_unless(
            $article->is_published && $article->published_at?->lessThanOrEqualTo(now()),
            404
        );

        // Same silent-success pattern as the contact form.
        if (Honeypot::tripped($request)) {
            return $this->submitted($article);
        }

        Comment::create([
            ...$request->safe()->only(['name', 'email', 'body']),
            'article_id' => $article->getKey(),
            /*
             * FORCED pending, never read from the request. Three things
             * would each have to fail for a public submission to arrive
             * approved: this line, the absence of `status` from the Form
             * Request rules, and the moderation queue itself.
             *
             * `body` is stored as submitted and ESCAPED on render — a
             * comment is never rendered as HTML at any privilege level.
             */
            'status' => Comment::STATUS_PENDING,
            // Truncated by Comment's AnonymizesIp mutator before it is
            // stored; the raw address never reaches the database.
            'ip_address' => $request->ip(),
        ]);

        /*
         * An action on the timeline, with NO lead argument.
         *
         * A comment is engagement, not a conversion — see submitted()
         * below. Passing a lead here would be how a comment silently
         * started counting as one in every journey report.
         */
        $this->visitorSessions->recordConversion($request, 'comment_submitted');

        return $this->submitted($article);
    }

    /**
     * No Lead is created. A comment is engagement, not a conversion —
     * recording one as a lead would inflate every conversion figure the
     * reports compute.
     */
    private function submitted(Article $article): RedirectResponse
    {
        return redirect()
            ->to(lroute('articles.show', $article).'#comments')
            ->with('status', __('site.flash.comment_submitted'));
    }
}
