<?php

require_once(__DIR__ . '/crest.php');

class CRestCurrent extends CRest
{
	protected static $dataExt = [];
	protected static function getSettingData()
	{
		$return = static::expandData(file_get_contents(__DIR__ . '/settings.json'));
		if(is_array($return))
		{
			if(!empty(static::$dataExt))
			{
				$return['access_token'] = htmlspecialchars(static::$dataExt['AUTH_ID']);
				$return['domain'] = htmlspecialchars(static::$dataExt['DOMAIN']);
				$return['refresh_token'] = htmlspecialchars(static::$dataExt['REFRESH_ID']);
				$return['application_token'] = htmlspecialchars(static::$dataExt['APP_SID']);
			}
			else
			{
				$return['access_token'] = htmlspecialchars($_REQUEST['AUTH_ID']);
				$return['domain'] = htmlspecialchars($_REQUEST['DOMAIN']);
				$return['refresh_token'] = htmlspecialchars($_REQUEST['REFRESH_ID']);
				$return['application_token'] = htmlspecialchars($_REQUEST['APP_SID']);
			}
		}

		return $return;
	}

	public static function setDataExt($data)
	{
		static::$dataExt = $data;
	}
}