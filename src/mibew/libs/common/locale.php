<?php
/*
 * Copyright 2005-2014 the original author or authors.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

use Mibew\Database;
use Mibew\Plugin\Manager as PluginManager;
use Symfony\Component\Translation\Loader\PoFileLoader;

/**
 * Name for the cookie to store locale code in use
 */
define('LOCALE_COOKIE_NAME', 'mibew_locale');

// Test and set default locales

/**
 * Verified value of the $default_locale configuration parameter (see
 * "libs/default_config.php" for details)
 */
define(
    'DEFAULT_LOCALE',
    locale_pattern_check($default_locale) && locale_exists($default_locale) ? $default_locale : 'en'
);

/**
 * Verified value of the $home_locale configuration parameter (see
 * "libs/default_config.php" for details)
 */
define(
    'HOME_LOCALE',
    locale_pattern_check($home_locale) && locale_exists($home_locale) ? $home_locale : 'en'
);

/**
 * Code of the current system locale
 */
define('CURRENT_LOCALE', get_locale());

function locale_exists($locale)
{
    return file_exists(MIBEW_FS_ROOT . "/locales/$locale/translation.po");
}

function locale_pattern_check($locale)
{
    $locale_pattern = "/^[\w-]{2,5}$/";

    return preg_match($locale_pattern, $locale);
}

function get_available_locales()
{
    if (installation_in_progress()) {
        // We cannot get info from database during installation, thus we only
        // can use discovered locales as available locales.
        // TODO: Remove this workaround after installation will be rewritten.
        return discover_locales();
    }

    // Get list of enabled locales from the database.
    $rows = Database::getInstance()->query(
        "SELECT code FROM {locale} WHERE enabled = 1",
        array(),
        array('return_rows' => Database::RETURN_ALL_ROWS)
    );
    $enabled_locales = array();
    foreach ($rows as $row) {
        $enabled_locales[] = $row['code'];
    }

    $fs_locales = discover_locales();

    return array_intersect($fs_locales, $enabled_locales);
}

/**
 * Returns list of locales which are available and enabled.
 *
 * @return array List of enabled locale codes.
 */
function get_enabled_locales()
{
    return array();
}

/**
 * Returns list of all locales that are present in the file system.
 *
 * @return array List of locales codes.
 */
function discover_locales()
{
    static $list = null;

    if (is_null($list)) {
        $list = array();
        $folder = MIBEW_FS_ROOT . '/locales';
        if ($handle = opendir($folder)) {
            while (false !== ($file = readdir($handle))) {
                if (locale_pattern_check($file) && is_dir("$folder/$file")) {
                    $list[] = $file;
                }
            }
            closedir($handle);
        }
        sort($list);
    }

    return $list;
}

function get_user_locale()
{
    if (isset($_COOKIE[LOCALE_COOKIE_NAME])) {
        $requested_lang = $_COOKIE[LOCALE_COOKIE_NAME];
        if (locale_pattern_check($requested_lang) && locale_exists($requested_lang)) {
            return $requested_lang;
        }
    }

    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $requested_langs = explode(",", $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        foreach ($requested_langs as $requested_lang) {
            if (strlen($requested_lang) > 2) {
                $requested_lang = substr($requested_lang, 0, 2);
            }

            if (locale_pattern_check($requested_lang) && locale_exists($requested_lang)) {
                return $requested_lang;
            }
        }
    }

    if (locale_pattern_check(DEFAULT_LOCALE) && locale_exists(DEFAULT_LOCALE)) {
        return DEFAULT_LOCALE;
    }

    return 'en';
}

function get_locale()
{
    $locale = verify_param("locale", "/./", "");

    // Check if locale code passed in as a param is valid
    $locale_param_valid = $locale
        && locale_pattern_check($locale)
        && locale_exists($locale);

    // Check if locale code stored in session data is valid
    $session_locale_valid = isset($_SESSION['locale'])
        && locale_pattern_check($_SESSION['locale'])
        && locale_exists($_SESSION['locale']);

    if ($locale_param_valid) {
        $_SESSION['locale'] = $locale;
    } elseif ($session_locale_valid) {
        $locale = $_SESSION['locale'];
    } else {
        $locale = get_user_locale();
    }

    setcookie(LOCALE_COOKIE_NAME, $locale, time() + 60 * 60 * 24 * 1000, MIBEW_WEB_ROOT . "/");

    return $locale;
}

