<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

// UI-only DTOs, not a test of SQL sorting. SQL/eligibility are tested in PHPUnit.
putenv('APP_CONFIG_CACHE='.dirname(__DIR__, 3).'/.laravel-tools/dealer-no-cache.php');
putenv('APP_ENV=testing');
putenv('DB_CONNECTION=disabled-for-ui');
putenv('SESSION_DRIVER=array');
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'disabled-for-ui', 'cache.default' => 'array', 'session.driver' => 'array']);
app('url')->forceRootUrl('http://127.0.0.1:8771');
$request = Request::create('http://127.0.0.1:8771/dealers');
$request->setRouteResolver(fn () => Route::getRoutes()->match($request));
$app->instance('request', $request);
$directory = base_path('.laravel-tools/competition/dealer-filter-states');
if (! is_dir($directory)) {
    mkdir($directory, 0777, true);
}
$cases = ['default' => ['members', 'desc'], 'no-provinces' => ['members', 'desc'], 'empty' => ['name', 'asc'],
    'members-asc' => ['members', 'asc'], 'members-desc' => ['members', 'desc'],
    'name-asc' => ['name', 'asc'], 'name-desc' => ['name', 'desc'], 'rank-asc' => ['rank', 'asc'], 'rank-desc' => ['rank', 'desc']];
foreach ($cases as $state => [$sort, $direction]) {
    foreach ([1, 2] as $page) {
        $items = collect();
        if ($state !== 'empty') {
            foreach (['Toyota Hà Nội', 'Toyota Đà Nẵng', 'Toyota An Giang', 'Toyota Chưa có hồ sơ'] as $index => $name) {
                $items->push((object) ['id' => $index + 1, 'name' => $name,
                    'province' => $state === 'no-provinces' ? null : ['Hà Nội', 'Đà Nẵng', 'An Giang', 'Hà Nội'][$index],
                    'score' => [2, 8, 2, 0][$index], 'members_count' => [2, 8, 2, 0][$index], 'rank' => [3, 1, 2, null][$index]]);
            }
            if (! in_array($state, ['default', 'no-provinces'])) {
                for ($i = 1; $i <= 42; $i++) {
                    $items->push((object) ['id' => 4 + $i, 'name' => sprintf('Toyota QA %02d', $i), 'province' => 'Hà Nội',
                        'score' => 0, 'members_count' => 0, 'rank' => null]);
                }
                $items = $items->where('province', 'Hà Nội')->values();
            }
            $items = $items->sort(function ($a, $b) use ($sort, $direction) {
                if ($sort === 'rank' && ($a->rank === null || $b->rank === null)) {
                    return ($a->rank === null) <=> ($b->rank === null) ?: $a->id <=> $b->id;
                }
                $column = ['members' => 'members_count', 'name' => 'name', 'rank' => 'rank'][$sort];
                $comparison = $sort === 'name' ? strnatcasecmp(Str::ascii($a->name), Str::ascii($b->name)) : $a->$column <=> $b->$column;
                if ($comparison) {
                    return $direction === 'asc' ? $comparison : -$comparison;
                }

                return ($sort === 'members' ? strcmp($a->name, $b->name) : 0) ?: $a->id <=> $b->id;
            })->values();
        }
        $filters = ['sort' => $sort, 'direction' => $direction];
        if (! in_array($state, ['default', 'no-provinces'])) {
            $filters += ['q' => 'Toyota', 'province' => $state === 'empty' ? 'Không có đại lý' : 'Hà Nội'];
        }
        $rows = new LengthAwarePaginator($items->forPage($page, 20)->values(), $items->count(), 20, $page, ['path' => 'http://127.0.0.1:8771/dealers']);
        $rows->appends($filters);
        $provinces = $state === 'no-provinces' ? [] : ['An Giang', 'Đà Nẵng', 'Hà Nội'];
        file_put_contents($directory.'/'.$state.'-'.$page.'.html', Blade::render('<x-competition.dealer-directory :dealers="$rows" :filters="$filters" :provinces="$provinces" />', compact('rows', 'filters', 'provinces')));
    }
}
echo 'PASS18 DB-disabled filter presentation states; no DB writes or application fixture endpoints.'.PHP_EOL;
