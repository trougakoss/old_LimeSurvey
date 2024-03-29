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
class Expressions extends Survey_Common_Action {
	function index()
	{
    ?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>ExpressionManager:  <?php $this->_printTitle(Yii::app()->request->getQuery('sa', 'index')); ?></title>
        <script src="<?php echo Yii::app()->getConfig('generalscripts')  . 'jquery/jquery.js'; ?>"></script>
        <script src="<?php echo Yii::app()->getConfig('generalscripts')  . 'expressions/em_javascript.js'; ?>" /></script>
        <script src="<?php echo Yii::app()->getConfig('generalscripts')  . 'survey_runtime.js'; ?>" /></script>
        <style type="text/css">
            <!--
                .error {
                    background-color: #ff0000;
                }
                .ok {
                    background-color: #00ff00
                }
            -->
        </style>
    </head>
    <body <?php $this->_printOnLoad(Yii::app()->request->getQuery('sa', 'index'))?>>
    <?php
		if(isset($_GET['sa']))
			$this->test($_GET['sa']);
		else $this->getController()->render('/admin/expressions/test_view');
    ?>
    </body>
</html>
    <?php
    }

    protected function test($which)
    {
        $this->getController()->render('/admin/expressions/test/'.$which);
    }

    private function _printOnLoad($which)
    {
        switch ($which)
        {
            case 'relevance':
                echo ' onload="ExprMgr_process_relevance_and_tailoring(\'\');"';
                break;
            case 'unit':
                echo ' onload="recompute()"';
                break;
        }
    }

    private function _printTitle($which)
    {
        switch ($which)
        {
            case 'index':
                echo 'Test Suite';
                break;
            case 'relevance':
                echo 'Unit Test Relevance';
                break;
            case 'stringspilt':
                echo 'Unit Test String Splitter';
                break;
            case 'functions':
                echo 'Available Functions';
                break;
            case 'data':
                echo 'Current Data';
                break;
            case 'reset_syntax_error_log':
                echo 'Reset Log of Syntax Errors';
                break;
            case 'tokenizer':
                echo 'Unit Test Tokenizer';
                break;
            case 'unit':
                echo 'Unit Test Core Evaluator';
                break;
            case 'conditions2relevance':
                echo 'Preview Conditions to Relevance';
                break;
            case 'navigation_test':
                echo 'Navigation Test';
                break;
            case 'reset_syntax_error_log.php':
                break;
                echo 'Reset Log of Syntax Errors';
                break;
            case 'revert_upgrade_conditions2relevance':
                break;
                echo 'Revert Upgrade Conditions to Relevance';
                break;
            case 'strings_with_expressions':
                echo 'Test Evaluation of Strings Containing Expressions';
                break;
            case 'survey_logic_file':
                echo 'Survey logic file';
                break;
            case 'syntax_errors':
                echo 'Show Log of Syntax Errors';
                break;
            case 'upgrade_conditions2relevance':
                echo 'Upgrade Conditions to Relevance';
                break;
            case 'upgrade_relevance_location':
                echo 'Upgrade Relevance Location';
                break;
            case 'usage':
                echo 'Running Translation Log';
                break;
        }
    }
}
/* End of file expressions.php */
/* Location: ./application/controllers/admin/expressions.php */
