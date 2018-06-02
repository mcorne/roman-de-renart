<?php
/**
 * Roman de Renart.
 *
 * Common functions
 *
 * @author    Michel Corne <mcorne@yahoo.com>
 * @copyright 2012 Michel Corne
 * @license   http://www.opensource.org/licenses/gpl-3.0.html GNU GPL v3
 *
 * @link      https://roman-de-renart.blogspot.com/
 */
define('INDEX_ROWS', 1);

define('MAX_VERSES_TO_TRANSLATE', 160);

// the list of punctuation characters to be used in a preg function pattern
define('PUNCTUATION', '!|,|\.|:|;|\?|«|»|—|“|”');

/**
 * Echos the command title.
 *
 * @param string $command_title the command title
 */
function echo_command_title($command_title)
{
    // echo str_pad($command_title . '...', 30);
    echo $command_title . ' ... ';
}

/**
 * Echos a message stating if the content has changed or not.
 *
 * @param bool $has_content_changed true if the content has changed, false otherwise
 */
function echo_has_content_changed($has_content_changed)
{
    echo $has_content_changed ? '(content has changed)' : '(content has not changed)';
    echo "\n";
}

/**
 * Fixes a file content line endings to the Unix line ending.
 *
 * @param string $content the file content
 *
 * @return string the file content with Unix style line endings
 */
function fix_line_endings($content)
{
    // fixes windows line ending
    $content = str_replace("\r\n", "\n", $content);
    // fixes mac line ending
    $content = str_replace("\r", "\n", $content);

    return $content;
}

/**
 * Returns the column headers from the first row of a CSV file.
 *
 * @param array $rows the array of rows
 *
 * @return array the list of column headers
 */
function get_column_headers($rows)
{
    $first_row    = current($rows);
    $column_names = array_keys($first_row);

    return array_combine($column_names, $column_names);
}

/**
 * Returns the number of the last verse to translate.
 *
 * @param array $verses the verses of the text
 *
 * @throws Exception
 *
 * @return int the verse number
 */
function get_number_last_verse_to_translate($verses)
{
    $prev_number = null;

    foreach ($verses as $number => $verse) {
        if ($verse['translated-verse'] == '...' or $verse['translated-verse'] == '…') {
            if (is_null($prev_number)) {
                throw new Exception('there is nothing to translate');
            }

            return $prev_number;
        }

        $prev_number = $number;
    }

    throw new Exception('cannot find last verse to translate (missing "..." marker in translated-verse column');
}

/**
 * Returns the list of punctuation characters.
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
 * Indexes an array of rows with one of the keys.
 *
 * The key is meant to be a column file header.
 *
 * @param array  $rows          the array of rows, each row being an associative array,
 *                              one of the keys must be set to the column name
 * @param string $column_header the name of the key or column header
 *
 * @throws Exception
 *
 * @return array the associative array of rows
 */
function index_rows($rows, $column_header)
{
    $indexed_rows = array();

    foreach ($rows as $row) {
        if (! isset($row[$column_header])) {
            throw new Exception("missing column $column_header");
        }
        $index = $row[$column_header];

        if (isset($indexed_rows[$index])) {
            throw new Exception("index already used: $index");
        }
        $indexed_rows[$index] = $row;
    }

    return $indexed_rows;
}

/**
 * Reads a CSV file.
 *
 * @param string $filename      the file name
 * @param string $column_header the name of the key or column header to index with, or null for no indexing
 *
 * @return the array of rows, each row being an associative array containing the cell values
 *             with the column headers as keys
 */
function read_csv($filename, $column_header = null)
{
    $content = read_file($filename);

    $content = fix_line_endings($content);
    $lines   = explode("\n", $content); // TODO: handle line feeds within a cell
    $lines   = array_filter($lines);

    $first_line     = array_shift($lines);
    $column_headers = read_line($first_line);
    $columns_count  = count($column_headers);

    $rows = array();
    foreach ($lines as $line) {
        $cells  = read_line($line);
        $cells  = array_pad($cells, $columns_count, null);
        $rows[] = array_combine($column_headers, $cells);
    }

    if (isset($column_header)) {
        $rows = index_rows($rows, $column_header);
    }

    return $rows;
}

/**
 * Reads a file.
 *
 * @param string $filename the file name
 *
 * @throws Exception
 *
 * @return string the file content
 */
