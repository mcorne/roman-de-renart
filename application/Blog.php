<?php
/**
 * Processing of blog messages.
 *
 * @author    Michel Corne <mcorne@yahoo.com>
 * @copyright 2015 Michel Corne
 * @license   http://www.opensource.org/licenses/gpl-3.0.html GNU GPL v3
 */
class Blog
{
    /**
     * The blog ID.
     *
     * The blog ID MUST BE DEFINED IN "blog_id.php" in the same directory
     *
     * @var string
     *
     * @see self::__construct()
     */
    public $blogId;

    /**
     * The encryption key used to encrypt the client ID and secret in "credentials.php".
     *
     * @var string
     */
    public $encryptionKey;

    /**
     * The encryption IV used by the encryption algorythm.
     *
     * @var string
     *
     * @see self::decryptString() and self::encryptString()
     */
    public $encryptionIv = '7459589619995061';

    /**
     * Name of the temporary file where authorization tokens are stored.
     *
     * @var string
     */
    public $tokensFilename;

    /**
     * Sets the encryption key and token storage file name.
     *
     * @param string $user
     * @param string $password
     */
    public function __construct($user, $password)
    {
        $this->blogId         = require 'blog_id.php';
        $this->encryptionKey  = $this->setEncryptionKey($user, $password);
        $this->tokensFilename = $this->getTokensFilename();
    }

    /**
     * Gets and stores the authorization tokens.
     *
     * @param string $authorizationCode
     */
    public function authorize($authorizationCode)
    {
        $tokens = $this->getTokens($authorizationCode);
        $this->writeTokens($tokens);
    }

    /**
     * Calls the Blogger API.
     *
     * @param array $options
     *
     * @return array
     *
     * @throws Exception
     */
    public function callBloggerApi(array $options)
    {
        $options += [
            CURLOPT_HEADER         => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ];

        $ch = curl_init();

        if (! curl_setopt_array($ch, $options)) {
            throw new Exception('cannot set curl options: ' . curl_error($ch));
        }

        if (! $response = curl_exec($ch)) {
            throw new Exception('cannot execute curl: ' . curl_error($ch));
        }

        if (! $info = curl_getinfo($ch)) {
            throw new Exception('cannot get curl info: ' . curl_error($ch));
        }

        if ($info['http_code'] == 401) {
            throw new Exception('Invalid tokens! Please run "authorize -h"');
        }

        if ($info['http_code'] != 200) {
            if (strpos($response, 'invalid_grant')) {
                throw new Exception('Invalid authorization! Please run "authorize -h"');
            }

            $response = preg_replace('~\s+~', ' ', $response);
            throw new Exception('http error: ' . $response);
        }

        if (! $decoded = json_decode($response, true)) {
            $response = preg_replace('~\s+~', ' ', $response);
            throw new Exception('cannot json decode response: ' . $response);
        }

        curl_close($ch);

        return $decoded;
    }

    /**
     * Decrypts a string.
     *
     * @param string $base64
     *
     * @return string
     *
     * @throws Exception
     */
    public function decryptString($base64)
    {
        if (! $encrypted = base64_decode($base64)) {
            throw new Exception('cannot base64 decode string');
        }

        if (! $decrypted = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $this->encryptionKey, $encrypted, MCRYPT_MODE_CBC, $this->encryptionIv)) {
            throw new Exception('cannot decrypt string');
        }

        $decrypted = rtrim($decrypted, "\0");

