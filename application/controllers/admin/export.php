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

/**
* Export Action
*
* This controller performs export actions
*
* @package		LimeSurvey
* @subpackage	Backend
*/
class export extends Survey_Common_Action {

    function __construct($controller, $id)
    {
        parent::__construct($controller, $id);

        Yii::app()->loadHelper('export');
    }

    public function survey()
    {
        $action = Yii::app()->request->getParam('action');
        $iSurveyID = sanitize_int(Yii::app()->request->getParam('surveyid'));

        if ( hasSurveyPermission($iSurveyID, 'surveycontent', 'export') )
        {
            $this->_surveyexport($action, $iSurveyID);
            return;
        }
    }

    /**
    * This function exports a ZIP archives of several ZIP archives - it is used in the listSurvey controller
    * The SIDs are read from session flashdata.
    *
    */
    public function surveyarchives()
    {
        if ( ! $this->session->userdata('USER_RIGHT_SUPERADMIN') )
        {
            die('Access denied.');
        }

        $aSurveyIDs = $this->session->flashdata('sids');
        $aExportedFiles = array();

        foreach ($aSurveyIDs as $iSurveyID)
        {
            $iSurveyID = (int)$iSurveyID;

            if ( $iSurveyID > 0 )
            {
                $aExportedFiles[$iSurveyID] = $this->_exportarchive($iSurveyID,FALSE);
            }
        }

        if ( count($aExportedFiles) > 0 )
        {
            $aZIPFileName=$this->config->item("tempdir") . DIRECTORY_SEPARATOR . randomChars(30);

            $this->load->library("admin/pclzip/pclzip", array('p_zipname' => $aZIPFileName));

            $zip = new PclZip($aZIPFileName);
            foreach ($aExportedFiles as $iSurveyID=>$sFileName)
            {
                $zip->add(
                array(
                array(
                PCLZIP_ATT_FILE_NAME => $sFileName,
                PCLZIP_ATT_FILE_NEW_FULL_NAME => 'survey_archive_' . $iSurveyID . '.zip')
                )
                );

                unlink($sFileName);
            }
        }

        if ( is_file($aZIPFileName) )
        {
            //Send the file for download!
            header("Pragma: public");
            header("Expires: 0");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

            header("Content-Type: application/force-download");
            header( "Content-Disposition: attachment; filename=survey_archives_pack.zip" );
            header( "Content-Description: File Transfer");
            @readfile($aZIPFileName);

            //Delete the temporary file
            unlink($aZIPFileName);
            return;
        }
    }

    public function group()
    {
        $gid = sanitize_int(Yii::app()->request->getParam('gid'));
        $iSurveyID = sanitize_int(Yii::app()->request->getParam('surveyid'));

        if ( Yii::app()->getConfig("export4lsrc") === TRUE && hasSurveyPermission($iSurveyID, 'survey', 'export') )
        {
            if ( ! empty($_POST['action']) )
            {
                group_export(Yii::app()->request->getPost('action'), $iSurveyID, $gid);
                return;
            }

            $data = array("surveyid" => $iSurveyID, "gid" => $gid);

            $this->_renderWrappedTemplate('export', "group_view", $data);
        }
        else
        {
            group_export("exportstructurecsvGroup", $iSurveyID, $gid);

            return;
        }
    }

    public function question()
    {
        $gid = sanitize_int(Yii::app()->request->getParam('gid'));
        $qid = sanitize_int(Yii::app()->request->getParam('qid'));
        $iSurveyID = sanitize_int(Yii::app()->request->getParam('surveyid'));

        if( Yii::app()->getConfig('export4lsrc') === TRUE && hasSurveyPermission($iSurveyID, 'survey', 'export') )
        {
            if( ! empty($_POST['action']) )
            {
                questionExport(Yii::app()->request->getPost('action'), $iSurveyID, $gid, $qid);
                return;
            }

            $data = array("surveyid" => $iSurveyID, "gid" => $gid, "qid" =>$qid);

            $this->_renderWrappedTemplate('export', "question_view", $data);
        }
        else
        {
            questionExport("exportstructurecsvQuestion", $iSurveyID, $gid, $qid);

            return;
        }
    }

