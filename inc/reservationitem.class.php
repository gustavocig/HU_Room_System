<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2015-2016 Teclib'.

 http://glpi-project.org

 based on GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2014 by the INDEPNET Development Team.

 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

/** @file
 * @brief
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * ReservationItem Class
 **/
class ReservationItem extends CommonDBChild {

    /// From CommonDBChild
    static public $itemtype          = 'itemtype';
    static public $items_id          = 'items_id';

    static public $checkParentRights = self::HAVE_VIEW_RIGHT_ON_ITEM;

    static $rightname                = 'reservation';

    const RESERVEANITEM              = 1024;

    public $get_item_to_display_tab = false;
    public $showdebug               = false;


    /**
     * @since version 0.85
     **/
    static function canView() {
        global $CFG_GLPI;

        return Session::haveRightsOr(self::$rightname, array(READ, self::RESERVEANITEM));
    }


    static function getTypeName($nb=0) {
        return _n('Reservable item', 'Reservable items',$nb);
    }


    /**
     * @see CommonGLPI::getMenuName()
     *
     * @since version 0.85
     **/
    static function getMenuName() {
        return Reservation::getTypeName(Session::getPluralNumber());
    }


    /**
     * @see CommonGLPI::getForbiddenActionsForMenu()
     *
     * @since version 0.85
     **/
    static function getForbiddenActionsForMenu() {
        return array('add');
    }


    /**
     * @see CommonGLPI::getAdditionalMenuLinks()
     *
     * @since version 0.85
     **/
    static function getAdditionalMenuLinks() {

        if (static::canView()) {
            return array('showall' => Reservation::getSearchURL(false));
        }
        return false;
    }


    /**
     * @since 0.84
     **/
//   static function canView() {
//      return true;
//   }


