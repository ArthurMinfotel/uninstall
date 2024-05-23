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

class PluginUninstallModelcontainerfield extends CommonDBTM
{
    public $dohistory = true;

    public static $rightname = "uninstall:profile";
    // don't modify the field
    const ACTION_NONE = 0;
    // delete value
    const ACTION_RAZ = 1;
    // set value to new_value
    const ACTION_NEW_VALUE = 2;

    public static function getTypeName($nb = 0)
    {
        return __("Plugin fields field", "uninstall");
    }

    public function rawSearchOptions()
    {
        $tab = [];

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
            'table' => PluginFieldsField::getTable(),
            'field' => 'label',
            'name' => __('Label'),
            'datatype' => 'dropdown',
            'linkfield' => 'plugin_fields_fields_id',
        ];

        $tab[] = [
            'id'            => 3,
            'table'         => PluginFieldsField::getTable(),
            'field'         => 'type',
            'name'          => __("Type"),
            'datatype'      => 'specific',
            'massiveaction' => false,
            'nosearch'      => true,
        ];

        $tab[] = [
            'id'            => 4,
            'table'         => self::getTable(),
            'field'         => 'action',
            'name'          => __("Action"),
            'datatype'      => 'specific',
            'massiveaction' => true,
            'nosearch'      => true,
        ];

        return $tab;
    }

    public function showForm($ID, $options = [])
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        $pluginFieldsField = new PluginFieldsField();
        if ($pluginFieldsField->getFromDB($this->fields['plugin_fields_fields_id'])) {
            echo "<tr class='tab_bg_1 center'>";
            echo "<th colspan='4'>" . __('Field informations', 'uninstall') .
                "</th></tr>";
            echo "<tr class='tab_bg_1 center'>";
            echo "<td>" . __("Label") . " : </td>";
            echo "<td>";
            echo $pluginFieldsField->fields['label'];
            echo "</td>";
            echo "<td>" . __("Type") . " : </td>";
            echo "<td>";
            echo PluginFieldsField::getTypes(true)[$pluginFieldsField->fields['type']];
            echo "</td>";
            echo "</tr>";
            // DEFAULT VALUE
            echo "<tr class='tab_bg_1 center'>";
            echo "<td>" . __('Active') . " :</td>";
            echo "<td>";
            echo $pluginFieldsField->fields["is_active"]  ? __('Yes') : __('No');
            echo "</td>";
            echo "<td>" . __("Mandatory field") . " : </td>";
            echo "<td>";
            echo $pluginFieldsField->fields["mandatory"]  ? __('Yes') : __('No');
            echo "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_1 center'>";
            echo "<th colspan='4'>" . __('Uninstall action', 'uninstall') .
                "</th></tr>";
            echo "<tr class='tab_bg_1 center'>";
            echo "<td>" . __('Action', 'uninstall') . " :</td>";
            echo "<td>";
            $rand = mt_rand();
            $options = [
                self::ACTION_NONE => __("Don't alter", 'uninstall'),
                self::ACTION_RAZ => __('Blank')
            ];
            if ($pluginFieldsField->fields['type'] !== 'glpi_item') {
                $options[self::ACTION_NEW_VALUE] = __('Set value', 'uninstall');
            }
            Dropdown::showFromArray(
                "action",
                $options,
                [
                    'value' => (isset($this->fields["action"])
                        ? $this->fields["action"] : self::ACTION_RAZ),
                    'width' => '100%',
                    'rand' => $rand
                ]
            );
            echo "</td>";
            echo "<td><span id='label-set-value' style='display: none'>".__('New value', 'uninstall')." : </span></td>";
            echo "<td id='container-set-value'>";
            if ($pluginFieldsField->fields['type'] === 'glpi_item') {
                echo __('Action set value is not available for this field type', 'uninstall');
            }
            echo "</td>";
            echo "</tr>";
            $url = Plugin::getWebDir('uninstall') . "/ajax/fieldValueInput.php";
            echo "
            <script>
                $(document).ready(function() {
                    const select = $('#dropdown_action$rand');
                    const label = $('#label-set-value');
                    const inputContainer = $('#container-set-value');
                    select.change(e => {
                        if (e.target.selectedIndex === ".self::ACTION_NEW_VALUE.") {
                            label[0].style.display = '';
                        } else {
                            label[0].style.display = 'none'
                        }
                        inputContainer.load('$url', {
                            'id' : $ID,
                            'action' : e.target.selectedIndex
                        });
                    })
                    select.trigger('change');
                });
            </script>
        ";

            $this->showFormButtons($options);
        } else {
            // TODO warning message + button to delete
        }

        return true;
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        switch ($field) {
            case 'type':
                // TODO why does type never go there ?
                $types = PluginFieldsField::getType(false);
                return $types[$values[$field]];
            case 'action':
                switch ($values[$field]) {
                    case self::ACTION_NONE:
                        return __("Don't alter", 'uninstall');
                    case self::ACTION_RAZ:
                        return __('Blank');
                    case self::ACTION_NEW_VALUE:
                        return __('Set value', 'uninstall');
                }
        }

        return '';
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
                    `plugin_uninstall_modelcontainers_id` int {$default_key_sign} DEFAULT '0',
                    `plugin_fields_fields_id` tinyint NOT NULL DEFAULT '0',
                    `action` int NOT NULL DEFAULT ". self::ACTION_RAZ ." ,
                    `new_value` varchar(255),
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
