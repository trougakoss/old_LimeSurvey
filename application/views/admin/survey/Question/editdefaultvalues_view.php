    <div class='header ui-widget-header'><?php $clang->eT('Edit default answer values') ?></div>
    <form class='form30' id='frmdefaultvalues' name='frmdefaultvalues'
            action='<?php echo $this->createUrl('admin/database/index') ?>' method='post'>
        <div id="tabs">
            <ul>
<?php
    foreach ($questlangs as $language)
    {
?>
                <li><a href='#df_<?php echo $language ?>'><?php echo getLanguageNameFromCode($language, false) ?></a></li>
<?php
    }
?>
            </ul>
<?php
    foreach ($questlangs as $language)
    {
?>
            <div id='df_<?php echo $language ?>'>
                <ul>
<?php
        if ($qtproperties[$questionrow['type']]['answerscales'] > 0)
        {
            for ($scale_id = 0; $scale_id < $qtproperties[$questionrow['type']]['answerscales']; $scale_id++)
            {
                $opts = $langopts[$language][$questionrow['type']][$scale_id];
?>
                    <li>
                        <label for='defaultanswerscale_<?php echo "{$scale_id}_{$language}" ?>'>
                            <?php $qtproperties[$questionrow['type']]['answerscales'] > 1 ? sprintf($clang->gT('Default answer for scale %s:'), $scale_id) : sprintf($clang->gT('Default answer value:'), $scale_id) ?>
                        </label>

                        <select name='defaultanswerscale_<?php echo "{$scale_id}_{$language}" ?>' id='defaultanswerscale_<?php echo "{$scale_id}_{$language}" ?>'>

                            <option value=''<?php is_null($opts['defaultvalue']) ? ' selected="selected"' : '' ?>>
                                <?php $clang->eT('<No default value>') ?>
                            </option>
<?php
                foreach ($opts['answers'] as $answer)
                {
                    $answer = $answer->attributes;
?>                          <option<?php $answer['code'] == $opts['defaultvalue'] ? ' selected="selected"' : '' ?> value="<?php echo $answer['code'] ?>"><?php echo $answer['answer'] ?></option>
<?php
                }
?>
                        </select>
                    </li>
<?php
                if ($questionrow['other'] == 'Y')
                {
?>
                    <li>
                        <label for='other_<?php echo "{$scale_id}_{$language}" ?>'>
                            <?php $clang->eT("Default value for option 'Other':")?>
                        </label>
                        <input type='text' name='other_<?php echo "{$scale_id}_{$language}" ?>' value='<?php echo $opts['Ydefaultvalue'] ?>' id='other_<?php echo "{$scale_id}_{$language}" ?>'>
                    </li>
<?php
                }
            }
        }

        // If there are subquestions and no answerscales
        if ($qtproperties[$questionrow['type']]['answerscales'] == 0 && $qtproperties[$questionrow['type']]['subquestions'] > 0)
        {
            for ($scale_id = 0; $scale_id < $qtproperties[$questionrow['type']]['subquestions']; $scale_id++)
            {
                $opts = $langopts[$language][$questionrow['type']][$scale_id];

                if ($qtproperties[$questionrow['type']]['subquestions'] > 1)
                {
?>
                    <div class='header ui-widget-header'>
                        <?php echo sprintf($clang->gT('Default answer for scale %s:'), $scale_id) ?>
                    </div>
<?php
                }
?>
                    <ul>
<?php
                switch($questionrow['type'])
                {
                    case 'L':
                    case 'M':
                    case 'O':
                    case 'P':
                    case '!':
                        $inputStyle='enum';
                        break;
                    case 'K':
                    case 'Q':
                        $inputStyle='text';
                        break;
                }
                if ($inputStyle == 'enum')
                {
                    foreach ($opts['sqresult'] as $aSubquestion)
                    {
?>
                        <li>
                            <label for='defaultanswerscale_<?php echo "{$scale_id}_{$language}_{$aSubquestion['qid']}" ?>'>
                                   <?php echo "{$aSubquestion['title']}: " . flattenText($aSubquestion['question']) ?>
                            </label>
                            <select name='defaultanswerscale_<?php echo "{$scale_id}_{$language}_{$aSubquestion['qid']}" ?>'
                                    id='defaultanswerscale_<?php echo "{$scale_id}_{$language}_{$aSubquestion['qid']}" ?>'>
<?php
                        foreach ($aSubquestion['options'] as $value => $label)
                        {
?>
                                <option value="<?php echo $value ?>"<?php $value == $aSubquestion['defaultvalue'] ? ' selected="selected"' : '' ?>><?php echo $label ?></option>
<?php
                        }
?>
                            </select>
                        </li>
<?php
                    }
                }
                if ($inputStyle == 'text')
                {
                    foreach ($opts['sqresult'] as $aSubquestion)
                    {
                        ?>
                        <li>
                            <label for='defaultanswerscale_<?php echo "{$scale_id}_{$language}_{$aSubquestion['qid']}" ?>'>
                                   <?php echo "{$aSubquestion['title']}: " . flattenText($aSubquestion['question']) ?>
                            </label>
                            <textarea cols='50' name='defaultanswerscale_<?php echo "{$scale_id}_{$language}_{$aSubquestion['qid']}" ?>'
                                    id='defaultanswerscale_<?php echo "{$scale_id}_{$language}_{$aSubquestion['qid']}" ?>'>
                                <?php echo $aSubquestion['defaultvalue'] ?>
                            </textarea>
                        </li>
<?php
                    }
                }
?>
                    </ul>
<?php
            }
        }
        if ($qtproperties[$questionrow['type']]['answerscales']==0 && $qtproperties[$questionrow['type']]['subquestions']==0)
        {
            /*
                    case 'D':
                    case 'N':
                    case 'S':
                    case 'T':
                    case 'U':*
             */
?>
                    <li>
                        <textarea cols='50' name='defaultanswerscale_<?php echo "0_{$language}_0" ?>'
                                id='defaultanswerscale_<?php echo "0_{$language}_0" ?>'>
                            <?php echo "TODO - not sure how to access the default value" ?>
                        </textarea>           
                    </li>
<?php
        }

        if ($language == $baselang && count($questlangs) > 1)
        {
?>
                    <li>
                        <label for='samedefault'>
                            <?php $clang->eT('Use same default value across languages:') ?>
                        </label>
                        <input type='checkbox' name='samedefault' id='samedefault'<?php $questionrow['same_default'] ? ' checked="checked"' : '' ?> />
                    </li>
<?php
        }
?>
                </ul>
            </div>
<?php
    }
?>
        </div>
        <input type='hidden' id='action' name='action' value='updatedefaultvalues' />
        <input type='hidden' id='sid' name='sid' value='<?php echo $surveyid ?>' />
        <input type='hidden' id='gid' name='gid' value='<?php echo $gid ?>' />
        <input type='hidden' id='qid' name='qid' value='<?php echo $qid ?>' />
        <input type='submit' value='<?php $clang->eT('Save') ?>'/>
    </form>
