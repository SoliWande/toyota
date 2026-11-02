<?php

$root = dirname(__DIR__, 3);
$manifest = json_decode(file_get_contents($root.'/docs/figma/assets-manifest.json'), true, flags: JSON_THROW_ON_ERROR);
foreach ($manifest['assets'] as $asset) {
    $file = $root.'/'.$asset['path'];
    $size = is_file($file) ? getimagesize($file) : false;
    if (! $size || ! filesize($file)) throw new RuntimeException('Missing/invalid asset: '.$asset['path']);
    echo basename($file).' '.$size[0].'x'.$size[1].' '.filesize($file).' bytes'.PHP_EOL;
}
foreach (array_merge(glob($root.'/resources/views/components/dealer-registration/*.blade.php'), glob($root.'/resources/views/dealers/registration/*.blade.php')) as $view) {
    if (str_contains(file_get_contents($view), 'figma.com/api/mcp/asset')) throw new RuntimeException('Temporary asset URL in '.$view);
}
echo 'PASS '.count($manifest['assets']).' non-empty PNG assets; no temporary Figma URLs in views'.PHP_EOL;
