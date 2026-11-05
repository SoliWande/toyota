<?php

use App\Models\Dealer;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

// Presentation fixtures only. No database driver, no runtime fixture routes.
putenv('APP_CONFIG_CACHE='.dirname(__DIR__, 3).'/.laravel-tools/dealer-no-cache.php');
putenv('APP_ENV=testing');
putenv('DB_CONNECTION=disabled-for-ui');
putenv('SESSION_DRIVER=array');
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'disabled-for-ui', 'cache.default' => 'array', 'session.driver' => 'array']);
app('url')->forceRootUrl('http://127.0.0.1:8771');
$directory = base_path('.laravel-tools/competition/dealer-states');
if (! is_dir($directory)) {
    mkdir($directory, 0777, true);
}
$names = ['Toyota Thái Nguyên', 'Toyota Bến Thành', 'Toyota Hà Tĩnh', 'Toyota Vinh', 'Toyota An Sương'];
$provinces = ['Thái Nguyên', 'TP. Hồ Chí Minh', 'Hà Tĩnh', 'Nghệ An', 'TP. Hồ Chí Minh'];
foreach (['directory', 'filtered', 'empty', 'one', 'long-directory', 'many', 'page-two', 'profile', 'zero', 'long-profile', 'photo', 'inactive', 'unapproved'] as $state) {
    $isDirectory = in_array($state, ['directory', 'filtered', 'empty', 'one', 'long-directory', 'many', 'page-two']);
    $request = Request::create('http://127.0.0.1:8771/'.($isDirectory ? 'dealers' : 'dealers/1/profile'));
    $request->setRouteResolver(fn () => Route::getRoutes()->match($request));
    $app->instance('request', $request);
    $long = str_starts_with($state, 'long-');
    if ($isDirectory) {
        $count = match ($state) {
            'empty' => 0, 'one' => 1, 'many', 'page-two' => 46, default => 5
        };
        $items = collect();
        for ($index = 0; $index < $count; $index++) {
            $items->push((object) ['id' => $index + 1, 'name' => $long ? str_repeat('Đại lý kết nối hành trình Việt Nam ', 4) : $names[$index % 5],
                'province' => $long ? str_repeat('Thành phố Hồ Chí Minh ', 4) : $provinces[$index % 5],
                'score' => $long ? 123456789 : [87, 41, 33, 29, 5][$index % 5], 'members_count' => $long ? 123456789 : [87, 41, 33, 29, 5][$index % 5], 'rank' => [1, 4, 7, 10, 12][$index % 5]]);
        }
        $page = $state === 'page-two' ? 2 : 1;
        $rows = new LengthAwarePaginator($items->forPage($page, 20)->values(), in_array($state, ['directory', 'long-directory']) ? 87 : $count, 20, $page, ['path' => 'http://127.0.0.1:8771/dealers']);
        $html = Blade::render('<x-competition.dealer-directory :dealers="$rows" :provinces="$provinces" :filters="$filters" />', ['rows' => $rows, 'provinces' => array_unique($provinces), 'filters' => $state === 'filtered' ? ['province' => 'Thái Nguyên', 'q' => 'Toyota'] : []]);
    } else {
        $dealer = new Dealer;
        $dealer->forceFill(['id' => 1, 'slug' => 'qa-thai-nguyen', 'name' => $long ? str_repeat('Toyota kết nối hành trình Việt Nam ', 5) : $names[0],
            'code' => 'TMV-724', 'province' => 'Thái Nguyên', 'is_active' => $state !== 'inactive', 'phone' => '0208 383 8888',
            'address' => $long ? str_repeat('Đường Cách Mạng Tháng Tám, phường Tích Lương, ', 10) : 'Đường Cách Mạng Tháng 8, tổ 2, phường Tích Lương, Thái Nguyên',
            'opening_hours' => 'Thứ Hai đến Thứ Bảy, 8:00 – 17:00']);
        $standing = $state === 'zero' ? null : (object) ['score' => $long ? 123456789 : 87, 'rank' => 6];
        $months = array_map(fn ($i) => ['label' => 'Tháng '.($i + 8), 'period' => '2026-'.sprintf('%02d', $i + 8), 'score' => $state === 'zero' ? 0 : [6, 14, 67][$i]], [0, 1, 2]);
        $phone = in_array($state, ['inactive', 'unapproved']) ? null : '02083838888';
        $photo = $state === 'photo' ? asset('dealer-registration/benefit-service.png') : null;
        $html = Blade::render('<x-competition.dealer-profile :dealer="$dealer" :standing="$standing" :months="$months" :phone="$phone" :photo-url="$photo" />', compact('dealer', 'standing', 'months', 'phone', 'photo'));
    }
    file_put_contents($directory.'/'.$state.'.html', $html);
}
echo 'PASS13 DB-disabled Dealer Blade fixtures; no writes or runtime fixture endpoints.'.PHP_EOL;
