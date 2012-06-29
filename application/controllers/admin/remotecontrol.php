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
 * 	$Id$
 */

class remotecontrol extends Survey_Common_Action
{
    /**
     * @var Zend_XmlRpc_Server
     */
    protected $xmlrpc;

    /**
     * This is the XML-RPC server routine
     *
     * @access public
     * @return void
     */
    public function run()
    {
        $cur_path = get_include_path();

        set_include_path($cur_path . ':' . APPPATH . 'helpers');

        // Yii::import was causing problems for some odd reason
        require_once('Zend/XmlRpc/Server.php');
        require_once('Zend/XmlRpc/Server/Exception.php');
        require_once('Zend/XmlRpc/Value/Exception.php');

        $this->xmlrpc = new Zend_XmlRpc_Server();
        $this->xmlrpc->sendArgumentsToAllMethods(false);
        $this->xmlrpc->setClass('remotecontrol_handle', '', $this->controller);
        echo $this->xmlrpc->handle();
        exit;
    }

    /**
     * Couldn't include test routine as it'd require a couple more Zend libraries
     * Instead use PHP XMLRPC Debugger with the following payloads to test routines
     * Adjusts some values accordingly
     *
     * get_session_key : Use this to obtain session_key for other call
          <param>
              <value><string>username</string></value>
          </param>
          <param>
              <value><string>password</string></value>
          </param>
     *
     * add_participants
          <param>
              <value><string>session_key</string></value>
          </param>
          <!-- Survey id -->
          <param>
              <value><i4>552489</i4></value>
          </param>
          <!-- Participants information -->
          <param>
              <value><array><data><value><struct><member><name>firstname</name><value><string>firstname1</string></value></member><member><name>lastname</name><value><string>lastname1</string></value></member><member><name>dummy</name><value><string>lastname1</string></value></member></struct></value></data></array></value>
          </param>
     *
     * delete_survey
          <param>
              <value><string>session_key</string></value>
          </param>
          <!-- Survey id --.
          <param>
              <value><i4>78184</i4></value>
          </param>
     */
}

class remotecontrol_handle
{
    /**
     * @var AdminController
     */
    protected $controller;

    /**
     * Constructor, stores the action instance into this handle class
     *
     * @access public
     * @param AdminController $controller
     * @return void
     */
    public function __construct(AdminController $controller)
    {
        $this->controller = $controller;
    }

    /**
     * XML-RPC routine to create a session key
     *
     * @access public
     * @param string $username
     * @param string $password
     * @return string
     * @throws Zend_XmlRpc_Server_Exception
     */
    public function get_session_key($username, $password)
    {
        if ($this->_doLogin($username, $password))
        {
            $this->_jumpStartSession($username);
            $session_key = randomChars(32);

            $session = new Sessions;
            $session->id = $session_key;
            $session->expire = time() + Yii::app()->getConfig('iSessionExpirationTime');
            $session->data = $username;
            $session->save();

            return $session_key;
        }
        else
            throw new Zend_XmlRpc_Server_Exception('Login failed', 1);
    }

    /**
     * Closes the RPC session
     *
     * @access public
     * @param string $session_key
     * @return string
     */
    public function release_session_key($session_key)
    {
        Sessions::model()->deleteAllByAttributes(array('id' => $session_key));
        $criteria = new CDbCriteria;
        $criteria->condition = 'expire < ' . time();
        Sessions::model()->deleteAll($criteria);
        return 'OK';
    }

    /**
     * XML-RPC routine to get settings
     *
     * @access public
     * @param string $session_key
     * @param string $settting_name
     * @return string
     */
   public function get_site_settings($session_key,$setting_name)
    {
       if ($this->_checkSessionKey($session_key))
       {
		   if( Yii::app()->session['USER_RIGHT_SUPERADMIN'] == 1)
		   {     
			   if (Yii::app()->getRegistry($setting_name) !== false)
					return Yii::app()->getRegistry($setting_name);
				elseif (Yii::app()->getConfig($setting_name) !== false)
					return Yii::app()->getConfig($setting_name);
				else
					throw new Zend_XmlRpc_Server_Exception('Invalid setting', 20);	
			}
			else
				throw new Zend_XmlRpc_Server_Exception('No permission', 2); 	
        }
    } 

