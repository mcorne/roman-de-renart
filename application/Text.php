<?php
/**
 * Roman de Renart.
 *
 * Processing of the text
 *
 * @author    Michel Corne <mcorne@yahoo.com>
 * @copyright 2012 Michel Corne
 * @license   http://www.opensource.org/licenses/gpl-3.0.html GNU GPL v3
 *
 * @link      https://roman-de-renart.blogspot.com/
 */
require_once 'Blog.php';

class Text
{
    /**
     * Columns headers of the CSV file containing the text.
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
        'vol-1-fixes (fhs)',
        'vol-2-fixes (mc)',
    );

    /**
     * Default titles and links of the episode being translated.
     *
     * @var array
     */
    public $episodeBeingTranslated = array(
        'episode-number'          => 999,
        'episode-title'           => 'Épisode en cours de traduction',
        'image-src'               => 'https://3.bp.blogspot.com/-Jf4A6NUdnDU/V-_0BpPycPI/AAAAAAAAH2I/yPrVTGIdeA4IDCzcoMod5h81cvIHC-X2QCLcB/s320/99a-construction.jpg',
        'translation-in-progress' => true,
        'url'                     => 'https://roman-de-renart.blogspot.com/2009/02/episode-en-cours-de-traduction.html',
    );

    /**
     * Constructor.
     */
    public function __construct()
    {
        date_default_timezone_set('UTC');
    }

    /**
     * Creates the HTML translation in progress intro for the episode being translated.
     *
     * @return string The HTML intro
     */
    public function addTranslationInProgressIntro()
    {
        $template = $this->loadTemplate('translation-in-progress-intro.html');
        $date     = $this->makeMessageDate();

        return sprintf($template, $date);
    }

    /**
     * Converts a UTF-8 string into the output encoding.
     *
     * Only applied in CGI mode.
     * Uses CP850 for MS-DOS.
     *
     * @param string $string The string to convert
     *
     * @return string The converted string
     */
    public function convertUtf8ToOutputEncoding($string)
    {
        if (PHP_SAPI == 'cli') {
            $encoding = stripos(PHP_OS, 'win') !== false ? 'CP850' : iconv_get_encoding('output_encoding');
            $string   = iconv('UTF-8', $encoding, $string);
        }

        return $string;
    }

    /**
     *
     * @staticvar array $keyword_patterns
     * @param string $title
     * @param array $lines
     * @return array
     */
    public function extractKeywords($title, $lines)
    {
        static $keyword_patterns;

        if (! isset($keyword_patterns)) {
            $keyword_patterns = require __DIR__ . "/../data/keywords.php";
        }

        $text = $title . ' ' . implode(' ', $lines);

        $keywords = [];

        foreach ($keyword_patterns as $keyword => $pattern) {
            if (preg_match($pattern, $text)) {
                $keywords[] = $keyword;
            }
        }

        // sorting seems to be necessary to get a maximum number of labels posted !
        sort($keywords);
        // Blogger seems to have a limitation on the number of labels that can be posted per message
        // note that only a few messages are affected
        $keywords = array_slice($keywords, 0, 19);

        return $keywords;
    }

    /**
     * Fixes a verse.
     *
     * @param string $verse
     *
     * @return string
     */
    public function fixVerse($verse)
    {
        $verse = preg_replace('~ ([?;!:»])~u', '&nbsp;$1', $verse);
        $verse = preg_replace('~([«]) ~u', '$1&nbsp;', $verse);

        return $verse;
    }

    /**
     * Returns the URL of the gothic letter of the first letter of a verse.
     *
     * @param string $verse         The verse
     * @param int    $episodeNumber The episode number
     *
     * @throws Exception
     *
     * @return array The URL of the gothic letter, and the verse excluding the first letter
     */
    public function getGothicLetter($verse, $episodeNumber)
    {
        static $gothicLetters;

        if (! isset($gothicLetters)) {
            $gothicLetters = require __DIR__ . '/gothic-letters.php';
        }

        // removes punctuation characters before the first character
        $verse = preg_replace('~^(« |— |“|\.)~', '', $verse);

        $latinLetter = mb_strtoupper($verse[0], 'UTF-8');

        if (! isset($gothicLetters[$latinLetter])) {
            throw new Exception("no gothic letter for letter $latinLetter in episode: $episodeNumber");
        }

        $gothicLetter = $gothicLetters[$latinLetter];
        $verse        = substr($verse, 1);

        return array($gothicLetter, $latinLetter, $verse);
    }

