<?php
/**
 * Roman de Renart
 *
 * Processing of the text
 *
 * @author    Michel Corne <mcorne@yahoo.com>
 * @copyright 2012 Michel Corne
 * @license   http://www.opensource.org/licenses/gpl-3.0.html GNU GPL v3
 * @link      http://roman-de-renart.blogspot.com/
 */

require_once 'Blog.php';

class Text
{
    /**
     * Columns headers of the CSV file containing the text
     *
     * The order and names of the columns must be kept in synch with the column headers in data/verses.csv.
     *
     * @var array
     */
    public $columnHeaders = array(
        'verse-number',
        'original-verse',
        'translated-verse',
        'original-verse-to-confirm',
        'translated-verse-to-confirm',
        'episode-number',
        'is-last-verse',
        'top-margin',
        'indentation',
        'story-title',
        'episode-title',
        'url',
        'image-src',
        'image-href',
        'section-original-title',
        'section-translated-title',
        'vol-1-fixes',
    );

    /**
     * Default titles and links of the episode being translated
     *
     * @var array
     */
    public $episodeBeingTranslated = array(
        'episode-number'          => 999,
        'episode-title'           => 'Épisode en cours de traduction',
        'image-href'              => 'https://picasaweb.google.com/lh/photo/ei2R58YpwqJaUOQJc4RbTNMTjNZETYmyPJy0liipFm0?feat=directlink',
        'image-src'               => 'https://lh3.googleusercontent.com/-2k2gqmMVAro/SoUkxGZ5YOI/AAAAAAAABgM/gWBO4GYkYxg/s288/99a-construction.jpg',
        'translation-in-progress' => true,
        'url'                     => 'http://roman-de-renart.blogspot.com/2009/02/episode-en-cours-de-traduction.html',
    );

    /**
     * Constructor
     */
    public function __construct()
    {
        date_default_timezone_set('UTC');
    }

    /**
     * Creates the HTML translation in progress intro for the episode being translated
     *
     * @return string The HTML intro
     */
    public function addTranslationInProgressIntro()
    {
        $template = $this->loadTemplate('translation-in-progress-intro.html');
        $date = $this->makeMessageDate();

        return sprintf($template, $date);
    }

    /**
     * Converts a UTF-8 string into the output encoding
     *
     * Only applied in CGI mode.
     * Uses CP850 for MS-DOS.
     *
     * @param  string $string The string to convert
     * @return string         The converted string
     */
    public function convertUtf8ToOutputEncoding($string)
    {
        if (PHP_SAPI == 'cli') {
            $encoding = stripos(PHP_OS, 'win') !== false ? 'CP850' : iconv_get_encoding('output_encoding');
            $string = iconv('UTF-8', $encoding, $string);
        }

        return $string;
    }


    /**
     * Returns the URL of the gothic letter of the first letter of a verse
     *
     * @param string  $verse         The verse
     * @param int     $episodeNumber The episode number
     * @throws Exception
     * @return array                 The URL of the gothic letter, and the verse excluding the first letter
     */
    public function getGothicLetter($verse, $episodeNumber)
    {
        static $gothicLetters;

        if (! isset($gothicLetters)) {
            $gothicLetters = require __DIR__ . '/gothic-letters.php';
        }

        // removes punctuation characters before the first character
        $verse = preg_replace('~^(« |— |“)~', '', $verse);

        $firstLetter = $verse[0];
        if (! isset($gothicLetters[$firstLetter])) {
            throw new Exception('no gothic letter for episode: ' . $episodeNumber);
        }

        $gothicLetter = $gothicLetters[$firstLetter];
        $verse = substr($verse, 1);

        return array($gothicLetter, $verse);

    }

    /**
     * Returns the list of the episodes in a readable output format
     *
     * @param array $episodes The episodes details
     * @return string         The list of episodes
     */
    public function listEpisodes($episodes)
    {
        $list = array();

        foreach($episodes as $episode) {
            $title = $this->setTitle($episode);
            $list[] = sprintf('%2s : %s', $episode['episode-number'], $title);
        }

        $list = implode("\n", $list);

        return $this->convertUtf8ToOutputEncoding($list);
    }

