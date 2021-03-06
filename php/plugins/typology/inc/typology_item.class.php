<?php

/*
 -------------------------------------------------------------------------
 Typology plugin for GLPI
 Copyright (C) 2006-2012 by the Typology Development Team.

 https://forge.indepnet.net/projects/typology
 -------------------------------------------------------------------------

 LICENSE

 This file is part of Typology.

 Typology is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 Typology is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with Typology. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
*/

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

// Typology_Item Class
class PluginTypologyTypology_Item extends CommonDBRelation {

   // From CommonDBRelation
   static public $itemtype_1          = 'PluginTypologyTypology';
   static public $items_id_1          = 'plugin_typology_typologies_id';

   static public $itemtype_2          = 'itemtype';
   static public $items_id_2          = 'items_id';

   const LOG_ADD        = 1;
   const LOG_UPDATE     = 2;
   const LOG_DELETE     = 3;
   /**
    * functions mandatory
    * getTypeName(), canCreate(), canView()
    **/
   public static function getTypeName($nb=0) {

      return _n('Element', 'Elements', $nb, 'typology');
   }

   public static function canCreate() {
      return plugin_typology_haveRight('typology', 'w');
   }


   public static function canView() {
      return plugin_typology_haveRight('typology', 'r');
   }


   /**
    * Display typology-item's tab for each computer
    *
    * @param CommonGLPI $item
    * @param int $withtemplate
    * @return array|string
    */
   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {

      if (!$withtemplate) {
         if ($item->getType() == 'PluginTypologyTypology') {
               if ($_SESSION['glpishow_count_on_tabs']) {
                  return self::createTabEntry(self::getTypeName(2),
                     countElementsInTable($this->getTable(),
                        "`plugin_typology_typologies_id` = '".$item->getID()."'"));
               }
               return self::getTypeName(2);
         } else if (in_array($item->getType(), PluginTypologyTypology::getTypes(true))
            && $this->canView()) {
            return PluginTypologyTypology::getTypeName(1);
         }
      }
      return '';
   }


   /**
    * Display tab's content for each computer
    *
    * @static
    * @param CommonGLPI $item
    * @param int $tabnum
    * @param int $withtemplate
    * @return bool|true
    */
   public static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