    // From CommonDBTM
    /**
     * Retrieve an item from the database for a specific item
     *
     * @param $itemtype   type of the item
     * @param $ID         ID of the item
     *
     * @return true if succeed else false
     **/
    function getFromDBbyItem($itemtype, $ID) {

        return $this->getFromDBByQuery("WHERE `".$this->getTable()."`.`itemtype` = '$itemtype'
                                            AND `".$this->getTable()."`.`items_id` = '$ID'");
    }


    function cleanDBonPurge() {

        $class = new Reservation();
        $class->cleanDBonItemDelete($this->getType(), $this->fields['id']);

        $class = new Alert();
        $class->cleanDBonItemDelete($this->getType(), $this->fields['id']);

    }


    function getSearchOptions() {

        $tab                          = array();

        $tab[4]['table']              = $this->getTable();
        $tab[4]['field']              = 'comment';
        $tab[4]['name']               = __('Comments');
        $tab[4]['datatype']           = 'text';

        $tab[5]['table']              = $this->getTable();
        $tab[5]['field']              = 'is_active';
        $tab[5]['name']               = __('Active');
        $tab[5]['datatype']           = 'bool';

        $tab['common']                = __('Characteristics');

        $tab[1]['table']              = 'reservation_types';
        $tab[1]['field']              = 'name';
        $tab[1]['name']               = __('Name');
        $tab[1]['datatype']           = 'itemlink';
        $tab[1]['massiveaction']      = false;
        $tab[1]['addobjectparams']    = array('forcetab' => 'Reservation$1');

        $tab[2]['table']              = 'reservation_types';
        $tab[2]['field']              = 'id';
        $tab[2]['name']               = __('ID');
        $tab[2]['massiveaction']      = false;
        $tab[2]['datatype']           = 'number';

        $tab[9]['table']              = 'glpi_reservationitems';
        $tab[9]['field']              = '_virtual';
        $tab[9]['name']               = __('Planning');
        $tab[9]['datatype']           = 'specific';
        $tab[9]['massiveaction']      = false;
        $tab[9]['nosearch']           = true;
        $tab[9]['nosort']             = true;
        $tab[9]['additionalfields']   = array('is_active');

        $loc = Location::getSearchOptionsToAdd();
        // Force massive actions to false
        foreach ($loc as $key => $val) {
            $tab[$key]                  = $val;
            $tab[$key]['massiveaction'] = false;
        }

        $tab[6]['table']              = 'reservation_types';
        $tab[6]['field']              = 'otherserial';
        $tab[6]['name']               = __('Inventory number');
        $tab[6]['datatype']           = 'string';

        $tab[16]['table']             = 'reservation_types';
        $tab[16]['field']             = 'comment';
        $tab[16]['name']              = __('Comments');
        $tab[16]['datatype']          = 'text';
        $tab[16]['massiveaction']     = false;

        $tab[70]['table']             = 'glpi_users';
        $tab[70]['field']             = 'name';
        $tab[70]['name']              = __('User');
        $tab[70]['datatype']          = 'dropdown';
        $tab[70]['right']             = 'all';
        $tab[70]['massiveaction']     = false;

        $tab[71]['table']             = 'glpi_groups';
        $tab[71]['field']             = 'completename';
        $tab[71]['name']              = __('Group');
        $tab[71]['datatype']          = 'dropdown';
        $tab[71]['massiveaction']     = false;

        $tab[19]['table']             = 'reservation_types';
        $tab[19]['field']             = 'date_mod';
        $tab[19]['name']              = __('Last update');
        $tab[19]['datatype']          = 'datetime';
        $tab[19]['massiveaction']     = false;

        $tab[23]['table']             = 'glpi_manufacturers';
        $tab[23]['field']             = 'name';
        $tab[23]['name']              = __('Manufacturer');
        $tab[23]['datatype']          = 'dropdown';
        $tab[23]['massiveaction']     = false;

        $tab[24]['table']             = 'glpi_users';
        $tab[24]['field']             = 'name';
        $tab[24]['linkfield']         = 'users_id_tech';
        $tab[24]['name']              = __('Technician in charge of the hardware');
        $tab[24]['datatype']          = 'dropdown';
        $tab[24]['right']             = 'interface';
        $tab[24]['massiveaction']     = false;

        $tab[80]['table']             = 'glpi_entities';
        $tab[80]['field']             = 'completename';
        $tab[80]['name']              = __('Entity');
        $tab[80]['massiveaction']     = false;
        $tab[80]['datatype']          = 'dropdown';
        $tab[80]['massiveaction']     = false;

        return $tab;
    }


    /**
     * @param $item   CommonDBTM object
     **/
    static function showActivationFormForItem(CommonDBTM $item) {



        if (!self::canUpdate()) {
            return false;
        }
        if ($item->getID()) {
            // Recursive type case => need entity right
            if ($item->isRecursive()) {
                if (!Session::haveAccessToEntity($item->fields["entities_id"])) {
                    return false;
                }
            }
        } else {
            return false;
        }

        $ri = new self();

        echo "<div>";
        echo "<table class='tab_cadre_fixe'>";
        echo "<tr><th colspan='2'>".__('Reserve an item')."</th></tr>";
        echo "<tr class='tab_bg_1'>";
        if ($ri->getFromDBbyItem($item->getType(),$item->getID())) {
            echo "<td class='center'>";
            //Switch reservation state

            if ($ri->fields["is_active"]) {
                Html::showSimpleForm(static::getFormURL(), 'update', __('Make unavailable'),
                    array('id'        => $ri->fields['id'],
                        'is_active' => 0));
            } else {
                Html::showSimpleForm(static::getFormURL(), 'update', __('Make available'),
                    array('id'        => $ri->fields['id'],
                        'is_active' => 1));
            }

            echo '</td><td>';
            Html::showSimpleForm(static::getFormURL(), 'purge', __('Prohibit reservations'),
                array('id' => $ri->fields['id']),'','',
                array(__('Are you sure you want to return this non-reservable item?'),
                    __('That will remove all the reservations in progress.')));

            echo "</td>";
        } else {
            echo "<td class='center'>";
            Html::showSimpleForm(static::getFormURL(), 'add', __('Authorize reservations'),
                array('items_id'     => $item->getID(),
                    'itemtype'     => $item->getType(),
                    'entities_id'  => $item->getEntityID(),
                    'is_recursive' => $item->isRecursive(),));
            echo "</td>";
        }
        echo "</tr></table>";
        echo "</div>";
    }


    function showForm($ID, $options=array()) {

        if (!self::canView()) {
            return false;
        }

        $r = new self();

        if ($r->getFromDB($ID)) {
            $type = $r->fields["itemtype"];
            $name = NOT_AVAILABLE;
            if ($item = getItemForItemtype($r->fields["itemtype"])) {
                $type = $item->getTypeName();
                if ($item->getFromDB($r->fields["items_id"])) {
                    $name = $item->getName();
                }
            }

            echo "<div class='center'><form method='post' name=form action='".$this->getFormURL()."'>";
            echo "<input type='hidden' name='id' value='$ID'>";
            echo "<table class='tab_cadre'>";
            echo "<tr><th colspan='2'>".__s('Modify the comment')."</th></tr>";

            // Ajouter le nom du materiel
            echo "<tr class='tab_bg_1'><td>".__('Item')."</td>";
            echo "<td class='b'>".sprintf(__('%1$s'), $name)."</td></tr>\n";

            echo "<tr class='tab_bg_1'><td>".__('Comments')."</td>";
            echo "<td><textarea name='comment' cols='30' rows='10' >".$r->fields["comment"];
            echo "</textarea></td></tr>\n";

            echo "<tr class='tab_bg_2'><td colspan='2' class='top center'>";
            echo "<input type='submit' name='update' value=\""._sx('button','Save')."\" class='submit'>";
            echo "</td></tr>\n";

            echo "</table>";
            Html::closeForm();
            echo "</div>";
            return true;

        }
        return false;
    }

    static function printArray($array) {
        foreach($array as $key => $value) {
            if(is_array($value)) {
                echo "</br>     ArrayKey - ".$key;
                self::printArray($value);
            } else {
                echo "</br>     elemKey - ".$key;
                echo "</br>     elemval - ".$value;
            }
        }

    }


    static function showListSimple() {
        global $DB, $CFG_GLPI;

        if (!Session::haveRight(self::$rightname, self::RESERVEANITEM)) {
            return false;
        }

        $ri         = new self();
        $ok         = false;
        $showentity = Session::isMultiEntitiesMode();
        $values     = array();

        if (isset($_SESSION['glpi_saved']['ReservationItem'])) {
            $_POST = $_SESSION['glpi_saved']['ReservationItem'];
        }

        if (isset($_POST['reserve'])) {
            echo "<div id='viewresasearch'  class='center'>";
            //Toolbox::manageBeginAndEndPlanDates($_POST['reserve']);
            Toolbox::managePOSTPlanTimes($_POST['reserve']);
            echo "<div id='nosearch' class='center firstbloc'>".
                "<a href=\"".$CFG_GLPI['root_doc']."/front/reservationitem.php\">";
            echo "Limpar resultados da pesquisa.</a></div>\n";

        } else {
            //echo "<div id='makesearch' class='center firstbloc'>".
            //     "<a class='pointer' onClick=\"javascript:showHideDiv('viewresasearch','','','');".
            //       "showHideDiv('makesearch','','','')\">";
            //echo __('Find a free item in a specific period')."</a></div>\n";

            echo "<div id='viewresasearch' style=\"display:block;\" class='center'>";
            $begin_time                 = time();
            $begin_time                -= ($begin_time%HOUR_TIMESTAMP);
            //

            //$_POST['reserve']["begin"]  = date("Y-m-d",$begin_time);
            //$_POST['reserve']["end"]    = date("Y-m-d H:i:s",$begin_time+HOUR_TIMESTAMP);
            $_POST['reserve']['day']    = date("Y-m-d",$begin_time);
            $_POST['reserve']["timebegin"]= date("H:m",$begin_time);
            $_POST['reserve']["timeend"]  = date("H:m",$begin_time + HOUR_TIMESTAMP);
            $_POST['reservation_types'] = '';
            $_POST['size'] = '';
            $_POST['videoprojetor'] = '';
            $_POST['tv'] = '';
            $_POST['pc'] = '';
            $_POST['wifi'] = '';
            $_POST['location'] = '';
        }
        echo "<form method='post' name='form' action='".Toolbox::getItemTypeSearchURL(__CLASS__)."'>";
        echo "<table class='tab_cadre_fixe'><tr class='tab_bg_2'>";
        echo "<th colspan='3'>Procure uma sala para sua atividade</th></tr>";



        /**
         * #HOLDAT Controls 'semester' reservation logic
         */
        $currentMonth = date('m');
        $maxMonth = 12;
        if((1 <= $currentMonth) && ($currentMonth <= 6)) {
            $maxMonth = 6;
        }
        $maxDate = '31' . '-' . $maxMonth . '-' . date('Y');


        echo "<tr class='tab_bg_2'><td>Data</td><td>";
        //Html::showDateField("reserve[begin]", array('value'      =>  $_POST['reserve']["begin"],
        Html::showDateField("reserve[day]", array('value'      =>  $_POST['reserve']["day"],
            'maybeempty' => false,
            'readonlyHTML' => true,
            'min' => date('d-m-Y'),
            'max' => $maxDate
        ));
        echo "</td><td rowspan='3'>";
        echo "<input type='submit' class='submit' name='submit' value=\""._sx('button', 'Search')."\">";
        echo "</td></tr>";


        /**
         * #HOLDAT Definition of min and max time values
         */
        $timeMin = 6;
        $timeMax = 23;

        echo "<tr class='tab_bg_2'><td>Horário de Inicio</td><td>";


        /*$randTimeInit = Dropdown::showTimeStamp("reserve[_timebegin]", array('min'        => 7*HOUR_TIMESTAMP,
            'max'        => 23*HOUR_TIMESTAMP,
            'value'      => '',
            'step'       => 60*MINUTE_TIMESTAMP,
            'emptylabel' => 'Especifique o horario de inicio do evento',
            'width'      => '55%'));*/


        $randTimeInit = Dropdown::showSimpleTimeDropdown("reserve[_timebegin]",
            array('value'             => '',
                'width'               => '55%',
                'display_emptychoice' => true,
                'min'                 => $timeMin,
                'max'                 => $timeMax,
                'emptylabel'          => 'Especifique o horario de inicio do evento'));

        echo "<br><div id='time_begin$randTimeInit'></div>";

        $params = array('duration'     => '__VALUE__',
            'begin'          => $_POST['reserve']["timebegin"],
            'name'         => "reserve[timebegin]");

        Ajax::updateItemOnSelectEvent("dropdown_reserve[_timebegin]$randTimeInit", "time_begin$randTimeInit",
            $CFG_GLPI["root_doc"]."/ajax/planningend.php", $params);

        echo "</td></tr>";



        echo "<tr class='tab_bg_2'><td>Horário de Termino</td><td>";
        //$default_delay = floor((strtotime(["end"]) - strtotime($_POST['reserve']["begin"]))
        //        /$CFG_GLPI['time_step']/MINUTE_TIMESTAMP)
        //    *$CFG_GLPI['time_step']*MINUTE_TIMESTAMP;

        /*$randTimeEnd = Dropdown::showTimeStamp("reserve[_timeend]", array('min'        => 7*HOUR_TIMESTAMP,
            'max'        => 23*HOUR_TIMESTAMP,
            'value'      => '',
            'step'       => 60*MINUTE_TIMESTAMP,
            'emptylabel' => 'Especifique o horario de termino do evento',
            'width'      => '55%'));

        $randTimeEnd = Dropdown::showFromArray("reserve[_timeend]", $timeValues,
            array('value'             => '',
                'width'               => '55%',
                'display_emptychoice' => true,
                'emptylabel'          => 'Especifique o horario de termino do evento'));*/
        /**
         * #HOLDAT Finish new time implementation by, finishing the dropdowns and appending it's value with the 'day' value
         * thus becoming a ''date' variable' allowing to be used in SQL
         */
        $randTimeEnd = Dropdown::showSimpleTimeDropdown("reserve[_timeend]",
            array('value'             => '',
                'width'               => '55%',
                'display_emptychoice' => true,
                'min'                 => $timeMin,
                'max'                 => $timeMax,
                'emptylabel'          => 'Especifique o horario de termino do evento'));

        echo "<br><div id='time_end$randTimeEnd'></div>";
        $params = array('duration'     => '__VALUE__',
            'end'          => $_POST['reserve']["timeend"],
            'name'         => "reserve[timeend]");

        Ajax::updateItemOnSelectEvent("dropdown_reserve[_timeend]$randTimeEnd", "time_end$randTimeEnd",
            $CFG_GLPI["root_doc"]."/ajax/planningend.php", $params);
        echo "</td></tr>";




        /*
        *
        * Sessao utilizada para dropdown box de tipos reservaveis
        * Pesquisa com base em tipo de objeto reservavel
        *
        */
        /*
              echo "<tr class='tab_bg_2'><td>".__('Item type')."</td><td>";

              $sql = "SELECT DISTINCT(`itemtype`)
                      FROM `glpi_reservationitems`
                      WHERE `is_active` = 1".
                            getEntitiesRestrictRequest(" AND", 'glpi_reservationitems',
                                                      'entities_id',
                                                      $_SESSION['glpiactiveentities']);

              $result = $DB->query($sql);

              while ($data = $DB->fetch_assoc($result)) {
                 $values[$data['itemtype']] = $data['itemtype']::getTypeName();
              }

              $query = "SELECT `glpi_peripheraltypes`.`name`, `glpi_peripheraltypes`.`id`
                        FROM `glpi_peripheraltypes`
                        LEFT JOIN `glpi_peripherals`
                          ON `glpi_peripherals`.`peripheraltypes_id` = `glpi_peripheraltypes`.`id`
                        LEFT JOIN `glpi_reservationitems`
                          ON `glpi_reservationitems`.`items_id` = `glpi_peripherals`.`id`
                        WHERE `itemtype` = 'Peripheral'
                              AND `is_active` = 1
                              AND `peripheraltypes_id`".
                              getEntitiesRestrictRequest(" AND", 'glpi_reservationitems', 'entities_id',
                                    $_SESSION['glpiactiveentities'])."
                        ORDER BY `glpi_peripheraltypes`.`name`";

              foreach ($DB->request($query) as $ptype) {
                 $id = $ptype['id'];
                 $values["Peripheral#$id"] = $ptype['name'];
              }

              Dropdown::showFromArray("reservation_types", $values,
                                      array('value'               => $_POST['reservation_types'],
                                            'display_emptychoice' => true));

              echo "</td></tr>";*/




        echo "<tr class='tab_bg_2'><td>Videoprojetor</td><td>";
        Dropdown::showFromArray("videoprojetor", array('----','Sim', 'Não'));
        echo "</td></tr>";

        echo "<tr class='tab_bg_2'><td>TV</td><td>";
        Dropdown::showFromArray("tv", array('----','Sim', 'Não'));
        echo "</td></tr>";

        echo "<tr class='tab_bg_2'><td>PC</td><td>";
        Dropdown::showFromArray("pc", array('----','Sim', 'Não'));
        echo "</td></tr>";

        echo "<tr class='tab_bg_2'><td>Wi-Fi</td><td>";
        Dropdown::showFromArray("wifi", array('----','Sim', 'Não'));
        echo "</td></tr>";

        echo "<tr class='tab_bg_2'><td>Local</td><td>";
        Dropdown::showFromArray("location", array('----','GEP', 'HU', 'MEAC'));
        echo "</td></tr>";


        echo "<tr class='tab_bg_2'><td>".'Quantidade de pessoas'."</td><td>";
        $randRoomSizes = mt_rand();
        echo "<div class='no-wrap'>";
        echo "<input id='roomSize" . $randRoomSizes . "' type='text' onfocus='this.value=``' size='4' name='size'>";
        echo "</div>";





        echo "<td rowspan='3'>";
        echo "<a id='special-cases-button' class='vsubmit'>Casos Especiais</a>";



        echo "</td></tr>";


        echo "</table>";
        Html::closeForm();
        echo "</div>";


        if(isset($_POST["submit"])) {

            if ($_POST['reserve']["_timebegin"] < $_POST['reserve']["_timeend"]) {
                // GET method passed to form creation
                echo "<div id='nosearch' class='center'>";
                echo "<form name='form' method='GET' action='reservation.form.php'>";
                echo "<table class='tab_cadre_fixehov'>";
                echo "<tr><th colspan='9'>Salas Disponíveis</th></tr>\n";


                // Index header for row attributes


                /*foreach ($CFG_GLPI["reservation_types"] as $itemtype) {
                   if (!($item = getItemForItemtype($itemtype))) {
                      continue;
                   }
                   $itemtable = getTableForItemType($itemtype);
                   $otherserial = "'' AS otherserial";
                   if ($item->isField('otherserial')) {
                      $otherserial = "`$itemtable`.`otherserial`";
                   }*/

                /**
                 * #HOLDAT Corrects time dropdown problem of passing a POST value
                 * not matching the shown value chosen in the dropdown
                 */
                if ($_POST['reserve']["_timebegin"] != 0) {
                    $_POST['reserve']["_timebegin"] += $timeMin;
                }

                if ($_POST['reserve']["_timeend"] != 0) {
                    $_POST['reserve']["_timeend"] += $timeMin;
                }

                /**
                 * #HOLDAT Reformats time once again, now so that seconds are added
                 * Done so that values comply with the Date type in SQL
                 */
                $begin = Toolbox::addColonAndRightmostZeros($_POST['reserve']["_timebegin"]);
                $end = Toolbox::addColonAndRightmostZeros($_POST['reserve']["_timeend"]);

                $begin = $_POST['reserve']['day'] . ' ' . $begin;
                $end = $_POST['reserve']['day'] . ' ' . $end;

                $tv = ($_POST['tv'] == 0) ? NULL : $_POST['tv'];
                $proj = ($_POST['videoprojetor'] == 0) ? NULL : $_POST['videoprojetor'];
                $pc = ($_POST['pc'] == 0) ? NULL : $_POST['pc'];
                $wifi = ($_POST['wifi'] == 0) ? NULL : $_POST['wifi'];
                $location = ($_POST['location'] == 0) ? NULL : $_POST['location'];

                if ($_POST['size'] === "") {
                    $size = NULL;
                } else {
                    $size = $_POST['size'];
                }

                //}
                //$left  = "";
                //$where = "";
                $exceptRoom = "";
                $exceptLocation = "";
                $projQuery = "";
                $tvQuery = "";
                $pcQuery = "";
                $wifiQuery = "";
                $locationQuery = "";
                //$begin -> end
                //$end -> begin

                $sizeQuery = (isset($size)) ? "OR (`glpi_plugin_room_rooms`.`size` < " . $size . ")" : "";


                if (isset($_POST['submit']) && isset($begin) && isset($end)) {
                    $exceptRoom = "AND 
                            `glpi_plugin_room_rooms`.`id` NOT IN (
                                      SELECT `glpi_plugin_room_rooms`.`id` AS id
                                      FROM `glpi_plugin_room_rooms`
                                      INNER JOIN `glpi_reservations`
                                          ON (`glpi_plugin_room_rooms`.`id` = `glpi_reservations`.`reservationitems_id`
                                             AND  
                                             ('" . $begin . "' < `glpi_reservations`.`end`
                                                AND '" . $end . "' > `glpi_reservations`.`begin`)";

                }

                if (isset($proj)) {
                    if ($proj == 1) {
                        $projQuery = "OR (`glpi_plugin_room_rooms`.`videoprojector` = 0)";
                    } elseif ($proj == 2) {
                        $projQuery = "OR (`glpi_plugin_room_rooms`.`videoprojector` = 1)";
                    }
                }

                if (isset($tv)) {
                    if ($tv == 1) {
                        $tvQuery = "OR (`glpi_plugin_room_rooms`.`tv` = 0)";
                    } elseif ($tv == 2) {
                        $tvQuery = "OR (`glpi_plugin_room_rooms`.`tv` = 1)";
                    }
                }

                if (isset($pc)) {
                    if ($pc == 1) {
                        $pcQuery = "OR (`glpi_plugin_room_rooms`.`computer` = 0)";
                    } elseif ($pc == 2) {
                        $pcQuery = "OR (`glpi_plugin_room_rooms`.`computer` = 1)";
                    }
                }

                if (isset($wifi)) {
                    if ($wifi == 1) {
                        $wifiQuery = "OR (`glpi_plugin_room_rooms`.`wifi` = 0)";
                    } elseif ($wifi == 2) {
                        $wifiQuery = "OR (`glpi_plugin_room_rooms`.`wifi` = 1)";
                    }
                }

                $where = "WHERE `glpi_plugin_room_rooms`.`is_deleted` = 0)";


                /**
                 * #HOLDAT Specification of Room's characteristics exception query string
                 */
                $exceptRoom .= $sizeQuery;
                $exceptRoom .= $projQuery;
                $exceptRoom .= $tvQuery;
                $exceptRoom .= $pcQuery;
                $exceptRoom .= $wifiQuery;
                $exceptRoom .= ")";
                $exceptRoom .= $where;


                /**
                 * #HOLDAT Specification of Location's characteristics exception query string
                 */
                if (isset($location)) {
                    $exceptLocation = "AND 
                                    `glpi_plugin_room_rooms`.`id` NOT IN (
                                        SELECT `glpi_plugin_room_rooms`.`id` AS id
                                        FROM `glpi_plugin_room_rooms`
                                        INNER JOIN `glpi_locations`
                                            ON (`glpi_plugin_room_rooms`.`locations_id` = `glpi_locations`.`id`";

                    if ($location == 1) {
                        $locationQuery = "AND ((`glpi_locations`.`name` = 'HU') OR (`glpi_locations`.`name` = 'MEAC')))";
                    } elseif ($location == 2) {
                        $locationQuery = "AND ((`glpi_locations`.`name` = 'GEP') OR (`glpi_locations`.`name` = 'MEAC')))";
                    } elseif ($location == 3) {
                        $locationQuery = "AND ((`glpi_locations`.`name` = 'GEP') OR (`glpi_locations`.`name` = 'HU')))";
                    }
                    $exceptLocation .= $locationQuery;
                    $exceptLocation .= $where;
                }


                /*if (isset($_POST["reservation_types"]) && !empty($_POST["reservation_types"])) {
                   $tmp = explode('#', $_POST["reservation_types"]);
                   $where .= " AND `glpi_reservationitems`.`itemtype` = '".$tmp[0]."'";
                   if (isset($tmp[1]) && ($tmp[0] == 'Peripheral')
                       && ($itemtype == 'Peripheral')) {
                      $left  .= " LEFT JOIN `glpi_peripheraltypes`
                                     ON (`glpi_peripherals`.`peripheraltypes_id` = `glpi_peripheraltypes`.`id`)";
                      $where .= " AND `$itemtable`.`peripheraltypes_id` = '".$tmp[1]."'";
                   }
                }*/

                /**
                 *
                 * Extra "WHERE" is there for a reason, to guarantee that both the rooms inside the "From" and those outside
                 * select only those that aren't deleted.
                 * Was already tested without, and results ended up including all rooms registered, regardless if they were
                 * deleted or not.
                 *
                 */
                $query = "SELECT DISTINCT `glpi_plugin_room_rooms`.`id` AS id,
                              `glpi_plugin_room_rooms`.`name` AS name,
                              `glpi_plugin_room_rooms`.`size` AS size,
                              `glpi_plugin_room_rooms`.`comment` AS comment,
                              `glpi_plugin_room_rooms`.`videoprojector` AS videoprojetor,
                              `glpi_plugin_room_rooms`.`tv` AS tv,
                              `glpi_plugin_room_rooms`.`computer` AS pc,
                              `glpi_plugin_room_rooms`.`wifi` AS wifi,
                              `glpi_locations`.`name` AS location
                            FROM `glpi_plugin_room_rooms`
                            LEFT JOIN `glpi_reservations`
                                ON (`glpi_plugin_room_rooms`.`id` = `glpi_reservations`.`reservationitems_id`)
                            LEFT JOIN `glpi_locations`
                                ON (`glpi_plugin_room_rooms`.`locations_id` = `glpi_locations`.`id`)
                            WHERE `glpi_plugin_room_rooms`.`is_deleted` = 0
                            ";

                /**
                 * #HOLDAT Specification of query order
                 */
                $order = "ORDER BY size;";


                /**
                 * #HOLDAT Building the complete query
                 */
                $query .= $exceptRoom;
                $query .= $exceptLocation;
                $query .= $order;


                /*
                $query = "SELECT `glpi_reservationitems`.`id`,
                                 `glpi_reservationitems`.`comment`,
                                 `$itemtable`.`name` AS name,
                                 `$itemtable`.`entities_id` AS entities_id,
                                 $otherserial,
                                 `glpi_locations`.`id` AS location,
                                 `glpi_reservationitems`.`items_id` AS items_id
                          FROM `glpi_reservationitems`
                          INNER JOIN `$itemtable`
                               ON (`glpi_reservationitems`.`itemtype` = '$itemtype'
                                   AND `glpi_reservationitems`.`items_id` = `$itemtable`.`id`)
                          $left
                          LEFT JOIN `glpi_locations`
                               ON (`$itemtable`.`locations_id` = `glpi_locations`.`id`)
                          WHERE `glpi_reservationitems`.`is_active` = '1'
                                AND `glpi_reservationitems`.`is_deleted` = '0'
                                AND `$itemtable`.`is_deleted` = '0'
                                $where ".
                                getEntitiesRestrictRequest(" AND", $itemtable, '',
                                                           $_SESSION['glpiactiveentities'],
                                                           $item->maybeRecursive())."
                          ORDER BY `$itemtable`.`entities_id`,
                                   `$itemtable`.`name`";
               */

                if ($result = $DB->query($query)) {
                    if ($result->num_rows > 0) {

                        echo "<tr class='tab_bg_2_header'>
                                <td></td>
                                <td>Sala</td>
                                <td class='td_header'>Capacidade</td>
                                <td class='td_header'>Comentários</td>
                                <td class='td_header'>Possui videoprojetor?</td>
                                <td class='td_header'>Possui TV?</td>
                                <td class='td_header'>Possui PC?</td>
                                <td class='td_header'>Possui Wi-Fi?</td>
                                <td class='td_header'>Local</td>
                               </tr>";


                        while ($row = $DB->fetch_assoc($result)) {

                            echo "<tr class='tab_bg_2_salas'><td>";
                            echo "<input type='checkbox'  name='item[" . $row["id"] . "]' value='" . $row["id"] . "'>" .
                                "</td>";
                            //$typename = $item->getTypeName();
                            /*if ($itemtype == 'Peripheral') {
                               $item->getFromDB($row['items_id']);
                               if (isset($item->fields["peripheraltypes_id"])
                                   && ($item->fields["peripheraltypes_id"] != 0)) {

                                  $typename = Dropdown::getDropdownName("glpi_peripheraltypes",
                                                                        $item->fields["peripheraltypes_id"]);
                               }
                            }*/
                            echo "<td><a href='reservation.php?reservationitems_id=" . $row['id'] . "'>" .
                                sprintf(__('%1$s'), $row["name"]) . "</a></td>";
                            echo "<td>" . nl2br($row["size"]) . "</td>";
                            echo "<td>" . nl2br($row["comment"]) . "</td>";
                            echo ($row["videoprojetor"] == 1) ? "<td>" . nl2br("Sim") . "</td>" : "<td>" . nl2br("Não") . "</td>";
                            echo ($row["tv"] == 1) ? "<td>" . nl2br("Sim") . "</td>" : "<td>" . nl2br("Não") . "</td>";
                            echo ($row["pc"] == 1) ? "<td>" . nl2br("Sim") . "</td>" : "<td>" . nl2br("Não") . "</td>";
                            echo ($row["wifi"] == 1) ? "<td>" . nl2br("Sim") . "</td>" : "<td>" . nl2br("Não") . "</td>";
                            echo "<td>" . nl2br($row["location"]) . "</td>";

                            if ($showentity) {
                                echo "<td>" . Dropdown::getDropdownName("glpi_entities", $row["entities_id"]) .
                                    "</td>";
                            }
                            echo "</tr>\n";
                            $ok = true;

                        }
                    }
                }

                if ($ok) {
                    echo "<tr class='tab_bg_1 center'><td colspan='8'>";
                    if (isset($_POST['reserve'])) {
                        echo Html::hidden('timebegin', array('value' => $begin));
                        echo Html::hidden('timeend', array('value' => $end));
                    }
                    echo "<input type='submit' value=\"Continuar\" class='submit'></td></tr>\n";

                }
                echo "</table>\n";
                echo "<input type='hidden' name='id' value=''>";
                echo "</form>";// No CSRF token needed
                echo "</div>\n";

            } else {
                /**
                 * #HOLDAT Exception room query modal.
                 */
                echo "<div id='errorSearch' class='invisible' title='Pesquisa Inválida'>";
                echo "<p>";
                echo "Horário de inicio passado para pesquisa maior ou igual a horário de termino.</br></br>";
                echo "Por favor, reveja sua pesquisa.";
                echo "</p>";
                echo "</div>";
            }
        }

        echo "<div id='special-cases-dialog' class='invisible' title='Alerta para Casos Especiais'>
                <p>O requerimento de reservas para casos especiais ou não contemplados pelas opções dispostas no sistema,
                é um requerimento mais lento, que passará pela avaliação de um grupo moderador, podendo ser, inclusive, eventualmente negada.</p>
                <p>Qualquer resultado a cerca do requerimento será repassado ao usuário cadastrante através de e-mail.</p>
                <p>Ademais, qualquer requisição formalizada através deste requerimento especial, 
                cuja qual pudesse ser contemplada por opções já presentes no sistema será negada.</p></br>
                <p>Deseja continuar com cadastro?</p></div>";

        echo "<div id='special-cases-forms' class='invisible' title='Criar requisição especial'>
                <p class='validateTips'>Fill all fields</p>";
        echo "<form>
                <fieldset>
                <label for='timeBegin'>Begins</label>
                    <input type='text' name='timeBegin' id='timeBegin' class='text ui-widget-content ui-corner-all' readonly>
                <label for='timeEnd'>Ends</label>
                    <input type='text' name='timeEnd' id='timeEnd' class='text ui-widget-content ui-corner-all' readonly>
                <label for='description'>Description</label>
                    <textarea type='textarea' name='description' id='description' rows='8' cols='70' maxlength='585' required 
                    placeholder='Descreva sua requisição da forma mais completa e detalhada possível. 
                    Exemplo: 'Desejo reservar a Sala X na hora especificada acima toda a primeira segunda-feira de cada mês.' 
                    class='text ui-widget-content ui-corner-all'></textarea>
                <!-- Allow form submission with keyboard without duplicating the dialog button -->
                <input type='Enviar requisição' tabindex='-1' style='position:absolute; top:-1000px'>
                </fieldset>
            </form></div>";


        echo "<div id='special-cases-confirm' class='invisible' title='Confirmação de pedido de requisição'>
                <p>Requisição enviada para analise, confirmação ou rejeição da mesma será informada por e-mail.</p>
              </div>";


        /**
         * #HOLDAT Funcionalidade para que checkbox de salas fique limitado a apenas uma sala
         */
        $js = "$(document).ready(function(){
                                $('input[type=checkbox]').click(function(){
                                    $('input[type=checkbox]').prop('checked', false);
                                    $(this).prop('checked', true);
                                });
                            });";

        /**
         * #HOLDAT Implements query exception handler, by displaying a 'error' modal, to the user.
         */
        $js .= "$(document).ready(function() {
                    $('#errorSearch').dialog({
                        modal: true,
                        draggable: false,
                        resizable: false,
                        closeOnEscape: true,
                        dialogClass: 'no-close',
                        height: 'auto',
                        width: 700,
                        show: true,
                        hide: 'fadeOut',
                        buttons: [
                        {
                            text: 'Fechar',
                            click: function() {
                                $(this).dialog( 'close' );
                            }
                        }
                        ]
                    });
                });";


        /**
         * #HOLDAT Implements modals for the requisition of special reservations.
         * E-mail functionality still needs to be implemented
         */
        $js .="$(document).ready(function(){
                
                
                $('#special-cases-confirm').dialog({
                        autoOpen: false,
	                    resizable: false,
	                    draggable: false,
                        closeOnEscape: true,
                        dialogClass: 'no-close',
	                    height: 'auto',
	                    width: 700,
	                    modal: true,
	                    show: true,
                        hide: 'fadeOut',
	                    buttons: [
                            {
                                text: 'Fechar',
                                click: function() {
                                    $(this).dialog( 'close' );
                                }
                            }
                        ]
                });
                
                
                $('#special-cases-forms').dialog({
                        autoOpen: false,
	                    resizable: false,
	                    draggable: false,
                        closeOnEscape: true,
                        dialogClass: 'no-close',
	                    height: 'auto',
	                    width: 700,
	                    modal: true,
	                    show: true,
                        hide: 'fadeOut',
	                    buttons: [
                            {
                                text: 'Cancelar',
                                click: function() {
                                    $(this).dialog( 'close' );
                                }
                            },
                            {
                                text: 'Enviar',
                                click: function() {
                                    $('#special-cases-confirm').dialog( 'open' );
                                    $('#special-cases-forms').dialog( 'close' );
                                }
                            }
                        ]
                });
                
                
                
                
                $('#special-cases-button').click(function(){
                    $('#special-cases-dialog').dialog({
                        modal: true,
                        draggable: false,
                        resizable: false,
                        closeOnEscape: true,
                        dialogClass: 'no-close',
                        height: 'auto',
	                    width: 700,
                        show: true,
                        hide: 'fadeOut',
                        buttons: [
                            {
                                text: 'Não',
                                click: function() {
                                    $(this).dialog( 'close' );
                                }
                            },
                            {
                                text: 'Sim',
                                click: function() {
                                    $('#special-cases-forms').dialog( 'open' );
                                    $('#special-cases-dialog').dialog( 'close' );
                                }
                            }
                        ]
                    });
                });
            });";

