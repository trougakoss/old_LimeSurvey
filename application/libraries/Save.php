<?php
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
*
//Security Checked: POST, GET, SESSION, REQUEST, returnGlobal, DB

Redesigned 7/25/2006 - swales

1.  Save Feature (// --> START NEW FEATURE - SAVE)

How it used to work
-------------------
1. The old save method would save answers to the "survey_x" table only when the submit button was clicked.
2. If "allow saves" was turned on then answers were temporarily recorded in the "saved" table.

Why change this feature?
------------------------
If a user did not complete a survey, ALL their answers were lost since no submit (database insert) was performed.


Save Feature redesign
---------------------
Benefits
Partial survey answers are saved (provided at least Next/Prev/Last/Submit/Save so far clicked at least once).

Details.
1. The answers are saved in the "survey_x" table only.  The "saved" table is no longer used.
2. The "saved_control" table has new column (srid) that points to the "survey_x" record it corresponds to.
3. Answers are saved every time you move between pages (Next,Prev,Last,Submit, or Save so far).
4. Only the fields modified on the page are updated. A new hidden field "modfields" store which fields have changed. - REVERTED
5. Answered are reloaded from the database after the save so that if some other answers were modified by someone else
the updates would be picked up for the current page.  There is still an issue if two people modify the same
answer at the same time.. the 'last one to save' wins.
6. The survey_x datestamp field is updated every time the record is updated.
7. Template can now contain {DATESTAMP} to show the last modified date/time.
8. A new field 'submitdate' has been added to the survey_x table and is written when the submit button is clicked.
9. Save So Far now displays on Submit page. This allows the user one last chance to create a saved_control record so they
can return later.

Notes
-----
1. A new column SRID has been added to saved_control.
2. saved table no longer exists.
*/

class Save {

    function showsaveform()
    {
        //Show 'SAVE FORM' only when click the 'Save so far' button the first time, or when duplicate is found on SAVE FORM.
        global $thistpl, $errormsg, $thissurvey, $surveyid, $clang, $clienttoken, $thisstep;
		$redata = compact(array_keys(get_defined_vars()));
        sendCacheHeaders();
        doHeader();
		echo templatereplace(file_get_contents("$thistpl/startpage.pstpl"),array(),$redata);
        echo "\n\n<!-- JAVASCRIPT FOR CONDITIONAL QUESTIONS -->\n"
        ."\t<script type='text/javascript'>\n"
        ."\t<!--\n"
        ."function checkconditions(value, name, type, evt_type)\n"
        ."\t{\n"
        ."\t}\n"
        ."\t//-->\n"
        ."\t</script>\n\n";

        echo "<form method='post' action='".Yii::app()->getController()->createUrl("/survey/index")."'>\n";
        //PRESENT OPTIONS SCREEN
        if (isset($errormsg) && $errormsg != "")
        {
            $errormsg .= "<p>".$clang->gT("Please try again.")."</p>";
        }
		echo templatereplace(file_get_contents("$thistpl/save.pstpl"),array(),$redata);
        //END
        echo "<input type='hidden' name='sid' value='$surveyid' />\n";
        echo "<input type='hidden' name='thisstep' value='",$thisstep,"' />\n";
        echo "<input type='hidden' name='token' value='",$clienttoken,"' />\n";
        echo "<input type='hidden' name='saveprompt' value='Y' />\n";
        echo "</form>";

		echo templatereplace(file_get_contents("$thistpl/endpage.pstpl"),array(),$redata);
        echo "</html>\n";
        exit;
    }



    function savedcontrol()
    {
        //This data will be saved to the "saved_control" table with one row per response.
        // - a unique "saved_id" value (autoincremented)
        // - the "sid" for this survey
        // - the "srid" for the survey_x row id
        // - "saved_thisstep" which is the step the user is up to in this survey
        // - "saved_ip" which is the ip address of the submitter
        // - "saved_date" which is the date ofthe saved response
        // - an "identifier" which is like a username
        // - a "password"
        // - "fieldname" which is the fieldname of the saved response
        // - "value" which is the value of the response
        //We start by generating the first 5 values which are consistent for all rows.

        global $surveyid, $thissurvey, $errormsg, $publicurl, $sitename, $timeadjust, $clang, $clienttoken, $thisstep;

        //Check that the required fields have been completed.
        $errormsg="";
        if (!isset($_POST['savename']) || !$_POST['savename']) {$errormsg.=$clang->gT("You must supply a name for this saved session.")."<br />\n";}
        if (!isset($_POST['savepass']) || !$_POST['savepass']) {$errormsg.=$clang->gT("You must supply a password for this saved session.")."<br />\n";}
        if ((isset($_POST['savepass']) && !isset($_POST['savepass2'])) || $_POST['savepass'] != $_POST['savepass2'])
        {$errormsg.=$clang->gT("Your passwords do not match.")."<br />\n";}
        // if security question asnwer is incorrect
        if (function_exists("ImageCreate") && isCaptchaEnabled('saveandloadscreen',$thissurvey['usecaptcha']))
        {
            if (!isset($_POST['loadsecurity']) ||
            !isset($_SESSION['survey_'.$surveyid]['secanswer']) ||
            $_POST['loadsecurity'] != $_SESSION['survey_'.$surveyid]['secanswer'])
            {
                $errormsg .= $clang->gT("The answer to the security question is incorrect.")."<br />\n";
            }
        }

        if ($errormsg)
        {
            return;
        }
        //All the fields are correct. Now make sure there's not already a matching saved item
        $query = "SELECT COUNT(*) FROM {{saved_control}}\n"
        ."WHERE sid=$surveyid\n"
        ."AND identifier='{$_POST['savename']}'";
        $result = Yii::app()->db->createCommand($query)->query() or safeDie("Error checking for duplicates!<br />$query<br />");   // Checked
        list($count) = $result->getRowCount();
        if ($count > 0)
        {
            $errormsg.=$clang->gT("This name has already been used for this survey. You must use a unique save name.")."<br />\n";
            return;
        }
        else
        {
            //INSERT BLANK RECORD INTO "survey_x" if one doesn't already exist
            if (!isset($_SESSION['survey_'.$surveyid]['srid']))
            {
                $today = dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i:s", $timeadjust);
                $sdata = array("datestamp"=>$today,
                "ipaddr"=>getIPAddress(),
                "startlanguage"=>$_SESSION['survey_'.$surveyid]['s_lang'],
                "refurl"=>getenv("HTTP_REFERER"));
                if (Survey_dynamic::model($thissurvey['sid'])->insert($sdata))    // Checked
                {
                    $srid = Yii::app()->db->getLastInsertID();
                    $_SESSION['survey_'.$surveyid]['srid'] = $srid;
                }
                else
                {
                    safeDie("Unable to insert record into survey table.<br /><br />");
                }
            }
            //CREATE ENTRY INTO "saved_control"
            $today = dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i:s", $timeadjust);
            $scdata = array("sid"=>$surveyid,
            "srid"=>$_SESSION['survey_'.$surveyid]['srid'],
            "identifier"=>$_POST['savename'], // Binding does escape , so no quoting/escaping necessary
            "access_code"=>md5($_POST['savepass']),
            "email"=>$_POST['saveemail'],
            "ip"=>getIPAddress(),
            "refurl"=>getenv("HTTP_REFERER"),
            "saved_thisstep"=>$thisstep,
            "status"=>"S",
            "saved_date"=>$today);


            if (Saved_control::model()->insert($scdata))   // Checked
            {
                $scid = Yii::app()->db->getLastInsertID();
                $_SESSION['survey_'.$surveyid]['scid'] = $scid;
            }
            else
            {
                safeDie("Unable to insert record into saved_control table.<br /><br />");
            }

            $_SESSION['survey_'.$surveyid]['holdname']=$_POST['savename']; //Session variable used to load answers every page. Unsafe - so it has to be taken care of on output
            $_SESSION['survey_'.$surveyid]['holdpass']=$_POST['savepass']; //Session variable used to load answers every page.  Unsafe - so it has to be taken care of on output

            //Email if needed
            if (isset($_POST['saveemail']) && validateEmailAddress($_POST['saveemail']))
            {
                $subject=$clang->gT("Saved Survey Details") . " - " . $thissurvey['name'];
                $message=$clang->gT("Thank you for saving your survey in progress.  The following details can be used to return to this survey and continue where you left off.  Please keep this e-mail for your reference - we cannot retrieve the password for you.","unescaped");
                $message.="\n\n".$thissurvey['name']."\n\n";
                $message.=$clang->gT("Name","unescaped").": ".$_POST['savename']."\n";
                $message.=$clang->gT("Password","unescaped").": ".$_POST['savepass']."\n\n";
                $message.=$clang->gT("Reload your survey by clicking on the following link (or pasting it into your browser):","unescaped").":\n";
                $message.=$publicurl."/index.php?sid=$surveyid&loadall=reload&scid=".$scid."&loadname=".urlencode($_POST['savename'])."&loadpass=".urlencode($_POST['savepass']);

                if ($clienttoken){$message.="&token=".$clienttoken;}
                $from="{$thissurvey['adminname']} <{$thissurvey['adminemail']}>";
                if (SendEmailMessage($message, $subject, $_POST['saveemail'], $from, $sitename, false, getBounceEmail($surveyid)))
                {
                    $emailsent="Y";
                }
                else
                {
                    echo $clang->gT('Error: Email failed, this may indicate a PHP Mail Setup problem on the server. Your survey details have still been saved, however you will not get an email with the details. You should note the "name" and "password" you just used for future reference.');
                }
            }
            return  $clang->gT('Your survey was successfully saved.');
        }
    }

    /**
    * savesilent() saves survey responses when the "Resume later" button
    * is press but has no interaction. i.e. it does not ask for email,
    * username or password or capture.
    *
    * @return string confirming successful save.
    */
    function savedsilent()
    {
        global $surveyid, $thissurvey, $errormsg, $publicurl, $sitename, $timeadjust, $clang, $clienttoken, $thisstep;
        submitanswer();
        // Prepare email
        $tokenentryquery = 'SELECT * from {{tokens_'.$surveyid.'}} WHERE token=\''.sanitize_paranoid_string($clienttoken).'\';';
        $tokenentryresult = dbExecuteAssoc($tokenentryquery);
        $tokenentryarray = $tokenentryresult->read();

        $from = $thissurvey['adminname'].' <'.$thissurvey['adminemail'].'>';
        $to = $tokenentryarray['firstname'].' '.$tokenentryarray['lastname'].' <'.$tokenentryarray['email'].'>';
        $subject = $clang->gT("Saved Survey Details") . " - " . $thissurvey['name'];
        $message = $clang->gT("Thank you for saving your survey in progress. You can return to the survey at the same point you saved it at any time using the link from this or any previous email sent to regarding this survey.","unescaped")."\n\n";
        $message .= $clang->gT("Reload your survey by clicking on the following link (or pasting it into your browser):","unescaped").":\n";
        $language = $tokenentryarray['language'];


        $message .= "\n\n$publicurl/$surveyid/lang-$language/tk-$clienttoken";


        if (SendEmailMessage($message, $subject, $to, $from, $sitename, false, getBounceEmail($surveyid)))
        {
            $emailsent="Y";
        }
        else
        {
            echo "Error: Email failed, this may indicate a PHP Mail Setup problem on your server. Your survey details have still been saved, however you will not get an email with the details. You should note the \"name\" and \"password\" you just used for future reference.";
        };
        return  $clang->gT('Your survey was successfully saved.');
    }

    /**
    * This functions saves the answer time for question/group and whole survey.
    * [ It compares current time with the time in $_POST['start_time'] ]
    * The times are saved in table: {prefix}{surveytable}_timings
    * @return void
    */
    function set_answer_time()
    {
        global $thissurvey;
        if (!isset($_POST['start_time']))
        {
            return; // means haven't passed welcome page yet.
        }

        if (isset($_POST['lastanswer']))
        {
            $setField = $_POST['lastanswer'];
        }
        elseif (isset($_POST['lastgroup']))
        {
            $setField = $_POST['lastgroup'];
        }
        $passedTime = round(microtime(true) - $_POST['start_time'],2);
        if(!isset($setField)){ //we show the whole survey on one page - we don't have to save time for group/question
            $query = "UPDATE {{survey_{$thissurvey['sid']}_timings}} SET "
            ."interviewtime = IFNULL(interviewtime, 0 ) + " .$passedTime
            ." WHERE id = " .$_SESSION['survey_'.$thissurvey['sid']]['srid'];

        }
        else
        {
            $setField .= "time";
            $query = "UPDATE {{survey_{$thissurvey['sid']}_timings}} SET "
            ."interviewtime =  IFNULL(interviewtime, 0 ) + " .$passedTime .","
            .$setField." =  IFNULL(".$setField.", 0 ) + ".$passedTime
            ." WHERE id = " .$_SESSION['survey_'.$thissurvey['sid']]['srid'];
        }
        Yii::app()->db->createCommand($query)->execute();
    }
}
