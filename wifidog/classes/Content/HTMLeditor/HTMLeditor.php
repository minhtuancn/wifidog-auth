<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

// +-------------------------------------------------------------------+
// | WiFiDog Authentication Server                                     |
// | =============================                                     |
// |                                                                   |
// | The WiFiDog Authentication Server is part of the WiFiDog captive  |
// | portal suite.                                                     |
// +-------------------------------------------------------------------+
// | PHP version 5 required.                                           |
// +-------------------------------------------------------------------+
// | Homepage:     http://www.wifidog.org/                             |
// | Source Forge: http://sourceforge.net/projects/wifidog/            |
// +-------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or     |
// | modify it under the terms of the GNU General Public License as    |
// | published by the Free Software Foundation; either version 2 of    |
// | the License, or (at your option) any later version.               |
// |                                                                   |
// | This program is distributed in the hope that it will be useful,   |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of    |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the     |
// | GNU General Public License for more details.                      |
// |                                                                   |
// | You should have received a copy of the GNU General Public License |
// | along with this program; if not, contact:                         |
// |                                                                   |
// | Free Software Foundation           Voice:  +1-617-542-5942        |
// | 59 Temple Place - Suite 330        Fax:    +1-617-542-2652        |
// | Boston, MA  02111-1307,  USA       gnu@gnu.org                    |
// |                                                                   |
// +-------------------------------------------------------------------+

/**
 * @package    WiFiDogAuthServer
 * @subpackage Content classes
 * @author     Max Horvath <max.horvath@maxspot.de>
 * @copyright  2005 Max Horvath <max.horvath@maxspot.de> - maxspot GmbH
 * @version    CVS: $Id$
 * @link       http://sourceforge.net/projects/wifidog/
 * @todo       Add CSS styles for editors.
 */

require_once BASEPATH.'classes/Dependencies.php';

