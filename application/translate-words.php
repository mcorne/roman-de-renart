<?php
/**
 * Roman de Renart
 *
 * Command to translate words
 *
 * @author    Michel Corne <mcorne@yahoo.com>
 * @copyright 2012 Michel Corne
 * @license   http://www.opensource.org/licenses/gpl-3.0.html GNU GPL v3
 * @link      http://roman-de-renart.blogspot.com/
 */

require_once 'common.php';

/**
 * Adds the translations of each word of the lexicon
 *
 * @param array $lexicon the lexicon
 * @return array         the lexicon updated with the translations
 */
function add_translations($lexicon)
{
    $fixed = array();

    foreach($lexicon as $original => $translations) {
        // counts the frequency of the translations of a word
        $translations = array_count_values($translations);
        // sorts the translations (most frequent first)
        arsort($translations);
        $translations = array_keys($translations);

        $fixed[$original]['translated-word'] = current($translations);

        if (count($translations) != 1) {
            $fixed[$original]['translations'] = implode(', ', $translations);
        }
    }

    return $fixed;
}

/**
 * Calculates the statistics of the words of the text
 *
 * @param array $words the words of the text
 * @return array the number of translated words, the number of words excluding the punctuation, the ratio
 */
function calculate_stats($words)
{
    $punctuation = get_punctuation();
    $word_count = 0;
    $translated_count = 0;

    foreach($words as $word) {
        if (! isset($punctuation[$word['original-word']])) {
            $word_count++;
        }

        if (! empty($word['translated-word'])) {
            $translated_count++;
        }
    }

    $ratio = round($translated_count / $word_count * 100);

    return array($translated_count, $word_count, $ratio);
}

/**
 * Main function to translate words
 */
 function exec_translate_words()
{
    echo_command_title('translating words');

    $words_filename = __DIR__ . "/../data/words.csv";
    $words = read_csv($words_filename);
    $words = parse_words($words);

    $lexicon = make_lexicon($words);
    $lexicon = add_translations($lexicon);

    $combined_words = get_combined_words($lexicon);
    $words = set_combined_words($words, $combined_words);
    $words = translate_words($words, $lexicon);

    list($translated_count, $word_count, $ratio) = calculate_stats($words);

    $has_content_changed = write_csv($words_filename, $words);

    echo "$translated_count translated / $word_count words ($ratio %) ";
    echo_has_content_changed($has_content_changed);
}

/**
 * Returns the list of punctuation characters
 *
 * @return array the punctuation characters
 */
function get_punctuation()
{
    $punctuation = str_replace('\\', '', PUNCTUATION);
    $punctuation = explode('|', $punctuation);

    return array_fill_keys($punctuation, true);
}

/**
 * Returns the list of combined words indexed by the first word of a combined word
 *
 * @param array $lexicon the lexicon
 * @return array         the list of combined words
 */
function get_combined_words($lexicon)
{
    $combined_words = array();

    foreach(array_keys($lexicon) as $word) {
        $values = explode('/', $word);

        if (count($values) == 2){
            // this is a combined word, ex. "si/si_con" or "com/si_con"
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

/**
 * Checks if a set of words is actually a combined word
 *
 * @param array $combined_word the list of words of the combined word, ex. array("si", "con")
 * @param array $words         the words of the text
 * @param int   $index         the index of the first word of the set
 * @return boolean             true if this is a combined word, false otherwise
 */
function is_combined_word($combined_word, $words, $index)
{
    foreach($combined_word as $sub_index => $word) {
        if ($sub_index == 0) {
            continue;
        }

        $next_entry = $words[$index + $sub_index];

        if ($next_entry['original_word_lower_case'] != $word) {
            return false;
        }
    }

    return true;
}

/**
 * Builds the lexicon
 *
 * @param array $words the words of the text
 * @return array       the lexicon
 */
function make_lexicon($words)
{
    $lexicon = array();

    foreach($words as $word) {
        if (! empty($word['translated-word'])) {
            $original = $word['original_word_lower_case'];
            $lexicon[$original][] = $word['translated-word'];
        }
    }

    return $lexicon;
}

/**
 * Parses the words of the text (keeps confirmed translations)
 *
 * @param array $words the words of the text
 * @return array       the words of the text
 */
function parse_words($words) {
    foreach($words as &$word) {
        if ($word['not-confirmed'] == '?') {
            // this is an unconfirmed translation, discards the "combined word" if any
            list($word['original-word']) = explode('/', $word['original-word']);
            $word['translated-word'] = null;
        }

        $word['original_word_lower_case'] = mb_strtolower($word['original-word'], 'UTF-8');
        $word['not-confirmed'] = null;
        $word['translations'] = null;
    }

    return $words;
}

/**
 * Searches for a combined word at a given point in the words of the text
 *
 * @param array $combined_words the combined words
 * @param array $words          the words of the text
 * @param int   $index          the index of the word that is possibly the first word of a combined word
 * @return array|false          the words of the combined word, false otherwise
 */
function search_combined_word($combined_words, $words, $index)
{
    foreach($combined_words as $combined_word) {
        if (is_combined_word($combined_word['array'], $words, $index)) {
            return $combined_word;
        }
    }

    return false;
}

/**
 * Sets the words of a combined word
 *
 * @param array $combined_word the combined word
 * @param array $words         the words of the text
 * @param int   $index         the index of the first word of the combined word
 * @return                     the words of the text updated with the combined word
 */
function set_combined_word($combined_word, $words, $index)
{
    $count = count($combined_word['array']);

    while($count--) {
        $combined = '/' . $combined_word['string'];
        $words[$index]['original-word']            .= $combined;
        $words[$index]['original_word_lower_case'] .= $combined;
        $index++;
    }

    return $words;
}

/**
 * Sets the combined words
 *
 * @param array $words          the words of the text
 * @param array $combined_words the combined words
 * @return array                the words of the text
 */
function set_combined_words($words, $combined_words)
{
    foreach(array_keys($words) as $index) {
        $original = $words[$index]['original_word_lower_case'];

        if (isset($combined_words[$original])) {
            $combined_word = search_combined_word($combined_words[$original], $words, $index);

            if ($combined_word !== false) {
                if (isset($words[$index]['translated-word'])) {
                    die(sprintf('you must fix combined word %s manually line %s', $combined_word['string'], $index + 1));
                }

                $words = set_combined_word($combined_word, $words, $index);
            }
        }
    }

    return $words;
}

/**
 * Translated the words of the text excluding those with a confirmed translation
 *
 * @param array $words   the words of the text
 * @param array $lexicon the lexicon
 * @return array         the words of the text updated with translations
 */
function translate_words($words, $lexicon)
{
    foreach($words as &$word) {
        $original = $word['original_word_lower_case'];
        unset($word['original_word_lower_case']);

        if (empty($word['translated-word']) and isset($lexicon[$original])) {
            $word['translated-word'] = $lexicon[$original]['translated-word'];
            $word['not-confirmed'] = '?';
        }

        if (isset($lexicon[$original]['translations'])) {
            $word['translations'] = $lexicon[$original]['translations'];
        }
    }

    return $words;
}
