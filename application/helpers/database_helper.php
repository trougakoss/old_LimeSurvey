<?php
if ( !defined('BASEPATH')) exit('No direct script access allowed');
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
function dbExecuteAssoc($sql,$inputarr=false,$silent=false)
{
    $error = '';
    try {
        if($inputarr)
        {
            $dataset=Yii::app()->db->createCommand($sql)->bindValues($inputarr)->query();	//Checked
        }
        else
        {
            $dataset=Yii::app()->db->createCommand($sql)->query();

        }
    } catch(CDbException $e) {
        $error = $e->getMessage();
        $dataset=false;
    }

    if (!$silent && !$dataset)
    {
        safeDie('Error executing query in dbExecuteAssoc:'.$error);
    }
    return $dataset;
}


function dbQueryOrFalse($sql)
{
    try {
        $dataset=Yii::app()->db->createCommand($sql)->query();
    } catch(CDbException $e) {
        $dataset=false;
    }
    return $dataset;
}


function dbSelectLimitAssoc($sql,$numrows=0,$offset=0,$inputarr=false,$dieonerror=true)
{
    $query = Yii::app()->db->createCommand($sql.= " ");
    if ($numrows)
    {
        if ($offset)
        {
            $query->limit($numrows, $offset);
        }
        else
        {
            $query->limit($numrows, 0);
        }
    }
    if($inputarr)
    {
        $query->bindValues($inputarr);    //Checked
    }
    try
    {
        $dataset=$query->query();
    }
    catch (CDbException $e)
    {
        $dataset=false;
    }
    if (!$dataset && $dieonerror) {safeDie('Error executing query in dbSelectLimitAssoc:'.$query->text);}
    return $dataset;
}


/**
* Returns the first row of values of the $sql query result
* as a 1-dimensional array
*
* @param mixed $sql
*/
function dbSelectColumn($sql)
{
    $dataset=Yii::app()->db->createCommand($sql)->query();
    if ($dataset->count() > 0)
    {
        $fields = array_keys($dataset[0]);
        $firstfield = $fields[0];
        $resultarray=array();
        foreach ($dataset->readAll() as $row)
        {
            $resultarray[] = $row[$firstfield];
        }
        /**while ($row = $dataset->fetchRow()) {
        $resultarray[]=$row[0];
        }*/
    }
    else
        safeDie('No results were returned from the query :'.$sql);
    return $resultarray;
}


/**
* This functions quotes fieldnames accordingly
*
* @param mixed $id Fieldname to be quoted
*/

function dbQuoteID($id)
{
    switch (Yii::app()->db->getDriverName())
    {
        case "mysqli" :
        case "mysql" :
            return "`".$id."`";
            break;
        case "mssql" :
        case "sqlsrv" :
            return "[".$id."]";
            break;
        case "pgsql":
            return "\"".$id."\"";
            break;
        default:
            return $id;
    }
}

/**
 * Return the random function to use in ORDER BY sql statements
 *
 * @return string
 */
function dbRandom()
{
    $driver = Yii::app()->db->getDriverName();

    // Looked up supported db-types in InstallerConfigForm.php
    // Use below statement to find them
    //$configForm = new InstallerConfigForm();
    //$dbTypes    = $configForm->db_names; //Supported types are in this array

    switch ($driver)
    {
        case 'mssql':
        case 'sqlsrv':
            $srandom='NEWID()';
            break;

        case 'pgsql':
            $srandom='RANDOM()';
            break;

        case 'mysql':
        case 'mysqli':
            $srandom='RAND()';
            break;

        default:
            //Some db type that is not mentioned above, could fail and if so should get an entry above.
            $srandom= 0 + lcg_value()*(abs(1));
            break;
    }
    
    return $srandom;

}

/**
*  Return a sql statement for finding LIKE named tables
*  Be aware that you have to escape underscor chars by using a backslash
* otherwise you might get table names returned you don't want
*
* @param mixed $table
*/
function dbSelectTablesLike($table)
{
    switch (Yii::app()->db->getDriverName()) {
        case 'mysqli':
        case 'mysql' :
            return "SHOW TABLES LIKE '$table'";
        case 'mssql' :
        case 'sqlsrv' :
            return "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES where TABLE_TYPE='BASE TABLE' and TABLE_NAME LIKE '$table'";
        case 'pgsql' :
            $table=str_replace('\\','\\\\',$table);
            return "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' and table_name like '$table'";
        default: safeDie ("Couldn't create 'select tables like' query for connection type '".Yii::app()->db->getDriverName()."'");
    }
}

/**
* Gets the table names. Do not prefix.
* @param string $table String to match
* @uses dbSelectTablesLike() To get the tables like sql query
* @return array Array of matched table names
*/
function dbGetTablesLike($table)
{
    return (array) Yii::app()->db->createCommand(dbSelectTablesLike("{{{$table}}}"))->queryAll();
}
