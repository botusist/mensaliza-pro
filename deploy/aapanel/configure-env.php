<?php

if ($argc !== 2 || ! is_file($argv[1])) {
    fwrite(STDERR, "Uso: configure-env.php /caminho/.env\n");
    exit(1);
}

$path = $argv[1];
$contents = file_get_contents($path);
$input = stream_get_contents(STDIN);

foreach (preg_split('/\R/', trim($input)) as $line) {
    if ($line === '' || ! str_contains($line, '=')) {
        continue;
    }
    [$key, $value] = explode('=', $line, 2);
    $escaped = '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '', ''], $value).'"';
    $pattern = '/^'.preg_quote($key, '/').'=.*/m';
    $replacement = $key.'='.$escaped;
    $contents = preg_match($pattern, $contents) ? preg_replace($pattern, $replacement, $contents) : rtrim($contents).PHP_EOL.$replacement.PHP_EOL;
}

file_put_contents($path, $contents, LOCK_EX);
