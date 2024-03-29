<script type='text/javascript'>
    var strdeleteconfirm='<?php $clang->eT('Do you really want to delete this response?', 'js'); ?>';
    var strDeleteAllConfirm='<?php $clang->eT('Do you really want to delete all marked responses?', 'js'); ?>';
    var noFilesSelectedForDeletion = '<?php $clang->eT('Please select at least one file for deletion', 'js'); ?>';
    var noFilesSelectedForDnld = '<?php $clang->eT('Please select at least one file for download', 'js'); ?>';
</script>
<div class='menubar'>
    <div class='menubar-title ui-widget-header'>
        <strong><?php $clang->eT("Data view control"); ?></strong></div>
    <div class='menubar-main'>
        <div class='menubar-left'>
            <?php if (!isset($_POST['sql']))
                { ?>
                <a href='<?php echo $this->createUrl("admin/browse/index/surveyid/$surveyid/all/start/0/limit/$limit"); ?>'>
                    <img src='<?php echo $sImageURL; ?>databegin.png' alt='<?php $clang->eT("Show start..."); ?>' /></a>
                <a href='<?php echo $this->createUrl("admin/browse/index/surveyid/$surveyid/all/start/$last/limit/$limit"); ?>'>
                    <img src='<?php echo $sImageURL; ?>databack.png' alt='<?php $clang->eT("Show previous.."); ?>' /></a>
                <img src='<?php echo $sImageURL; ?>blank.gif' width='13' height='20' alt='' />

                <a href='<?php echo $this->createUrl("admin/browse/index/surveyid/$surveyid/all/start/$next/limit/$limit"); ?>' title='<?php $clang->eT("Show next..."); ?>' >
                    <img src='<?php echo $sImageURL; ?>dataforward.png' alt='<?php $clang->eT("Show next.."); ?>' /></a>
                <a href='<?php echo $this->createUrl("admin/browse/index/surveyid/$surveyid/all/start/$end/limit/$limit"); ?>' title='<?php $clang->eT("Show last..."); ?>' >
                    <img src='<?php echo $sImageURL; ?>dataend.png' alt='<?php $clang->eT("Show last.."); ?>' /></a>
                <img src='<?php echo $sImageURL; ?>separator.gif' class='separator' alt='' />
                <?php
                }
                $selectshow = '';
                $selectinc = '';
                $selecthide = '';

                if (incompleteAnsFilterState() == "inc")
                {
                    $selectinc = "selected='selected'";
                }
                elseif (incompleteAnsFilterState() == "filter")
                {
                    $selecthide = "selected='selected'";
                }
                else
                {
                    $selectshow = "selected='selected'";
                }
            ?>
            <form action='<?php echo $this->createUrl("admin/browse/index/surveyid/$surveyid/all/"); ?>' id='browseresults' method='post'>
                    <img src='<?php echo $sImageURL; ?>blank.gif' width='31' height='20' alt='' />
                    <?php $clang->eT("Records displayed:"); ?><input type='text' size='4' value='<?php echo $dtcount2; ?>' name='limit' id='limit' />
                    &nbsp;&nbsp; <?php $clang->eT("Starting from:"); ?><input type='text' size='4' value='<?php echo $start; ?>' name='start' id='start' />
                    &nbsp;&nbsp; <input type='submit' value='<?php $clang->eT("Show"); ?>' />
                    &nbsp;&nbsp; <?php $clang->eT("Display:"); ?> <select name='filterinc' onchange='javascript:submit();'>
                        <option value='show' <?php echo $selectshow; ?>><?php $clang->eT("All responses"); ?></option>
                        <option value='filter' <?php echo $selecthide; ?>><?php $clang->eT("Completed responses only"); ?></option>
                        <option value='incomplete' <?php echo $selectinc; ?>><?php $clang->eT("Incomplete responses only"); ?></option>
                    </select>
                <input type='hidden' name='sid' value='<?php echo $surveyid; ?>' />
                <input type='hidden' name='action' value='browse' />
                <input type='hidden' name='subaction' value='all' />

                <?php if (isset($_POST['sql']))
                    { ?>
                    <input type='hidden' name='sql' value='<?php echo HTMLEscape($_POST['sql']); ?>' />
                    <?php } ?>
            </form></div>
    </div>
</div>

<form action='<?php echo $this->createUrl("admin/browse/index/surveyid/$surveyid/all"); ?>' id='resulttableform' method='post'>

<!-- DATA TABLE -->
<?php if ($fncount < 10) { ?>
    <table class='browsetable' style='width:100%'>
    <?php } else { ?>
    <table class='browsetable'>
    <?php } ?>

<thead>
    <tr>
        <th><input type='checkbox' id='selectall'></th>
        <th><?php $clang->eT('Actions'); ?></th>
        <?php
            foreach ($fnames as $fn)
            {
                if (!isset($currentgroup))
                {
                    $currentgroup = $fn[1];
                    $gbc = "odd";
                }
                if ($currentgroup != $fn[1])
                {
                    $currentgroup = $fn[1];
                    if ($gbc == "odd")
                    {
                        $gbc = "even";
                    }
                    else
                    {
                        $gbc = "odd";
                    }
                }
            ?>
            <th class='<?php echo $gbc; ?>'>
                <strong><?php echo flattenText(stripJavaScript($fn[1]), true); ?></strong>
            </th>
            <?php } ?>
    </tr>
</thead>
<tfoot>
    <tr>
        <td colspan=<?php echo $fncount + 2; ?>>
            <?php if (hasSurveyPermission($iSurveyId, 'responses', 'delete')) { ?>
                <img id='imgDeleteMarkedResponses' src='<?php echo $sImageURL; ?>token_delete.png' alt='<?php $clang->eT('Delete marked responses'); ?>' />
                <?php } ?>
            <?php if (hasFileUploadQuestion($iSurveyId)) { ?>
                <img id='imgDownloadMarkedFiles' src='<?php echo $sImageURL; ?>down_all.png' alt='<?php $clang->eT('Download marked files'); ?>' />
                <?php } ?>
        </td>
    </tr>
            </tfoot>
