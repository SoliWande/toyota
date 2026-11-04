<?php

use App\Models\Award;
use App\Models\AwardWinner;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

// Unsaved presentation DTOs. Database disabled; never add fixture routes to app.
putenv('APP_ENV=testing');
putenv('DB_CONNECTION=disabled-for-ui');
putenv('SESSION_DRIVER=array');
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'disabled-for-ui', 'cache.default' => 'array', 'session.driver' => 'array']);
$request = Request::create('http://127.0.0.1:8771/awards');
$request->setRouteResolver(fn () => Route::getRoutes()->match($request));
$app->instance('request', $request);
app('url')->forceRootUrl('http://127.0.0.1:8771');
$directory = base_path('.laravel-tools/competition/awards-states');
if (! is_dir($directory)) {
    mkdir($directory, 0777, true);
}
foreach (['design', 'selected-2', 'selected-3', 'empty', 'long', 'many', 'page-two', 'weekly', 'missing', 'one', 'detail'] as $state) {
    $count = in_array($state, ['many', 'page-two']) ? 14 : ($state === 'empty' ? 0 : 3);
    $periods = collect();
    for ($index = 0; $index < $count; $index++) {
        $type = $state === 'weekly' ? 'weekly' : 'monthly';
        $start = CarbonImmutable::parse($type === 'monthly' ? '2026-10-01' : '2026-10-26');
        $start = $type === 'monthly' ? $start->subMonths($index) : $start->subWeeks($index);
        $award = new Award;
        $award->setDateFormat('Y-m-d H:i:s');
        $award->forceFill(['id' => $index + 1, 'period_type' => $type, 'period_start' => $start,
            'period_end' => $type === 'monthly' ? $start->addMonth() : $start->addWeek(), 'published_at' => $start->addMonth()]);
        $winners = collect();
        foreach ([1 => 67, 2 => 9, 3 => 8] as $rank => $score) {
            if ($state === 'one' && $rank > 1) {
                continue;
            }
            $winner = new AwardWinner;
            $names = ['Toyota Thái Nguyên', 'Toyota Lý Thường Kiệt – CN Tân Phú', 'Toyota Nankai Hải Phòng'];
            if ($rank === 1 && $index > 0) {
                $names[0] = $index === 1 ? 'Toyota Bến Thành' : 'Toyota Hà Tĩnh';
            }
            $winner->forceFill(['dealer_id' => $state === 'missing' ? null : $rank, 'rank' => $rank,
                'score' => $state === 'long' ? 123456789 : $score,
                'dealer_name_snapshot' => $state === 'missing' ? '' : ($state === 'long' ? str_repeat('MỖI KẾT NỐI MỘT HÀNH TRÌNH MỚI – ', 4).$rank : $names[$rank - 1]),
                'dealer_code_snapshot' => $state === 'missing' ? '' : ($state === 'long' ? str_repeat('QA-', 15) : ['TMV-724', 'TMV-684', 'TMV-972'][$rank - 1])]);
            $winners->push($winner);
        }
        $award->setRelation('winners', $winners);
        $periods->push($award);
    }
    $page = $state === 'page-two' ? 2 : 1;
    $type = $state === 'weekly' ? 'weekly' : '';
    $awards = new LengthAwarePaginator($periods->forPage($page, 12)->values(), $count, 12, $page, ['path' => 'http://127.0.0.1:8771/awards', 'pageName' => 'page']);
    if ($type !== '') {
        $awards->appends(['period_type' => $type]);
    }
    $selected = match ($state) {
        'selected-2' => $periods[1], 'selected-3' => $periods[2], default => $awards->first()
    };
    $html = Blade::render('<x-competition.awards-page :awards="$awards" :selected-award="$selected" :period-type="$type" :detail="$detail" />', [
        'awards' => $awards, 'selected' => $selected, 'type' => $type, 'detail' => $state === 'detail',
    ]);
    file_put_contents($directory.'/'.$state.'.html', $html);
}
echo 'PASS11 private Awards states; database disabled, no writes or runtime QA route.'.PHP_EOL;