    public function exportresults()
    {
        $iSurveyID = sanitize_int(Yii::app()->request->getParam('surveyid'));

        if ( ! isset($imageurl) ) { $imageurl = "./images"; }
        if ( ! isset($iSurveyID) ) { $iSurveyID = returnGlobal('sid'); }
        if ( ! isset($exportstyle) ) { $exportstyle = returnGlobal('exportstyle'); }
        if ( ! isset($answers) ) { $answers = returnGlobal('answers'); }
        if ( ! isset($type) ) { $type = returnGlobal('type'); }
        if ( ! isset($convertyto1) ) { $convertyto1 = returnGlobal('convertyto1'); }
        if ( ! isset($convertnto2) ) { $convertnto2 = returnGlobal('convertnto2'); }
        if ( ! isset($convertspacetous) ) { $convertspacetous = returnGlobal('convertspacetous'); }

        $clang = Yii::app()->lang;

        if ( ! hasSurveyPermission($iSurveyID, 'responses', 'export') )
        {
            exit;
        }

        Yii::app()->loadHelper("admin/exportresults");

        $surveybaselang = Survey::model()->findByPk($iSurveyID)->language;
        $exportoutput = "";

        // Get info about the survey
        $thissurvey = getSurveyInfo($iSurveyID);

        if ( ! $exportstyle )
        {
            //FIND OUT HOW MANY FIELDS WILL BE NEEDED - FOR 255 COLUMN LIMIT
            $excesscols = createFieldMap($iSurveyID,'full',false,false,getBaseLanguageFromSurveyID($iSurveyID));
            $excesscols = array_keys($excesscols);

            $afieldcount = count($excesscols);

            $selecthide = "'";
            $selectshow = "";
            $selectinc = "";
            if ( incompleteAnsFilterState() == "filter" )
            {
                $selecthide = "selected='selected'";
            }
            elseif ( incompleteAnsFilterState() == "inc" )
            {
                $selectinc = "selected='selected'";
            }
            else
            {
                $selectshow = "selected='selected'";
            }

            $data['selecthide'] = $selecthide;
            $data['selectshow'] = $selectshow;
            $data['selectinc'] = $selectinc;
            $data['afieldcount'] = $afieldcount;
            $data['excesscols'] = $excesscols;

            //get max number of datasets
            $max_datasets_query = Yii::app()->db->createCommand("SELECT COUNT(id) AS count FROM {{survey_".intval($iSurveyID)."}}")->query()->read();
            $max_datasets = $max_datasets_query['count'];

            $data['max_datasets'] = $max_datasets;
            $data['surveyid'] = $iSurveyID;
            $data['imageurl'] = Yii::app()->getConfig('imageurl');
            $data['thissurvey'] = $thissurvey;
            $data['display']['menu_bars']['browse'] = $clang->gT("Export results");

            $this->_renderWrappedTemplate('export', 'exportresults_view', $data);

            return;
        }

        // Export Language is set by default to surveybaselang
        // * the explang language code is used in SQL queries
        // * the alang object is used to translate headers and hardcoded answers
        // In the future it might be possible to 'post' the 'export language' from
        // the exportresults form
        $explang = $surveybaselang;
        $elang = new limesurvey_lang($explang);

        //Get together our FormattingOptions and then call into the exportSurvey
        //function.
        $options = new FormattingOptions();
        $options->selectedColumns = Yii::app()->request->getPost('colselect');
        $options->responseMinRecord = sanitize_int(Yii::app()->request->getPost('export_from')) - 1;
        $options->responseMaxRecord = sanitize_int(Yii::app()->request->getPost('export_to')) - 1;
        $options->answerFormat = $answers;
        $options->convertN = $convertnto2;

        if ( $options->convertN )
        {
            $options->nValue = $convertnto2;
        }

        $options->convertY = $convertyto1;

        if ( $options->convertY )
        {
            $options->yValue = $convertyto1;
        }

        $options->format = $type;
        $options->headerSpacesToUnderscores = $convertspacetous;
        $options->headingFormat = $exportstyle;
        $options->responseCompletionState = incompleteAnsFilterState();

        //If we have no data for the filter state then default to show all.
        if ( empty($options->responseCompletionState) )
        {
            if ( ! isset($_POST['attribute_select']) )
            {
                $_POST['attribute_select'] = array();
            }

            $options->responseCompletionState = 'show';

            $dquery = '';
            if ( in_array('first_name', Yii::app()->request->getPost('attribute_select')) )
            {
                $dquery .= ", {{tokens_$iSurveyID}}.firstname";
            }

            if ( in_array('last_name', Yii::app()->request->getPost('attribute_select')) )
            {
                $dquery .= ", {{tokens_$iSurveyID}}.lastname";
            }

            if ( in_array('email_address', Yii::app()->request->getPost('attribute_select')) )
            {
                $dquery .= ", {{tokens_$iSurveyID}}.email";
            }

            if ( in_array('token', Yii::app()->request->getPost('attribute_select')) )
            {
                $dquery .= ", {{tokens_$iSurveyID}}.token";
            }

            $attributeFields = getTokenFieldsAndNames($iSurveyID, TRUE);

            foreach ($attributeFields as $attr_name => $attr_desc)
            {
                if ( in_array($attr_name, Yii::app()->request->getPost('attribute_select')) )
                {
                    $dquery .= ", {{tokens_$iSurveyID}}.$attr_name";
                }
            }
        }

        if ( $options->responseCompletionState == 'inc' )
        {
            $options->responseCompletionState = 'incomplete';
        }

        $resultsService = new ExportSurveyResultsService();
        $resultsService->exportSurvey($iSurveyID, $explang, $options);

        exit;
    }

