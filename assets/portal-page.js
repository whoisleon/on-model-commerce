(function(){
  function initProductSource(portal){
    if(!portal||portal.dataset.sourceReady==='1')return;
    portal.dataset.sourceReady='1';
    var fileInputs=Array.prototype.slice.call(portal.querySelectorAll('input[name^="product-file-"]'));
    if(fileInputs.length){
    if(!fileInputs[0].closest('.aip-dropzone')){
      var dropzone=document.createElement('div');
      dropzone.className='aip-dropzone';
      dropzone.innerHTML='<div class="aip-drop-prompt"><div class="aip-drop-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M20 15v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-4"/></svg></div><div class="aip-drop-text"><strong>Drop up to 4 product files here</strong>or <button type="button" class="aip-drop-browse">browse from your device</button></div><small class="aip-drop-sub">JPG, PNG, WEBP, PDF, or ZIP · 20 MB per file</small></div><div class="aip-drop-preview-list" hidden></div><div class="aip-drop-footer" hidden><span class="aip-drop-count"></span><button type="button" class="aip-drop-add">+ Add another file</button></div>';
      var firstWrap=fileInputs[0].closest('.wpcf7-form-control-wrap')||fileInputs[0];
      firstWrap.parentNode.insertBefore(dropzone,firstWrap);
      fileInputs.forEach(function(input){dropzone.appendChild(input);input.classList.add('aip-hidden-file-input');});
      var browseBtn=dropzone.querySelector('.aip-drop-browse');
      var addBtn=dropzone.querySelector('.aip-drop-add');
      var previewList=dropzone.querySelector('.aip-drop-preview-list');
      var footer=dropzone.querySelector('.aip-drop-footer');
      var countEl=dropzone.querySelector('.aip-drop-count');
      var fileIcon='<span class="aip-drop-file-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h5"/></svg></span>';
      function formatSize(bytes){if(!bytes)return '';if(bytes<1024*1024)return (bytes/1024).toFixed(1)+' KB';return (bytes/(1024*1024)).toFixed(1)+' MB';}
      function selectedInputs(){return fileInputs.filter(function(input){if(!input.files)return false;return input.files.length>0;});}
      function nextEmptyInput(){return fileInputs.find(function(input){return !input.files||!input.files.length;});}
      function openPicker(){var input=nextEmptyInput();if(input)input.click();}
      function updatePreview(){var selected=selectedInputs();previewList.textContent='';selected.forEach(function(input){var file=input.files[0];var isImg=file&&(file.type?file.type.indexOf('image/')===0:/\.(jpg|jpeg|png|webp|gif|svg|avif|bmp)$/i.test(file.name));var thumbHtml=isImg?'<img class="aip-drop-thumb" src="'+URL.createObjectURL(file)+'" alt="" style="width:44px; height:44px; object-fit:cover; border-radius:10px; flex:0 0 44px; border:1px solid #ddd5e7; background:#fff;">':fileIcon;var card=document.createElement('div');card.className='aip-drop-preview';card.innerHTML=thumbHtml+'<div class="aip-drop-file-info"><strong class="aip-drop-file-name"></strong><small class="aip-drop-file-size"></small></div><button type="button" class="aip-drop-file-remove" title="Remove file" aria-label="Remove selected file">&times;</button>';card.querySelector('.aip-drop-file-name').textContent=file.name;card.querySelector('.aip-drop-file-size').textContent=formatSize(file.size)+' · Ready for upload';card.querySelector('.aip-drop-file-remove').addEventListener('click',function(){input.value='';input.dispatchEvent(new Event('change',{bubbles:true}));});previewList.appendChild(card);});var count=selected.length;previewList.hidden=count===0;footer.hidden=count===0;countEl.textContent=count+' of 4 files selected';addBtn.hidden=count>=4;dropzone.classList.toggle('has-file',count>0);}
      ['dragenter','dragover'].forEach(function(evt){dropzone.addEventListener(evt,function(e){e.preventDefault();e.stopPropagation();dropzone.classList.add('is-dragover');},false);});
      ['dragleave','drop'].forEach(function(evt){dropzone.addEventListener(evt,function(e){e.preventDefault();e.stopPropagation();dropzone.classList.remove('is-dragover');},false);});
      dropzone.addEventListener('drop',function(e){var files=[];if(e.dataTransfer){if(e.dataTransfer.files)files=Array.prototype.slice.call(e.dataTransfer.files);}var available=fileInputs.length-selectedInputs().length;var error=portal.querySelector('.aip-form-error');if(files.length>available){if(error)error.textContent='You can upload up to 4 files.';}files.slice(0,available).forEach(function(file){var input=nextEmptyInput();if(input){var transfer=new DataTransfer();transfer.items.add(file);input.files=transfer.files;}});updatePreview();},false);
      fileInputs.forEach(function(input){input.addEventListener('change',updatePreview);});
      browseBtn.addEventListener('click',openPicker);
      addBtn.addEventListener('click',openPicker);
      updatePreview();
      function clearFormFiles(){
        var forms=portal.querySelectorAll('form.wpcf7-form, form');
        forms.forEach(function(form){
          form.classList.remove('submitting','sent','failed','invalid','spam');
          form.setAttribute('data-status','init');
          var submitBtn=form.querySelector('input[type="submit"], button[type="submit"]');
          if(submitBtn)submitBtn.disabled=false;
        });
        fileInputs.forEach(function(input){
          input.value='';
          try{input.files=(new DataTransfer()).files;}catch(e){}
        });
        var refInput=portal.querySelector('input[name="product-reference"]');
        if(refInput)refInput.value='';
        var emailInput=portal.querySelector('input[name="your-email"]');
        if(emailInput)emailInput.value='';
        var notesInput=portal.querySelector('textarea[name="your-message"]');
        if(notesInput)notesInput.value='';
        updatePreview();
      }
      portal.addEventListener('reset',clearFormFiles);
      document.addEventListener('wpcf7reset',function(e){if(!e.target||e.target.closest('.aip-portal')===portal||e.target===portal){clearFormFiles();}});
    }
    }
    function sync(){
      var selected=portal.querySelector('input[name="source-method"]:checked');
      var upload=selected?selected.value==='Upload product files':false;
      var amazon=portal.querySelector('[data-aip-source="amazon"]');
      var files=portal.querySelector('[data-aip-source="upload"]');
      if(amazon)amazon.hidden=upload;
      if(files)files.hidden=!upload;
      var error=portal.querySelector('.aip-form-error');
      if(error)error.textContent='';
    }
    portal.addEventListener('change',function(event){if(event.target.name==='source-method')sync();});
    portal.addEventListener('submit',function(event){
      var form=event.target;
      if(form&&form.tagName==='FORM'){
        form.setAttribute('action','javascript:void(0);');
      }
      var selected=portal.querySelector('input[name="source-method"]:checked');
      var upload=selected?selected.value==='Upload product files':false;
      var reference=portal.querySelector('input[name="product-reference"]');
      var files=Array.prototype.slice.call(portal.querySelectorAll('input[name^="product-file-"]'));
      var error=portal.querySelector('.aip-form-error');
      var hasFile=files.some(function(file){if(!file.files)return false;return file.files.length>0;});
      var hasReference=reference?Boolean(reference.value.trim()):false;
      var missing=upload?!hasFile:!hasReference;
      if(missing){
        event.preventDefault();
        event.stopImmediatePropagation();
        if(error)error.textContent=upload?'Please upload at least one product image, PDF, or ZIP file.':'Please paste an Amazon link or ASIN.';
        (upload?(portal.querySelector('.aip-drop-browse')||files[0]):reference).focus();
      }else{
        if(form&&form.getAttribute('data-status')==='sent'){
          form.setAttribute('data-status','init');
          form.classList.remove('sent','submitting','failed','invalid');
        }
      }
    },true);
    sync();
  }

  function initPortal(){
    var portal=document.querySelector('.aip-portal');
    if(!portal)return;
    initProductSource(portal);

    var lookToggle=portal.querySelector('.aip-look-toggle');
    var lookList=portal.querySelector('.aip-look-list');
    if(lookToggle){if(lookList){lookToggle.addEventListener('click',function(){var open=lookToggle.getAttribute('aria-expanded')==='true';lookToggle.setAttribute('aria-expanded',String(!open));lookList.hidden=open;});}}
    var dots=portal.querySelectorAll('[data-aip-section]');
    var topbar=portal.querySelector('.aip-topbar');
    var panels=portal.querySelectorAll('.aip-page-panel');

    function updateActiveSection(){
      var activeId='';
      var vh=window.innerHeight||document.documentElement.clientHeight;
      var focalPoint=vh*0.4;
      var scrollTop=window.pageYOffset||document.documentElement.scrollTop;
      var scrollHeight=document.documentElement.scrollHeight;
      var isAtBottom=(vh+scrollTop)>=(scrollHeight-30);

      if(isAtBottom){if(panels.length>0){
        activeId=panels[panels.length-1].id;
      }}else{
        var maxCoverage=-1;
        panels.forEach(function(panel){
          var rect=panel.getBoundingClientRect();
          var visibleHeight=Math.max(0,Math.min(rect.bottom,vh)-Math.max(rect.top,0));
          if(rect.top<=focalPoint?rect.bottom>=focalPoint:false){
            activeId=panel.id;
          }else if(!activeId){if(visibleHeight>maxCoverage){
            maxCoverage=visibleHeight;
            activeId=panel.id;
          }}
        });
      }

      if(!activeId){if(panels.length>0){
        activeId=panels[0].id;
      }}

      dots.forEach(function(dot){
        dot.classList.toggle('is-active',dot.dataset.aipSection===activeId);
      });
      if(topbar){
        topbar.classList.toggle('is-on-dark',activeId==='how-it-works');
      }
    }

    if('IntersectionObserver' in window){
      var observer=new IntersectionObserver(function(){
        updateActiveSection();
      },{threshold:[0,0.1,0.25,0.5,0.75,1.0]});
      panels.forEach(function(panel){observer.observe(panel);});
    }

    window.addEventListener('scroll',updateActiveSection,{passive:true});
    window.addEventListener('resize',updateActiveSection,{passive:true});

    portal.querySelectorAll('a[href^="#"]').forEach(function(link){
      link.addEventListener('click',function(){
        var href=link.getAttribute('href');
        if(href){if(href.startsWith('#')){if(href.length>1){
          var targetId=href.slice(1);
          var targetEl=document.getElementById(targetId);
          if(targetEl){
            dots.forEach(function(dot){
              dot.classList.toggle('is-active',dot.dataset.aipSection===targetId);
            });
            if(topbar){
              topbar.classList.toggle('is-on-dark',targetId==='how-it-works');
            }
            setTimeout(updateActiveSection,300);
            setTimeout(updateActiveSection,600);
          }
        }}}
      });
    });

    updateActiveSection();
    setTimeout(updateActiveSection,100);
    setTimeout(updateActiveSection,500);
  }

  function openModal(){
    var modal=document.querySelector('.aip-order-modal');
    if(modal){
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden','false');
      document.body.classList.add('aip-order-open');
      modal.style.display='flex';
      modal.style.opacity='1';
      modal.style.pointerEvents='auto';
      modal.style.visibility='visible';
      modal.style.zIndex='99999999';
      window.setTimeout(function(){var first=modal.querySelector('input, select, button');if(first)first.focus();},50);
    }
  }

  var aipAddons={
    'extra-environment':{label:'Extra environment',price:15},
    'another-version':{label:'Another version',price:15},
    'new-version':{label:'Another version',price:15,slug:'another-version'},
    '20-second-story':{label:'20-second story',price:10},
    'alternate-lighting':{label:'Alternate lighting',price:10},
    'priority-delivery':{label:'Priority delivery',price:10}
  };

  function selectAddon(slug,sourceOrder){
    var modal=document.querySelector('.aip-order-modal');
    var form=modal&&modal.querySelector('form');
    var selected=aipAddons[slug]||null;
    var normalized=selected?(selected.slug||slug):'';
    if(form){
      var hidden=form.querySelector('input[name="aip-addon"]');
      if(!hidden){hidden=document.createElement('input');hidden.type='hidden';hidden.name='aip-addon';form.appendChild(hidden);}
      hidden.value=normalized;
      var source=form.querySelector('input[name="aip-source-order"]');
      if(!source){source=document.createElement('input');source.type='hidden';source.name='aip-source-order';form.appendChild(source);}
      source.value=sourceOrder||'';
      var notes=form.querySelector('textarea[name="creative-notes"],textarea[name="your-message"]');
      if(notes&&selected){
        var line='Requested add-on: '+selected.label+' (+$'+selected.price+').';
        if(notes.value.indexOf(line)===-1){notes.value=(notes.value?notes.value.replace(/\s+$/,'')+'\n':'')+line;}
      }
    }
    if(modal){
      var title=modal.querySelector('.aip-form-wrap h3');
      var intro=modal.querySelector('.aip-form-wrap>p');
      if(title)title.textContent=selected?'Add '+selected.label.toLowerCase():'Create your REii feature';
      if(intro)intro.textContent=selected?'Your $20 REii feature plus '+selected.label.toLowerCase()+' (+$'+selected.price+'). Add your product details to continue.':'Add an Amazon link or upload your product files. Your $20 launch feature includes one 10-second, AI-created influencer UGC video.';
    }
  }

  function closeModal(){
    var modal=document.querySelector('.aip-order-modal');
    if(modal){
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden','true');
      modal.style.display='none';
      document.body.classList.remove('aip-order-open');
    }
  }

  document.addEventListener('click',function(event){
    var target=event.target;
    var addonBtn=target.closest('[data-aip-addon]');
    if(addonBtn){
      event.preventDefault();
      event.stopPropagation();
      selectAddon(addonBtn.getAttribute('data-aip-addon')||'',addonBtn.getAttribute('data-source-order')||'');
      openModal();
      return;
    }
    var openBtn=target.closest('[data-aip-open-order], .aip-contact-action button, .aip-contact-action a, a[href="#open-order"]');
    if(!openBtn){if(target){
      var candidate=target.closest('button, a, div');
      if(candidate){if(candidate.textContent){if(/start.*order/i.test(candidate.textContent.trim()))openBtn=candidate;}}
    }}
    if(openBtn){
      event.preventDefault();
      event.stopPropagation();
      selectAddon('',openBtn.getAttribute('data-source-order')||'');
      openModal();
      return;
    }
    var closeBtn=target.closest('.aip-order-close');
    if(closeBtn){
      event.preventDefault();
      closeModal();
      return;
    }
    var activeModal=document.querySelector('.aip-order-modal.is-open, .aip-order-modal[style*="display: flex"]');
    if(activeModal){if(target===activeModal){
      closeModal();
    }}
  },true);

  document.addEventListener('keydown',function(event){
    if(event.key==='Escape'){
      closeModal();
    }
  },true);

  function initOfferFromUrl(){
    var params=new URLSearchParams(window.location.search||'');
    var offer=params.get('aip_offer')||'';
    if(aipAddons[offer]){
      selectAddon(offer,params.get('source_order')||'');
      openModal();
    }
  }

  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',function(){initPortal();initOfferFromUrl();});else{initPortal();initOfferFromUrl();}
  document.addEventListener('wpcf7init',initPortal);
  window.addEventListener('load',initPortal);
})();
