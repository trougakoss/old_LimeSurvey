// $Id: subquestions.js 9692 2011-01-15 21:31:10Z c_schmitz $
var labelcache=[];
$(document).ready(function(){
    $("body").delegate(".code", "keypress", function(e) {
        key=e.which;
        if ( key==null || key==0 || key==8 || key==9  || key==27 )
            return true;
        thischar=String.fromCharCode(key);
        returnvalue=(thischar==thischar.replace(/[^a-zA-Z0-9_]/,''));
        return returnvalue;
    });
    $("body").delegate(".code", "keyup", function() {
        $(this).val($(this).val().replace(/[^a-zA-Z0-9_]/,''));
    });
    $('.tab-page:first .answertable tbody').sortable({   containment:'parent',
        start:startmove,
        update:aftermove,
        distance:3});
    $('.btnaddanswer').click(addinput);
    $('.btndelanswer').click(deleteinput);
    $('#editsubquestionsform').submit(code_duplicates_check)
    $('#labelsetbrowser').dialog({ autoOpen: false,
        modal: true,
        width:800,
        title: lsbrowsertitle});
    $('#quickadd').dialog({ autoOpen: false,
        modal: true,
        width:600,
        title: quickaddtitle});

    $('.btnlsbrowser').click(lsbrowser);
    $('#btncancel').click(function(){
        $('#labelsetbrowser').dialog('close');
    });

    $('#btnlsreplace').click(transferlabels);
    $('#btnlsinsert').click(transferlabels);
    $('#btnqacancel').click(function(){
        $('#quickadd').dialog('close');
    });
    $('#btnqareplace').click(quickaddlabels);
    $('#btnqainsert').click(quickaddlabels);
    $('#labelsets').click(lspreview);
    $('#languagefilter').click(lsbrowser);
    $('.btnquickadd').click(quickadddialog);
    $('#saveaslabel').dialog({ autoOpen: false,
        modal: true,
        width: 300,
        title: saveaslabletitle});
    $('.bthsaveaslabel').click(getlabel);
    $('#btnlacancel').click(function(){
        $('#saveaslabel').dialog('close');
    });
    $('input[name=savelabeloption]:radio').click(setlabel);
    flag = [false, false];
    $('#btnsave').click(savelabel);

    updaterowproperties();
});

function deleteinput()
{

    // 1.) Check if there is at least one answe

    countanswers=$(this).parent().parent().parent().children().length;
    if (countanswers>1)
        {
        // 2.) Remove the table row

        scale_id=removechars($(this).closest('table').attr('id'));
        index = Number($(this).closest('tr').parent().children().index($(this).closest('tr')))+1;
        languages=langs.split(';');

        var x;
        for (x in languages)
            {
            tablerow=$('#answertable_'+languages[x]+'_'+scale_id+' tbody tr:nth-child('+index+')');
            if (x==0) {
                tablerow.fadeTo(400, 0, function(){
                    $(this).remove();
                    updaterowproperties();
                });
            }
            else {
                tablerow.remove();
            }
            rowinfo=tablerow.attr('id').split('_');
            $('#deletedqids').val($('#deletedqids').val()+' '+rowinfo[2]);
        }
    }
    else
        {
        $.blockUI({message:"<p><br/>"+strCantDeleteLastAnswer+"</p>"});
        setTimeout(jQuery.unblockUI,1000);
    }
    updaterowproperties();
}