    /**
     * Returns the list of the episodes in a readable output format.
     *
     * @param array $episodes The episodes details
     *
     * @return string The list of episodes
     */
    public function listEpisodes($episodes)
    {
        $list = array();

        foreach ($episodes as $episode) {
            $title  = $this->setTitle($episode);
            $list[] = sprintf('%2s : %s', $episode['episode-number'], $title);
        }

        $list = implode("\n", $list);

        return $this->convertUtf8ToOutputEncoding($list);
    }

    /**
     * Loads an HTML template.
     *
     * @param string $basename The base name of the template
     *
     * @return string The HTML content of the template excluding the docblock
     */
    public function loadTemplate($basename)
    {
        $html = $this->readFile(__DIR__ . "/../templates/$basename");
        // removes template (first) docblock
        $html = preg_replace('~^<!--.+?-->\s*~s', '', $html);

        return trim($html);
    }

    /**
     * Returns the link to an episode.
     *
     * @param array $episode The episode details
     *
     * @return string The link to the episode
     */
    public function makeLinkToEpisode($episode)
    {
        $title = $this->setTitle($episode);

        return sprintf('href="%s" title="%s"', $episode['url'],  $title);
    }

    /**
     * Creates the HTML of a blog message containing an episode.
     *
     * @param array $episode The episode details
     *
     * @return string The HTML content of the episode
     */
    public function makeMessage($episode)
    {
        static $template;

        if (! isset($template)) {
            $template = $this->loadTemplate('episode.html');
        }

        $translationInProgressIntro = empty($episode['translation-in-progress']) ? '' : $this->addTranslationInProgressIntro();

        $linkToPreviousEpisode = isset($episode['previous-episode']) ? $this->makeLinkToEpisode($episode['previous-episode']) : '';
        $linkToNextEpisode     = (isset($episode['next-episode']) and empty($episode['next-episode']['translation-in-progress'])) ?
            $this->makeLinkToEpisode($episode['next-episode']) : '';

        empty($episode['top-margin']) and $episode['top-margin'] = 3;
        $topMargin                                               = str_repeat('<br />', $episode['top-margin']);

        $firstTranslatedVerse = array_shift($episode['translated-text']);
        $firstOriginalVerse   = array_shift($episode['original-text']);
        array_shift($episode['verse-numbers']);

        list($gothicLetter, $latinLetter, $firstOriginalVerse) = $this->getGothicLetter($firstOriginalVerse, $episode['episode-number']);

        $translationNotes = isset($episode['translation-notes']) ? $this->makeTranslationNotes($episode['translation-notes']) : '';

        $keywords = implode(', ', $episode['keywords']);

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
            $latinLetter,
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
            $translationNotes,
            $keywords
        );

