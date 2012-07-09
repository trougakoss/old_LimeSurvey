<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
 * LimeSurvey
 * Copyright (C) 2007-2011 The LimeSurvey Project Team / Carsten Schmitz
 * All rights reserved.
 * License: GNU/GPL License v2 or later, see LICENSE.php
 * LimeSurvey is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 *
 *	$Id$
 */
/*
 * This is the main controller for Participants Panel
 */
class participantsaction extends Survey_Common_Action
{
    /**
     * Loads jqGrid for the view
     * @param string $sScript Subaction
     */
    private function _loadjqGrid($sScript = '', $aData = array())
    {
        $this->getController()->_css_admin_includes(Yii::app()->getConfig('generalscripts') . 'jquery/jqGrid/css/ui.jqgrid.css');
        $this->getController()->_css_admin_includes(Yii::app()->getConfig('generalscripts') . 'jquery/jqGrid/css/jquery.ui.datepicker.css');
        $this->getController()->_js_admin_includes(Yii::app()->getConfig('generalscripts')  . 'jquery/jquery.js');
        $this->getController()->_js_admin_includes(Yii::app()->getConfig('generalscripts')  . 'jquery/jquery-ui.js');
        $this->getController()->_js_admin_includes(Yii::app()->getConfig('generalscripts')  . 'jquery/jqGrid/js/i18n/grid.locale-en.js');
        $this->getController()->_js_admin_includes(Yii::app()->getConfig('generalscripts')  . 'jquery/jqGrid/js/jquery.jqGrid.min.js');
        $this->getController()->_js_admin_includes(Yii::app()->getConfig('generalscripts')  . 'jquery/jqGrid/plugins/jquery.searchFilter.js');
        $this->getController()->_js_admin_includes(Yii::app()->getConfig('generalscripts')  . 'jquery/jqGrid/src/grid.celledit.js');
        $this->getController()->_js_admin_includes(Yii::app()->getConfig('generalscripts')  . 'jquery/jqGrid/js/i18n/grid.locale-en.js');

        if (!empty($sScript))
        {
            $this->getController()->_js_admin_includes(Yii::app()->getConfig('adminscripts') . $sScript . '.js');
            $this->_renderWrappedTemplate('participants', array('participantsPanel', $sScript), $aData);
        }
    }

    /**
     * Renders template(s) wrapped in header and footer
     *
     * @param string $sAction Current action, the folder to fetch views from
     * @param string|array $aViewUrls View url(s)
     * @param array $aData Data to be passed on. Optional.
     */
    protected function _renderWrappedTemplate($sAction = 'participants', $aViewUrls = array(), $aData = array())
    {
        $aData['display']['menu_bars'] = false;

        foreach((array) $aViewUrls as $sViewUrl)
        {
            $a_ViewUrls[] = $sViewUrl . '_view';
        }

        parent::_renderWrappedTemplate($sAction, $a_ViewUrls, $aData);
    }

    /**
     * Loads the view 'participantsPanel'
     */
    function index()
    {
        // if superadmin all the records in the cpdb will be displayed
        if (Yii::app()->session['USER_RIGHT_SUPERADMIN'])
        {
            $iTotalRecords = Participants::model()->count();
        }
        // if not only the participants on which he has right on (shared and owned)
        else
        {
            $iTotalRecords = Participants::getParticipantsOwnerCount($iUserID);
        }

        // gets the count of participants, their attributes and other such details
        $iUserID = Yii::app()->session['loginID'];
        $aData = array(
            'totalrecords' => $iTotalRecords,
            'owned' => Participants::model()->count('owner_uid = ' . $iUserID),
            'shared' => Participants::getParticipantsSharedCount($iUserID),
            'attributecount' => ParticipantAttributeNames::model()->count(),
            'blacklisted' => Participants::model()->count('owner_uid = ' . $iUserID . ' AND blacklisted = \'Y\'')
        );

        // loads the participant panel and summary view
        $this->_renderWrappedTemplate('participants', array('participantsPanel', 'summary'), $aData);
    }

    /**
     * Loads the view 'importCSV'
     */
    function importCSV()
    {
        $this->_renderWrappedTemplate('participants', array('participantsPanel', 'importCSV'));
    }

    /**
     * Loads the view 'displayParticipants' which contains the main grid
     */
    function displayParticipants()
    {
        $lang = Yii::app()->session['adminlang'];
        // loads the survey names to be shown in add to survey
        // if user is superadmin, all survey names
        if (Yii::app()->session['USER_RIGHT_SUPERADMIN'])
        {
            $aSurveyNames = Surveys_languagesettings::model()->with('survey', 'owner')->findAll('surveyls_language=:lang', array(':lang'=>$lang));
        }
        // otherwise owned by him
        else
        {
            $aSurveyNames = Surveys_languagesettings::model()->with('survey', 'owner')->findAll('survey.owner_id=:uid AND surveyls_language=:lang',array('survey.uid'=>Yii::app()->session['loginID'], ':lang'=>$lang));
        }

        /* Build a list of surveys that have tokens tables */
        $tSurveyNames=array();
        foreach($aSurveyNames as $row)
        {
            //echo $row['surveyls_survey_id']."<br />";
            $bTokenExists = tableExists('{{tokens_' . $row['surveyls_survey_id'] . '}}');
            if ($bTokenExists) //If tokens table exists
            {
                $tSurveyNames[]=$row;
            }
        }

        // data to be passed to view
        $aData = array(
            'names' => User::model()->findAll(),
            'attributes' => ParticipantAttributeNames::getVisibleAttributes(),
            'allattributes' => ParticipantAttributeNames::getAllAttributes(),
            'attributeValues' => ParticipantAttributeNames::getAllAttributesValues(),
            'surveynames' => $aSurveyNames,
            'tokensurveynames' => $tSurveyNames
        );

        $this->getController()->_js_admin_includes(Yii::app()->getConfig('generalscripts')  . 'jquery/jqGrid/js/i18n/grid.locale-en.js');
        $this->getController()->_js_admin_includes(Yii::app()->getConfig('generalscripts')  . 'jquery/jqGrid/js/jquery.jqGrid.min.js');
        $this->getController()->_css_admin_includes(Yii::app()->getConfig('generalscripts') . 'jquery/css/jquery.multiselect.css');
        $this->getController()->_css_admin_includes(Yii::app()->getConfig('generalscripts') . 'jquery/css/jquery.multiselect.filter.css');
        $this->getController()->_css_admin_includes(Yii::app()->getConfig('adminstyleurl')       . 'displayParticipants.css');
        $this->getController()->_css_admin_includes(Yii::app()->getConfig('generalscripts') . 'jquery/jqGrid/css/ui.jqgrid.css');
        $this->getController()->_css_admin_includes(Yii::app()->getConfig('generalscripts') . 'jquery/jqGrid/css/jquery.ui.datepicker.css');

        // loads the participant panel view and display participant view
        $this->_renderWrappedTemplate('participants', array('participantsPanel', 'displayParticipants'), $aData);
    }

    /**
     * Loads the view 'blacklistControl'
     */
    function blacklistControl()
    {
        $aData = array(
            'blacklistallsurveys' => Yii::app()->getConfig('blacklistallsurveys'),
            'blacklistnewsurveys' => Yii::app()->getConfig('blacklistnewsurveys'),
            'blockaddingtosurveys' => Yii::app()->getConfig('blockaddingtosurveys'),
            'hideblacklisted' => Yii::app()->getConfig('hideblacklisted'),
            'deleteblacklisted' => Yii::app()->getConfig('deleteblacklisted'),
            'allowunblacklist' => Yii::app()->getConfig('allowunblacklist')
        );
        $this->_renderWrappedTemplate('participants', array('participantsPanel', 'blacklist'), $aData);
    }

    /**
     * Loads the view 'userControl'
     */
    function userControl()
    {
        $aData = array(
            'userideditable' => Yii::app()->getConfig('userideditable')
        );

        $this->_renderWrappedTemplate('participants', array('participantsPanel', 'userControl'), $aData);
    }

    /**
     * Loads the view 'sharePanel'
     */
    function sharePanel()
    {
        $this->_loadjqGrid('sharePanel');
    }

