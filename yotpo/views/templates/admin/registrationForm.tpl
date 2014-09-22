{if !isset($hide_email_exists) }
<span class="y-red"> Email already exists</span>
{/if}
<div class="y-wrapper">
	
	<div class="y-white-box">
		<form action="{$yotpo_action|escape:'htmlall':'UTF-8'}" method="post">
			<div class="y-page-header"><i class="y-logo"></i>{l s='Create your Yotpo account' mod='yotpo'}</div>
			<fieldset id="y-fieldset">
				<div class="y-header">{l s='Generate more reviews, more engagement, and more sales.' mod='yotpo'}</div>
				<div class="y-label">{l s='Email address:' mod='yotpo'}</div>
				<div class="y-input"><input type="text" name="yotpo_user_email" value="{$yotpo_email|escape:'htmlall':'UTF-8'}" /></div>
				<div class="y-label">{l s='Password' mod='yotpo'}</div>
				<div class="y-input"><input type="password" name="yotpo_user_password" /></div>
				<div class="y-label">{l s='Confirm password' mod='yotpo'}</div>
				<div class="y-input"><input type="password" name="yotpo_user_confirm_password" /></div>
			</fieldset>
			<div class="y-footer"><input type="submit" name="yotpo_register" value="{l s='Register' mod='yotpo'}" class="y-submit-btn" /></div>
			<div class="y-footer">		
			<a href="https://my.yotpo.com/login?origin=prestashop&target=reset" target="_blank">Reset Password</a>
			<a href="https://my.yotpo.com/?utm_source=prestashop_admin&utm_medium=link&utm_campaign=create_new_account_prestashop_admin" target="_blank" >Create new Yotpo account </a>
			</div>
		</form>
	</div>
</div>