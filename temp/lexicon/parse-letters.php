<?php
$content = file_get_contents('verses.txt');
preg_match_all('~.~u', $content, $matches);
$letters = current($matches);
$letters = array_unique($letters);
sort($letters);
$content = implode("\n", $letters);
file_put_contents('letters.txt', $content);