        return $html;
    }

    /**
     * Formats the current date as in a blog message.
     *
     * @return string The date
     */
    public function makeMessageDate()
    {
        setlocale(LC_TIME, 'fr_FR', 'fra');
        $format = stripos(PHP_OS, 'win') !== false ? '%A %#d %B %Y' : '%A %e %B %Y';
        $date   = strftime($format);

        return mb_convert_encoding($date, 'UTF-8');
    }

    /**
     * Creates the HTML of the table of contents aka list of episodes.
     *
     * The HTML content of the table of contents will have to be loaded manually in the corresponding blog widget.
     *
     * @param array $episodes The episodes details
     *
     * @return string The table of contents
     */
    public function makeTableOfContents($episodes)
    {
        $optgroupBeginPattern = '    <optgroup label="%s">';
        $optionPattern        = '      <option value="%1$s" title="%2$s - %3$s (%4$d)" />%3$s';
        $optgroupEndPattern   = '    </optgroup>';

        $options             = array();
        $prev_episode        = null;
        $lastEpisodePathName = null;

        foreach ($episodes as $episode) {
            if (empty($prev_episode) or $episode['story-title'] != $prev_episode['story-title']) {
                // this is the beginning of a story
                // adds the optgroup closing tag of the previous story (except for the first story)
                empty($options) or $options[] = $optgroupEndPattern;
                // adds the optgroup opening tag of the current story
                $options[] = sprintf($optgroupBeginPattern, $episode['story-title']);
            }

            if (! empty($episode['episode-title']) and empty($episode['translation-in-progress'])) {
                // this is the begining of a story
                list(, , , $pathname) = explode('/', $episode['url'], 4);
                $pathname             = "/$pathname";
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
     * Creates the HTML of a translation note.
     *
     * @param array $note The note details
     *
     * @return string The HTML content of a note
     */
    public function makeTranslationNote($note)
    {
        list($verseNumber, $originalPart, $translatedPart) = $note;

        return sprintf('      <li>%s&nbsp;: «&nbsp;%s&nbsp;» = «&nbsp;%s&nbsp;»</li>', $verseNumber, $originalPart, $translatedPart);
    }

    /**
     * Creates the HTML of the translations notes.
     *
     * @param array $notes The notes details
     *
     * @return string The translation notes
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
     * Parses and validates the episode details.
     *
     * @param array $episode         The episode details
     * @param bool  $beingTranslated True if this is the episode being translated, false otherwise
     * @param array $prevEpisode     The previous episode details
     * @param int   $lineNumber      The line number in the file being parsed
     *
     * @throws Exception
     *
     * @return array The episode details,
     *               and the possibly updated flag indicating if the episode is being translated or not
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
            $episode         = $this->episodeBeingTranslated + $episode;
        } else {
            list($year) = explode('/', $episode['url']);

            if (! ctype_digit($year)) {
                throw new Exception("bad URL, line: $lineNumber");
            }

            $episode['url'] = 'https://roman-de-renart.blogspot.com/' . $episode['url'];

            if (empty($episode['episode-title'])) {
                throw new Exception("missing episode title, line: $lineNumber");
            }
            if (empty($episode['image-src'])) {
                throw new Exception("missing image source, line: $lineNumber");
            }

            $image_src = $episode['image-src'];

            // fixes image source to 250 px, note that this works only for Google Photos
            $episode['image-src'] = str_replace('/s320/', '/s250/', $image_src);

            if (empty($episode['image-href'])) {
                // defaults the image href to the max size, note that this works only for Google Photos
                $episode['image-href'] = str_replace('/s320/', '/s1600/', $image_src);
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
            empty($episode['story-title']) and $episode['story-title']                           = $prevEpisode['story-title'];
            empty($episode['section-original-title']) and $episode['section-original-title']     = $prevEpisode['section-original-title'];
            empty($episode['section-translated-title']) and $episode['section-translated-title'] = $prevEpisode['section-translated-title'];
        }

        return array($episode, $beingTranslated);
    }

    /**
     * Parses the CVS file containing the text.
     *
     * @return array The episodes details
     */
    public function parseFile()
    {
        $file = '/../data/verses.csv';

        if (! $lines = file(__DIR__ . $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) {
            throw new Exception("cannot read file: $file");
        }

        // skips the column headers
        $lines = array_slice($lines, 1);

        $episodes        = array();
        $lineNumber      = 2;
        $episodeBegining = true;
        $beingTranslated = false;
        $prevEpisode     = null;

        foreach ($lines as $line) {
            $line = $this->parseLine($line, $lineNumber);

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
            }

            $episode         = $this->parseVerse($line, $episode, $beingTranslated, $lineNumber, $episodeBegining);
            $episodeBegining = false;

            if (! empty($line['is-last-verse'])) {
                // this is the last verse of the episode
                if ($episodeCount = count($episodes)) {
                    // links previous episode to current episode
                    $episodes[$episodeCount - 1]['next-episode'] = $episode;
                    // links current episode to previous episode
                    $episode['previous-episode'] = $prevEpisode;
                }

                $episode['keywords'] = $this->extractKeywords($episode['episode-title'], $episode['translated-text']);

                $number              = $episode['episode-number'];
                $episodes[$number]   = $episode;
                $prevEpisode         = $episode;
                $episodeBegining     = true;
            }

            ++$lineNumber;
        }

        if ($episodeBegining) {
            throw new Exception("missing episode end, line: $lineNumber");
        }

        return $episodes;
    }

    /**
     * Parses a line of the CSV file containing the text.
     *
     * @param string $line       The line to parse
     * @param int    $lineNumber The line number
     *
     * @return array The line details with the column headers as keys
     */
    public function parseLine($line, $lineNumber)
    {
        // splits the line by tabs
        $cells = explode("\t", $line);

        foreach ($cells as &$cell) {
            // trims the enclosing quotes
            $cell = trim($cell, '" ');
            // fixes escaped quotes
            $cell = str_replace('""', '"', $cell);
        }

        if (count($this->columnHeaders) != count($cells)) {
            throw new Exception("column header and cell counts do not match, line: $lineNumber");
        }

        return array_combine($this->columnHeaders, $cells);
    }

    /**
     * Parses and validates a verse.
     *
     * @param string $line            The line containing the verse number, original text, translated text,
     * @param array  $episode         The episode details
     * @param bool   $beingTranslated True if this is the episode being translated, false otherwise
     * @param int    $lineNumber      The line number in the file being parsed
     * @param bool   $episodeBegining True if this is the first vese, false otherwise
     *
     * @throws Exception
     *
     * @return array The episode details
     */
    public function parseVerse($line, $episode, $beingTranslated, $lineNumber, $episodeBegining)
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

        if ($episodeBegining) {
            $line['translated-verse'] = $this->fixVerse($line['translated-verse']);
        }

        $episode['translated-text'][] = $line['translated-verse'];

        if (! empty($line['original-verse-to-confirm']) and ! empty($line['translated-verse-to-confirm'])) {
            // collects text part to confirm (to display as translation note)
            $episode['translation-notes'][] = array($line['verse-number'], $line['original-verse-to-confirm'], $line['translated-verse-to-confirm']);
        } elseif (! empty($line['original-verse-to-confirm']) or ! empty($line['translated-verse-to-confirm'])) {
            throw new Exception("missing original or translation part to confirm, line: $lineNumber");
        }

        return $episode;
    }

    /**
     * Reads a file.
     *
     * @param string $file The file name
     *
     * @throws Exception
     *
     * @return string The file content
     */
    public function readFile($file)
    {
        if (! $content = file_get_contents($file)) {
            throw new Exception("cannot read file: $file");
        }

        return $content;
    }

    /**
     * Removes the generated date from a docblock for comparison purposes.
     *
     * @param string $html The HTML content of an episode
     *
     * @return string The HTML content without the generated date
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
     * Saves an episode in a blog message (publishes an episode).
     *
     * The episode is also saved in a file.
     * The blog message is published only if the HTML content of the episode has changed.
     *
     * @param string    $html    The HTML content of the episode
     * @param array     $episode The episode details
     * @param Blog|null $blog    The Blog object
     * @param int       $number  The episode number
     *
     * @return bool True if the episode has changed, false otherwise
     */
    public function saveMessage($html, $episode, $blog, $number)
    {
        $url      = $episode['url'];
        $file     = __DIR__ . "/../messages/$number-" . basename($url);
        $prevHtml = file_exists($file) ? $this->readFile($file) : null;

        if ($this->removeGeneratedDate($html) == $this->removeGeneratedDate($prevHtml)) {
            // the episode is the same as the currently saved version, no change
            return false;
        }

        echo "$number ";

        if (! $blog) {
            // this is the verification mode, no publishing
            return true;
        }

        $postPath = str_replace('https://roman-de-renart.blogspot.com', '', $url);
        $title    = $this->setTitle($episode);
        // removes line breaks because Blogger replaces them with <br> for some reason which screws up the display
        // although messages are set to use HTML as it is and to use <br> for line feeds
        $content = str_replace("\n", ' ', $html);
        $blog->patchPost($postPath, $title, $content, $episode['keywords']);
        $this->writeFile($file, $html);

        return true;
    }

    /**
     * Saves the episodes in the blog (publishes the episodes).
     *
     * @param array     $htmls    The HTML contents of the episodes
     * @param array     $episodes The episodes details
     * @param Blog|null $blog     The Blog object
     *
     * @return string The result of the action to be displayed to the output
     */
    public function saveMessages($htmls, $episodes, $blog = null)
    {
        $publishedCount = 0;

        foreach ($htmls as $number => $html) {
            $publishedCount += $this->saveMessage($html, $episodes[$number], $blog, $number);
        }

        if ($blog) {
            if ($publishedCount == 0) {
                $result = 'No episode has changed, no episode was published.';
            } elseif ($publishedCount == 1) {
                $result = "\n" . 'The episode has changed and was published successfully.';
            } else {
                $result = "\n" . "The $publishedCount episodes have changed and were published successfully.";
            }
        } else {
            if ($publishedCount == 0) {
                $result = 'No episode has changed, no episode needs to be published.';
            } elseif ($publishedCount == 1) {
                $result = "\n" . 'The episode has changed and need to be published.';
            } else {
                $result = "\n" . "The $publishedCount episodes have changed and will need to be published.";
            }
        }

        return $result;
    }

    /**
     * Saves the HTML content of an episode into a temporary file.
     *
     * The episode is saved in messages/temp.html that is used for checking changes before commiting them to the blog.
     *
     * @param string $html   The HTML content of the episode
     * @param int    $number The episode number
     *
     * @return string The result of the action to be displayed to the output
     */
    public function saveTempMessage($html, $number)
    {
        $temp     = 'messages/temp.html';
        $file     = __DIR__ . "/../$temp";
        $prevHtml = file_exists($file) ? $this->readFile($file) : null;

        if ($this->removeGeneratedDate($html) == $this->removeGeneratedDate($prevHtml)) {
            $result = "The episode is already up to date in $temp.";
        } else {
            $this->writeFile(__DIR__ . "/../$temp", $html);
            $result = "The episode was saved successfully in $temp.";
        }

        return $result;
    }

    /**
     * Saves the HTML content of a widget.
     *
     * @param string $html              The HTML content of the widget
     * @param string $basename          The file base name
     * @param string $widget            The widget name
     * @param bool   $verification_only
     *
     * @return string The result of the action to be displayed to the output
     */
    public function saveWidget($html, $basename, $widget, $verification_only)
    {
        $file     = "widgets/$basename";
        $path     = __DIR__ . "/../$file";
        $prevHtml = file_exists($path) ? $this->readFile($path) : null;

        if ($this->removeGeneratedDate($html) == $this->removeGeneratedDate($prevHtml)) {
            $result[] = "The $widget is already up to date.";

            if (! $verification_only) {
                $result[] = "No changes were made to $file.";
            }
        } elseif (! $verification_only) {
            $this->writeFile($path, $html);

            $result[] = "The $widget was updated successfully.";
            $result[] = "Please, COPY & PASTE the content of $file";
            $result[] = 'into the corresponding blog widget.';
        } else {
            $result[] = "The $widget has changed and will need to be published.";
        }

        return implode("\n", $result);
    }

    /**
     * Sets the title of the episode.
     *
     * The title is made of the story title and the episode title.
     *
     * @param array $episode The episode details
     *
     * @return string The episode title
     */
    public function setTitle($episode)
    {
        return sprintf('%s - %s', $episode['story-title'],  $episode['episode-title']);
    }

    /**
     * Updates the HTML of the copyright widget with the current year.
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
     * Updates the HTML of the introduction widget with the number of the last translated verse.
     *
     * The HTML content of the introduction will have to be loaded manually in the corresponding blog widget.
     *
     * @param array $episodes The episodes details
     *
     * @return string The introduction
     */
    public function updateIntroduction($episodes)
    {
        $html = $this->readFile(__DIR__ . '/../widgets/introduction.html');

        if (! isset($episodes[999])) {
            // the last episode was excluded due to the "-n" option, do not update the introduction
            return $html;
        }

        $lastTranslatedVerseNumber = $episodes[999]['verse-number'] - 1;

        // pattern allows for a negative number eg -1 in case of a previous bad run
        return preg_replace('~<span id="mc_fait">-?\d+</span>~', "<span id=\"mc_fait\">$lastTranslatedVerseNumber</span>", $html);
    }

    /**
     * Writes into a file.
     *
     * @param string $file    The file name
     * @param string $content The file content
     *
     * @throws Exception
     */
    public function writeFile($file, $content)
    {
        if (! @file_put_contents($file, $content)) {
            throw new Exception("cannot write file: $file");
        }
    }
}
