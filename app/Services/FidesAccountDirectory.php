<?php

namespace App\Services;

use App\Models\Borrower;
use App\Models\PlatformAccountLink;
use App\Models\PlatformCooperative;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class FidesAccountDirectory
{
    public function syncUser(User $user): void
    {
        $cooperativeId = config('fides.current_tenant_id');

        if (! $cooperativeId) {
            return;
        }

        if (DB::connection()->transactionLevel() > 0) {
            $userId = $user->id;
            DB::connection()->afterCommit(fn () => $this->syncUserAfterCommit($userId));

            return;
        }

        $this->persistUser($user);
    }

    private function syncUserAfterCommit(int $userId): void
    {
        $user = User::query()->find($userId);

        if ($user) {
            $this->persistUser($user);
        }
    }

    private function persistUser(User $user): void
    {
        $cooperativeId = config('fides.current_tenant_id');

        if (! $cooperativeId) {
            return;
        }

        $borrowerCode = Borrower::query()->where('user_id', $user->id)->value('borrower_code');

        PlatformAccountLink::query()->updateOrCreate(
            ['cooperative_id' => $cooperativeId, 'tenant_user_id' => $user->id],
            [
                'username' => $user->username,
                'phone' => $user->phone,
                'borrower_code' => $borrowerCode,
            ],
        );
    }

    public function syncBorrower(Borrower $borrower): void
    {
        if (! $borrower->user_id || ! config('fides.current_tenant_id')) {
            return;
        }

        $user = User::query()->find($borrower->user_id);

        if ($user) {
            $this->syncUser($user);
        }
    }

    public function removeUser(User $user): void
    {
        $cooperativeId = config('fides.current_tenant_id');

        if ($cooperativeId) {
            if (DB::connection()->transactionLevel() > 0) {
                $userId = $user->id;
                DB::connection()->afterCommit(fn () => $this->removeUserAfterCommit($userId));

                return;
            }

            PlatformAccountLink::query()
                ->where('cooperative_id', $cooperativeId)
                ->where('tenant_user_id', $user->id)
                ->delete();
        }
    }

    private function removeUserAfterCommit(int $userId): void
    {
        $cooperativeId = config('fides.current_tenant_id');

        if ($cooperativeId) {
            PlatformAccountLink::query()
                ->where('cooperative_id', $cooperativeId)
                ->where('tenant_user_id', $userId)
                ->delete();
        }
    }

    public function candidates(string $identifier): Collection
    {
        return PlatformAccountLink::query()
            ->where(function ($query) use ($identifier) {
                $query->where('username', $identifier)
                    ->orWhere('phone', $identifier)
                    ->orWhere('borrower_code', $identifier);
            })
            ->where('is_active', true)
            ->whereHas('cooperative', fn ($query) => $query->where('status', 'active'))
            ->with('cooperative')
            ->get()
            ->unique('cooperative_id')
            ->values();
    }

    public function authenticate(string $identifier, string $password, int $cooperativeId): ?User
    {
        $links = PlatformAccountLink::query()
            ->where('cooperative_id', $cooperativeId)
            ->where(function ($query) use ($identifier) {
                $query->where('username', $identifier)
                    ->orWhere('phone', $identifier)
                    ->orWhere('borrower_code', $identifier);
            })
            ->where('is_active', true)
            ->get();

        if ($links->isEmpty()) {
            return null;
        }

        $cooperative = PlatformCooperative::query()
            ->whereKey($cooperativeId)
            ->where('status', 'active')
            ->first();

        if (! $cooperative) {
            return null;
        }

        app(FidesTenantManager::class)->activate($cooperative);

        foreach ($links as $link) {
            $user = User::query()->find($link->tenant_user_id);

            if ($user && Hash::check($password, $user->password)) {
                return $user;
            }
        }

        return null;
    }

    public function synchronizeCooperative(PlatformCooperative $cooperative): int
    {
        app(FidesTenantManager::class)->activate($cooperative);

        $count = 0;
        User::query()->with('borrower')->chunkById(200, function ($users) use (&$count) {
            foreach ($users as $user) {
                $this->syncUser($user);
                $count++;
            }
        });

        return $count;
    }
}