    /**
     * XML-RPC routine to get survey properties
     * Properties are those defined in tables surveys and surveys_language_settings
     *
     * @access public
     * @param string $session_key
     * @param int $sid
     * @param string $sproperty_name
	 * @param string $slang
     * @return string
     */
   public function get_survey_properties($session_key,$sid, $sproperty_name, $slang='')
    {
       if ($this->_checkSessionKey($session_key))
       { 
		$surveyidExists = Survey::model()->findByPk($sid);		   
		if (!isset($surveyidExists))
		{
			throw new Zend_XmlRpc_Server_Exception('Invalid surveyid', 22);
			exit;
		}		   
		if (hasSurveyPermission($sid, 'survey', 'read'))
            {
                $abasic_attrs = Survey::model()->findByPk($sid)->getAttributes();
                if ($slang == '')
					$slang = $abasic_attrs['language'];
				$alang_attrs = Surveys_languagesettings::model()->findByAttributes(array('surveyls_survey_id' => $sid, 'surveyls_language' => $slang))->getAttributes();	
				
				if (isset($abasic_attrs[$sproperty_name]))
					return $abasic_attrs[$sproperty_name];
				elseif (isset($alang_attrs[$sproperty_name]))
					return $alang_attrs[$sproperty_name];
				else
					throw new Zend_XmlRpc_Server_Exception('Data not available', 23);
            }
        else
			throw new Zend_XmlRpc_Server_Exception('No permission', 2);  
        }
    } 

    /**
     * XML-RPC routine to set survey properties
     * Properties are those defined in tables surveys and surveys_language_settings
     * In case survey is activated, certain properties are not allowed to change
     * 
     * @access public
     * @param string $session_key
     * @param int $sid
     * @param string $sproperty_name
     * @param string $sproperty_value
	 * @param string $slang
     * @return array
     */
   public function set_survey_properties($session_key,$sid, $sproperty_name, $sproperty_value, $slang='')
    {
       if ($this->_checkSessionKey($session_key))
       { 
		$surveyidExists = Survey::model()->findByPk($sid);
		if (!isset($surveyidExists))
		{
			throw new Zend_XmlRpc_Server_Exception('Invalid surveyid', 22);
			exit;
		}		   
		if (hasSurveyPermission($sid, 'survey', 'update'))
            {
				$valid_value = $this->_internal_validate($sproperty_name, $sproperty_value);
				
				if (!$valid_value)
				{
					throw new Zend_XmlRpc_Server_Exception('Update values are not valid', 24);
					exit;
				}
					
				$ocurrent_Survey = Survey::model()->findByPk($sid);				
                $abasic_attrs = $ocurrent_Survey->getAttributes();

                if ($slang == '')
					$slang = $abasic_attrs['language'];
					
				$ocurrent_Survey_languagesettings = Surveys_languagesettings::model()->findByAttributes(array('surveyls_survey_id' => $sid, 'surveyls_language' => $slang));		
				$alang_attrs = $ocurrent_Survey_languagesettings->getAttributes();

				$active = $abasic_attrs['active'];	
				$adissallowed = array('language', 'additional_languages', 'attributedescriptions', 'surveyls_survey_id', 'surveyls_language');					
				if ($active == 'Y')
					array_push($adissallowed, 'active', 'anonymized', 'savetimings', 'datestamp', 'ipaddr','refurl');
					
				if (!in_array($sproperty_name, $adissallowed))
				{
					if (array_key_exists($sproperty_name, $abasic_attrs))
					{
						$ocurrent_Survey->setAttribute($sproperty_name,$valid_value);
						return $ocurrent_Survey->save();
					}
					elseif (array_key_exists($sproperty_name, $alang_attrs))
					{
						$ocurrent_Survey_languagesettings->setAttribute($sproperty_name,$valid_value);
						return $ocurrent_Survey_languagesettings->save();	
					}
					else
						throw new Zend_XmlRpc_Server_Exception('No such property', 25);						
				}
				else
					throw new Zend_XmlRpc_Server_Exception('Property not editable', 26);	
            }
        else
			throw new Zend_XmlRpc_Server_Exception('No permission', 2); 
			
        }
    } 

