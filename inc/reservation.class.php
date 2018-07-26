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
 * Reservation Class
 **/
class Reservation extends CommonDBChild
{

    // From CommonDBChild
    static public $itemtype = 'ReservationItem';
    static public $items_id = 'reservationitems_id';

    static $rightname = 'reservation';
    static public $checkParentRights = self::HAVE_VIEW_RIGHT_ON_ITEM;


    /**
     * @param $nb  integer  for singular or plural
     **/
    static function getTypeName($nb = 0)
    {
        return _n('Reservation', 'Reservations', $nb);
    }


    /**
     * @see CommonGLPI::getTabNameForItem()
     **/
    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {

        if (!$withtemplate
            && Session::haveRight("reservation", READ)) {
            return self::getTypeName(Session::getPluralNumber());
        }
        return '';
    }


    /**
     * @param $item         CommonGLPI object
     * @param $tabnum (default1)
     * @param $withtemplate (default0)
     **/
    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {


        if ($item->getType() == 'User') {
            self::showForUser($_GET["id"]);
        } else {
            self::showForItem($item);
        }
        return true;
    }


    function pre_deleteItem()
    {
        global $CFG_GLPI;

        if (isset($this->fields["users_id"])
            && (($this->fields["users_id"] === Session::getLoginUserID())
                || Session::haveRight("reservation", DELETE))) {

            // Processing Email
            if (!isset($this->input['_disablenotif']) && $CFG_GLPI["use_mailing"]) {
                NotificationEvent::raiseEvent("delete", $this);
            }
        }
        return true;
    }


    /**
     * @see CommonDBChild::prepareInputForUpdate()
     **/
    function prepareInputForUpdate($input)
    {

        $item = 0;
        if (isset($input['_item'])) {
            $item = $_POST['_item'];
        }

        // Save fields
        $oldfields = $this->fields;
        // Needed for test already planned
        if (isset($input["begin"])) {
            $this->fields["begin"] = $input["begin"];
        }
        if (isset ($input["end"])) {
            $this->fields["end"] = $input["end"];
        }

        if (!$this->test_valid_date()) {
            $this->displayError("date", $item);
            return false;
        }

        if ($this->is_reserved()) {
            $this->displayError("is_res", $item);
            return false;
        }

        // Restore fields
        $this->fields = $oldfields;

        return parent::prepareInputForUpdate($input);
    }


    /**
     * @see CommonDBTM::post_updateItem()
     **/
    function post_updateItem($history = 1)
    {
        global $CFG_GLPI;

        if (count($this->updates)
            && $CFG_GLPI["use_mailing"]
            && !isset($this->input['_disablenotif'])) {
            NotificationEvent::raiseEvent("update", $this);
            //$mail = new MailingResa($this,"update");
            //$mail->send();
        }

        parent::post_updateItem($history);
    }


    /**
     * @see CommonDBChild::prepareInputForAdd()
     **/
    function prepareInputForAdd($input)
    {

        // Error on previous added reservation on several add
        if (isset($input['_ok']) && !$input['_ok']) {
            return false;
        }

        // set new date.
        $this->fields["reservationitems_id"] = $input["reservationitems_id"];
        $this->fields["begin"] = $input["begin"];
        $this->fields["end"] = $input["end"];

        if (!$this->test_valid_date()) {
            $this->displayError("date", $input["reservationitems_id"]);
            return false;
        }

        if ($this->is_reserved()) {
            $this->displayError("is_res", $input["reservationitems_id"]);
            return false;
        }

        return parent::prepareInputForAdd($input);
    }


    function post_addItem()
    {
        global $CFG_GLPI;

        if (!isset($this->input['_disablenotif']) && $CFG_GLPI["use_mailing"]) {
            NotificationEvent::raiseEvent("new", $this);
        }

        parent::post_addItem();
    }


    // SPECIFIC FUNCTIONS

    /**
     * @param $reservationitems_id
     **/
    function getUniqueGroupFor($reservationitems_id)
    {
        global $DB;

        do {
            $rand = mt_rand(1, mt_getrandmax());

            $query = "SELECT COUNT(*) AS CPT
                   FROM `glpi_reservations`
                   WHERE `reservationitems_id` = '$reservationitems_id'
                         AND `group` = '$rand';";
            $result = $DB->query($query);
            $count = $DB->result($result, 0, 0);
        } while ($count > 0);

        return $rand;
    }


    /**
     * Is the item already reserved ?
     *
     * @return boolean
     **/
    function is_reserved()
    {
        global $DB;

        if (!isset($this->fields["reservationitems_id"])
            || empty($this->fields["reservationitems_id"])) {
            return true;
        }

        // When modify a reservation do not itself take into account
        $ID_where = "";
        if (isset($this->fields["id"])) {
            $ID_where = " `id` <> '" . $this->fields["id"] . "' AND ";
        }
        $query = "SELECT *
                FROM `" . $this->getTable() . "`
                WHERE $ID_where
                      `reservationitems_id` = '" . $this->fields["reservationitems_id"] . "'
                      AND '" . $this->fields["begin"] . "' < `end`
                      AND '" . $this->fields["end"] . "' > `begin`";
        if ($result = $DB->query($query)) {
            return ($DB->numrows($result) > 0);
        }
        return true;
    }


    /**
     * Current dates are valid ? begin before end
     *
     * @return boolean
     **/
    function test_valid_date()
    {

        return (!empty($this->fields["begin"])
            && !empty($this->fields["end"])
            && (strtotime($this->fields["begin"]) < strtotime($this->fields["end"])));
    }


    /**
     * display error message
     *
     * @param $type   error type : date / is_res / other
     * @param $ID     ID of the item
     *
     * @return nothing
     **/
    function displayError($type, $ID)
    {

        echo "<br><div class='center'>";
        switch ($type) {
            case "date" :
                _e('Error in entering dates. The starting date is later than the ending date');
                break;

            case "is_res" :
                _e('The required item is already reserved for this timeframe');
                break;

            default :
                _e("Unknown error");
        }

        echo "<br><a href='reservation.php?reservationitems_id=$ID'>" . __('Back to planning') . "</a>";
        echo "</div>";
    }


    /**
     * @since version 0.84
     **/
    static function canCreate()
    {
        return (Session::haveRight(self::$rightname, ReservationItem::RESERVEANITEM));
    }


    /**
     * @since version 0.84
     **/
    static function canUpdate()
    {
        return (Session::haveRight(self::$rightname, ReservationItem::RESERVEANITEM));
    }


    /**
     * @since version 0.84
     **/
    static function canDelete()
    {
        return (Session::haveRight(self::$rightname, ReservationItem::RESERVEANITEM));
    }


    /**
     * Overload canChildItem to make specific checks
     * @since version 0.84
     **/
    function canChildItem($methodItem, $methodNotItem)
    {

        // Original user always have right
        if ($this->fields['users_id'] === Session::getLoginUserID()) {
            return true;
        }

        if (!parent::canChildItem($methodItem, $methodNotItem)) {
            return false;
        }

        $ri = $this->getItem();
        if ($ri === false) {
            return false;
        }

        $item = $ri->getItem();
        if ($item === false) {
            return false;
        }

        return Session::haveAccessToEntity($item->getEntityID());
    }


