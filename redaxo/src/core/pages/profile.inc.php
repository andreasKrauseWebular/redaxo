<?php
/**
 *
 * @package redaxo5
 * @version svn:$Id$
 */

$info = '';
$warning = '';
$user = rex::getUser();
$user_id = $user->getValue('user_id');

// Allgemeine Infos
$userpsw       = rex_request('userpsw', 'string');
$userpsw_new_1 = rex_request('userpsw_new_1', 'string');
$userpsw_new_2 = rex_request('userpsw_new_2', 'string');

$username = rex_request('username', 'string', $user->getName());
$userdesc = rex_request('userdesc', 'string', $user->getValue('description'));
$userlogin = $user->getUserLogin();

// --------------------------------- Title

rex_title(rex_i18n::msg('profile_title'),'');

// --------------------------------- BE LANG

// backend sprache
$sel_be_sprache = new rex_select;
$sel_be_sprache->setStyle('class="rex-form-select"');
$sel_be_sprache->setSize(1);
$sel_be_sprache->setName("userperm_be_sprache");
$sel_be_sprache->setId("userperm-mylang");
$sel_be_sprache->addOption("default","");

$saveLocale = rex_i18n::getLocale();
$langs = array();
foreach(rex_i18n::getLocales() as $locale)
{
	rex_i18n::setLocale($locale,FALSE); // Locale nicht neu setzen
  $sel_be_sprache->addOption(rex_i18n::msg('lang'), $locale);
}
rex_i18n::setLocale($saveLocale, false);
$userperm_be_sprache = rex_request('userperm_be_sprache', 'string', $user->getLanguage());
$sel_be_sprache->setSelected($userperm_be_sprache);


// --------------------------------- FUNCTIONS

if (rex_post('upd_profile_button', 'string'))
{
  $updateuser = rex_sql::factory();
  $updateuser->setTable(rex::getTablePrefix().'user');
  $updateuser->setWhere(array('user_id' => $user_id));
  $updateuser->setValue('name',$username);
  $updateuser->setValue('description',$userdesc);
  $updateuser->setValue('language', $userperm_be_sprache);

  $updateuser->addGlobalUpdateFields();

  try {
    $updateuser->update();
    $info = rex_i18n::msg('user_data_updated');
  } catch (rex_sql_exception $e) {
    $warning = $e->getMessage();
  }
}


if (rex_post('upd_psw_button', 'string'))
{
  $updateuser = rex_sql::factory();
  $updateuser->setTable(rex::getTablePrefix().'user');
  $updateuser->setWhere(array('user_id' => $user_id));

  // the service side encryption of pw is only required
  // when not already encrypted by client using javascript
  if (rex::getProperty('pswfunc') != '' && rex_post('javascript') == '0')
    $userpsw = call_user_func(rex::getProperty('pswfunc'),$userpsw);

  if($userpsw != '' && $user->getValue('password') == $userpsw && $userpsw_new_1 != '' && $userpsw_new_1 == $userpsw_new_2)
  {
    // the service side encryption of pw is only required
    // when not already encrypted by client using javascript
    if (rex::getProperty('pswfunc') != '' && rex_post('javascript') == '0')
      $userpsw_new_1 = call_user_func(rex::getProperty('pswfunc'),$userpsw_new_1);

    $updateuser->setValue('password',$userpsw_new_1);
    $updateuser->addGlobalUpdateFields();

    try {
      $updateuser->update();
      $info = rex_i18n::msg('user_psw_updated');
    } catch (rex_sql_exception $e) {
      $warning = $e->getMessage();
    }
  }else
  {
  	$warning = rex_i18n::msg('user_psw_error');
  }

}


// ---------------------------------- ERR MSG

if ($info != '')
  echo rex_info($info);

if ($warning != '')
  echo rex_warning($warning);

// --------------------------------- FORMS