    /**
     * XML-RPC routine to get survey summary, regarding token usage and survey participation
     * Return integer with the requested value
     * @access public
     * @param string $session_key
     * @param int $sid
     * @param string $stats_name
     * @return string
     */
   public function get_survey_summary($session_key,$sid, $stat_name)
    {
       $permitted_stats = array();
       if ($this->_checkSessionKey($session_key))
       { 	  
		$permitted_token_stats = array('token_count', 
								'token_invalid', 
								'token_sent', 
								'token_opted_out',
								'token_completed'
								);					
		$permitted_survey_stats  = array('completed_responses',  
								'incomplete_responses', 
								'full_responses' 
								);  
								
		if (tableExists('{{tokens_' . $sid . '}}'))
		{
			$summary = Tokens_dynamic::model($sid)->summary();
			$permitted_stats = array_merge($permitted_stats, $permitted_token_stats);
		}
		
		if (tableExists('{{survey_' . $sid . '}}'))
		{
			$permitted_stats = array_merge($permitted_stats, $permitted_survey_stats);
		}		
											
		if (!in_array($stat_name, $permitted_stats)) 
		{
			throw new Zend_XmlRpc_Server_Exception('Data not available', 23);
			exit;
		}	

		switch($stat_name) 
		{
			case 'token_count':
				if (isset($summary))
					return $summary['tkcount'];
				break;
			case 'token_invalid':
				if (isset($summary))
					return $summary['tkinvalid'];
				break;	
			case 'token_sent':
				if (isset($summary))
					return $summary['tksent'];
				break;
			case 'token_opted_out':
				if (isset($summary))
					return $summary['tkoptout'];
				break;
			case 'token_completed';
				if (isset($summary))
					return $summary['tkcompleted'];
				break;
			case 'completed_responses':
				return Survey_dynamic::model($sid)->count('submitdate IS NOT NULL');
				break;
			case 'incomplete_responses':
				return Survey_dynamic::model($sid)->countByAttributes(array('submitdate' => null));
				break;
			case 'full_responses';
				return Survey_dynamic::model($sid)->count();
				break;			
			default:
				throw new Zend_XmlRpc_Server_Exception('Data is not available', 23);
		}
        }
    } 

   /**
     * XML-RPC routine to import a survey
     *
     * @access public
     * @param string $session_key
     * @param int $sid
	 * @param string $sSurveyfile
     * @return string
     * @throws Zend_XmlRpc_Server_Exception
     */
	public function import_survey_file($session_key, $sid, $sSurveyfile)   
	{
		Yii::app()->loadHelper('admin/import');
		if ($this->_checkSessionKey($session_key))
        {  
			$surveyidExists = Survey::model()->findByPk($sid);
			if (isset($surveyidExists))
			{
                throw new Zend_XmlRpc_Server_Exception('Survey already exists', 27);
				exit;
			}  
			//Assuming that surveys should be in the upload/surveys directory
			$sFullFilepath = Yii::app()->getConfig('uploaddir').'/surveys/'.$sSurveyfile;
			if ($sSurveyfile!='' && file_exists($sFullFilepath))
			{	
				$aPathInfo = pathinfo($sFullFilepath);
				$sExtension = $aPathInfo['extension'];

				if (isset($sExtension) && strtolower($sExtension)=='csv')
				{
					$aImportResults=CSVImportSurvey($sFullFilepath,$sid);
				}
				elseif (isset($sExtension) && strtolower($sExtension)=='lss')
				{
					$aImportResults=XMLImportSurvey($sFullFilepath,NULL,NULL,$sid);
				}
				else
					throw new Zend_XmlRpc_Server_Exception('Invalid input', 21);
							
				if($aImportResults['error']!=NULL)
					throw new Zend_XmlRpc_Server_Exception($aImportResults['error'], 29);

				if($aImportResults['newsid']==NULL )
				{
					throw new Zend_XmlRpc_Server_Exception('Import failed', 29);
					exit;
				}
				else
				{
					$iNewSid = $aImportResults['newsid'];
					Survey::model()->updateByPk($iNewSid, array('datecreated'=> date("Y-m-d")));
					return $iNewSid;
				}			
			}
			else
			{
				throw new Zend_XmlRpc_Server_Exception('Survey file does not exist (in server)', 21);
				exit;					
			}
        }		
	} 