    /**
     * Sends the shared participant info to the share panel using JSON encoding
     * Called after the share panel grid is loaded
     * Returns the json depending on the user logged in by checking it from the session
     * @return JSON encoded string containg sharing information
     */
    function getShareInfo_json()
    {
        $aData->page = 1;

        // If super administrator all the share info in the links table will be shown
        if (Yii::app()->session['USER_RIGHT_SUPERADMIN'])
        {
            $records = Participants::getParticipantSharedAll();
            $aData->records = count($records);
            $aData->total = ceil($aData->records / 10);
            $i = 0;

            foreach ($records as $row)
            {
                $oShared = User::getName($row['share_uid']); //for conversion of uid to human readable names
                $owner = User::getName($row['owner_uid']);
                $aData->rows[$i]['id'] = $row['participant_id'];
                $aData->rows[$i]['cell'] = array($row['firstname'], $row['lastname'], $row['email'], $oShared[0]['full_name'], $row['share_uid'], $owner[0]['full_name'], $row['date_added'], $row['can_edit']);
                $i++;
            }

            echo ls_json_encode($aData);
        }
        // otherwise only the shared participants by that user
        else
        {
            $records = User::getParticipantShared(Yii::app()->session['loginID']);
            $aData->records = count($records);
            $aData->total = ceil($aData->records / 10);
            $i = 0;

            foreach ($records as $row)
            {
                $sharename = User::getName($row['share_uid']); //for conversion of uid to human readable names
                $aData->rows[$i]['id'] = $row['participant_id'];
                $aData['rows'][$i]['cell'] = array($row['firstname'], $row['lastname'], $row['email'], $sharename['full_name'], $row['share_uid'], $row['date_added'], $row['can_edit']);
                $i++;
            }

            echo ls_json_encode($aData);
        }
    }

    /**
     * Takes the edit call from the share panel, which either edits or deletes the share information
     * Basically takes the call on can_edit
     */
    function editShareInfo()
    {
        $operation = Yii::app()->request->getPost('oper');
        if ($operation == 'del') // If operation is delete , it will delete, otherwise edit it
        {
            ParticipantShares::deleteRow($_POST);
        }
        else
        {
            $aData = array(
                'participant_id' => Yii::app()->request->getPost('participant_id'),
                'can_edit' => Yii::app()->request->getPost('can_edit'),
                'share_uid' => Yii::app()->request->getPost('shared_uid')
            );
            ParticipantShares::updateShare($aData);
        }
    }

    /**
     * Loads the view 'attributeControl'
     */
    function attributeControl()
    {
        $this->_loadjqGrid('attributeControl');
    }

    /**
     * Sends the attributes info using JSON encoding
     * Called after the Attribute management grid is loaded
     * @return JSON encoded string containg sharing information
     */
    function getAttributeInfo_json()
    {
        $clang = Yii::app()->lang;
        $page = Yii::app()->request->getPost('page');
        $limit = Yii::app()->request->getPost('rows');
    	$limit = isset($limit) ? $limit : 50; //Stop division by zero errors

        $records = ParticipantAttributeNames::getAttributes();

        $attribute_types = array(
            'DD' => $clang->gT("Drop-down list"),
            'DP' => $clang->gT("Date"),
            'TB' => $clang->gT("Text box")
        );

        $aData->page = $page;
        $aData->records = count($records);
        $aData->total = ceil(ParticipantAttributeNames::getAttributes(true) / $limit);

        $i = 0;
        foreach ($records as $row)
        {
            $aData->rows[$i]['id'] = $row['attribute_id'];
            $aData->rows[$i]['cell'] = array('', $row['attribute_name'], $attribute_types[$row['attribute_type']], $row['visible']);
            $i++;
        }

        echo ls_json_encode($aData);
    }

    /**
     * Takes the edit call from the share panel, which either edits or deletes the share information
     * Basically takes the call on can_edit
     */
    function editAttributeInfo()
    {
        $operation = Yii::app()->request->getPost('oper');
        if ($operation == 'del' && Yii::app()->request->getPost('id'))
        {
            $aAttributeIds = (array) explode(',', Yii::app()->request->getPost('id'));
            $aAttributeIds = array_map('trim', $aAttributeIds);
            $aAttributeIds = array_map('intval', $aAttributeIds);

            foreach ($aAttributeIds as $iAttributeId)
            {
                ParticipantAttributeNames::delAttribute($iAttributeId);
            }
        }
        elseif ($operation == 'add' && Yii::app()->request->getPost('attribute_name'))
        {
            $aData = array(
                'attribute_name' => Yii::app()->request->getPost('attribute_name'),
                'attribute_type' => Yii::app()->request->getPost('attribute_type'),
                'visible' => Yii::app()->request->getPost('visible') == 'TRUE' ? 'TRUE' : 'FALSE'
            );
            echo ParticipantAttributeNames::storeAttribute($aData);
        }
        elseif ($operation == 'edit' && Yii::app()->request->getPost('id'))
        {
            $aData = array(
                'attribute_id' => Yii::app()->request->getPost('id'),
                'attribute_name' => Yii::app()->request->getPost('attribute_name'),
                'attribute_type' => Yii::app()->request->getPost('attribute_type'),
                'visible' => Yii::app()->request->getPost('visible') == 'TRUE' ? 'TRUE' : 'FALSE'
            );
            ParticipantAttributeNames::saveAttribute($aData);
        }
    }

    /**
     * Takes the delete call from the display participants and take appropriate action depending on the condition
     */
    function delParticipant()
    {
        $selectoption = Yii::app()->request->getPost('selectedoption');
        $iParticipantId = Yii::app()->request->getPost('participant_id');
		//echo $selectoption." -- ".$iParticipantId."<br />";
    	//die();

        // Deletes from participants only
        if ($selectoption == 'po')
        {
            Participants::deleteParticipant($iParticipantId);
        }
        // Deletes from central and token table
        elseif ($selectoption == 'ptt')
        {
            Participants::deleteParticipantToken($iParticipantId);
        }
        // Deletes from central , token and assosiated responses as well
        else
        {
            Participants::deleteParticipantTokenAnswer($iParticipantId);
        }
    }

    /**
     * Resposible for editing data on the jqGrid
     */
    function editParticipant()
    {
        $operation = Yii::app()->request->getPost('oper');

        //In case the uid is not editable, then user id is not posted and hence the current user is added in the uid
        if (Yii::app()->request->getPost('owner_uid') == '')
        {
            $oid = Yii::app()->session['loginID'];
        }
        //otherwise the one which is posted is added
        else
        {
            $oid = Yii::app()->request->getPost('owner_uid');
        }
        if (Yii::app()->request->getPost('language') == '')
        {
            $lang = Yii::app()->session['adminlang'];
        }
        else
        {
            $lang = Yii::app()->request->getPost('language');
        }

        // if edit it will update the row
        if ($operation == 'edit')
        {
            $aData = array(
                'participant_id' => Yii::app()->request->getPost('id'),
                'firstname' => Yii::app()->request->getPost('firstname'),
                'lastname' => Yii::app()->request->getPost('lastname'),
                'email' => Yii::app()->request->getPost('email'),
                'language' => Yii::app()->request->getPost('language'),
                'blacklisted' => Yii::app()->request->getPost('blacklisted'),
                'owner_uid' => $oid
            );
            Participants::updateRow($aData);
        }
        // if add it will insert a new row
        elseif ($operation == 'add')
        {
            $uuid = $this->gen_uuid();
            $aData = array(
                'participant_id' => $uuid,
                'firstname' => Yii::app()->request->getPost('firstname'),
                'lastname' => Yii::app()->request->getPost('lastname'),
                'email' => Yii::app()->request->getPost('email'),
                'language' => Yii::app()->request->getPost('language'),
                'blacklisted' => Yii::app()->request->getPost('blacklisted'),
                'owner_uid' => $oid
            );
            Participants::insertParticipant($aData);
        }
    }

    /**
     * Stores the user control setting to the database
     */
    function storeUserControlValues()
    {
        if ($find = Settings_global::model()->findByPk('userideditable'))
        {
            Settings_global::model()->updateByPk('userideditable', array('stg_value'=>Yii::app()->request->getPost('userideditable')));
        }
        else
        {
            $stg = new Settings_global;
            $stg ->stg_name='userideditable';
            $stg ->stg_value=Yii::app()->request->getPost('userideditable');
            $stg->save();
        }
        CController::redirect(Yii::app()->getController()->createUrl('admin/participants/userControl'));
    }