function get_locale_links()
{
    // Get list of available locales
    $locale_links = array();
    $all_locales = get_available_locales();
    if (count($all_locales) < 2) {
        return null;
    }

    // Attache locale names
    foreach ($all_locales as $k) {
        $locale_info = get_locale_info($k);
        $locale_links[$k] = $locale_info ? $locale_info['name'] : $k;
    }

    return $locale_links;
}

/**
 * Returns meta data for all known locales.
 *
 * @return array Associative arrays which keys are locale codes and the values
 *   are locales info. Locale info itself is an associative array with the
 *   following keys:
 *     - name: string, human readable locale name.
 *     - rtl: boolean, indicates with the locale uses right-to-left
 *       writing mode.
 *     - time_locale: string, locale code which is used in {@link setlocale()}
 *       function to set the correct date/time formatting.
 *     - date_format: array, list of available date formats. Each key of the
 *       array is format name and each value is a format string for
 *       {@link strftime()} function.
 */
function get_locales()
{
    return array(
        'ar' => array(
            'name' => 'العربية',
            'rtl' => true,
            'time_locale' => 'ar_EG.UTF8',
            'date_format' => array(
                'full' => '%B %d, %Y %I:%M %p',
                'date' => '%B %d, %Y',
                'time' => '%I:%M %p',
            ),
        ),
        'be' => array(
            'name' => 'Беларуская',
            'rtl' => false,
            'time_locale' => 'be_BY.UTF8',
            'date_format' => array(
                'full' => '%d %B %Y, %H:%M',
                'date' => '%d %B %Y',
                'time' => '%H:%M',
            ),
        ),
        'bg' => array(
            'name' => 'Български',
            'rtl' => false,
            'time_locale' => 'bg_BG.UTF8',
            'date_format' => array(
                'full' => '%d %B %Y, %H:%M',
                'date' => '%d %B %Y',
                'time' => '%H:%M',
            ),
        ),
        'ca' => array(
            'name' => 'Català',
            'rtl' => false,
            'time_locale' => 'ca_ES.UTF8',
            'date_format' => array(
                'full' => '%B %d, %Y, %H:%M',
                'date' => '%B %d, %Y',
                'time' => '%H:%M',
            ),
        ),
        'cs' => array(
            'name' => 'Česky',
            'rtl' => false,
            'time_locale' => 'cs_CZ.UTF8',
            'date_format' => array(
                'full' => '%B %d, %Y %I:%M %p',
                'date' => '%B %d, %Y',
                'time' => '%I:%M %p',
            ),
        ),
        'da' => array(
            'name' => 'Dansk',
            'rtl' => false,
            'time_locale' => 'da_DK.UTF8',
            'date_format' => array(
                'full' => '%B %d, %Y %I:%M %p',
                'date' => '%B %d, %Y',
                'time_format' => '%I:%M %p',
            ),
        ),
        'de' => array(
            'name' => 'Deutsch',
            'rtl' => false,
            'time_locale' => 'de_DE.UTF8',
            'date_format' => array(
                'full' => '%B %d, %Y %H:%M',
                'date' => '%B %d, %Y',
                'time' => '%H:%M',
            ),
        ),
        'el' => array(
            'name' => 'Ελληνικά',
            'rtl' => false,
            'time_locale' => 'el_GR.UTF8',
            'date_format' => array(
                'full' => '%B %d, %Y %I:%M %p',
                'date' => '%B %d, %Y',
                'time' => '%I:%M %p',
            ),
        ),
        'en' => array(
            'name' => 'English',
            'rtl' => false,
            'time_locale' => 'en_US',
            'date_format' => array(
                'full' => '%B %d, %Y %I:%M %p',
                'date' => '%B %d, %Y',
                'time' => '%I:%M %p',
            ),
        ),
        'es' => array(
            'name' => 'Español',
            'rtl' => false,
            'time_locale' => 'es_ES.UTF8',
            'date_format' => array(
                'full' => '%B %d, %Y %H:%M',
                'date' => '%B %d, %Y',
                'time' => '%H:%M',
            ),
        ),
        'et' => array(
            'name' => 'Eesti',
            'rtl' => false,
            'time_locale' => 'et_EE.UTF8',
            'date_format' => array(
                'full' => '%B %d, %Y %I:%M %p',
                'date' => '%B %d, %Y',
                'time' => '%I:%M %p',
            ),
        ),
        'fa' => array(
            'name' => 'فارسی',
            'rtl' => true,
            'time_locale' => 'fa_IR.UTF8',
            'date_format' => array(
                'full' => '%B %d, %Y %I:%M %p',
                'date' => '%B %d, %Y',
                'time' => '%I:%M %p',
            ),
        ),
        'fi' => array(
            'name' => 'Suomi',
            'rtl' => false,
            'time_locale' => 'fi_FI.UTF8',
            'date_format' => array(
                'full' => '%B %d, %Y %I:%M %p',
                'date' => '%B %d, %Y',
                'time' => '%I:%M %p',
            ),
        ),
        'fr' => array(
            'name' => 'Français',
            'rtl' => false,
            'time_locale' => 'fr_FR.UTF8',
            'date_format' => array(
                'full' => '%B %d, %Y %H:%M',
                'date' => '%B %d, %Y',
                'time' => '%H:%M',
            ),
        ),
        'he' => array(
            'name' => 'עברית',
            'rtl' => true,
            'time_locale' => 'he_IL.UTF8',
            'date_format' => array(
                'full' => '%B %d, %Y %H:%M',
                'date' => '%B %d, %Y',
                'time' => '%H:%M',
            ),
        ),
        'hr' => array(
            'name' => 'Hrvatski',
            'rtl' => false,
            'time_locale' => 'hr_HR.UTF8',
            'date_format' => array(
                'full' => '%d.%m.%Y %H:%M',
                'date' => '%d.%m.%Y',
                'time' => '%H:%M',
            ),
        ),
        'hu' => array(
            'name' => 'Magyar',
            'rtl' => false,
            'time_locale' => 'hu_HU.UTF8',
            'date_format' => array(
                'full' => '%Y-%B-%d %I:%M %p',
                'date' => '%Y-%B-%d',
                'time' => '%I:%M %p',
            ),
        ),
        'id' => array(
            'name' => 'Bahasa Indonesia',
            'rtl' => false,
            'time_locale' => 'id_ID.UTF8',
            'date_format' => array(
                'full' => '%B %d, %Y %I:%M %p',
                'date' => '%B %d, %Y',
                'time' => '%I:%M %p',
            ),
        ),
        'it' => array(
            'name' => 'Italiano',
            'rtl' => false,
            'time_locale' => 'it_IT.UTF8',
            'date_format' => array(
                'full' => '%d %b %Y, %H:%M',
                'date' => '%d %b %Y',
                'time' => '%H:%M',
            ),
        ),
        'ja' => array(
            'name' => '日本語',
            'rtl' => false,
            'time_locale' => 'ja_JP.UTF8',
            'date_format' => array(
                'full' => '%B %d, %Y %I:%M %p',
                'date' => '%B %d, %Y',
                'time' => '%I:%M %p',
            ),
        ),
        'ka' => array(
            'name' => 'ქართული',
            'rtl' => false,
            'time_locale' => 'ka_GE.UTF8',
            'date_format' => array(
                'full' => '%B %d, %Y %I:%M %p',
                'date' => '%B %d, %Y',
                'time' => '%I:%M %p',
            ),
        ),
        'kk' => array(
            'name' => 'Қазақша',
            'rtl' => false,
            'time_locale' => 'kk_KZ.UTF8',
            'date_format' => array(
                'full' => '%B %d, %Y %I:%M %p',
                'date' => '%B %d, %Y',
                'time' => '%I:%M %p',
            ),
        ),
        'ko' => array(
            'name' => '한국어',
            'rtl' => false,
            'time_locale' => 'ko_KR.UTF8',
            'date_format' => array(
                'full' => '%B %d, %Y %I:%M %p',
                'date' => '%B %d, %Y',
                'time' => '%I:%M %p',
            ),
        ),
        'ky' => array(
            'name' => 'Кыргызча',
            'rtl' => false,
            'time_locale' => 'ky_KG.UTF8',
            'date_format' => array(
                'full' => '%B %d, %Y %I:%M %p',
                'date' => '%B %d, %Y',
                'time' => '%I:%M %p',
            ),
        ),
        'lt' => array(
            'name' => 'Lietuvių',
            'rtl' => false,
            'time_locale' => 'lt_LT.UTF8',
            'date_format' => array(
                'full' => '%d %B %Y %H:%M',
                'date' => '%d %B %Y',
                'time' => '%H:%M',
            )
        ),
        'lv' => array(
            'name' => 'Latviešu',
            'rtl' => false,
            'time_locale' => 'lv_LV.UTF8',
            'date_format' => array(
                'full' => '%B %d, %Y %H:%M',
                'date' => '%B %d, %Y',
                'time' => '%H:%M',
            ),
        ),
        'nl' => array(
            'name' => 'Nederlands',
            'rtl' => false,
            'time_locale' => 'nl_NL.UTF8',
            'date_format' => array(
                'full' => '%B %d, %Y %I:%M %p',
                'date' => '%B %d, %Y',
                'time' => '%I:%M %p',
            ),
        ),
        'nn' => array(
            'name' => 'Norsk nynorsk',
            'rtl' => false,
            'time_locale' => 'nn_NO.UTF8',
            'date_format' => array(
                'full' => '%B %d, %Y %I:%M %p',
                'date' => '%B %d, %Y',
                'time' => '%I:%M %p',
            ),
        ),
        'no' => array(
            'name' => 'Norsk bokmål',
            'rtl' => false,
            'time_locale' => 'no_NO.UTF8',
            'date_format' => array(
                'full' => '%B %d, %Y %I:%M %p',
                'date' => '%B %d, %Y',
                'time' => '%I:%M %p',
            ),
        ),
        'pl' => array(
            'name' => 'Polski',
            'rtl' => false,
            'time_locale' => 'pl_PL.UTF8',
            'date_format' => array(
                'full' => '%B %d, %Y %H:%M',
                'date' => '%B %d, %Y',
                'time' => '%H:%M',
            ),
        ),
        'pt-pt' => array(
            'name' => 'Português',
            'rtl' => false,
            'time_locale' => 'pt_PT.UTF8',
            'date_format' => array(
                'full' => '%d %B, %Y %H:%M',
                'date' => '%d %B, %Y',
                'time' => '%H:%M',
            ),
        ),
        'pt-br' => array(
            'name' => 'Português Brasil',
            'rtl' => false,
            'time_locale' => 'pt_BR.UTF8',
            'date_format' => array(
                'full' => '%d %B, %Y %H:%M',
                'date' => '%d %B, %Y',
                'time' => '%H:%M',
            ),
        ),
        'ro' => array(
            'name' => 'Română',
            'rtl' => false,
            'time_locale' => 'ro_RO.UTF8',
            'date_format' => array(
                'full' => '%B %d, %Y %I:%M %p',
                'date' => '%B %d, %Y',
                'time' => '%I:%M %p',
            ),
        ),
        'ru' => array(
            'name' => 'Русский',
            'rtl' => false,
            'time_locale' => 'ru_RU.UTF8',
            'date_format' => array(
                'full' => '%d %B %Y, %H:%M',
                'date' => '%d %B %Y',
                'time' => '%H:%M',
            ),
        ),
        'sk' => array(
            'name' => 'Slovenčina',
            'rtl' => false,
            'time_locale' => 'sk_SK.UTF8',
            'date_format' => array(
                'full' => '%B %d, %Y %I:%M %p',
                'date' => '%B %d, %Y',
                'time' => '%I:%M %p',
            ),
        ),
        'sl' => array(
            'name' => 'Slovenščina',
            'rtl' => false,
            'time_locale' => 'sl_SI.UTF8',
            'date_format' => array(
                'full' => '%B %d, %Y %I:%M %p',
                'date' => '%B %d, %Y',
                'time' => '%I:%M %p',
            ),
        ),
        'sr' => array(
            'name' => 'Српски',
            'rtl' => false,
            'time_locale' => 'sr_RS.UTF8',
            'date_format' => array(
                'full' => '%B %d, %Y %I:%M %p',
                'date' => '%B %d, %Y',
                'time' => '%I:%M %p',
            ),
        ),
        'sv' => array(
            'name' => 'Svenska',
            'rtl' => false,
            'time_locale' => 'sv_SE.UTF8',
            'date_format' => array(
                'full' => '%B %d, %Y %H:%M',
                'date' => '%B %d, %Y',
                'time' => '%H:%M',
            ),
        ),
        'th' => array(
            'name' => 'ไทย',
            'rtl' => false,
            'time_locale' => 'th_TH.UTF8',
            'date_format' => array(
                'full' => '%d %B, %Y %I:%M %p',
                'date' => '%d %B, %Y',
                'time' => '%I:%M %p',
            ),
        ),
        'tr' => array(
            'name' => 'Türkçe',
            'rtl' => false,
            'time_locale' => 'tr_TR.UTF8',
            'date_format' => array(
                'full' => '%d.%m.%Y %H:%i',
                'date' => '%d.%m.%Y',
                'time' => '%H:%i',
            ),
        ),
        'ua' => array(
            'name' => 'Українська',
            'rtl' => false,
            'time_locale' => 'uk_UA.UTF8',
            'date_format' => array(
                'full' => '%d %B %Y, %H:%M',
                'date' => '%d %B %Y',
                'time' => '%H:%M',
            ),
        ),
        'zh-cn' => array(
            'name' => '中文',
            'rtl' => false,
            'time_locale' => 'zh_CN.UTF8',
            'date_format' => array(
                'full' => '%Y-%m-%d， %H:%M',
                'date' => '%Y-%m-%d',
                'time' => '%H:%M',
            ),
        ),
        'zh-tw' => array(
            'name' => '文言',
            'rtl' => false,
            'time_locale' => 'zh_TW.UTF8',
            'date_format' => array(
                'full' => '%Y-%m-%d， %H:%M',
                'date' => '%Y-%m-%d',
                'time' => '%H:%M',
            ),
        ),
    );
}

