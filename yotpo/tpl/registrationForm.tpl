    <form action="{$action}" method="post">
      <fieldset class="width2" id="tab">
        <legend><img src="../img/admin/cog.gif" alt="" class="middle" />{l s='Register' mod='yotpo'}</legend>
        <label>{l s='Email address: ' mod='yotpo'}</label>
        <div class="margin-form">
          <input type="text" name="yotpo_user_email" value="{$email}"/>
        </div>
        <label>{l s='Name' mod='yotpo'}</label>
        <div class="margin-form">
          <input type="text" name="yotpo_user_name" value="{$userName}"/>
        </div>
        <label>{l s='Password' mod='yotpo'}</label>
        <div class="margin-form">
          <input type="password" name="yotpo_user_password" value="{$password}"/>
        </div>
        <label>{l s='Confirm password' mod='yotpo'}</label>
        <div class="margin-form">
          <input type="password" name="yotpo_user_confirm_password" value="{$confirmPassword}"/>
        </div>
        <input type="submit" name="yotpo_register" value="{l s='Register' mod='yotpo'}" class="button" />
        <a><input type="submit" name="log_in_button" value="Allready registered?" /></a>
      </fieldset>
    </form>