    /**
     * Stores the blacklist setting to the database
     */
    function storeBlacklistValues()
    {
        $values = Array('blacklistallsurveys', 'blacklistnewsurveys', 'blockaddingtosurveys', 'hideblacklisted', 'deleteblacklisted', 'allowunblacklist', 'userideditable');
        foreach ($values as $value)
        {
            if ($find = Settings_global::model()->findByPk($value))
            {
                Settings_global::model()->updateByPk($value, array('stg_value'=>Yii::app()->request->getPost($value)));
            }
            else
            {
                $stg = new Settings_global;
                $stg ->stg_name=$value;
                $stg ->stg_value=Yii::app()->request->getPost($value);
                $stg->save();
            }
        }
        CController::redirect(Yii::app()->getController()->createUrl('admin/participants/blacklistControl'));
    }

    /**
     * Receives an ajax call containing the participant id in the fourth segment of the url
     * Supplies list of survey links - surveys of which this participant is on the tokens table
     * URL: [localurl]/limesurvey/admin/participants/getSurveyInfo_json/pid/[participant_id]
     * RETURNS: json data containing linked survey information (Survey name, survey id, token_id and date_added)
     */
    function getSurveyInfo_json()
    {
        $participantid = Yii::app()->request->getQuery('pid');
        $records = Survey_links::model()->findAllByAttributes((array('participant_id' => $participantid)));
        $aData = new stdClass();
        $aData->page = 1;
        $aData->records = count($records);
        $aData->total = ceil($aData->records / 10);
        $i = 0;

        foreach ($records as $row)
        {
            $surveyname = Surveys_languagesettings::getSurveyNames($row['survey_id']);
            $aData->rows[$i]['cell'] = array($surveyname[0]['surveyls_title'], '<a href=' . Yii::app()->getController()->createUrl("/admin/tokens/browse/surveyid/{$row['survey_id']}") . '>' . $row['survey_id'], $row['token_id'], $row['date_created']);
            $i++;
        }

        echo ls_json_encode($aData);
    }

    /**
     * Returns the count of the participants in the CSV and show it in the title of the modal box
     * This is to give the user the hint to see the number of participants he is exporting
     */
    function exporttocsvcount()
    {
        $clang = $this->getController()->lang;

        $searchconditionurl = Yii::app()->request->getPost('searchcondition');
        $searchcondition = basename($searchconditionurl);

        if (Yii::app()->session['USER_RIGHT_SUPERADMIN']) //If super admin all the participants in the cpdb are counted
        {
            if ($searchcondition != 'getParticipants_json') // if there is a search condition then only the participants that match the search criteria are counted
            {
                $condition = explode("||", $searchcondition);
                if (count($condition) == 3)
                {
                    $query = Participants::getParticipantsSearch($condition, 0, 0);
                }
                else
                {
                    $query = Participants::getParticipantsSearchMultiple($condition, 0, 0);
                }
            }
            else // if no search criteria all the participants will be counted
            {
                $query = Participants::getParticipantsWithoutLimit();
            }
        }
        else // If no search criteria it will simply return the number of participants
        {
            $iUserID = Yii::app()->session['loginID'];
            $query = Particiapnts::getParticipantsOwner($iUserID);
        }

        echo sprintf($clang->gT("Export %s participant(s) to CSV"), count($query));
    }

    /**
     * Outputs the count of participants when using the export all button on the top
     */
    function exporttocsvcountAll()
    {
        if (Yii::app()->session['USER_RIGHT_SUPERADMIN']) //If super admin all the participants in the central table will be counted
        {
            $query = Participants::getParticipantsWithoutLimit();
        }
        else // otherwise only the participants on which the logged in user has the rights
        {
            $iUserID = Yii::app()->session['loginID'];
            $query = Participants::getParticipantsOwner($iUserID);
        }

        if (count($query) > 0) // If count is greater than 0 it will show the message
        {
            printf($clang->gT("Export %s participant(s) to CSV"), count($query));
        }
        else // else it will return a numeric count which will tell that there is no participant to be exported
        {
            echo count($query);
        }
    }

    /**
     * Responsible to export all the participants in the central table
     */
    function exporttocsvAll()
    {
        Yii::app()->loadHelper('export');  // loads the export helper
        if (Yii::app()->session['USER_RIGHT_SUPERADMIN']) //If super admin all the participants will be exported
        {
            $query = Participants::getParticipantsWithoutLimit();
        }
        else // otherwise only the ones over which the user has rights on
        {
            $iUserID = Yii::app()->session['loginID'];
            $query = Participants::getParticipantsOwner($iUserID);
        }

        if (!$query)
            return false;

        // These are the consistent fields that will be exported
        $fields = array('participant_id', 'firstname', 'lastname', 'email', 'language', 'blacklisted', 'owner_uid');
        $i = 0;
        $outputarray = array();

        foreach ($fields as $field)
        {
            $outputarray[0][$i] = $field; // The fields are being added to the index 0 of the array to be written to the header of the csv file
            $i++;
        }

        $attributenames = ParticipantAttributeNames::getAttributes();
        // Attribute names are being added to the index 0 of the array
        foreach ($attributenames as $key => $value)
        {
            $outputarray[0][$i] = $value['attribute_name'];
            $i++;
        }
        // Fetching the table data
        $i = 1;
        $j = 0;
        // Read through the query result and add it to the array
        // Please not it will give only basic field in the central database
        foreach ($query as $field => $aData)
        {
            foreach ($fields as $field)
            {
                $outputarray[$i][$j] = $aData[$field];
                //increment the column
                $j++;
            }

            // it will iterate through the additional attributes that the user has choosen to export and will fetch the values
            // that are to be exported to the CSV file
            foreach ($attributenames as $key => $value)
            {
                $answer = ParticipantAttributeNames::getAttributeValue($aData['participant_id'], $value['attribute_id']);
                if (isset($answer['value']))
                { // if the attribute value is there for that attribute and the user then it will written to the array
                    $outputarray[$i][$j] = $answer['value'];
                    //increment the column
                    $j++;
                }
                else
                { // otherwise blank value will be written to the array
                    $outputarray[$i][$j] = "";
                    //increment the column
                    $j++;
                }
            }
            // increment the row
            $i++;
        }

        // Load the helper and pass the array to be written to a CSV file
        CPDBExport($outputarray, "central_" . time());
    }

    /**
     * Similar to export to all message where it counts the number to participants to be copied
     * and echo them to be displayed in modal box header
     */
    function getaddtosurveymsg()
    {
        $searchcondition = basename(Yii::app()->request->getPost('searchcondition'));

        // If there is a search condition in the url of the jqGrid
        if ($searchcondition != 'getParticipants_json')
        {
            $participantid = "";
            $condition = explode("||", $searchcondition);
            if (count($condition) == 3) // If there is no and condition , if the count is equal to 3 that means only one condition
            {
                $query = Participants::getParticipantsSearch($condition, 0, 0);
            }
            else  // if there are 'and' and 'or' condition in the condition the count is to be greater than 3
            {
                $query = Participants::getParticipantsSearchMultiple($condition, 0, 0);
            }

            printf( $this->getController()->lang->gT("%s participant(s) are to be copied "), count($query));
        }
        // if there is no search condition the participants will be counted on the basis of who is logged in
        else
        {
            if (Yii::app()->session['USER_RIGHT_SUPERADMIN']) //If super admin all the participants will be visible
            {
                $query = Participants::getParticipantsWithoutLimit();
            }
            else
            {
                $query = Participants::getParticipantsOwner(Yii::app()->session['loginID']);
            }

            printf($this->getController()->lang->gT("%s participant(s) are to be copied "), count($query));
        }
    }

    /**
     * Gets the ids of participants to be copied to the individual survey
     */
    function getSearchIDs()
    {
        $searchcondition = basename(Yii::app()->request->getPost('searchcondition')); // get the search condition from the URL
        /* a search contains posted data inside $_POST['searchcondition'].
        * Each seperate query is made up of 3 fields, seperated by double-pipes ("|")
        * EG: fname||eq||jason||lname||ct||c
        *
        */
        if ($searchcondition != 'getParticipants_json') // if there is a search condition present
        {
            $participantid = "";
            $condition = explode("||", $searchcondition);  // explode the condition to the array
            // format for the condition is field||condition||value
            if (count($condition) == 3) // if count is 3 , then it's a single search
            {
                $query = Participants::getParticipantsSearch($condition, 0, 0);
            }
            else// if count is more than 3 , then it's a multiple search
            {
                $query = Participants::getParticipantsSearchMultiple($condition, 0, 0);
            }
            foreach ($query as $key => $value)
            {
                $participantid = $participantid . "," . $value['participant_id']; // combine the participant id's in an string
            }
            echo $participantid; //echo the participant id's
        }
        else// if no search condition
        {
            $participantid = ""; // initiallise the participant id to blank
            if (Yii::app()->session['USER_RIGHT_SUPERADMIN']) //If super admin all the participants will be visible
            {
                $query = Participants::getParticipantsWithoutLimit(); // get all the participant id if it is a super admin
            }
            else // get participants on which the user has right on
            {
                $query = Participants::getParticipantsOwner(Yii::app()->session['loginID']);
            }

            foreach ($query as $key => $value)
            {
                $participantid = $participantid . "," . $value['participant_id']; // combine the participant id's in an string
            }
            echo $participantid; //echo the participant id's
        }
    }