/**
 * Returns locale info by its code.
 *
 * It is a wrapper for {@link get_locales()} function and can be used to improve
 * readability of the code.
 *
 * @param string $locale
 * @return array|false Associative array of locale info or boolean false if the
 *   locale is unknown. See {@link get_locales()} description for details of the
 *   info array keys.
 */
function get_locale_info($locale)
{
    $locales = get_locales();

    return isset($locales[$locale]) ? $locales[$locale] : false;
}

/**
 * Load localized messages id some service locale info.
 *
 * Messages are statically cached.
 *
 * @param string $locale Name of a locale whose messages should be loaded.
 * @return array Localized messages array
 */
function load_messages($locale)
{
    static $messages = array();

    if (!isset($messages[$locale])) {
        $messages[$locale] = array();

        if (installation_in_progress()) {
            // Load localization files because we cannot use database during
            // installation.
            $locale_file = MIBEW_FS_ROOT . "/locales/{$locale}/translation.po";
            $locale_data = read_locale_file($locale_file);

            $messages[$locale] = $locale_data['messages'];
        } else {
            // Load active plugins localization
            $plugins_list = array_keys(PluginManager::getAllPlugins());

            foreach ($plugins_list as $plugin_name) {
                // Build plugin path
                list($vendor_name, $plugin_short_name) = explode(':', $plugin_name, 2);
                $plugin_name_parts = explode('_', $plugin_short_name);
                $locale_file = MIBEW_FS_ROOT
                    . "/plugins/" . ucfirst($vendor_name) . "/Mibew/Plugin/"
                    . implode('', array_map('ucfirst', $plugin_name_parts))
                    . "/locales/{$locale}/translation.po";

                // Get localized strings
                if (is_readable($locale_file)) {
                    $locale_data = read_locale_file($locale_file);
                    // array_merge used to provide an ability for plugins to override
                    // localized strings
                    $messages[$locale] = array_merge(
                        $messages[$locale],
                        $locale_data['messages']
                    );
                }
            }

            // Load localizations from the database
            $db = Database::getInstance();
            $db_messages = $db->query(
                'SELECT * FROM {translation} WHERE locale = ?',
                array($locale),
                array(
                    'return_rows' => Database::RETURN_ALL_ROWS
                )
            );

            foreach ($db_messages as $message) {
                $messages[$locale][$message['source']] = $message['translation'];
            }
        }
    }

    return $messages[$locale];
}

