<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');
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

/**
* templates
*
* @package LimeSurvey
* @author
* @copyright 2011
* @version $Id$
*/
class templates extends Survey_Common_Action
{

    /**
    * Exports a template
    *
    * @access public
    * @param string $templatename
    * @return void
    */
    public function templatezip($templatename)
    {
        Yii::import('application.libraries.admin.Phpzip', true);
        $zip = new PHPZip();
        $templatedir = getTemplatePath($templatename) . DIRECTORY_SEPARATOR;
        $tempdir = Yii::app()->getConfig('tempdir');

        $zipfile = "$tempdir/$templatename.zip";
        $zip->Zip($templatedir, $zipfile);

        if (is_file($zipfile)) {
            // Send the file for download!
            header("Pragma: public");
            header("Expires: 0");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

            header("Content-Type: application/force-download");
            header("Content-Disposition: attachment; filename=$templatename.zip");
            header("Content-Description: File Transfer");

            @readfile($zipfile);

            // Delete the temporary file
            unlink($zipfile);
        }
    }

    /**
    * Responsible to import a template archive.
    *
    * @access public
    * @return void
    */
    public function upload()
    {
        $clang = $this->getController()->lang;

//        $this->getController()->_js_admin_includes(Yii::app()->baseUrl . '/scripts/admin/templates.js');

        $aViewUrls = $this->_initialise('default', 'welcome', 'startpage.pstpl', FALSE);
        $lid = returnGlobal('lid');
        $action = returnGlobal('action');

        if ($action == 'templateupload') {
            if (Yii::app()->getConfig('demoMode'))
                $this->getController()->error($clang->gT("Demo mode: Uploading templates is disabled."));

            Yii::import('application.libraries.admin.Phpzip', true);

            $zipfile = $_FILES['the_file']['tmp_name'];
            $zip = new PHPZip();

            // Create temporary directory so that if dangerous content is unzipped it would be unaccessible
            $extractdir = self::_tempdir(Yii::app()->getConfig('tempdir'));
            $basedestdir = Yii::app()->getConfig('usertemplaterootdir');
            $newdir = str_replace('.', '', self::_strip_ext(sanitize_paranoid_string($_FILES['the_file']['name'])));
            $destdir = $basedestdir . '/' . $newdir . '/';

            if (!is_writeable($basedestdir))
                $this->getController()->error(sprintf($clang->gT("Incorrect permissions in your %s folder."), $basedestdir));

            if (!is_dir($destdir))
                mkdir($destdir);
            else
                $this->getController()->error(sprintf($clang->gT("Template '%s' does already exist."), $newdir));

            $aImportedFilesInfo = array();
            $aErrorFilesInfo = array();

            if (is_file($zipfile)) {
                if ($zip->extract($extractdir, $zipfile) != 'OK')
                    $this->getController()->error($clang->gT("This file is not a valid ZIP file archive. Import failed."));

                // Now read tempdir and copy authorized files only
                $dh = opendir($extractdir);
                while ($direntry = readdir($dh))
                    if (($direntry != ".") && ($direntry != ".."))
                        if (is_file($extractdir . "/" . $direntry)) {
                            // Is a file
                            $extfile = substr(strrchr($direntry, '.'), 1);

                            if (!(stripos(',' . Yii::app()->getConfig('allowedresourcesuploads') . ',', ',' . $extfile . ',') === false)
                            )
                                // Extension allowed
                                if (!copy($extractdir . "/" . $direntry, $destdir . $direntry))
                                    $aErrorFilesInfo[] = Array(
                                    "filename" => $direntry,
                                    "status" => $clang->gT("Copy failed")
                                    );
                                else
                                    $aImportedFilesInfo[] = Array(
                                    "filename" => $direntry,
                                    "status" => $clang->gT("OK")
                                    );
                            else
                                // Extension forbidden
                                $aErrorFilesInfo[] = Array(
                                "filename" => $direntry,
                                "status" => $clang->gT("Error") . " (" . $clang->gT("Forbidden Extension") . ")"
                                );
                            unlink($extractdir . "/" . $direntry);
                        }

                        // Delete the temporary file
                        unlink($zipfile);
                closedir($dh);

                // Delete temporary folder
                rmdir($extractdir);

                if (count($aErrorFilesInfo) == 0 && count($aImportedFilesInfo) == 0)
                    $this->getController()->error($clang->gT("This ZIP archive contains no valid template files. Import failed."));
            }
            else
                $this->getController()->error(sprintf($clang->gT("An error occurred uploading your file. This may be caused by incorrect permissions in your %s folder."), $basedestdir));


            $aViewUrls = 'importuploaded_view';
            $aData = array(
            'aImportedFilesInfo' => $aImportedFilesInfo,
            'aErrorFilesInfo' => $aErrorFilesInfo,
            'lid' => $lid,
            'newdir' => $newdir,
            );
        }
        else
        {
            $aViewUrls = 'importform_view';
            $aData = array('lid' => $lid);
        }

        $this->_renderWrappedTemplate('templates', $aViewUrls, $aData);
    }
    /**
    * Responsible to import a template file.
    *
    * @access public
    * @return void
    */
    public function uploadfile()
    {
        $clang = $this->getController()->lang;
        $action = returnGlobal('action');
        $editfile = returnGlobal('editfile');
        $templatename = returnGlobal('templatename');
        $screenname = returnGlobal('screenname');
        $files = $this->_initfiles($templatename);
        $cssfiles = $this->_initcssfiles();
        $basedestdir = Yii::app()->getConfig('usertemplaterootdir');
        $tempdir = Yii::app()->getConfig('tempdir');
        $allowedtemplateuploads=Yii::app()->getConfig('allowedtemplateuploads');
        $filename=sanitize_filename($_FILES['upload_file']['name'],false,false);// Don't force lowercase or alphanumeric
        $fullfilepath=$basedestdir."/".$templatename . "/" . $filename;

        if($action=="templateuploadfile")
        {
            if(Yii::app()->getConfig('demoMode'))
            {
                $uploadresult = $clang->gT("Demo mode: Uploading template files is disabled.");
            }
            elseif($filename!=$_FILES['upload_file']['name'])
            {
                $uploadresult = $clang->gT("This filename is not allowed to be uploaded.");
            }
            elseif(!in_array(substr(strrchr($filename, '.'),1),explode ( "," , $allowedtemplateuploads )))
            {

                $uploadresult = $clang->gT("This file type is not allowed to be uploaded.");
            }
            else
            {
                  //Uploads the file into the appropriate directory
                   if (!@move_uploaded_file($_FILES['upload_file']['tmp_name'], $fullfilepath)) {
                        $uploadresult = sprintf($clang->gT("An error occurred uploading your file. This may be caused by incorrect permissions in your %s folder."),$tempdir);
                   }
                   else
                   {
                        $uploadresult = sprintf($clang->gT("File %s uploaded"),$filename);
                   }
            }
            Yii::app()->session['flashmessage'] = $uploadresult;
        }
        $this->getController()->redirect(array("admin/templates/view/editfile/" . $editfile . "/screenname/" . $screenname . "/templatename/" . $templatename));
    }
    /**
    * Generates a random temp directory
    *
    * @access protected
    * @param string $dir
    * @param string $prefix
    * @param string $mode
    * @return string
    */
    protected function _tempdir($dir, $prefix = '', $mode = 0700)
    {
        if (substr($dir, -1) != '/')
            $dir .= '/';

        do
        {
            $path = $dir . $prefix . mt_rand(0, 9999999);
        }
        while (!mkdir($path, $mode));

        return $path;
    }

