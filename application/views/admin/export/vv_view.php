<form id='vvexport' method='post' action='<?php echo $this->createUrl("admin/export/vvexport/surveyid/$surveyid/subaction/export");?>'>
<div class='header ui-widget-header'><?php $clang->eT("Export a VV survey file");?></div>
<ul>
<li>
<label for='sid'><?php $clang->eT("Export Survey");?>:</label>
<input type='text' size='10' value='<?php echo $surveyid;?>' id='sid' name='sid' readonly='readonly' />
</li>
<li>
 <label for='filterinc'><?php $clang->eT("Export");?>:</label>
 <select name='filterinc' id='filterinc'>
  <option value='filter' <?php echo $selecthide;?>><?php $clang->eT("Completed responses only");?></option>
  <option value='show' <?php echo $selectshow;?>><?php $clang->eT("All responses");?></option>
  <option value='incomplete' <?php echo $selectinc;?>><?php $clang->eT("Incomplete responses only");?></option>
 </select>
</li>
<li>
 <label for='extension'><?php $clang->eT("File Extension");?>: </label>
 <input type='text' id='extension' name='extension' size='3' value='csv' /><span style='font-size: 7pt'>*</span>
</li>
</ul>
<p><input type='submit' value='<?php $clang->eT("Export results");?>' />&nbsp;
<input type='hidden' name='subaction' value='export' />
</form>

<p><span style='font-size: 7pt'>* <?php $clang->eT("For easy opening in MS Excel, change the extension to 'tab' or 'txt'");?></span><br />