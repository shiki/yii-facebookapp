<?php


/**
 * 
 *
 * @author shiki
 */
class fbSafariIFrameFixAction extends CAction
{
  const SKEY_SAFARI_COOKIE_SET = 'safari_cookie_set';

  public function run()
  {
    $session = Yii::app()->session;
    $session[self::SKEY_SAFARI_COOKIE_SET] = 1;

    $fbApp = Yii::app()->fbApplication;
    $fbApp->redirectAbsolute($fbApp->fanPageTabUrl, RequireOnFBPageTabFilter::getRedirectMessage($fbApp->fanPageTabUrl));
  }
}