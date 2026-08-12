<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function index(): View
    {
        return view('public.articles.index', [
            // Article::live() is is_published AND published_at <= now, so
            // a scheduled post stays invisible until its date passes with
            // no cron job involved.
            'articles' => Article::live()->latest('published_at')->paginate(9),
        ]);
    }

    public function show(Article $article): View
    {
        // The same two conditions as the index scope, applied to a record
        // the binding already resolved by slug — otherwise a draft or a
        // scheduled post would be readable by anyone who knows the URL.
        abort_unless(
            $article->is_published && $article->published_at?->lessThanOrEqualTo(now()),
            404
        );

        return view('public.articles.show', [
            'article' => $article->load('seoMeta'),
            /*
             * Approved only, oldest first.
             *
             * approved() rather than "not rejected": a comment awaiting
             * moderation has not been published by anyone, and showing it
             * would make the moderation queue decorative.
             *
             * Oldest-first because this is a flat, unthreaded discussion
             * on an editorial post — it reads as a conversation from the
             * first reply onwards, not as a social feed.
             */
            'comments' => $article->comments()->approved()->oldest()->get(),
            'related' => Article::live()
                ->whereKeyNot($article->getKey())
                ->latest('published_at')
                ->limit(3)
                ->get(),
        ]);
    }
}