    /**
     * Loads an HTML template
     *
     * @param string $basename The base name of the template
     * @return string          The HTML content of the template excluding the docblock
     */
    public function loadTemplate($basename)
    {
        $html = $this->readFile(__DIR__ . "/../templates/$basename");
        // removes docblock
        $html = preg_replace('~^<!--.+?-->\s*~s', '', $html);

        return trim($html);
    }

    /**
     * Returns the link to an episode
     *
     * @param array $episode The episode details
     * @return string        The link to the episode
     */
    public function makeLinkToEpisode($episode)
    {
        $title = $this->setTitle($episode);

        return sprintf('href="%s" title="%s"', $episode['url'],  $title);
    }

    /**
     * Creates the HTML of a blog message containing an episode
     *
     * @param array $episode The episode details
     * @return string        The HTML content of the episode
     */
    public function makeMessage($episode)
    {
        static $template;

        if (! isset($template)) {
            $template = $this->loadTemplate('episode.html');
        }

        $translationInProgressIntro = empty($episode['translation-in-progress'])? '' : $this->addTranslationInProgressIntro();

        $linkToPreviousEpisode = isset($episode['previous-episode'])? $this->makeLinkToEpisode($episode['previous-episode']) : '';
        $linkToNextEpisode     = isset($episode['next-episode'])?     $this->makeLinkToEpisode($episode['next-episode'])     : '';

        empty($episode['top-margin']) and $episode['top-margin'] = 3;
        $topMargin = str_repeat('<br />', $episode['top-margin']);

        $firstTranslatedVerse = array_shift($episode['translated-text']);
        $firstOriginalVerse   = array_shift($episode['original-text']);
        array_shift($episode['verse-numbers']);

        list($gothicLetter, $firstOriginalVerse) = $this->getGothicLetter($firstOriginalVerse, $episode['episode-number']);

        $translationNotes = isset($episode['translation-notes'])? $this->makeTranslationNotes($episode['translation-notes']) : '';

        $html = sprintf($template,
            $this->setTitle($episode),
            date('c'), // generation date
            date('Y'), // copyright year
            $translationInProgressIntro,
            $linkToPreviousEpisode,
            $linkToNextEpisode,
            $episode['image-href'],
            $episode['image-src'],
            $topMargin,
            $firstTranslatedVerse,
            $gothicLetter,
            $topMargin,
            $firstOriginalVerse,
            implode("<br />\n", $episode['translated-text']),
            implode("<br />\n", $episode['verse-numbers']),
            implode("<br />\n", $episode['original-text']),
            $episode['section-translated-title'],
            $episode['section-original-title'],
            $linkToPreviousEpisode,
            $linkToNextEpisode,
            $translationNotes
        );

        return $html;
    }

    /**
     * Formats the current date as in a blog message
     *
     * @return string The date
     */
    public function makeMessageDate()
    {
        setlocale(LC_TIME, 'fr_FR', 'fra');
        $format = stripos(PHP_OS, 'win') !== false ? '%A %#d %B %Y' : '%A %e %B %Y';
        $date = strftime($format);

        return mb_convert_encoding($date, 'UTF-8');
    }