    /**
     * Responsible for reading the CSV file line by line, check for duplicate participants
     * invalid participants and invalid attributes and copy them to the central table
     * Also responsible for creation of new attribute and mapping of old attribute to attribute in csv
     */
    function exporttocsv()
    {
        Yii::app()->loadHelper('export');

        $searchconditionurl = Yii::app()->request->getPost('searchcondition');
        $searchcondition = basename($searchconditionurl);

        if (Yii::app()->session['USER_RIGHT_SUPERADMIN']) //If super admin all the participants will be visible
        {
            if ($searchcondition != 'getParticipants_json') // If there is a search condition then only does participants are exported
            {
                $condition = explode("||", $searchcondition);
                if (count($condition) == 3) // Single search
                {
                    $query = Participants::getParticipantsSearch($condition, 0, 0);
                }
                else //combined search
                {
                    $query = Participants::getParticipantsSearchMultiple($condition, 0, 0);
                }
            } // else all the participants in the central table will be exported since it's superadmin
            else
            {
                $query = Participants::getParticipantsWithoutLimit();
            }
        }
        else
        {
            $iUserID = Yii::app()->session['loginID']; // else only the
            $query = Participants::getParticipantsOwner($iUserID);
        }

        if (!$query)
            return false;

        // Field names in the first row
        $fields = array('participant_id', 'firstname', 'lastname', 'email', 'language', 'blacklisted', 'owner_uid');
        $i = 0;
        $outputarray = array(); // The array to be passed to the export helper to be written to a csv file
        foreach ($fields as $field)
        {
            $outputarray[0][$i] = $field; //fields written to output array
            $i++;
        }

        if (Yii::app()->request->getQuery('id') == "null")
        {
            $i = 1;
            $j = 0;
            foreach ($query as $field => $aData)
            {
                foreach ($fields as $field)
                {
                    $outputarray[$i][$j] = $aData[$field];
                    $j++;
                }
                $i++;
            }
            CPDBExport($outputarray, "central_" . time());
        }
        else
        {
            $iAttributeId = explode(",", Yii::app()->request->getQuery('id'));
            foreach ($iAttributeId as $key => $value)
            {
                $attributename = ParticipantAttributeNames::getAttributeNames($value);
                $outputarray[0][$i] = $attributename[0]['attribute_name'];
                $i++;
            }
            $i = 1;
            $j = 0;
            // Fetching the table data
            foreach ($query as $field => $aData)
            {
                foreach ($fields as $field)
                {
                    $outputarray[$i][$j] = $aData[$field];
                    $j++;
                }
                foreach ($iAttributeId as $key => $value)
                {
                    $answer = ParticipantAttributeNames::getAttributeValue($aData['participant_id'], $value);
                    if (isset($answer['value']))
                    {
                        $outputarray[$i][$j] = $answer['value'];
                        $j++;
                    }
                    else
                    {
                        $outputarray[$i][$j] = "";
                        $j++;
                    }
                }
                $i++;
            }
            CPDBExport($outputarray, "central_" . time());
        }
    }

    function getParticipantsResults_json()
    {
    	///admin/participants/getParticipantsResults_json/search/email||contains||com
        //Possible methods: equal,contains,notequal,notcontains,greaterthan,lessthan
        //First entry is field to search, second method, third value, seperated by double pipe "||"
        $page = Yii::app()->request->getPost('page');
        $limit = Yii::app()->request->getPost('rows');
    	$page=($page) ? $page : 1;
    	$limit=($limit) ? $limit : 25;

        $attid = ParticipantAttributeNames::getAttributeVisibleID();
        $participantfields = array('participant_id', 'can_edit', 'firstname', 'lastname', 'email', 'blacklisted', 'surveys', 'language', 'owner_uid');

        //If super admin all the participants will be visible
        if (Yii::app()->session['USER_RIGHT_SUPERADMIN'])
        {
            $searchcondition = Yii::app()->request->getQuery('search');

            $searchcondition = urldecode($searchcondition);
            $finalcondition = array();
            $condition = explode("||", $searchcondition);
            $aData = new stdClass();
            $aData->page = $page;
            if (count($condition) == 3)
            {
                $records = Participants::getParticipantsSearch($condition, $page, $limit);
                $aData->records = count(Participants::getParticipantsSearch($condition, 0, 0));
                $aData->total = ceil($aData->records / $limit);
            }
            else
            {
                $records = Participants::getParticipantsSearchMultiple($condition, $page, $limit);
                $aData->records = count(Participants::getParticipantsSearchMultiple($condition, 0, 0));
                $aData->total = ceil($aData->records / $limit);
            }
            $i = 0;

            foreach ($records as $row => $value)
            {
                $username = User::getName($value['owner_uid']); //for conversion of uid to human readable names
                $surveycount = Participants::getSurveyCount($value['participant_id']);
                $sortablearray[$i] = array($value['participant_id'], "true", $value['firstname'], $value['lastname'], $value['email'], $value['blacklisted'], $surveycount, $value['language'], $username[0]['full_name']); // since it's the admin he has access to all editing on the participants inspite of what can_edit option is
                $attributes = ParticipantAttributeNames::getParticipantVisibleAttribute($value['participant_id']);
                foreach ($attid as $iAttributeId)
                {
                    $answer = ParticipantAttributeNames::getAttributeValue($value['participant_id'], $iAttributeId['attribute_id']);
                    if (isset($answer['value']))
                    {
                        array_push($sortablearray[$i], $answer['value']);
                    }
                    else
                    {
                        array_push($sortablearray[$i], "");
                    }
                }
                $i++;
            }

            function subval_sort($a, $subkey, $order)
            {
                foreach ($a as $k => $v)
                {
                    $b[$k] = strtolower($v[$subkey]);
                }
                if ($order == "asc")
                {
                    asort($b, SORT_REGULAR);
                }
                else
                {
                    arsort($b, SORT_REGULAR);
                }
                foreach ($b as $key => $val)
                {
                    $c[] = $a[$key];
                }
                return $c;
            }

            if (!empty($sortablearray))
            {
                $indexsort = array_search(Yii::app()->request->getPost('sidx'), $participantfields);
                $sortedarray = subval_sort($sortablearray, $indexsort, Yii::app()->request->getPost('sord'));
                $i = 0;
                $count = count($sortedarray[0]);

                foreach ($sortedarray as $key => $value)
                {
                    $aData->rows[$i]['id'] = $value[0];
                    $aData->rows[$i]['cell'] = array();
                    for ($j = 0; $j < $count; $j++)
                    {
                        array_push($aData->rows[$i]['cell'], $value[$j]);
                    }
                    $i++;
                }
            }
            echo ls_json_encode($aData);
        }
        // Only the owned and shared participants will be visible
        else
        {
            $searchcondition = Yii::app()->request->getQuery('search');
            $searchcondition = urldecode($searchcondition);
            $finalcondition = array();
            $condition = explode("||", $searchcondition);
            $aData = new stdClass();
            $aData->page = $page;
            if (count($condition) == 3)
            {
                $records = Participants::getParticipantsSearch($condition, $page, $limit);
            }
            else
            {
                $records = Participants::getParticipantsSearchMultiple($condition, $page, $limit);
            }
            $i = 0;
            foreach ($records as $row => $value)
            {
                if (Participants::is_owner($value['participant_id']))
                {
                    $username = User::getName($value['owner_uid']); //for conversion of uid to human readable names
                    $surveycount = Participants::getSurveyCount($value['participant_id']);
                    $sortablearray[$i] = array($value['participant_id'], "true", $value['firstname'], $value['lastname'], $value['email'], $value['blacklisted'], $surveycount, $value['language'], $username[0]['full_name']); // since it's the admin he has access to all editing on the participants inspite of what can_edit option is
                    $attributes = ParticipantAttributeNames::getParticipantVisibleAttribute($value['participant_id']);
                    foreach ($attid as $iAttributeId)
                    {
                        $answer = ParticipantAttributeNames::getAttributeValue($value['participant_id'], $iAttributeId['attribute_id']);
                        if (isset($answer['value']))
                        {
                            array_push($sortablearray[$i], $answer['value']);
                        }
                        else
                        {
                            array_push($sortablearray[$i], "");
                        }
                    }
                    $i++;
                }
            }

            function subval_sort($a, $subkey, $order)
            {
                foreach ($a as $k => $v)
                {
                    $b[$k] = strtolower($v[$subkey]);
                }
                if ($order == "asc")
                {
                    asort($b, SORT_REGULAR);
                }
                else
                {
                    arsort($b, SORT_REGULAR);
                }
                foreach ($b as $key => $val)
                {
                    $c[] = $a[$key];
                }
                return $c;
            }

            if (!empty($sortablearray))
            {
                $aData->records = count($sortablearray);
                $aData->total = ceil(count($sortablearray) / $limit);
                $indexsort = array_search(Yii::app()->request->getPost('sidx'), $participantfields);
                $sortedarray = subval_sort($sortablearray, $indexsort, Yii::app()->request->getPost('sord'));
                $i = 0;
                $count = count($sortedarray[0]);
                foreach ($sortedarray as $key => $value)
                {
                    $aData->rows[$i]['id'] = $value[0];
                    $aData->rows[$i]['cell'] = array();
                    for ($j = 0; $j < $count; $j++)
                    {
                        array_push($aData->rows[$i]['cell'], $value[$j]);
                    }
                    $i++;
                }
            }
            echo ls_json_encode($aData);
        }
    }