    /**
    * Strips file extension
    *
    * @access protected
    * @param string $name
    * @return string
    */
    protected function _strip_ext($name)
    {
        $ext = strrchr($name, '.');
        if ($ext !== false) {
            $name = substr($name, 0, -strlen($ext));
        }
        return $name;
    }

    /**
    * Load default view screen of template controller.
    *
    * @access public
    * @param string $editfile
    * @param string $screenname
    * @param string $templatename
    * @return void
    */
    public function index($editfile = 'startpage.pstpl', $screenname = 'welcome', $templatename = 'default')
    {
        $aViewUrls = $this->_initialise($templatename, $screenname, $editfile);
        $this->getController()->_js_admin_includes(Yii::app()->getConfig('adminscripts') . 'templates.js');
        $this->getController()->_css_admin_includes(Yii::app()->getConfig('adminscripts') . 'codemirror_ui/lib/CodeMirror-2.0/lib/codemirror.css');
        $this->getController()->_css_admin_includes(Yii::app()->getConfig('adminscripts') . 'codemirror_ui/lib/CodeMirror-2.0/mode/css/css.css');
        $this->getController()->_css_admin_includes(Yii::app()->getConfig('adminscripts') . 'codemirror_ui/lib/CodeMirror-2.0/mode/javascript/javascript.css');
        $this->getController()->_css_admin_includes(Yii::app()->getConfig('adminscripts') . 'codemirror_ui/lib/CodeMirror-2.0/mode/xml/xml.css');
        $this->getController()->_css_admin_includes(Yii::app()->getConfig('adminscripts') . 'codemirror_ui/css/codemirror-ui.css');

        $this->_renderWrappedTemplate('templates', $aViewUrls);

        if ($screenname != 'welcome')
            Yii::app()->session['step'] = 1;
        // This helps handle the load/save buttons)
        else
            unset(Yii::app()->session['step']);
    }

    /**
    * templates::screenredirect()
    * Function that modify order of arguments and pass to main viewing function i.e. view()
    *
    * @access public
    * @param string $editfile
    * @param string $templatename
    * @param string $screenname
    * @return void
    */
    public function screenredirect($editfile = 'startpage.pstpl', $templatename = 'default', $screenname = 'welcome')
    {
        $this->getController()->redirect($this->getController()->createUrl("admin/templates/view/editfile/" . $editfile . "/screenname/" . $screenname . "/templatename/" . $templatename));
    }

    /**
    * Function that modify order of arguments and pass to main viewing function i.e. view()
    *
    * @access public
    * @param string $templatename
    * @param string $screenname
    * @param string $editfile
    * @return void
    */
    public function fileredirect($templatename = 'default', $screenname = 'welcome', $editfile = 'startpage.pstpl')
    {
        $this->getController()->redirect($this->getController()->createUrl("admin/templates/view/editfile/" . $editfile . "/screenname/" . $screenname . "/templatename/" . $templatename));
    }