   /**
     * XML-RPC routine to import a survey from xmlstream
     *
     * @access public
     * @param string $session_key
     * @param int $sid
	 * @param string $sXMLdata
     * @return string
     * @throws Zend_XmlRpc_Server_Exception
     */    
	public function import_survey_xmldata($session_key, $sid, $sXMLdata)   
	{
		Yii::app()->loadHelper('admin/import');
		if ($this->_checkSessionKey($session_key))
        {  
			$surveyidExists = Survey::model()->findByPk($sid);
			if (isset($surveyidExists))
			{
                throw new Zend_XmlRpc_Server_Exception('Survey already exists', 27);
				exit;
			}  
			
			if ($sXMLdata!='')
			{	
				$sXMLdata=htmlspecialchars_decode($sXMLdata);
				$aImportResults=XMLImportSurvey(NULL,$sXMLdata,NULL,$sid);
				
				if(array_key_exists('error',$aImportResults))
					throw new Zend_XmlRpc_Server_Exception($aImportResults['error'], 29);					
				
				if($aImportResults['newsid']==NULL )
				{
					throw new Zend_XmlRpc_Server_Exception('Import failed', 29);
					exit;
				}
				else
				{
					$iNewSid = $aImportResults['newsid'];
					Survey::model()->updateByPk($iNewSid, array('datecreated'=> date("Y-m-d")));
					return $iNewSid;
				}		
			}
			else
			{
				throw new Zend_XmlRpc_Server_Exception('Insufficient input', 21);
				exit;					
			}
						
        }		
	} 


    /**
     * XML-RPC routine to create an empty survey with minimum details
     * Used as a placeholder for importing groups and/or questions
     *
     * @access public
     * @param string $session_key
     * @param int $sid
	 * @param string $sSurveyTitle
	 * @param string $sSurveyLanguage	 
	 * @param string $sformat
     * @return string
     * @throws Zend_XmlRpc_Server_Exception
     */
	public function create_survey($session_key, $sid, $sSurveyTitle, $sSurveyLanguage, $sformat = 'G')
	{
		Yii::app()->loadHelper("surveytranslator");
		if ($this->_checkSessionKey($session_key))
        {
			if (Yii::app()->session['USER_RIGHT_CREATE_SURVEY'])
			{	
				if( $sSurveyTitle=='' || $sSurveyLanguage=='' || !array_key_exists($sSurveyLanguage,getLanguageDataRestricted()) || !in_array($sformat, array('A','G','S')))
				{
					throw new Zend_XmlRpc_Server_Exception('Faulty parameters', 21);
					exit;
				}   

            $aInsertData = array(
            'template' => 'default',
            'owner_id' => Yii::app()->session['loginID'],
            'active' => 'N',
            'language'=>$sSurveyLanguage,
            'format' => $sformat
            );

            if(Yii::app()->getConfig('filterxsshtml') && Yii::app()->session['USER_RIGHT_SUPERADMIN'] != 1)
                $xssfilter = true;
            else
                $xssfilter = false;


            if (!is_null($sid))
            {
					$aInsertData['wishSID'] = $sid;
            }

			$iNewSurveyid = Survey::model()->insertNewSurvey($aInsertData, $xssfilter);
			if (!$iNewSurveyid)
			{
				throw new Zend_XmlRpc_Server_Exception('Creation failed', 28);
				exit;				
			}
           
			$sTitle = html_entity_decode($sSurveyTitle, ENT_QUOTES, "UTF-8");

			// Load default email templates for the chosen language
			$oLanguage = new Limesurvey_lang($sSurveyLanguage);
			$aDefaultTexts = templateDefaultTexts($oLanguage, 'unescaped');
			unset($oLanguage);
			
			$bIsHTMLEmail = false;
			
            $aInsertData = array('surveyls_survey_id' => $iNewSurveyid,
            'surveyls_title' => $sTitle,
            'surveyls_language' => $sSurveyLanguage,          
            'surveyls_email_invite_subj' => $aDefaultTexts['invitation_subject'],
            'surveyls_email_invite' => conditionalNewlineToBreak($aDefaultTexts['invitation'], $bIsHTMLEmail, 'unescaped'),
            'surveyls_email_remind_subj' => $aDefaultTexts['reminder_subject'],
            'surveyls_email_remind' => conditionalNewlineToBreak($aDefaultTexts['reminder'], $bIsHTMLEmail, 'unescaped'),
            'surveyls_email_confirm_subj' => $aDefaultTexts['confirmation_subject'],
            'surveyls_email_confirm' => conditionalNewlineToBreak($aDefaultTexts['confirmation'], $bIsHTMLEmail, 'unescaped'),
            'surveyls_email_register_subj' => $aDefaultTexts['registration_subject'],
            'surveyls_email_register' => conditionalNewlineToBreak($aDefaultTexts['registration'], $bIsHTMLEmail, 'unescaped'),
            'email_admin_notification_subj' => $aDefaultTexts['admin_notification_subject'],
            'email_admin_notification' => conditionalNewlineToBreak($aDefaultTexts['admin_notification'], $bIsHTMLEmail, 'unescaped'),
            'email_admin_responses_subj' => $aDefaultTexts['admin_detailed_notification_subject'],
            'email_admin_responses' => $aDefaultTexts['admin_detailed_notification']
            );
            
            $langsettings = new Surveys_languagesettings;
            $langsettings->insertNewSurvey($aInsertData, $xssfilter);
            Survey_permissions::model()->giveAllSurveyPermissions(Yii::app()->session['loginID'], $iNewSurveyid);

			return 	$iNewSurveyid;				
			}				
		}			
	}
  
