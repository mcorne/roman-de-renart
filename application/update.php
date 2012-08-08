<?php
/**
 * Roman de Renart
 *
 * Command line to update episodes, blog messages, or the table of contents
 *
 * @author    Michel Corne <mcorne@yahoo.com>
 * @copyright 2012 Michel Corne
 * @license   http://www.opensource.org/licenses/gpl-3.0.html GNU GPL v3
 * @link      http://roman-de-renart.blogspot.com/
 */

require_once 'Blog.php';
require_once 'Text.php';

/**
 * The command help
 */
$help =
'Usage:
-l              Display the list of episodes.
-n number,...   Optional comma separated list of numbers of episodes.
                By default, all episodes are updated.
                Mandatory in logged off mode, only one number allowed.
                999 is the number of the episode being translated.
-p password     Blogger account Password.
-t              Update table of contents.
-u name         Blogger user/email/login name.

Notes:
In logged on mode, episodes are created/updated in the data directory.
In logged off mode, the episode is stored in data/temp.html.

Examples:
# update episode(s) in Blogger
update -u abc -p xyz

# update episodes 10 and 11 in Blogger
update -u abc -p xyz -n 10,11

# updates episode 10 in data/temp.html
update -n 10
';

try {
    if (! $options = getopt("hln:p:tu:")) {
        throw new Exception('invalid or missing option(s)');
    }

    if (isset($options['h'])) {
        // displays the command usage (help)
        exit($help);
    }

    $text = new Text();
    $episodes = $text->parseFile();

    if (isset($options['l'])) {
        // displays the list of episodes
        echo $text->listEpisodes($episodes);

    } else if (isset($options['t'])) {
        // creates the table of contents
        $html = $text->makeTableOfContents($episodes);
        echo $text->saveTableOfContents($html);

    } else if (isset($options['u']) and isset($options['p'])) {
        // this is the logged on mode, updates one more episodes in Blogger and saves them in local files
        if (isset($options['n'])) {
            $numbers = explode(',', $options['n']);
            $episodes = array_intersect_key($episodes, array_flip($numbers));
        }

        $htmls = array_map(array($text, 'makeMessage'), $episodes);
        $blog = new Blog($options['u'], $options['p'], 'Le Roman de Renart');
        echo $text->saveMessages($htmls, $episodes, $blog);

    } else if (! isset($options['u']) and ! isset($options['p'])) {
        // this is the logged off mode, makes an episode HTML and saves the content in data/temp.html
        if (empty($options['n'])) {
            throw new Exception('option -n is mandatory in logged off mode');
        }
        $number = $options['n'];

        if (! isset($episodes[$number])) {
            throw new Exception('invalid episode number');
        }

        $html = $text->makeMessage($episodes[$number]);
        echo $text->saveTempMessage($html, $number);

    } else {
        throw new Exception('missing user name or password');
    }

} catch(Exception $e) {
    echo($e->getMessage());
}
