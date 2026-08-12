<?php

namespace Tests\Feature\Dashboard;

use App\Models\Article;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleAndCommentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::factory()->admin()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'title' => ['ar' => 'مقال جديد', 'en' => 'A new article'],
            'excerpt' => ['ar' => 'مقتطف', 'en' => 'Excerpt'],
            'body' => ['ar' => '<p>محتوى</p>', 'en' => '<p>Body</p>'],
        ], $overrides);
    }

    // -----------------------------------------------------------------
    // The three publication states
    // -----------------------------------------------------------------

    public function test_an_unpublished_article_is_a_draft_with_no_date(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.articles.store'), $this->payload([
            // is_published omitted — the unticked checkbox.
            'published_at' => now()->subDay()->format('Y-m-d\TH:i'),
        ]));

        $article = Article::latest('id')->first();

        $this->assertFalse($article->is_published);
        // The date is CLEARED, so a draft never carries a stale value that
        // would make it go live the moment someone ticks the box.
        $this->assertNull($article->published_at);
    }

    public function test_publishing_with_no_date_means_now(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.articles.store'), $this->payload([
            'is_published' => '1',
        ]));

        $article = Article::latest('id')->first();

        $this->assertTrue($article->is_published);
        $this->assertNotNull($article->published_at);
        $this->assertTrue($article->published_at->isToday());
        $this->assertSame(1, Article::live()->whereKey($article->id)->count());
    }

    public function test_a_future_date_schedules_the_article_without_a_cron_job(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.articles.store'), $this->payload([
            'is_published' => '1',
            'published_at' => now()->addWeek()->format('Y-m-d\TH:i'),
        ]));

        $article = Article::latest('id')->first();

        $this->assertTrue($article->is_published);
        $this->assertTrue($article->published_at->isFuture());

        // Invisible until the date passes, because the public scope
        // filters on published_at <= now().
        $this->assertSame(0, Article::live()->whereKey($article->id)->count());
        $this->get('/')->assertDontSee('مقال جديد', false);
    }

    public function test_a_published_article_appears_on_the_public_site(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.articles.store'), $this->payload([
            'title' => ['ar' => 'مقال منشور', 'en' => 'A published article'],
            'is_published' => '1',
        ]));

        $this->get('/')->assertSee('مقال منشور', false);
        $this->get('/en')->assertSee('A published article', false);
    }

    public function test_unpublishing_removes_it_from_the_public_site(): void
    {
        $article = Article::factory()->create(['title' => ['ar' => 'مقال حي', 'en' => 'Live']]);
        $this->get('/')->assertSee('مقال حي', false);

        $this->actingAs($this->admin)->put(
            route('dashboard.articles.update', $article),
            $this->payload(['title' => ['ar' => 'مقال حي']])
        );

        $this->get('/')->assertDontSee('مقال حي', false);
    }

    public function test_the_body_is_purified_but_the_title_is_stripped_entirely(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.articles.store'), $this->payload([
            'title' => ['ar' => '<em>عنوان</em>'],
            'body' => ['ar' => '<h2>عنوان</h2><script>alert(1)</script><p onmouseover="x()">نص</p>'],
        ]));

        $article = Article::latest('id')->first();

        $this->assertSame('عنوان', $article->getTranslation('title', 'ar'));

        $body = $article->getTranslation('body', 'ar');
        $this->assertStringContainsString('<h2>', $body);
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('alert(1)', $body);
        $this->assertStringNotContainsString('onmouseover', $body);
    }

    public function test_article_crud_and_gating(): void
    {
        $this->get(route('dashboard.articles.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('dashboard.articles.index'))->assertForbidden();

        $this->actingAs($this->admin)->get(route('dashboard.articles.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('dashboard.articles.create'))->assertOk();

        $this->actingAs($this->admin)->post(route('dashboard.articles.store'), $this->payload())->assertRedirect();
        $article = Article::latest('id')->first();

        $this->actingAs($this->admin)->get(route('dashboard.articles.edit', $article))->assertOk();
        $this->actingAs($this->admin)->delete(route('dashboard.articles.destroy', $article))->assertRedirect();

        // Articles soft-delete, so the row survives for restoration.
        $this->assertSoftDeleted($article);
    }

    // -----------------------------------------------------------------
    // Comment moderation
    // -----------------------------------------------------------------

    public function test_comments_arrive_pending_and_stay_off_the_site(): void
    {
        $comment = Comment::factory()->create();

        $this->assertSame(Comment::STATUS_PENDING, $comment->status);
        $this->assertSame(0, Comment::approved()->count());
    }

    public function test_an_admin_can_approve_reject_and_delete(): void
    {
        $comment = Comment::factory()->create();

        $this->actingAs($this->admin)->patch(route('dashboard.comments.approve', $comment))->assertRedirect();
        $this->assertSame(Comment::STATUS_APPROVED, $comment->fresh()->status);

        $this->actingAs($this->admin)->patch(route('dashboard.comments.reject', $comment))->assertRedirect();
        $this->assertSame(Comment::STATUS_REJECTED, $comment->fresh()->status);

        $this->actingAs($this->admin)->delete(route('dashboard.comments.destroy', $comment))->assertRedirect();
        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    public function test_moderation_is_admin_only(): void
    {
        $comment = Comment::factory()->create();
        $user = User::factory()->create();

        $this->patch(route('dashboard.comments.approve', $comment))->assertRedirect(route('login'));
        $this->actingAs($user)->patch(route('dashboard.comments.approve', $comment))->assertForbidden();

        $this->assertSame(Comment::STATUS_PENDING, $comment->fresh()->status);
    }

    public function test_the_queue_filters_by_status(): void
    {
        Comment::factory()->count(2)->create();
        Comment::factory()->approved()->create();

        $pending = $this->actingAs($this->admin)->get(route('dashboard.comments.index', ['status' => 'pending']));
        $pending->assertOk();
        $this->assertCount(2, $pending->viewData('comments'));

        $approved = $this->actingAs($this->admin)->get(route('dashboard.comments.index', ['status' => 'approved']));
        $this->assertCount(1, $approved->viewData('comments'));

        $all = $this->actingAs($this->admin)->get(route('dashboard.comments.index'));
        $this->assertCount(3, $all->viewData('comments'));
    }

    public function test_an_unknown_status_filter_falls_back_to_all_rather_than_an_empty_queue(): void
    {
        Comment::factory()->count(2)->create();

        // A bad filter that returned nothing would look like "no
        // comments" and hide the real queue.
        $response = $this->actingAs($this->admin)->get(route('dashboard.comments.index', ['status' => 'nonsense']));

        $response->assertOk();
        $this->assertCount(2, $response->viewData('comments'));
    }

    public function test_a_comment_on_a_soft_deleted_article_still_names_its_article(): void
    {
        $article = Article::factory()->create();
        $comment = Comment::factory()->for($article)->create();

        $article->delete();

        // withTrashed() on the belongsTo — otherwise the moderation queue
        // shows a blank row nobody can act on.
        $response = $this->actingAs($this->admin)->get(route('dashboard.comments.index'));

        $response->assertOk();
        $this->assertNotNull($comment->fresh()->article);
    }
}
