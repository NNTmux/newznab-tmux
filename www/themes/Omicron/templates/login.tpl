<!DOCTYPE html>
<html>
{if isset($error) && $error != ''}
<div class="alert alert-danger">{$error}</div>
{/if}
{if isset($notice) && $notice != ''}
<div class="alert alert-info">{$notice}</div>
{/if}
  <head>
    <meta charset="UTF-8">
    <title>{$site->title} | Log in</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <!-- Bootstrap 3.3.6 -->
    <link href="{$smarty.const.WWW_THEMES}/shared/libs/bootstrap/v3/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <!-- Font Awesome Icons -->
    <link href="{$smarty.const.WWW_THEMES}/shared/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <!-- Theme style -->
    <link href="{$smarty.const.WWW_THEMES}/{$theme}/dist/css/AdminLTE.min.css" rel="stylesheet" type="text/css" />
    <!-- iCheck -->
    <link href="{$smarty.const.WWW_THEMES}/{$theme}/plugins/iCheck/square/blue.css" rel="stylesheet" type="text/css" />
    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
        <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
  </head>
  <body class="login-page">
    <div class="login-box">
		<div class="login-logo">
			<a href="{$serverroot}"><b>{$site->title}</b></a>
		</div><!-- /.login-logo -->
      <div class="login-box-body">
        <p class="login-box-msg">Please sign in to access the site</p>
        <form action="login" method="post">
          {if isset($redirect)}
          <input type="hidden" name="redirect" value="{$redirect|escape:"htmlall"}" />
          {/if}
          <div class="form-group has-feedback">
            <input id="username" name="username" type="text" class="form-control" placeholder="Username"/>
            <span class="glyphicon glyphicon-envelope form-control-feedback"></span>
          </div>
          <div class="form-group has-feedback">
            <input id="password" name="password" type="password" class="form-control" placeholder="Password"/>
            <span class="glyphicon glyphicon-lock form-control-feedback"></span>
          </div>
          <div class="row">
            <div class="col-xs-8">
              <div class="checkbox icheck">
                <label>
                  <input id="rememberme" {if isset($rememberme) && $rememberme == 1}checked="checked"{/if} name="rememberme" type="checkbox"> Remember Me
                </label>
				  <hr>
                <div style="text-align: center;">
				  {$page->smarty->fetch('captcha.tpl')}
                </div>
              </div>
            </div><!-- /.col -->
            <div class="col-xs-4">
              <button type="submit" class="btn btn-primary btn-block btn-flat">Sign In</button>
            </div><!-- /.col -->
          </div>
        </form>
		  <a href="{$smarty.const.WWW_TOP}forgottenpassword" class="text-center">I forgot my password</a><br>
        <a href="{$smarty.const.WWW_TOP}register" class="text-center">Register a new membership</a>
    <!-- jQuery 2.1.4 -->
    <script src="{$smarty.const.WWW_THEMES}/{$theme}/plugins/jQuery/jQuery-2.1.4.min.js"></script>
    <!-- Bootstrap 3.3.6 JS -->
    <script src="{$smarty.const.WWW_THEMES}/shared/libs/bootstrap/v3/js/bootstrap.min.js" type="text/javascript"></script>
    <!-- iCheck -->
    <script src="{$smarty.const.WWW_THEMES}/{$theme}/plugins/iCheck/icheck.min.js" type="text/javascript"></script>
    <script>
      $(function () {
        $('input').iCheck({
          checkboxClass: 'icheckbox_square-blue',
          radioClass: 'iradio_square-blue',
          increaseArea: '20%' // optional
        });
      });
    </script>
  </body>
</html>