    /*
    * The SPSS DATA LIST / BEGIN DATA parser is rather simple minded, the number after the type
    * specifier identifies the field width (maximum number of characters to scan)
    * It will stop short of that number of characters, honouring quote delimited
    * space separated strings, however if the width is too small the remaining data in the current
    * line becomes part of the next column.  Since we want to restrict this script to ONE scan of
    * the data (scan & output at same time), the information needed to construct the
    * DATA LIST is held in the $fields array, while the actual data is written to a
    * to a temporary location, updating length (size) values in the $fields array as
    * the tmp file is generated (uses @fwrite's return value rather than strlen).
    * Final output renders $fields to a DATA LIST, and then stitches in the tmp file data.
    *
    * Optimization opportunities remain in the VALUE LABELS section, which runs a query / column
    */
    public function exportspss()
    {
        $iSurveyID = sanitize_int(Yii::app()->request->getParam('sid'));
        $subaction = Yii::app()->request->getParam('subaction');

        $clang = $this->getController()->lang;
        //for scale 1=nominal, 2=ordinal, 3=scale

        //		$typeMap = $this->_getTypeMap();

        $filterstate = incompleteAnsFilterState();
        $spssver = returnGlobal('spssver');

        if ( is_null($spssver) )
        {
            if ( ! Yii::app()->session['spssversion'] )
            {
                Yii::app()->session['spssversion'] = 2;	//Set default to 2, version 16 or up
            }

            $spssver = Yii::app()->session['spssversion'];
        }
        else
        {
            Yii::app()->session['spssversion'] = $spssver;
        }

        $length_varlabel = '255'; // Set the max text length of Variable Labels
        $length_vallabel = '120'; // Set the max text length of Value Labels

        switch ( $spssver )
        {
            case 1:	//<16
                $iLength	 = '255'; // Set the max text length of the Value
                break;
            case 2:	//>=16
                $iLength	 = '16384'; // Set the max text length of the Value
                break;
            default:
                $iLength	 = '16384'; // Set the max text length of the Value
        }

        $headerComment = '*$Rev: 10193 $' . " $filterstate $spssver.\n";

        if ( isset($_POST['dldata']) ) $subaction = "dldata";
        if ( isset($_POST['dlstructure']) ) $subaction = "dlstructure";

        if  ( ! isset($subaction) )
        {
            $selecthide = "";
            $selectshow = "";
            $selectinc = "";

            switch ($filterstate)
            {
                case "inc":
                    $selectinc="selected='selected'";
                    break;
                case "filter":
                    $selecthide="selected='selected'";
                    break;
                default:
                    $selectshow="selected='selected'";
            }

            $data['selectinc'] = $selectinc;
            $data['selecthide'] = $selecthide;
            $data['selectshow'] = $selectshow;
            $data['spssver'] = $spssver;
            $data['surveyid'] = $iSurveyID;
            $data['display']['menu_bars']['browse'] = $clang->gT('Export results');

            $this->_renderWrappedTemplate('export', 'spss_view', $data);
        }
        else
        {
            // Get Base language:

            $language = Survey::model()->findByPk($iSurveyID)->language;
            $clang = new limesurvey_lang($language);
            Yii::app()->loadHelper("admin/exportresults");
        }

        if ( $subaction == 'dldata' )
        {
            header("Content-Disposition: attachment; filename=survey_" . $iSurveyID . "_SPSS_data_file.dat");
            header("Content-type: text/comma-separated-values; charset=UTF-8");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Pragma: public");

            if ( $spssver == 2 )
            {
                echo "\xEF\xBB\xBF";
            }

            SPSSExportData($iSurveyID, $iLength);

            exit;
        }

        if ( $subaction == 'dlstructure' )
        {
            header("Content-Disposition: attachment; filename=survey_" . $iSurveyID . "_SPSS_syntax_file.sps");
            header("Content-type: application/download; charset=UTF-8");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Pragma: public");

            // Build array that has to be returned
            $fields = SPSSFieldMap($iSurveyID);

            //Now get the query string with all fields to export
            $query = SPSSGetQuery($iSurveyID);
            $result = Yii::app()->db->createCommand($query)->query()->readAll(); //Checked

            $num_fields = isset( $result[0] ) ? count($result[0]) : 0;

            //Now we check if we need to adjust the size of the field or the type of the field
            foreach ( $result as $row )
            {
                $row = array_values($row);
                $fieldno = 0;

                while ( $fieldno < $num_fields )
                {
                    //Performance improvement, don't recheck fields that have valuelabels
                    if ( ! isset($fields[$fieldno]['answers']) )
                    {
                        $strTmp = mb_substr(stripTagsFull($row[$fieldno]), 0, $iLength);
                        $len = mb_strlen($strTmp);

                        if ( $len > $fields[$fieldno]['size'] ) $fields[$fieldno]['size'] = $len;

                        if ( trim($strTmp) != '' )
                        {
                            if ( $fields[$fieldno]['SPSStype'] == 'F' && (isNumericExtended($strTmp) === FALSE || $fields[$fieldno]['size'] > 16) )
                            {
                                $fields[$fieldno]['SPSStype'] = 'A';
                            }
                        }
                    }
                    $fieldno++;
                }
            }

            /**
            * End of DATA print out
            *
            * Now $fields contains accurate length data, and the DATA LIST can be rendered -- then the contents of the temp file can
            * be sent to the client.
            */
            if ( $spssver == 2 )
            {
                echo "\xEF\xBB\xBF";
            }

            echo $headerComment;

            if  ($spssver == 2 )
            {
                echo "SET UNICODE=ON.\n";
            }

            echo "GET DATA\n"
            ." /TYPE=TXT\n"
            ." /FILE='survey_" . $iSurveyID . "_SPSS_data_file.dat'\n"
            ." /DELCASE=LINE\n"
            ." /DELIMITERS=\",\"\n"
            ." /QUALIFIER=\"'\"\n"
            ." /ARRANGEMENT=DELIMITED\n"
            ." /FIRSTCASE=1\n"
            ." /IMPORTCASE=ALL\n"
            ." /VARIABLES=";

            foreach ( $fields as $field )
            {
                if( $field['SPSStype'] == 'DATETIME23.2' ) $field['size'] = '';

                if($field['SPSStype'] == 'F' && ($field['LStype'] == 'N' || $field['LStype'] == 'K'))
                {
                    $field['size'] .= '.' . ($field['size']-1);
                }

                if ( !$field['hide'] ) echo "\n {$field['id']} {$field['SPSStype']}{$field['size']}";
            }

            echo ".\nCACHE.\n"
            ."EXECUTE.\n";

            //Create the variable labels:
            echo "*Define Variable Properties.\n";
            foreach ( $fields as $field )
            {
                if ( ! $field['hide'] )
                {
                    echo "VARIABLE LABELS " . $field['id'] . " \"" . str_replace('"','""',mb_substr(stripTagsFull($field['VariableLabel']), 0, $length_varlabel)) . "\".\n";
                }
            }

            // Create our Value Labels!
            echo "*Define Value labels.\n";
            foreach ( $fields as $field )
            {
                if ( isset($field['answers']) )
                {
                    $answers = $field['answers'];

                    //print out the value labels!
                    echo "VALUE LABELS  {$field['id']}\n";

                    $i=0;
                    foreach ( $answers as $answer )
                    {
                        $i++;

                        if ( $field['SPSStype'] == "F" && isNumericExtended($answer['code']) )
                        {
                            $str = "{$answer['code']}";
                        }
                        else
                        {
                            $str = "\"{$answer['code']}\"";
                        }

                        if ( $i < count($answers) )
                        {
                            echo " $str \"{$answer['value']}\"\n";
                        }
                        else
                        {
                            echo " $str \"{$answer['value']}\".\n";
                        }
                    }
                }
            }

            foreach ( $fields as $field )
            {
                if( $field['scale'] !== '' )
                {
                    switch ( $field['scale'] )
                    {
                        case 2:
                            echo "VARIABLE LEVEL {$field['id']}(ORDINAL).\n";
                            break;
                        case 3:
                            echo "VARIABLE LEVEL {$field['id']}(SCALE).\n";
                    }
                }
            }

            //Rename the Variables (in case somethings goes wrong, we still have the OLD values
            foreach ( $fields as $field )
            {
                if ( isset($field['sql_name']) && $field['hide'] === 0 )
                {
                    $ftitle = $field['title'];

                    if ( ! preg_match ("/^([a-z]|[A-Z])+.*$/", $ftitle) )
                    {
                        $ftitle = "q_" . $ftitle;
                    }

                    $ftitle = str_replace(array(" ","-",":",";","!","/","\\","'"), array("_","_hyph_","_dd_","_dc_","_excl_","_fs_","_bs_",'_qu_'), $ftitle);

                    if ( $ftitle != $field['title'] )
                    {
                        echo "* Variable name was incorrect and was changed from {$field['title']} to $ftitle .\n";
                    }

                    echo "RENAME VARIABLE ( " . $field['id'] . ' = ' . $ftitle . " ).\n";
                }
            }
            exit;
        }
    }

