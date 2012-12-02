<?php
$available = file('available.txt', FILE_IGNORE_NEW_LINES);
$fhs = file('fhs.txt', FILE_IGNORE_NEW_LINES);
$typos = array_diff($fhs, $available);
$content = implode("\n", $typos);
file_put_contents('typos.txt', $content);
echo count($typos);