/**
 * Imports localized messages from the specified file to the specified locale.
 *
 * @param string $locale Traget locale code.
 * @param string $file Full path to translation file.
 * @param boolean $override Indicates if messages should be overridden or not.
 */
function import_messages($locale, $file, $override = false)
{
    $available_messages = load_messages($locale);
    $locale_data = read_locale_file($file);

    foreach ($locale_data['messages'] as $source => $translation) {
        if (isset($available_messages[$source]) && !$override) {
            continue;
        }

        save_message($locale, $source, $translation);
    }
}


/**
 * Read and parse locale file.
 *
 * @param string $path Locale file path
 * @return array Associative array with following keys:
 *  - 'messages': associative array of localized strings. The keys of the array
 *    are localization keys and the values of the array are localized strings.
 *    All localized strings are encoded in UTF-8.
 */
function read_locale_file($path)
{
    $loader = new PoFileLoader();
    // At this point locale name (the second argument of the "load" method) has
    // no sense, so an empty string is passed in.
    $messages = $loader->load($path, '');

    return array(
        'messages' => $messages->all('messages'),
    );
}

/**
 * Returns localized string.
 *
 * @param string $text A text which should be localized
 * @param array $params Indexed array with placeholders.
 * @param string $locale Target locale code.
 * @param boolean $raw Indicates if the result should be sanitized or not.
 * @return string Localized text.
 */