    /**
    * Function responsible to delete a template file.
    *
    * @access public
    * @return void
    */
    public function templatefiledelete()
    {
        $clang = $this->getController()->lang;
        if (returnGlobal('action') == "templatefiledelete") {
            // This is where the temp file is
            $sFileToDelete=preg_replace("[^\w\s\d\.\-_~,;:\[\]\(\]]", '', returnGlobal('otherfile'));

            $the_full_file_path = Yii::app()->getConfig('usertemplaterootdir') . "/" . $_POST['templatename'] . "/" . $sFileToDelete;
            if (@unlink($the_full_file_path))
            {
                Yii::app()->session['flashmessage'] = sprintf($clang->gT("The file %s was deleted."), htmlspecialchars($sFileToDelete));
            }
            else
            {
                Yii::app()->session['flashmessage'] = sprintf($clang->gT("File %s couldn't be deleted. Please check the permissions on the /upload/template folder"), htmlspecialchars($sFileToDelete));
            }
            $this->getController()->redirect($this->getController()->createUrl("admin/templates/view/editfile/" . returnGlobal('editfile') . "/screenname/" . returnGlobal('screenname') . "/templatename/" . returnGlobal('templatename')));
        }
    }

    /**
    * Function responsible to rename a template(folder).
    *
    * @access public
    * @return void
    */
    public function templaterename()
    {
        if (returnGlobal('action') == "templaterename" && returnGlobal('newname') && returnGlobal('copydir')) {
            $clang = Yii::app()->lang;
            $newname=sanitize_paranoid_string(returnGlobal('newname'));
            $newdirname = Yii::app()->getConfig('usertemplaterootdir') . "/" . $newname;
            $olddirname = Yii::app()->getConfig('usertemplaterootdir') . "/" . returnGlobal('copydir');
            if (isStandardTemplate(returnGlobal('newname')))
                $this->getController()->error(sprintf($clang->gT("Template could not be renamed to `%s`.", "js"), $newname) . " " . $clang->gT("This name is reserved for standard template.", "js"));
            elseif (rename($olddirname, $newdirname) == false)
                $this->getController()->error(sprintf($clang->gT("Directory could not be renamed to `%s`.", "js"), $newname) . " " . $clang->gT("Maybe you don't have permission.", "js"));
            else
            {
                $templatename = $newname;
                $this->index("startpage.pstpl", "welcome", $templatename);
            }
        }
    }

    /**
    * Function responsible to copy a template.
    *
    * @access public
    * @return void
    */
    public function templatecopy()
    {
        $clang = $this->getController()->lang;

        if (returnGlobal('action') == "templatecopy" && returnGlobal('newname') && returnGlobal('copydir')) {
            // Copies all the files from one template directory to a new one
            // This is a security issue because it is allowing copying from get variables...
            Yii::app()->loadHelper('admin/template');
            $newname= sanitize_paranoid_string(returnGlobal('newname'));
            $newdirname = Yii::app()->getConfig('usertemplaterootdir') . "/" . $newname;
            $copydirname = getTemplatePath(returnGlobal('copydir'));
            $mkdirresult = mkdir_p($newdirname);

            if ($mkdirresult == 1) {
                $copyfiles = getListOfFiles($copydirname);
                foreach ($copyfiles as $file)
                {
                    $copyfile = $copydirname . "/" . $file;
                    $newfile = $newdirname . "/" . $file;
                    if (!copy($copyfile, $newfile))
                        $this->getController()->error(sprintf($clang->gT("Failed to copy %s to new template directory.", "js"), $file));
                }

                $templatename = $newname;
                $this->index("startpage.pstpl", "welcome", $templatename);
            }
            elseif ($mkdirresult == 2)
                $this->getController()->error(sprintf($clang->gT("Directory with the name `%s` already exists - choose another name", "js"), $newname));
            else
                $this->getController()->error(sprintf($clang->gT("Unable to create directory `%s`.", "js"), $newname) . " " . $clang->gT("Please check the directory permissions.", "js"));
            ;
        }
    }

    /**
    * Function responsible to delete a template.
    *
    * @access public
    * @param string $templatename
    * @return void
    */
    public function delete($templatename)
    {
        Yii::app()->loadHelper("admin/template");
        if (is_template_editable($templatename) == true) {
            $clang = $this->getController()->lang;

            if (rmdirr(Yii::app()->getConfig('usertemplaterootdir') . "/" . $templatename) == true) {
                $surveys = Survey::model()->findAllByAttributes(array('template' => $templatename));
                foreach ($surveys as $s)
                {
                    $s->template = Yii::app()->getConfig('defaulttemplate');
                    $s->save();
                }

                Template::model()->deleteAllByAttributes(array('folder' => $templatename));
                Templates_rights::model()->deleteAllByAttributes(array('folder' => $templatename));

                Yii::app()->session['flashmessage'] = sprintf($clang->gT("Template '%s' was successfully deleted."), $templatename);
            }
            else
                Yii::app()->session['flashmessage'] = sprintf($clang->gT("There was a problem deleting the template '%s'. Please check your directory/file permissions."), $templatename);
        }

        // Redirect with default templatename, editfile and screenname
        $this->getController()->redirect($this->getController()->createUrl("admin/templates/view"));
    }