    /*
    * The SPSS DATA LIST / BEGIN DATA parser is rather simple minded, the number after the type
    * specifier identifies the field width (maximum number of characters to scan)
    * It will stop short of that number of characters, honouring quote delimited
    * space separated strings, however if the width is too small the remaining data in the current
    * line becomes part of the next column.  Since we want to restrict this script to ONE scan of
    * the data (scan & output at same time), the information needed to construct the
    * DATA LIST is held in the $fields array, while the actual data is written to a
    * to a temporary location, updating length (size) values in the $fields array as
    * the tmp file is generated (uses @fwrite's return value rather than strlen).
    * Final output renders $fields to a DATA LIST, and then stitches in the tmp file data.
    *
    * Optimization opportunities remain in the VALUE LABELS section, which runs a query / column
    */
    public function exportr()
    {
        $iSurveyID = sanitize_int(Yii::app()->request->getParam('sid'));
        $subaction = Yii::app()->request->getParam('subaction');

        $clang = $this->getController()->lang;
        //for scale 1=nominal, 2=ordinal, 3=scale

        //$typeMap = $this->_getTypeMap();

        $length_vallabel = '120'; // Set the max text length of Value Labels
        $iLength = '25500'; // Set the max text length of Text Data
        $length_varlabel = '25500'; // Set the max text length of Variable Labels
        $headerComment = '';
        $tempFile = '';

        if ( ! isset($iSurveyID) ) { $iSurveyID = returnGlobal('sid'); }
        $filterstate = incompleteAnsFilterState();

        $headerComment = '#$Rev: 10193 $' . " $filterstate.\n";

        if ( isset($_POST['dldata']) ) $subaction = "dldata";
        if ( isset($_POST['dlstructure']) ) $subaction = "dlstructure";

        if  ( ! isset($subaction) )
        {
            $selecthide = "";
            $selectshow = "";
            $selectinc = "";

            switch ( $filterstate )
            {
                case "inc":
                    $selectinc = "selected='selected'";
                    break;
                case "filter":
                    $selecthide = "selected='selected'";
                    break;
                default:
                    $selectshow = "selected='selected'";
            }

            $data['selectinc'] = $selectinc;
            $data['selecthide'] = $selecthide;
            $data['selectshow'] = $selectshow;
            $data['filename'] = "survey_" . $iSurveyID . "_R_syntax_file.R";
            $data['surveyid'] = $iSurveyID;
            $data['display']['menu_bars']['browse'] = $clang->gT("Export results");

            $this->_renderWrappedTemplate('export', 'r_view', $data);
        }
        else
        {
            Yii::app()->loadHelper("admin/exportresults");
        }


        if ( $subaction == 'dldata' )
        {
            header("Content-Disposition: attachment; filename=survey_" . $iSurveyID . "_R_data_file.csv");
            header("Content-type: text/comma-separated-values; charset=UTF-8");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Pragma: public");

            $na = "";	//change to empty string instead of two double quotes to fix warnings on NA
            SPSSExportData($iSurveyID, $iLength);

            exit;
        }

        if  ( $subaction == 'dlstructure' )
        {
            header("Content-Disposition: attachment; filename=survey_" . $iSurveyID . "_R_syntax_file.R");
            header("Content-type: application/download; charset=UTF-8");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Pragma: public");

            echo $headerComment;
            echo "data <- read.table(\"survey_" . $iSurveyID
            ."_R_data_file.csv\", sep=\",\", quote = \"'\", "
            ."na.strings=c(\"\",\"\\\"\\\"\"), "
            ."stringsAsFactors=FALSE)\n\n";


            // Build array that has to be returned
            $fields = SPSSFieldMap($iSurveyID,"V");

            //Now get the query string with all fields to export
            $query = SPSSGetQuery($iSurveyID);

            $result = Yii::app()->db->createCommand($query)->query(); //Checked
            $result = $result->readAll();
            $num_fields = isset( $result[0] ) ? count($result[0]) : array();

            //Now we check if we need to adjust the size of the field or the type of the field
            foreach ( $result as $row )
            {
                $row = array_values($row);
                $fieldno = 0;

                while ( $fieldno < $num_fields )
                {
                    //Performance improvement, don't recheck fields that have valuelabels
                    if ( ! isset($fields[$fieldno]['answers']) )
                    {
                        $strTmp = mb_substr(stripTagsFull($row[$fieldno]), 0, $iLength);
                        $len = mb_strlen($strTmp);

                        if ( $len > $fields[$fieldno]['size'] ) $fields[$fieldno]['size'] = $len;

                        if ( trim($strTmp) != '' )
                        {
                            if ( $fields[$fieldno]['SPSStype'] == 'F' && (isNumericExtended($strTmp) === FALSE || $fields[$fieldno]['size'] > 16) )
                            {
                                $fields[$fieldno]['SPSStype'] = 'A';
                            }
                        }
                    }

                    $fieldno++;
                }
            }

            $errors = "";
            $i = 1;
            foreach ( $fields as $field )
            {
                if ( $field['SPSStype'] == 'DATETIME23.2' ) $field['size']='';

                if ( $field['LStype'] == 'N' || $field['LStype'] == 'K' )
                {
                    $field['size'] .= '.' . ($field['size'] - 1);
                }

                switch ( $field['SPSStype'] )
                {
                    case 'F':
                        $type = "numeric";
                        break;
                    case 'A':
                        $type = "character";
                        break;
                    case 'DATETIME23.2':
                    case 'SDATE':
                        $type = "character";
                        //@TODO set $type to format for date
                        break;
                }

                if ( ! $field['hide'] )
                {
                    echo "data[, " . $i . "] <- "
                    . "as.$type(data[, " . $i . "])\n";

                    echo 'attributes(data)$variable.labels[' . $i . '] <- "'
                    . addslashes(
                    htmlspecialchars_decode(
                    mb_substr(
                    stripTagsFull(
                    $field['VariableLabel']
                    ), 0, $length_varlabel
                    )
                    )
                    )
                    . '"' . "\n";

                    // Create the value Labels!
                    if ( isset($field['answers']) )
                    {
                        $answers = $field['answers'];

                        //print out the value labels!
                        echo 'data[, ' . $i .'] <- factor(data[, ' . $i . '], levels=c(';

                        $str = "";
                        foreach ( $answers as $answer )
                        {
                            if ( $field['SPSStype'] == "F" && isNumericExtended($answer['code']) )
                            {
                                $str .= ",{$answer['code']}";
                            }
                            else
                            {
                                $str .= ",\"{$answer['code']}\"";
                            }
                        }

                        $str = mb_substr($str, 1);
                        echo $str . '),labels=c(';
                        $str = "";

                        foreach ( $answers as $answer )
                        {
                            $str .= ",\"{$answer['value']}\"";
                        }

                        $str = mb_substr($str, 1);

                        if ( $field['scale'] !== '' && $field['scale'] == 2 )
                        {
                            $scale = ",ordered=TRUE";
                        }
                        else
                        {
                            $scale = "";
                        }

                        echo "$str)$scale)\n";
                    }

                    //Rename the Variables (in case somethings goes wrong, we still have the OLD values
                    if ( isset($field['sql_name']) )
                    {
                        $ftitle = $field['title'];
                        if (!preg_match ("/^([a-z]|[A-Z])+.*$/", $ftitle))
                        {
                            $ftitle = "q_" . $ftitle;
                        }

                        $ftitle = str_replace(array("-",":",";","!"), array("_hyph_","_dd_","_dc_","_excl_"), $ftitle);

                        if ( ! $field['hide'] )
                        {
                            if ( $ftitle != $field['title'] )
                            {
                                $errors .= "# Variable name was incorrect and was changed from {$field['title']} to $ftitle .\n";
                            }

                            echo "names(data)[" . $i . "] <- "
                            . "\"". $ftitle . "\"\n";  // <AdV> added \n
                        }

                        $i++;
                    }
                    else
                    {
                        echo "#sql_name not set\n";
                    }
                }
                else
                {
                    echo "#Field hidden\n";
                }

                echo "\n";

            }  // end foreach
            echo $errors;
            exit;
        }


    }

