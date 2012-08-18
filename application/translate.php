<?php
/**
 * Roman de Renart
 *
 * Command line to translate verses
 *
 * @author    Michel Corne <mcorne@yahoo.com>
 * @copyright 2012 Michel Corne
 * @license   http://www.opensource.org/licenses/gpl-3.0.html GNU GPL v3
 * @link      http://roman-de-renart.blogspot.com/
 */

require_once 'common.php';

define('OPTION_A', '-w -t');

/**
 * The command help
 */
$help =
'Usage:
-a    Options: %1$s.
-t    Translate the words.
-u    Update the verses with the translation.
-w    Add the words of the verses to translate.
';


try {
    if (! $options = getopt("hatuw")) {
        throw new Exception('invalid or missing option(s)');
    }

    if (isset($options['h'])) {
        // displays the command usage (help)
        exit(sprintf($help, OPTION_A));
    }

    if (isset($options['a'])) {
        // this is the (combined) option A, adds the options
        preg_match_all('~\w~', (string)OPTION_A, $matches);
        $options += array_fill_keys($matches[0], false);
        unset($options['a']);
    }

    foreach(array_keys($options) as $option) {
        switch($option) {
            case 't':
                require_once 'translate-words.php';
                exec_translate_words();
                break;

            case 'u':
                require_once 'update-verses-translation.php';
                exec_update_verses_translation();
                break;

            case 'w':
                require_once 'add-words-to-translate.php';
                exec_add_words_to_translate();
                break;
        }
    }

} catch(Exception $e) {
    echo "\nerror! " . $e->getMessage();
}
