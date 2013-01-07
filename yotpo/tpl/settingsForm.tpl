    <form action="{$action}" method="post">
      <fieldset class="width2" id="tab">
        <legend><img src="../img/admin/cog.gif" alt="" class="middle" />{l s='App Settings' mod='yotpo'}</legend>
        <label>{l s='App key' mod='yotpo'}</label>
        <div class="margin-form">
          <input type="text" name="yotpo_app_key" value="{$appKey}"/>
        </div>
        <label>{l s='Secret token' mod='yotpo'}</label>
        <div class="margin-form">
          <input type="text" name="yotpo_oauth_token" value="{$oauthToken}"/>
        </div>

    <label>{l s='Mail after purchase' mod='yotpo'}</label>
        <div class="margin-form">
          <input type="checkbox" name="yotpo_map_enabled" value="yotpo_map_enabled" {if $mapEnabled}checked="checked"{/if} </>
        </div>
        <input type="submit" name="yotpo_settings" value="{l s='Update' mod='yotpo'}" class="button" />
      </fieldset>
      <a href="https://api.yotpo.com/users/sign_in" target="_blank"><input type="button"  value="Get cradentials" /></a>
    </form>