<?php

namespace Database\Seeders;

use App\Enums\LeaderboardPeriod;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\LeaderboardService;
use App\Services\SalesAvatars;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Throwable;

class DevelopmentSalesAvatarSeeder extends Seeder
{
    public function run(LeaderboardService $leaderboard, SalesAvatars $avatars): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Demo avatars are restricted to local/testing.');
        }
        $sources = array_map(fn ($i) => database_path(sprintf('data/demo-avatars/portrait-%02d.png', $i)), range(1, 3));
        foreach ($sources as $source) {
            if (! is_file($source)) {
                throw new LogicException('A generated demo portrait is missing.');
            }
        }
        $emails = array_map(fn ($i) => sprintf('demo.sales.%02d@example.test', $i), range(1, 50));
        $paths = [];
        $created = 0;
        try {
            DB::transaction(function () use ($emails, $sources, $leaderboard, $avatars, &$paths, &$created) {
                $users = User::whereIn('email', $emails)->orderBy('id')->lockForUpdate()->get();
                if ($users->contains(fn ($user) => $user->role !== UserRole::Sales || ! str_starts_with($user->name, '[DEMO] '))) {
                    throw new LogicException('Conflicting demo accounts exist; refusing to overwrite them.');
                }
                // Give the three weekly leaders different portraits, then reuse the demo set.
                $rankedIds = $leaderboard->sales(LeaderboardPeriod::Week)->pluck('id')->all();
                $ordered = $users->sortBy(fn ($user) => array_search($user->id, $rankedIds, true) === false
                    ? count($rankedIds) + $user->id : array_search($user->id, $rankedIds, true))->values();
                foreach ($ordered as $index => $user) {
                    if ($user->avatar_path !== null) {
                        continue; // Preserve uploads and allow safe repeated execution.
                    }
                    $path = $avatars->store($sources[$index % count($sources)], generatedDemo: true);
                    $paths[] = $path;
                    $user->avatar_path = $path;
                    $user->save();
                    $created++;
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('sales_avatars')->delete($paths);
            throw $exception;
        }
        $this->command?->info("Assigned {$created} private demo avatars; existing uploads were preserved.");
    }
}
