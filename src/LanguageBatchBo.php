<?php

namespace Language;

/**
 * Business logic related to generating language files.
 */
class LanguageBatchBo
{
	/**
	 * Contains the applications which ones require translations.
	 *
	 * @var array
	 */
	protected static $applications = array();
	protected static $applets      = array();

    public function __construct() {
        self::$applications = Config::get('system.translated_applications');
        self::$applets = array(
            'memberapplet' => 'JSM2_MemberApplet',
        );
    }
	/**
	 * Starts the language file generation.
	 *
	 * @return void
	 */

	public function generateAppletFiles(){
        $result = new \stdClass();
	    $file = self::generateLanguageFiles();
	    $xml  = self::generateAppletLanguageXmlFiles();

	    $result->return = array(
	        'file' => $file,
            'xml'  => $xml
        );
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        echo json_encode($result->return);
    }

	public static function generateLanguageFiles()
	{
        $result = new \stdClass();
		// The applications where we need to translate.
        $result->TITLE = 'Generating language files';
		foreach (self::$applications as $application => $languages) {
            $result->APPLICATION = $application;
			foreach ($languages as $language) {
                $result->LANGUAGE = $language;
				if (self::getLanguageFile($application, $language)->SUCCESS == 'OK') {
                    $result->SUCCESS = 'OK';
                    $result->MSG     = 'Success to generate language file!';

				} else {
                    $result->SUCCESS = 'NO';
                    $result->MSG     = 'Unable to generate language file!';
				}
			}
		}
		return $result;
	}

	/**
	 * Gets the language file for the given language and stores it.
	 *
	 * @param string $application   The name of the application.
	 * @param string $language      The identifier of the language.
	 *
	 * @throws CurlException   If there was an error during the download of the language file.
	 *
	 * @return bool   The success of the operation.
	 */
	protected static function getLanguageFile($application, $language)
	{
        $result = new \stdClass();
		$languageResponse = ApiCall::call(
			'system_api',
			'language_api',
			array(
				'system' => 'LanguageFiles',
				'action' => 'getLanguageFile'
			),
			array('language' => $language)
		);

        $result->APPLICATION = $application;
        $result->LANGUAGE    = $language;

        if ($languageResponse['status'] != 'OK'){
            $result->SUCCESS = 'NO';
            $result->ERR_MSG = 'Error during getting language file: (' . $application . '/' . $language . ')';
        }else{
            $result->SUCCESS = 'OK';
            $destination = self::getLanguageCachePath($application) . $language . '.php';

            if (!is_dir(dirname($destination))) {
                mkdir(dirname($destination), 0755, true);
            }
            file_put_contents($destination, $languageResponse['data']);
        }
		return $result;
	}

	/**
	 * Gets the directory of the cached language files.
	 *
	 * @param string $application   The application.
	 *
	 * @return string   The directory of the cached language files.
	 */
	protected static function getLanguageCachePath($application)
	{
		return Config::get('system.paths.root') . '/cache/' . $application. '/';
	}

	/**
	 * Gets the language files for the applet and puts them into the cache.
	 *
	 * @throws Exception   If there was an error.
	 *
	 * @return void
	 */
	public static function generateAppletLanguageXmlFiles()
	{
        $result = new \stdClass();
		foreach (self::$applets as $appletDirectory => $appletLanguageId) {
            $result->APPLET_LANG_ID = $appletLanguageId;
            $result->APPLET_DIRR    = $appletDirectory;

			$languages = self::getAppletLanguages($appletLanguageId);
			if ($languages->SUCCESS != 'OK') {
			    $result->MSG = 'There is no available languages for the ' . $appletLanguageId . ' applet.';
			}else {
                $result->MSG = ' - Available languages: ' . $languages->LANG . "\n";
			}
			$path = Config::get('system.paths.root') . '/cache/flash';
			foreach ($languages as $language) {
				$xmlContent = self::getAppletLanguageFile($appletLanguageId, $language);
				$xmlFile    = $path . '/lang_' . $language . '.xml';
                $result->MSG_ERR =  $xmlContent->ERR_MSG;
				if ($xmlContent->SUCCESS == 'OK'){
                    file_put_contents($xmlFile, $xmlContent);
                    $result->MSG_XML = " OK saving $xmlFile was successful.\n";
                }else{
                    $result->MSG_XML = 'Unable to save applet: (' . $appletLanguageId . ') language: (' . $language
                        . ') xml (' . $xmlFile . ')!';

                }
			}
		}
        return $result;
	}

	/**
	 * Gets the available languages for the given applet.
	 *
	 * @param string $applet   The applet identifier.
	 *
	 * @return array   The list of the available applet languages.
	 */
	protected static function getAppletLanguages($applet)
	{
        $result = new \stdClass();
		$response = ApiCall::call(
			'system_api',
			'language_api',
			array(
				'system' => 'LanguageFiles',
				'action' => 'getAppletLanguages'
			),
			array('applet' => $applet)
		);

        $result->LANG = $applet;
        if ($response['status'] != 'OK'){
            $result->SUCCESS = 'NO';
            $result->ERR_MSG = 'Getting languages for applet (' . $applet . ') was unsuccessful';
        }else{
            $result->SUCCESS = 'OK';
            $result->ERR_MSG = '';
        }
		return $result;
	}


	/**
	 * Gets a language xml for an applet.
	 *
	 * @param string $applet      The identifier of the applet.
	 * @param string $language    The language identifier.
	 *
	 * @return string|false   The content of the language file or false if weren't able to get it.
	 */
	protected static function getAppletLanguageFile($applet, $language)
	{
        $result = new \stdClass();
        $response = ApiCall::call(
			'system_api',
			'language_api',
			array(
				'system' => 'LanguageFiles',
				'action' => 'getAppletLanguageFile'
			),
			array(
				'applet' => $applet,
				'language' => $language
			)
		);

        if ($response['status'] != 'OK'){
            $result->SUCCESS = 'NO';
            $result->ERR_MSG = 'Getting language xml for applet: (' . $applet . ') on language: (' . $language . ') was unsuccessful: ';
        }else{
            $result->SUCCESS = 'OK';
            $result->ERR_MSG = '';
        }
		return $result;
	}

	/**
	 * Checks the api call result.
	 *
	 * @param mixed  $result   The api call result to check.
	 *
	 * @throws Exception   If the api call was not successful.
	 *
	 * @return void
	 */
	protected static function checkForApiErrorResult($result)
	{
		// Error during the api call.
		if ($result === false || !isset($result['status'])) {
			throw new \Exception('Error during the api call');
		}
		// Wrong response.
		if ($result['status'] != 'OK') {
			throw new \Exception('Wrong response: '
				. (!empty($result['error_type']) ? 'Type(' . $result['error_type'] . ') ' : '')
				. (!empty($result['error_code']) ? 'Code(' . $result['error_code'] . ') ' : '')
				. ((string)$result['data']));
		}
		// Wrong content.
		if ($result['data'] === false) {
			throw new \Exception('Wrong content!');
		}
	}
}