	/*
	   * Sends the data in JSON format extracted from the database to be displayed using the jqGrid
	*/
    function getParticipants_json()
    {
    	function subval_sort($a, $subkey, $order)
    	{
    		foreach ($a as $k => $v)
    		{
    			$b[$k] = strtolower($v[$subkey]);
    		}
    		if ($order == "asc")
    		{
    			asort($b, SORT_REGULAR);
    		}
    		else
    		{
    			arsort($b, SORT_REGULAR);
    		}
    		foreach ($b as $key => $val)
    		{
    			$c[] = $a[$key];
    		}
    		return $c;
    	}

        $page = Yii::app()->request->getPost('page');
        $limit = Yii::app()->request->getPost('rows');
    	$limit = isset($limit) ? $limit : 50; //Stop division by zero errors

        $attid = ParticipantAttributeNames::getAttributeVisibleID();
        $participantfields = array('participant_id', 'can_edit', 'firstname', 'lastname', 'email', 'blacklisted', 'surveys', 'language', 'owner_uid');
        foreach ($attid as $key => $value)
        {
            array_push($participantfields, $value['attribute_name']);
        }

        //If super admin all the participants will be visible
        if (Yii::app()->session['USER_RIGHT_SUPERADMIN'])
        {
            $records = Participants::getParticipants($page, $limit);
            $aData->page = $page;
            $aData->records = Participants::model()->count();
            $aData->total = ceil($aData->records / $limit);
            $i = 0;
        	$sortablearray=array();
        	foreach ($records as $key => $row)
            {
                $username = User::getName($row['owner_uid']); //for conversion of uid to human readable names
                $surveycount = Participants::getSurveyCount($row['participant_id']);
                $sortablearray[$i] = array($row['participant_id'], "true", $row['firstname'], $row['lastname'], $row['email'], $row['blacklisted'], $surveycount, $row['language'], $username[0]['full_name']); // since it's the admin he has access to all editing on the participants inspite of what can_edit option is
                $attributes = ParticipantAttributeNames::getParticipantVisibleAttribute($row['participant_id']);
                foreach ($attid as $iAttributeId)
                {
                    $answer = ParticipantAttributeNames::getAttributeValue($row['participant_id'], $iAttributeId['attribute_id']);
                    if (isset($answer['value']))
                    {
                        array_push($sortablearray[$i], $answer['value']);
                    }
                    else
                    {
                        array_push($sortablearray[$i], "");
                    }
                }
                $i++;
            }

            $indexsort = array_search(Yii::app()->request->getPost('sidx'), $participantfields);
        	if(!empty($sortablearray)) {
        		$sortedarray = subval_sort($sortablearray, $indexsort, Yii::app()->request->getPost('sord'));
        		$i = 0;
        		$count = count($sortedarray[0]);
        		foreach ($sortedarray as $key => $value)
        		{
        			$aData->rows[$i]['id'] = $value[0];
        			$aData->rows[$i]['cell'] = array();
        			for ($j = 0; $j < $count; $j++)
        			{
        				array_push($aData->rows[$i]['cell'], $value[$j]);
        			}
        			$i++;
        		}
        	}
            echo ls_json_encode($aData);
        }
        // Only the owned and shared participants will be visible
        else
        {
            $iUserID = Yii::app()->session['loginID'];
            $records = Participants::getParticipantsOwner($iUserID);
            $aData->page = $page;
            $aData->records = count($records);
            $aData->total = ceil($aData->records / $limit);
            $attid = ParticipantAttributeNames::getAttributeVisibleID();
            $i = 0;
        	$sortablearray=array();
            foreach ($records as $row)
            {
                $surveycount = Participants::getSurveyCount($row['participant_id']);
                $ownername = User::getName($row['owner_uid']); //for conversion of uid to human readable names
                $sortablearray[$i] = array($row['participant_id'], $row['can_edit'], $row['firstname'], $row['lastname'], $row['email'], $row['blacklisted'], $surveycount, $row['language'], $ownername[0]['full_name']);
                $attributes = ParticipantAttributeNames::getParticipantVisibleAttribute($row['participant_id']);
                foreach ($attid as $iAttributeId)
                {
                    $answer = ParticipantAttributeNames::getAttributeValue($row['participant_id'], $iAttributeId['attribute_id']);
                    if (isset($answer['value']))
                    {
                        array_push($sortablearray[$i], $answer['value']);
                    }
                    else
                    {
                        array_push($sortablearray[$i], "");
                    }
                }
                $i++;
            }

            $indexsort = array_search(Yii::app()->request->getPost('sidx'), $participantfields);
        	if(!empty($sortablearray)) {
        		$sortedarray = subval_sort($sortablearray, $indexsort, Yii::app()->request->getPost('sord'));
	            $i = 0;
	            $count = count($sortedarray[0]);
	            foreach ($sortedarray as $key => $value)
	            {
	                $aData->rows[$i]['id'] = $value[0];
	                $aData->rows[$i]['cell'] = array();
	                for ($j = 0; $j < $count; $j++)
	                {
	                    array_push($aData->rows[$i]['cell'], $value[$j]);
	                }
	                $i++;
	            }
        	}
            echo ls_json_encode($aData);
        }
    }

    /*
     * Fetches the attributes of a participant to be displayed in the attribute subgrid
     */
    function getAttribute_json()
    {
        $iParticipantId = Yii::app()->request->getQuery('pid');
        $records = ParticipantAttributeNames::getParticipantVisibleAttribute($iParticipantId);
        $getallattributes = ParticipantAttributeNames::getAttributes();
        $aData = new stdClass();
        $aData->page = 1;
        $aData->records = count($records);
        $aData->total = ceil($aData->records / 10);
        $aData->rows[0]['id'] = $iParticipantId;
        $aData->rows[0]['cell'] = array();
        $i = 0;

        $doneattributes = array();
        foreach ($records as $row)
        {
            $aData->rows[$i]['id'] = $row['participant_id'] . "_" . $row['attribute_id'];
            $aData->rows[$i]['cell'] = array("", $row['participant_id'], $row['attribute_type'], $row['attribute_name'], $row['value']);
            if ($row['attribute_type'] == "DD")
            {
                $attvalues = ParticipantAttributeNames::getAttributesValues($row['attribute_id']);
                if (!empty($attvalues))
                {
                    $attval = "";
                    foreach ($attvalues as $val)
                    {
                        $attval .= $val['value'] . ":" . $val['value'];
                        $attval .= ";";
                    }
                    $attval = substr($attval, 0, -1);
                    array_push($aData->rows[$i]['cell'], $attval);
                }
                else
                {
                    array_push($aData->rows[$i]['cell'], "");
                }
            }
            else
            {
                array_push($aData->rows[$i]['cell'], "");
            }
            array_push($doneattributes, $row['attribute_id']);
            $i++;
        }
        if (count($doneattributes) == 0)
        {
            $attributenotdone = ParticipantAttributeNames::getAttributes();
        }
        else
        {
            $attributenotdone = ParticipantAttributeNames::getnotaddedAttributes($doneattributes);
        }
        if ($attributenotdone > 0)
        {
            foreach ($attributenotdone as $row)
            {

                $aData->rows[$i]['id'] = $iParticipantId . "_" . $row['attribute_id'];
                $aData->rows[$i]['cell'] = array("", $iParticipantId, $row['attribute_type'], $row['attribute_name'], "");
                if ($row['attribute_type'] == "DD")
                {
                    $attvalues = ParticipantAttributeNames::getAttributesValues($row['attribute_id']);
                    if (!empty($attvalues))
                    {
                        $attval = "";
                        foreach ($attvalues as $val)
                        {
                            $attval .= $val['value'] . ":" . $val['value'];
                            $attval .= ";";
                        }
                        $attval = substr($attval, 0, -1);
                        array_push($aData->rows[$i]['cell'], $attval);
                    }
                    else
                    {
                        array_push($aData->rows[$i]['cell'], "");
                    }
                }
                else
                {
                    array_push($aData->rows[$i]['cell'], "");
                }
                $i++;
            }
        }
        echo ls_json_encode($aData);
    }

