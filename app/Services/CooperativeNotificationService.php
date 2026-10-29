<?php

namespace App\Services;

use App\Models\Borrower;
use App\Models\User;
use App\Models\UserNotification;

class CooperativeNotificationService
{
    public function notifyUser(?User $user, string $title, string $body, string $url): void
    {
        if (! $user) {
            return;
        }

        UserNotification::create([
            'user_id' => $user->id,
            'title' => $title,
            'body' => $body,
            'url' => $url,
        ]);
    }

    public function notifyRoles(array $roles, string $title, string $body, string $url, ?int $exceptUserId = null): void
    {
        User::query()
            ->whereIn('role', $roles)
            ->when($exceptUserId, fn ($query) => $query->where('id', '<>', $exceptUserId))
            ->each(fn (User $user) => $this->notifyUser($user, $title, $body, $url));
    }

    public function notifyBorrower(Borrower $borrower, string $title, string $body, string $url): void
    {
        $this->notifyUser($borrower->user()->first(), $title, $body, $url);
    }
}