    /**
     * Creates the HTML of the table of contents aka list of episodes
     *
     * The HTML content of the table of contents will have to be loaded manually in the corresponding blog widget.
     *
     * @param array $episodes The episodes details
     * @return string         The table of contents
     */
    public function makeTableOfContents($episodes)
    {
        $optgroupBeginPattern = '    <optgroup label="%s">';
        $optionPattern        = '      <option value="%1$s" title="%2$s - %3$s (%4$d)" />%3$s';
        $optgroupEndPattern   = '    </optgroup>';

        $options = array();
        $prev_episode = null;
        $lastEpisodePathName = null;

        foreach($episodes as $episode) {
            if (empty($prev_episode) or $episode['story-title'] != $prev_episode['story-title']) {
                // this is the beginning of a story
                // adds the optgroup closing tag of the previous story (except for the first story)
                empty($options) or $options[] = $optgroupEndPattern;
                // adds the optgroup opening tag of the current story
                $options[] = sprintf($optgroupBeginPattern, $episode['story-title']);
            }

            if (! empty($episode['episode-title'])) {
                // this is the begining of a story
                list(,,, $pathname) = explode('/', $episode['url'], 4);
                $pathname = "/$pathname";
                // adds the episode title (select option)
                $options[] = sprintf($optionPattern, $pathname, $episode['story-title'], $episode['episode-title'], $episode['episode-number']);
                // captures the pathname of the last episode exlcuding the episode being translated if any
                // this is used to populate a hidden HTML element used by a javascript function
                // to set the select option when the blog is open without a pathname
                empty($episode['translation-in-progress']) and $lastEpisodePathName = $pathname;
            }

            $prev_episode = $episode;
        }

        $html = sprintf(
            $this->loadTemplate('table-of-contents.html'),
            date('c'), // generation date/time
            date('Y'), // copyright year
            $lastEpisodePathName,
            implode("\n", $options)
        );

        return $html;
    }

    /**
     * Creates the HTML of a translation note
     *
     * @param array $note The note details
     * @return string     The HTML content of a note
     */
    public function makeTranslationNote($note)
    {
        list($verseNumber, $originalPart, $translatedPart) = $note;

        return sprintf('      <li>%s&nbsp;: «&nbsp;%s&nbsp;» = «&nbsp;%s&nbsp;»</li>', $verseNumber, $originalPart, $translatedPart);
    }

    /**
     * Creates the HTML of the translations notes
     *
     * @param array $notes The notes details
     * @return string      The translation notes
     */
    public function makeTranslationNotes($notes)
    {
        static $template;

        if (! isset($template)) {
            $template = $this->loadTemplate('translation-notes.html');
        }

        $notes = array_map(array($this, 'makeTranslationNote'), $notes);
        $notes = implode("\n", $notes);

        return sprintf($template, $notes);
    }

    /**
     * Parses and validates the episode details
     *
     * @param array $episode         The episode details
     * @param bool  $beingTranslated True if this is the episode being translated, false otherwise
     * @param array $prevEpisode     The previous episode details
     * @param int   $lineNumber      The line number in the file being parsed
     * @throws Exception
     * @return array                 The episode details,
     *                               and the possibly updated flag indicating if the episode is being translated or not
     */
    public function parseEpisode($episode, $beingTranslated, $prevEpisode, $lineNumber)
    {
        if ($beingTranslated) {
            throw new Exception("not expecting new episode at this point, line: $lineNumber");
        }

        if (empty($episode['url'])) {
            // there is no episode URL yet, this is the episode currently being translated
            // note: the episode currently being translated MUST NOT have a URL
            $beingTranslated = true;
            $episode = $this->episodeBeingTranslated + $episode;

        } else {
            list($year) = explode('/', $episode['url']);

            if (! ctype_digit($year)) {
                throw new Exception("bad URL, line: $lineNumber");
            }

            $episode['url'] = 'http://roman-de-renart.blogspot.com/' . $episode['url'];

            if (empty($episode['episode-title'])) {
                throw new Exception("missing episode title, line: $lineNumber");
            }
            if (empty($episode['image-src'])) {
                throw new Exception("missing image source, line: $lineNumber");
            }
            if (empty($episode['image-href'])) {
                throw new Exception("missing image href, line: $lineNumber");
            }
            if (! empty($prevEpisode) and $episode['episode-number'] != ($prevEpisode['episode-number'] + 1)) {
                throw new Exception("bad episode number, line: $lineNumber");
            }
        }

        if (empty($prevEpisode)) {
            // this is the first episode, expecting titles
            if (empty($episode['story-title'])) {
                throw new Exception("missing story title, line: $lineNumber");
            }
            if (empty($episode['section-original-title'])) {
                throw new Exception("missing section original title, line: $lineNumber");
            }
            if (empty($episode['section-translated-title'])) {
                throw new Exception("missing section translated title, line: $lineNumber");
            }

        } else {
            // this is a following episode, defaults titles as in previous episode
            empty($episode['story-title'])              and $episode['story-title']              = $prevEpisode['story-title'];
            empty($episode['section-original-title'])   and $episode['section-original-title']   = $prevEpisode['section-original-title'];
            empty($episode['section-translated-title']) and $episode['section-translated-title'] = $prevEpisode['section-translated-title'];
        }

        return array($episode, $beingTranslated);
    }

