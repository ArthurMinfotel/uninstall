<?php

/**
 * -------------------------------------------------------------------------
 * Uninstall plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Uninstall.
 *
 * Uninstall is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Uninstall is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Uninstall. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2015-2023 by Teclib'.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/uninstall
 * -------------------------------------------------------------------------
 */

class PluginUninstallModelcontainer extends CommonDBTM
{
    public $dohistory = true;

    public static $rightname = "uninstall:profile";

    const ACTION_NONE = 0;
    // delete value
    const ACTION_RAZ = 1;
    // set value to new_value
    const ACTION_CUSTOM = 2;

    public static function getTypeName($nb = 0)
    {
        return __("Plugin fields block", "uninstall");
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id' => 'common',
            'name' => self::getTypeName(),
        ];

        $tab[] = [
            'id' => '1',
            'table' => self::getTable(),
            'field' => 'id',
            'name' => __('ID'),
            'massiveaction' => false,
            'datatype' => 'itemlink'
        ];

        $tab[] = [
            'id' => '2',
            'table' => PluginFieldsContainer::getTable(),
            'field' => 'label',
            'name' => __('Block', 'fields'),
            'datatype' => 'dropdown',
            'linkfield' => 'plugin_fields_containers_id',
            'massiveaction' => false
        ];

        $tab[] = [
            'id'            => 3,
            'table'         => PluginFieldsContainer::getTable(),
            'field'         => 'itemtypes',
            'name'          => __("Associated item type"),
            'datatype'      => 'specific',
            'massiveaction' => false
        ];

        return $tab;
    }

    /**
     * Copy from PluginFieldsContainer
     * @param $field
     * @param $values
     * @param array $options
     * @return string
     */
    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        switch ($field) {
            case 'itemtypes':
                $types = json_decode($values[$field]);
                $obj   = '';
                $count = count($types);
                $i     = 1;
                foreach ($types as $type) {
                    // prevent usage of plugin class if not loaded
                    if (!class_exists($type)) {
                        continue;
                    }
                    $name_type = getItemForItemtype($type);
                    $obj .= $name_type->getTypeName(2);
                    if ($count > $i) {
                        $obj .= ", ";
                    }
                    $i++;
                }
                return $obj;
        }

        return '';
    }

    /**
     * Copy from PluginFieldsContainer
     * @param $field_id_or_search_options
     * @param $name
     * @param $values
     * @param $options
     * @return mixed
     */
    public function getValueToSelect($field_id_or_search_options, $name = '', $values = '', $options = [])
    {
        switch ($field_id_or_search_options['table'] . '.' . $field_id_or_search_options['field']) {
            case $this->getTable() . '.itemtypes':
                $options['display'] = false;
                return Dropdown::showFromArray($name, self::getItemtypes(false), $options);
        }

        return parent::getValueToSelect($field_id_or_search_options, $name, $values, $options);
    }

    public function showForm($ID, $options = [])
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        $pluginFieldsContainer = new PluginFieldsContainer();
        if ($pluginFieldsContainer->getFromDB($this->fields['plugin_fields_containers_id'])) {
            echo "<tr class='tab_bg_1 center'>";
            echo "<th colspan='4'>" . __('Block informations', 'uninstall') .
                "</th></tr>";
            echo "<tr class='tab_bg_1 center'>";
            echo "<td>" . __("Label") . " : </td>";
            echo "<td>";
            echo $pluginFieldsContainer->fields['label'];
            echo "</td>";
            echo "<td>" . __("Associated item type") . " : </td>";
            echo "<td>";
            $types = json_decode($pluginFieldsContainer->fields['itemtypes']);
            $obj = '';
            $count = count($types);
            $i = 1;
            foreach ($types as $type) {
                // prevent usage of plugin class if not loaded
                if (!class_exists($type)) {
                    continue;
                }

                $name_type = getItemForItemtype($type);
                $obj .= $name_type->getTypeName(2);
                if ($count > $i) {
                    $obj .= ", ";
                }
                $i++;
            }
            echo $obj;
            echo "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_1 center'>";
            echo "<th colspan='4'>" . __('Uninstall action', 'uninstall') .
                "</th></tr>";
            echo "<tr class='tab_bg_1 center'>";
            echo "<td>" . __('Action', 'uninstall') . " :</td>";
            echo "<td colspan='3'>";
            $rand = mt_rand();
            Dropdown::showFromArray(
                "action",
                [
                    self::ACTION_NONE => __("Don't alter", 'uninstall'),
                    self::ACTION_RAZ => __('Blank'),
                    self::ACTION_CUSTOM => __('Per field action', 'uninstall'),
                ],
                [
                    'value' => (isset($this->fields["action"])
                        ? $this->fields["action"] : self::ACTION_RAZ),
                    'width' => '100%',
                    'rand' => $rand
                ]
            );
            echo "</td>";
            echo "</tr>";
            $this->showFormButtons($options);
        }

        return true;
    }

    public static function install($migration)
    {
        /** @var DBmysql $DB */
        global $DB;

        $default_charset = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

        // first version with this feature
        if (!$DB->tableExists(getTableForItemType(__CLASS__))) {
            $query = "CREATE TABLE IF NOT EXISTS `" . getTableForItemType(__CLASS__) . "` (
                    `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
                    `plugin_uninstall_models_id` int {$default_key_sign} DEFAULT '0',
                    `plugin_fields_containers_id` tinyint NOT NULL DEFAULT '0',
                    `action` int NOT NULL DEFAULT ". self::ACTION_RAZ ." ,
                    PRIMARY KEY (`id`)
                  ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->queryOrDie($query, $DB->error());
        }
        return true;
    }

    public static function uninstall()
    {
        /** @var DBmysql $DB */
        global $DB;

        $DB->query("DROP TABLE IF EXISTS `" . getTableForItemType(__CLASS__) . "`");

        //Delete history
        $log = new Log();
        $log->dohistory = false;
        $log->deleteByCriteria(['itemtype' => __CLASS__]);
    }
}