    public function vvexport()
    {
        $iSurveyID = sanitize_int(Yii::app()->request->getParam('surveyid'));
        $subaction = Yii::app()->request->getParam('subaction');

        //Exports all responses to a survey in special "Verified Voting" format.
        $clang = $this->getController()->lang;

        if ( ! hasSurveyPermission($iSurveyID, 'responses','export') )
        {
            return;
        }

        if ( $subaction != "export" )
        {
            $selecthide = "";
            $selectshow = "";
            $selectinc = "";
            if( incompleteAnsFilterState() == "inc" )
            {
                $selectinc = "selected='selected'";
            }
            elseif ( incompleteAnsFilterState() == "filter" )
            {
                $selecthide = "selected='selected'";
            }
            else
            {
                $selectshow = "selected='selected'";
            }

            $data['selectinc'] = $selectinc;
            $data['selecthide'] = $selecthide;
            $data['selectshow'] = $selectshow;
            $data['surveyid'] = $iSurveyID;
            $data['display']['menu_bars']['browse'] = $clang->gT("Export VV file");

            $this->_renderWrappedTemplate('export', 'vv_view', $data);
        }
        elseif ( isset($iSurveyID) && $iSurveyID )
        {
            //Export is happening
            $extension = sanitize_paranoid_string(returnGlobal('extension'));

            $fn = "vvexport_$iSurveyID." . $extension;
            $this->_addHeaders($fn, "text/comma-separated-values", 0, "cache");

            $s="\t";

            $fieldmap = createFieldMap($iSurveyID,'full',false,false,getBaseLanguageFromSurveyID($iSurveyID));
            $surveytable = "{{survey_$iSurveyID}}";

            Survey::model()->findByPk($iSurveyID)->language;

            $fieldnames = Yii::app()->db->schema->getTable($surveytable)->getColumnNames();

            //Create the human friendly first line
            $firstline = "";
            $secondline = "";
            foreach ( $fieldnames as $field )
            {
                $fielddata=arraySearchByKey($field, $fieldmap, "fieldname", 1);

                if ( count($fielddata) < 1 )
                {
                    $firstline .= $field;
                }
                else
                {
                    $firstline.=preg_replace('/\s+/', ' ', strip_tags($fielddata['question']));
                }
                $firstline .= $s;
                $secondline .= $field.$s;
            }

            $vvoutput = $firstline . "\n";
            $vvoutput .= $secondline . "\n";
            $query = "SELECT * FROM ".Yii::app()->db->quoteTableName($surveytable);

            if (incompleteAnsFilterState() == "inc")
            {
                $query .= " WHERE submitdate IS NULL ";
            }
            elseif (incompleteAnsFilterState() == "filter")
            {
                $query .= " WHERE submitdate >= '01/01/1980' ";
            }
            $result = Yii::app()->db->createCommand($query)->query();

            foreach ( $result->readAll() as $row )
            {
                foreach ( $fieldnames as $field )
                {
                    if ( is_null($row[$field]) )
                    {
                        $value = '{question_not_shown}';
                    }
                    else
                    {
                        $value = trim($row[$field]);
                        // sunscreen for the value. necessary for the beach.
                        // careful about the order of these arrays:
                        // lbrace has to be substituted *first*
                        $value = str_replace(
                        array(
                        "{",
                        "\n",
                        "\r",
                        "\t"),
                        array("{lbrace}",
                        "{newline}",
                        "{cr}",
                        "{tab}"
                        ),
                        $value
                        );
                    }

                    // one last tweak: excel likes to quote values when it
                    // exports as tab-delimited (esp if value contains a comma,
                    // oddly enough).  So we're going to encode a leading quote,
                    // if it occurs, so that we can tell the difference between
                    // strings that "really are" quoted, and those that excel quotes
                    // for us.
                    $value = preg_replace('/^"/','{quote}',$value);
                    // yay!  that nasty soab won't hurt us now!
                    if( $field == "submitdate" && !$value ) { $value = "NULL"; }

                    $sun[]=$value;
                }

                $beach = implode($s, $sun);
                $vvoutput .= $beach;

                unset($sun);
                $vvoutput .= "\n";
            }

            echo $vvoutput;
            exit;
        }
    }