    /**
    * Function responsible to save the changes made in CodemMirror editor.
    *
    * @access public
    * @return void
    */
    public function templatesavechanges()
    {
        if (returnGlobal('changes')) {
            $changedtext = returnGlobal('changes');
            $changedtext = str_replace('<?', '', $changedtext);
            if (get_magic_quotes_gpc())
                $changedtext = stripslashes($changedtext);
        }

        if (returnGlobal('changes_cp')) {
            $changedtext = returnGlobal('changes_cp');
            $changedtext = str_replace('<?', '', $changedtext);
            if (get_magic_quotes_gpc())
                $changedtext = stripslashes($changedtext);
        }

        $action = returnGlobal('action');
        $editfile = returnGlobal('editfile');
        $templatename = returnGlobal('templatename');
        $screenname = returnGlobal('screenname');
        $files = $this->_initfiles($templatename);
        $cssfiles = $this->_initcssfiles();

        if ($action == "templatesavechanges" && $changedtext) {
            Yii::app()->loadHelper('admin/template');
            $changedtext = str_replace("\r\n", "\n", $changedtext);

            if ($editfile) {
                // Check if someone tries to submit a file other than one of the allowed filenames
                if (multiarray_search($files, 'name', $editfile) === false &&
                multiarray_search($cssfiles, 'name', $editfile) === false
                )
                    $this->getController()->error('Invalid template name');

                $savefilename = Yii::app()->getConfig('usertemplaterootdir') . "/" . $templatename . "/" . $editfile;
                if (is_writable($savefilename)) {
                    if (!$handle = fopen($savefilename, 'w'))
                        $this->getController()->error('Could not open file ' . $savefilename);

                    if (!fwrite($handle, $changedtext))
                        $this->getController()->error('Could not write file ' . $savefilename);

                    fclose($handle);
                }
                else
                    $this->getController()->error("The file $savefilename is not writable");
            }
        }

        $this->getController()->redirect($this->getController()->createUrl("admin/templates/view/editfile/" . $editfile . "/screenname/" . $screenname . "/templatename/" . $templatename));
    }

    /**
    * Load menu bar related to a template.
    *
    * @access protected
    * @param string $screenname
    * @param string $editfile
    * @param string $screens
    * @param string $tempdir
    * @param string $templatename
    * @return void
    */
    protected function _templatebar($screenname, $editfile, $screens, $tempdir, $templatename)
    {
        $aData['clang'] = $this->getController()->lang;
        $aData['screenname'] = $screenname;
        $aData['editfile'] = $editfile;
        $aData['screens'] = $screens;
        $aData['tempdir'] = $tempdir;
        $aData['templatename'] = $templatename;
        $aData['usertemplaterootdir'] = Yii::app()->getConfig('usertemplaterootdir');

        $this->getController()->render("/admin/templates/templatebar_view", $aData);
    }

    /**
    * Load CodeMirror editor and various files information.
    *
    * @access protected
    * @param string $templatename
    * @param string $screenname
    * @param string $editfile
    * @param string $templates
    * @param string $files
    * @param string $cssfiles
    * @param array $otherfiles
    * @param array $myoutput
    * @return void
    */
    protected function _templatesummary($templatename, $screenname, $editfile, $templates, $files, $cssfiles, $otherfiles, $myoutput)
    {
        $tempdir = Yii::app()->getConfig("tempdir");
        $tempurl = Yii::app()->getConfig("tempurl");

        Yii::app()->loadHelper("admin/template");
        $aData = array();
        $time = date("ymdHis");

        // Prepare textarea class for optional javascript
        $templateclasseditormode = getGlobalSetting('defaulttemplateeditormode'); // default
        if (Yii::app()->session['templateeditormode'] == 'none')
            $templateclasseditormode = 'none';

        $aData['templateclasseditormode'] = $templateclasseditormode;

        // The following lines are forcing the browser to refresh the templates on each save
        @$fnew = fopen("$tempdir/template_temp_$time.html", "w+");
        $aData['time'] = $time;

        if (!$fnew) {
            $aData['filenotwritten'] = true;
        }
        else
        {
            @fwrite($fnew, getHeader());
            foreach ($cssfiles as $cssfile)
                $myoutput = str_replace($cssfile['name'], $cssfile['name'] . "?t=$time", $myoutput);

            foreach ($myoutput as $line)
                @fwrite($fnew, $line);

            @fclose($fnew);
        }

        $sExtension=substr(strrchr($editfile, '.'), 1);
        switch ($sExtension)
        {
           case 'css':$sEditorFileType='css';
           break;
           case 'pstpl':$sEditorFileType='htmlmixed';
           break;
           case 'js':$sEditorFileType='javascript';
           break;
           default: $sEditorFileType='htmlmixed';
           break;
        }


        $aData['clang'] = $this->getController()->lang;
        $aData['screenname'] = $screenname;
        $aData['editfile'] = $editfile;

        $aData['tempdir'] = $tempdir;
        $aData['templatename'] = $templatename;
        $aData['templates'] = $templates;
        $aData['files'] = $files;
        $aData['cssfiles'] = $cssfiles;
        $aData['otherfiles'] = $otherfiles;
        $aData['tempurl'] = $tempurl;
        $aData['time'] = $time;
        $aData['sEditorFileType'] = $sEditorFileType;

        $aViewUrls['templatesummary_view'][] = $aData;

        return $aViewUrls;
    }