      if ($item->getType()=='PluginTypologyTypology') {

         self::showForTypology($item);

      } else if (in_array($item->getType(), PluginTypologyTypology::getTypes(true))) {
         self::showPluginFromItems($item->getType(),$item->getField('id'));
      }
      return true;
   }

   //if item deleted
   static function cleanItemTypology(CommonDBTM $item) {

      $temp = new self();
      $temp->deleteByCriteria(
         array('itemtype' => $item->getType(),
               'items_id' => $item->getField('id'))
      );
   }
   
   /**
    * Add Logs
    *
    * @return nothing
    **/
   static function addLog($input, $logtype) {

         $new_value = $_SESSION["glpiname"]." ";
         if ($logtype == self::LOG_ADD) {
            $new_value .= __('Add element to the typology','typology')." : ";
         } else if ($logtype == self::LOG_UPDATE) {
            $new_value .= __('Update element to the typology','typology')." : ";
         } else if ($logtype == self::LOG_DELETE) {
            $new_value .= __('Element out of the typology','typology')." : ";
         }
         
         $item = new $input['itemtype']();
         $item->getFromDB($input['items_id']);
 
         $new_value .= $item->getName(0)." - ".
         Dropdown::getDropdownName('glpi_plugin_typology_typologies',$input['plugin_typology_typologies_id']);
         
      self::addHistory($input['plugin_typology_typologies_id'],
         "PluginTypologyTypology","",$new_value);
         
      self::addHistory($input['items_id'],
         $input['itemtype'],"",$new_value);
      
   }
   
   /**
    * Add an history
    *
    * @return nothing
    **/
   static function addHistory($ID,$type, $old_value='',$new_value='') {
      $changes[0] = 0;
      $changes[1] = $old_value;
      $changes[2] = $new_value;
      Log::history($ID, $type, $changes, 0, Log::HISTORY_LOG_SIMPLE_MESSAGE);
   }

   /**
    * allow to control data before adding in bdd
    *
    * @param datas $input
    * @return bool|datas|the
    */
   function prepareInputForAdd($input) {
      
      if(isset($input['_ruleid'])) {
         $input=self::checkValidated($input);
      } else {
         
         $values = array('plugin_typology_typologies_id' => $input['plugin_typology_typologies_id'],
                            'items_id'      => $input['items_id'],
                            'itemtype'      => $input['itemtype']);
                            
         $ID = self::checkIfExist($values);
         if($ID == -1) {
            return false;
         } else {
            $input=self::checkValidated($input);
         }
      }
      
      return $input;
   }
   
   /**
    * Check is typology is exist
    *
    * @param $input
    *
    * @return true / false
    
    **/
   static function checkIfExist($input, $display = true){

      //to control if item has already a typo
      $restrict = "`items_id` = '".$input["items_id"]."'
                  AND `itemtype` = '".$input["itemtype"]."'";
      //item a déjà une typo, action annulee + message erreur
      $typo_items = getAllDatasFromTable("glpi_plugin_typology_typologies_items",$restrict);
      if (!empty($typo_items)) {
         foreach ($typo_items as $typo_item) {
            $typoID = $typo_item["plugin_typology_typologies_id"];
            $ID = $typo_item["id"];
         }
      }
      if (isset($typoID) && $typoID > 0) {
      
         if ($display) {
            $itemtype_table = getTableForItemType($input["itemtype"]);
            $message = Dropdown::getDropdownName($itemtype_table,$input["items_id"])." : ".
               __('You cannot assign this typology to this material as he has already a typology : ','typology').
               Dropdown::getDropdownName('glpi_plugin_typology_typologies', $typoID);
            Session::addMessageAfterRedirect($message, ERROR, true);
            return -1;
         }
         return $ID;
      }
      
      return 0;
   }
   
   /**
    * Check is typology is validated
    *
    * @param $input
    *
    * @return error list / result
    
    **/
   static function checkValidated($input){

      $res = self::showManagementConsole($input["items_id"],
         $input["plugin_typology_typologies_id"],
         false);
      if(count($res) == 0){
         $input["is_validated"] = '1';
         $input["error"]='NULL';
      } else {
         $input["is_validated"] = '0';
         $list='';
         $i=0;
         Foreach($res as $critID){
            if($i==0){
               $list.=$critID;
               $i++;
            } else
               $list.=','.$critID;
         }
         $input["error"]=$list;
      }
      
      return $input;
   }
   
   
   /**
    * See typology errors
    *
    * @param $error list
    *
    * @return nothing
    
    **/
   static function displayErrors($error, $withlink = true){
      global $CFG_GLPI;

      $items = explode(",", $error);

      $i = 0;
      $display = "";
      if($items[0]!= NULL){
         foreach ($items as $critID){
            $crit = new PluginTypologyTypologyCriteria();
            if($crit->getFromDB($critID)) {

               if($i==0){
                  $display.=" ";
                  $i++;
               } else {
                  $display.=", ";
               }
               $itemtype = $crit->fields["itemtype"];
               $criteria= "";
               if ($withlink) {
                  $criteria="<a href='".$CFG_GLPI["root_doc"].
                     "/plugins/typology/front/typologycriteria.form.php?id=".
                     $crit->fields["id"]."' target='_blank'>";
               }
               $criteria.=$crit->fields["name"];
               if ($_SESSION["glpiis_ids_visible"]||empty($crit->fields["name"])) {
                  $criteria.=" (".$crit->fields["id"].")";
               }
               if ($withlink) {
                  $criteria.= "</a>";
               }
               $criteria.= " (".$itemtype::getTypeName(0).")";

               $display.=$criteria;
            }

         }
      }

      return $display;
   }


   /**
    * Actions done after the ADD of a item in the database
    *
    * @param $item : the item added
    *
    * @return nothing
    **/
   static function addItem($item) {

      $values = array();
      $plugin = new Plugin();
      $typo_item = new self();
      if ($plugin->isActivated("typology")) {
         $ruleCollection = new PluginTypologyRuleTypologyCollection($item->fields['entities_id']);
         $fields= array();
         //si massive action ajouter tous les champs de la rules
         if(!isset($item->input['_update'])){
            $rule = new PluginTypologyRuleTypology();
            foreach ($rule->getCriterias() as $ID => $crit) {
               if(!isset($item->input[$ID])){
                  if (isset($item->fields[$ID])) {
                     $item->input[$ID] = $item->fields[$ID];
                  }
               }
            }
         }
         $fields=$ruleCollection->processAllRules($item->input,$fields, array());
         //Store rule that matched
         if (isset($fields['_ruleid'])) {
            
            $values = array('plugin_typology_typologies_id' => $fields['plugin_typology_typologies_id'],
                            'items_id'      => $item->fields['id'],
                            'itemtype'      => $item->getType());
            
            //verifie si tu as deja une typo
            $ID = self::checkIfExist($values, false);
            //si pas de typo, ajout
            if($ID == 0) {
               $values['_ruleid'] = $fields['_ruleid'];
               $self = new self();
               $self->add($values);
               
               self::addLog($values, self::LOG_ADD);
   
            } else {
               //si typo, return ID typo_item
               $values['id'] = $ID;
            }
         } else {
            //math no rules
            $values = array('items_id'      => $item->fields['id'],
                            'itemtype'      => $item->getType());
            $ID = self::checkIfExist($values, false);
            //si typo, delete
            if($ID != '0'){
               
               $values['id'] = $ID;
               $self = new self();
               $self->getFromDB($ID);
               $values['plugin_typology_typologies_id'] = $self->fields['plugin_typology_typologies_id'];
               $self->delete($values);

               self::addLog($values, self::LOG_DELETE);
               return false;
            }
         }
         return $values;
      }
   }
   
   /**
    * Actions done after the ADD of a computer in the database
    *
    * @param $item : the item added
    *
    * @return nothing
    **/
   static function updateItem($item) {

      $plugin = new Plugin();
      if ($plugin->isActivated("typology")) {

         $values = self::addItem($item);

         if (isset($values['id'])) {
            $input=self::checkValidated($values);
            
            $self = new self();
            $input["id"] = $values['id'];
            $self->update($input);
            
            self::addLog($values, self::LOG_UPDATE);
         
         }
      }
   }

   /**
    * Display a link to add directly an item to a typo.
    *
    * @param $itemtype of item class
    * @param $ID : id item
    *
    * @return Nothing (displays)
    **/
   public static function showPluginFromItems($itemtype,$ID,$withtemplate='') {
      global $DB,$CFG_GLPI;

      $typo = new PluginTypologyTypology();
      $typo_item = new PluginTypologyTypology_Item();
      $table_typo_item = $typo_item->getTable();
      $item = new $itemtype();
      
      if (!plugin_typology_haveRight('typology','r')) {
         return false;
      }
      
      if (!$item->can($ID,'r')) {
         return false;
      }
      
      $canread = $item->can($ID,'r');
      $canedit = $item->can($ID,'w');
      
      $restrict = "`items_id` = '".$ID."'
              AND `itemtype` = '".$itemtype."'";

      if (Session::isMultiEntitiesMode()) {
         $colsup=1;
      } else {
         $colsup=0;
      }
      
      $used=array();

      if ($withtemplate!=2)
         echo "<form method='post' action=\"".
               $CFG_GLPI["root_doc"]."/plugins/typology/front/typology.form.php\">";
         
      echo "<div align='center'><table class='tab_cadre_fixe'>";

      //typologie attribuée
      if (countElementsInTable($table_typo_item,$restrict) > 0) {
         
         $typos = getAllDatasFromTable($table_typo_item,$restrict);
         if (!empty($typos)) {
            foreach ($typos as $typo) {
               $typo_ID = $typo["plugin_typology_typologies_id"];
            }
         }

         $query = "SELECT `".$table_typo_item."`.`id` AS typo_items_id,
                           `".$table_typo_item."`.`is_validated`,
                           `".$table_typo_item."`.`error`,
                             `glpi_plugin_typology_typologies`.*"
                    ." FROM `".$table_typo_item."`,`glpi_plugin_typology_typologies` "
                    ." LEFT JOIN `glpi_entities` ON (`glpi_entities`.`id` = `glpi_plugin_typology_typologies`.`entities_id`) "
                    ." WHERE `".$table_typo_item."`.`plugin_typology_typologies_id` = '".$typo_ID."' "
                    ." AND `".$table_typo_item."`.`items_id` = '".$ID."' "
                    ." AND `".$table_typo_item."`.`itemtype` = '".$itemtype."'
                    AND `".$table_typo_item."`.`plugin_typology_typologies_id`=`glpi_plugin_typology_typologies`.`id`"
                    . getEntitiesRestrictRequest(" AND ","glpi_plugin_typology_typologies",'','',true);

         $result = $DB->query($query);
         
         echo "<tr><th>".__('Typology assigned to this material','typology')."</th>";

         if (Session::isMultiEntitiesMode())
            echo "<th>".__('Entity')."</th>";

         echo "<th>".__('Responding to typology\'s criteria', 'typology')."</th>";

         echo "<th>".__('Actions','typology')."</th>";
         
         echo "</tr>";

         while ($data=$DB->fetch_array($result)) {
            $typo_ID=$data["id"];
            $used[]=$typo_ID;
 
            echo "<tr class='tab_bg_1'>";
            if ($withtemplate!=3
                  && $canread
                  && ((in_array($data['entities_id'],$_SESSION['glpiactiveentities'])
                                 || $data["is_recursive"]))) {
               echo "<td class='center'><a href='".
                        $CFG_GLPI["root_doc"]."/plugins/typology/front/typology.form.php?id=".
                        $data["id"]."'>".$data["name"];
               if ($_SESSION["glpiis_ids_visible"]||empty($data["name"]))
                  echo " (".$data["id"].")";
               echo "</a></td>";
            } else {
               echo "<td class='center'>".$data["name"];
               if ($_SESSION["glpiis_ids_visible"]||empty($data["name"]))
                  echo " (".$data["id"].")";
               echo "</td>";
            }
            if (Session::isMultiEntitiesMode())
               echo "<td  class='center'>".
                  Dropdown::getDropdownName("glpi_entities",$data['entities_id'])."</td>";

            if($data["is_validated"] > 0){
               $critTypOK = __('Yes');
            } else {
               $critTypOK = "<font color='red'>".__('No')." ".__('for the criteria','typology')." ";
               $i=0;
               
               $critTypOK.=self::displayErrors($data["error"]);

               $critTypOK.="</font>";
            }

            echo "<td class ='center'><b>".$critTypOK."</b></td>";
            
            echo "<td class='center'  width = '25%'>";
            
            echo "<input type='hidden' name='items_id' value='$ID'>";
            echo "<input type='hidden' name='itemtype' value='$itemtype'>";
            echo "<input type='hidden' name='id' value='".$data["typo_items_id"]."'>";
            echo "<input type='hidden' name='plugin_typology_typologies_id' value='".$data["id"]."'>";
                
            //actualiser la typologie
            
            echo "<input type='submit' name='update_item' value=\""._sx('button','Upgrade').
               "\" class='submit'>";
            
            //retirer la typologie
            if ($canedit){

                echo "&nbsp;&nbsp;<input type='submit' name='delete_item' value=\""._sx('button','Delete permanently').
                     "\" class='submit'>";
            }
            echo "</td>";
            echo "</tr>";
         }
         
         self::showManagementConsole($ID, $typo_ID);

      //typologie non attribuée
      } else {
         echo "<tr><th colspan='".(5+$colsup)."'>".__('Assign a typology to this material','typology')."</th></tr>";

         //Affecter une typologie
         if ($canedit) {

            if ($withtemplate<2) {
               if ($typo_item->canCreate()) {

                  $typo_item->showAdd($itemtype,$ID);
               }
            }
         }
      }

      echo "</table></div>";
      if ($withtemplate!=2)
         Html::closeForm();
   }

   /**
    * Display a link to add directly an item to a typo.
    *
    * @param $itemtype of item class
    * @param $ID : id item
    *
    * @return Nothing (displays)
    **/
   function showAdd ($itemtype,$ID,$value=0) {
      global $DB;
      
      if (Session::isMultiEntitiesMode()) {
         $colsup=1;
      } else {
         $colsup=0;
      }
      
      $item = new $itemtype();
      
      $entities="";

      if ($item->isRecursive()) {
         $entities = getSonsOf('glpi_entities',$item->getEntityID());
      } else {
         $entities = $item->getEntityID();
      }   

      $limit = getEntitiesRestrictRequest(" AND ","glpi_plugin_typology_typologies",'',$entities,true);

      $q="SELECT COUNT(*)
          FROM `glpi_plugin_typology_typologies`
          WHERE `is_deleted` = '0' ";
      $q.=$limit;
      $result = $DB->query($q);
      $nb = $DB->result($result,0,0);
      
      $item = new $itemtype();

      echo "<div align='center'>";
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr class='tab_bg_1'>";
      echo "<input type='hidden' name='items_id' value='$ID'>";
      echo "<input type='hidden' name='itemtype' value='$itemtype'>";
      echo "<td class='center' class='tab_bg_2'>";
      echo PluginTypologyTypology::getTypeName(2)." ";
      Dropdown::show('PluginTypologyTypology', 
                     array('name' => "plugin_typology_typologies_id",
                           'entities_id' => $entities));
;
      echo "</td><td class='center' class='tab_bg_2'>";
      echo "<input type='submit' name='add_item' value=\"".__s('Post')."\" class='submit'></td></tr></div>";
   }
   
   /**
    * Display all the linked computers of a defined typology
    *
    *@param $typoID = typo ID.
    *
    *@return Nothing (displays)
    **/
   public static function showForTypology(PluginTypologyTypology $typo) {
      global $DB, $CFG_GLPI;

      $typoID = $typo->fields['id'];

      if (!$typo->can($typoID,'r'))
         return false;

      $canedit = $typo->can($typoID, 'w');
      $canview = $typo->can($typoID, 'r');
      $rand=mt_rand();

      $query = "SELECT DISTINCT `itemtype`
                    FROM `glpi_plugin_typology_typologies_items`
                    WHERE `plugin_typology_typologies_id` = '$typoID'
                    ORDER BY `itemtype`";
      $result = $DB->query($query);
      $number = $DB->numrows($result);

      if (Session::isMultiEntitiesMode()) {
         $colsup=1;
      } else {
         $colsup=0;
      }

      if ($canedit) {

         echo "<div class='firstbloc'>";
         echo "<form method='post' name='typologies_form$rand' id='typologies_form$rand' action='".
            $CFG_GLPI["root_doc"]."/plugins/typology/front/typology.form.php'>";

         echo "<table class='tab_cadre_fixe'>";
         echo "<tr>";
         echo "<th colspan='7'>".__('Add an item')."</th></tr>";

         echo "<tr class='tab_bg_1'><td colspan='4' class='center'>";
         echo "<input type='hidden' name='plugin_typology_typologies_id' value='$typoID'>";
         Dropdown::showAllItems("items_id", 0, 0,($typo->fields['is_recursive']?-1:$typo->fields['entities_id']),
            PluginTypologyTypology::getTypes());
         echo "</td>";
         echo "<td colspan='3' class='center' class='tab_bg_2'>";

         echo "<input type='submit' name='add_item' value=\""._sx('button','Add')."\" class='submit'>";
         echo "</td></tr>";

         echo "</table>";
         Html::closeForm();
         echo "</div>";
      }

      echo "<div class='spaced'>";
      if ($canedit && $number) {
         Html::openMassiveActionsForm('mass'.__CLASS__.$rand);
         $massiveactionparams = array();
         Html::showMassiveActions(__CLASS__, $massiveactionparams);
      }
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr>";
      echo "<th colspan='".($canedit?(7+$colsup):(6+$colsup))."'>";

      if ($DB->numrows($result)==0) {
         _e('No linked element','typology');

      } else {
         _e('Linked elements','typology');
      }

      echo "</th></tr><tr>";

      if ($canedit && $number) {
         echo "<th width='10'>".Html::getCheckAllAsCheckbox('mass'.__CLASS__.$rand)."</th>";
      }

      echo "<th>".__('Type')."</th>";
      echo "<th>".__('Name')."</th>";
      if (Session::isMultiEntitiesMode()) {
         echo "<th>".__('Entity')."</th>";
      }
      echo "<th>".__('Serial number')."</th>";
      echo "<th>".__('Inventory number')."</th>";
      echo "<th>".__('Responding to typology\'s criteria','typology')."</th>";
      echo "</tr>";

      for ($i=0 ; $i < $number ; $i++) {
         $itemtype=$DB->result($result, $i, "itemtype");
         if (!($item = getItemForItemtype($itemtype))) {
            continue;
         }
         if ($canview) {
            $column="name";
            $itemtable = getTableForItemType($itemtype);

            $query = "SELECT `".$itemtable."`.*,
                                `glpi_plugin_typology_typologies_items`.`id` AS IDP,
                                `glpi_plugin_typology_typologies_items`.`is_validated`,
                                `glpi_plugin_typology_typologies_items`.`error`,
                                `glpi_entities`.`id` AS entity "
               ." FROM `glpi_plugin_typology_typologies_items`, `".$itemtable
               ."` LEFT JOIN `glpi_entities` ON (`glpi_entities`.`id` = `".$itemtable."`.`entities_id`) "
               ." WHERE `".$itemtable."`.`id` = `glpi_plugin_typology_typologies_items`.`items_id`
                              AND `glpi_plugin_typology_typologies_items`.`itemtype` = '$itemtype'
                              AND `glpi_plugin_typology_typologies_items`.`plugin_typology_typologies_id` = '$typoID'";
            if ($itemtype!='User')
               $query.=getEntitiesRestrictRequest(" AND ",$itemtable,'','',$item->maybeRecursive());

            if ($item->maybeTemplate()) {
               $query.=" AND ".$itemtable.".is_template='0'";
            }

            $query.=" ORDER BY `glpi_entities`.`completename`, `".$itemtable."`.`$column` ";

            if ($result_linked=$DB->query($query)) {
               if ($DB->numrows($result_linked)) {
                  Session::initNavigateListItems($itemtype,PluginTypologyTypology::getTypeName(1).
                     " = ".$typo->fields['name']);

                  while ($data=$DB->fetch_assoc($result_linked)) {
                     $ID="";

                     $item->getFromDB($data["id"]);

                     Session::addToNavigateListItems($itemtype,$data["id"]);

                     if ($itemtype=='User') {
                        $format=formatUserName($data["id"],$data["name"],$data["realname"],$data["firstname"],1);
                     } else {
                        $format=$data["name"];
                     }

                     if($_SESSION["glpiis_ids_visible"] || empty($data["name"]))
                        $ID = " (".$data["id"].")";

                     $link=Toolbox::getItemTypeFormURL($itemtype);

                     $name= "<a href=\"".$link."?id=".$data["id"]."\">".$format;

                     if ($itemtype!='User')
                        $name.= "&nbsp;".$ID;

                     $name.= "</a>";

                     echo "<tr class='tab_bg_1'>";

                     if ($canedit) {
                        echo "<td width='10'>";
                        Html::showMassiveActionCheckBox(__CLASS__, $data["IDP"]);
                        echo "</td>";
                     }

                     echo "<input type='hidden' name='plugin_typology_typologies_id' value='$typoID'>";
                     echo "<td class='center'>".$item->getTypeName()."</td>";
                     echo "<td class='center' ".(isset($data['is_deleted'])&&$data['is_deleted']?"class='tab_bg_2_2'":"").">".$name."</td>";
                     if (Session::isMultiEntitiesMode())
                        if ($itemtype!='User') {
                           echo "<td class='center'>".Dropdown::getDropdownName("glpi_entities",$data['entity'])."</td>";
                        } else {
                           echo "<td class='center'>-</td>";
                        }
                     echo "<td class='center'>".(isset($data["serial"])? "".$data["serial"]."" :"-")."</td>";
                     echo "<td class='center'>".(isset($data["otherserial"])? "".$data["otherserial"]."" :"-")."</td>";

                     if($data["is_validated"] > 0){
                        $critTypOK = __('Yes');
                     } else {
                        $critTypOK = "<font color='red'>".__('No')." ".
                           __('for the criteria','typology')." ";
                        $i=0;

                        $critTypOK.=self::displayErrors($data["error"]);

                        $critTypOK.="</font>";
                     }

                     echo "<td class ='center'><b>".$critTypOK."</b></td>";

                     echo "</tr>";
                  }
               }
            }
         }
      }
      echo "</table>";
      if ($canedit && $number) {
         $paramsma['ontop'] =false;
         Html::showMassiveActions(__CLASS__, $paramsma);
         Html::closeForm();
      }

      echo "</div>";
   }


   /**
    * Display a management console in order to see the difference between the typology linkked
    * and data from each computer
    *
    * @param $ID : id item
    *
    * @return Nothing (displays)
    **/
   static function showManagementConsole($ID, $typo_ID, $display = true){
      
      $notOK = array();

      //image type
      $options['seeResult'] = 1 ;
      $options['seeItemtype']=1;

      $restrict = "`glpi_plugin_typology_typologycriterias`.`plugin_typology_typologies_id` = '$typo_ID'
                     AND `glpi_plugin_typology_typologycriterias`.`is_active` = 1
                     ORDER BY `glpi_plugin_typology_typologycriterias`.`itemtype`";
      $criterias = getAllDatasFromTable('glpi_plugin_typology_typologycriterias', $restrict);

      //typology criteria exist -> managmentconsole will be not empty
      if(!empty($criterias)){

         if ($display){
            echo "<div class='center'><table class='tab_cadre_fixe'>";

            //title
            echo "<tr><th colspan='7'>";
            _e('Management console','typology');
            echo "</th></tr>";

            //column name
            echo "<tr>";
            echo "<th rowspan='2'>".__('Item')."</th>";
            echo "<th colspan='2' rowspan='2'>"._n('Field','Fields',2)."</th>";
            echo "<th rowspan='2'>".__('Comparison','typology')."</th>";
            echo "<th colspan='2'>".__('Detail of the assigned typology','typology')."</th>";
            echo "<th>".__('Detail of the encountered configuration','typology')."</th>";
            echo "</tr>";

            //sub-column name
            echo "<tr>";
            echo "<th class='center b'>".__('Logical operator')."</th>";
            echo "<th class='center b'>".__('Waiting value','typology')."</th>";
            echo "<th class='center b'>".__('Real value','typology')."</th>";
            echo "</tr>";
         }

         foreach ($criterias as $criteria){
            $tabCrit[$criteria['itemtype']][] = $criteria['id'];
         }

         foreach ($tabCrit as $itemtype => $tabCritID){

            $datas = PluginTypologyTypologyCriteriaDefinition::getConsoleData($tabCritID, $ID,$itemtype, $display);
            if(!$display){
               foreach($datas[$itemtype] as $k=>$critId){
                  if($critId['result'] == 'not_ok'){
                     $notOK[]=$k;
                  }
               }
            } else {
               foreach($datas as $itemtype=>$allCrit){
                  if(!empty($allCrit)){
                     foreach ($allCrit as $key1=>$allDef){
                        if(!empty($allDef)){
                           foreach($allDef as $key2=>$def){
                              if(!empty($def)){
                                 echo "<tr class='tab_bg_1'>";
                                 $def['itemtype'] = $itemtype;

                                 PluginTypologyTypologyCriteriaDefinition::showMinimalDefinitionForm($def, $options);
                                 echo "</tr>";
                              }
                           }
                        }
                        echo "<tr>";
                        echo "<th colspan='7' class='center b'></th>";
                        echo "</tr>";
                     }
                  }
               }
            }
         }

         if ($display)
            echo "</table></div>";
         return $notOK;
      }
   }

   /**
    * Get the standard massive actions which are forbidden
    *
    * @since version 0.84
    *
    * @return an array of massive actions
    **/
   public function getForbiddenStandardMassiveAction() {
      $forbidden = parent::getForbiddenStandardMassiveAction();
      $forbidden[] = 'update';
      $forbidden[] = 'purge';

      return $forbidden;
   }

   /**
    * Get the specific massive actions
    *
    * @since version 0.84
    * @param $checkitem link item to check right   (default NULL)
    *
    * @return an array of massive actions
    **/
   function getSpecificMassiveActions($checkitem=NULL) {
      $isadmin = static::canUpdate();
      $actions = parent::getSpecificMassiveActions($checkitem);
      if ($isadmin) {
         $actions['delete_item'] = _sx('button','Delete permanently');
         $actions['update_allitem'] = __('Recalculate typology for the elements','typology');
      }
      return $actions;
   }

   /**
    * Display specific options add action button for massive actions
    *
    * Parameters must not be : itemtype, action, is_deleted, check_itemtype or check_items_id
    * @param $input array of input datas
    * @since version 0.84
    *
    * @return boolean if parameters displayed ?
    **/
   function showSpecificMassiveActionsParameters($input = array()) {

      switch ($input['action']) {
         case "delete_item":
            echo "&nbsp;<input type=\"submit\" name=\"massiveaction\" class=\"submit\" value=\"".
               __s('Post')."\" >";
            return true;
            break;
         case "update_allitem":
            echo "&nbsp;<input type=\"submit\" name=\"massiveaction\" class=\"submit\" value=\"".
               __s('Post')."\" >";
            return true;
            break;

         default :
            return parent::showSpecificMassiveActionsParameters($input);
            break;
      }
      return false;
   }

   /**
    * Do the specific massive actions
    *
    * @since version 0.84
    *
    * @param $input array of input datas
    *
    * @return an array of results (nbok, nbko, nbnoright counts)
    **/
   function doSpecificMassiveActions($input = array()) {

      $res = array('ok'      => 0,
         'ko'      => 0,
         'noright' => 0);

      $typo_item = new PluginTypologyTypology_Item();

      switch ($input['action']) {
         case "delete_item":
            if ($input['itemtype']=='PluginTypologyTypology_Item') {

               foreach ($input["item"] as $key => $val) {
                  if ($val!= 0) {
                     $typo_item->getFromDB($key);
                     if ($typo_item->delete(array('id'=>$key))) {
                        $values = array('plugin_typology_typologies_id' => $input['plugin_typology_typologies_id'],
                           'items_id'      => $typo_item->fields['items_id'],
                           'itemtype'      => $typo_item->fields['itemtype']);

                        PluginTypologyTypology_Item::addLog($values, PluginTypologyTypology_Item::LOG_DELETE);

                        $res['ok']++;
                     } else {
                        $res['ko']++;
                     }
                  }
               }
            }
            break;
         case "update_allitem":
            if ($input['itemtype'] == 'PluginTypologyTypology_Item') {

               foreach ($input["item"] as $key => $val) {
                  if ($val!= 0) {
                     $typo_item->getFromDB($key);
                     $result = PluginTypologyTypology_Item::checkValidated(array('items_id' => $typo_item->fields['items_id'],
                     'plugin_typology_typologies_id'=>$typo_item->fields['plugin_typology_typologies_id'],
                     'id'=>$typo_item->fields['id']));
                     if($typo_item->update($result)){
                        $values = array('plugin_typology_typologies_id' => $typo_item->fields['plugin_typology_typologies_id'],
                           'items_id'      => $typo_item->fields['items_id'],
                           'itemtype'      => $typo_item->fields['itemtype']);

                        PluginTypologyTypology_Item::addLog($values, PluginTypologyTypology_Item::LOG_UPDATE);
                        $res['ok']++;
                     } else {
                        $res['ko']++;
                     }
                  }
               }
            }
            break;

         default :
            return parent::doSpecificMassiveActions($input);
      }
      return $res;
   }
}

?>