    /**
    * quexml survey export
    */
    public function showquexmlsurvey()
    {
        $iSurveyID = sanitize_int(Yii::app()->request->getParam('surveyid'));
        $lang = ( isset($_GET['lang']) ) ? Yii::app()->request->getParam('lang') : NULL;
        $tempdir = Yii::app()->getConfig("tempdir");

        // Set the language of the survey, either from GET parameter of session var
        if ( $lang != NULL )
        {
            $lang = preg_replace("/[^a-zA-Z0-9-]/", "", $lang);
            if ( $lang ) $surveyprintlang = $lang;
        }
        else
        {
            $surveyprintlang=Survey::model()->findByPk($iSurveyID)->language;
        }

        // Setting the selected language for printout
        $clang = new limesurvey_lang($surveyprintlang);

        Yii::import("application.libraries.admin.quexmlpdf", TRUE);
        $quexmlpdf = new quexmlpdf($this->getController());

        set_time_limit(120);

        $noheader = TRUE;

        $quexml = quexml_export($iSurveyID, $surveyprintlang);

        $quexmlpdf->create($quexmlpdf->createqueXML($quexml));

        //NEED TO GET QID from $quexmlpdf
        $qid = intval($quexmlpdf->getQuestionnaireId());

        $zipdir= $this->_tempdir($tempdir);

        $f1 = "$zipdir/quexf_banding_{$qid}_{$surveyprintlang}.xml";
        $f2 = "$zipdir/quexmlpdf_{$qid}_{$surveyprintlang}.pdf";
        $f3 = "$zipdir/quexml_{$qid}_{$surveyprintlang}.xml";
        $f4 = "$zipdir/readme.txt";

        file_put_contents($f1, $quexmlpdf->getLayout());
        file_put_contents($f2, $quexmlpdf->Output("quexml_$qid.pdf", 'S'));
        file_put_contents($f3, $quexml);
        file_put_contents($f4, $clang->gT('This archive contains a PDF file of the survey, the queXML file of the survey and a queXF banding XML file which can be used with queXF: http://quexf.sourceforge.net/ for processing scanned surveys.'));

        Yii::import('application.libraries.admin.Phpzip', TRUE);
        $z = new Phpzip;
        $zipfile="$tempdir/quexmlpdf_{$qid}_{$surveyprintlang}.zip";
        $z->Zip($zipdir, $zipfile);

        unlink($f1);
        unlink($f2);
        unlink($f3);
        unlink($f4);
        rmdir($zipdir);

        $fn = "quexmlpdf_{$qid}_{$surveyprintlang}.zip";
        $this->_addHeaders($fn, "application/zip", 0);
        header('Content-Transfer-Encoding: binary');

        // load the file to send:
        readfile($zipfile);
        unlink($zipfile);
    }

