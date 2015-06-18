<?php namespace Gibson\Wechat;

use Illuminate\Support\Facades\Config;
use Overtrue\Wechat\Input;
use Overtrue\Wechat\Utils\Bag;

/**
 * OAuth 网页授权获取用户信息
 */
class Auth extends \Overtrue\Wechat\Auth
{
	protected $component_id;

	const API_URL           = 'https://open.weixin.qq.com/connect/oauth2/authorize'; //请求CODE
	const API_TOKEN_GET     = 'https://api.weixin.qq.com/sns/oauth2/component/access_token'; //通过code换取access_token
	const API_TOKEN_REFRESH = 'https://api.weixin.qq.com/sns/oauth2/component/refresh_token'; //刷新access_token
	const API_USER          = 'https://api.weixin.qq.com/sns/userinfo';

	public function __construct()
	{
		$this->input = new Input();
		$this->http  = new ComponentHttp(new ComponentAccessToken());
		$this->component_appid = Config::get('wechat::appid');
	}

	/**
	 * 生成oAuth URL
	 *
	 * @param string $to
	 * @param string $scope
	 * @param string $state
	 *
	 * @return string
	 */
	public function url($to = null, $scope = 'snsapi_userinfo', $state = 'STATE')
	{
		$to !== null || $to = Url::current();

		$params = array(
				'appid'         => $this->appId,
				'redirect_uri'  => $to,
				'response_type' => 'code',
				'scope'         => $scope,
				'state'         => $state,
				'component_appid' => $this->component_appid,
		);

		return self::API_URL.'?'.http_build_query($params).'#wechat_redirect';
	}

	public function user()
	{
		$code = $this->input->get('code');
		$appid = $this->input->get('appid');

		if ($this->authorizedUser || !$code || !$appid) {
			return $this->authorizedUser;
		}

		$permission = $this->getAccessPermission($appid, $code);

		if ($permission['scope'] !== 'snsapi_userinfo') {
			$user = new Bag(array('openid' => $permission['openid']));
		} else {
			$user = $this->getUser($permission['openid'], $permission['access_token']);
		}

		return $this->authorizedUser = $user;
	}


	public function getAccessPermission($appid, $code)
	{
		$params = array(
			'appid' => $appid,
			'code' => $code,
			'grant_type' => 'authorization_code',
			'component_appid' => $this->component_appid,
		);

		return $this->lastPermission = $this->http->get(self::API_TOKEN_GET, $params);
	}

	/**
	 * 刷新 access_token
	 *
	 * @param string $appid
	 * @param string $refreshToken
	 *
	 * @return Bag
	 */
	public function refresh($appid, $refreshToken)
	{
		$params = array(
				'appid'         => $appid,
				'grant_type'    => 'refresh_token',
				'refresh_token' => $refreshToken,
				'component_appid' => $this->component_appid,
		);

		$permission = $this->http->get(self::API_TOKEN_REFRESH, $params);

		$this->lastPermission = array_merge($this->lastPermission, $permission);

		return new Bag($permission);
	}
}