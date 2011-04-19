<?php

/**
 * 
 *
 * @author Shiki
 */
class fbApplicationComponent extends CApplicationComponent
{
  /**
   * Intentional but temporary duplicate with fbSafariIframeFixAction
   * @todo fixme
   */
  const SKEY_SAFARI_COOKIE_SET = 'safari_cookie_set';

  public $sdkFilePath;

  public $applicationId;
  public $apiKey;
  public $applicationSecret;

  /**
   * The url of the app as seen on Facebook (e.g. http://apps.facebook.com/myapp)
   * @var string
   */
  public $appBaseUrl;

  /**
   * The actual url of the application (e.g. http://mydomain.com/canvas)
   * @var string
   */
  public $realBaseUrl;

  /**
   * The url pointing to the tab page.
   * @var String
   */
  public $fanPageTabUrl;


  /**
   * @see http://developers.facebook.com/docs/authentication/permissions
   * @var array
   */
  public $permissions;

  /**
   *
   * @var Facebook
   */
  protected $_client;

  public function init()
  {
    Yii::import($this->sdkFilePath, true);
    parent::init();
  }

  /**
   * @return Facebook
   */
  public function getClient()
  {
    if (!$this->_client) {
      $this->_client = $this->createClient();
    }
    return $this->_client;
  }

  public function getPermissionsAsString()
  {
    if (!is_array($this->permissions))
      return '';
    else
      return implode(',', $this->permissions);
  }

  /**
   * @return Facebook
   */
  public function createClient($options = null)
  {
    if (!is_array($options))
      $options = array();
    $options = array_merge(array('cookie' => true), $options);

    $client = new Facebook(array(
      'appId'  => $this->applicationId,
      'secret' => $this->applicationSecret,
      'cookie' => $options['cookie'],
    ));

    return $client;
  }

  /**
   *
   * @param string $url
   */
  public function redirectAbsolute($url, $message = null)
  {
    if (!empty($message)) {
      echo $message;
      echo '<script type="text/javascript">setTimeout(function(){top.location.href = "' . $url . '";}, 2000);</script>';
    } else {
      echo '<script type="text/javascript">top.location.href = "' . $url . '";</script>';
    }

    
    Yii::app()->end();
  }

  /**
   * Redirect to the url relative to $appBaseUrl.
   * @see $appBaseUrl
   * @param string $relativeUrl
   */
  public function redirect($relativeUrl)
  {
    $this->redirectAbsolute($this->appBaseUrl . '/' . $relativeUrl);
  }

  public function isInTabPage()
  {
    $signedRequest = $this->getClient()->getSignedRequest();
    return isset($signedRequest['page']);
  }

  /**
   *
   * @param <type> $uid
   * @return Array
   */
  public static function getPublicUserData($uid)
  {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://graph.facebook.com/'. $uid);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $json = curl_exec($ch);
    curl_close($ch);

    if ($json)
      return CJSON::decode($json);
    return false;
  }

  /**
   * @param Int $appId Defaults to its `applicationId` property
   * @return Array
   */
  public function getCommentsInfo($appId = null)
  {
    $appId = empty($appId) ? $this->applicationId: $appId;
    $query = "SELECT xid, count, updated_time FROM comments_info WHERE app_id = '{$appId}'";
    try {
      $client = $this->getClient();
      return $client->api(array(
        'method' => 'fql.query',
        'query'  => $query,
      ));
    } catch(FacebookApiException $e) {
      Yii::log($e->__toString(), CLogger::LEVEL_ERROR, __CLASS__);
      return null;
    }
  }

  /**
   * To know how many comments per XID, you first have to
   * query `comments_info` FQL table.
   *
   * @param String $xid
   * @param Int $offset
   * @param Int $limit  // Limit per query is 100 rows
   * @return Array
   */
  public function getComments($xid, $offset = 0, $limit = 100)
  {
    if (!$xid)
      return null;

    // throttle SSL connect timeout
    // May not be necessary for one or two succeeding `getComments()` calls
    Facebook::$CURL_OPTS[CURLOPT_CONNECTTIMEOUT] = 720; // 12 minutes

    $query1 = "SELECT id, xid, fromid, time, text"
                . " FROM comment WHERE xid='{$xid}' "
                . " ORDER BY time DESC"
                . " LIMIT {$limit} OFFSET {$offset}";
    $query2 = "SELECT id, name, url, pic_square  FROM profile WHERE id IN (SELECT fromid FROM #query1)";
    $multiQuery = sprintf('{"query1": "%s", "query2": "%s"}', $query1, $query2);

    try {
      $client = $this->getClient();
      return $client->api(array(
        'method' => 'fql.multiquery',
        'queries'  => $multiQuery,
      ));

    } catch (FacebookApiException $e) {
      Yii::log($e->__toString(), CLogger::LEVEL_ERROR, __CLASS__);
      return null;
    }
  }

  public function removeComment($commentId, $xid)
  {
    try {
      // This prevents FB from throwing: `SSL certificate problem, verify that the CA cert is OK.`
      Facebook::$CURL_OPTS[CURLOPT_SSL_VERIFYPEER] = false;
      Facebook::$CURL_OPTS[CURLOPT_SSL_VERIFYHOST] = 2;
      
      $client = $this->getClient();
      return $client->api(array(
        'method' => 'comments.remove',
        'comment_id' => $commentId,
        'xid' => $xid,
      ));

    } catch (FacebookApiException $e) {
      Yii::log($e->__toString(), CLogger::LEVEL_ERROR, __CLASS__);
      return false;
    }
  }

  public function ensureSafariIFrameFix()
  {
    // hack for safari not persisting cookies on iframes
    // http://anantgarg.com/2010/02/18/cross-domain-cookies-in-safari/
    if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Safari') !== false) {
      // this browser detection might not be the best solution, but I think it works fine
      // If there's problem, we could just use this I think: http://chrisschuld.com/projects/browser-php-detecting-a-users-browser-from-php/

      $session = Yii::app()->session;
      if (!isset($session[self::SKEY_SAFARI_COOKIE_SET])) {
        $this->render('ext.facebookapp.safari-cookie-fix');
        return;
      }
    }
  }
}