        echo Html::scriptBlock($js);
    }


    /**
     * @param $name
     *
     * @return an array
     **/
    static function cronInfo($name) {
        return array('description' => __('Alerts on reservations'));
    }


    /**
     * Cron action on reservation : alert on end of reservations
     *
     * @param $task to log, if NULL use display (default NULL)
     *
     * @return 0 : nothing to do 1 : done with success
     **/
    static function cronReservation($task=NULL) {
        global $DB, $CFG_GLPI;

        if (!$CFG_GLPI["use_mailing"]) {
            return 0;
        }

        $message        = array();
        $cron_status    = 0;
        $items_infos    = array();
        $items_messages = array();

        foreach (Entity::getEntitiesToNotify('use_reservations_alert') as $entity => $value) {
            $secs = $value * HOUR_TIMESTAMP;

            // Reservation already begin and reservation ended in $value hours
            $query_end = "SELECT `glpi_reservationitems`.*,
                              `glpi_reservations`.`end` AS `end`,
                              `glpi_reservations`.`id` AS `resaid`
                       FROM `glpi_reservations`
                       LEFT JOIN `glpi_alerts`
                           ON (`glpi_reservations`.`id` = `glpi_alerts`.`items_id`
                               AND `glpi_alerts`.`itemtype` = 'Reservation'
                               AND `glpi_alerts`.`type` = '".Alert::END."')
                       LEFT JOIN `glpi_reservationitems`
                           ON (`glpi_reservations`.`reservationitems_id`
                                 = `glpi_reservationitems`.`id`)
                       WHERE `glpi_reservationitems`.`entities_id` = '$entity'
                             AND (UNIX_TIMESTAMP(`glpi_reservations`.`end`) - $secs) < UNIX_TIMESTAMP()
                             AND `glpi_reservations`.`begin` < NOW()
                             AND `glpi_alerts`.`date` IS NULL";

            foreach ($DB->request($query_end) as $data) {
                if ($item_resa = getItemForItemtype($data['itemtype'])) {
                    if ($item_resa->getFromDB($data["items_id"])) {
                        $data['item_name']                     = $item_resa->getName();
                        $data['entity']                        = $entity;
                        $items_infos[$entity][$data['resaid']] = $data;

                        if (!isset($items_messages[$entity])) {
                            $items_messages[$entity] = __('Device reservations expiring today')."<br>";
                        }
                        $items_messages[$entity] .= sprintf(__('%1$s - %2$s'), $item_resa->getTypeName(),
                                $item_resa->getName())."<br>";
                    }
                }
            }
        }

        foreach ($items_infos as $entity => $items) {
            $resitem = new self();
            if (NotificationEvent::raiseEvent("alert", new Reservation(),
                array('entities_id' => $entity,
                    'items'       => $items))) {
                $message     = $items_messages[$entity];
                $cron_status = 1;
                if ($task) {
                    $task->addVolume(1);
                    $task->log(sprintf(__('%1$s: %2$s')."\n",
                        Dropdown::getDropdownName("glpi_entities", $entity),
                        $message));
                } else {
                    //TRANS: %1$s is a name, %2$s is text of message
                    Session::addMessageAfterRedirect(sprintf(__('%1$s: %2$s'),
                        Dropdown::getDropdownName("glpi_entities",
                            $entity),
                        $message));
                }

                $alert             = new Alert();
                $input["itemtype"] = 'Reservation';
                $input["type"]     = Alert::END;
                foreach ($items as $resaid => $item) {
                    $input["items_id"] = $resaid;
                    $alert->add($input);
                    unset($alert->fields['id']);
                }

            } else {
                $entityname = Dropdown::getDropdownName('glpi_entities', $entity);
                //TRANS: %s is entity name
                $msg = sprintf(__('%1$s: %2$s'), $entityname, __('Send reservation alert failed'));
                if ($task) {
                    $task->log($msg);
                } else {
                    Session::addMessageAfterRedirect($msg, false, ERROR);
                }
            }
        }
        return $cron_status;
    }


    /**
     * Display debug information for reservation of current object
     **/
    function showDebugResa() {

        $resa                                = new Reservation();
        $resa->fields['id']                  = '1';
        $resa->fields['reservationitems_id'] = $this->getField('id');
        $resa->fields['begin']               = $_SESSION['glpi_currenttime'];
        $resa->fields['end']                 = $_SESSION['glpi_currenttime'];
        $resa->fields['users_id']            = Session::getLoginUserID();
        $resa->fields['comment']             = '';

        NotificationEvent::debugEvent($resa);
    }


    /**
     * @since version 0.85
     *
     * @see commonDBTM::getRights()
     **/
    function getRights($interface='central') {

        if ($interface == 'central') {
            $values = parent::getRights();
        }
        $values[self::RESERVEANITEM] = __('Make a reservation');

        return $values;
    }


    /**
     * @see CommonGLPI::defineTabs()
     *
     * @since version 0.85
     **/
    function defineTabs($options=array()) {

        $ong = array();
        $this->addStandardTab(__CLASS__, $ong, $options);
        $ong['no_all_tab'] = true;
        return $ong;
    }


    /**
     * @see CommonGLPI::getTabNameForItem()
     *
     * #HOLDAT
     *
     * @since version 0.85
     **/
    function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {

        if ($item->getType() == __CLASS__) {
            if (Session::haveRight("reservation", ReservationItem::RESERVEANITEM)) {
                $tabs[1] = __('Reservation');
            }
            if (($_SESSION["glpiactiveprofile"]["interface"] == "central")
                && Session::haveRight("reservation", READ)) {
                $tabs[2] = __('Administration');
            }
            return $tabs;
        }
        return '';
    }

    /**
     * @param $item         CommonGLPI object
     * @param $tabnum       (default1)
     * @param $withtemplate (default0)
     **/
    static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

        if ($item->getType() == __CLASS__) {
            switch ($tabnum) {
                case 1 :
                    $item->showListSimple();
                    break;

                case 2 :
                    Search::show('ReservationItem');
                    break;
            }
        }
        return true;
    }

    /**
     * @see CommonDBTM::isNewItem()
     *
     * @since version 0.85
     **/
    function isNewItem() {
        return false;
    }

}
?>
