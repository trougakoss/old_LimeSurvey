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

//include_once("login_check.php");
//Security Checked: POST/GET/SESSION/DB/returnGlobal

function updateset($lid)
{
    $clang = Yii::app()->lang;

    // Get added and deleted languagesid arrays
    if ($_POST['languageids'])
        $postlanguageids = sanitize_languagecodeS($_POST['languageids']);

    if ($_POST['label_name'])
        $postlabel_name = sanitize_labelname($_POST['label_name']);

    $newlanidarray = explode(" ",trim($postlanguageids));

    $oldlangidsarray = array();
    $labelset = Labelsets::model()->findByAttributes(array('lid' => $lid));
    $oldlangidsarray = explode(' ', $labelset->languages);

    $addlangidsarray = array_diff($newlanidarray, $oldlangidsarray);
    $dellangidsarray = array_diff($oldlangidsarray, $newlanidarray);

    // If new languages are added, create labels' codes and sortorder for the new languages
    $result = Label::model()->findAllByAttributes(array('lid' => $lid), array('order' => 'code, sortorder, assessment_value'));
    if ($result)
        foreach ($result as $row)
            $oldcodesarray[$row['code']] = array('sortorder'=> $row['sortorder'], 'assessment_value'=> $row['assessment_value']);

    if (isset($oldcodesarray) && count($oldcodesarray) > 0 )
        foreach ($addlangidsarray as $addedlangid)
            foreach ($oldcodesarray as $oldcode => $olddata)
                $sqlvalues[]= array('lid' => $lid, 'code' => $oldcode, 'sortorder' => $olddata['sortorder'], 'language' => $addedlangid, 'assessment_value' => $olddata['assessment_value']);

    if (isset($sqlvalues))
        foreach ($sqlvalues as $sqlvalue)
            Label::model()->insert($sqlvalue);

    // If languages are removed, delete labels for these languages
    $criteria = new CDbCriteria;
    $criteria->addColumnCondition(array('lid' => $lid));
    $langcriteria = new CDbCriteria();
    foreach ($dellangidsarray as $dellangid)
        $langcriteria->addColumnCondition(array('language' => $dellangid), 'OR');
    $criteria->mergeWith($langcriteria);

    if (!empty($dellangidsarray))
        $result = Label::model()->deleteAll($criteria);

    // Update the label set itself
    $labelset->label_name = $postlabel_name;
    $labelset->languages = $postlanguageids;
    $labelset->save();
}

/**
* Deletes a label set alog with its labels
*
* @param mixed $lid Label ID
* @return boolean Returns always true
*/
function deletelabelset($lid)
{
    $query = "DELETE FROM {{labels}} WHERE lid=$lid";
    $result = Yii::app()->db->createCommand($query)->execute();
    $query = "DELETE FROM {{labelsets}} WHERE lid=$lid";
    $result = Yii::app()->db->createCommand($query)->execute();
    return true;
}



function insertlabelset()
{
    //global $labelsoutput;
    //	$labelsoutput.= $_POST['languageids'];  For debug purposes
    $clang = Yii::app()->lang;


    if (!empty($_POST['languageids']))
    {
        $postlanguageids=sanitize_languagecodeS($_POST['languageids']);
    }

    if (!empty($_POST['label_name']))
    {
        $postlabel_name=sanitize_labelname($_POST['label_name']);
    }

    //postlabel_name = dbQuoteAll($postlabel_name,true);
    //$postlanguageids = dbQuoteAll($postlanguageids,true);
    $data = array(
    'label_name' => $postlabel_name,
    'languages' => $postlanguageids
    );

    //$query = "INSERT INTO ".db_table_name('labelsets')." (label_name,languages) VALUES ({$postlabel_name},{$postlanguageids})";
    $result=Labelsets::model()->insertRecords($data);
    if (!$result)
    {
        safeDie("Inserting the label set failed:<br />".$query."<br />");
    }
    else
    {
        return $result;
    }

}