    /**
     * XML-RPC routine to import a group into a survey
     *
     * @access public
     * @param string $session_key
     * @param int $sid
	 * @param string $sGroupfile
	 * @param string $sName	 
	 * @param string $sDesc
     * @return string
     * @throws Zend_XmlRpc_Server_Exception
     */
	public function import_group($session_key, $sid, $sGroupfile, $sName='', $sDesc='')
	{
		libxml_use_internal_errors(true);
		Yii::app()->loadHelper('admin/import');
		if ($this->_checkSessionKey($session_key))
        { 
			$surveyidExists = Survey::model()->findByPk($sid);
			if (!isset($surveyidExists))
				throw new Zend_XmlRpc_Server_Exception('Invalid Survey id', 22);
						 
			$sFullFilepath = Yii::app()->getConfig('uploaddir').'/surveys/'.$sGroupfile;
			if(!file_exists($sFullFilepath) || $sGroupfile=='' )
					throw new Zend_XmlRpc_Server_Exception('File does not exist', 21);
		
			if (hasSurveyPermission($sid, 'survey', 'update'))
            {
				$aPathInfo = pathinfo($sFullFilepath);
				$sExtension = $aPathInfo['extension'];

				if (isset($sExtension) && strtolower($sExtension)=='csv')
				{
					$checkImport = CSVImportGroup($sFullFilepath, $sid);
				}
				elseif (isset($sExtension) && strtolower($sExtension)=='lsg')
				{
					$xml = simplexml_load_file($sFullFilepath);
					if(!$xml)
						throw new Zend_XmlRpc_Server_Exception('This is not a valid LimeSurvey group structure XML file', 21);
				
					$checkImport = XMLImportGroup($sFullFilepath, $sid);
				}
				else
					throw new Zend_XmlRpc_Server_Exception('Invalid input file', 21);
					
				if(array_key_exists('fatalerror',$checkImport))
					throw new Zend_XmlRpc_Server_Exception($checkImport['fatalerror'], 29);					
				
					
				$iNewSid = $checkImport['newgid'];	
				
				$group = Groups::model()->findByAttributes(array('gid' => $iNewSid));
				$slang=$group['language'];
				if($sName!='')
				$group->setAttribute('group_name',$sName);
				if($sDesc!='')
				$group->setAttribute('description',$sDesc);
				$group->save();
				
				return "Import OK ".$iNewSid;
			}
			else
				throw new Zend_XmlRpc_Server_Exception('No permission', 2);	
        }		
	}      

