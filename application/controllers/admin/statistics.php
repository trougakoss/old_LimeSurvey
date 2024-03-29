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
 * Statistics Controller
 *
 * This controller performs statistics actions
 *
 * @package		LimeSurvey
 * @subpackage	Backend
 */
class statistics extends Survey_Common_Action {

    function __construct($controller, $id)
    {
        parent::__construct($controller, $id);

		Yii::app()->loadHelper("surveytranslator");
    }

	/**
	 * Constructor
	 */
	public function run($surveyid, $subaction = null)
	{
		$surveyid = sanitize_int($surveyid);
		//TODO: Convert question types to views

		$clang = $this->getController()->lang;

		$imageurl = Yii::app()->getConfig("imageurl");
		$aData = array('clang' => $clang, 'imageurl' => $imageurl);

		/*
		 * We need this later:
		 *  1 - Array Dual Scale
		 *  5 - 5 Point Choice
		 *  A - Array (5 Point Choice)
		 *  B - Array (10 Point Choice)
		 *  C - Array (Yes/No/Uncertain)
		 *  D - Date
		 *  E - Array (Increase, Same, Decrease)
		 *  F - Array (Flexible Labels)
		 *  G - Gender
		 *  H - Array (Flexible Labels) by Column
		 *  I - Language Switch
		 *  K - Multiple Numerical Input
		 *  L - List (Radio)
		 *  M - Multiple choice
		 *  N - Numerical Input
		 *  O - List With Comment
		 *  P - Multiple choice with comments
		 *  Q - Multiple Short Text
		 *  R - Ranking
		 *  S - Short Free Text
		 *  T - Long Free Text
		 *  U - Huge Free Text
		 *  X - Boilerplate Question
		 *  Y - Yes/No
		 *  ! - List (Dropdown)
		 *  : - Array (Flexible Labels) multiple drop down
		 *  ; - Array (Flexible Labels) multiple texts
		 *  | - File Upload


		 Debugging help:
		 echo '<script language="javascript" type="text/javascript">alert("HI");</script>';
		 */

		//split up results to extend statistics -> NOT WORKING YET! DO NOT ENABLE THIS!
		$showcombinedresults = 0;

		/*
		 * this variable is used in the function shortencode() which cuts off a question/answer title
		 * after $maxchars and shows the rest as tooltip
		 */
		$maxchars = 50;



		//don't call this script directly!
		//if (isset($_REQUEST['homedir'])) {die('You cannot start this script directly');}

		//some includes, the progressbar is used to show a progressbar while generating the graphs
		//include_once("login_check.php");
		//require_once('classes/core/class.progressbar.php');

		//we collect all the output within this variable
		$statisticsoutput ='';

		//output for chosing questions to cross query
		$cr_statisticsoutput = '';

		// This gets all the 'to be shown questions' from the POST and puts these into an array
		$summary=returnGlobal('summary');
		$statlang=returnGlobal('statlang');

		//if $summary isn't an array we create one
		if (isset($summary) && !is_array($summary)) {
		    $summary = explode("+", $summary);
		}

		//no survey ID? -> come and get one
		if (!isset($surveyid)) {$surveyid=returnGlobal('sid');}

		//still no survey ID -> error
		$aData['surveyid'] = $surveyid;


		// Set language for questions and answers to base language of this survey
		$language = Survey::model()->findByPk($surveyid)->language;
		$aData['language'] = $language;

//		$chartfontfile = Yii::app()->getConfig("chartfontfile");
//		//pick the best font file if font setting is 'auto'
//		if ($chartfontfile=='auto')
//		{
//		    $chartfontfile='vera.ttf';
//		    if ( $language=='ar')
//		    {
//		        $chartfontfile='KacstOffice.ttf';
//		    }
//		    elseif  ($language=='fa' )
//		    {
//		        $chartfontfile='KacstFarsi.ttf';
//		    }
//		    elseif  ($language=='el' )
//		    {
//		        $chartfontfile='DejaVuLGCSans.ttf';
//		    }
//		    elseif  ($language=='zh-Hant-HK' || $language=='zh-Hant-TW' || $language=='zh-Hans')
//		    {
//		        $chartfontfile='fireflysung.ttf';
//		    }
//
//		}

		//$statisticsoutput .= "
		//<script type='text/javascript'' >
		//<!--
		//function selectAll(name){
		//	//var name=name;
		//
		//	alert(name);
		//
		//	temp = document.+name+.elements.length;
		//
		//    for (i=0; i < temp; i++) {
		//    if(document.+name+.elements[i].checked == 1)
		//    	{document.+name+.elements[i].checked = 0;
		//    	 document.+name+.+name+_btn.value = 'Select All'; }
		//    else {document.+name.elements[i].checked = 1;
		//   	 document.+name+.+name+_btn.value = 'Deselect All'; }
		//	}
		//}
		////-->
		//</script>";

		//hide/show the filter
		//filtersettings by default aren't shown when showing the results
		//$statisticsoutput .= '<script type="text/javascript" src="scripts/statistics.js"></script>';
		$this->getController()->_js_admin_includes(Yii::app()->getConfig('adminscripts') . 'statistics.js');

		$aData['display']['menu_bars']['browse'] = $clang->gT("Quick statistics");

		//Select public language file
		$row  = Survey::model()->find('sid = :sid', array(':sid' => $surveyid));

		/*
		 * check if there is a datestamp available for this survey
		 * yes -> $datestamp="Y"
		 * no -> $datestamp="N"
		 */
		$datestamp = $row->datestamp;

		// 1: Get list of questions from survey

		/*
		 * We want to have the following data
		 * a) "questions" -> all table namens, e.g.
		 * qid
		 * sid
		 * gid
		 * type
		 * title
		 * question
		 * preg
		 * help
		 * other
		 * mandatory
		 * lid
		 * lid1
		 * question_order
		 * language
		 *
		 * b) "groups" -> group_name + group_order *
		 */

		//store all the data in $rows
		$rows = Questions::getQuestionList($surveyid, $language);

		//SORT IN NATURAL ORDER!
		usort($rows, 'groupOrderThenQuestionOrder');

		//put the question information into the filter array
		$filters = array();
		foreach ($rows as $row)
		{
		    //store some column names in $filters array
		    $filters[]=array($row['qid'],
		    $row['gid'],
		    $row['type'],
		    $row['title'],
		    $row['group_name'],
		    flattenText($row['question']));
		}
		$aData['filters'] = $filters;

		//var_dump($filters);
		// SHOW ID FIELD

		$grapherror = false;
        $error = '';
		if (!function_exists("gd_info")) {
			$grapherror = true;
            $error.='<br />'.$clang->gT('You do not have the GD Library installed. Showing charts requires the GD library to function properly.');
            $error.='<br />'.$clang->gT('visit http://us2.php.net/manual/en/ref.image.php for more information').'<br />';
		}
		elseif (!function_exists("imageftbbox")) {
		    $grapherror = true;
            $error.='<br />'.$clang->gT('You do not have the Freetype Library installed. Showing charts requires the Freetype library to function properly.');
            $error.='<br />'.$clang->gT('visit http://us2.php.net/manual/en/ref.image.php for more information').'<br />';
		}
        
		if ($grapherror)
		{
		    unset($_POST['usegraph']);
		}


		//pre-selection of filter forms
		if (incompleteAnsFilterState() == "filter")
		{
		    $selecthide="selected='selected'";
		    $selectshow="";
		    $selectinc="";
		}
		elseif (incompleteAnsFilterState() == "inc")
		{
		    $selecthide="";
		    $selectshow="";
		    $selectinc="selected='selected'";
		}
		else
		{
		    $selecthide="";
		    $selectshow="selected='selected'";
		    $selectinc="";
		}
		$aData['selecthide'] = $selecthide;
		$aData['selectshow'] = $selectshow;
		$aData['selectinc'] = $selectinc;
        $aData['error'] = $error;

		//if ($selecthide!='')
		//{
		//    $statisticsoutput .= " style='display:none' ";
		//}

		$survlangs = Survey::model()->findByPk($surveyid)->additionalLanguages;
		$survlangs[] = Survey::model()->findByPk($surveyid)->language;
		$aData['survlangs'] = $survlangs;
		$aData['datestamp'] = $datestamp;

		//if the survey contains timestamps you can filter by timestamp, too


		//Output selector


		//second row below options -> filter settings headline


		$filterchoice_state=returnGlobal('filterchoice_state');
		$aData['filterchoice_state'] = $filterchoice_state;


		/*
		 * let's go through the filter array which contains
		 * 	['qid'],
		 ['gid'],
		 ['type'],
		 ['title'],
		 ['group_name'],
		 ['question'],
		 ['lid'],
		 ['lid1']);
		 */

		$currentgroup='';
        $counter = 0;
		foreach ($filters as $key1 => $flt)
		{
		    //is there a previous question type set?


		    /*
		     * remember: $flt is structured like this
		     *  ['qid'],
		     ['gid'],
		     ['type'],
		     ['title'],
		     ['group_name'],
		     ['question'],
		     ['lid'],
		     ['lid1']);
		     */

		    //SGQ identifier

		    //full question title

		    /*
		     * Check question type: This question types will be used (all others are separated in the if clause)
		     *  5 - 5 Point Choice
		     G - Gender
		     I - Language Switch
		     L - List (Radio)
		     M - Multiple choice
		     N - Numerical Input
		     | - File Upload
		     O - List With Comment
		     P - Multiple choice with comments
		     Y - Yes/No
		     ! - List (Dropdown) )
		     */


		    /////////////////////////////////////////////////////////////////////////////////////////////////
		    //This section presents the filter list, in various different ways depending on the question type
		    /////////////////////////////////////////////////////////////////////////////////////////////////

		    //let's switch through the question type for each question
		    switch ($flt[2])
		    {
		        case "K": // Multiple Numerical
		            //get answers
		            $result = Questions::getQuestionsForStatistics('title as code, question as answer', "parent_qid=$flt[0] AND language = '{$language}'", 'question_order');
		            $aData['result'][$key1]['key1'] = $result;
		            break;



		        case "Q": // Multiple Short Text

		            //get subqestions
		            $result = Questions::getQuestionsForStatistics('title as code, question as answer', "parent_qid=$flt[0] AND language = '{$language}'", 'question_order');
		            $aData['result'][$key1] = $result;
		            break;

		            //----------------------- ARRAYS --------------------------

		        case "A": // ARRAY OF 5 POINT CHOICE QUESTIONS

		            //get answers
		            $result = Questions::getQuestionsForStatistics('title, question', "parent_qid=$flt[0] AND language = '{$language}'", 'question_order');
		            $aData['result'][$key1] = $result;
		            break;



		            //just like above only a different loop
		        case "B": // ARRAY OF 10 POINT CHOICE QUESTIONS
		            $result = Questions::getQuestionsForStatistics('title, question', "parent_qid=$flt[0] AND language = '{$language}'", 'question_order');
		            $aData['result'][$key1] = $result;
		            break;



		        case "C": // ARRAY OF YES\No\$clang->gT("Uncertain") QUESTIONS
		            //get answers
		            $result = Questions::getQuestionsForStatistics('title, question', "parent_qid=$flt[0] AND language = '{$language}'", 'question_order');
		            $aData['result'][$key1] = $result;
		            break;



		            //similiar to the above one
		        case "E": // ARRAY OF Increase/Same/Decrease QUESTIONS
		            $result = Questions::getQuestionsForStatistics('title, question', "parent_qid=$flt[0] AND language = '{$language}'", 'question_order');
		            $aData['result'][$key1] = $result;
		            break;

		        case ";":  //ARRAY (Multi Flex) (Text)
		            $result = Questions::getQuestionsForStatistics('title, question', "parent_qid=$flt[0] AND language = '{$language}' AND scale_id = 0", 'question_order');
		            $aData['result'][$key1] = $result;
		            foreach($result as $key => $row)
		            {
		            	$fresult = Questions::getQuestionsForStatistics('title, question', "parent_qid=$flt[0] AND language = '{$language}' AND scale_id = 1", 'question_order');
		                $aData['fresults'][$key1][$key] = $fresult;
		            }
		            break;

		        case ":":  //ARRAY (Multi Flex) (Numbers)
		            $result = Questions::getQuestionsForStatistics('title, question', "parent_qid=$flt[0] AND language = '{$language}' AND scale_id = 0", 'question_order');
		            $aData['result'][$key1] = $result;
		            foreach($result as $row)
		            {
		            	$fresult = Questions::getQuestionsForStatistics('*', "parent_qid=$flt[0] AND language = '{$language}' AND scale_id = 1", 'question_order, title');
		            	$aData['fresults'][$key1] = $fresult;
		            }
		            break;
		            /*
		             * For question type "F" and "H" you can use labels.
		             * The only difference is that the labels are applied to column heading
		             * or rows respectively
		             */
		        case "F": // FlEXIBLE ARRAY
		        case "H": // ARRAY (By Column)
		            //Get answers. We always use the answer code because the label might be too long elsewise
		            $result = Questions::getQuestionsForStatistics('title, question', "parent_qid=$flt[0] AND language = '{$language}'", 'question_order');
		            $aData['result'][$key1] = $result;

		            //check all the answers
		            foreach($result as $row)
		            {
		                $fresult = Answers::getQuestionsForStatistics('*', "qid=$flt[0] AND language = '{$language}'", 'sortorder, code');
		                $aData['fresults'][$key1] = $fresult;
		            }

		            //$statisticsoutput .= "\t\t\t\t<td>\n";
		            $counter=0;
		            break;



		        case "R": //RANKING
		            //get some answers
		            $result = Answers::getQuestionsForStatistics('code, answer', "qid=$flt[0] AND language = '{$language}'", 'sortorder, answer');
		            $aData['result'][$key1] = $result;
		            break;

		        case "1": // MULTI SCALE

		            //get answers
		            $result = Questions::getQuestionsForStatistics('title, question', "parent_qid=$flt[0] AND language = '{$language}'", 'question_order');
		            $aData['result'][$key1] = $result;
		            //loop through answers
		            foreach($result as $key => $row)
		            {

		                //check if there is a dualscale_headerA/B
		                $dshresult = Question_attributes::getQuestionsForStatistics('value', "qid=$flt[0] AND attribute = 'dualscale_headerA'", '');
		                $aData['dshresults'][$key1][$key] = $dshresult;


		                $fresult = Answers::getQuestionsForStatistics('*', "qid=$flt[0] AND language = '{$language}' AND scale_id = 0", 'sortorder, code');

		                $aData['fresults'][$key1][$key] = $fresult;


		                $dshresult2 = Question_attributes::getQuestionsForStatistics('value', "qid=$flt[0] AND attribute = 'dualscale_headerB'", '');
		                $aData['dshresults2'][$key1][$key] = $dshresult2;
		            }
		            break;

		        case "P":  //P - Multiple choice with comments
		        case "M":  //M - Multiple choice

		            //get answers
		            $result = Questions::getQuestionsForStatistics('title, question', "parent_qid = $flt[0] AND language = '$language'", 'question_order');
		            $aData['result'][$key1] = $result;
		            break;


		            /*
		             * This question types use the default settings:
		             * 	L - List (Radio)
		             O - List With Comment
		             P - Multiple choice with comments
		             ! - List (Dropdown)
		             */
		        default:

		            //get answers
		            $result = Answers::getQuestionsForStatistics('code, answer', "qid=$flt[0] AND language = '$language'", 'sortorder, answer');
		            $aData['result'][$key1] = $result;
		            break;

		    }	//end switch -> check question types and create filter forms

		    $currentgroup=$flt[1];

		    $counter++;

		    //temporary save the type of the previous question
		    //used to adjust linebreaks
		    $previousquestiontype = $flt[2];

		    //Group close
		    //$statisticsoutput .= "\n\t\t\t\t<!-- --></tr>\n\t\t\t</table></div></td></tr>\n";
		}

		// ----------------------------------- END FILTER FORM ---------------------------------------

		Yii::app()->loadHelper('admin/statistics');
		//Show Summary results
		if (isset($summary) && $summary)
		{
		    if(isset($_POST['usegraph']))
		    {
		        $usegraph = 1;
		    }
		    else
		    {
		        $usegraph = 0;
		    }
            $aData['usegraph'] = $usegraph;
		    $outputType = $_POST['outputtype'];
		    switch($outputType){

		        case 'html':
		            $statisticsoutput .= generate_statistics($surveyid,$summary,$summary,$usegraph,$outputType,'DD',$statlang);
		            break;
		        case 'pdf':
		            generate_statistics($surveyid,$summary,$summary,$usegraph,$outputType,'I',$statlang);
		            exit;
		            break;
		        case 'xls':
		            generate_statistics($surveyid,$summary,$summary,$usegraph,$outputType,'DD',$statlang);
		            exit;
		            break;
		        default:

		            break;

		    }

		}	//end if -> show summary results
        
		$aData['output'] = $statisticsoutput;

        $this->_renderWrappedTemplate('export', 'statistics_view', $aData);

	}

