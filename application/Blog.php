<?php
/**
 * Roman de Renart
 *
 * Processing of blog messages
 *
 * @author    Michel Corne <mcorne@yahoo.com>
 * @copyright 2012 Michel Corne
 * @license   http://www.opensource.org/licenses/gpl-3.0.html GNU GPL v3
 * @link      http://roman-de-renart.blogspot.com/
 */

require_once 'Zend/Gdata.php';
require_once 'Zend/Gdata/ClientLogin.php';
require_once 'Zend/Gdata/Query.php';

class Blog
{
    /**
     * Maximum number of messages retrieves
     *
     * @var int
     */
    const MAX_MESSAGES = 500;

    /**
     * The blog ID
     *
     * @var string
     */
    protected $_blogId;

    /**
     * The client object
     *
     * @var Zend_Gdata
     */
    protected $_client;

    /**
     * The list of blog messages
     *
     * @var array
     */
    protected $_feeds = array();

    /**
     * The constructor
     *
     * @param string $user     The Blogger account user name
     * @param string $password The Blogger account password
     * @param string $title    The blog title
     */
    public function __construct($user = null, $password = null, $title = null)
    {
        if ($user and $password) {
            $this->getClient($user, $password);

            if ($title) {
                $this->getBlogId($title);
            }
        }
    }

    /**
     * Creates a blog message
     *
     * @param string $title   The message title
     * @param string $content The message content
     * @param string $label   The message label aka category
     * @return string         The message ID
     */
    public function createPost($title, $content, $label)
    {
        $uri = 'http://www.blogger.com/feeds/' . $this->_blogId . '/posts/default';

        $entry = $this->_client->newEntry();
        $entry->title = $this->_client->newTitle($title);
        $entry->content = $this->_client->newContent($content);
        $entry->content->setType('text');
        $category = $this->_client->newCategory($label, 'http://www.blogger.com/atom/ns#');
        $entry->setCategory(array($category));

        $post = $this->_client->insertEntry($entry, $uri);
        list(,, $postId) = explode('-', $post->id->text);

        return $postId;
    }

    /**
     * Returns the blog ID
     *
     * @param string $title The blog title
     * @throws Exception
     */
    public function getBlogId($title)
    {
        $uri = 'http://www.blogger.com/feeds/default/blogs';

        if (! $this->_blogId = $this->getFeedEntry($uri, $title)) {
            throw new Exception('invalid blog');
        }
    }

    /**
     * Sets a client instance aka logs into Blogger
     *
     * @param string $user     The Blogger account user name
     * @param string $password The Blogger account password
     */
    public function getClient($user, $password)
    {
        $client = Zend_Gdata_ClientLogin::getHttpClient(
            $user, $password, 'blogger', null,
            Zend_Gdata_ClientLogin::DEFAULT_SOURCE, null, null,
            Zend_Gdata_ClientLogin::CLIENTLOGIN_URI, 'GOOGLE');

        $this->_client = new Zend_Gdata($client);
    }

    /**
     * Returs a message ID by its title or URL
     *
     * @param string $uri        The URI of the blog feed
     * @param string $titleOrUrl The title or URL of the message
     * @return string            The message ID
     */
    public function getFeedEntry($uri, $titleOrUrl)
    {
        if (!isset($this->_feeds[$uri])) {
            // gets and saves the list of blog messages
            $query = new Zend_Gdata_Query($uri);
            $query->setMaxResults(self::MAX_MESSAGES);
            $this->_feeds[$uri] = $this->_client->getFeed($query);
        }

        foreach($this->_feeds[$uri]->entries as $entry) {
            if ($entry->getTitleValue() == $titleOrUrl or $entry->getAlternateLink()->href == $titleOrUrl) {
                // the message was found by its title or URL
                $idText = explode('-', $entry->id->text);

                return $idText[2];
            }
        }

        return null;
    }

    /**
     * Returns the message ID
     *
     * @param string $url The blog message URL
     * @return string     The message ID
     */
    public function getPostId($url)
    {
        $uri = 'http://www.blogger.com/feeds/' . $this->_blogId . '/posts/default';

        return $this->getFeedEntry($uri, $url);
    }

    /**
     * Updates a blog message
     *
     * @param string $title   The message title
     * @param string $content The message content
     * @param string $label   The message label aka category
     * @param string $postId  The message ID
     * @return string         The message ID
     */
    public function updatePost($title, $content, $label, $postId)
    {
        $uri = 'http://www.blogger.com/feeds/' . $this->_blogId . '/posts/default/' . $postId;
        $query = new Zend_Gdata_Query($uri);
        $entry = $this->_client->getEntry($query);
        $entry->content->text = $this->_client->newContent($content);
        $entry->title = $this->_client->newTitle($title);
        $category = $this->_client->newCategory($label, 'http://www.blogger.com/atom/ns#');
        $entry->setCategory(array($category));

        $control = $this->_client->newControl();
        $draft = $this->_client->newDraft('no');
        $control->setDraft($draft);
        $entry->control = $control;

        $post = $entry->save();

        return $postId;
    }

    /**
     * Creates or updates a blog message
     *
     * @param string $title   The message title
     * @param string $content The message content
     * @param string $url     The blog message URL
     * @param string $label   The message label aka category
     * @return string         The message ID
     */
    public function savePost($title, $content, $url, $label)
    {
        if ($postId = $this->getPostId($url)) {
            $this->updatePost($title, $content, $label, $postId);
        } else {
            $postId = $this->createPost($title, $content, $label);
        }

        return $postId;
    }
}
