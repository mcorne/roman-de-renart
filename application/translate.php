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

/**
 * The command help
 */
$help =
'Usage:
-t        Translate words.
-w        Add words.
';


try {
    if (! $options = getopt("htw")) {
        throw new Exception('invalid or missing option(s)');
    }

    if (isset($options['h'])) {
        // displays the command usage (help)
        exit($help);
    }

    foreach(array_keys($options) as $option) {
        switch($option) {
            case 't':
                require_once 'translate-words.php';
                exec_translate_words();
                break;

            case 'w':
                require_once 'get-words-to-translate.php';
                exec_get_words_to_translate();
                break;
        }
    }

} catch(Exception $e) {
    echo($e->getMessage());
}