	function graph()
	{
        Yii::app()->loadHelper('admin/statistics_helper');
		Yii::app()->loadHelper("surveytranslator");

        // Initialise PCHART
        require_once(Yii::app()->basePath . '/third_party/pchart/pchart/pChart.class');
        require_once(Yii::app()->basePath . '/third_party/pchart/pchart/pData.class');
        require_once(Yii::app()->basePath . '/third_party/pchart/pchart/pCache.class');
        $tempdir = Yii::app()->getConfig("tempdir");
        $MyCache = new pCache($tempdir.'/');

	    $aData['success'] = 1;

	    if (isset($_POST['cmd']) || !isset($_POST['id'])) {
	        list($qsid, $qgid, $qqid) = explode("X", substr($_POST['id'], 1), 3);
	        $qtype = substr($_POST['id'], 0, 1);
            $aattr = getQuestionAttributeValues($qqid, substr($_POST['id'], 0, 1));
            $field = substr($_POST['id'], 1);

	        switch ($_POST['cmd']) {
	            case 'showmap':
	                if (isset($aattr['location_mapservice'])) {

                        $aData['mapdata'] = array (
                            "coord" => getQuestionMapData($field, $qsid),
                            "zoom" => $aattr['location_mapzoom'],
                            "width" => $aattr['location_mapwidth'],
                            "height" => $aattr['location_mapheight']
                        );
	                    Question_attributes::model()->setAttribute($qqid, 'statistics_showmap', 1);
                    } else {
	                    $aData['success'] = 0;
                    }
	                break;
	            case 'hidemap':
	                if (isset($aattr['location_mapservice'])) {
                        $aData['success'] = 1;
	                    Question_attributes::model()->setAttribute($qqid, 'statistics_showmap', 0);
                    } else {
	                    $aData['success'] = 0;
                    }
	                break;
	            case 'showgraph':
	                if (isset($aattr['location_mapservice'])) {
                        $aData['mapdata'] = array (
                            "coord" => getQuestionMapData($field, $qsid),
                            "zoom" => $aattr['location_mapzoom'],
                            "width" => $aattr['location_mapwidth'],
                            "height" => $aattr['location_mapheight']
                        );
	                }

                    $bChartType = $qtype != "M" && $qtype != "P" && $aattr["statistics_graphtype"] == "1";

                    $adata = Yii::app()->session['stats'][$_POST['id']];
	                $aData['chartdata'] = createChart($qqid, $qsid, $bChartType, $adata['lbl'], $adata['gdata'], $adata['grawdata'], $MyCache);


                    Question_attributes::model()->setAttribute($qqid, 'statistics_showgraph', 1);
	                break;
	            case 'hidegraph':
                    Question_attributes::model()->setAttribute($qqid, 'statistics_showgraph', 0);
	                break;
	            case 'showbar':
	                if ($qtype == "M" || $qtype == "P") {
	                    $aData['success'] = 0;
	                    break;
	                }

                    Question_attributes::model()->setAttribute($qqid, 'statistics_graphtype', 0);

                    $adata = Yii::app()->session['stats'][$_POST['id']];
	                $aData['chartdata'] =  createChart($qqid, $qsid, 0, $adata['lbl'], $adata['gdata'], $adata['grawdata'], $MyCache);

	                break;
	            case 'showpie':

	                if ($qtype == "M" || $qtype == "P") {
	                    $aData['success'] = 0;
	                    break;
	                }

                    Question_attributes::model()->setAttribute($qqid, 'statistics_graphtype', 1);

                    $adata = Yii::app()->session['stats'][$_POST['id']];
	                $aData['chartdata'] =  createChart($qqid, $qsid, 1, $adata['lbl'], $adata['gdata'], $adata['grawdata'], $MyCache);


	                break;
	            default:
	                $aData['success'] = 0;
	                break;
	        }
	    } else {
	        $aData['success'] = 0;
	    }

        $this->_renderWrappedTemplate('export', 'statistics_graph_view', $aData);
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
        parent::_renderWrappedTemplate($sAction, $aViewUrls, $aData);
    }

}
