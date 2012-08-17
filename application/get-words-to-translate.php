<?php
/**
 * Roman de Renart
 *
 * Command to get the words to translate
 *
 * @author    Michel Corne <mcorne@yahoo.com>
 * @copyright 2012 Michel Corne
 * @license   http://www.opensource.org/licenses/gpl-3.0.html GNU GPL v3
 * @link      http://roman-de-renart.blogspot.com/
 */

require_once 'common.php';

/**
 * Main function to get the words to translates
 */
 function exec_get_words_to_translate()
{
    echo_command_title('getting words to translate');

    $words_filename = __DIR__ . "/../data/words.csv";
    $words = read_csv($words_filename);
    $last_verse_number = get_last_verse_number($words);

    $verses = read_csv(__DIR__ . "/../data/roman-de-renart.csv");
    $index_first_verse = get_index_first_verse_to_translate($verses, $last_verse_number + 1);
    $index_last_verse = get_index_last_verse_to_translate($verses);
    $words_to_translate = get_words_from_verses($verses, $index_first_verse, $index_last_verse);

    $words = array_merge($words, $words_to_translate);
    $has_content_changed = write_csv($words_filename, $words);

    echo count($words_to_translate). " words added ";
    echo_has_content_changed($has_content_changed);
}

/**
 * Returns the index of the first verse to translate
 *
 * @param array $verses       the verses of the text
 * @param int   $verse_number the number of the first verse
 * @throws Exception
 * @return int                the index of the verse
 */
function get_index_first_verse_to_translate($verses, $verse_number)
{
    foreach($verses as $index => $verse) {
        if ($verse['verse-number'] == $verse_number) {
            return $index;
        }
    }

    throw new Exception('cannot find first verse to translate');
}

/**
 * Returns the index of the last verse to translate
 *
 * @param array $verses the verses of the text
 * @throws Exception
 * @return int          the index of the verse
 */
function get_index_last_verse_to_translate($verses)
{
    $prev_index = null;

    foreach($verses as $index => $verse) {
        if ($verse['translated-verse'] == '...') {
            if (is_null($prev_index)) {
                throw new Exception('there is nothing to translate');
            }

            return $prev_index;
        }

        $prev_index = $index;
    }

    throw new Exception('cannot find last verse to translate (missing "..." marker in translated-verse column');
}

/**
 * Returns the verse number of the last translated word
 *
 * @param array $words the words of the text
 * @return int         the verse number
 */
function get_last_verse_number($words)
{
    $last_word = end($words);

    return $last_word['verse-number'];
}

/**
 * Returns the words of a verse
 *
 * @param string $verse the verse
 * @return array        the list of words
 */
function get_words_from_verse($verse)
{
    // replaces dots enclosing roman numbers not to be mistaken with a punctuation
    $verse = preg_replace('~\.([IVXLCDM]+)\.~', '_$1_', $verse);
    // enclose punctuations with spaces
    $verse = preg_replace('~(' . PUNCTUATION . ')~', ' $1 ', $verse);
    // restores the dot enclosing roman numbers
    $verse = str_replace('_', '.', $verse);
    // removes extra spaces
    $verse = preg_replace('~ +~', ' ', $verse);
    $verse = trim($verse);

    return explode(' ', $verse);
}

/**
 * Returns the words of a set of verses (the verses to translate)
 *
 * @param array $verses            the verses of the text
 * @param int   $index_first_verse the index of the first verse
 * @param int   $index_last_verse  the index of the last verse
 * @throws Exception
 * @return array                   the words of the verses
 */
function get_words_from_verses($verses, $index_first_verse, $index_last_verse)
{
    $count = $index_last_verse - $index_first_verse + 1;

    if ($count == 0) {
        throw new Exception('there is no words to add');
    }

    if ($count < 0) {
        throw new Exception("first verse: $index_first_verse greater than last verse: $index_last_verse");
    }

    $verses = array_slice($verses, $index_first_verse, $count);
    $words = array();

    foreach($verses as $verse) {
        $verse_words = get_words_from_verse($verse['original-verse']);

        foreach($verse_words as $word) {
            $words[] = array(
                'verse-number' => $verse['verse-number'],
                'original-word' => $word,
            );
        }
    }

    return $words;
}
