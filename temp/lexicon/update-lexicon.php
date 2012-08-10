<?php
function index_words($lexicon)
{
    $words = array();

    foreach($lexicon as $entry) {
        if (isset($entry['translation'])) {
            $original = mb_strtolower($entry['original'], 'UTF-8');
            $words[$original][] = $entry['translation'];
        }
    }

    $indexed = array();

    foreach($words as $original => $translations) {
        $translations = array_count_values($translations);
        arsort($translations);
        $translations = array_keys($translations);

        $indexed[$original]['translation'] = current($translations);

        if (count($translations) != 1) {
            $indexed[$original]['translations'] = implode(', ', $translations);
        }
    }

    return $indexed;
}

function parse_lexicon($lexicon) {
    $parsed = array();
    $end_of_translation_tag = '_END_';
    $is_end_of_translation = false;

    foreach($lexicon as $index => $line) {
        if (! $is_end_of_translation and strpos($line, $end_of_translation_tag) !== false) {
            $is_end_of_translation = true;
            $line = str_replace($end_of_translation_tag, '', $line);
        }

        $values = explode(';', $line);

        if (count($values) > 3) {
            die("more than 3 items on line " . ++$index);
        }

        $parsed[$index]['original'] = trim($values[0]);

        if (! $is_end_of_translation and ! empty($values[1])) {
            $parsed[$index]['translation'] = trim($values[1]);
        }
    }

    if (! $is_end_of_translation) {
        die("$end_of_translation_tag of translation tag is missing");
    }

    return $parsed;
}

function update_lexicon($lexicon, $words)
{
    $updated = array();

    foreach($lexicon as $entry) {
        $original = mb_strtolower($entry['original'], 'UTF-8');

        if (! isset($entry['translation']) and isset($words[$original])) {
            $entry['translation'] = $words[$original]['translation'];
        }

        if (isset($words[$original]['translations'])) {
            $updated[] = sprintf('%-20s ; %-20s ; %s', $entry['original'], $entry['translation'], $words[$original]['translations']);
        } else if (isset($entry['translation'])) {
            $updated[] = sprintf('%-20s ; %s', $entry['original'], $entry['translation']);
        } else {
            $updated[] = $entry['original'];
        }
    }

    return $updated;
}

$filename = 'lexicon.txt';
$lexicon = file($filename, FILE_IGNORE_NEW_LINES);
// $lexicon = array_slice($lexicon, 0, 1000); // TODO: remove
$lexicon = parse_lexicon($lexicon);
$words = index_words($lexicon);
$lexicon = update_lexicon($lexicon, $words);

rename($filename, basename($filename, '.txt') . '.' . time() .  '.txt');
$content = implode("\n", $lexicon);
file_put_contents($filename, $content);