function getlocal($text, $params = null, $locale = CURRENT_LOCALE, $raw = false)
{
    $string = get_localized_string($text, $locale);

    if ($params) {
        for ($i = 0; $i < count($params); $i++) {
            $string = str_replace("{" . $i . "}", $params[$i], $string);
        }
    }

    return $raw ? $string : sanitize_string($string, 'low', 'moderate');
}

/**
 * Return localized string by its key and locale.
 *
 * Do not use this function manually because it is for internal use only and may
 * be removed soon. Use {@link getlocal()} function instead.
 *
 * @access private
 * @param string $string Localization string key.
 * @param string $locale Target locale code.
 * @return string Localized string.
 */
function get_localized_string($string, $locale)
{
    $localized = load_messages($locale);
    if (isset($localized[$string])) {
        return $localized[$string];
    }

    // The string is not localized, save it to the database to provide an
    // ability to translate it from the UI later.
    if (!installation_in_progress()) {
        save_message($locale, $string, $string);
    }

    // One can change english strings from the UI. Try to use these strings.
    if ($locale != 'en') {
        return get_localized_string($string, 'en');
    }

    // The string is not localized at all. Use it "as is".
    return $string;
}

/**
 * Saves a localized string to the database.
 *
 * @param string $locale Locale code.
 * @param string $key String key.
 * @param string $value Translated string.
 */