// Make sure the FCKeditor support is installed
if (Dependencies :: check("FCKeditor", $errmsg)) {
    require_once BASEPATH.'classes/Cache.php';
    require_once BASEPATH.'classes/Content.php';
    require_once BASEPATH.'classes/FormSelectGenerator.php';
    require_once BASEPATH.'classes/LocaleList.php';
    require_once BASEPATH.'classes/Locale.php';

    // FCKeditor class
    require_once BASEPATH.'lib/FCKeditor/fckeditor.php';

    error_reporting(E_ALL);

    /**
     * FCKeditor implementation
     */
    class HTMLeditor extends Content {

        const ALLOWED_HTML_TAGS = "<p><div><pre><address><h1><h2><h3><h4><h5><h6><br><b><strong><i><em><u><span><ol><ul><li><a><img><embed><table><tbody><thead><th><tr><td><hr>";

        /**
         * Constructor. Gets called if FCKeditor is installed.
         * @param int $content_id ID of content.
         * @return void
         */
        protected function __construct($content_id) {
            parent :: __construct($content_id);
            global $db;
            $this->mBd = & $db;
        }

        /**
         * Return string in the language requested by the user.
         * @return string UTF-8 string.
         */
        private function getString() {
            // Init values
            $_retval = null;
            $_row = null;
            $_useCache = false;
            $_cachedData = null;

            // Create new cache objects
            $_cacheLanguage = new Cache('langstrings_' . $this->id . '_substring_' . substr(Locale :: getCurrentLocale()->getId(), 0, 2) . '_string', $this->id);
            $_cache = new Cache('langstrings_' . $this->id . '_substring__string', $this->id);

            // Check if caching has been enabled.
            if ($_cacheLanguage->isCachingEnabled) {
                if ($_cachedData = $_cacheLanguage->getCachedData()) {
                    // Return cached data.
                    $_useCache = true;
                    $_retval = $_cachedData;
                } else {
                    // Language specific cached data has not been found.
                    // Try to get language independent cached data.
                    if ($_cachedData = $_cache->getCachedData()) {
                        // Return cached data.
                        $_useCache = true;
                        $_retval = $_cachedData;
                    }
                }
            }

            if (!$_useCache) {
                // Get string in the prefered language of the user
                $_sql = "SELECT value, locales_id, \n";
                $_sql .= Locale :: getSqlCaseStringSelect(Locale :: getCurrentLocale()->getId());
                $_sql .= " as score FROM langstring_entries WHERE langstring_entries.langstrings_id = '{$this->id}' AND value!='' ORDER BY score LIMIT 1";
                $this->mBd->ExecSqlUniqueRes($_sql, $_row, false);

                if ($_row == null) {
                    // String has not been found
                    $_retval = "(Empty string)";
                } else {
                    // String has been found
                    $_retval = $_row['value'];

                    // Check if caching has been enabled.
                    if ($_cache->isCachingEnabled) {
                        // Save data into cache, because it wasn't saved into cache before.
                        $_cache->saveCachedData($_retval);
                    }
                }
            }

            return $_retval;
        }

        /**
         * Adds the string associated with the locale.
         * @param string $string String to be added.
         * @param string $locale Locale of string (i.e. 'fr_CA') - can be NULL.
         * @param boolean $allow_empty_string Defines if string may be empty
         * (optional).
         * @return boolean True if string has been added, otherwise false.
         */
        private function addString($string, $locale, $allow_empty_string = false) {
            // Init values
            $_retval = false;
            $_id = 'NULL';
            $_idSQL = $_id;

            if ($locale) {
                // Set locale of string
                $_language = new Locale($locale);

                $_id = $_language->GetId();
                $_idSQL = "'" . $_id . "'";
            }

            if ($allow_empty_string || ($string != null && $string != '')) {
                // Save string in database
                $string = $this->mBd->EscapeString($string);
                $this->mBd->ExecSqlUpdate("INSERT INTO langstring_entries (langstring_entries_id, langstrings_id, locales_id, value) VALUES ('" . get_guid() . "', '$this->id', $_idSQL , '$string')", FALSE);

                // Create new cache object.
                $_cache = new Cache('langstrings_' . $this->id . '_substring_' .  $_id . '_string', $this->id);

                // Check if caching has been enabled.
                if ($_cache->isCachingEnabled) {
                    // Remove old cached data.
                    $_cache->eraseCachedData();

                    // Save data into cache.
                    $_cache->saveCachedData($string);
                }

                $_retval = true;
            }

            return $_retval;
        }

        /**
         * Updates the string associated with the locale.
         * @param string $string String to be updated.
         * @param string $locale Locale of string (i.e. 'fr_CA') - can be NULL.
         * @return boolean True if string has been updated, otherwise false.
         */
        private function UpdateString($string, $locale) {
            // Init values
            $_retval = false;
            $_id = 'NULL';
            $_row = null;

            if ($locale) {
                // Set locale of string
                $_language = new Locale($locale);

                $_id = $_language->GetId();
                $_idSQL = "'" . $_id . "'";
            }

            if ($string != null && $string != '') {
                $string = $this->mBd->EscapeString($string);

                // If the update returns 0 (no update), try inserting the record
                $this->mBd->ExecSqlUniqueRes("SELECT * FROM langstring_entries WHERE locales_id = $_idSQL AND langstrings_id = '$this->id'", $_row, false);

                if ($_row != null) {
                    $this->mBd->ExecSqlUpdate("UPDATE langstring_entries SET value = '$string' WHERE langstrings_id = '$this->id' AND locales_id = $_idSQL", false);

                    // Create new cache object.
                    $_cache = new Cache('langstrings_' . $this->id . '_substring_' .  $_id . '_string', $this->id);

                    // Check if caching has been enabled.
                    if ($_cache->isCachingEnabled) {
                        // Remove old cached data.
                        $_cache->eraseCachedData();

                        // Save data into cache.
                        $_cache->saveCachedData($string);
                    }
                } else {
                    $this->addString($string, $locale);
                }

                $_retval = true;
            }
            return $_retval;
        }

        /**
         * Shows the administration interface for HTMLeditor. Gets called if
         * FCKeditor is installed.
         * @param string $type_interface SIMPLE for a small HTML editor, LARGE
         * for a larger HTML editor (default).
         * @param int $num_nouveau Number of new HTML editors to be created.
         * @return string HTML code for the administration interface.
         */
        function getAdminUI($type_interface = 'LARGE', $num_nouveau = 1) {
            // Init values
            $_result = null;
            $_html = '';
            $_languages = new LocaleList();

            $_html .= "<div class='admin_class'>Langstring (" . get_class($this) . " instance)</div>\n";
            $_html .= "<div class='admin_section_container'>\n";

            $_html .= "<ul class='admin_section_list'>\n";

            $_sql = "SELECT * FROM langstring_entries WHERE langstring_entries.langstrings_id = '$this->id' ORDER BY locales_id";
            $this->mBd->ExecSql($_sql, $_result, FALSE);

            // Show existing content
            if ($_result != null) {
                while (list ($_key, $_value) = each($_result)) {
                    $_html .= "<li class='admin_section_list_item'>\n";
                    $_html .= "<div class='admin_section_data'>\n";
                    $_html .= $_languages->GenererFormSelect($_value["locales_id"], "langstrings_" . $this->id . "_substring_" . $_value["langstring_entries_id"] . "_language", 'Langstring::AfficherInterfaceAdmin', TRUE);

                    $_FCKeditor = new FCKeditor('langstrings_' . $this->id . '_substring_' . $_value["langstring_entries_id"] . '_string');
                    $_FCKeditor->BasePath	= BASEPATH . "lib/FCKeditor/";
                    $_FCKeditor->Config["CustomConfigurationsPath"] = BASE_URL_PATH . "js/HTMLeditor.js";
                    $_FCKeditor->Config["AutoDetectLanguage"] = false;
                    $_FCKeditor->Config["DefaultLanguage"] = substr(Locale :: getCurrentLocale()->getId(), 0, 2);
                    $_FCKeditor->Config["StylesXmlPath"] = BASE_URL_PATH . "templates/FCKeditor/css/" . substr(Locale :: getCurrentLocale()->getId(), 0, 2) . ".xml";
                    $_FCKeditor->Config["TemplatesXmlPath"] = BASE_URL_PATH . "templates/FCKeditor/templates/" . substr(Locale :: getCurrentLocale()->getId(), 0, 2) . ".xml";

                    $_FCKeditor->ToolbarSet = "WiFiDOG";

                    $_FCKeditor->Value = $_value['value'];

                    if ($type_interface == 'LARGE') {
                        $_FCKeditor->Height = 400;
                    } else {
                        $_FCKeditor->Height = 200;
                    }

                    $_html .= $_FCKeditor->CreateHtml();

                    $_html .= "</div>\n";
                    $_html .= "<div class='admin_section_tools'>\n";

                    $_name = "langstrings_" . $this->id . "_substring_" . $_value["langstring_entries_id"] . "_erase";
                    $_html .= "<input type='submit' name='$_name' value='" . _("Delete string") . "'>";

                    $_html .= "</div>\n";
                    $_html .= "</li>\n";
                }
            }

            // Editor for new content
            $_locale = LocaleList :: GetDefault();

            $_html .= "<li class='admin_section_list_item'>\n";
            $_html .= "<div class='admin_section_data'>\n";

            $_html .= $_languages->GenererFormSelect($_locale, "langstrings_" . $this->id . "_substring_new_language", 'Langstring::AfficherInterfaceAdmin', TRUE);

            $_FCKeditor = new FCKeditor('langstrings_' . $this->id . '_substring_new_string');
            $_FCKeditor->BasePath	= BASEPATH . "lib/FCKeditor/";
            $_FCKeditor->Config["CustomConfigurationsPath"] = BASE_URL_PATH . "js/HTMLeditor.js";
            $_FCKeditor->Config["AutoDetectLanguage"] = false;
            $_FCKeditor->Config["DefaultLanguage"] = substr(Locale :: getCurrentLocale()->getId(), 0, 2);
            $_FCKeditor->Config["StylesXmlPath"] = BASE_URL_PATH . "templates/FCKeditor/css/" . substr(Locale :: getCurrentLocale()->getId(), 0, 2) . ".xml";
            $_FCKeditor->Config["TemplatesXmlPath"] = BASE_URL_PATH . "templates/FCKeditor/templates/" . substr(Locale :: getCurrentLocale()->getId(), 0, 2) . ".xml";
            $_FCKeditor->ToolbarSet = "WiFiDOG";

            $_FCKeditor->Value = "";

            if ($type_interface == 'LARGE') {
                $_FCKeditor->Height = 400;
            } else {
                $_FCKeditor->Height = 200;
            }

            $_html .= $_FCKeditor->CreateHtml();

            $_html .= "</div>\n";
            $_html .= "<div class='admin_section_tools'>\n";

            $_html .= "<input type='submit' name='langstrings_" . $this->id . "_add_new_entry' value='" . _("Add new string") . "'>";
            $_html .= "</div>\n";
            $_html .= "</li>\n";

            $_html .= "</ul>\n";
            $_html .= "</div>\n";

            return parent :: getAdminUI($_html);
        }

        /**
         * Processes the input of the administration interface for HTMLeditor
         * @return void
         */
        function processAdminUI() {
            // Init values
            $_result = null;

            if ($this->isOwner(User :: getCurrentUser()) || User :: getCurrentUser()->isSuperAdmin()) {
                parent :: processAdminUI();
                $_form_select = new FormSelectGenerator();

                $_sql = "SELECT * FROM langstring_entries WHERE langstring_entries.langstrings_id = '$this->id'";
                $this->mBd->ExecSql($_sql, $_result, FALSE);

                if ($_result != null) {
                    while (list ($_key, $_value) = each($_result)) {
                        $_language = $_form_select->getResult("langstrings_" . $this->id . "_substring_" . $_value["langstring_entries_id"] . "_language", 'Langstring::AfficherInterfaceAdmin');

                        if (empty ($_language)) {
                            $_language = '';
                            $_languageSQL = 'NULL';
                        } else {
                            $_languageSQL = "'" . $_language . "'";
                        }

                        if (!empty ($_REQUEST["langstrings_" . $this->id . "_substring_" . $_value["langstring_entries_id"] . "_erase"]) && $_REQUEST["langstrings_" . $this->id . "_substring_" . $_value["langstring_entries_id"] . "_erase"] == true) {
                            $this->mBd->ExecSqlUpdate("DELETE FROM langstring_entries WHERE langstrings_id = '$this->id' AND langstring_entries_id='" . $_value["langstring_entries_id"] . "'", FALSE);

                            // Create new cache object.
                            $_cache = new Cache('langstrings_' . $this->id . '_substring_' .  $_language . '_string', $this->id);

                            // Check if caching has been enabled.
                            if ($_cache->isCachingEnabled) {
                                // Remove old cached data.
                                $_cache->eraseCachedData();
                            }
                        } else {
                            // Strip HTML tags!
                            $string = $_REQUEST["langstrings_" . $this->id . "_substring_" . $_value["langstring_entries_id"] . "_string"];
                            $string = $this->mBd->EscapeString(strip_tags($string, self :: ALLOWED_HTML_TAGS));
                            $this->mBd->ExecSqlUpdate("UPDATE langstring_entries SET locales_id = " . $_languageSQL . " , value = '$string' WHERE langstrings_id = '$this->id' AND langstring_entries_id='" . $_value["langstring_entries_id"] . "'", FALSE);

                            // Create new cache object.
                            $_cache = new Cache('langstrings_' . $this->id . '_substring_' .  $_language . '_string', $this->id);

                            // Check if caching has been enabled.
                            if ($_cache->isCachingEnabled) {
                                // Remove old cached data.
                                $_cache->eraseCachedData();

                                // Save data into cache.
                                $_cache->saveCachedData($string);
                            }
                        }
                    }
                }

                $_new_substring_name = "langstrings_" . $this->id . "_substring_new_string";
                $_new_substring_submit_name = "langstrings_" . $this->id . "_add_new_entry";
                if ((isset ($_REQUEST[$_new_substring_submit_name]) && $_REQUEST[$_new_substring_submit_name] == true) || !empty ($_REQUEST[$_new_substring_name])) {
                    $_language = $_form_select->getResult("langstrings_" . $this->id . "_substring_new_language", 'Langstring::AfficherInterfaceAdmin');

                    if (empty($_language)) {
                        $_language = null;
                    }

                    $this->addString($_REQUEST[$_new_substring_name], $_language, true);
                }
            }
        }

        /**
         * Retreives the user interface of this object. Anything that overrides
         * this method should call the parent method with it's output at the
         * END of processing.
         * @param string $subclass_admin_interface HTML content of the interface
         * element of a children.
         * @return string The HTML fragment for this interface.
         */
        public function getUserUI($subclass_user_interface = null) {
            $_html = '';
            $_html .= "<div class='user_ui_container'>\n";
            $_html .= "<div class='user_ui_object_class'>Langstring (" . get_class($this) . " instance)</div>\n";
            $_html .= "<div class='langstring'>\n";
            $_html .= $this->getString();
            $_html .= $subclass_user_interface;
            $_html .= "</div>\n";
            $_html .= "</div>\n";

            return parent :: getUserUI($_html);
        }

        /**
         * Reloads the object from the database. Should normally be called after
         * a set operation. This function is private because calling it from a
         * subclass will call the constructor from the wrong scope.
         * @return void
         */
        private function refresh() {
            $this->__construct($this->id);
        }

        /**
         * @see GenericObject
         * @note Persistent content will not be deleted
         */
        public function delete(& $errmsg) {
            // Init values.
            $_retval = false;

            if ($this->isPersistent()) {
                $errmsg = _("Content is persistent (you must make it non persistent before you can delete it)");
            } else {
                global $db;

                if ($this->isOwner(User :: getCurrentUser()) || User :: getCurrentUser()->isSuperAdmin()) {
                    $_sql = "DELETE FROM content WHERE content_id='$this->id'";
                    $db->ExecSqlUpdate($_sql, false);
                    $_retval = true;

                    // Create new cache object.
                    $_cache = new Cache('all', $this->id);

                    // Check if caching has been enabled.
                    if ($_cache->isCachingEnabled) {
                        // Remove old cached data.
                        $_cache->eraseCachedGroupData();
                    }
                } else {
                    $errmsg = _("Access denied (not owner of content)");
                }
            }

            return $_retval;
        }

    }
} else {
    class HTMLeditor extends Content {

        /**
         * Constructor. Gets called if FCKeditor is NOT installed.
         * @param int $content_id ID of content.
         * @return void
         */
        protected function __construct($content_id) {
            parent :: __construct($content_id);
        }

        /**
         * Shows the administration interface for HTMLeditor. Gets called if
         * FCKeditor is NOT installed.
         * @param string $subclass_admin_interface This parameter should stay
         * null.
         * @return string HTML code for the administration interface. Tells the
         * user that the feature is not available.
         */
        public function getAdminUI($subclass_admin_interface = null) {
            $_html = '';
            $_html .= "<div class='admin_class'>FCKeditor (".get_class($this)." instance)</div>\n";
            $_html .= _("FCKeditor is not installed");

            return parent :: getAdminUI($_html);
        }

    }
}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * c-hanging-comment-ender-p: nil
 * End:
 */

?>