function modlabelsetanswers($lid)
{

    //global  $labelsoutput;

    $clang = Yii::app()->lang;

    $ajax = false;

    if (isset($_POST['ajax']) && $_POST['ajax'] == "1"){
        $ajax = true;
    }
    if (!isset($_POST['method'])) {
        $_POST['method'] = $clang->gT("Save");
    }

    //unescape single quotes
    $labeldata = CHttpRequest::getPost('dataToSend');
    $labeldata = str_replace("\'","'",$labeldata);


    $data = json_decode($labeldata);

    if ($ajax)
        $lid = insertlabelset();

    if (count(array_unique($data->{'codelist'})) == count($data->{'codelist'}))
    {

        $query = "DELETE FROM {{labels}} WHERE lid = '$lid'";

        $result = Yii::app()->db->createCommand($query)->execute();

        foreach($data->{'codelist'} as $index=>$codeid){

            $codeObj = $data->$codeid;


            $actualcode = $codeObj->{'code'};
            //$codeid = dbQuoteAll($codeid,true);

            $assessmentvalue = (int)($codeObj->{'assessmentvalue'});
            foreach($data->{'langs'} as $lang){

                $strTemp = 'text_'.$lang;
                $title = $codeObj->$strTemp;

                $p = new CHtmlPurifier();

                if (Yii::app()->getConfig('filterxsshtml'))
                    $title = $p->purify($title);
                else
                    $title = html_entity_decode($title, ENT_QUOTES, "UTF-8");


                // Fix bug with FCKEditor saving strange BR types
                $title = fixCKeditorText($title);
                $sort_order = $index;

                $insertdata = array(
                'lid' => $lid,
                'code' => $actualcode,
                'title' => $title,
                'sortorder' => $sort_order,
                'assessment_value' => $assessmentvalue,
                'language' => $lang
                );

                //$query = "INSERT INTO ".db_table_name('labels')." (`lid`,`code`,`title`,`sortorder`, `assessment_value`, `language`)
                //    VALUES('$lid',$actualcode,$title,$sort_order,$assessmentvalue,$lang)";

                $result = Yii::app()->db->createCommand()->insert('{{labels}}', $insertdata);
            }
        }


        Yii::app()->session['flashmessage'] = $clang->gT("Labels sucessfully updated");

    }
    else
    {
        $labelsoutput= "<script type=\"text/javascript\">\n<!--\n alert(\"".$clang->gT("Can't update labels because you are using duplicated codes","js")."\")\n //-->\n</script>\n";
    }

    if ($ajax){ die(); }

    if (isset($labelsoutput))
    {
        echo $labelsoutput;
        exit();
    }

}

/**
* Function rewrites the sortorder for a label set
*
* @param mixed $lid Label set ID
*/
function fixorder($lid) {

    $clang = Yii::app()->lang;

    $qulabelset = "SELECT * FROM {{labelsets}} WHERE lid=$lid";
    $rslabelset = Yii::app()->db->createCommand($qulabelset)->query();
    $rwlabelset=$rslabelset->read();
    $lslanguages=explode(" ", trim($rwlabelset['languages']));
    foreach ($lslanguages as $lslanguage)
    {
        $query = "SELECT lid, code, title, sortorder FROM {{labels}} WHERE lid=:lid and language=:lang ORDER BY sortorder, code";
        $result = Yii::app()->createCommand($query)->query(array(':lid' => $lid, ':lang' => $lslanguage)); // or safeDie("Can't read labels table: $query // (lid=$lid, language=$lslanguage) "
        $position=0;
        foreach ($result->readAll() as $row)
        {
            $position=sprintf("%05d", $position);
            $query2="UPDATE {{labels}} SET sortorder='$position' WHERE lid=".$row['lid']." AND code=".$row['code']." AND title=".$row['title']." AND language='$lslanguage' ";
            $result2=Yii::app()->db->createCommand($query2)->execute();
            $position++;
        }
    }
}
