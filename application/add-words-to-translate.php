<?php
/**
 * Roman de Renart
 *
 * Command to add the words to translate
 *
 * @author    Michel Corne <mcorne@yahoo.com>
 * @copyright 2012 Michel Corne
 * @license   http://www.opensource.org/licenses/gpl-3.0.html GNU GPL v3
 * @link      http://roman-de-renart.blogspot.com/
 */

require_once 'common.php';

/**
 * Main function to add the words to translates
 */
 function exec_add_words_to_translate()
{
    echo_command_title('adding words to translate');

    $words_filename = __DIR__ . "/../data/words.csv";
    $words = read_csv($words_filename);
    $last_translated_verse_number = get_last_verse_number($words);

    $verses = read_csv(__DIR__ . "/../data/roman-de-renart.csv", 'verse-number');
    $first_verse_number = $last_translated_verse_number + 1;
    $last_verse_number = get_number_last_verse_to_translate($verses);
    $verses_to_translate = validate_verse_number_range($verses, $first_verse_number, $last_verse_number);
    $words_to_translate = get_words_from_verses($verses_to_translate);

    $words = array_merge($words, $words_to_translate);
    $has_content_changed = write_csv($words_filename, $words);

    echo count($words_to_translate). " words added ";
    echo_has_content_changed($has_content_changed);
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
 * @throws Exception
 * @return array                   the words of the verses
 */
function get_words_from_verses($verses)
{
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