    /**
     * XML-RPC routine to activate a survey
     *
     * @access public
     * @param string $session_key
     * @param int $sid
     * @param string dStart
     * @param string dEnd
     * @return string|bool
     * @throws Zend_XmlRpc_Server_Exception
     */
    public function activate_survey($session_key, $sid, $dStart='', $dEnd='')
    {
		Yii::app()->loadHelper('admin/activate');
		
		if ($this->_checkSessionKey($session_key))
        {
			$surveyidExists = Survey::model()->findByPk($sid);
			if (!isset($surveyidExists))
			{
				throw new Zend_XmlRpc_Server_Exception('Invalid surveyid', 22);
				exit;
			}				
            if (hasSurveyPermission($sid, 'survey', 'update'))
            {
				//Start and end dates are updated regardless the survey's status
				if($dStart!='' && substr($dStart,0,10)!='1980-01-01' && $this->_internal_validate('startdate', $dStart) )
				{
					Survey::model()->updateByPk($sid, array('startdate'=> $dStart));
				}
				if($dEnd!='' && substr($dEnd,0,10)!='1980-01-01' && $this->_internal_validate('expires', $dEnd))
				{
					Survey::model()->updateByPk($sid, array('expires'=> $dEnd));
				}	
				
				$survey_attributes = Survey::model()->findByPk($sid)->getAttributes();
				if ($survey_attributes['active'] == 'N')
				{
					$activateResult = activateSurvey($sid);
					if ($activateResult==false)
						throw new Zend_XmlRpc_Server_Exception('Activation went wrong', 32);	
				}
				else 
					throw new Zend_XmlRpc_Server_Exception('Survey is active', 31);
					
				return $sid;
							
            }
            else
                throw new Zend_XmlRpc_Server_Exception('No permission', 2);			
		}	
	}


    /**
     * XML-RPC routine to delete a survey
     *
     * @access public
     * @param string $session_key
     * @param int $sid
     * @return string
     * @throws Zend_XmlRpc_Server_Exception
     */
    public function delete_survey($session_key, $sid)
    {
        if ($this->_checkSessionKey($session_key))
        {
            if (hasSurveyPermission($sid, 'survey', 'delete'))
            {
                Survey::model()->deleteAllByAttributes(array('sid' => $sid));
                rmdirr(Yii::app()->getConfig("uploaddir") . '/surveys/' . $sid);
                return array('status' => 'OK');
            }
            else
                throw new Zend_XmlRpc_Server_Exception('No permission', 2);
        }
    }

    /**
     * XML-RPC routing to add a response to the survey table
     * Returns the id of the inserted survey response
     *
     * @access public
     * @param string $session_key
     * @param int $sid
     * @param struct $aResponseData
     * @return int
     * @throws Zend_XmlRpc_Server_Exception
     */
    public function add_response($session_key, $sid, $aResponseData)
    {
        if ($this->_checkSessionKey($session_key))
        {
            if (hasSurveyPermission($sid, 'response', 'create'))
            {
                if (!Yii::app()->db->schema->getTable('{{survey_' . $sid . '}}'))
                    throw new Zend_XmlRpc_Server_Exception('No survey response table', 12);

                //set required values if not set
                if (!isset($aResponseData['submitdate']))
                    $aResponseData['submitdate'] = date("Y-m-d H:i:s");
                if (!isset($aResponseData['datestamp']))
                    $aResponseData['datestamp'] = date("Y-m-d H:i:s");
                if (!isset($aResponseData['startdate']))
                    $aResponseData['startdate'] = date("Y-m-d H:i:s");
                if (!isset($aResponseData['startlanguage']))
                    $aResponseData['startlanguage'] = getBaseLanguageFromSurveyID($iSurveyID);

                Survey_dynamic::sid($sid);
                $survey_dynamic = new Survey_dynamic;
                $result = $survey_dynamic->insert($aResponseData);

                if ($result)
                    return $survey_dynamic->primaryKey;
                else
                    throw new Zend_XmlRpc_Server_Exception('Unable to add survey', 13);
            }
            else
                throw new Zend_XmlRpc_Server_Exception('No permission', 2);
        }
    }