function read_file($filename)
{
    if (! $content = file_get_contents($filename)) {
        throw new Exception("cannot read $filename");
    }

    return $content;
}

/**
 * Reads a TAB separated line content.
 *
 * @param string $line the line
 *
 * @return array an array of cell values
 */
function read_line($line)
{
    // splits the line by tabs
    $cells = explode("\t", $line);

    foreach ($cells as &$cell) {
        // trims the enclosing quotes
        $cell = trim($cell, '" ');
        // unescapes quotes
        $cell = str_replace('""', '"', $cell);
    }

    return $cells;
}

/**
 *
 * @return array
 */
function read_password_file()
{
    $filename = __DIR__ . '/password.php';

    if (! file_exists($filename)) {
        return array();
    }

    $options = require $filename;

    $options['u'] = openssl_decrypt($options['u'], 'AES-128-ECB', '1234567812345678');
    $options['p'] = openssl_decrypt($options['p'], 'AES-128-ECB', '1234567812345678');

    return $options;
}

/**
 * Validates the range of verse numbers.
 *
 * @param array $verses             the verses of the text
 * @param int   $first_verse_number the number of the first verse
 * @param int   $last_verse_number  the number of the last verse
 *
 * @throws Exception
 *
 * @return array the verses within the range
 */
function validate_verse_number_range($verses, $first_verse_number, $last_verse_number)
{
    if (! isset($verses[$first_verse_number])) {
        throw new Exception("unknown first verse number $first_verse_number");
    }

    if (! isset($verses[$last_verse_number])) {
        throw new Exception("unknown last verse number $last_verse_number");
    }

    $count = $last_verse_number - $first_verse_number + 1;

    if ($count < 0) {
        throw new Exception("first verse $first_verse_number greater than last verse $last_verse_number");
    }

    if ($count == 0) {
        throw new Exception('there is no verse to process');
    }

    if ($count > MAX_VERSES_TO_TRANSLATE) {
        throw new Exception(sprintf('verses range (%s - %s) greater than %s',
            $first_verse_number, $last_verse_number, MAX_VERSES_TO_TRANSLATE));
    }

    $offset = $first_verse_number - 1; // offset expected to be equal to verse number - 1
    $verses = array_slice($verses, $offset, $count, true);
    $verse  = current($verses);
    if ($verse['verse-number'] != $first_verse_number) {
        throw new Exception("unexpected first verse {$verse['verse-number']} instead of $first_verse_number");
    }

    foreach ($verses as $number => $verse) {
        if (! empty($verse['translated-verse'])) {
            throw new Exception("not expecting translation in verse $number");
        }
    }

    return $verses;
}

/**
 * Writes a CSV file.
 *
 * @param string $filename the file name
 * @param array  $rows     the array of rows, each row being an associative array
 *
 * @throws Exception
 *
 * @return bool true if the file content has changed (and the file was actually written),
 *              false otherwise
 */
function write_csv($filename, $rows)
{
    if (empty($rows)) {
        throw new Exception("no rows to write for $filename");
    }

    $column_headers = get_column_headers($rows);
    array_unshift($rows, $column_headers);
    $row_defaults = array_fill_keys($column_headers, null);

    $lines = array();
    foreach (array_keys($rows) as $key) {
        $cells   = array_merge($row_defaults, $rows[$key]);
        $lines[] = write_line($cells);
    }

    return write_file($filename, $lines);
}

/**
 * Writes a file.
 *
 * @param string $filename the file name
 * @param string $content  the file content
 *
 * @throws Exception
 *
 * @return bool true if the file content has changed (and the file was actually written),
 *              false otherwise
 */
function write_file($filename, $content)
{
    if (is_array($content)) {
        $content = implode("\n", $content);
    }

    $is_file = file_exists($filename);

    if ($is_file and read_file($filename) == $content) {
        $has_content_changed = false;
    } else {
        $has_content_changed = true;

        if (! @file_put_contents($filename, $content)) {
            throw new Exception("cannot write $filename");
        }
    }

    return $has_content_changed;
}

/**
 * Writes a TAB separated line content.
 *
 * @param array $cells the row cells
 *
 * @return string the line
 */
function write_line($cells)
{
    foreach ($cells as &$cell) {
        if (! is_numeric($cell)) {
            // escapes quotes
            $cell = str_replace('"', '""', $cell);
           // encloses cells with quotes
            $cell = '"' . $cell . '"';
        }
    }

    return implode("\t", $cells);
}