?>


  <div class="rex-form" id="rex-form-profile">
  <form action="index.php" method="post">
    <fieldset class="rex-form-col-2">
      <legend><?php echo rex_i18n::msg('profile_myprofile'); ?></legend>

      <div class="rex-form-wrapper">
        <input type="hidden" name="page" value="profile" />

        <div class="rex-form-row">
          <p class="rex-form-col-a rex-form-read">
            <label for="userlogin"><?php echo htmlspecialchars(rex_i18n::msg('login_name')); ?></label>
            <span class="rex-form-read" id="userlogin"><?php echo htmlspecialchars($userlogin); ?></span>
          </p>

          <p class="rex-form-col-b rex-form-select">
            <label for="userperm-mylang"><?php echo rex_i18n::msg('backend_language'); ?></label>
            <?php echo $sel_be_sprache->get(); ?>
          </p>
        </div>

        <div class="rex-form-row">
          <p class="rex-form-col-a rex-form-text">
            <label for="username"><?php echo rex_i18n::msg('name'); ?></label>
            <input class="rex-form-text" type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" />
          </p>
          <p class="rex-form-col-b rex-form-text">
            <label for="userdesc"><?php echo rex_i18n::msg('description'); ?></label>
            <input class="rex-form-text" type="text" id="userdesc" name="userdesc" value="<?php echo htmlspecialchars($userdesc); ?>" />
          </p>
        </div>

      </div>
    </fieldset>

    <fieldset class="rex-form-col-1">
      <div class="rex-form-wrapper">
        <div class="rex-form-row">
          <p class="rex-form-col-a rex-form-submit">
            <input class="rex-form-submit" type="submit" name="upd_profile_button" value="<?php echo rex_i18n::msg('profile_save'); ?>" <?php echo rex::getAccesskey(rex_i18n::msg('profile_save'), 'save'); ?> />
          </p>
        </div>
      </div>
    </fieldset>
  </form>
  </div>

<p>&nbsp;</p>

  <div class="rex-form" id="rex-form-profile-psw">
  <form action="index.php" method="post" id="pwformular">
    <input type="hidden" name="javascript" value="0" id="javascript" />
    <fieldset class="rex-form-col-2">
      <legend><?php echo rex_i18n::msg('profile_changepsw'); ?></legend>

      <div class="rex-form-wrapper">
        <input type="hidden" name="page" value="profile" />

        <div class="rex-form-row">
          <p class="rex-form-col-a rex-form-text">
                  <label for="userpsw"><?php echo rex_i18n::msg('old_password'); ?></label>
            <input class="rex-form-text" type="password" id="userpsw" name="userpsw" autocomplete="off" />
          </p>
        </div>


        <div class="rex-form-row">
          <p class="rex-form-col-a rex-form-text">
            <label for="userpsw"><?php echo rex_i18n::msg('new_password'); ?></label>
            <input class="rex-form-text" type="password" id="userpsw_new_1" name="userpsw_new_1" autocomplete="off" />
          </p>
          <p class="rex-form-col-b rex-form-text">
            <label for="userpsw"><?php echo rex_i18n::msg('new_password_repeat'); ?></label>
            <input class="rex-form-text" type="password" id="userpsw_new_2" name="userpsw_new_2" autocomplete="off" />
          </p>
        </div>

      </div>
    </fieldset>

    <fieldset class="rex-form-col-1">
      <div class="rex-form-wrapper">
        <div class="rex-form-row">
          <p class="rex-form-col-a rex-form-submit">
            <input class="rex-form-submit" type="submit" name="upd_psw_button" value="<?php echo rex_i18n::msg('profile_save_psw'); ?>" <?php echo rex::getAccesskey(rex_i18n::msg('profile_save_psw'), 'save'); ?> />
          </p>
        </div>
      </div>
    </fieldset>
  </form>
  </div>

  <script type="text/javascript">
     <!--
    jQuery(function($) {
      $("#username").focus();

      $("#pwformular")
        .submit(function(){
          var pwInp0 = $("#userpsw");
          if(pwInp0.val() != "")
          {
            pwInp0.val(Sha1.hash(pwInp0.val()));
          }

          var pwInp1 = $("#userpsw_new_1");
          if(pwInp1.val() != "")
          {
            pwInp1.val(Sha1.hash(pwInp1.val()));
          }

          var pwInp2 = $("#userpsw_new_2");
          if(pwInp2.val() != "")
          {
            pwInp2.val(Sha1.hash(pwInp2.val()));
          }
      });

      $("#javascript").val("1");
    });
     //-->
  </script>