function save_message($locale, $key, $value)
{
    $db = Database::getInstance();

    // Check if the string is already in the database.
    list($count) = $db->query(
        'SELECT COUNT(*) FROM {translation} WHERE locale = :locale AND source = :key',
        array(
            ':locale' => $locale,
            ':key' => $key,
        ),
        array(
            'return_rows' => Database::RETURN_ONE_ROW,
            'fetch_type' => Database::FETCH_NUM,
        )
    );
    $exists = ($count != 0);

    // Prepare the value to save in the database.
    $translation = str_replace("\r", "", trim($value));

    if ($exists) {
        // There is no such string in the database. Create it.
        $db->query(
            ('UPDATE {translation} SET translation = :translation '
                . 'WHERE locale = :locale AND source = :key'),
            array(
                ':locale' => $locale,
                ':key' => $key,
                ':translation' => $translation,
            )
        );
    } else {
        // The string is already in the database. Update it.
        $db->query(
            ('INSERT INTO {translation} (locale, source, translation) '
                . 'VALUES (:locale, :key, :translation)'),
            array(
                ':locale' => $locale,
                ':key' => $key,
                ':translation' => $translation,
            )
        );
    }
}

/**
 * Enables specified locale.
 *
 * @param string $locale Locale code according to RFC 5646.
 * @todo Rewrite the function and move somewhere locale creation and its import.
 */
function enable_locale($locale)
{
    $db = Database::getInstance();

    // Check if the locale exists in the database
    list($count) = $db->query(
        "SELECT COUNT(*) FROM {locale} WHERE code = :code",
        array(':code' => $locale),
        array(
            'return_rows' => Database::RETURN_ONE_ROW,
            'fetch_type' => Database::FETCH_NUM,
        )
    );

    if ($count == 0) {
        // The locale does not exist in the database. Create it.
        $db->query(
            "INSERT INTO {locale} (code, enabled) VALUES (:code, :enabled)",
            array(
                ':code' => $locale,
                ':enabled' => 1,
            )
        );

        // Import localized messages to just created locale
        import_messages(
            $locale,
            MIBEW_FS_ROOT . '/locales/' . $locale . '/translation.po',
            true
        );
    } else {
        // The locale exists in the database. Update it.
        $db->query(
            "UPDATE {locale} SET enabled = :enabled WHERE code = :code",
            array(
                ':enabled' => 1,
                ':code' => $locale,
            )
        );
    }
}

/**
 * Disables specified locale.
 *
 * @param string $locale Locale code according to RFC 5646.
 */
function disable_locale($locale)
{
    Database::getInstance()->query(
        "UPDATE {locale} SET enabled = :enabled WHERE code = :code",
        array(
            ':enabled' => 0,
            ':code' => $locale,
        )
    );
}