    /**
    * Function that initialises file data.
    *
    * @access protected
    * @param mixed $templatename
    * @return void
    */
    protected function _initfiles($templatename)
    {
        $files[] = array('name' => 'assessment.pstpl');
        $files[] = array('name' => 'clearall.pstpl');
        $files[] = array('name' => 'completed.pstpl');
        $files[] = array('name' => 'endgroup.pstpl');
        $files[] = array('name' => 'endpage.pstpl');
        $files[] = array('name' => 'groupdescription.pstpl');
        $files[] = array('name' => 'load.pstpl');
        $files[] = array('name' => 'navigator.pstpl');
        $files[] = array('name' => 'printanswers.pstpl');
        $files[] = array('name' => 'privacy.pstpl');
        $files[] = array('name' => 'question.pstpl');
        $files[] = array('name' => 'register.pstpl');
        $files[] = array('name' => 'save.pstpl');
        $files[] = array('name' => 'surveylist.pstpl');
        $files[] = array('name' => 'startgroup.pstpl');
        $files[] = array('name' => 'startpage.pstpl');
        $files[] = array('name' => 'survey.pstpl');
        $files[] = array('name' => 'welcome.pstpl');
        $files[] = array('name' => 'print_survey.pstpl');
        $files[] = array('name' => 'print_group.pstpl');
        $files[] = array('name' => 'print_question.pstpl');

        if (is_file(Yii::app()->getConfig('usertemplaterootdir') . '/' . $templatename . '/question_start.pstpl'))
            $files[] = array('name' => 'question_start.pstpl');

        return $files;
    }

    /**
    * Function that initialises cssfile data.
    *
    * @access protected
    * @return void
    */
    protected function _initcssfiles()
    {
        $cssfiles[] = array('name' => 'template.css');
        $cssfiles[] = array('name' => 'template-rtl.css');
        $cssfiles[] = array('name' => 'ie_fix_6.css');
        $cssfiles[] = array('name' => 'ie_fix_7.css');
        $cssfiles[] = array('name' => 'ie_fix_8.css');
        $cssfiles[] = array('name' => 'jquery-ui-custom.css');
        $cssfiles[] = array('name' => 'print_template.css');
        $cssfiles[] = array('name' => 'template.js');

        return $cssfiles;
    }