    /*
     * Gets the data from the form for add participants and pass it to the participants model
     */
    function storeParticipants()
    {
        $aData = array('participant_id' => uniqid(),
            'firstname' => Yii::app()->request->getPost('firstname'),
            'lastname' => Yii::app()->request->getPost('lastname'),
            'email' => Yii::app()->request->getPost('email'),
            'language' => Yii::app()->request->getPost('language'),
            'blacklisted' => Yii::app()->request->getPost('blacklisted'),
            'owner_uid' => Yii::app()->request->getPost('owner_uid'));

        Participants::insertParticipant($aData);
    }

    /*
     * Responsible for showing the additional attribute for central database
     */
    function viewAttribute()
    {
        $iAttributeId = Yii::app()->request->getQuery('aid');
        $aData = array(
            'attributes' => ParticipantAttributeNames::getAttribute($iAttributeId),
            'attributenames' => ParticipantAttributeNames::getAttributeNames($iAttributeId),
            'attributevalues' => ParticipantAttributeNames::getAttributesValues($iAttributeId)
        );

        $this->getController()->_css_admin_includes(Yii::app()->getConfig('styleurl') . 'admin/'.Yii::app()->getConfig("admintheme").'/participants.css');
        $this->getController()->_css_admin_includes(Yii::app()->getConfig('styleurl') . 'admin/'.Yii::app()->getConfig("admintheme").'/viewAttribute.css');

        $this->_renderWrappedTemplate('participants', array('participantsPanel', 'viewAttribute'), $aData);
    }

    /*
     * Responsible for saving the additional attribute. It iterates through all the new attributes added dynamically
     * and iterates through them
     */
    function saveAttribute()
    {
        $iAttributeId = Yii::app()->request->getQuery('aid');
        $aData = array(
            'attribute_id' => $iAttributeId,
            'attribute_type' => Yii::app()->request->getPost('attribute_type'),
            'visible' => Yii::app()->request->getPost('visible')
        );
        ParticipantAttributeNames::saveAttribute($aData);

        foreach ($_POST as $key => $value)
        {
            // check for language code in the post variables this is a hack as the only way to check for language data
            if (strlen($key) == 2)
            {
                $langdata = array(
                    'attribute_id' => $iAttributeId,
                    'attribute_name' => $value,
                    'lang' => $key
                );

                ParticipantAttributeNames::saveAttributeLanguages($langdata);
            }
        }
        if (Yii::app()->request->getPost('langdata'))
        {
            $langdata = array(
                'attribute_id' => $iAttributeId,
                'attribute_name' => Yii::app()->request->getPost('attname'),
                'lang' => Yii::app()->request->getPost('langdata')
            );

            ParticipantAttributeNames::saveAttributeLanguages($langdata);
        }
        if (Yii::app()->request->getPost('attribute_value_name_1'))
        {
            $i = 1;
            do
            {
                $attvaluename = 'attribute_value_name_' . $i;
                if (!empty($_POST[$attvaluename]))
                {
                    $aDatavalues[$i] = array(
                        'attribute_id' => $iAttributeId,
                        'value' => Yii::app()->request->getPost($attvaluename)
                    );
                }
                $i++;
            } while (isset($_POST[$attvaluename]));
            ParticipantAttributeNames::storeAttributeValues($aDatavalues);
        }
        if (Yii::app()->request->getPost('editbox'))
        {
            $editattvalue = array(
                'attribute_id' => $iAttributeId,
                'value_id' => Yii::app()->request->getPost('value_id'),
                'value' => Yii::app()->request->getPost('editbox')
            );
            ParticipantAttributeNames::saveAttributeValue($editattvalue);
        }
        CController::redirect(Yii::app()->getController()->createUrl('admin/participants/attributeControl'));
    }

    /*
     * Responsible for deleting the additional attribute values in case of drop down.
     */
    function delAttributeValues()
    {
        $iAttributeId = Yii::app()->request->getQuery('aid');
        $iValueId = Yii::app()->request->getQuery('vid');
        ParticipantAttributeNames::delAttributeValues($iAttributeId, $iValueId);
        CController::redirect(Yii::app()->getController()->createUrl('/admin/participants/viewAttribute/aid/' . $iAttributeId));
    }

    /*
     * Responsible for editing the additional attributes values
     */
    function editAttributevalue()
    {
        if (Yii::app()->request->getPost('oper') == "edit" && Yii::app()->request->getPost('attvalue'))
        {
            $iAttributeId = explode("_", Yii::app()->request->getPost('id'));
            $aData = array('participant_id' => Yii::app()->request->getPost('participant_id'), 'attribute_id' => $iAttributeId[1], 'value' => Yii::app()->request->getPost('attvalue'));
            ParticipantAttributeNames::editParticipantAttributeValue($aData);
        }
    }

    function attributeMapCSV()
    {
        $config['upload_path'] = './tmp/uploads';
        $config['allowed_types'] = 'text/x-csv|text/plain|application/octet-stream|csv';
        $config['max_size'] = '1000';

        $clang = $this->getController()->lang;
        $sFilePath = preg_replace('/\\\/', '/', Yii::app()->getConfig('tempdir')) . "/" . $_FILES['the_file']['name'];
        $bMoveFileResult = @move_uploaded_file($_FILES['the_file']['tmp_name'], $sFilePath);
        $errorinupload = '';
        $filterblankemails = Yii::app()->request->getPost('filterbea');

        if (!$bMoveFileResult)
        {
            $templateData['error_msg'] = sprintf($clang->gT("An error occurred uploading your file. This may be caused by incorrect permissions in your %s folder."), Yii::app()->getConfig('tempdir'));
            $errorinupload = array('error' => $this->upload->display_errors());
            Yii::app()->session['summary'] = array('errorinupload' => $errorinupload);
            $this->_renderWrappedTemplate('participants', array('participantsPanel', 'uploadSummary'));
        }
        else
        {
            $aData = array('upload_data' => $_FILES['the_file']);
            $sFileName = $_FILES['the_file']['name'];

            $regularfields = array('firstname', 'participant_id', 'lastname', 'email', 'language', 'blacklisted', 'owner_uid');
            $csvread = fopen($sFilePath, 'r');

            $seperator = Yii::app()->request->getPost('seperatorused');
            $firstline = fgetcsv($csvread, 1000, ',');
            $selectedcsvfields = array();
            foreach ($firstline as $key => $value)
            {
                if (!in_array($value, $regularfields))
                {
                    array_push($selectedcsvfields, $value);
                }
            }

            $linecount = count(file($sFilePath));

            $attributes = ParticipantAttributeNames::model()->getAttributes();
            $aData = array(
                'attributes' => $attributes,
                'firstline' => $selectedcsvfields,
                'fullfilepath' => $sFilePath,
                'linecount' => $linecount - 1,
                'filterbea' => $filterblankemails
            );
            $this->_renderWrappedTemplate('participants', 'attributeMapCSV', $aData);
        }
    }