    public function resources()
    {
        switch ( Yii::app()->request->getParam('export') )
        {
            case 'survey' :
                $iSurveyID = sanitize_int(CHttpRequest::getParam('surveyid'));
                $resourcesdir = 'surveys/' . $iSurveyID;
                $zipfilename = "resources-survey-$iSurveyID.zip";
                break;
            case 'label' :
                $lid = sanitize_int(CHttpRequest::getParam('lid'));
                $resourcesdir = 'labels/' . $lid;
                $zipfilename = "resources-labelset-$lid.zip";
                break;
        }

        if (!empty($zipfilename) && !empty($resourcesdir))
        {
            $resourcesdir = Yii::app()->getConfig('uploaddir') . "/{$resourcesdir}/";
            $tmpdir = Yii::app()->getConfig('tempdir') . '/';
            $zipfilepath = $tmpdir . $zipfilename;
            Yii::app()->loadLibrary('admin.pclzip.pclzip');
            $zip = new PclZip($zipfilepath);
            $zipdirs = array();
            foreach (array('files', 'flash', 'images') as $zipdir)
            {
                if (is_dir($resourcesdir . $zipdir))
                    $zipdirs[] = $resourcesdir . $zipdir . '/';
            }
            if ($zip->create($zipdirs, PCLZIP_OPT_REMOVE_PATH, $resourcesdir) === 0)
            {
                die("Error : ".$zip->errorInfo(true));
            }
            elseif (file_exists($zipfilepath))
            {
                $this->_addHeaders($zipfilename, 'application/force-download', 0);
                readfile($zipfilepath);
                unlink($zipfilepath);
                exit;
            }
        }
    }

    public function dumplabel()
    {
        $lid = sanitize_int(Yii::app()->request->getParam('lid'));
        // DUMP THE RELATED DATA FOR A SINGLE QUESTION INTO A SQL FILE FOR IMPORTING LATER ON OR
        // ON ANOTHER SURVEY SETUP DUMP ALL DATA WITH RELATED QID FROM THE FOLLOWING TABLES
        // 1. questions
        // 2. answers

        $lids=returnGlobal('lids');

        if ( ! $lid && ! $lids )
        {
            die('No LID has been provided. Cannot dump label set.');
        }

        if ( $lid )
        {
            $lids = array($lid);
        }

        $lids = array_map('sanitize_int', $lids);

        $fn = "limesurvey_labelset_" . implode('_', $lids) . ".lsl";
        $xml = getXMLWriter();

        $this->_addHeaders($fn, "text/html/force-download", "Mon, 26 Jul 1997 05:00:00 GMT", "cache");

        $xml->openURI('php://output');

        $xml->setIndent(TRUE);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('document');
        $xml->writeElement('LimeSurveyDocType', 'Label set');
        $xml->writeElement('DBVersion', getGlobalSetting("DBVersion"));

        // Label sets table
        $lsquery = "SELECT * FROM {{labelsets}} WHERE lid=" . implode(' or lid=', $lids);
        buildXMLFromQuery($xml, $lsquery, 'labelsets');

        // Labels
        $lquery = "SELECT lid, code, title, sortorder, language, assessment_value FROM {{labels}} WHERE lid=" . implode(' or lid=', $lids);
        buildXMLFromQuery($xml, $lquery, 'labels');
        $xml->endElement(); // close columns
        $xml->endDocument();
        exit;
    }

