<?php
function calculate_stats($lexicon)
{
    $translation_count = 0;

    foreach($lexicon as $entry) {
        if (strpos($entry, ';')) {
            $translation_count++;
        }
    }

    $total = count($lexicon);
    $ratio = round($translation_count / $total * 100);

    return array($translation_count, $total, $ratio);
}

function get_combined_words($words)
{
    $combined_words = array();

    foreach(array_keys($words) as $word) {
        $values = explode('/', $word);

        if (count($values) == 2){
            list($original, $combined_as_string) = $values;
            $combined_as_array = explode('_', $combined_as_string);
            $first_combined = current($combined_as_array);

            if ($first_combined == $original) {
                $combined_words[$original][] = array(
                    'string' => $combined_as_string,
                    'array'  => $combined_as_array,
                );
            }
        }
    }

    return $combined_words;
}

function index_words($lexicon, $end_of_translation_index)
{
    $words = array();

    foreach($lexicon as $index => $entry) {
        if ($index == $end_of_translation_index) {
            break;
        }

        if (isset($entry['translation'])) {
            $original = $entry['original_lower_case'];
            $words[$original][] = $entry['translation'];
        }
    }

    return $words;
}

function is_combined_word($combined_word, $lexicon, $index)
{
    foreach($combined_word as $sub_index => $word) {
        if ($sub_index == 0) {
            continue;
        }

        $next_entry = $lexicon[$index + $sub_index];

        if ($next_entry['original_lower_case'] != $word) {
            return false;
        }
    }

    return true;
}

function parse_lexicon($lexicon) {
    $parsed = array();
    $end_of_translation_tag = '_END_';
    $end_of_translation_index = false;

    foreach($lexicon as $index => $line) {
        if ($end_of_translation_index === false and strpos($line, $end_of_translation_tag) !== false) {
            $end_of_translation_index = $index;
            $line = str_replace($end_of_translation_tag, '', $line);
        }

        $values = explode(';', $line);

        if (count($values) > 3) {
            die("more than 3 items on line " . ++$index);
        }

        $original = trim($values[0]);

        if ($end_of_translation_index !== false) {
            // this word has not been translated yet, removes the "combined word" if any
            list($original) = explode('/', $original);
        }

        $parsed[$index]['original'] = $original;
        $parsed[$index]['original_lower_case'] = mb_strtolower($parsed[$index]['original'], 'UTF-8');


        if ($end_of_translation_index === false and ! empty($values[1])) {
            $parsed[$index]['translation'] = trim($values[1]);
        }
    }

    if ($end_of_translation_index === false) {
        die("$end_of_translation_tag of translation tag is missing");
    }

    return array($parsed, $end_of_translation_index);
}

function search_combined_word($combined_words, $lexicon, $index)
{
    foreach($combined_words as $combined_word) {
        if (is_combined_word($combined_word['array'], $lexicon, $index)) {
            return $combined_word;
        }
    }

    return false;
}

function set_combined_word($combined_word, $lexicon, $index)
{
    $count = count($combined_word['array']);

    while($count--) {
        $combined = '/' . $combined_word['string'];
        $lexicon[$index]['original']            .= $combined;
        $lexicon[$index]['original_lower_case'] .= $combined;
        $index++;
    }

    return $lexicon;
}

function set_combined_words($lexicon, $combined_words, $end_of_translation_index)
{
    foreach(array_keys($lexicon) as $index) {
        $original = $lexicon[$index]['original_lower_case'];

        if (isset($combined_words[$original])) {
            $combined_word = search_combined_word($combined_words[$original], $lexicon, $index);

            if ($combined_word !== false) {
                if ($index < $end_of_translation_index) {
                    die(sprintf('you must fix combined word %s manually line %s', $combined_word['string'], $index + 1));
                }

                $lexicon = set_combined_word($combined_word, $lexicon, $index);
            }
        }
    }

    return $lexicon;
}

function set_translations($words)
{
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

function update_lexicon($lexicon, $words)
{
    $updated = array();

    foreach($lexicon as $entry) {
        $original = $entry['original_lower_case'];

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
list($lexicon, $end_of_translation_index) = parse_lexicon($lexicon);
$words = index_words($lexicon, $end_of_translation_index);
$words = set_translations($words);
$combined_words = get_combined_words($words);
$lexicon = set_combined_words($lexicon, $combined_words, $end_of_translation_index);
$lexicon = update_lexicon($lexicon, $words);
list($translation_count, $total, $ratio) = calculate_stats($lexicon);

rename($filename, basename($filename, '.txt') . '.' . time() .  '.txt');
$content = implode("\n", $lexicon);
file_put_contents($filename, $content);

echo "$translation_count / $total ($ratio %)";