    /**
    * Function that initialises all data and call other functions to load default view.
    *
    * @access protected
    * @param string $templatename
    * @param string $screenname
    * @param string $editfile
    * @param bool $showsummary
    * @return
    */
    protected function _initialise($templatename, $screenname, $editfile, $showsummary = true)
    {
        global $siteadminname, $siteadminemail;

        $clang = $this->getController()->lang;
        Yii::app()->loadHelper('surveytranslator');
        Yii::app()->loadHelper('admin/template');

        $files = $this->_initfiles($templatename);

        $cssfiles = $this->_initcssfiles();

        // Standard Support Files
        // These files may be edited or saved
        $supportfiles[] = array('name' => 'print_img_radio.png');
        $supportfiles[] = array('name' => 'print_img_checkbox.png');

        // Standard screens
        // Only these may be viewed
        $screens[] = array('name' => $clang->gT('Survey List Page'), 'id' => 'surveylist');
        $screens[] = array('name' => $clang->gT('Welcome Page'), 'id' => 'welcome');
        $screens[] = array('name' => $clang->gT('Question Page'), 'id' => 'question');
        $screens[] = array('name' => $clang->gT('Completed Page'), 'id' => 'completed');
        $screens[] = array('name' => $clang->gT('Clear All Page'), 'id' => 'clearall');
        $screens[] = array('name' => $clang->gT('Register Page'), 'id' => 'register');
        $screens[] = array('name' => $clang->gT('Load Page'), 'id' => 'load');
        $screens[] = array('name' => $clang->gT('Save Page'), 'id' => 'save');
        $screens[] = array('name' => $clang->gT('Print answers page'), 'id' => 'printanswers');
        $screens[] = array('name' => $clang->gT('Printable survey page'), 'id' => 'printablesurvey');

        // Page display blocks
        $SurveyList = array('startpage.pstpl',
        'surveylist.pstpl',
        'endpage.pstpl'
        );
        $Welcome = array('startpage.pstpl',
        'welcome.pstpl',
        'privacy.pstpl',
        'navigator.pstpl',
        'endpage.pstpl'
        );
        $Question = array('startpage.pstpl',
        'survey.pstpl',
        'startgroup.pstpl',
        'groupdescription.pstpl',
        'question.pstpl',
        'endgroup.pstpl',
        'navigator.pstpl',
        'endpage.pstpl'
        );
        $CompletedTemplate = array(
        'startpage.pstpl',
        'assessment.pstpl',
        'completed.pstpl',
        'endpage.pstpl'
        );
        $Clearall = array('startpage.pstpl',
        'clearall.pstpl',
        'endpage.pstpl'
        );
        $Register = array('startpage.pstpl',
        'survey.pstpl',
        'register.pstpl',
        'endpage.pstpl'
        );
        $Save = array('startpage.pstpl',
        'save.pstpl',
        'endpage.pstpl'
        );
        $Load = array('startpage.pstpl',
        'load.pstpl',
        'endpage.pstpl'
        );
        $printtemplate = array('startpage.pstpl',
        'printanswers.pstpl',
        'endpage.pstpl'
        );
        $printablesurveytemplate = array('print_survey.pstpl',
        'print_group.pstpl',
        'print_question.pstpl'
        );

        $file_version = "LimeSurvey template editor " . Yii::app()->getConfig('versionnumber');
        Yii::app()->session['s_lang'] = Yii::app()->session['adminlang'];

        $templatename = sanitize_paranoid_string($templatename);
        $screenname = autoUnescape($screenname);

        // Checks if screen name is in the list of allowed screen names
        if (multiarray_search($screens, 'id', $screenname) === false)
            $this->getController()->error('Invalid screen name');

        if (!isset($action))
            $action = sanitize_paranoid_string(returnGlobal('action'));

        if (!isset($subaction))
            $subaction = sanitize_paranoid_string(returnGlobal('subaction'));

        if (!isset($newname))
            $newname = sanitize_paranoid_string(returnGlobal('newname'));

        if (!isset($copydir))
            $copydir = sanitize_paranoid_string(returnGlobal('copydir'));

        if (is_file(Yii::app()->getConfig('usertemplaterootdir') . '/' . $templatename . '/question_start.pstpl')) {
            $files[] = array('name' => 'question_start.pstpl');
            $Question[] = 'question_start.pstpl';
        }

        $availableeditorlanguages = array('bg', 'cs', 'de', 'dk', 'en', 'eo', 'es', 'fi', 'fr', 'hr', 'it', 'ja', 'mk', 'nl', 'pl', 'pt', 'ru', 'sk', 'zh');
        $extension = substr(strrchr($editfile, "."), 1);
        if ($extension == 'css' || $extension == 'js')
            $highlighter = $extension;
        else
            $highlighter = 'html';

        if (in_array(Yii::app()->session['adminlang'], $availableeditorlanguages))
            $codelanguage = Yii::app()->session['adminlang'];
        else
            $codelanguage = 'en';

        $templates = getTemplateList();
        if (!isset($templates[$templatename]))
            $templatename = Yii::app()->getConfig('defaulttemplate');

        $normalfiles = array("DUMMYENTRY", ".", "..", "preview.png");
        foreach ($files as $fl)
            $normalfiles[] = $fl["name"];

        foreach ($cssfiles as $fl)
            $normalfiles[] = $fl["name"];

        // Set this so common.php doesn't throw notices about undefined variables
        $thissurvey['active'] = 'N';

        // FAKE DATA FOR TEMPLATES
        $thissurvey['name'] = $clang->gT("Template Sample");
        $thissurvey['description'] =
        $clang->gT('This is a sample survey description. It could be quite long.') . '<br /><br />' .
        $clang->gT("But this one isn't.");
        $thissurvey['welcome'] =
        $clang->gT('Welcome to this sample survey') . '<br />' .
        $clang->gT('You should have a great time doing this') . '<br />';
        $thissurvey['allowsave'] = "Y";
        $thissurvey['active'] = "Y";
        $thissurvey['tokenanswerspersistence'] = "Y";
        $thissurvey['templatedir'] = $templatename;
        $thissurvey['format'] = "G";
        $thissurvey['surveyls_url'] = "http://www.limesurvey.org/";
        $thissurvey['surveyls_urldescription'] = $clang->gT("Some URL description");
        $thissurvey['usecaptcha'] = "A";
        $percentcomplete = makegraph(6, 10);

        $groupname = $clang->gT("Group 1: The first lot of questions");
        $groupdescription = $clang->gT("This group description is fairly vacuous, but quite important.");

        $navigator = $this->getController()->render('/admin/templates/templateeditor_navigator_view', array(
        'screenname' => $screenname,
        'clang' => $clang,
        ), true);

        $completed = $this->getController()->render('/admin/templates/templateeditor_completed_view', array(
        'clang' => $clang,
        ), true);

        $assessments = $this->getController()->render('/admin/templates/templateeditor_assessments_view', array(
        'clang' => $clang,
        ), true);

        $printoutput = $this->getController()->render('/admin/templates/templateeditor_printoutput_view', array(
        'clang' => $clang
        ), true);

        $help = $clang->gT("This is some help text.");
        $totalquestions = '10';
        $surveyformat = 'Format';
        $notanswered = '5';
        $privacy = '';
        $surveyid = '1295';
        $token = 1234567;

        $templatedir = getTemplatePath($templatename);
        $templateurl = getTemplateURL($templatename);

        // Save these variables in an array
        $aData['thissurvey'] = $thissurvey;
        $aData['percentcomplete'] = $percentcomplete;
        $aData['groupname'] = $groupname;
        $aData['groupdescription'] = $groupdescription;
        $aData['navigator'] = $navigator;
        $aData['help'] = $help;
        $aData['surveyformat'] = $surveyformat;
        $aData['totalquestions'] = $totalquestions;
        $aData['completed'] = $completed;
        $aData['notanswered'] = $notanswered;
        $aData['privacy'] = $privacy;
        $aData['surveyid'] = $surveyid;
        $aData['token'] = $token;
        $aData['assessments'] = $assessments;
        $aData['printoutput'] = $printoutput;
        $aData['templatedir'] = $templatedir;
        $aData['templateurl'] = $templateurl;
        $aData['templatename'] = $templatename;
        $aData['screenname'] = $screenname;
        $aData['editfile'] = $editfile;

        $myoutput[] = "";
        switch ($screenname)
        {
            case 'surveylist':
                unset($files);

                $surveylist = array(
                "nosid" => $clang->gT("You have not provided a survey identification number"),
                "contact" => sprintf($clang->gT("Please contact %s ( %s ) for further assistance."), $siteadminname, $siteadminemail),
                "listheading" => $clang->gT("The following surveys are available:"),
                "list" => $this->getController()->render('/admin/templates/templateeditor_surveylist_view', array(), true),
                );
                $aData['surveylist'] = $surveylist;

                $myoutput[] = "";
                foreach ($SurveyList as $qs)
                {
                    $files[] = array("name" => $qs);
                    $myoutput = array_merge($myoutput, doreplacement(getTemplatePath($templatename) . "/$qs", $aData));
                }
                break;

            case 'question':
                unset($files);
                foreach ($Question as $qs)
                    $files[] = array("name" => $qs);

                $myoutput[] = $this->getController()->render('/admin/templates/templateeditor_question_meta_view', array(), true);
                $myoutput = array_merge($myoutput, doreplacement(getTemplatePath($templatename) . "/startpage.pstpl", $aData));
                $myoutput = array_merge($myoutput, doreplacement(getTemplatePath($templatename) . "/survey.pstpl", $aData));
                $myoutput = array_merge($myoutput, doreplacement(getTemplatePath($templatename) . "/startgroup.pstpl", $aData));
                $myoutput = array_merge($myoutput, doreplacement(getTemplatePath($templatename) . "/groupdescription.pstpl", $aData));

                $question = array(
                'all' => 'How many roads must a man walk down?',
                'text' => 'How many roads must a man walk down?',
                'code' => '1a',
                'help' => 'helpful text',
                'mandatory' => '',
                'man_message' => '',
                'valid_message' => '',
                'file_valid_message' => '',
                'essentials' => 'id="question1"',
                'class' => 'list-radio',
                'man_class' => '',
                'input_error_class' => '',
                'number' => '1',
                'type' => 'L'
                );
                $aData['question'] = $question;

                $answer = $this->getController()->render('/admin/templates/templateeditor_question_answer_view', array(), true);
                $aData['answer'] = $answer;
                $myoutput = array_merge($myoutput, doreplacement(getTemplatePath($templatename) . "/question.pstpl", $aData));

                $answer = $this->getController()->render('/admin/templates/templateeditor_question_answer_view', array('alt' => true), true);
                $aData['answer'] = $answer;
                $question = array(
                'all' => '<span class="asterisk">*</span>' . $clang->gT("Please explain something in detail:"),
                'text' => $clang->gT('Please explain something in detail:'),
                'code' => '2a',
                'help' => '',
                'mandatory' => $clang->gT('*'),
                'man_message' => '',
                'valid_message' => '',
                'file_valid_message' => '',
                'essentials' => 'id="question2"',
                'class' => 'text-long',
                'man_class' => 'mandatory',
                'input_error_class' => '',
                'number' => '2',
                'type' => 'T'
                );
                $aData['question'] = $question;
                $myoutput = array_merge($myoutput, doreplacement(getTemplatePath($templatename) . "/question.pstpl", $aData));
                $myoutput = array_merge($myoutput, doreplacement(getTemplatePath($templatename) . "/endgroup.pstpl", $aData));
                $myoutput = array_merge($myoutput, doreplacement(getTemplatePath($templatename) . "/navigator.pstpl", $aData));
                $myoutput = array_merge($myoutput, doreplacement(getTemplatePath($templatename) . "/endpage.pstpl", $aData));
                break;

            case 'welcome':
                unset($files);
                $myoutput[] = "";
                foreach ($Welcome as $qs)
                {
                    $files[] = array("name" => $qs);
                    $myoutput = array_merge($myoutput, doreplacement(getTemplatePath($templatename) . "/$qs", $aData));
                }
                break;

            case 'register':
                unset($files);
                foreach ($Register as $qs)
                    $files[] = array("name" => $qs);

                $myoutput[] = templatereplace(file_get_contents("$templatedir/startpage.pstpl"), array(), $aData);
                $myoutput[] = templatereplace(file_get_contents("$templatedir/survey.pstpl"), array(), $aData);
                $myoutput[] = templatereplace(file_get_contents("$templatedir/register.pstpl"), array(), $aData);
                $myoutput[] = templatereplace(file_get_contents("$templatedir/endpage.pstpl"), array(), $aData);
                $myoutput[] = "\n";
                break;

            case 'save':
                unset($files);
                foreach ($Save as $qs)
                    $files[] = array("name" => $qs);

                $myoutput[] = templatereplace(file_get_contents("$templatedir/startpage.pstpl"), array(), $aData);
                $myoutput[] = templatereplace(file_get_contents("$templatedir/save.pstpl"), array(), $aData);
                $myoutput[] = templatereplace(file_get_contents("$templatedir/endpage.pstpl"), array(), $aData);
                $myoutput[] = "\n";
                break;

            case 'load':
                unset($files);
                foreach ($Load as $qs)
                    $files[] = array("name" => $qs);

                $myoutput[] = templatereplace(file_get_contents("$templatedir/startpage.pstpl"), array(), $aData);
                $myoutput[] = templatereplace(file_get_contents("$templatedir/load.pstpl"), array(), $aData);
                $myoutput[] = templatereplace(file_get_contents("$templatedir/endpage.pstpl"), array(), $aData);
                $myoutput[] = "\n";
                break;

            case 'clearall':
                unset($files);
                foreach ($Clearall as $qs)
                    $files[] = array("name" => $qs);

                $myoutput[] = templatereplace(file_get_contents("$templatedir/startpage.pstpl"), array(), $aData);
                $myoutput[] = templatereplace(file_get_contents("$templatedir/clearall.pstpl"), array(), $aData);
                $myoutput[] = templatereplace(file_get_contents("$templatedir/endpage.pstpl"), array(), $aData);
                $myoutput[] = "\n";
                break;

            case 'completed':
                unset($files);
                $myoutput[] = "";
                foreach ($CompletedTemplate as $qs)
                {
                    $files[] = array("name" => $qs);
                    $myoutput = array_merge($myoutput, doreplacement(getTemplatePath($templatename) . "/$qs", $aData));
                }
                break;

            case 'printablesurvey':
                unset($files);
                foreach ($printablesurveytemplate as $qs)
                {
                    $files[] = array("name" => $qs);
                }

                $questionoutput = array();
                foreach (file("$templatedir/print_question.pstpl") as $op)
                {
                    $questionoutput[] = templatereplace($op, array(
                    'QUESTION_NUMBER' => '1',
                    'QUESTION_CODE' => 'Q1',
                    'QUESTION_MANDATORY' => $clang->gT('*'),
                    // If there are conditions on a question, list the conditions.
                    'QUESTION_SCENARIO' => 'Only answer this if certain conditions are met.',
                    'QUESTION_CLASS' => ' mandatory list-radio',
                    'QUESTION_TYPE_HELP' => $clang->gT('Please choose *only one* of the following:'),
                    // (not sure if this is used) mandatory error
                    'QUESTION_MAN_MESSAGE' => '',
                    // (not sure if this is used) validation error
                    'QUESTION_VALID_MESSAGE' => '',
                    // (not sure if this is used) file validation error
                    'QUESTION_FILE_VALID_MESSAGE' => '',
                    'QUESTION_TEXT' => 'This is a sample question text. The user was asked to pick an entry.',
                    'QUESTIONHELP' => 'This is some help text for this question.',
                    'ANSWER' =>
                    $this->getController()->render('/admin/templates/templateeditor_printablesurvey_quesanswer_view', array(
                    'templateurl' => $templateurl,
                    ), true),
                    ), $aData);
                }
                $groupoutput = array();
                $groupoutput[] = templatereplace(file_get_contents("$templatedir/print_group.pstpl"), array('QUESTIONS' => implode(' ', $questionoutput)), $aData);

                $myoutput[] = templatereplace(file_get_contents("$templatedir/print_survey.pstpl"), array('GROUPS' => implode(' ', $groupoutput),
                'FAX_TO' => $clang->gT("Please fax your completed survey to:") . " 000-000-000",
                'SUBMIT_TEXT' => $clang->gT("Submit your survey."),
                'HEADELEMENTS' => getPrintableHeader(),
                'SUBMIT_BY' => sprintf($clang->gT("Please submit by %s"), date('d.m.y')),
                'THANKS' => $clang->gT('Thank you for completing this survey.'),
                'END' => $clang->gT('This is the survey end message.')
                ), $aData);
                break;

            case 'printanswers':
                unset($files);
                foreach ($printtemplate as $qs)
                {
                    $files[] = array("name" => $qs);
                }

                $myoutput[] = templatereplace(file_get_contents("$templatedir/startpage.pstpl"), array(), $aData);
                $myoutput[] = templatereplace(file_get_contents("$templatedir/printanswers.pstpl"), array('ANSWERTABLE' => $printoutput), $aData);
                $myoutput[] = templatereplace(file_get_contents("$templatedir/endpage.pstpl"), array(), $aData);

                $myoutput[] = "\n";
                break;
        }
        $myoutput[] = "</html>";

        if (is_array($files)) {
            $match = 0;
            foreach ($files as $f)
                if ($editfile == $f["name"])
                    $match = 1;

                foreach ($cssfiles as $f)
                if ($editfile == $f["name"])
                    $match = 1;

                if ($match == 0)
                if (count($files) > 0)
                    $editfile = $files[0]["name"];
                else
                    $editfile = "";
        }

        // Get list of 'otherfiles'
        $otherfiles = array();
        if ($handle = opendir($templatedir)) {
            while (false !== ($file = readdir($handle)))
            {
                if (!array_search($file, $normalfiles)) {
                    if (!is_dir($templatedir . DIRECTORY_SEPARATOR . $file)) {
                        $otherfiles[] = array("name" => $file);
                    }
                }
            }

            closedir($handle);
        }

        $aData['clang'] = $this->getController()->lang;
        $aData['codelanguage'] = $codelanguage;
        $aData['highlighter'] = $highlighter;
        $aData['screens'] = $screens;
        $aData['templatename'] = $templatename;
        $aData['templates'] = $templates;
        $aData['editfile'] = $editfile;
        $aData['screenname'] = $screenname;
        $aData['tempdir'] = Yii::app()->getConfig('tempdir');
        $aData['usertemplaterootdir'] = Yii::app()->getConfig('usertemplaterootdir');

        $aViewUrls['templateeditorbar_view'][] = $aData;

        if ($showsummary)
            $aViewUrls = array_merge($aViewUrls, $this->_templatesummary($templatename, $screenname, $editfile, $templates, $files, $cssfiles, $otherfiles, $myoutput));

        return $aViewUrls;
    }

    /**
    * Renders template(s) wrapped in header and footer
    *
    * @param string $sAction Current action, the folder to fetch views from
    * @param string|array $aViewUrls View url(s)
    * @param array $aData Data to be passed on. Optional.
    */
    protected function _renderWrappedTemplate($sAction = 'templates', $aViewUrls = array(), $aData = array())
    {
        $aData['display']['menu_bars'] = false;
        parent::_renderWrappedTemplate($sAction, $aViewUrls, $aData);
    }
}