    /**
     * XML-RPC routing to to return unused Tokens as String.
     * Returns the unused tokens in an Array.
     *
     * @access public
     * @param string $session_key
     * @param int $sid
     * @return array
     * @throws Zend_XmlRpc_Server_Exception
     */
	public function token_return($session_key, $sid)
	{
		
        if ($this->_checkSessionKey($session_key))
        {
			if(!isset($sid) || $sid=='' || $sid==0 )
			{
                throw new Zend_XmlRpc_Server_Exception('Faulty parameters', 21);
                exit;
			}

			$surveyidExists = Survey::model()->findByPk($sid);
			if (!isset($surveyidExists))
			{
				throw new Zend_XmlRpc_Server_Exception('Invalid surveyid', 22);
				exit;
			}		
				
			if(!tableExists("{{tokens_$sid}}"))
			{
				throw new Zend_XmlRpc_Server_Exception('No token table', 11);
				exit;
			}

			$tokens = Tokens_dynamic::model($sid)->findAll("completed = 'N'");
			if(count($tokens)==0)
				throw new Zend_XmlRpc_Server_Exception('No unused Tokens found', 30);
			
			foreach ($tokens as $token)
				{
					$aData[] = $token->attributes['token'];
				}
			return $aData;
        }			
	}
	

    /**
     * XML-RPC routine to add a participant to a token table
     * Returns the inserted data including additional new information like the Token entry ID and the token
     *
     * @access public
     * @param string $session_key
     * @param int $sid
     * @param struct $participant_data
     * @param bool $create_token
     * @return array
     * @throws Zend_XmlRpc_Server_Exception
     */
    public function add_participants($session_key, $sid, $participant_data, $create_token)
    {
        if ($this->_checkSessionKey($session_key))
        {
            if (hasSurveyPermission($sid, 'tokens', 'create'))
            {
                if (!Yii::app()->db->schema->getTable('{{tokens_' . $sid . '}}'))
                    throw new Zend_XmlRpc_Server_Exception('No token table', 11);

                $field_names = Yii::app()->db->schema->getTable('{{tokens_' . $sid . '}}')->getColumnNames();
                $field_names = array_flip($field_names);

                foreach ($participant_data as &$participant)
                {
                    foreach ($participant as $field_name => $value)
                        if (!isset($field_names[$field_name]))
                            unset($participant[$field_name]);

                    Tokens_dynamic::sid($sid);
                    $token = new Tokens_dynamic;

                    if ($token->insert($participant))
                    {
                        $new_token_id = $token->primaryKey;

                        if ($create_token)
                            $token_string = Tokens_dynamic::model()->createToken($new_token_id);
                        else
                            $token_string = '';

                        $participant = array_merge($participant, array(
                            'tid' => $new_token_id,
                            'token' => $token_string,
                        ));
                    }
                }

                return $participant_data;
            }
            else
                throw new Zend_XmlRpc_Server_Exception('No permission', 2);
        }
    }

    /**
     * Tries to login with username and password
     *
     * @access protected
     * @param string $sUsername
     * @param mixed $sPassword
     * @return bool
     */
    protected function _doLogin($sUsername, $sPassword)
    {
        if (Failed_login_attempts::model()->isLockedOut())
            return false;

        $identity = new UserIdentity(sanitize_user($sUsername), $sPassword);

        if (!$identity->authenticate())
        {
            Failed_login_attempts::model()->addAttempt();
            return false;
        }
        else
            return true;
    }

    /**
     * Fills the session with necessary user info on the fly
     *
     * @access protected
     * @param string $sUsername
     * @return bool
     */
    protected function _jumpStartSession($username)
    {
        $aUserData = User::model()->findByAttributes(array('users_name' => $username))->attributes;

        $session = array(
            'loginID' => intval($aUserData['uid']),
            'user' => $aUserData['users_name'],
            'full_name' => $aUserData['full_name'],
            'htmleditormode' => $aUserData['htmleditormode'],
            'templateeditormode' => $aUserData['templateeditormode'],
            'questionselectormode' => $aUserData['questionselectormode'],
            'dateformat' => $aUserData['dateformat'],
            'adminlang' => 'en'
        );
        foreach ($session as $k => $v)
            Yii::app()->session[$k] = $v;
        Yii::app()->user->setId($aUserData['uid']);

        $this->controller->_GetSessionUserRights($aUserData['uid']);
        return true;
    }