    function post_purgeItem()
    {
        global $DB;

        if (isset($this->input['_delete_group']) && $this->input['_delete_group']) {
            $query = "SELECT *
                   FROM `glpi_reservations`
                   WHERE `reservationitems_id` = '" . $this->fields['reservationitems_id'] . "'
                         AND `group` = '" . $this->fields['group'] . "' ";
            $rr = clone $this;
            foreach ($DB->request($query) as $data) {
                $rr->delete(array('id' => $data['id']));
            }
        }
    }


    /**
     * Show reservation calendar
     *
     * @param $ID   ID of the reservation item (if empty display all) (default '')
     **/
    static function showCalendar($ID = "")
    {
        global $DB, $CFG_GLPI;

        if (!Session::haveRight("reservation", ReservationItem::RESERVEANITEM)) {
            return false;
        }

        if (!isset($_GET["mois_courant"])) {
            $mois_courant = intval(strftime("%m"));
        } else {
            $mois_courant = $_GET["mois_courant"];
        }

        if (!isset($_GET["annee_courante"])) {
            $annee_courante = strftime("%Y");
        } else {
            $annee_courante = $_GET["annee_courante"];
        }

        $mois_courant = intval($mois_courant);
        $mois_suivant = $mois_courant + 1;
        $mois_precedent = $mois_courant - 1;
        $annee_suivante = $annee_courante;
        $annee_precedente = $annee_courante;

        if ($mois_precedent == 0) {
            $mois_precedent = 12;
            $annee_precedente--;
        }

        if ($mois_suivant == 13) {
            $mois_suivant = 1;
            $annee_suivante++;
        }

        $monthsarray = Toolbox::getMonthsOfYearArray();

        $str_suivant = "?reservationitems_id=$ID&amp;mois_courant=$mois_suivant&amp;" .
            "annee_courante=$annee_suivante";
        $str_precedent = "?reservationitems_id=$ID&amp;mois_courant=$mois_precedent&amp;" .
            "annee_courante=$annee_precedente";

        if (!empty($ID)) {
            $m = new ReservationItem();
            $m->getFromDB($ID);

            if ((!isset($m->fields['is_active'])) || !$m->fields['is_active']) {
                echo "<div class='center'>";
                echo "<table class='tab_cadre_fixe'>";
                echo "<tr class='tab_bg_2'>";
                echo "<td class='center b'>" . __('Device temporarily unavailable') . "</td></tr>";
                echo "<tr class='tab_bg_1'><td class='center b'>";
                Html::displayBackLink();
                echo "</td></tr>";
                echo "</table>";
                echo "</div>";
                return false;
            }
            $type = $m->fields["itemtype"];
            $name = NOT_AVAILABLE;
            if ($item = getItemForItemtype($m->fields["itemtype"])) {
                $type = $item->getTypeName();

                if ($item->getFromDB($m->fields["items_id"])) {
                    $name = $item->getName();
                }
                $name = sprintf(__('%1$s'), $name);
            }

            $all = "<a class='vsubmit' href='reservation.php?reservationitems_id=&amp;mois_courant=" .
                "$mois_courant&amp;annee_courante=$annee_courante'>" . __('Show all') . "</a>";

        } else {
            $type = "";
            $name = "Todas as salas reservaveis";
            $all = "&nbsp;";
        }

        echo "<div class='center'><table class='tab_glpi'><tr><td>";
        echo "<img src='" . $CFG_GLPI["root_doc"] . "/pics/reservation.png' alt='' title=''></td>";
        echo "<td class ='b'>" . $name . "</td></tr>";
        echo "<tr><td colspan='2' class ='center'>$all</td></tr></table></div><br>\n";


        /**
         * #HOLDAT Shows Room tabs accordingly to MySQL query, showing it's name, comment as 'title' and ordered alphabetically
         * A fodder span is used to align the right margin of the buttons with the page's calendar.
         * Another fodder span is used to separate between buttons, using 5px as distance.
         * Sometimes CSS changes aren't immediately visible, needing things, such as, changing browser, to be able to visualize.
         */
        $query = "SELECT DISTINCT `glpi_plugin_room_rooms`.`id` AS id,
                                `glpi_plugin_room_rooms`.`name` AS name,
                                `glpi_plugin_room_rooms`.`comment` AS comment
                FROM `glpi_plugin_room_rooms`
                LEFT JOIN `glpi_reservations`
                    ON (`glpi_plugin_room_rooms`.`id` = `glpi_reservations`.`reservationitems_id`)
                WHERE `glpi_plugin_room_rooms`.`is_deleted` = 0
                ORDER BY `glpi_plugin_room_rooms`.`name`";

        if ($result = $DB->query($query)) {
            if ($result->num_rows > 0) {
                echo "<div>
                    <span class='fodder1'></span>";
                while ($row = $DB->fetch_assoc($result)) {
                    echo "<a class='vsubmit tabSala' href='../front/reservation.php?reservationitems_id=" . $row['id'] .
                        "' title='Mostrar calendário - " . $row['comment'] . "'>" . $row['name'] . "</a>";
                    echo "<span class='fodderTabSala'></span>";
                }
                echo "</div>";
            }
        }


        // Check bisextile years
        if (($annee_courante % 4) == 0) {
            $fev = 29;
        } else {
            $fev = 28;
        }
        $nb_jour = array(31, $fev, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);

        // Datas used to put right information in columns
        $jour_debut_mois = strftime("%w", mktime(0, 0, 0, $mois_courant, 1, $annee_courante));
        if ($jour_debut_mois == 0) {
            $jour_debut_mois = 7;
        }
        $jour_fin_mois = strftime("%w", mktime(0, 0, 0, $mois_courant, $nb_jour[$mois_courant - 1],
            $annee_courante));

        echo "<div class='center'>";
        echo "<table class='tab_glpi'><tr><td><a href='reservation.php" . $str_precedent . "'>";
        echo "<img src='" . $CFG_GLPI["root_doc"] . "/pics/left.png' alt=\"" . __s('Previous') .
            "\" title=\"" . __s('Previous') . "\"></a></td>";
        echo "<td class='b'>" . sprintf(__('%1$s %2$s'), $monthsarray[$mois_courant], $annee_courante) .
            "</td>";
        echo "<td><a href='reservation.php" . $str_suivant . "'>";
        echo "<img src='" . $CFG_GLPI["root_doc"] . "/pics/right.png' alt=\"" . __s('Next') .
            "\" title=\"" . __s('Next') . "\"></a></td></tr></table>\n";

        // test
        echo "<table width='90%' class='tab_glpi'><tr><td class='top' width='100px'>";

        echo "<table><tr><td width='100px' class='top'>";

        // today date
        $today = getdate(time());
        $mois = $today["mon"];
        $annee = $today["year"];

        $annee_avant = $annee_courante - 1;
        $annee_apres = $annee_courante + 1;


        /**
         * #HOLDAT Adds previous and next year 'toggle' function.
         */

        echo "<div class='calendrier_mois'>";
        echo "<div class='center b ' id='previousYearToggle'>$annee_avant</div>";

        for ($i = $mois_courant; $i < 13; $i++) {
            echo "<div class='calendrier_case2 invisible previousYear'>";
            echo "<a href='reservation.php?reservationitems_id=$ID&amp;mois_courant=$i&amp;" .
                "annee_courante=$annee_avant'>" . $monthsarray[$i] . "</a></div>";
        }

        echo "<div class='center b' id='currentYearToggle'>$annee_courante</div>";

        for ($i = 1; $i < 13; $i++) {
            if ($i == $mois_courant) {
                echo "<div class='calendrier_case1 b invisible currentYear'>" . $monthsarray[$i] . "</div>\n";
            } else {
                echo "<div class='calendrier_case2 invisible currentYear'>";
                echo "<a href='reservation.php?reservationitems_id=$ID&amp;mois_courant=$i&amp;" .
                    "annee_courante=$annee_courante'>" . $monthsarray[$i] . "</a></div>\n";
            }
        }
        echo "<div class='center b' id='nextYearToggle'>$annee_apres</div>\n";

        for ($i = 1; $i < $mois_courant + 1; $i++) {
            echo "<div class='calendrier_case2 invisible nextYear'>";
            echo "<a href='reservation.php?reservationitems_id=$ID&amp;mois_courant=$i&amp;" .
                "annee_courante=$annee_apres'>" . $monthsarray[$i] . "</a></div>\n";
        }
        echo "</div>";


        $js = "$(document).ready(function() {
            $('#previousYearToggle').click(function() {
                $('.previousYear').toggleClass('invisible');
            });
            $('#currentYearToggle').click(function() {
                $('.currentYear').toggleClass('invisible');
            });
            $('#nextYearToggle').click(function() {
                $('.nextYear').toggleClass('invisible');
            });
        })";

        echo html::scriptBlock($js);

        $mail = new GLPIMailer();
        $mail->setFrom('gustavocignachi@hotmail.com');
        $mail->addAddress('gustavocignachi@hotmail.com');
        $mail->Subject  = 'First PHPMailer Message';
        $mail->Body     = 'Hi! This is my first e-mail sent through PHPMailer.';
        if(!$mail->send()) {
            echo 'Message was not sent.';
            echo 'Mailer error: ' . $mail->ErrorInfo;
        } else {
            echo 'Message has been sent.';
        }

        echo "</td></tr></table>";
        echo "</td><td class='top' width='100%'>";


        echo "<div id='buttonsCalendar'>";
        echo "<button class='menuCalendar' id='buttonBackwards'>Retornar</button>";
        echo "<button class='menuCalendar' id='buttonShowAll'>Mostrar Tudo</button>";
        echo "<button class='menuCalendar' id='buttonForwards'>Avançar</button>";
        echo "</div>";


        // test
        echo "<table width='100%' class='tab_cadre'><tr>";
        echo "<th width='14%'>" . __('Monday') . "</th>";
        echo "<th width='14%'>" . __('Tuesday') . "</th>";
        echo "<th width='14%'>" . __('Wednesday') . "</th>";
        echo "<th width='14%'>" . __('Thursday') . "</th>";
        echo "<th width='14%'>" . __('Friday') . "</th>";
        echo "<th width='14%'>" . __('Saturday') . "</th>";
        echo "<th width='14%'>" . __('Sunday') . "</th>";
        echo "</tr>\n";


        list($currentmonth, $currentday, $currentyear) =  explode('/',strftime('%m/%d/%Y'));

        if(($currentday - (8 - $jour_debut_mois) <= 0) && ($annee_courante == $currentyear)
            && ($mois_courant == $currentmonth)) {
            echo "<tr class='tab_bg_3 currentWeek week0' >";
        } else {
            echo "<tr class='tab_bg_3 week0' >";
        }

        // Insert blank cell before the first day of the month
        for ($i = 1; $i < $jour_debut_mois; $i++) {
            echo "<td class='calendrier_case_white'>&nbsp;</td>";
        }

        // Here is the actual filling
        if (($mois_courant < 10) && (strlen($mois_courant) == 1)) {
            $mois_courant = "0" . $mois_courant;
        }

        $week = 1;

        for ($i = 1; $i < $nb_jour[$mois_courant - 1] + 1; $i++) {
            if ($i < 10) {
                $ii = "0" . $i;
            } else {
                $ii = $i;
            }

            /*if($i == $currentday) {
                echo "<td class='top invisible' height='100px'>";
            } else {
                echo "<td class='top' height='100px'>";
            }*/
            echo "<td class='top day" . $i . "' height='100px'>";
            echo "<table class='center test" . $i . "' width='100%'><tr><td class='center'>";
            echo "<span class='calendrier_jour'>" . $i . "</span></td></tr>\n";

            if (!empty($ID)) {
                echo "<tr><td class='center'>";
                echo "<a href='reservation.form.php?id=&amp;item[$ID]=$ID&amp;" .
                    "begin=" . $annee_courante . "-" . $mois_courant . "-" . $ii . " 12:00:00'>";
                echo "<img  src='" . $CFG_GLPI["root_doc"] . "/pics/addresa.png' alt=\"" .
                    __s('Reserve') . "\" title=\"" . __s('Reserve') . "\"></a></td></tr>\n";
            }

            echo "<tr><td>";
            self::displayReservationDay($ID, $annee_courante . "-" . $mois_courant . "-" . $ii);
            echo "</td></tr></table>\n";
            echo "</td>";

            // do not forget to go to the next line at the end of the week
            if ((($i + $jour_debut_mois) % 7) == 1) {
                echo "</tr>\n";
                if ($i != $nb_jour[$mois_courant - 1]) {
                    if(($currentday - $i <= 7) && ($currentday - $i >= 1) && ($annee_courante == $currentyear)
                        && ($mois_courant == $currentmonth)) {
                        echo "<tr class='tab_bg_3 currentWeek week" . $week . "'>";
                    } else {
                        echo "<tr class='tab_bg_3 week" . $week . "'>";
                    }
                    $week +=1;
                }
            }
        }

        // we start again to finish the painting properly for the same reasons
        // on recommence pour finir le tableau proprement pour les m�es raisons
        if ($jour_fin_mois != 0) {
            for ($i = 0; $i < 7 - $jour_fin_mois; $i++) {
                echo "<td class='calendrier_case_white'>&nbsp;</td>";
            }
        }

        echo "</tr></table>\n";
        echo "</td></tr></table></div>\n";

        $js = "first_execution = true;
        current_week = -1;
        
        $('#buttonForwards').click(forwardWeek);
        $('#buttonBackwards').click(backwardWeek);
        $('#buttonShowAll').click(showFullCalendar);
        
        function forwardWeek() {
            new_week = 0;
            for(i = 0; i < " . $week . "; i++) {
                if(first_execution) {
                    if( !$('.week' + i).hasClass('currentWeek') ) {
                        $('.week' + i).addClass('invisible');
                    } else {
                        current_week = i;
                    }
                } else {
                    if(i === current_week) {
                        $('.week' + i).addClass('invisible');
                        if(current_week == " . $week . " - 1) {
                            l = 0;
                            new_week = 0;
                        } else {
                            l = i + 1;
                            new_week = current_week + 1;
                        }
                        console.log(l);
                        $('.week' + l).removeClass('invisible');
                    }
                }
            }
            if(current_week === -1) {
                $('.week0').removeClass('invisible');
                current_week = 0;
            }
            if(!first_execution) {
                current_week = new_week;
            }
            first_execution = false;
        };
        
        
        function backwardWeek() {
            new_week = 0;
            for(i = 0; i < " . $week . "; i++) {
                if(first_execution) {
                    if( !$('.week' + i).hasClass('currentWeek') ) {
                        $('.week' + i).addClass('invisible');
                    } else {
                        current_week = i;
                    }
                } else {
                    if(i == current_week) {
                        $('.week' + i).addClass('invisible');
                        if(current_week == 0) {
                            l = " . ($week - 1) . ";
                            new_week = " . ($week - 1) . ";
                        } else {
                            l = i - 1;
                            new_week = current_week - 1;
                        }
                        console.log(l);
                        $('.week' + l).removeClass('invisible');
                    }
                }
            }
            if(current_week === -1) {
                $('.week" . ($week-1) . "').removeClass('invisible');
                current_week = " . ($week-1) . ";
            }
            if(!first_execution) {
                current_week = new_week;
            }
            console.log(first_execution);
            first_execution = false;
        };
        
        function showFullCalendar() {
            for(i = 0; i < " . $week . "; i++) {
                $('.week' + i).removeClass('invisible');
            }
            first_execution = true;
            current_week = -1;
        };
        
        
        ";

        echo html::scriptBlock($js);


        if (isset($_SESSION['glpi_finished_reservation']['confirmed']) && $_SESSION['glpi_finished_reservation']['confirmed']) {
            $confirmedReservationString = self::formatReservationConfirmationString($_SESSION['glpi_finished_reservation']);
            html::genericModal($confirmedReservationString);
            $_SESSION['glpi_finished_reservation'] = false;
        }

    }


    /**
     * Formats and returns reservation information to be displayed in a confirmation modal
     * WIP - Needs to implement a more detailed version of this confirmation modal, might meet some problems with the $_SESSIONS VARIABLE
     *
     * @param $reservationInformationToBeDisplayed String containing the information relating to the reservation to be displayed
     * @return string String formated using the reservation info, to be displayed
     */
    static function formatReservationConfirmationString($reservationInformationToBeDisplayed)
    {
            /*$formatString = sprintf('Caro %1$s, </br>Sua reserva da sala %2$s para o seguinte periodo:</br>%3$s - %4$s</br>
                Foi confirmada.',
                $reservationInformationToBeDisplayed['user'], 'teste', $reservationInformationToBeDisplayed['time_begin'],
                $reservationInformationToBeDisplayed['time_end']);*/
            $formatString = sprintf('Caro %1$s, </br></br>Sua reserva da sala:</br></br> &emsp;&emsp;%2$s</br></br>Foi concluida com sucesso.',
                $reservationInformationToBeDisplayed['user'], $reservationInformationToBeDisplayed['local']);

            return $formatString;
    }


    /**
     * Finds the reservation's room's name based on it's ID
     *
     * @param $ID
     * @return String Name of a room | int represents that no result was found
     */
    static function findRoomById($ID) {
        global $DB;

        $query = "SELECT name FROM glpi_plugin_room_rooms WHERE id='$ID'";
        if ($result = $DB->query($query)) {
            return $DB->result($result, 0, 0);
        }
        return -1;
    }


    /**
     * Display for reservation
     *
     * @param $ID              ID of the reservation (empty for create new)
     * @param $options   array of possibles options:
     *     - item  reservation items ID for creation process
     *     - date date for creation process
     **/
    function showForm($ID, $options = array())
    {
        global $CFG_GLPI;


        /**
         * #HOLDAT Modified to support changes in the room search, while also making the dates immutable directly
         */
        if (!Session::haveRight("reservation", ReservationItem::RESERVEANITEM)) {
            return false;
        }

        $resa = new self();

        if (!empty($ID)) {
            if (!$resa->getFromDB($ID)) {
                return false;
            }

            if (!$resa->can($ID, UPDATE)) {
                return false;
            }
            // Set item if not set
            if ((!isset($options['item']) || (count($options['item']) == 0))
                && ($itemid = $resa->getField('reservationitems_id'))) {
                $options['item'][$itemid] = $itemid;
            }

        } else {
            $resa->getEmpty();
            $resa->fields["begin"] = $options['timebegin'];
            $resa->fields["end"] = $options['timeend'];

            /*if (!isset($options['end'])) {
               $resa->fields["end"] = date("Y-m-d H:00:00",
                                           strtotime($resa->fields["begin"])+HOUR_TIMESTAMP);
            } else {
               $resa->fields["end"] = $options['end'];
            }*/
        }

        // No item : problem
        if (!isset($options['item']) || (count($options['item']) == 0)) {
            return false;
        }

        echo "<div class='center'><form method='post' name=form action='reservation.form.php'>";

        if (!empty($ID)) {
            echo "<input type='hidden' name='id' value='$ID'>";
        }

        echo "<table class='tab_cadre' width='700px'>";
        echo "<tr><th colspan='2'>" . __('Reserva') . "</th></tr>\n";

        // Add Hardware name
        $r = new ReservationItem();

        echo "<tr class='tab_bg_1'><td>Sala</td>";
        echo "<td>";
        foreach ($options['item'] as $itemID) {
            $r->getFromDB($itemID);
            $type = $r->fields["itemtype"];
            $name = NOT_AVAILABLE;
            $item = NULL;

            if ($item = getItemForItemtype($r->fields["itemtype"])) {
                $type = $item->getTypeName();

                if ($item->getFromDB($r->fields["items_id"])) {
                    $name = $item->getName();
                } else {
                    $item = NULL;
                }
            }

            echo "<span class='b'>" . sprintf(__('%1$s'), $name) . "</span><br>";
            echo "<input type='hidden' name='items[$itemID]' value='$itemID'>";
        }

        echo "</td></tr>\n";
        if (!Session::haveRight("reservation", UPDATE)
            || is_null($item)
            || !Session::haveAccessToEntity($item->fields["entities_id"])) {

            echo "<input type='hidden' name='users_id' value='" . Session::getLoginUserID() . "'>";

        } else {
            echo "<tr class='tab_bg_2'><td>" . __('By') . "</td>";
            echo "<td>";
            if (empty($ID)) {
                User::dropdown(array('value' => Session::getLoginUserID(),
                    'entity' => $item->getEntityID(),
                    'right' => 'all'));
            } else {
                User::dropdown(array('value' => $resa->fields["users_id"],
                    'entity' => $item->getEntityID(),
                    'right' => 'all'));
            }
            echo "</td></tr>\n";
        }
        echo "<tr class='tab_bg_2'><td>Inicio da reserva</td><td>";
        /*$rand_begin = Html::showDateTimeField("resa[begin]",
                                              array('value'      => $resa->fields["begin"],
                                                    'timestep'   => -1,
                                                    'maybeempty' => false));
        */
        echo "<input type='text' name='timebegin' value='" . date('d-m-Y H:i', strtotime($resa->fields['begin'])) . "' readonly>";
        echo "<input type='hidden' name='resa[begin]' value='" . $resa->fields['begin'] . "' >";
        echo "</td></tr>\n";
        $default_delay = floor((strtotime($resa->fields["end"]) - strtotime($resa->fields["begin"]))
                / $CFG_GLPI['time_step'] / MINUTE_TIMESTAMP)
            * $CFG_GLPI['time_step'] * MINUTE_TIMESTAMP;
        echo "<tr class='tab_bg_2'><td>Fim da reserva</td><td>";

        echo "<input type='text' name='timeend' value='" . date('d-m-Y H:i', strtotime($resa->fields['end'])) . "' readonly>";
        echo "<input type='hidden' name='resa[end]' value='" . $resa->fields['end'] . "' >";
        /*$rand = Dropdown::showTimeStamp("resa[_duration]",
                                        array('min'        => 0,
                                              'max'        => 24*HOUR_TIMESTAMP,
                                              'value'      => '',
                                              'emptylabel' => __('Specify an end date')));
        echo "<br><div id='date_end$rand'></div>";
        $params = array('duration'     => '__VALUE__',
                        'end'          => $resa->fields["end"],
                        'name'         => "resa[end]");

        Ajax::updateItemOnSelectEvent("dropdown_resa[_duration]$rand", "date_end$rand",
                                      $CFG_GLPI["root_doc"]."/ajax/planningend.php", $params);

        if ($default_delay == 0) {
           $params['duration'] = 0;
           Ajax::updateItem("date_end$rand", $CFG_GLPI["root_doc"]."/ajax/planningend.php", $params);
        }*/

        Alert::displayLastAlert('Reservation', $ID);
        echo "</td></tr>\n";

        if (empty($ID)) {
            echo "<tr class='tab_bg_2'><td>" . __('Rehearsal') . "</td>";
            echo "<td>";
            $values = array('' => _x('periodicity', 'None'),
                'day' => _x('periodicity', 'Daily'),
                'week' => _x('periodicity', 'Weekly'),
                'month' => _x('periodicity', 'Monthly'));
            $rand = Dropdown::showFromArray('periodicity[type]', $values);
            $field_id = Html::cleanId("dropdown_periodicity[type]$rand");

            $params = array('type' => '__VALUE__',
                'end' => $resa->fields["end"]);

            Ajax::updateItemOnSelectEvent($field_id, "resaperiodcontent$rand",
                $CFG_GLPI["root_doc"] . "/ajax/resaperiod.php", $params);
            echo "<br><div id='resaperiodcontent$rand'></div>";

            echo "</td></tr>\n";
        }

        echo "<tr class='tab_bg_2'><td>Área Temática da reserva</td><td>";
        $valueThemes = array('NULL' => ' ---- ',
            'Aula' => 'Aula',
            'Apresentação' => 'Apresentação');
        Dropdown::showFromArray('theme', $valueThemes);

        echo "<tr class='tab_bg_2'><td>Descrição da Atividade</td>";
        echo "<td><textarea name='comment' id='reservationText' rows='8' cols='60' maxlength='520' placeholder='Descreva sua atividade com no mínimo X caracteres' required>" . $resa->fields["comment"] . "</textarea>";
        echo "</td></tr>\n";


        if (empty($ID)) {
            echo "<tr class='tab_bg_2'>";
            echo "<td colspan='2' class='top center'>";
            echo "<input type='submit' id='reservationFormSubmit' name='add' value=\"" . _sx('button', 'Add') . "\" class='submit'>";
            echo "</td></tr>\n";

        } else {
            if (($resa->fields["users_id"] == Session::getLoginUserID())
                || Session::haveRightsOr(static::$rightname, array(PURGE, UPDATE))) {
                echo "<tr class='tab_bg_2'>";
                if (($resa->fields["users_id"] == Session::getLoginUserID())
                    || Session::haveRight(static::$rightname, PURGE)) {
                    echo "<td class='top center'>";
                    echo "<input type='submit' name='purge' value=\"" . _sx('button', 'Delete permanently') . "\"
                      class='submit'>";
                    if ($resa->fields["group"] > 0) {
                        echo "<br><input type='checkbox' name='_delete_group'>&nbsp;" .
                            __s('Delete all rehearsals');
                    }
                    echo "</td>";
                }
                if (($resa->fields["users_id"] == Session::getLoginUserID())
                    || Session::haveRight(static::$rightname, UPDATE)) {
                    echo "<td class='top center'>";
                    echo "<input type='submit'  name='update' value=\"" . _sx('button', 'Save') . "\"
                     class='submit'>";
                    echo "</td>";
                }
                echo "</tr>\n";
            }
        }
        echo "</table>";


        $js = "$(document).ready(function() {
            $('#reservationText').qtip({
                content: {
                    text: 520 - $('#reservationText').val().length,
                    title: 'Número de caracteres restantes'
                },
                style: {
                    classes: 'qtip-shadow qtip-bootstrap'
                },
                hide: {
                    event: 'unfocus'
                }
            });
        });

        $('#reservationText').keyup(function () {
            let qapi = $('#reservationText').data('qtip');
            let newtip = 520 - $('#reservationText').val().length;
            qapi.options.content.text = newtip;
            qapi.elements.content.text(newtip);
        });";

        echo Html::scriptBlock($js);

        Html::closeForm();
        echo "</div>\n";
    }


    /**
     * compute periodicities for reservation
     *
     * @since version 0.84
     *
     * @param $begin             begin of the initial reservation
     * @param $end               begin of the initial reservation
     * @param $options   array   periodicity parameters : must contain : type (day/week/month), end
     **/
    static function computePeriodicities($begin, $end, $options = array())
    {
        $toadd = array();

        if (isset($options['type']) && isset($options['end'])) {
            $begin_time = strtotime($begin);
            $end_time = strtotime($end);
            $repeat_end = strtotime($options['end'] . ' 23:59:59');

            switch ($options['type']) {
                case 'day' :
                    $begin_time = strtotime("+1 day", $begin_time);
                    $end_time = strtotime("+1 day", $end_time);
                    while ($begin_time < $repeat_end) {
                        $toadd[date('Y-m-d H:i:s', $begin_time)] = date('Y-m-d H:i:s', $end_time);
                        $begin_time = strtotime("+1 day", $begin_time);
                        $end_time = strtotime("+1 day", $end_time);
                    }
                    break;

                case 'week' :
                    $dates = array();

                    // No days set add 1 week
                    if (!isset($options['days'])) {
                        $dates = array(array('begin' => strtotime('+1 week', $begin_time),
                            'end' => strtotime('+1 week', $end_time)));
                    } else {
                        if (is_array($options['days'])) {
                            $begin_hour = $begin_time - strtotime(date('Y-m-d', $begin_time));
                            $end_hour = $end_time - strtotime(date('Y-m-d', $end_time));
                            foreach ($options['days'] as $day => $val) {
                                $dates[] = array('begin' => strtotime("next $day", $begin_time) + $begin_hour,
                                    'end' => strtotime("next $day", $end_time) + $end_hour);
                            }
                        }
                    }

                    foreach ($dates as $key => $val) {
                        $begin_time = $val['begin'];
                        $end_time = $val['end'];

                        while ($begin_time < $repeat_end) {
                            $toadd[date('Y-m-d H:i:s', $begin_time)] = date('Y-m-d H:i:s', $end_time);
                            $begin_time = strtotime('+1 week', $begin_time);
                            $end_time = strtotime('+1 week', $end_time);
                        }
                    }
                    break;

                case 'month' :
                    if (isset($options['subtype'])) {
                        switch ($options['subtype']) {
                            case 'date':
                                $i = 1;
                                $calc_begin_time = strtotime("+$i month", $begin_time);
                                $calc_end_time = strtotime("+$i month", $end_time);
                                while ($calc_begin_time < $repeat_end) {
                                    $toadd[date('Y-m-d H:i:s', $calc_begin_time)] = date('Y-m-d H:i:s',
                                        $calc_end_time);
                                    $i++;
                                    $calc_begin_time = strtotime("+$i month", $begin_time);
                                    $calc_end_time = strtotime("+$i month", $end_time);
                                }
                                break;

                            case 'day':
                                $dayofweek = date('l', $begin_time);

                                $i = 1;
                                $calc_begin_time = strtotime("+$i month", $begin_time);
                                $calc_end_time = strtotime("+$i month", $end_time);
                                $begin_hour = $begin_time - strtotime(date('Y-m-d', $begin_time));
                                $end_hour = $end_time - strtotime(date('Y-m-d', $end_time));

                                $calc_begin_time = strtotime("next $dayofweek", $calc_begin_time)
                                    + $begin_hour;
                                $calc_end_time = strtotime("next $dayofweek", $calc_end_time) + $end_hour;

                                while ($calc_begin_time < $repeat_end) {
                                    $toadd[date('Y-m-d H:i:s', $calc_begin_time)] = date('Y-m-d H:i:s',
                                        $calc_end_time);
                                    $i++;
                                    $calc_begin_time = strtotime("+$i month", $begin_time);
                                    $calc_end_time = strtotime("+$i month", $end_time);
                                    $calc_begin_time = strtotime("next $dayofweek", $calc_begin_time)
                                        + $begin_hour;
                                    $calc_end_time = strtotime("next $dayofweek", $calc_end_time)
                                        + $end_hour;
                                }
                                break;
                        }

                    }

                    break;
            }

        }

        return $toadd;
    }


    /**
     * Display for reservation
     *
     * @param $ID     ID a the reservation item (empty to show all)
     * @param $date   date to display
     **/
    static function displayReservationDay($ID, $date)
    {
        global $DB;

        //HOLDAT First method to show reservations in calendar

        if (!empty($ID)) {
            self::displayReservationsForAnItem($ID, $date);

        } else {
            $debut = $date . " 00:00:00";
            $fin = $date . " 23:59:59";

            $query = "SELECT DISTINCT `glpi_reservationitems`.`id`
                   FROM `glpi_reservationitems`
                   INNER JOIN `glpi_reservations`
                     ON (`glpi_reservationitems`.`id` = `glpi_reservations`.`reservationitems_id`)
                   WHERE `is_active` = '1'
                         AND '" . $debut . "' < `end`
                         AND '" . $fin . "' > `begin`
                   ORDER BY `begin`";
            $result = $DB->query($query);

            if ($DB->numrows($result) > 0) {
                $m = new ReservationItem();
                while ($data = $DB->fetch_assoc($result)) {
                    $m->getFromDB($data['id']);

                    if (!($item = getItemForItemtype($m->fields["itemtype"]))) {
                        continue;
                    }

                    if ($item->getFromDB($m->fields["items_id"])
                        && Session::haveAccessToEntity($item->fields["entities_id"])) {

                        $typename = $item->getTypeName();

                        if ($m->fields["itemtype"] == 'Peripheral') {
                            if (isset($item->fields["peripheraltypes_id"])
                                && ($item->fields["peripheraltypes_id"] != 0)) {

                                $typename = Dropdown::getDropdownName("glpi_peripheraltypes",
                                    $item->fields["peripheraltypes_id"]);
                            }
                        }

                        list($annee, $mois, $jour) = explode("-", $date);
                        echo "<tr class='tab_bg_1'><td>";
                        echo "<a href='reservation.php?reservationitems_id=" . $data['id'] .
                            "&amp;mois_courant=$mois&amp;annee_courante=$annee'>" .
                            sprintf(__('%1$s'), $item->getName()) . "</a></td></tr>\n";
                        echo "<tr><td>";
                        self::displayReservationsForAnItem($data['id'], $date);
                        echo "</td></tr>\n";
                    }
                }
            }
        }
    }


    /**
     * Display a reservation
     *
     * @param $ID     ID a the reservation item
     * @param $date   date to display
     **/
    static function displayReservationsForAnItem($ID, $date)
    {
        global $DB;

        $users_id = Session::getLoginUserID();
        $resa = new self();
        $user = new User;
        list($year, $month, $day) = explode("-", $date);
        $debut = $date . " 00:00:00";
        $fin = $date . " 23:59:59";

        $query = "SELECT *
                FROM `glpi_reservations`
                WHERE '" . $debut . "' < `end`
                      AND '" . $fin . "' > `begin`
                      AND `reservationitems_id` = '$ID'
                ORDER BY `begin`";

        if ($result = $DB->query($query)) {
            if ($DB->numrows($result) > 0) {
                echo "<table width='100%'>";
                while ($row = $DB->fetch_assoc($result)) {
                    echo "<tr>";
                    $user->getFromDB($row["users_id"]);
                    $display = "";

                    if ($debut > $row['begin']) {
                        $heure_debut = "00:00";
                    } else {
                        $heure_debut = get_hour_from_sql($row['begin']);
                    }

                    if ($fin < $row['end']) {
                        $heure_fin = "24:00";
                    } else {
                        $heure_fin = get_hour_from_sql($row['end']);
                    }

                    if ((strcmp($heure_debut, "00:00") == 0)
                        && (strcmp($heure_fin, "24:00") == 0)) {
                        $display = __('Day');

                    } else if (strcmp($heure_debut, "00:00") == 0) {
                        $display = sprintf(__('To %s'), $heure_fin);

                    } else if (strcmp($heure_fin, "24:00") == 0) {
                        $display = sprintf(__('From %s'), $heure_debut);

                    } else {
                        $display = $heure_debut . "-" . $heure_fin;
                    }

                    $rand = mt_rand();
                    $modif = $modif_end = "";


                    /**
                     *
                     * #HOLDAT Implemented tooltip content modifier, so that thematic area is displayed, if available
                     *
                     */
                    $tooltipContent = "";

                    if ($row["theme"] != "") {
                        $tooltipContent .= "Área: " . $row["theme"] . "</br>";
                    }
                    $tooltipContent .= $row["comment"];

                    /**
                     * #HOLDAT Changed to allow all users to see comments
                     */
                    if ($resa->canEdit($row['id'])) {
                        $modif = "<a id='content_" . $ID . $rand . "'
                                  href='reservation.form.php?id=" . $row['id'] . "'>";
                        $modif_end = "</a>";
                        $modif_end .= Html::showToolTip($tooltipContent,
                            array('applyto' => "content_" . $ID . $rand,
                                'display' => false));
                    } else {
                        $modif = "<a id='content_" . $ID . $rand . "'>";
                        $modif_end = "</a>";
                        $modif_end .= Html::showToolTip($tooltipContent,
                            array('applyto' => "content_" . $ID . $rand,
                                'display' => false));
                    }

                    echo "<td class='tab_resa center'>" . $modif . "<span>" . $display . "<br><span class='b'>" .
                        formatUserName($user->fields["id"], $user->fields["name"], $user->fields["realname"],
                            $user->fields["firstname"]);
                    echo "</span></span>";
                    echo $modif_end;
                    echo "</td></tr>\n";
                }
                echo "</table>\n";
            }
        }
    }


    /**
     * Display reservations for an item
     *
     * @param $item            CommonDBTM object for which the reservation tab need to be displayed
     * @param $withtemplate    withtemplate param (default '')
     **/
    static function showForItem(CommonDBTM $item, $withtemplate = '')
    {
        global $DB, $CFG_GLPI;

        $resaID = 0;
        if (!Session::haveRight("reservation", READ)) {
            return false;
        }

        echo "<div class='firstbloc'>";
        ReservationItem::showActivationFormForItem($item);

        $ri = new ReservationItem();
        if ($ri->getFromDBbyItem($item->getType(), $item->getID())) {
            $now = $_SESSION["glpi_currenttime"];

            // Print reservation in progress
            $query = "SELECT *
                   FROM `glpi_reservations`
                   WHERE `end` > '" . $now . "'
                         AND `reservationitems_id` = '" . $ri->fields['id'] . "'
                   ORDER BY `begin`";
            $result = $DB->query($query);

            echo "<table class='tab_cadre_fixehov'><tr><th colspan='5'>";

            if ($ri->fields["is_active"]) {
                echo "<a href='" . $CFG_GLPI["root_doc"] . "/front/reservation.php?reservationitems_id=" .
                    $ri->fields['id'] . "'>" . __('Current and future reservations') . "</a>";
            } else {
                _e('Current and future reservations');
            }
            echo "</th></tr>\n";

            if ($DB->numrows($result) == 0) {
                echo "<tr class='tab_bg_2'>";
                echo "<td class='center' colspan='5'>" . __('No reservation') . "</td></tr>\n";

            } else {
                echo "<tr><th>" . __('Start date') . "</th>";
                echo "<th>" . __('End date') . "</th>";
                echo "<th>" . __('By') . "</th>";
                echo "<th>" . __('Comments') . "</th><th>&nbsp;</th></tr>\n";

                while ($data = $DB->fetch_assoc($result)) {
                    echo "<tr class='tab_bg_2'>";
                    echo "<td class='center'>" . Html::convDateTime($data["begin"]) . "</td>";
                    echo "<td class='center'>" . Html::convDateTime($data["end"]) . "</td>";
                    echo "<td class='center'>";
                    echo "<a href='" . $CFG_GLPI["root_doc"] . "/front/user.form.php?id=" .
                        $data["users_id"] . "'>" . getUserName($data["users_id"]) . "</a></td>";
                    echo "<td class='center'>" . nl2br($data["comment"]) . "</td>";
                    echo "<td class='center'>";
                    list($annee, $mois, $jour) = explode("-", $data["begin"]);
                    echo "<a href='" . $CFG_GLPI["root_doc"] . "/front/reservation.php?reservationitems_id=" .
                        $ri->fields['id'] . "&amp;mois_courant=$mois&amp;annee_courante=$annee' title=\"" .
                        __s('See planning') . "\">";
                    echo "<img src=\"" . $CFG_GLPI["root_doc"] . "/pics/reservation-3.png\" alt='' title=''>" .
                        "</a>";
                    echo "</td></tr>\n";
                }
            }
            echo "</table></div>\n";

            // Print old reservations
            $query = "SELECT *
                   FROM `glpi_reservations`
                   WHERE `end` <= '" . $now . "'
                         AND `reservationitems_id` = '" . $ri->fields['id'] . "'
                   ORDER BY `begin` DESC";
            $result = $DB->query($query);

            echo "<div class='spaced'><table class='tab_cadre_fixehov'><tr><th colspan='5'>";

            if ($ri->fields["is_active"]) {
                echo "<a href='" . $CFG_GLPI["root_doc"] . "/front/reservation.php?reservationitems_id=" .
                    $ri->fields['id'] . "' >" . __('Past reservations') . "</a>";
            } else {
                _e('Past reservations');
            }
            echo "</th></tr>\n";

            if ($DB->numrows($result) == 0) {
                echo "<tr class='tab_bg_2'>";
                echo "<td class='center' colspan='5'>" . __('No reservation') . "</td></tr>\n";

            } else {
                echo "<tr><th>" . __('Start date') . "</th>";
                echo "<th>" . __('End date') . "</th>";
                echo "<th>" . __('By') . "</th>";
                echo "<th>" . __('Comments') . "</th><th>&nbsp;</th></tr>\n";

                while ($data = $DB->fetch_assoc($result)) {
                    echo "<tr class='tab_bg_2'>";
                    echo "<td class='center'>" . Html::convDateTime($data["begin"]) . "</td>";
                    echo "<td class='center'>" . Html::convDateTime($data["end"]) . "</td>";
                    echo "<td class='center'>";
                    echo "<a href='" . $CFG_GLPI["root_doc"] . "/front/user.form.php?id=" .
                        $data["users_id"] . "'>" . getUserName($data["users_id"]) . "</a></td>";
                    echo "<td class='center'>" . nl2br($data["comment"]) . "</td>";
                    echo "<td class='center'>";
                    list($annee, $mois, $jour) = explode("-", $data["begin"]);
                    echo "<a href='" . $CFG_GLPI["root_doc"] . "/front/reservation.php?reservationitems_id=" .
                        $ri->fields['id'] . "&amp;mois_courant=$mois&amp;annee_courante=$annee' title=\"" .
                        __s('See planning') . "\">";
                    echo "<img src=\"" . $CFG_GLPI["root_doc"] . "/pics/reservation-3.png\" alt='' title=''>";
                    echo "</a></td></tr>\n";
                }
            }
            echo "</table>\n";
        }
        echo "</div>\n";
    }


    /**
     * Display reservations for a user
     *
     * @param $ID ID a the user
     **/
    static function showForUser($ID)
    {
        global $DB, $CFG_GLPI;

        $resaID = 0;

        if (!Session::haveRight("reservation", READ)) {
            return false;
        }

        echo "<div class='firstbloc'>";
        $now = $_SESSION["glpi_currenttime"];

        // Print reservation in progress
        $query = "SELECT `begin`, `end`, `items_id`, `glpi_reservationitems`.`entities_id`,
                       `users_id`, `glpi_reservations`.`comment`, `reservationitems_id`,
                       `completename`
                FROM `glpi_reservations`
                LEFT JOIN `glpi_reservationitems`
                  ON `glpi_reservations`.`reservationitems_id` = `glpi_reservationitems`.`id`
                LEFT JOIN `glpi_entities`
                  ON  `glpi_reservationitems`.`entities_id` = `glpi_entities`.`id`
                WHERE `end` > '" . $now . "'
                      AND `users_id` = '$ID'
                ORDER BY `begin`";
        $result = $DB->query($query);

        $ri = new ReservationItem();
        echo "<table class='tab_cadre_fixehov'>";
        echo "<tr><th colspan='6'>" . __('Current and future reservations') . "</th></tr>\n";

        if ($DB->numrows($result) == 0) {
            echo "<tr class='tab_bg_2'>";
            echo "<td class='center' colspan='6'>" . __('No reservation') . "</td></tr\n>";

        } else {
            echo "<tr><th>" . __('Start date') . "</th>";
            echo "<th>" . __('End date') . "</th>";
            echo "<th>" . __('Item') . "</th>";
            echo "<th>" . __('Entity') . "</th>";
            echo "<th>" . __('By') . "</th>";
            echo "<th>" . __('Comments') . "</th><th>&nbsp;</th></tr>\n";

            while ($data = $DB->fetch_assoc($result)) {
                echo "<tr class='tab_bg_2'>";
                echo "<td class='center'>" . Html::convDateTime($data["begin"]) . "</td>";
                echo "<td class='center'>" . Html::convDateTime($data["end"]) . "</td>";

                if ($ri->getFromDB($data["reservationitems_id"])) {
                    $link = "&nbsp;";

                    if ($item = getItemForItemtype($ri->fields['itemtype'])) {
                        if ($item->getFromDB($ri->fields['items_id'])) {
                            $link = $item->getLink();
                        }
                    }
                    echo "<td class='center'>$link</td>";
                    echo "<td class='center'>" . $data['completename'] . "</td>";

                } else {
                    echo "<td class='center'>&nbsp;</td>";
                }

                echo "<td class='center'>" . getUserName($data["users_id"]) . "</td>";
                echo "<td class='center'>" . nl2br($data["comment"]) . "</td>";
                echo "<td class='center'>";
                list($annee, $mois, $jour) = explode("-", $data["begin"]);
                echo "<a href='" . $CFG_GLPI["root_doc"] . "/front/reservation.php?reservationitems_id=" .
                    $data["reservationitems_id"] . "&amp;mois_courant=$mois&amp;" .
                    "annee_courante=$annee' title=\"" . __s('See planning') . "\"><img src=\"" .
                    $CFG_GLPI["root_doc"] . "/pics/reservation-3.png\" alt='' title=''></a>";
                echo "</td></tr>\n";
            }
        }
        echo "</table></div>\n";

        // Print old reservations
        $query = "SELECT `begin`, `end`, `items_id`, `glpi_reservationitems`.`entities_id`,
                       `users_id`, `glpi_reservations`.`comment`, `reservationitems_id`,
                       `completename`
                FROM `glpi_reservations`
                LEFT JOIN `glpi_reservationitems`
                  ON `glpi_reservations`.`reservationitems_id` = `glpi_reservationitems`.`id`
                LEFT JOIN `glpi_entities`
                  ON  `glpi_reservationitems`.`entities_id` = `glpi_entities`.`id`
                WHERE `end` <= '" . $now . "'
                      AND `users_id` = '$ID'
                ORDER BY `begin` DESC";
        $result = $DB->query($query);

        echo "<div class='spaced'>";
        echo "<table class='tab_cadre_fixehov'>";
        echo "<tr><th colspan='6'>" . __('Past reservations') . "</th></tr>\n";

        if ($DB->numrows($result) == 0) {
            echo "<tr class='tab_bg_2'>";
            echo "<td class='center' colspan='6'>" . __('No reservation') . "</td></tr>\n";

        } else {
            echo "<tr><th>" . __('Start date') . "</th>";
            echo "<th>" . __('End date') . "</th>";
            echo "<th>" . __('Item') . "</th>";
            echo "<th>" . __('Entity') . "</th>";
            echo "<th>" . __('By') . "</th>";
            echo "<th>" . __('Comments') . "</th><th>&nbsp;</th></tr>\n";

            while ($data = $DB->fetch_assoc($result)) {
                echo "<tr class='tab_bg_2'>";
                echo "<td class='center'>" . Html::convDateTime($data["begin"]) . "</td>";
                echo "<td class='center'>" . Html::convDateTime($data["end"]) . "</td>";

                if ($ri->getFromDB($data["reservationitems_id"])) {
                    $link = "&nbsp;";

                    if ($item = getItemForItemtype($ri->fields['itemtype'])) {
                        if ($item->getFromDB($ri->fields['items_id'])) {
                            $link = $item->getLink();
                        }
                    }
                    echo "<td class='center'>$link</td>";
                    echo "<td class='center'>" . $data['completename'] . "</td>";

                } else {
                    echo "<td class='center'>&nbsp;</td>";
                }

                echo "<td class='center'>" . getUserName($data["users_id"]) . "</td>";
                echo "<td class='center'>" . nl2br($data["comment"]) . "</td>";
                echo "<td class='center'>";
                list($annee, $mois, $jour) = explode("-", $data["begin"]);
                echo "<a href='" . $CFG_GLPI["root_doc"] . "/front/reservation.php?reservationitems_id=" .
                    $data["reservationitems_id"] . "&amp;mois_courant=$mois&amp;annee_courante=$annee' " .
                    "title=\"" . __s('See planning') . "\">";
                echo "<img src='" . $CFG_GLPI["root_doc"] . "/pics/reservation-3.png' alt='' title=''></a>";
                echo "</td></tr>\n";
            }
        }
        echo "</table></div>\n";
    }


}

?>