    /*
     * Uploads the file to the server and process it for valid enteries and import them into database
     */
    function uploadCSV()
    {
        unset(Yii::app()->session['summary']);
        $characterset = Yii::app()->request->getPost('characterset');
        $seperator = Yii::app()->request->getPost('seperatorused');
        $newarray = Yii::app()->request->getPost('newarray');
        $mappedarray = Yii::app()->request->getPost('mappedarray');
        $sFilePath = Yii::app()->request->getPost('fullfilepath');
        $filterblankemails = Yii::app()->request->getPost('filterbea');
        $errorinupload = "";
        $tokenlistarray = file($sFilePath);
        $recordcount = 0;
        $mandatory = 0;
        $mincriteria = 0;
        $imported = 0;
        $dupcount = 0;
        $duplicatelist = array();
        $invalidemaillist = array();
        $invalidformatlist = array();
        $invalidattribute = array();
        $invalidparticipantid = array();
        // This allows to read file with MAC line endings too
        @ini_set('auto_detect_line_endings', true);
        // open it and trim the endings
        $separator = Yii::app()->request->getPost('seperatorused');
        $uploadcharset = Yii::app()->request->getPost('characterset');
        /* The $newarray contains a list of fields that will be used
           to create new attributes */
		if (!empty($newarray))
        {
			/* Create a new entry in the lime_participant_attribute_names table,
			   and it's associated lime_participant_attribute_names_lang table
			   for each NEW attribute being created in this import process */
            foreach ($newarray as $key => $value)
            {
                $aData = array('attribute_type' => 'TB', 'attribute_name' => $value, 'visible' => 'FALSE');
                $insertid = ParticipantAttributeNames::model()->storeAttributeCSV($aData);
            	/* Keep a record of the attribute_id for this new attribute
            	   in the $mappedarray string. For example, if the new attribute
            	   has attribute_id of 35 and is called "gender",
            	   $mappedarray['35']='gender' */
				$mappedarray[$insertid] = $value;
            }
        }
        if (!isset($uploadcharset))
        {
            $uploadcharset = 'auto';
        }
        foreach ($tokenlistarray as $buffer) //Iterate through the CSV file line by line
        {
        	$buffer = @mb_convert_encoding($buffer, "UTF-8", $uploadcharset);
            $firstname = "";
            $lastname = "";
            $email = "";
            $language = "";
        	if ($recordcount == 0) {
            	//The first time we iterate through the file we look at the very
            	//first line, which contains field names, not values to import
                // Pick apart the first line
                $buffer = removeBOM($buffer);
                $attrid = ParticipantAttributeNames::model()->getAttributeID();
                $allowedfieldnames = array('participant_id', 'firstname', 'lastname', 'email', 'language', 'blacklisted');
                if (!empty($mappedarray))
                {
                    foreach ($mappedarray as $key => $value)
                    {
                        array_push($allowedfieldnames, $value);
                    }
                }
                //For Attributes
                switch ($separator)
                {
                    case 'comma':
                        $separator = ',';
                        break;
                    case 'semicolon':
                        $separator = ';';
                        break;
                    default:
                        $comma = substr_count($buffer, ',');
                        $semicolon = substr_count($buffer, ';');
                        if ($semicolon > $comma)
                            $separator = ';'; else
                            $separator = ',';
                }
                $firstline = convertCSVRowToArray($buffer, $separator, '"');
                $firstline = array_map('trim', $firstline);
                $ignoredcolumns = array();
                //now check the first line for invalid fields
                foreach ($firstline as $index => $fieldname)
                {
                    $firstline[$index] = preg_replace("/(.*) <[^,]*>$/", "$1", $fieldname);
                    $fieldname = $firstline[$index];
                    if (!in_array($fieldname, $allowedfieldnames))
                    {
                        $ignoredcolumns[] = $fieldname;
                    }
                }
                if (!in_array('firstname', $firstline) || !in_array('lastname', $firstline) || !in_array('email', $firstline))
                {
                    $recordcount = count($tokenlistarray);
                    break;
                }
        	} else {
                // After looking at the first line, we now import the actual values
                $line = convertCSVRowToArray($buffer, $separator, '"');
                if (count($firstline) != count($line))
                {
                    $invalidformatlist[] = $recordcount;
                    continue;
                }
                $writearray = array_combine($firstline, $line);
                //kick out ignored columns
                foreach ($ignoredcolumns as $column)
                {
                    unset($writearray[$column]);
                }
                $invalidemail = false;
                $dupfound = false;
                $thisduplicate = 0;
                $filterduplicatefields = array('firstname', 'lastname', 'email');

                //Check for duplicate participants
                $aData = array(
                         'firstname' => $writearray['firstname'],
                         'lastname' => $writearray['lastname'],
                         'email' => $writearray['email'],
                         'owner_uid' => Yii::app()->session['loginID']
                         );
            	//HACK - converting into SQL instead of doing an array search
                $aData = "firstname = '".mysql_real_escape_string($writearray['firstname'])."' AND lastname = '".mysql_real_escape_string($writearray['lastname'])."' AND email = '".mysql_real_escape_string($writearray['email'])."' AND owner_uid = '".Yii::app()->session['loginID']."'";
                //End of HACK
				$aData = Participants::model()->checkforDuplicate($aData);
                if ($aData == true) {
                    $thisduplicate = 1;
                    $dupcount++;
                }
                if ($thisduplicate == 1) {
                    $dupfound = true;
                    $duplicatelist[] = $writearray['firstname'] . " " . $writearray['lastname'] . " (" . $writearray['email'] . ")";
                }

                //Checking the email address is in a valid format
                $invalidemail = false;
                $writearray['email'] = trim($writearray['email']);
                if ($writearray['email'] != '') {
                    $aEmailAddresses = explode(';', $writearray['email']);
                    foreach ($aEmailAddresses as $sEmailaddress) {
                        if (!validateEmailAddress($sEmailaddress)) {
                            $invalidemail = true;
                            $invalidemaillist[] = $line[0] . " " . $line[1] . " (" . $line[2] . ")";
                        }
                    }
                }
            	if (!$dupfound && !$invalidemail) {
            		//If it isn't a duplicate value or an invalid email, process the entry

               	    //First, process the known fields
                    if (!isset($writearray['participant_id']) || $writearray['participant_id'] == "") {
                        $uuid = $this->gen_uuid(); //Generate a UUID for the new participant
                        $writearray['participant_id'] = $uuid;
                    }
                    if (isset($writearray['emailstatus']) && trim($writearray['emailstatus'] == '')) {
                        unset($writearray['emailstatus']);
                    }
                	if (!isset($writearray['language']) || $writearray['language'] == "") {
                		$writearray['language'] = "en";
                	}
                	if (!isset($writearray['blacklisted']) || $writearray['blacklisted'] == "") {
                        $writearray['blacklisted'] = "N";
                    }
                    $writearray['owner_uid'] = Yii::app()->session['loginID'];
                    if (isset($writearray['validfrom']) && trim($writearray['validfrom'] == '')) {
                        unset($writearray['validfrom']);
                    }
                    if (isset($writearray['validuntil']) && trim($writearray['validuntil'] == '')) {
                        unset($writearray['validuntil']);
                    }
                    $dontimport=false;
                    if (($filterblankemails == "accept" && $writearray['email'] == "") || $writearray['firstname'] == "" || $writearray['lastname'] == "") {
                    	//The mandatory fields of email, firstname and lastname
						//must be filled, but one or more are empty
                        $mandatory++;
                        $dontimport=true;
                    } else {
                        foreach ($writearray as $key => $value) {
                            if (!empty($mappedarray)) {
                            	//The mapped array contains the attributes we are
                            	//saving in this import
                            	if (in_array($key, $allowedfieldnames)) {
                                    foreach ($mappedarray as $attid => $attname) {
                                        if ($attname == $key) {
                                            if (!empty($value)) {
                                                $aData = array('participant_id' => $writearray['participant_id'],
                                                               'attribute_id' => $attid,
                                                               'value' => $value);
                                             	ParticipantAttributeNames::model()->saveParticipantAttributeValue($aData);
                                            } else {
                                                //If the value is empty, don't write the value
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    //If any of the mandatory fields are blank, then don't import this user
                    if(!$dontimport)
                    {
                        Participants::model()->insertParticipantCSV($writearray);
                        $imported++;
                    }
            	}
                $mincriteria++;
            }
            $recordcount++;
        }
        unlink($sFilePath);
        $clang = $this->getController()->lang;
        $aData = array();
        $aData['clang'] = $clang;
        $aData['recordcount'] = $recordcount - 1;
        $aData['duplicatelist'] = $duplicatelist;
        $aData['mincriteria'] = $mincriteria;
        $aData['imported'] = $imported;
        $aData['errorinupload'] = $errorinupload;
        $aData['invalidemaillist'] = $invalidemaillist;
        $aData['mandatory'] = $mandatory;
        $aData['invalidattribute'] = $invalidattribute;
        $aData['mandatory'] = $mandatory;
        $aData['invalidparticipantid'] = $invalidparticipantid;
        $this->getController()->render('/admin/participants/uploadSummary_view', $aData);
    }

    function summaryview()
    {
        $this->_renderWrappedTemplate('participants', array('participantsPanel', 'uploadSummary'));
    }

    /*
     * Responsible for setting the session variables for attribute map page redirect
     */
    function setSession()
    {
        unset(Yii::app()->session['participantid']);
        Yii::app()->session['participantid'] = Yii::app()->request->getPost('participantid');
    }

    /*
     * Generation of unique id
     */
    function gen_uuid()
    {
        return sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff)
        );
    }

    /*
     * Stores the shared participant information in participant_shares
     */
    function shareParticipants()
    {
        $clang = $this->getController()->lang;
        $iParticipantId = Yii::app()->request->getPost('participantid');
        $iShareUserId = Yii::app()->request->getPost('shareuser');
        $bCanEdit = Yii::app()->request->getPost('can_edit');

        $i = 0;
        foreach ($iParticipantId as $iId)
        {
            $time = time();
            $aData = array('participant_id' => $iId,
                'share_uid' => $iShareUserId,
                'date_added' => date(DATE_W3C, $time),
                'can_edit' => $bCanEdit);
            ParticipantShares::storeParticipantShare($aData);
            $i++;
        }

        printf($clang->gT("%s participants have been shared"), $i);
    }

    /*
     * Responsible for copying the participant from tokens to the central Database
     */
    function addToCentral()
    {
        $newarr = Yii::app()->request->getPost('newarr');
        $mapped = Yii::app()->request->getPost('mapped');
        $response = Participants::copyToCentral(Yii::app()->request->getPost('surveyid'), $newarr, $mapped);
        $clang = $this->getController()->lang;

        printf($clang->gT("%s participants have been copied, %s participants have not been copied because they already exist"), $response['success'], $response['duplicate']);
    }

    /*
     * Responsible for adding the participant to the specified survey
     */
    function addToToken()
    {
        $response = Participants::copytoSurvey(Yii::app()->request->getPost('participantid'), Yii::app()->request->getPost('surveyid'), Yii::app()->request->getPost('attributeid'));
        $clang = $this->getController()->lang;

        printf($clang->gT("%s participants have been copied, %s participants have not been copied because they already exist"), $response['success'], $response['duplicate']);
    }

    /*
     * Responsible for adding the participant to the specified survey with attribute mapping
     */
    function addToTokenattmap()
    {
        $iParticipantId = Yii::app()->request->getPost('participant_id');
        $iSurveyId = Yii::app()->request->getPost('surveyid');
        $mapped = Yii::app()->request->getPost('mapped');
        $newcreate = Yii::app()->request->getPost('newarr');
        $clang = $this->getController()->lang;
        if (empty($newcreate[0]))
        {
            $newcreate = array();
        }
        $response = Participants::copytosurveyatt($iSurveyId, $mapped, $newcreate, $iParticipantId);

        printf($clang->gT("%s participants have been copied,%s participants have not been copied because they already exist"), $response['success'], $response['duplicate']);
    }

    /*
     * Responsible for attribute mapping while copying participants from cpdb to token's table
     */
    function attributeMap()
    {
        Yii::app()->loadHelper('common');
        $this->getController()->_js_admin_includes(Yii::app()->getConfig('adminscripts') . "attributeMap.js");
        $this->getController()->_css_admin_includes(Yii::app()->getConfig('adminstyleurl') ."attributeMap.css");

        $iSurveyId = Yii::app()->request->getPost('survey_id');
        $redirect = Yii::app()->request->getPost('redirect');
        $count = Yii::app()->request->getPost('count');
        $iParticipantId = Yii::app()->request->getPost('participant_id');
        $attributes = ParticipantAttributeNames::getAttributes();
        $arr = Tokens_dynamic::model($iSurveyId)->find();
        if (is_array($arr))
        {
            $tokenfieldnames = array_keys($arr);
            $tokenattributefieldnames = array_filter($tokenfieldnames, 'filterForAttributes');
        }
        else
        {
            $tokenattributefieldnames = array();
        }

        $selectedattribute = array();
        $selectedcentralattribute = array();
        $alreadymappedattid = array();
        $alreadymappedattname = array();
        $i = 0;
        $j = 0;

        foreach ($tokenattributefieldnames as $key => $value)
        {
            if (is_numeric($value[10]))
            {
                $selectedattribute[$i] = $value;
                $i++;
            }
            else
            {
                array_push($alreadymappedattid, substr($value, 15));
            }
        }
        foreach ($attributes as $row)
        {
            if (!in_array($row['attribute_id'], $alreadymappedattid))
            {
                $selectedcentralattribute[$row['attribute_id']] = $row['attribute_name'];
            }
            else
            {
                array_push($alreadymappedattname, $row['attribute_name']);
            }
        }

        $aData = array(
            'selectedcentralattribute' => $selectedcentralattribute,
            'selectedtokenattribute' => $selectedattribute,
            'alreadymappedattributename' => $alreadymappedattname,
            'survey_id' => $iSurveyId,
            'redirect' => $redirect,
            'participant_id' => $iParticipantId,
            'count' => $count
        );

        $this->_renderWrappedTemplate('participants', 'attributeMap', $aData);
    }

    /*
     * This function is responsible for attribute mapping while copying participants from cpdb to token's table
     */

    function attributeMapToken()
    {
        Yii::app()->loadHelper('common');

        $iSurveyId = Yii::app()->request->getQuery('sid');
        $attributes = ParticipantAttributeNames::getAttributes();
        $tokenattributefieldnames = getTokenFieldsAndNames($iSurveyId, TRUE);

        $selectedattribute = array();
        $selectedcentralattribute = array();
        $alreadymappedattid = array();
        $alreadymappedattdisplay = array();
        $i = 0;
        $j = 0;

        foreach ($tokenattributefieldnames as $key => $value)
        {
            if (is_numeric($key[10]))
            {
                $selectedattribute[$value] = $key;
            }
            else
            {
                array_push($alreadymappedattid, substr($key, 15));
                array_push($alreadymappedattdisplay, $key);
            }
        }
        foreach ($attributes as $row)
        {
            if (!in_array($row['attribute_id'], $alreadymappedattid))
            {
                $selectedcentralattribute[$row['attribute_id']] = $row['attribute_name'];
            }
        }

        $aData = array(
            'attribute' => $selectedcentralattribute,
            'tokenattribute' => $selectedattribute,
            'alreadymappedattributename' => $alreadymappedattdisplay
        );

        $this->_renderWrappedTemplate('participants', 'attributeMapToken', $aData);
    }

    function mapCSVcancelled()
    {
        unlink(Yii::app()->getConfig('tempdir') . '/' . basename(Yii::app()->request->getPost('fullfilepath')));
    }

    function blacklistParticipant()
    {
        $this->load->model('participants_model');
        $iParticipantId = $this->uri->segment(4);
        $iSurveyId = $this->uri->segment(5);
        $clang = $this->limesurvey_lang;
        if (!is_numeric($iSurveyId))
        {
            $blacklist = $this->uri->segment(5);
            if ($blacklist == 'Y' || $blacklist == 'N')
            {
                $aData = array('blacklisted' => $blacklist, 'participant_id' => $iParticipantId);
                $aData = $this->participants_model->blacklistparticipantglobal($aData);
                $aData['global'] = 1;
                $aData['clang'] = $clang;
                $aData['blacklist'] = $blacklist;
                $this->load->view('admin/participants/blacklist_view', $aData);
            }
            else
            {
                $aData['is_participant'] = 0;
                $aData['is_updated'] = 0;
                $aData['clang'] = $clang;
                $this->load->view('admin/participants/blacklist_view', $aData);
            }
        }
        else
        {
            $blacklist = $this->uri->segment(6);
            if ($blacklist == 'Y' || $blacklist == 'N')
            {
                $aData = array('blacklisted' => $blacklist);
                $aData = $this->participants_model->blacklistparticipantlocal($aData, $iSurveyId, $iParticipantId);
                $aData['global'] = 1;
                $aData['clang'] = $clang;
                $aData['local'] = 1;
                $aData['blacklist'] = $blacklist;
                $this->load->view('admin/participants/blacklist_view', $aData);
            }
            else
            {
                $aData['is_participant'] = 0;
                $aData['is_updated'] = 0;
                $aData['clang'] = $clang;
                $this->load->view('admin/participants/blacklist_view', $aData);
            }
        }
    }

}

?>