    /**
     * This function checks if the XML-RPC session key is valid. If yes returns true, otherwise false and sends an error message with error code 1
     *
     * @access protected
     * @param string $session_key
     * @return bool
     * @throws Zend_XmlRpc_Server_Exception
     */
    protected function _checkSessionKey($session_key)
    {
        $criteria = new CDbCriteria;
        $criteria->condition = 'expire < ' . time();
        Sessions::model()->deleteAll($criteria);
        $oResult = Sessions::model()->findByPk($session_key);

        if (is_null($oResult))
            throw new Zend_XmlRpc_Server_Exception('Invalid session key', 3);
        else
        {
            $this->_jumpStartSession($oResult->data);
            return true;
        }
    }
    
    /**
     * This function validates parameters to be inserted in survey model
     *
     * @access protected
     * @param string $sparam_name
     * @param string $sparam_value
     * @return bool|string
     * @throws Zend_XmlRpc_Server_Exception
     */
    protected function _internal_validate($sparam_name, $sparam_value)
    {   	
		$date_pattern = '/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/';
		$validation_categories = array(
								'active'=>'char',
								'anonymized'=>'char',
								'savetimings'=>'char',
								'datestamp'=>'char',
								'ipaddr'=>'char',
								'refurl'=>'char',
								'usecookie'=>'char',
								'allowregister'=>'char',
								'allowsave'=>'char',
								'autoredirect'=>'char',
								'allowprev'=>'char',
								'printanswers'=>'char',
								'publicstatistics'=>'char',
								'publicgraphs'=>'char',
								'listpublic'=>'char',
								'htmlemail'=>'char',
								'sendconfirmation'=>'char',
								'tokenanswerspersistence'=>'char',
								'assessments'=>'char',
								'usecaptcha'=>'char',
								'usetokens'=>'char',
								'showxquestions'=>'char',
								'showgroupinfo'=>'char',
								'shownoanswer'=>'char',
								'showqnumcode'=>'char',
								'showwelcome'=>'char',
								'showprogress'=>'char',
								'allowjumps'=>'char',
								'nokeyboard'=>'char',
								'alloweditaftercompletion'=>'char',
								'googleanalyticsstyle'=>'char',
								'bounceprocessing'=>'char',
								'autonumber_start'=>'int',
								'tokenlength'=>'int',
								'bouncetime'=>'int',
								'navigationdelay'=>'int',
								'expires'=>'date',
								'startdate'=>'date',
								'datecreated'=>'date',
								'adminemail'=>'email',
								'bounce_email'=>'email',
								'surveyls_dateformat'=>'dateformat',
								'surveyls_numberformat'=>'numberformat',
								'template'=>'tmpl',
								'format'=>'gsa_format'
								);
		
		if (array_key_exists($sparam_name, $validation_categories))
		{
			switch($validation_categories[$sparam_name])
			{
			case 'char':
				if(in_array($sparam_value, array('Y','N')))
					return $sparam_value;
				else
					return false;
				break;
			
			case 'int':
				return filter_var($sparam_value, FILTER_VALIDATE_INT, array("options" => array("min_range"=>1, "max_range"=>999999999)));
				break;
			
			case 'date':
				return filter_var($sparam_value, FILTER_VALIDATE_REGEXP,array("options"=>array("regexp"=>$date_pattern)));
				break;

			case 'email':
				return filter_var($sparam_value, FILTER_VALIDATE_EMAIL);
				break;
							
			case 'dateformat':
				return filter_var($sparam_value, FILTER_VALIDATE_INT, array("options" => array("min_range"=>1, "max_range"=>12)));
				break;
				
			case 'numberformat':
				return filter_var($sparam_value, FILTER_VALIDATE_INT, array("options" => array("min_range"=>0, "max_range"=>1)));
				break;
			case 'tmpl':
				if(array_key_exists($sparam_value,getTemplateList()))
					return $sparam_value;
				else
					return false;
				break;
			case 'gsa_format':
				if(in_array($sparam_value, array('G','S','A')))
					return $sparam_value;
				else
					return false;
				break;							
			default:
				return $sparam_value;
	
			}

		}
		else
			return $sparam_value;
	}
    
}
