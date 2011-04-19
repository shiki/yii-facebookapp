<?php

/**
 * 
 *
 * @author Shiki
 */
class fbWebUserBehavior extends CBehavior
{
  /**
   *
   * @var Facebook
   */
  private $_client;

  private $_accessToken;

  public static function behaviorName()
  {
    return 'fbWebUser';
  }

  /**
   * @return Facebook
   */
  private function getClient()
  {
    if (!$this->_client)
      $this->_client = Yii::app()->fbApplication->createClient();
    return $this->_client;
  }

  /**
   *
   * @return boolean
   */
  public function hasSession()
  {
    $client = $this->getClient();
    return (bool)$client->getSession();
  }

  public function getUserId()
  {
    $client = $this->getClient();
    return $client->getUser();
  }

  /**
   * Checks whether this user is an Admin of the current App
   */
  public function isAdmin()
  {
    $signedRequest = $this->getClient()->getSignedRequest();
    return (isset($signedRequest['page']) && $signedRequest['page']['admin']);
  }

  /**
   * Checks whether this user has liked the Custom Fan Page
   * where the current App is embedded
   */
  public function hasLiked()
  {
    $signedRequest = $this->getClient()->getSignedRequest();
    return (isset($signedRequest['page']) && $signedRequest['page']['liked']);
  }

  /**
   * Checks if user has authorized the current App
   */
  public function hasAuthorized()
  {
    $signedRequest = $this->getClient()->getSignedRequest();
    return (!empty($signedRequest['oauth_token']));
  }

  public function getUserInfo()
  {
    $client = $this->getClient();
    try {
      return $client->api('/me');
    } catch (Exception $e) {
      Yii::log($e->__toString(), CLogger::LEVEL_ERROR, __CLASS__);
      return null;
    }
  }

  /**
   * This retrieves the permissions of the specified user.
   *
   * @see http://developers.facebook.com/docs/authentication/permissions
   *
   * @param array $permissions
   * @param string $uid
   * @return array
   */
  public function checkPermissions($permissions, $userId = null)
  {
    if (!is_array($permissions))
      $permissions = array($permissions);

    $query = "SELECT %s FROM permissions WHERE uid = %s LIMIT 1;";

    $client = $this->getClient();
    if ($userId == null && $this->hasSession())
      $userId = $client->getUser();

    try {
      $res = $client->api(array(
        'method' => 'fql.query',
        'query'  => sprintf($query, implode(',', $permissions), $userId),
      ));
      if ($res && isset($res[0]) && is_array($res[0]))
        return $res[0];
      
    } catch (FacebookApiException $e) {
      Yii::log($e, CLogger::LEVEL_ERROR, __CLASS__);
    }
    return array();
  }

  /**
   * @return Array
   */
  public function getFriends()
  {
    $client = $this->getClient();
    $_accessToken = isset($this->_accessToken) ? $this->_accessToken: $client->getAccessToken();

    try {
      $res = $client->api('/me/friends?access_token='. $_accessToken);
      if ($res && isset($res['data']) && is_array($res['data']))
        return $res['data'];

    } catch (FacebookApiException $e) {
      Yii::log($e, CLogger::LEVEL_ERROR, __CLASS__);
    }
    return array();
  }

  /**
   * @param String $accessToken
   * @return fbWebUserBehavior
   */
  public function setAccessToken($accessToken)
  {
    if (!empty($accessToken))
      $this->_accessToken = $accessToken;
    return $this;
  }

  /**
   * This checks if the user is a Fan of SmartComm
   * @return Boolean
   */
  public function isPageFan($fbUserId = null)
  {
    $client = $this->getClient();

    if (empty($fbUserId)) {
      $fbUserId = $client->getUser();
      if (empty($fbUserId))
        return false;
    }

    $query = 'SELECT uid, page_id FROM page_fan WHERE uid=%s AND page_id=%s';
    $query = sprintf($query, $fbUserId, Yii::app()->params['smartFanPageId']);

    try {
      $res = $client->api(array(
        'method' => 'fql.query',
        'query'  => $query,
      ));
      if ($res && is_array($res) && count($res) > 0)
        return true;
      
    } catch (FacebookApiException $e) {
      Yii::log($e, CLogger::LEVEL_ERROR, __CLASS__);
    }
    return false;
  }

  /**
   * Publish to profile feed as a status message or a link.
   *
   * Note: Action links are not yet supported in the Graph API.
   *       See: http://forum.developers.facebook.net/viewtopic.php?pid=223444
   *
   * $message param receives (as Array):
   * - message    : The message
   * - picture    : If available, a link to the picture included with this post
   * - link       : The link attached to this post
   * - name       : The name of the link
   * - caption    : The caption of the link (appears beneath the link name)
   * - description: A description of the link (appears beneath the link caption)
   *
   * @see http://developers.facebook.com/docs/reference/api/post
   *
   * @param String  $uid User Id
   * @param String  $accessToken Stored access_token value
   * @param Mixed   $message Array or String
   * @return Mixed  Array on success containing the Id of the post.
   *                False otherwise.
   */
  public function streamPublish($uid, $accessToken, $message)
  {
    if (empty($uid) || empty($accessToken) || empty($message))
      return false;

    if (!is_array($message))
      $message = array('message' => strval($message));

    $message = array_merge(array(
      'message' => null,
      'picture' => null,
      'link' => null,
      'name' => null,
      'caption' => null,
      'description' => null,
    ), $message);

    $arguments = array('access_token' => $accessToken);
    foreach($message as $key => $value) {
      if (isset($value))
        $arguments[$key] = $value;
    }

    $fbClient = $this->getClient();

    try {
      return $fbClient->api(sprintf('/%s/feed/', $uid), 'POST', $arguments);
    } catch (FacebookApiException $e) {
      Yii::log('Failed to publish to stream: ' . CJSON::encode($arguments) . ' :: ' . $e->__toString(), CLogger::LEVEL_ERROR, __CLASS__);
      return false;
    }
  }

  /**
   * Currently, there's no way to retrieve a list of Page Fans by just
   * filtering the page_id in the page_fan FQL table. This is because
   * page_id field is not indexable yet. So this is how we do it for now.
   *
   * What this thing does is that we let Fb do the filtering after
   * submitting the page_id and the list of valid Fb UIDs.
   *
   * @see http://developers.facebook.com/docs/reference/fql/page_fan
   * @param String $pageId Id of Fb page
   * @param Mixed $uid Array for multiple UIDs
   * @param String $type There may be lots of possible values for this.
   * @return Array Null on error
   */
  public function filterFansOf($pageId, $uid, $type = 'APPLICATION')
  {
    if (empty($uid) || empty($pageId))
      return null;

    $condition = '';
    //if (isset($type))
      //$condition .= 'type="'. $type .'"';

    $condition .= empty($condition) ? '': ' AND ';
    $condition .= 'page_id="'. $pageId .'" AND ';

    if (is_array($uid))
      $condition .= 'uid IN('. implode(',', $uid) .')';
    else
      $condition .= 'uid="'. $uid .'"';

    $query = sprintf('SELECT uid, page_id, type FROM page_fan WHERE %s', $condition) ;

    try {
      return $this->getClient()->api(array(
        'method' => 'fql.query',
        'query'  => $query,
      ));
    } catch (FacebookApiException $e) {
      Yii::log($e, CLogger::LEVEL_ERROR, __CLASS__);
      return null;
    }
  }
}