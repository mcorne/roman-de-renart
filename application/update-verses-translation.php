<?php
/**
 * Roman de Renart
 *
 * Command to update the translation of the verses
 *
 * @author    Michel Corne <mcorne@yahoo.com>
 * @copyright 2012 Michel Corne
 * @license   http://www.opensource.org/licenses/gpl-3.0.html GNU GPL v3
 * @link      http://roman-de-renart.blogspot.com/
 */

require_once 'common.php';

/**
 * Collects the translated words of a subset of verses
 *
 * @param array $words              the words of the text
 * @param int   $first_verse_number the number of the first verse to collect
 * @param int   $last_verse_number  the number of the last verse to collect
 * @throws Exception
 * @return array                    the verses translation
 */
function collect_verses_translation($words, $first_verse_number, $last_verse_number)
{
    $punctuation = get_punctuation();
    $verses = array();

    foreach ($words as $word) {
        $verse_number = $word['verse-number'];
        $original = $word['original-word'];

        if ($verse_number < $first_verse_number) {
            continue;
        } elseif ($verse_number > $last_verse_number) {
            throw new Exception("unexpected verse $verse_number beyond last verse $last_verse_number");
        } elseif (isset($punctuation[$original])) {
            $verses[$verse_number][] = $original;
        } elseif (empty($word['translated-word'])) {
            throw new Exception("missing translation verse $verse_number for word $original");
        } elseif ($word['translated-word'] == '_EMPTY_') {
            continue;
        } else {
            $verses[$verse_number][] = $word['translated-word'];
        }
    }

    return $verses;
}

/**
 * Main function to update the translation of the verses
 */
 function exec_update_verses_translation()
 {
     echo_command_title('updating verses translation');

     $filename = __DIR__ . '/../data/verses.csv';
     $verses = read_csv($filename, 'verse-number');
     $first_verse_number = get_number_first_verse_to_translate($verses);
     $last_verse_number = get_number_last_verse_to_translate($verses);
     validate_verse_number_range($verses, $first_verse_number, $last_verse_number);

     $words = read_csv(__DIR__ . "/../data/words.csv");
     $verses_translation = collect_verses_translation($words, $first_verse_number, $last_verse_number);
     validate_verse_number_sequence($verses_translation, $first_verse_number, $last_verse_number);
     $verses = fix_verses_translation($verses, $verses_translation);

     $has_content_changed = write_csv($filename, $verses);

     echo count($verses_translation). " verses added ";
     echo_has_content_changed($has_content_changed);
 }

/**
 * Fixes the newly translated verses and adds the translation to the verses of the text
 *
 * @param array $verses             the verses of the text
 * @param array $verses_translation the newly translated verses
 * @throws Exception
 * @return array                    the verses of the text updated with the newly translated verses
 */
function fix_verses_translation($verses, $verses_translation)
{
    foreach ($verses_translation as $number => $verse) {
        // concatenates the words of the verse
        $verse = implode(' ', $verse);
        // removes spaces before the comma and period characters
        $verse = preg_replace('~ ([.,])~', '$1', $verse);

        if (! isset($verses[$number])) {
            throw new Exception("unknown verse number $number");
        }

        if (! empty($verses[$number]['translated-verse'])) {
            throw new Exception("verse number $number already translated");
        }

        $verses[$number]['translated-verse'] = $verse;
    }

    return $verses;
}

/**
 * Returns the number of the first verse to translate
 *
 * @param array $verses       the verses of the text
 * @throws Exception
 * @return int                the index of the verse
 */
function get_number_first_verse_to_translate($verses)
{
    foreach ($verses as $number => $verse) {
        if (empty($verse['translated-verse'])) {
            return $number;
        }
    }

    throw new Exception('cannot find first verse to translate');
}

/**
 * Validates the sequence of verse numbers between to two verse numbers
 *
 * @param array $verses             the verses
 * @param int   $first_verse_number the number of the first verse
 * @param int   $last_verse_number  the number of the last verse
 * @throws Exception
 */
function validate_verse_number_sequence($verses, $first_verse_number, $last_verse_number)
{
    if (empty($verses)) {
        throw new Exception("no verses were collected between $first_verse_number and $last_verse_number");
    }

    $verse_numbers = array_keys($verses);
    $expected_numbers = range($first_verse_number, $last_verse_number);

    if ($verse_numbers != $expected_numbers) {
        $verse_numbers = implode(', ', $verse_numbers);
        throw new Exception("bad verse numbers: $verse_numbers between $first_verse_number and $last_verse_number");
    }
}
