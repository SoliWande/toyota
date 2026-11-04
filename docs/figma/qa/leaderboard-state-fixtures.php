<?php

use App\Enums\LeaderboardPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;

// Private UI fixtures: no database access, persistence, or production routes.
putenv('APP_ENV=testing');
putenv('DB_CONNECTION=disabled-for-ui');
putenv('SESSION_DRIVER=array');
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'disabled-for-ui', 'cache.default' => 'array', 'session.driver' => 'array']);
$request = Request::create('http://127.0.0.1:8771/leaderboard');
$request->setRouteResolver(fn () => Route::getRoutes()->match($request));
$app->instance('request', $request);
app('url')->forceRootUrl('http://127.0.0.1:8771');
$directory = base_path('.laravel-tools/competition/leaderboard-states');
if (! is_dir($directory)) {
    mkdir($directory, 0777, true);
}
$names = ['Toyota Thái Nguyên', 'Toyota Lý Thường Kiệt – CN Tân Phú', 'Toyota Nankai Hải Phòng', 'Toyota Bến Thành', 'Toyota Hưng Yên – CN Văn Lâm', 'Toyota Hùng Vương', 'Toyota Hà Tĩnh', 'Toyota Đông Sài Gòn – CN Thủ Đức', 'Toyota Tây Bắc Sơn La', 'Toyota Vinh'];
$codes = ['TMV-724', 'TMV-684', 'TMV-972', 'TMV-408', 'TMV-1024', 'TMV-422', 'TMV-979', 'TMV-995', 'TMV-991', 'TMV-434'];
$scores = [67, 9, 8, 8, 7, 7, 6, 6, 5, 5];
foreach (['design', 'long', 'empty', 'page-two', 'one', 'two'] as $state) {
    $count = match ($state) {
        'empty' => 0, 'one' => 1, 'two' => 2, 'design' => 10, default => 23
    };
    $rows = collect(range(1, max(1, $count)))->take($count)->map(fn ($rank) => (object) [
        'id' => $rank, 'rank' => $rank,
        'name' => $state === 'design' ? $names[$rank - 1] : 'Đại lý kiểm thử tên rất dài MỖI KẾT NỐI MỘT HÀNH TRÌNH MỚI – Chi nhánh '.$rank,
        'code' => $state === 'design' ? $codes[$rank - 1] : 'QA-ONLY-'.str_repeat('AB', 12).$rank,
        'score' => $state === 'design' ? $scores[$rank - 1] : 123456789 - $rank,
    ]);
    $page = $state === 'page-two' ? 2 : 1;
    $paginator = new LengthAwarePaginator($rows->forPage($page, 20)->values(), $count, 20, $page, ['path' => 'http://127.0.0.1:8771/leaderboard', 'pageName' => 'dealers_page']);
    $paginator->appends(['period' => 'month']);
    file_put_contents($directory.'/'.$state.'.html', view('leaderboard.index', [
        'period' => LeaderboardPeriod::Month,
        'bounds' => [CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-11-01')],
        'dealers' => $paginator, 'topDealers' => $rows->take(3),
    ])->render());
}
echo 'PASS six private Blade fixtures; database disabled, no application route or data write.'.PHP_EOL;
