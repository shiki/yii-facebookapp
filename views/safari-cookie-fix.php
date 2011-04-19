<?php
/**
 *  Safari fix
 *  @source http://anantgarg.com/2010/02/18/cross-domain-cookies-in-safari/
 */

$this->layout = 'empty';

$pageTabUrl = Yii::app()->fbApplication->fanPageTabUrl;
$baseUrl = Yii::app()->getBaseUrl();

// Note: Js scripts will be inserted by WebApplication through ApplicationBehavior
?>
<html>
<head>
<style type="text/css">
body {font:12px arial;}
#page-loader {position:absolute;top:4px;left:4px;z-index:1000;background-color:#FBB34F;color:#fff;padding:2px 6px;}

</style>
</head>
<body>
  <div id="page-loader">Setting final changes...</div>
  <script type="text/javascript">

  //var isSafari = (/Safari/.test(navigator.userAgent));
  var firstTimeSession = 0;

  function submitSessionForm() {
    if (firstTimeSession == 0) {
      firstTimeSession = 1;
      $("#sessionform").submit();
      setTimeout(processApplication(),2000);
    }
  }

  function processApplication() {
    //alert('ok!');
  }

  $(function(){
    $("body").append('<iframe id="sessionframe" name="sessionframe" onload="submitSessionForm()" src="<?php echo $baseUrl ?>/site/blank" style="display:none;"></iframe><form id="sessionform" enctype="application/x-www-form-urlencoded" action="<?php echo $baseUrl ?>/site/safari" target="sessionframe" action="post"></form>');
    setTimeout(function(){
      $('#page-loader').text('Reloading page... please wait.');
    }, 1000);
  });

  </script>
</body>
</html>