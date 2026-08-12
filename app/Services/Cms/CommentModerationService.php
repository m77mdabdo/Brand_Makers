<?php

namespace App\Services\Cms;

use App\Models\Comment;

/**
 * Comment moderation.
 *
 * Comments arrive PENDING (the column default, mirrored on the model) and
 * are only ever moved by an authenticated admin through one of these
 * three methods — there is no code path that publishes one automatically.
 */
class CommentModerationService
{
    public function approve(Comment $comment): Comment
    {
        return $this->transition($comment, Comment::STATUS_APPROVED);
    }

    public function reject(Comment $comment): Comment
    {
        return $this->transition($comment, Comment::STATUS_REJECTED);
    }

    public function delete(Comment $comment): void
    {
        $comment->delete();
    }

    /**
     * @param  array<int, int>  $ids
     */
    public function approveMany(array $ids): int
    {
        return Comment::whereIn('id', $ids)->update(['status' => Comment::STATUS_APPROVED]);
    }

    private function transition(Comment $comment, string $status): Comment
    {
        $comment->update(['status' => $status]);

        return $comment->refresh();
    }
}