        return $decrypted;
    }

    /**
     * Encrypts a string.
     *
     * @param string $string
     *
     * @return string
     *
     * @throws Exception
     */
    public function encryptString($string)
    {
        if (! $encrypted = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $this->encryptionKey, $string, MCRYPT_MODE_CBC, $this->encryptionIv)) {
            throw new Exception('cannot encrypt string');
        }

        if (! $base64 = base64_encode($encrypted)) {
            throw new Exception('cannot base64 encode string');
        }

        return $base64;
    }

    /**
     * Reads and decrypts the credentials.
     *
     * @return array
     *
     * @throws Exception
     */
    public function getCredentials()
    {
        $filename = __DIR__ . '/credentials.php';

        if (! file_exists($filename)) {
            throw new Exception('credentials file missing: ' . $filename);
        }

        $credentials = require_once $filename;

        $credentials['client_id']     = $this->decryptString($credentials['client_id']);
        $credentials['client_secret'] = $this->decryptString($credentials['client_secret']);

        $credentials['auth_screen_url'] = sprintf($credentials['auth_screen_url_tpl'], $credentials['client_id'], $credentials['redirect_uri']);

        return $credentials;
    }

    /**
     * Returns a message post ID.
     *
     * @param string $postPath
     * @param string $tokenType
     * @param string $accessToken
     *
     * @return string
     *
     * @throws Exception
     */
    public function getPostId($postPath, $tokenType, $accessToken)
    {
        $header[] = sprintf('Authorization: %s %s', $tokenType, $accessToken);

        $postPath = urlencode($postPath);
        $url      = sprintf('https://www.googleapis.com/blogger/v3/blogs/%s/posts/bypath?path=%s', $this->blogId, $postPath);

        $options = [
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER    => $header,
            CURLOPT_URL           => $url,
        ];

        $response = $this->callBloggerApi($options);

        if (empty($response['id'])) {
            throw new Exception('empty post ID');
        }

        return $response['id'];
    }

    /**
     * Returns the authorization tokens.
     *
     * @param string $authorizationCode
     *
     * @return array
     */
    public function getTokens($authorizationCode)
    {
        $credentials = $this->getCredentials();

        $data = http_build_query([
            'client_id'     => $credentials['client_id'],
            'client_secret' => $credentials['client_secret'],
            'code'          => $authorizationCode,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $credentials['redirect_uri'],
        ]);

        $length = strlen($data);

        $header = [
            "Content-length: $length",
            'Content-Type: application/x-www-form-urlencoded',
        ];

        $options = [
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER    => $header,
            CURLOPT_POSTFIELDS    => $data,
            CURLOPT_URL           => 'https://www.googleapis.com/oauth2/v3/token',
        ];

        $tokens = $this->callBloggerApi($options);

        return $tokens;
    }

    /**
     * Returns the temporary token storage file name.
     *
     * @return string
     */
    public function getTokensFilename()
    {
        $filename = sprintf('%s/blogger-api-tokens-%s.txt', sys_get_temp_dir(), $this->blogId);

        return $filename;
    }

    /**
     * Patches a message post.
     *
     * @param string $postPath
     * @param string $title
     * @param string $content
     * @param array  $labels
     *
     * @throws Exception
     */
    public function patchPost($postPath, $title, $content, $labels = null)
    {
        $tokens = $this->readTokens();

        if (empty($tokens['token_type']) or empty($tokens['access_token'])) {
            throw new Exception('empty token_type or access_token');
        }

        $header = [
            sprintf('Authorization: %s %s', $tokens['token_type'], $tokens['access_token']),
            'Content-Type: application/json',
        ];

        $data = [
            'content' => $content,
            'title'   => $title,
        ];

        if ($labels) {
            $data['labels'] = (array) $labels;
        }

        if (! $json = json_encode($data)) {
            throw new Exception('cannot json encode post data');
        }

        $postId = $this->getPostId($postPath, $tokens['token_type'], $tokens['access_token']);
        $url    = sprintf('https://www.googleapis.com/blogger/v3/blogs/%s/posts/%s?publish=true', $this->blogId, $postId);

        $options = [
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_HTTPHEADER    => $header,
            CURLOPT_POSTFIELDS    => $json,
            CURLOPT_URL           => $url,
        ];

        $this->callBloggerApi($options);
    }

    /**
     * Returns the authorization tokens.
     *
     * @return array
     *
     * @throws Exception
     */
    public function readTokens()
    {
        if (! file_exists($this->tokensFilename) or ! $base64 = file_get_contents($this->tokensFilename)) {
            throw new Exception('Tokens missing! Please run "authorize -h"');
        }

        $json = $this->decryptString($base64);

        if (! $tokens = json_decode($json, true)) {
            throw new Exception('Cannot process tokens! Please enter a valid name and password');
        }

        return $tokens;
    }

    /**
     * Sets the encryption key.
     *
     * @param string $user
     * @param string $password
     *
     * @return string
     */
    public function setEncryptionKey($user, $password)
    {
        $encryptionKey = $this->setStringTo16Bytes($password . $user);

        return $encryptionKey;
    }

    /**
     * Truncates or pads a string to 16 bits.
     *
     * @param string $string
     *
     * @return string
     */
    public function setStringTo16Bytes($string)
    {
        if (strlen($string) > 16) {
            $string = substr($string, 0, 16);
        } else {
            $string = str_pad($string, 16);
        }

        return $string;
    }

    /**
     * Writes the authorization tokens in the temporary file.
     *
     * @param array $tokens
     *
     * @throws Exception
     */
    public function writeTokens($tokens)
    {
        if (! $json = json_encode($tokens)) {
            throw new Exception('cannot json encode tokens');
        }

        $encrypted = $this->encryptString($json);

        if (! file_put_contents($this->tokensFilename, $encrypted)) {
            throw new Exception('cannot write: ' . $this->tokensFilename);
        }
    }
}