    /**
    * Exports a archive (ZIP) of the current survey (structure, responses, timings, tokens)
    *
    * @param integer $iSurveyID  The ID of the survey to export
    * @param boolean $bSendToBrowser If TRUE (default) then the ZIP file is sent to the browser
    * @return string Full path of the ZIP filename if $bSendToBrowser is set to TRUE, otherwise no return value
    */
    private function _exportarchive($iSurveyID, $bSendToBrowser=TRUE)
    {
        $aSurveyInfo = getSurveyInfo($iSurveyID);

        $sTempDir = Yii::app()->getConfig("tempdir");

        $aZIPFileName = $sTempDir . DIRECTORY_SEPARATOR . randomChars(30);
        $sLSSFileName = $sTempDir . DIRECTORY_SEPARATOR . randomChars(30);
        $sLSRFileName = $sTempDir . DIRECTORY_SEPARATOR . randomChars(30);
        $sLSTFileName = $sTempDir . DIRECTORY_SEPARATOR . randomChars(30);
        $sLSIFileName = $sTempDir . DIRECTORY_SEPARATOR . randomChars(30);

        Yii::import('application.libraries.admin.pclzip.pclzip', TRUE);
        $zip = new PclZip($aZIPFileName);

        file_put_contents($sLSSFileName, surveyGetXMLData($iSurveyID));

        $this->_addToZip($zip, $sLSSFileName, 'survey_' . $iSurveyID . '.lss');

        unlink($sLSSFileName);

        if ( $aSurveyInfo['active'] == 'Y' )
        {
            getXMLDataSingleTable($iSurveyID, 'survey_' . $iSurveyID, 'Responses', 'responses', $sLSRFileName, FALSE);

            $this->_addToZip($zip, $sLSRFileName, 'survey_' . $iSurveyID . '_responses.lsr');

            unlink($sLSRFileName);
        }

        if ( Yii::app()->db->schema->getTable('{{tokens_' . $iSurveyID . '}}') )
        {
            getXMLDataSingleTable($iSurveyID, 'tokens_' . $iSurveyID, 'Tokens', 'tokens', $sLSTFileName);

            $this->_addToZip($zip, $sLSTFileName, 'survey_' . $iSurveyID . '_tokens.lst');

            unlink($sLSTFileName);
        }

        if ( Yii::app()->db->schema->getTable('{{survey_' . $iSurveyID . '_timings}}') )
        {
            getXMLDataSingleTable($iSurveyID, 'survey_' . $iSurveyID . '_timings', 'Timings', 'timings', $sLSIFileName);

            $this->_addToZip($zip, $sLSIFileName, 'survey_' . $iSurveyID . '_timings.lsi');

            unlink($sLSIFileName);
        }

        if ( is_file($aZIPFileName) )
        {
            if ( $bSendToBrowser )
            {
                $fn = "survey_archive_{$iSurveyID}.zip";

                //Send the file for download!
                $this->_addHeaders($fn, "application/force-download", 0);

                @readfile($aZIPFileName);

                //Delete the temporary file
                unlink($aZIPFileName);

                return;
            }
            else
            {
                return($aZIPFileName);
            }
        }
    }

    private function _addToZip($zip, $name, $full_name)
    {
        $zip->add(
        array(
        array(
        PCLZIP_ATT_FILE_NAME => $name,
        PCLZIP_ATT_FILE_NEW_FULL_NAME => $full_name
        )
        )
        );
    }

    private function _surveyexport($action, $iSurveyID)
    {
        if ( $action == "exportstructurexml" )
        {
            $fn = "limesurvey_survey_{$iSurveyID}.lss";

            $this->_addHeaders($fn, "text/xml", "Mon, 26 Jul 1997 05:00:00 GMT");

            echo surveyGetXMLData($iSurveyID);
            exit;
        }
        elseif ( $action == "exportstructurequexml" )
        {
            if ( isset($surveyprintlang) && ! empty($surveyprintlang) )
            {
                $quexmllang = $surveyprintlang;
            }
            else
            {
                $quexmllang=Survey::model()->findByPk($iSurveyID)->language;
            }

            if ( ! (isset($noheader) && $noheader == TRUE) )
            {
                $fn = "survey_{$iSurveyID}_{$quexmllang}.xml";

                $this->_addHeaders($fn, "text/xml", "Mon, 26 Jul 1997 05:00:00 GMT");

                echo quexml_export($iSurveyID, $quexmllang);
                exit;
            }
        }
        elseif ( $action == "exportstructureLsrcCsv" )
        {
            lsrccsv_export($iSurveyID);
        }
        elseif ($action == 'exportstructureexcel')
        {
            $this->_exportexcel($iSurveyID);
        }
        elseif ( $action == "exportarchive" )
        {
            $this->_exportarchive($iSurveyID);
        }
    }

    /**
     * Generate and Excel file for the survey structure
     * @param type $surveyid
     */
    private function _exportexcel($surveyid)
    {
        $fn = "limesurvey_survey_$surveyid.xls";
        $this->_addHeaders($fn, "text/csv", 0);

        $data =& LimeExpressionManager::ExcelSurveyExport($surveyid);

        Yii::import('application.libraries.admin.pear.Spreadsheet.Excel.Xlswriter', true);

        // actually generate an Excel workbook
        $workbook = new xlswriter;
        $workbook->setVersion(8);
        $workbook->send($fn);

        $sheet =& $workbook->addWorksheet(); // do not translate/change this - the library does not support any special chars in sheet name
        $sheet->setInputEncoding('utf-8');

        $rc = -1;    // row counter
        $cc = -1;    // column counter
        foreach($data as $row)
        {
            ++$rc;
            $cc=-1;
            foreach ($row as $col)
            {
                // Enclose in \" if begins by =
                ++$cc;
                if (substr($col,0,1) ==  "=")
                {
                    $col = "\"".$col."\"";
                }
                $col = str_replace(array("\t","\n","\r"),array(" "," "," "),$col);
                $sheet->write($rc, $cc, $col);
            }
        }
        $workbook->close();
        return;
    }

    private function _addHeaders($filename, $content_type, $expires, $pragma = "public")
    {
        header("Content-Type: {$content_type}; charset=UTF-8");
        header("Content-Disposition: attachment; filename={$filename}");
        header("Expires: {$expires}");    // Date in the past
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Pragma: {$pragma}");                          // HTTP/1.0
    }

    /**
    * Renders template(s) wrapped in header and footer
    *
    * @param string $sAction Current action, the folder to fetch views from
    * @param string|array $aViewUrls View url(s)
    * @param array $aData Data to be passed on. Optional.
    */
    protected function _renderWrappedTemplate($sAction = 'export', $aViewUrls = array(), $aData = array())
    {
        $this->getController()->_css_admin_includes(Yii::app()->getConfig('adminstyleurl')."superfish.css");

        $aData['display']['menu_bars']['gid_action'] = 'exportstructureGroup';

        parent::_renderWrappedTemplate($sAction, $aViewUrls, $aData);
    }
}
