<?php

/**
 *
 * @author Shiki
 * @author mArk
 */
class RequireOnFBPageTabFilter extends CFilter
{
  /**
   *
   * @param CFilterChain $filterChain
   * @return <type>
   */
  protected function preFilter($filterChain)
  {
    $fbApp = Yii::app()->fbApplication;

    // make sure we're inside the Facebook's Custom Tab Page
    if (!$fbApp->isInTabPage()) {
      $fbApp->redirectAbsolute($fbApp->fanPageTabUrl, self::getRedirectMessage($fbApp->fanPageTabUrl));
      return false;
    }
    
    return parent::preFilter($filterChain);
  }

  public static function getRedirectMessage($url)
  {
    return '<div style="width: 300px; margin-top: 20px; font-family: helvetica, arial, sans-serif; font-size: 13px;">'
        .'<p>Redirecting to '. Yii::app()->name .'...'
        .'<br />Please <a target="_top" href="'. $url .'">click here</a> if you are not redirected.</p>'
        .'</div>';
  }
}