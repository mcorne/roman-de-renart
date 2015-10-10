#!/usr/bin/php
<?php
/**
 * Command line to authorize the publishing of blog messages
 *
 * @author    Michel Corne <mcorne@yahoo.com>
 * @copyright 2015 Michel Corne
 * @license   http://www.opensource.org/licenses/gpl-3.0.html GNU GPL v3
 */

require_once 'Blog.php';

/**
 * The command help
 */
$help =
'Usage:
-c code         Authorization code.
-g              Get the authorization URL.
-p password     Blogger account Password.
-u name         Blogger user/email/login name.

Examples:
# get the authorization URL
authorize -u abc -p xyz -g
# then enter the URL in a browser to get the authorization code


# get the authorization tokens to work for an hour or so
authorize -u abc -p xyz -c uvw
# the tokens are saved in the computer, they are needed to publish episodes
';

try {
    if (! $options = getopt("hc:gp:u:")) {
        throw new Exception('Invalid or missing option(s)');
    }

    if (isset($options['h'])) {
        // displays the command usage (help)
        exit($help);
    }

    if (empty($options['u']) or empty($options['p'])) {
        throw new Exception('Missing user name or password');
    }

    $blog = new Blog($options['u'], $options['p']);

    if (isset($options['c'])) {
        $blog->authorize($options['c']);
        echo "You are authorized to publish for an hour or so";
    } elseif (isset($options['g'])) {
        $credentials = $blog->getCredentials();
        echo "\n" . $credentials['auth_screen_url'] . "\n";
    } else {
        throw new Exception('Option c or g missing');
    }
} catch (Exception $e) {
    echo($e->getMessage());
}
