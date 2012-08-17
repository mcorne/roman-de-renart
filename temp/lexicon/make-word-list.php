<?php
$verses = file('verses-and-numbers.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach($verses as $index => $verse) {
    list($number, $verse) = explode("\t", $verse);
    if (! is_numeric($number)) {
        $index++;
        die("not a number: $number, line: $index");
    }

    $verse = preg_replace('~\.([IVXLCDM]+)\.~', '_$1_', $verse);
    $verse = preg_replace('~(!|,|\.|:|;|\?|«|»|—|“|”)~', ' $1 ', $verse);
    $verse = str_replace('_', '.', $verse);
    $verse = preg_replace('~ +~', ' ', $verse);
    $verse = trim($verse);
    $verse_words = explode(' ', $verse);

    foreach($verse_words as $word) {
        $words[] = array($number, $word);
        $words2[] = "$number ; $word"; // TODO: remove
    }
}

$content = implode("\n", $words2);
file_put_contents('words.txt', $content);

$lexicon = file('lexicon.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$index = 0;
$punctuation = array('!', ',', '.', ':', ';', '?', '«', '»', '—', '“', '”');
$punctuation = array_fill_keys($punctuation, true);

foreach($words as $words_index => &$word_and_number) {
    list($number, $word) = $word_and_number;

    if (! isset($punctuation[$word])) {
        list($lexicon_word) = explode(';', $lexicon[$index]);
        $lexicon_word = trim($lexicon_word);
        list($lexicon_word) = explode('/', $lexicon_word);

        if ($word != $lexicon_word) {
            $index++;
            $words_index++;
            die("word mismatch ($words_index): verse $number: $word different from lexicon entry $index: $lexicon_word");
        }

        $word = $lexicon[$index];
        $index++;
    }

    $word_and_number = sprintf('%5s ; %s', $number, $word);
}


$content = implode("\n", $words);
file_put_contents('words.txt', $content);