function addinput()
{
    var sID=$('input[name=sid]').val();
    var gID=$('input[name=gid]').val();
    var qID=$('input[name=qid]').val();

    scale_id=removechars($(this).closest('table').attr('id'));
    newposition = Number($(this).closest('tr').parent().children().index($(this).closest('tr')))+1;
    languages=langs.split(';');


    sNextCode=getNextCode($(this).parent().parent().find('.code').val());
    while ($(this).parent().parent().parent().find('input[value="'+sNextCode+'"]').length>0 && nextcode!=$(this).parent().parent().find('.code').val())
    {
        sNextCode=getNextCode(sNextCode);
    }

    for (x in languages)
        {
        tablerow=$('#answertable_'+languages[x]+'_'+scale_id+' tbody tr:nth-child('+newposition+')');
        var randomid='new'+Math.floor(Math.random()*111111)
        if (x==0) {
            inserthtml='<tr class="row_'+newposition+'" style="display:none;"><td><img class="handle" src="' + sImageURL + 'handle.png" /></td><td><input id="code_'+randomid+'_'+scale_id+'" name="code_'+randomid+'_'+scale_id+'" onkeypress="return goodchars(event,\'1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWZYZ_\')" class="code" type="text" maxlength="5" size="5" value="'+htmlspecialchars(sNextCode)+'" /></td><td><input type="text" size="100" id="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'" name="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'" class="answer" value="'+htmlspecialchars(newansweroption_text)+'"></input><a id="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'_ctrl" href="javascript:start_popup_editor(\'answer_'+languages[x]+'_'+randomid+'_'+scale_id+'\',\'[Subquestion:]('+languages[x]+')\',\''+sID+'\',\''+gID+'\',\''+qID+'\',\'editanswer\',\'editanswer\')" class="editorLink"><img id="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'_popupctrlena" class="btneditanswerena" src="' + sImageURL + 'edithtmlpopup.png" width="16" height="16" border="0" /><img id="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'_popupctrldis" class="btneditanswerdis" alt="Give focus to the HTML editor popup window" src="' + sImageURL + 'edithtmlpopup_disabled.png" style="display: none;" width="16" height="16" align="top" border="0" /></a></td><td><img src="' + sImageURL + 'addanswer.png" class="btnaddanswer" /><img src="' + sImageURL + 'deleteanswer.png" class="btndelanswer" /></td></tr>'
        }
        else
            {
            inserthtml='<tr class="row_'+newposition+'" style="display:none;"><td>&nbsp;</td><td>'+htmlspecialchars(sNextCode)+'</td><td><input type="text" size="100" id="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'" name="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'" class="answer" value="'+htmlspecialchars(newansweroption_text)+'"></input><a id="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'_ctrl" href="javascript:start_popup_editor(\'answer_'+languages[x]+'_'+randomid+'_'+scale_id+'\',\'[Subquestion:]('+languages[x]+')\',\''+sID+'\',\''+gID+'\',\''+qID+'\',\'editanswer\',\'editanswer\')" class="editorLink"><img id="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'_popupctrlena" class="btneditanswerena" src="' + sImageURL + 'edithtmlpopup.png" width="16" height="16" border="0" /><img id="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'_popupctrldis" class="btneditanswerdis" alt="Give focus to the HTML editor popup window" src="' + sImageURL + 'edithtmlpopup_disabled.png" style="display: none;" width="16" height="16" align="top" border="0" /></a></td><td>&nbsp;</td></tr>'
        }
        tablerow.after(inserthtml);
        tablerow.next().find('.btnaddanswer').click(addinput);
        tablerow.next().find('.btndelanswer').click(deleteinput);
        tablerow.next().find('.answer').focus(function(){
            if ($(this).val()==newansweroption_text)
                {
                $(this).val('');
            }
        });
        tablerow.next().find('.code').blur(updatecodes);
    }
    $('.row_'+newposition).fadeIn('slow');
    $('.row_'+newposition).show(); //Workaround : IE does not show with fadeIn only

    $('.tab-page:first .answertable tbody').sortable('refresh');
    updaterowproperties();
}

function startmove(event,ui)
{
    oldindex = Number($(ui.item[0]).parent().children().index(ui.item[0]))+1;
}


function aftermove(event,ui)
{
    // But first we have change the sortorder in translations, too


    var newindex = Number($(ui.item[0]).parent().children().index(ui.item[0]))+1;

    info=$(ui.item[0]).closest('table').attr('id').split("_");
    languages=langs.split(';');
    var x;
    for (x in languages)
        {
        if (x>0) {
            tablerow=$('#tabpage_'+languages[x]+' tbody tr:nth-child('+newindex+')');
            tablebody=$('#tabpage_'+languages[x]).find('tbody');
            if (newindex<oldindex)
                {
                $('#tabpage_'+languages[x]+' tbody tr:nth-child('+newindex+')').before($('#tabpage_'+languages[x]+' tbody tr:nth-child('+oldindex+')'));
            }
            else
                {
                $('#tabpage_'+languages[x]+' tbody tr:nth-child('+newindex+')').after($('#tabpage_'+languages[x]+' tbody tr:nth-child('+oldindex+')'));
                //tablebody.find('.row_'+newindex).after(tablebody.find('.row_'+oldindex));
            }
        }
    }
    updaterowproperties();
}

// This function adjusts the alternating table rows
// if the list changed
function updaterowproperties()
{
    $('.answertable tbody').each(function(){
        var highlight=true;
        $(this).children('tr').each(function(){

            $(this).removeClass('highlight');
            if (highlight){
                $(this).addClass('highlight');
            }
            highlight=!highlight;
        })
    })
}

function updatecodes()
{

}

function getNextCode(sourcecode)
{
    i=1;
    found=true;
    foundnumber=-1;
    sclength = sourcecode.length;
    while (i<=sclength && found == true)
    {
        found=is_numeric(sourcecode.substr(sclength-i,i));
        if (found)
            {
            foundnumber=sourcecode.substr(sclength-i,i);
            i++;
        }
    }
    if (foundnumber==-1)
        {
        return(sourcecode);
    }
    else
        {
        foundnumber++;
        foundnumber=foundnumber+'';
        result=sourcecode.substr(0,sclength-foundnumber.length)+foundnumber;
        return(result);
    }

}

function is_numeric (mixed_var) {
    return (typeof(mixed_var) === 'number' || typeof(mixed_var) === 'string') && mixed_var !== '' && !isNaN(mixed_var);
}

function popupeditor()
{
    input_id=$(this).parent().find('.answer').attr('id');
    start_popup_editor(input_id);
}

function code_duplicates_check()
{
    languages=langs.split(';');
    var dupefound=false;
    $('#tabpage_'+languages[0]+' .answertable tbody').each(function(){
        var codearray=[];
        $(this).find('tr .code').each(function(){
            codearray.push($(this).val());
        })
        if (arrHasDupes(codearray))
            {
            alert(duplicatesubquestioncode);
            dupefound=true;
            return;
        }
        if ($.inArray('other', codearray)!=-1)
        {
            alert(otherisreserved);
            dupefound=true;
            return;
        }
    })
    if (dupefound)
        {
        return false;
    }
}

function lsbrowser()
{
    scale_id=removechars($(this).attr('id'));
    $('#labelsetbrowser').dialog( 'open' );
    surveyid=$('input[name=sid]').val();
    /*
    match=0;
    if ($('#languagefilter').attr('checked')==true)
    {
    match=1;
    }*/
    $.getJSON(lspickurl,{sid:surveyid, match:1},function(json){
        var x=0;
        $("#labelsets").removeOption(/.*/);
        for (x in json)
            {
            $('#labelsets').addOption(json[x][0],json[x][1]);
            if (x==0){
                remind=json[x][0];
            }
        }
        if ($('#labelsets > option').size()>0)
            {
            $('#labelsets').selectOptions(remind);
            lspreview();
            $('#btnlsreplace').removeClass('ui-state-disabled');
            $('#btnlsinsert').removeClass('ui-state-disabled');
            $('#btnlsreplace').attr('disabled','');
            $('#btnlsinsert').attr('disabled','');
        }
        else
            {
            $("#labelsetpreview").html("<p class='ui-state-highlight ui-corner-all ui-notify-message'>"+strNoLabelSet+"</p>");
            $('#btnlsreplace').addClass('ui-state-disabled');
            $('#btnlsinsert').addClass('ui-state-disabled');
            $('#btnlsreplace').attr('disabled','disabled');
            $('#btnlsinsert').attr('disabled','disabled');
        }
    });

}

// previews the labels in a label set after selecting it in the select box
function lspreview()
{
    if ($('#labelsets > option').size()==0)
        {
        return;
    }

    var lsid=$('#labelsets').val();
    surveyid=$('input[name=sid]').val();
    // check if this label set is already cached
    if (!isset(labelcache[lsid]))
        {
        $.ajax({
            url: lsdetailurl,
            dataType: 'json',
            data: {lid:lsid, sid:surveyid},
            cache: true,
            success: function(json){
                $("#labelsetpreview").tabs('destroy');
                $("#labelsetpreview").empty();
                var tabindex='';
                var tabbody='';
                for ( x in json)
                    {

                    language=json[x];
                    for (y in language)
                        {
                        tabindex=tabindex+'<li><a href="#language_'+y+'">'+language[y][1]+'</a></li>';
                        tabbody=tabbody+"<div id='language_"+y+"'><table class='limetable'>";
                        lsrows=language[y][0];
                        tablerows='';
                        var highlight=true;
                        for (z in lsrows)
                            {
                            highlight=!highlight;
                            tabbody=tabbody+'<tbody><tr';
                            if (highlight==true) {
                                tabbody=tabbody+" class='highlight' ";
                            }
                            if (lsrows[z].title==null)
                                {
                                lsrows[z].title='';
                            }
                            tabbody=tabbody+'><td>'+lsrows[z].code+'</td><td>'+lsrows[z].title+'</td></tr><tbody>';
                        }
                        tabbody=tabbody+'<thead><tr><th>'+strcode+'</th><th>'+strlabel+'</th></tr></thead></table></div>';
                    }
                }
                $("#labelsetpreview").append('<ul>'+tabindex+'</ul>'+tabbody);
                labelcache[lsid]='<ul>'+tabindex+'</ul>'+tabbody;
                $("#labelsetpreview").tabs();
        }}
        );
    }
    else
        {
        $("#labelsetpreview").tabs('destroy');
        $("#labelsetpreview").empty();
        $("#labelsetpreview").append(labelcache[lsid]);
        $("#labelsetpreview").tabs();
    }


}

/**
* This is a debug function
* similar to var_dump in PHP
*/
function dump(arr,level) {
    var dumped_text = "";
    if(!level) level = 0;

    //The padding given at the beginning of the line.
    var level_padding = "";
    for(var j=0;j<level+1;j++) level_padding += "    ";

    if(typeof(arr) == 'object') { //Array/Hashes/Objects
        for(var item in arr) {
            var value = arr[item];

            if(typeof(value) == 'object') { //If it is an array,
                dumped_text += level_padding + "'" + item + "' ...\n";
                dumped_text += dump(value,level+1);
            } else {
                dumped_text += level_padding + "'" + item + "' => \"" + value + "\"\n";
            }
        }
    } else { //Stings/Chars/Numbers etc.
        dumped_text = "===>"+arr+"<===("+typeof(arr)+")";
    }
    return dumped_text;
}

function transferlabels()
{
    var sID=$('input[name=sid]').val();
    var gID=$('input[name=gid]').val();
    var qID=$('input[name=qid]').val();

    surveyid=$('input[name=sid]').val();
    if ($(this).attr('id')=='btnlsreplace')
        {
        var lsreplace=true;
    }
    else
        {
        var lsreplace=false;
    }

    if (lsreplace)
        {
        $('.answertable:eq('+scale_id+') tbody tr').each(function(){
            aRowInfo=this.id.split('_');
            $('#deletedqids').val($('#deletedqids').val()+' '+aRowInfo[2]);
        });
    }

    var lsid=$('#labelsets').val();
    $.ajax({
        url: lsdetailurl,
        dataType: 'json',
        data: {lid:lsid, sid:surveyid},
        cache: true,
        success: function(json){
            languages=langs.split(';');
            var x;
            var defaultdata_labels = null;
            for (x in languages)
                {
                lang_x_found_in_label=false;
                var tablerows='';
                var y;
                for (y in json)
                    {

                    language=json[y];
                    var lsrows = new Array();
                    //defaultdata=language[languages[0]][0];
                    for (z in language)
                        {
                        if (z==languages[0])
                            {
                            defaultdata_labels=language[languages[0]];
                        }
                        if (z==languages[x])
                            {
                            lang_x_found_in_label = true;
                            lsrows=language[z][0];
                        }

                        var k;
                        for (k in lsrows)
                            {
                            var randomid='new'+Math.floor(Math.random()*111111)
                            if (x==0) {
                                tablerows=tablerows+'<tr class="row_'+k+'_'+scale_id+'" ><td><img class="handle" src="' + sImageURL + 'handle.png" /></td><td><input class="code" id="code_'+randomid+'_'+scale_id+'" name="code_'+randomid+'_'+scale_id+'" onkeypress="return goodchars(event,\'1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWZYZ_\')" type="text" maxlength="5" size="5" value="'+htmlspecialchars(lsrows[k].code)+'" /></td><td><input type="text" size="100" id="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'" name="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'" class="answer" value="'+htmlspecialchars(lsrows[k].title)+'"></input><a id="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'_ctrl" href="javascript:start_popup_editor(\'answer_'+languages[x]+'_'+randomid+'_'+scale_id+'\',\'[Subquestion:]('+languages[x]+')\',\''+sID+'\',\''+gID+'\',\''+qID+'\',\'editanswer\',\'editanswer\')" class="editorLink"><img id="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'_popupctrlena" class="btneditanswerena" src="' + sImageURL + 'edithtmlpopup.png" width="16" height="16" border="0" /><img id="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'_popupctrldis" class="btneditanswerdis" alt="Give focus to the HTML editor popup window" src="' + sImageURL + 'edithtmlpopup_disabled.png" style="display: none;" width="16" height="16" align="top" border="0" /></a></td><td><img src="' + sImageURL + 'addanswer.png" class="btnaddanswer" /><img src="' + sImageURL + 'deleteanswer.png" class="btndelanswer" /></td></tr>'
                            }
                            else
                                {
                                tablerows=tablerows+'<tr class="row_'+k+'_'+scale_id+'" ><td>&nbsp;</td><td>'+htmlspecialchars(lsrows[k].code)+'</td><td><input type="text" size="100" id="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'" name="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'" class="answer" value="'+htmlspecialchars(lsrows[k].title)+'"></input><a id="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'_ctrl" href="javascript:start_popup_editor(\'answer_'+languages[x]+'_'+randomid+'_'+scale_id+'\',\'[Subquestion:]('+languages[x]+')\',\''+sID+'\',\''+gID+'\',\''+qID+'\',\'editanswer\',\'editanswer\')" class="editorLink"><img id="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'_popupctrlena" class="btneditanswerena" src="' + sImageURL + 'edithtmlpopup.png" width="16" height="16" border="0" /><img id="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'_popupctrldis" class="btneditanswerdis" alt="Give focus to the HTML editor popup window" src="' + sImageURL + 'edithtmlpopup_disabled.png" style="display: none;" width="16" height="16" align="top" border="0" /></a></td><td>&nbsp;</td></tr>'
                            }
                        }
                    }
                }
                if (lang_x_found_in_label === false)
                    {
                    lsrows=defaultdata_labels[0];
                    k=0;
                    for (k in lsrows)
                        {
                        tablerows=tablerows+'<tr class="row_'+k+'_'+scale_id+'" ><td>&nbsp;</td><td>'+htmlspecialchars(lsrows[k].code)+'</td><td><input type="text" size="100" id="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'" name="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'" class="answer" value="'+htmlspecialchars(lsrows[k].title)+'"></input><a id="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'_ctrl" href="javascript:start_popup_editor(\'answer_'+languages[x]+'_'+randomid+'_'+scale_id+'\',\'[Subquestion:]('+languages[x]+')\',\''+sID+'\',\''+gID+'\',\''+qID+'\',\'editanswer\',\'editanswer\')" class="editorLink"><img id="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'_popupctrlena" class="btneditanswerena" src="' + sImageURL + 'edithtmlpopup.png" width="16" height="16" border="0" /><img id="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'_popupctrldis" class="btneditanswerdis" alt="Give focus to the HTML editor popup window" src="' + sImageURL + 'edithtmlpopup_disabled.png" style="display: none;" width="16" height="16" align="top" border="0" /></a></td><td>&nbsp;</td></tr>'
                    }
                }
                if (lsreplace) {
                    $('#answertable_'+languages[x]+'_'+scale_id+' tbody').empty();
                }
                $('#answertable_'+languages[x]+'_'+scale_id+' tbody').append(tablerows);
                // Unbind any previous events
                $('#answertable_'+languages[x]+'_'+scale_id+' .btnaddanswer').unbind('click');
                $('#answertable_'+languages[x]+'_'+scale_id+' .btndelanswer').unbind('click');
                $('#answertable_'+languages[x]+'_'+scale_id+' .answer').unbind('focus');
                $('#answertable_'+languages[x]+'_'+scale_id+' .btnaddanswer').click(addinput);
                $('#answertable_'+languages[x]+'_'+scale_id+' .btndelanswer').click(deleteinput);
                $('#answertable_'+languages[x]+'_'+scale_id+' .answer').focus(function(){
                    if ($(this).val()==newansweroption_text)
                        {
                        $(this).val('');
                    }
                });
            }
            $('#labelsetbrowser').dialog('close');
            $('.tab-page:first .answertable tbody').sortable('refresh');
            updaterowproperties();

    }}
    );


}

function quickaddlabels()
{
    var sID=$('input[name=sid]').val();
    var gID=$('input[name=gid]').val();
    var qID=$('input[name=qid]').val();

    if ($(this).attr('id')=='btnqareplace')
        {
        var lsreplace=true;
    }
    else
        {
        var lsreplace=false;
    }

    if (lsreplace)
        {
        $('.answertable:eq('+scale_id+') tbody tr').each(function(){
            aRowInfo=this.id.split('_');
            $('#deletedqids').val($('#deletedqids').val()+' '+aRowInfo[2]);
        });
    }

    languages=langs.split(';');
    for (x in languages)
        {
        lsrows=$('#quickaddarea').val().split("\n");

        if (lsrows[0].indexOf("\t")==-1)
            {
            separatorchar=';'
        }
        else
            {
            separatorchar="\t";
        }
        tablerows='';
        for (k in lsrows)
            {
            thisrow=lsrows[k].splitCSV(separatorchar);
            if (thisrow.length<=languages.length)
                {
                thisrow.unshift(parseInt(k)+1);
            }
            else
                {
                thisrow[0]=thisrow[0].replace(/[^A-Za-z0-9]/g, "");
            }
            var randomid='new'+Math.floor(Math.random()*111111)

            if (typeof thisrow[parseInt(x)+1]=='undefined')
                {
                thisrow[parseInt(x)+1]=thisrow[1];
            }
            if (x==0) {
                tablerows=tablerows+'<tr class="row_'+k+'" ><td><img class="handle" src="' + sImageURL + 'handle.png" /></td><td><input class="code" id="code_'+randomid+'_'+scale_id+'" name="code_'+randomid+'_'+scale_id+'" type="text" maxlength="5" size="5" value="'+thisrow[0]+'" /></td><td><input type="text" size="100" id="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'" name="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'" class="answer" value="'+thisrow[parseInt(x)+1]+'"></input><a id="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'_ctrl" href="javascript:start_popup_editor(\'answer_'+languages[x]+'_'+randomid+'_'+scale_id+'\',\'[Subquestion:]('+languages[x]+')\',\''+sID+'\',\''+gID+'\',\''+qID+'\',\'editanswer\',\'editanswer\')" class="editorLink"><img id="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'_popupctrlena" class="btneditanswerena" src="' + sImageURL + 'edithtmlpopup.png" width="16" height="16" border="0" /><img id="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'_popupctrldis" class="btneditanswerdis" alt="Give focus to the HTML editor popup window" src="' + sImageURL + 'edithtmlpopup_disabled.png" style="display: none;" width="16" height="16" align="top" border="0" /></a></td><td><img src="' + sImageURL + 'addanswer.png" class="btnaddanswer" /><img src="' + sImageURL + 'deleteanswer.png" class="btndelanswer" /></td></tr>'
            }
            else
                {
                tablerows=tablerows+'<tr class="row_'+k+'" ><td>&nbsp;</td><td>&nbsp;</td><td><input type="text" size="100" id="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'" name="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'" class="answer" value="'+thisrow[parseInt(x)+1]+'"></input><a id="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'_ctrl" href="javascript:start_popup_editor(\'answer_'+languages[x]+'_'+randomid+'_'+scale_id+'\',\'[Subquestion:]('+languages[x]+')\',\''+sID+'\',\''+gID+'\',\''+qID+'\',\'editanswer\',\'editanswer\')" class="editorLink"><img id="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'_popupctrlena" class="btneditanswerena" src="' + sImageURL + 'edithtmlpopup.png" width="16" height="16" border="0" /><img id="answer_'+languages[x]+'_'+randomid+'_'+scale_id+'_popupctrldis" class="btneditanswerdis" alt="Give focus to the HTML editor popup window" src="' + sImageURL + 'edithtmlpopup_disabled.png" style="display: none;" width="16" height="16" align="top" border="0" /></a></td><td>&nbsp;</td></tr>'
            }
        }
        if (lsreplace) {
            $('#answertable_'+languages[x]+'_'+scale_id+' tbody').empty();
        }
        $('#answertable_'+languages[x]+'_'+scale_id+' tbody').append(tablerows);
        // Unbind any previous events
        $('#answertable_'+languages[x]+'_'+scale_id+' .btnaddanswer').unbind('click');
        $('#answertable_'+languages[x]+'_'+scale_id+' .btndelanswer').unbind('click');
        $('#answertable_'+languages[x]+'_'+scale_id+' .answer').unbind('focus');
        $('#answertable_'+languages[x]+'_'+scale_id+' .btnaddanswer').click(addinput);
        $('#answertable_'+languages[x]+'_'+scale_id+' .btndelanswer').click(deleteinput);
        $('#answertable_'+languages[x]+'_'+scale_id+' .answer').focus(function(){
            if ($(this).val()==newansweroption_text)
                {
                $(this).val('');
            }
        });
    }
    $('#quickadd').dialog('close');
    $('#quickaddarea').val('');
    $('.answertable tbody').sortable('refresh');
    updaterowproperties();
}

function getlabel()
{
    var answer_table = $(this).parent().children().eq(0);
    scale_id=removechars($(this).attr('id'));

    $('#saveaslabel').dialog('open');
    updaterowproperties();
}

function setlabel()
{
    switch($(this).attr('id'))
    {
        case 'newlabel':
        if(!flag[0]){
            $('#lasets').remove();
            $($(this).next().next()).after('<label for="laname">Label Set Name :</label> ' +
            '<input type="text" name="laname" id="laname">');
            flag[0] = true;
            flag[1] = false;
        }
        break;

        case 'replacelabel':
        if(!flag[1]){
            $('#laname').remove();
            $('[for=laname]').remove();
            $($(this).next().next()).after('<select name="laname" id="lasets">');
            jQuery.getJSON(lanameurl, function(data) {
                $.each(data, function(key, val) {
                    $('#lasets').append('<option value="' + key + '">' + val + '</option>');
                });
            });
            $('#lasets').append('</select>');
            flag[1] = true;
            flag[0] = false;
        }
        break;
    }
}

function savelabel()
{
    var lid = $('#lasets').val() ? $('#lasets').val() : 0;
    if(lid == 0)
        {
        var response = ajaxcheckdup();
        response.complete(function() {
            if(check)
                {
                ajaxreqsave();
            }
        })
    }
    else
        {
        $('#dialog-confirm-replace').dialog({
            resizable: false,
            height: 160,
            modal: true,
            buttons: [{
                text: ok,
                click: function() {
                    $(this).dialog("close");
                    ajaxreqsave();
            }},{
                text: cancel,
                click: function() {
                    check = false;
                    $(this).dialog("close");
            }}
            ]
        });
    }
}

function ajaxcheckdup()
{
    check = true; //set check to true everytime on call
    return jQuery.getJSON(lanameurl, function(data) {
        $.each(data, function(key, val) {
            if($('#laname').val() == val)
                {
                $("#dialog-duplicate").dialog({
                    resizable: false,
                    height: 160,
                    modal: true,
                    buttons: [{
                        text: ok,
                        click: function() {
                            $(this).dialog("close");
                        }
                    }]
                });
                check = false;
                return false;
            }
        });
    });
}

function ajaxreqsave() {
    var lid = $('#lasets').val() ? $('#lasets').val() : 0;

    // get code for the current scale
    var code = new Array();
    $('.code').each(function(index) {
        if($(this).attr('id').substr(-1) === scale_id)
            code.push($(this).val());
    });

    answers = new Object();
    languages = langs.split(';');

    for(x in languages)
        {
        answers[languages[x]] = new Array();
        $('.answer').each(function(index) {
            if($(this).attr('id').substr(-1) === scale_id && $(this).attr('id').indexOf(languages[x]) != -1)
                answers[languages[x]].push($(this).val());
        });
    }


    $.post(lasaveurl, { laname: $('#laname').val(), lid: lid, code: code, answers: answers }, function(data) {
        $("#saveaslabel").dialog('close');
        if(jQuery.parseJSON(data) == "ok")
            {
            $("#dialog-result").html(lasuccess);
            $('#dialog-result').dialog({
                height: 160,
                width: 250,
                buttons: [{
                    text: ok,
                    click: function() {
                        $(this).dialog("close");
                    }
                }]
            });
        }
        else
            {
            $("#dialog-result").html('lafail');
            $('#dialog-result').dialog({
                height: 160,
                width: 250,
                buttons: [{
                    text: ok,
                    click: function() {
                        $(this).dialog("close");
                    }
                }]
            });
        }
    });
}


function quickadddialog()
{
    scale_id=removechars($(this).attr('id'));
    $('#quickadd').dialog('open');
}

