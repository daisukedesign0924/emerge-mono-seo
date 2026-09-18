(function($){
    'use strict';
    function tab(name){
        $('.emseo-tabs button').removeClass('on').filter('[data-tab="'+name+'"]').addClass('on');
        $('.emseo-tab').removeClass('on').filter('[data-panel="'+name+'"]').addClass('on');
    }
    function collect($root){
        var fields={};
        $root.find('[data-emseo]').each(function(){fields[$(this).data('emseo')]=$(this).is(':checkbox')?($(this).is(':checked')?'1':'0'):$(this).val();});
        return fields;
    }
    $(document).on('click','.emseo-pick-image',function(){
        var input=$(this).siblings('input[type="url"]');
        var frame=wp.media({title:'SNSシェア画像を選択',button:{text:'この画像を使用'},library:{type:'image'},multiple:false});
        frame.on('select',function(){input.val(frame.state().get('selection').first().toJSON().url).trigger('input');});frame.open();
    });
    $('.emseo-tabs').on('click','button',function(){tab($(this).data('tab'));});
    var queryTab=new URLSearchParams(location.search).get('tab');if(queryTab)tab(queryTab);
    var settingsForm=$('.emseo-workspace-main>form'),saveNote=settingsForm.find('.emseo-save span'),saveButton=settingsForm.find('.emseo-save [type="submit"]');
    settingsForm.on('input change','input,textarea,select',function(){saveNote.text('未保存の変更があります。').addClass('is-dirty');saveButton.addClass('is-dirty');});
    $('#emseo-page-search').on('input',function(){var q=String(this.value||'').toLowerCase();$('.emseo-page-row').each(function(){$(this).toggle(!q||String($(this).data('search')).indexOf(q)>=0);});});
    $(document).on('click','.emseo-page-row',function(){var id=$(this).data('post'),tpl=document.getElementById('emseo-fields-'+id),m=$('#emseo-page-modal');$('#emseo-modal-title').text($(this).find('b').text());$('#emseo-modal-fields').html(tpl?tpl.innerHTML:'');m.data('post',id).prop('hidden',false);});
    $(document).on('click','#emseo-page-modal [data-action="cancel"]',function(){$('#emseo-page-modal').prop('hidden',true);});
    $(document).on('click','#emseo-page-modal [data-action="save"]',function(){var m=$('#emseo-page-modal'),b=$(this).prop('disabled',true);$.post(emseoAdmin.ajaxUrl,{action:'emseo_save_page',nonce:emseoAdmin.nonce,post_id:m.data('post'),fields:JSON.stringify(collect(m))},function(r){b.prop('disabled',false);$('#emseo-modal-message').text(r.data&&r.data.message||'');if(r.success)setTimeout(function(){location.reload();},500);});});
    $(document).on('click','.emseo-field-tabs button',function(){var root=$(this).closest('.emseo-post-fields'),section=$(this).data('seo-section');root.find('.emseo-field-tabs button').removeClass('on');$(this).addClass('on');root.find('.emseo-field-section').removeClass('on').filter('[data-seo-panel="'+section+'"]').addClass('on');});
    $(document).on('input','[data-emseo="title"], [data-emseo="description"]',function(){var root=$(this).closest('.emseo-post-fields'),key=$(this).data('emseo');root.find('[data-preview="'+key+'"]').text($(this).val()|| (key==='title'?'ページタイトル':'検索結果に表示する説明を入力してください。'));});
    $(document).on('click','.emseo-ai-suggest',function(){
        var button=$(this),root=button.closest('.emseo-post-fields'),postId=root.data('post-id'),result=root.find('.emseo-ai-result');
        button.prop('disabled',true).text('提案を作成中…');result.prop('hidden',false).html('<p>ページを分析しています。</p>');
        $.post(emseoAdmin.ajaxUrl,{action:'emseo_ai_suggest',nonce:emseoAdmin.nonce,post_id:postId},function(response){
            button.prop('disabled',false).text('AIに改善案を作ってもらう');
            if(!response.success){result.html('<p class="error">'+((response.data&&response.data.message)||'提案を取得できませんでした。')+'</p>');return;}
            var data=response.data.suggestions||{},html='<strong>AIからの提案</strong>';
            Object.keys(data).forEach(function(key){html+='<div class="emseo-suggestion"><small>'+key+'</small><p></p><button type="button" class="button" data-apply="'+key+'">この案を反映</button></div>';});
            result.html(html).data('suggestions',data);result.find('.emseo-suggestion').each(function(){var key=$(this).find('[data-apply]').data('apply');$(this).find('p').text(data[key]);});
        });
    });
    $(document).on('click','.emseo-suggestion [data-apply]',function(){var result=$(this).closest('.emseo-ai-result'),key=$(this).data('apply'),value=(result.data('suggestions')||{})[key]||'',root=$(this).closest('.emseo-post-fields');root.find('[data-emseo="'+key+'"]').val(value).trigger('input');$(this).text('反映済み').prop('disabled',true);});
})(jQuery);