    /**
     * Parses the CVS file containing the text
     *
     * @return array The episodes details
     */
    public function parseFile()
    {
        $file = '/../data/verses.csv';

        if (! $lines = @file(__DIR__ . $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) {
            throw new Exception("cannot read file: $file");
        }

        // skips the column headers
        $lines = array_slice($lines, 1);

        $episodes = array();
        $lineNumber = 2;
        $episodeBegining = true;
        $beingTranslated = false;
        $prevEpisode = null;

        foreach($lines as $line) {
            $line = $this->parseLine($line);

            if ($episodeBegining) {
                // this the  begining of an episode
                $episode = $line;

                if (! is_numeric($episode['episode-number'])) {
                    // there is no more episode translated or being translated
                    // note: the first verse of an episode MUST HAVE an episode number
                    $episodeBegining = false;
                    break;
                }

                list($episode, $beingTranslated) = $this->parseEpisode($episode, $beingTranslated, $prevEpisode, $lineNumber);
                $episodeBegining = false;
            }

            $episode = $this->parseVerse($line, $episode, $beingTranslated, $lineNumber);

            if (! empty($line['is-last-verse'])) {
                // this is the last verse of the episode
                if ($episodeCount = count($episodes)) {
                    // links previous episode to current episode
                    $episodes[$episodeCount - 1]['next-episode'] = $episode;
                    // links current episode to previous episode
                    $episode['previous-episode'] = $prevEpisode;
                }

                $number = $episode['episode-number'];
                $episodes[$number] = $episode;
                $prevEpisode = $episode;
                $episodeBegining = true;
            }

            $lineNumber++;
        }

        if ($episodeBegining) {
            throw new Exception("missing episode end, line: $lineNumber");
        }

        return $episodes;
    }

    /**
     * Parses a line of the CSV file containing the text
     *
     * @param string $line The line to parse
     * @return array       The line details with the column headers as keys
     */
    public function parseLine($line)
    {
        // splits the line by tabs
        $cells = explode("\t", $line);

        foreach($cells as &$cell) {
            // trims the enclosing quotes
            $cell = trim($cell, '" ');
            // fixes escaped quotes
            $cell = str_replace('""', '"', $cell);
        }

        return array_combine($this->columnHeaders, $cells);
    }

    /**
     * Parses and validates a verse
     *
     * @param string $line            The line containing the verse number, original text, translated text,
     * @param array  $episode         The episode details
     * @param bool   $beingTranslated True if this is the episode being translated, false otherwise
     * @param int    $lineNumber      The line number in the file being parsed
     * @throws Exception
     * @return array                  The episode details
     */
    public function parseVerse($line, $episode, $beingTranslated, $lineNumber)
    {
        if (empty($line['verse-number'])) {
            throw new Exception("missing verse number, line: $lineNumber");
        }

        if (empty($line['original-verse'])) {
            throw new Exception("missing original verse, line: $lineNumber");
        }

        if (! $beingTranslated and empty($line['translated-verse'])) {
            throw new Exception("missing translated verse, line: $lineNumber");
        }

        // collects every 4 verse numbers
        $episode['verse-numbers'][] = $line['verse-number'] % 4 ? '' : $line['verse-number'];
        if (count($episode['verse-numbers']) > 200) {
            throw new Exception("too many verses, line: $lineNumber");
        }

        if (! empty($line['indentation']) and $line['episode-number'] === '') {
            // adds indentation
            $indentation = '<span class="rdr-indentation">&nbsp;</span>';
        } else {
            $indentation = '';
        }

        // collects original and translated text
        $episode['original-text'][] = $indentation . $line['original-verse'];
        $episode['translated-text'][] = $line['translated-verse'];

        if (! empty($line['original-verse-to-confirm']) and ! empty($line['translated-verse-to-confirm'])) {
            // collects text part to confirm (to display as translation note)
            $episode['translation-notes'][] = array($line['verse-number'], $line['original-verse-to-confirm'], $line['translated-verse-to-confirm']);

        } else if (! empty($line['original-verse-to-confirm']) or ! empty($line['translated-verse-to-confirm'])) {
            throw new Exception("missing original or translation part to confirm, line: $lineNumber");
        }

        return $episode;
    }

    /**
     * Reads a file
     *
     * @param string $file The file name
     * @throws Exception
     * @return string      The file content
     */
    public function readFile($file)
    {
        if (! $content = @file_get_contents($file)) {
            throw new Exception("cannot read file: $file");
        }

        return $content;
    }

    /**
     * Removes the generated date from a docblock for comparison purposes
     *
     * @param string $html The HTML content of an episode
     * @return string      The HTML content without the generated date
     */
    public function removeGeneratedDate($html)
    {
        // removes the date in the episode being translated
        $html = preg_replace('~^ +<input id="rdr-translation-in-progress-date" type="hidden" value=".+?"/>$~m', '', $html);
        $html = preg_replace('~^\s*Generated.+?$~m', '', $html);
        $html = preg_replace('~^\s*@copyright.+?$~m', '', $html);

        return $html;
    }

    /**
     * Saves the HTML content of the copyright
     *
     * @param string $html The HTML content of the copyright
     * @return string      The result of the action to be displayed to the output
     */
    public function saveCopyright($html)
    {
        $file = 'widgets/copyright.html';
        $path = __DIR__ . "/../$file";
        $prevHtml = $this->readFile($path);

        if ($this->removeGeneratedDate($html) == $this->removeGeneratedDate($prevHtml)) {
            $result[] = 'The copyright is already up to date.';
            $result[] = "No changes were made to $file.";

        } else {
            $this->writeFile($path, $html);
            $result[] = 'The copyright was updated successfully.';
            $result[] = "Please, COPY & PASTE the content of $file";
            $result[] = 'into the corresponding blog widget.';
        }

        return implode("\n", $result);
    }

    /**
     * Saves an episode in a blog message (publishes an episode)
     *
     * The episode is also saved in a file.
     * The blog message is published only if the HTML content of the episode has changed.
     *
     * @param string $html    The HTML content of the episode
     * @param array  $episode The episode details
     * @param Blog   $blog    The Blog object
     * @param int    $number  The episode number
     * @return boolean        True if the episode has changed and was saved in the blog, false otherwise
     */
    public function saveMessage($html, $episode, Blog $blog, $number)
    {
        $url = $episode['url'];
        $file = __DIR__ . "/../messages/$number-" . basename($url);
        $prevHtml = file_exists($file)? $this->readFile($file) : null;

        if ($this->removeGeneratedDate($html) != $this->removeGeneratedDate($prevHtml)) {
            // the episode is different from the currently saved version
            echo "$number ";

            $title = $this->setTitle($episode);
            // removes line breaks because Blogger replaces them with <br> for some reason which screws up the display
            // although messages are set to use HTML as it is and to use <br> for line feeds
            $content = str_replace("\n", ' ', $html);
            $blog->savePost($title, $content, $url, $episode['story-title']);
            $this->writeFile($file, $html);
            $isPublished = true;

        } else {
            $isPublished = false;
        }

        return $isPublished;
    }

    /**
     * Saves the episodes in the blog (publishes the episodes)
     *
     * @param array $htmls    The HTML contents of the episodes
     * @param array $episodes The episodes details
     * @param Blog  $blog     The BLog object
     * @return string         The result of the action to be displayed to the output
     */
    public function saveMessages($htmls, $episodes, Blog $blog)
    {
        $publishedCount = 0;

        foreach($htmls as $number => $html) {
            $publishedCount += $this->saveMessage($html, $episodes[$number], $blog, $number);
        }

        if ($publishedCount == 0) {
            $result = 'No episode has changed, no episode was published.';
        } else if ($publishedCount == 1) {
            $result = "\n" . 'The episode has changed, the episode was published successfully.';
        } else {
            $result = "\n" . "The $publishedCount episodes were published successfully.";
        }

        return $result;
    }

    /**
     * Saves the HTML content of an episode into a temporary file
     *
     * The episode is saved in messages/temp.html that is used for checking changes before commiting them to the blog.
     *
     * @param string $html   The HTML content of the episode
     * @param int    $number The episode number
     * @return string        The result of the action to be displayed to the output
     */
    public function saveTempMessage($html, $number)
    {
        $temp = 'messages/temp.html';
        $file = __DIR__ . "/../$temp";
        $prevHtml = file_exists($file)? $this->readFile($file) : null;

        if ($this->removeGeneratedDate($html) == $this->removeGeneratedDate($prevHtml)) {
            $result = "The episode is already up to date in $temp.";

        } else {
            $this->writeFile(__DIR__ . "/../$temp", $html);
            $result = "The episode was saved successfully in $temp.";
        }

        return $result;
    }

    /**
     * Saves the HTML content of a widget
     *
     * @param  string $html     The HTML content of the widget
     * @param  string $basename The file base name
     * @param  string $widget   The widget name
     * @return string      The result of the action to be displayed to the output
     */
    public function saveWidget($html, $basename, $widget)
    {
        $file = "widgets/$basename";
        $path = __DIR__ . "/../$file";
        $prevHtml = file_exists($path)? $this->readFile($path) : null;

        if ($this->removeGeneratedDate($html) == $this->removeGeneratedDate($prevHtml)) {
            $result[] = "The $widget is already up to date.";
            $result[] = "No changes were made to $file.";

        } else {
            $this->writeFile($path, $html);
            $result[] = "The $widget was updated successfully.";
            $result[] = "Please, COPY & PASTE the content of $file";
            $result[] = 'into the corresponding blog widget.';
        }

        return implode("\n", $result);
    }

    /**
     * Sets the title of the episode
     *
     * The title is made of the story title and the episode title.
     *
     * @param array $episode The episode details
     * @return string        The episode title
     */
    public function setTitle($episode)
    {
        return sprintf('%s - %s', $episode['story-title'],  $episode['episode-title']);
    }

    /**
     * Updates the HTML of the copyright widget with the current year
     *
     * The HTML content of the copyright will have to be loaded manually in the corresponding blog widget.
     *
     * @return string The copyright
     */
    public function updateCopyright()
    {
        $html = $this->readFile(__DIR__ . '/../widgets/copyright.html');
        $year = date('Y');

        return preg_replace('~<span style="white-space:nowrap">2009-\d+</span>~', "<span style=\"white-space:nowrap\">2009-$year</span>", $html);
    }

    /**
     * Updates the HTML of the introduction widget with the number of the last translated verse
     *
     * The HTML content of the introduction will have to be loaded manually in the corresponding blog widget.
     *
     * @param array $episodes  The episodes details
     * @return string          The introduction
     */
    public function updateIntroduction($episodes)
    {
        $html = $this->readFile(__DIR__ . '/../widgets/introduction.html');
        $lastTranslatedVerseNumber = $episodes[999]['verse-number'] - 1;

        return preg_replace('~<span id="mc_fait">\d+</span>~', "<span id=\"mc_fait\">$lastTranslatedVerseNumber</span>", $html);
    }

    /**
     * Writes into a file
     *
     * @param string $file    The file name
     * @param string $content The file content
     * @throws Exception
     */
    public function writeFile($file, $content)
    {
        if (! @file_put_contents($file, $content)) {
            throw new Exception("cannot write file: $file");
        }
    }
}