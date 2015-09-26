#!php
<?php
/**
 * Roman de Renart
 *
 * Command line to publish episodes or update the table of contents
 *
 * @author    Michel Corne <mcorne@yahoo.com>
 * @copyright 2015 Michel Corne
 * @license   http://www.opensource.org/licenses/gpl-3.0.html GNU GPL v3
 * @link      http://roman-de-renart.blogspot.com/
 */

require_once 'Blog.php';
require_once 'Text.php';

define('OPTION_A', '-c -i -t');

/**
 * The command help
 */
$help =
'Usage:
-a              Options: %1$s.
-c              Update the copyright widget with the current year.
-i              Update the introduction widget with the number of
                the last translated verse.
-l              Display the list of episodes.
-n number,...   Optional comma separated list of numbers of episodes.
                By default, all episodes are processed.
                Mandatory in logged off mode, only one number allowed.
                999 is the number of the episode being translated.
-p password     Blogger account Password.
-t              Update the table of contents widget.
-u name         Blogger user/email/login name.

Notes:
In logged on mode, the episode HTML is created/updated in the messages directory.
In logged off mode, the episode HTML is stored in messages/temp.html.

You need to be authorized to be able to publish.
Run "authorize -h" for more information.

Examples:
# publish episode(s) in Blogger
publish -u abc -p xyz

# publish episodes 10 and 11 in Blogger
publish -u abc -p xyz -n 10,11

# create/update episode 10 in messages/temp.html
publish -n 10
';

try {
    if (! $options = getopt("haciln:p:tu:")) {
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

    $text = new Text();
    $episodes = $text->parseFile();

    if (isset($options['l'])) {
        // displays the list of episodes
        echo $text->listEpisodes($episodes);
        exit;
    }

    if (isset($options['u']) and isset($options['p'])) {
        // this is the logged on mode, publishes one more episodes in Blogger and saves them in local files
        if (isset($options['n'])) {
            $numbers = explode(',', $options['n']);
            $episodes = array_intersect_key($episodes, array_flip($numbers));
        }

        $htmls = array_map(array($text, 'makeMessage'), $episodes);
        $blog = new Blog($options['u'], $options['p']);
        echo "\n" . $text->saveMessages($htmls, $episodes, $blog) . "\n";
    } elseif (isset($options['u']) or isset($options['p'])) {
        throw new Exception('missing user name or password');
    } elseif (isset($options['n'])) {
        // this is the logged off mode, makes an episode HTML and saves the content in messages/temp.html
        $number = $options['n'];

        if (! isset($episodes[$number])) {
            throw new Exception('invalid episode number');
        }

        $html = $text->makeMessage($episodes[$number]);
        echo "\n" . $text->saveTempMessage($html, $number) . "\n";
    }

    if (isset($options['c'])) {
        // updates the copyright
        $html = $text->updateCopyright();
        echo "\n" . $text->saveWidget($html, 'copyright.html', 'copyright') . "\n";
    }

    if (isset($options['i'])) {
        // updates the introduction with the number of the last translated verse
        $html = $text->updateIntroduction($episodes);
        echo "\n" . $text->saveWidget($html, 'introduction.html', 'introduction') . "\n";
    }

    if (isset($options['t'])) {
        // creates the table of contents
        $html = $text->makeTableOfContents($episodes);
        echo "\n" . $text->saveWidget($html, 'table-of-contents.html', 'table of contents') . "\n";
    }
} catch (Exception $e) {
    echo($e->getMessage());
}
