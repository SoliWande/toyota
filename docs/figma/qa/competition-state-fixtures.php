<?php

use App\Enums\LeaderboardPeriod;
use App\Models\Award;
use App\Models\AwardWinner;
use Illuminate\Contracts\Console\Kernel;

// Isolated Blade-state fixtures. Database access is disabled, no production mock routes.
putenv('APP_ENV=testing');
putenv('DB_CONNECTION=disabled-for-ui');
putenv('SESSION_DRIVER=array');
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'disabled-for-ui', 'cache.default' => 'array', 'session.driver' => 'array']);
$period = LeaderboardPeriod::Month;
$directory = base_path('.laravel-tools/competition/final-qa-states');
if (! is_dir($directory)) {
    mkdir($directory, 0777, true);
}
file_put_contents($directory.'/empty.html', view('welcome', [
    'period' => $period, 'stats' => ['dealers' => 0, 'points' => 0],
    'topDealers' => collect(), 'latestAward' => null,
])->render());
$rows = collect(range(1, 3))->map(fn ($rank) => (object) [
    'id' => $rank, 'name' => 'Đại lý kiểm thử với tên rất dài để kiểm tra xuống dòng an toàn '.$rank,
    'code' => 'QA-ONLY-'.$rank, 'score' => 12345 - $rank, 'rank' => $rank,
]);
$award = new Award;
$award->setDateFormat('Y-m-d H:i:s');
$award->forceFill(['id' => 123, 'period_type' => 'monthly', 'period_start' => '2026-10-01',
    'period_end' => '2026-11-01', 'published_at' => '2026-11-02']);
$award->setRelation('winners', $rows->map(function ($row) {
    $winner = new AwardWinner;
    $winner->forceFill(['dealer_id' => $row->id, 'dealer_name_snapshot' => $row->name,
        'dealer_code_snapshot' => $row->code, 'rank' => $row->rank, 'score' => $row->score]);

    return $winner;
}));
file_put_contents($directory.'/published.html', view('welcome', [
    'period' => $period, 'stats' => ['dealers' => 3, 'points' => 37029],
    'topDealers' => $rows, 'latestAward' => $award,
])->render());
echo 'PASS private empty/published Blade fixtures; DB driver disabled, no writes.'.PHP_EOL;
