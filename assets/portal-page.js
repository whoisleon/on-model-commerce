(function(){
  function ensureSubmitHandoff(form){
    if(!form)return null;
    var submit=form.querySelector('input[type="submit"],button[type="submit"]');
    if(!submit)return null;
    var shell=submit.closest('.aip-submit-shell');
    if(!shell){
      shell=document.createElement('div');
      shell.className='aip-submit-shell';
      submit.parentNode.insertBefore(shell,submit);
      shell.appendChild(submit);
    }
    if(!shell.querySelector('.aip-submit-status')){
      var status=document.createElement('span');
      status.className='aip-submit-status';
      status.setAttribute('aria-hidden','true');
      status.innerHTML='<i></i><b>Opening secure payment&hellip;</b>';
      shell.appendChild(status);
    }
    var route=shell.nextElementSibling;
    if(!route||!route.classList.contains('aip-submit-route')){
      route=document.createElement('div');
      route.className='aip-submit-route';
      route.setAttribute('role','status');
      route.setAttribute('aria-live','polite');
      route.setAttribute('aria-atomic','true');
      route.setAttribute('aria-hidden','true');
      route.innerHTML='<span><i></i></span><div><strong>Moving you to payment</strong><small>Saving your product details and preparing protected checkout.</small></div>';
      shell.parentNode.insertBefore(route,shell.nextSibling);
    }
    return {submit:submit,shell:shell,route:route};
  }

  var DRAFT_STORAGE_KEY='aip_reii_intake_draft';

  function saveDraft(form){
    if(!form)form=document.querySelector('.aip-order-modal form, .aip-native-intake, form');
    if(!form)return;
    try{
      var email=form.querySelector('input[name="your-email"]');
      var method=form.querySelector('input[name="source-method"]:checked');
      var reference=form.querySelector('input[name="product-reference"]');
      var storefront=form.querySelector('input[name="aip-addon-storefront"]');
      var addon=form.querySelector('input[name="aip-addon"]');
      var sourceOrder=form.querySelector('input[name="aip-source-order"]');
      var rights=form.querySelector('input[name="rights-confirmed"]');

      var data={
        email:email?email.value:'',
        method:method?method.value:'Amazon link / ASIN',
        reference:reference?reference.value:'',
        storefront:storefront?Boolean(storefront.checked):false,
        addon:addon?addon.value:'',
        sourceOrder:sourceOrder?sourceOrder.value:'',
        rights:rights?Boolean(rights.checked):false
      };
      var serialized=JSON.stringify(data);
      localStorage.setItem(DRAFT_STORAGE_KEY,serialized);
      sessionStorage.setItem(DRAFT_STORAGE_KEY,serialized);
    }catch(e){}
  }

  function restoreDraft(form){
    if(!form)form=document.querySelector('.aip-order-modal form, .aip-native-intake, form');
    if(!form)return;
    try{
      var raw=localStorage.getItem(DRAFT_STORAGE_KEY)||sessionStorage.getItem(DRAFT_STORAGE_KEY);
      if(!raw)return;
      var data=JSON.parse(raw);
      if(!data||typeof data!=='object')return;

      var email=form.querySelector('input[name="your-email"]');
      if(email&&data.email&&!email.value)email.value=data.email;

      if(data.method){
        var methodInput=form.querySelector('input[name="source-method"][value="'+data.method+'"]');
        if(methodInput)methodInput.checked=true;
      }

      var reference=form.querySelector('input[name="product-reference"]');
      if(reference&&data.reference&&!reference.value)reference.value=data.reference;

      var storefront=form.querySelector('input[name="aip-addon-storefront"]');
      if(storefront&&typeof data.storefront==='boolean'){
        storefront.checked=data.storefront;
      }

      var addon=form.querySelector('input[name="aip-addon"]');
      if(addon&&data.addon&&!addon.value)addon.value=data.addon;

      var sourceOrder=form.querySelector('input[name="aip-source-order"]');
      if(sourceOrder&&data.sourceOrder&&!sourceOrder.value)sourceOrder.value=data.sourceOrder;

      var rights=form.querySelector('input[name="rights-confirmed"]');
      if(rights&&data.rights)rights.checked=true;
    }catch(e){}
  }

  function clearDraft(){
    try{
      localStorage.removeItem(DRAFT_STORAGE_KEY);
      sessionStorage.removeItem(DRAFT_STORAGE_KEY);
    }catch(e){}
  }

  function setSubmitHandoff(form,busy){
    var handoff=ensureSubmitHandoff(form);
    if(!handoff)return;
    form.classList.toggle('aip-is-handing-off',busy);
    handoff.shell.classList.toggle('is-busy',busy);
    handoff.route.classList.toggle('is-visible',busy);
    handoff.route.setAttribute('aria-hidden',busy?'false':'true');
    handoff.submit.setAttribute('aria-busy',busy?'true':'false');
    if(busy){
      handoff.submit.setAttribute('aria-label','Opening secure payment');
      var progress=form.closest('.aip-order-dialog');
      progress=progress&&progress.querySelector('.aip-checkout-progress');
      if(progress)progress.classList.add('is-handoff');
    }else{
      handoff.submit.removeAttribute('aria-label');
      var dialog=form.closest('.aip-order-dialog');
      var stepper=dialog&&dialog.querySelector('.aip-checkout-progress');
      if(stepper)stepper.classList.remove('is-handoff');
    }
  }

  function initProductSource(portal){
    if(!portal||portal.dataset.sourceReady==='1')return;
    portal.dataset.sourceReady='1';
    var fileInputs=Array.prototype.slice.call(portal.querySelectorAll('input[name^="product-file-"]'));
    if(fileInputs.length){
    if(!fileInputs[0].closest('.aip-dropzone')){
      var dropzone=document.createElement('div');
      dropzone.className='aip-dropzone';
      dropzone.innerHTML='<div class="aip-drop-prompt"><div class="aip-drop-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M20 15v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-4"/></svg></div><div class="aip-drop-text"><strong>Drop up to 4 product files here</strong>or <button type="button" class="aip-drop-browse">browse from your device</button></div><small class="aip-drop-sub">JPG, PNG, WEBP, PDF, or ZIP · 20 MB per file</small></div><div class="aip-drop-preview-list" hidden></div><div class="aip-drop-footer" hidden><span class="aip-drop-count"></span><button type="button" class="aip-drop-add">+ Add another file</button></div>';
      var firstWrap=fileInputs[0];
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
        fileInputs.forEach(function(input){
          input.value='';
          try{input.files=(new DataTransfer()).files;}catch(e){}
        });
        updatePreview();
      }
      var nativeForm=dropzone.closest('form');
      if(nativeForm)nativeForm.addEventListener('reset',clearFormFiles);
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
    var initialForm=portal.querySelector('form.aip-native-intake,form');
    if(initialForm)ensureSubmitHandoff(initialForm);
    portal.addEventListener('submit',function(event){
      var form=event.target;
      if(!form.classList.contains('aip-native-intake'))return;
      var selected=portal.querySelector('input[name="source-method"]:checked');
      var upload=selected?selected.value==='Upload product files':false;
      var reference=portal.querySelector('input[name="product-reference"]');
      var files=Array.prototype.slice.call(portal.querySelectorAll('input[name^="product-file-"]'));
      var email=portal.querySelector('input[name="your-email"]');
      var rights=portal.querySelector('input[name="rights-confirmed"]');
      var error=portal.querySelector('.aip-form-error');
      var hasFile=files.some(function(file){if(!file.files)return false;return file.files.length>0;});
      var hasReference=reference?Boolean(reference.value.trim()):false;
      var missing=upload?!hasFile:!hasReference;
      var oversized=files.find(function(input){return input.files&&input.files[0]&&input.files[0].size>20*1024*1024;});
      event.preventDefault();
      event.stopImmediatePropagation();
      if(!email||!email.value.trim()||!email.checkValidity()){
        if(error)error.textContent='Please provide a valid email address.';
        if(email)email.focus();
        return;
      }
      if(missing){
        if(error)error.textContent=upload?'Please upload at least one product image, PDF, or ZIP file.':'Please paste an Amazon link or ASIN.';
        (upload?(portal.querySelector('.aip-drop-browse')||files[0]):reference).focus();
        return;
      }
      if(oversized){if(error)error.textContent='Each upload must be 20 MB or smaller.';oversized.focus();return;}
      if(!rights||!rights.checked){if(error)error.textContent='Please confirm you have permission to use these product details.';if(rights)rights.focus();return;}
      var cfg=window.aipNativeCheckoutConfig||{};
      if(!cfg.ajaxUrl||!cfg.nonce){if(error)error.textContent='Secure checkout is temporarily unavailable. Please refresh and try again.';return;}
      if(form.dataset.aipSubmitting==='1')return;
      form.dataset.aipSubmitting='1';
      if(error)error.textContent='';
      setSubmitHandoff(form,true);
      var storefrontAddon=form.querySelector('input[name="aip-addon-storefront"]');
      var hiddenAddon=form.querySelector('input[name="aip-addon"]');
      if(storefrontAddon&&storefrontAddon.checked){
        if(!hiddenAddon){
          hiddenAddon=document.createElement('input');
          hiddenAddon.type='hidden';
          hiddenAddon.name='aip-addon';
          form.appendChild(hiddenAddon);
        }
        hiddenAddon.value='amazon-storefront';
      } else if(storefrontAddon&&!storefrontAddon.checked&&hiddenAddon&&hiddenAddon.value==='amazon-storefront'){
        hiddenAddon.value='';
      }
      saveDraft(form);
      var payload=new FormData(form);
      payload.append('action','aip_reii_prepare_checkout');
      payload.append('nonce',cfg.nonce);
      fetch(cfg.ajaxUrl,{method:'POST',body:payload,credentials:'same-origin',headers:{'Accept':'application/json'}})
        .then(function(response){return response.json().catch(function(){throw new Error('Checkout returned an unreadable response.');});})
        .then(function(result){
          if(!result||!result.success){var message=result&&result.data&&result.data.message?result.data.message:'Checkout could not prepare this order.';throw new Error(message);}
          setSubmitHandoff(form,false);
          form.dataset.aipSubmitting='0';
          if(result.data.checkout_mode==='stripe_redirect'){
            var stripeUrl=String(result.data.checkout_url||'');
            if(!/^https:\/\/checkout\.stripe\.com\//i.test(stripeUrl))throw new Error('Stripe returned an invalid checkout link.');
            window.location.assign(stripeUrl);
            return;
          }
          openPaymentModal(result.data.email||email.value,result.data.checkout_url||cfg.checkoutUrl||'');
        })
        .catch(function(problem){
          form.dataset.aipSubmitting='0';
          setSubmitHandoff(form,false);
          if(error)error.textContent=problem&&problem.message?problem.message:'Checkout could not prepare this order. Please try again.';
        });
    },true);
    portal.addEventListener('input',function(e){
      var form=e.target&&e.target.closest?e.target.closest('form'):portal.querySelector('form');
      if(form)saveDraft(form);
    });
    portal.addEventListener('change',function(e){
      if(e.target&&e.target.name==='aip-addon-storefront'){
        var form=e.target.closest('form')||portal.querySelector('form');
        if(form){
          var hidden=form.querySelector('input[name="aip-addon"]');
          if(!hidden){
            hidden=document.createElement('input');
            hidden.type='hidden';
            hidden.name='aip-addon';
            form.appendChild(hidden);
          }
          if(e.target.checked){
            hidden.value='amazon-storefront';
          }else if(hidden.value==='amazon-storefront'){
            hidden.value='';
          }
        }
      }
      var f=e.target&&e.target.closest?e.target.closest('form'):portal.querySelector('form');
      if(f)saveDraft(f);
    });
    restoreDraft(initialForm);
    sync();
  }

  function initPortal(){
    var portal=document.querySelector('.aip-portal');
    if(!portal)return;
    initProductSource(portal);

    var productVisual=portal.querySelector('.aip-contact-visual');
    if(productVisual){
      var revealProductCallouts=function(){
        productVisual.classList.add('is-product-callouts-visible');
      };
      if('IntersectionObserver' in window&&!window.matchMedia('(prefers-reduced-motion: reduce)').matches){
        var productObserver=new IntersectionObserver(function(entries){
          if(entries.some(function(entry){return entry.isIntersecting;})){
            revealProductCallouts();
            productObserver.disconnect();
          }
        },{threshold:.28});
        productObserver.observe(productVisual);
      }else{
        revealProductCallouts();
      }
    }

    var lookToggle=portal.querySelector('.aip-look-toggle');
    var lookList=portal.querySelector('.aip-look-list');
    if(lookToggle){if(lookList){lookToggle.addEventListener('click',function(){var open=lookToggle.getAttribute('aria-expanded')==='true';lookToggle.setAttribute('aria-expanded',String(!open));lookList.hidden=open;});}}
    var dots=portal.querySelectorAll('[data-aip-section]');
    var topbar=portal.querySelector('.aip-topbar');
    var panels=portal.querySelectorAll('.aip-page-panel');
    var darkPanels=portal.querySelectorAll('.aip-process-panel');

    function updateTopbarTheme(){
      if(!topbar)return;
      var brand=topbar.querySelector('.aip-brand')||topbar;
      var brandRect=brand.getBoundingClientRect();
      var logoLine=brandRect.top+(brandRect.height/2);
      var isOverDark=Array.prototype.some.call(darkPanels,function(panel){
        var rect=panel.getBoundingClientRect();
        return rect.top<=logoLine&&rect.bottom>logoLine;
      });
      topbar.classList.toggle('is-on-dark',isOverDark);
    }

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
      updateTopbarTheme();
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
            updateTopbarTheme();
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
      var form=modal.querySelector('form');
      restoreDraft(form);
      var syncFn=modal.querySelector('input[name="source-method"]:checked');
      if(syncFn){
        var upload=syncFn.value==='Upload product files';
        var amazon=modal.querySelector('[data-aip-source="amazon"]');
        var files=modal.querySelector('[data-aip-source="upload"]');
        if(amazon)amazon.hidden=upload;
        if(files)files.hidden=!upload;
      }
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
    'amazon-storefront':{label:'Post to REii\u2019s Amazon Storefront',price:10},
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
    }
    if(modal){
      var title=modal.querySelector('.aip-form-wrap h3');
      var intro=modal.querySelector('.aip-form-wrap>p');
      if(title)title.textContent=selected?'Add '+selected.label.toLowerCase():'Create your REii video';
      if(intro)intro.textContent=selected?'Your $10 REii video plus '+selected.label.toLowerCase()+' (+$'+selected.price+'). Add your product details to continue.':'Add an Amazon link or upload your product files. Your $10 order includes one 10-second, AI-created influencer UGC video.';
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

  function launchConfetti(container){
    var canvas=document.createElement('canvas');
    canvas.className='aip-confetti-canvas';
    canvas.style.cssText='position:fixed;top:0;left:0;width:100vw;height:100vh;pointer-events:none;z-index:999999999;';
    (container||document.body).appendChild(canvas);
    var ctx=canvas.getContext('2d');
    if(!ctx)return;
    var width=canvas.width=window.innerWidth||document.documentElement.clientWidth||800;
    var height=canvas.height=window.innerHeight||document.documentElement.clientHeight||600;
    var count=Math.min(110,Math.floor(width/12));
    var colors=['#5d32ea','#7e62e8','#f59e0b','#ec4899','#10b981','#3b82f6','#fbbf24','#a855f7'];
    var particles=[];
    for(var i=0;i<count;i++){
      particles.push({
        x:width*(0.25+0.5*Math.random()),
        y:height*0.45+(Math.random()*40),
        vx:(Math.random()-0.5)*18,
        vy:-Math.random()*15-6,
        size:Math.random()*8+6,
        color:colors[Math.floor(Math.random()*colors.length)],
        rotation:Math.random()*360,
        rotationSpeed:(Math.random()-0.5)*14,
        wobble:Math.random()*10,
        wobbleSpeed:0.1+Math.random()*0.12,
        shape:Math.random()>0.35?'rect':'circle'
      });
    }
    var startTime=performance.now();
    var duration=3600;
    function render(now){
      var elapsed=now-startTime;
      var progress=elapsed/duration;
      if(progress>=1){
        if(canvas.parentNode)canvas.remove();
        return;
      }
      ctx.clearRect(0,0,width,height);
      var gravity=0.36;
      var drag=0.985;
      particles.forEach(function(p){
        p.vx*=drag;
        p.vy=(p.vy*drag)+gravity;
        p.x+=p.vx;
        p.y+=p.vy;
        p.rotation+=p.rotationSpeed;
        p.wobble+=p.wobbleSpeed;
        var fade=progress>0.65?(1-progress)/0.35:1;
        ctx.save();
        ctx.translate(p.x+Math.sin(p.wobble)*3,p.y);
        ctx.rotate((p.rotation*Math.PI)/180);
        ctx.globalAlpha=Math.max(0,fade);
        ctx.fillStyle=p.color;
        if(p.shape==='rect'){
          ctx.fillRect(-p.size/2,-p.size/3,p.size,p.size*0.7);
        }else{
          ctx.beginPath();
          ctx.arc(0,0,p.size/2.5,0,Math.PI*2);
          ctx.fill();
        }
        ctx.restore();
      });
      requestAnimationFrame(render);
    }
    requestAnimationFrame(render);
  }

  function initStripeReturn(){
    var params=new URLSearchParams(window.location.search||'');
    var state=params.get('aip_stripe')||'';
    if(state==='cancelled'){
      restoreDraft();
      openModal();
      var error=document.querySelector('.aip-order-modal .aip-form-error');
      if(error)error.textContent='Payment was canceled. Your card was not charged.';
      return;
    }
    if(state!=='success')return;
    window.setTimeout(function(){launchConfetti();},1000);
    var previous=document.querySelector('.aip-payment-modal');
    if(previous)previous.remove();
    var modal=document.createElement('div');
    modal.className='aip-payment-modal is-open is-loaded is-complete is-uncode-confirmation';
    modal.setAttribute('role','dialog');
    modal.setAttribute('aria-modal','true');
    modal.setAttribute('aria-labelledby','aip-payment-title');
    modal.innerHTML='<button class="aip-payment-backdrop" type="button" tabindex="-1" aria-label="Close order confirmation"></button><section class="aip-payment-panel aip-uncode-confirmation-panel"><header class="aip-confirmation-topbar"><span class="aip-confirmation-brand">REii<i>.</i></span><div class="aip-confirmation-meta"><span class="aip-confirmation-status">Payment received</span><button class="aip-confirmation-close" type="button" aria-label="Close order confirmation">&times;</button></div></header><div class="aip-confirmation-body"><main class="aip-confirmation-message"><small class="aip-confirmation-kicker">Order confirmed &middot; 03 of 03</small><h2 id="aip-payment-title">Thank you for creating with REii.</h2><p>Your receipt and private delivery updates will be sent to <strong id="aip-confirmation-email">the email used at Stripe Checkout</strong>. No account or login is required.</p><div class="aip-confirmation-actions"><button type="button">Create another REii video</button><span>Have another product ready?</span></div></main><aside class="aip-confirmation-next" aria-label="What happens next"><small>What happens next</small><ol><li><b>01</b><strong>Receipt</strong><span>Payment confirmation by email.</span></li><li><b>02</b><strong>Creation</strong><span>REii produces your influencer video.</span></li><li><b>03</b><strong>Delivery</strong><span>Your private link arrives by email.</span></li></ol><p><strong>We&rsquo;ll keep you updated.</strong><br>Your receipt and private delivery link will arrive by email.</p><span class="aip-confirmation-version">REii Commerce v0.5.78</span></aside></div></section>';
    document.body.appendChild(modal);
    document.body.classList.add('aip-payment-open');
    var orderId=params.get('order_id')||'';
    var sessionId=params.get('session_id')||'';
    var emailTarget=modal.querySelector('#aip-confirmation-email');
    if(orderId&&sessionId&&emailTarget){
      var confirmationUrl='/wp-json/aip/v1/stripe-confirmation?order_id='+encodeURIComponent(orderId)+'&session_id='+encodeURIComponent(sessionId);
      fetch(confirmationUrl,{credentials:'same-origin',headers:{'Accept':'application/json'}}).then(function(response){
        if(!response.ok)throw new Error('Confirmation email unavailable');
        return response.json();
      }).then(function(data){
        if(data&&data.email){
          emailTarget.textContent=data.email;
          try{
            var draft=JSON.parse(localStorage.getItem(DRAFT_STORAGE_KEY)||'{}');
            draft.email=data.email;
            localStorage.setItem(DRAFT_STORAGE_KEY,JSON.stringify(draft));
            sessionStorage.setItem(DRAFT_STORAGE_KEY,JSON.stringify(draft));
          }catch(e){}
        }
      }).catch(function(){});
    }
    var onKeydown;
    var close=function(){document.removeEventListener('keydown',onKeydown);modal.remove();document.body.classList.remove('aip-payment-open');var clean=new URL(window.location.href);clean.searchParams.delete('aip_stripe');clean.searchParams.delete('order_id');clean.searchParams.delete('session_id');window.history.replaceState({},'',clean.pathname+clean.search+clean.hash);};
    modal.querySelector('.aip-payment-backdrop').addEventListener('click',close);
    var closeButton=modal.querySelector('.aip-confirmation-close');
    var createAnotherButton=modal.querySelector('.aip-confirmation-actions button');
    onKeydown=function(event){if(event.key==='Escape')close();};
    closeButton.addEventListener('click',close);
    createAnotherButton.addEventListener('click',function(){close();restoreDraft();openModal();});
    document.addEventListener('keydown',onKeydown);
    closeButton.focus();
  }

  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',function(){initPortal();initOfferFromUrl();initStripeReturn();});else{initPortal();initOfferFromUrl();initStripeReturn();}
  window.addEventListener('load',initPortal);

  function openPaymentModal(email,checkoutUrl){
    var previous=document.querySelector('.aip-payment-modal');
    if(previous){
      if(typeof previous.aipCleanup==='function')previous.aipCleanup();
      previous.remove();
    }
    var modal=document.createElement('div');
    modal.className='aip-payment-modal';
    modal.setAttribute('role','dialog');
    modal.setAttribute('aria-modal','true');
    modal.setAttribute('aria-labelledby','aip-payment-title');
    modal.setAttribute('data-step','2');
    var paymentUrl=checkoutUrl?new URL(checkoutUrl,window.location.origin):new URL('/checkout/?aip_embedded=1',window.location.origin);
    paymentUrl.searchParams.set('aip_embedded','1');
    modal.innerHTML='<button class="aip-payment-backdrop" type="button" tabindex="-1" aria-label="Close secure checkout"></button><section class="aip-payment-panel"><header class="aip-payment-header"><div class="aip-payment-heading"><span class="aip-payment-brand">REii<i>.</i><small>REIMAGINE</small></span><span class="aip-payment-title"><small>SECURE CHECKOUT &middot; STEP 2 OF 3</small><strong id="aip-payment-title">Complete your order</strong></span></div><span class="aip-payment-trust">Protected payment</span><button class="aip-payment-close" type="button" aria-label="Close secure checkout">&times;</button></header><div class="aip-payment-progress" role="list" aria-label="Checkout progress"><span class="is-complete" role="listitem" data-aip-payment-step="1"><i>&#10003;</i><b>Product</b></span><em></em><span class="is-active" role="listitem" aria-current="step" data-aip-payment-step="2"><i>2</i><b>Payment</b></span><em></em><span role="listitem" data-aip-payment-step="3"><i>3</i><b>Confirmation</b></span></div><p class="aip-payment-announcement" role="status" aria-live="polite" aria-atomic="true"></p><div class="aip-payment-stage"><div class="aip-payment-loading" role="status" aria-live="polite"><div class="aip-payment-loading-card"><small>SECURE CONNECTION</small><i class="aip-payment-spinner"></i><strong>Preparing your checkout</strong><p>Your product details are saved. We&rsquo;re opening protected payment now.</p><span class="aip-payment-loading-line"><b></b></span></div></div><iframe class="aip-payment-frame" title="Secure Stripe checkout" allow="payment *" src="'+paymentUrl.href+'"></iframe></div></section>';
    var paymentTrigger=document.activeElement;
    document.body.appendChild(modal);
    document.body.classList.add('aip-payment-open');
    var obscured=document.querySelector('.aip-portal');
    if(obscured)obscured.setAttribute('inert','');
    window.requestAnimationFrame(function(){modal.classList.add('is-open');});
    var frame=modal.querySelector('.aip-payment-frame');
    var closeButton=modal.querySelector('.aip-payment-close');
    var titleEyebrow=modal.querySelector('.aip-payment-title small');
    var title=modal.querySelector('.aip-payment-title strong');
    var trust=modal.querySelector('.aip-payment-trust');
    var announcement=modal.querySelector('.aip-payment-announcement');
    function markComplete(){
      if(modal.classList.contains('is-complete'))return;
      modal.classList.add('is-loaded','is-complete');
      modal.setAttribute('data-step','3');
      if(titleEyebrow)titleEyebrow.innerHTML='PAYMENT COMPLETE &middot; STEP 3 OF 3';
      if(title)title.textContent='Your order is confirmed';
      if(trust)trust.textContent='Payment received';
      if(announcement)announcement.textContent='Payment complete. Your order is confirmed.';
      closeButton.setAttribute('aria-label','Close order confirmation');
      frame.setAttribute('title','REii order confirmation');
      window.setTimeout(function(){launchConfetti(modal);},1000);
      modal.querySelectorAll('[data-aip-payment-step]').forEach(function(step){
        var complete=step.getAttribute('data-aip-payment-step')!=='3';
        step.classList.toggle('is-complete',complete);
        step.classList.toggle('is-active',!complete);
        if(complete)step.removeAttribute('aria-current');else step.setAttribute('aria-current','step');
        if(complete)step.querySelector('i').innerHTML='&#10003;';
      });
    }
    function frameShowsConfirmation(){
      try{
        var path=frame.contentWindow&&frame.contentWindow.location?frame.contentWindow.location.pathname:'';
        var body=frame.contentDocument&&frame.contentDocument.body;
        return String(path).indexOf('/order-received/')!==-1||Boolean(body&&body.classList.contains('woocommerce-order-received'));
      }catch(ignore){return false;}
    }
    function receiveCheckoutMessage(event){
      if(event.origin!==window.location.origin||event.source!==frame.contentWindow||!event.data)return;
      if(event.data.type==='aipCheckoutComplete')markComplete();
    }
    function cleanup(){
      document.removeEventListener('keydown',escapePayment,true);
      window.removeEventListener('message',receiveCheckoutMessage);
    }
    function closePayment(){
      modal.classList.remove('is-open');
      document.body.classList.remove('aip-payment-open');
      if(obscured)obscured.removeAttribute('inert');
      cleanup();
      window.setTimeout(function(){modal.remove();if(paymentTrigger&&document.body.contains(paymentTrigger))paymentTrigger.focus();},260);
    }
    function escapePayment(event){
      if(event.key==='Escape'){closePayment();return;}
      if(event.key==='Tab'){
        var first=closeButton;
        var last=frame;
        if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}
        else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}
      }
    }
    frame.addEventListener('load',function(){
      modal.classList.add('is-loaded');
      if(frameShowsConfirmation())markComplete();
      else if(announcement)announcement.textContent='Secure checkout is ready.';
    });
    modal.querySelector('.aip-payment-backdrop').addEventListener('click',closePayment);
    closeButton.addEventListener('click',closePayment);
    document.addEventListener('keydown',escapePayment,true);
    window.addEventListener('message',receiveCheckoutMessage);
    modal.aipCleanup=cleanup;
    window.setTimeout(function(){closeButton.focus();},50);
  }
})();
