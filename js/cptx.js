/*globals cprotext,tinyMCE */
(function($,undefined){
    "use strict";

    var cprotextOnLoad=function(){
      var token=null;
      var formevent="";

      var cptx=$("#cptx_check");
      var initialTitle="";
      var initialContent="";
      var initialIE8="";
      var initialFont="";
      var initialPlh="";
      var initialKw="";

      var fontlist=$("#fontlist");
      var clickhandlers=[];

      var cptxDialog="";
      var dialogOptions={
        autoOpen: false,
        title:"CPROTEXT",
        resizable: false,
        modal: true
      };

      function cptxEnableSaveButton(){
        var button=$("#cptxoptionsave");
        if(button.attr("disabled")==="disabled"){
          button.removeAttr("disabled");
        }
      }

      function cptxCloseDialog(){
        cptxDialog.dialog("close");
        cptxDialog.remove();
        cptxDialog="";
      }

      function cptxAddFont(token,wpcptx){
        $('#cptx_optionform').ajaxForm({
            beforeSerialize: function(form,options){
              var data={
                'f': 'font',
                't': token,
              };
              data[cprotext.API.NONCE_VAR]=wpcptx;
              data=$.param(data);
              options.url=cprotext.API.URL+data;
              options.type='post';
              form[0].enctype="multipart/form-data";
              $(form[0]).find("input,select,button").
              attr("disabled","disabled");
              $(form[0].fontFile).removeAttr("disabled");
            },
            beforeSend: function(){
              var progress=$(".progress");
              progress.css('display','inline-block');
              var pVal=0;
              progress.children('.bar').width(pVal);
              progress.children('.percent').html(pVal+'%');
            },
            uploadProgress: function(e,p,t,pc){
              var progress=$(".progress");
              var pVal=pc+'%';
              progress.children('.bar').width(pVal);
              progress.children('.percent').html(pVal);
            },
            success: function(){
              var progress=$(".progress");
              var pVal='100%';
              progress.children('.bar').width(pVal);
              progress.children('.percent').html(pVal);
            },
            complete: function(xhr){
              $('#cptx_optionform *').
              removeAttr("disabled");
              var response=$.parseJSON(xhr.responseText);
              if(response.hasOwnProperty("error")){
                $('.status').html(response.error);
                return;
              }
              $('.status').html(
                cprotext.L10N.fontfile1+" '"+response.data[1]+
                "' "+cprotext.L10N.fontfile2);
              $('#fontFile').val("");

              $("#cptx_fontsel").append("<option value='"+
                response.data[0]+"'>"+
                response.data[1]+"</option>");
              var options= $("#cptx_fontsel option");
              if($(options[0]).attr("title")){
                $("#cptx_fontsel").empty();
              }
              var list=[];
              list.push("[");
              var o,len;
              for(o=0,len=options.length;o<len;o++){
                list.push("[\""+
                  $(options[o]).val()+"\",\""+
                  $(options[o]).html()+"\"]");
                list.push(",");
              }
              list.pop();
              list.push("]");
              $("#cptx_fontupd").val("1");
              $("#cptx_fonts").val(list.join(""));
            }
        });

        cptxDialog.dialog("close");
        $("#cptx_optionform").submit();

        $('#cptx_optionform').ajaxFormUnbind();

        //TODO: remind user to save changes otherwise he'll
        //      have to update the font list next time the
        //      CPROTEXT settings page is reloaded

      }

      function cptxGetFontList(token,wpcptx){
        var data={
          'f': "fonts",
          't': token
        };
        data[cprotext.API.NONCE_VAR]=wpcptx;
        data=$.param(data);

        var response=$.ajax({
            url: cprotext.API.URL,
            data: data,
            dataType: "jsonp",
            error: function(jqXHR,textStatus,errorThrown){
              cptxDialog.children("#cptx_msg").
              html(cprotext.L10N.fontlistFail+":<br/>"+textStatus+"/"+errorThrown);
            },
            success: function(response){
              if(response.hasOwnProperty("error")){
                cptxDialog.children("#cptx_msg").
                html(cprotext.L10N.fontlistFail+":<br/>"+response.error);
              }else{
                var list=[];
                list.push("[");
                  $("#cptx_fontsel").empty();
                  for(var i=0,len=response.fontlist.length;i<len;i++){
                    $("#cptx_fontsel").append("<option value='"+
                        response.fontlist[i][0]+"'>"+
                        response.fontlist[i][1]+"</option>"
                    );
                    list.push("[\""+response.fontlist[i][0]+"\",\""+
                        response.fontlist[i][1]+"\"]");
                    list.push(",");
                  }
                  list.pop();
                  list.push("]");
                $("#cptx_fontsel").removeAttr("disabled");
                $("#cptx_fontupd").val("1");
                $("#cptx_fonts").val(list.join(""));
                cptxEnableSaveButton();
              }
              cptxCloseDialog();
            }
        });
      }

      function cptxGetText(token,wpcptx,textId){
        // disable user interaction
        $(".ui-dialog-buttonpane").attr("disabled","disabled").css("visibility","hidden");
        $(".legen p").html(cprotext.L10N.getInit);

        var data={
          'f':"get",
          't': token,
          'tid': textId
        };
        data[cprotext.API.NONCE_VAR]=wpcptx;
        data=$.param(data);

        var response=$.ajax({
            url: cprotext.API.URL+data,
            dataType: "jsonp",
            error: function(jqXHR,textStatus,errorThrown){
              $(".ui-dialog-buttonpane").removeAttr("disabled").css("visibility","visible");
              cptxDialog.html(cprotext.L10N.getFail+
                  ":<br/>"+
                  textStatus+"<br/>"+
                  cprotext.L10N.cancellation);
                cptxDialog.dialog("option","buttons",[{
                      text: cprotext.L10N.okButton,
                      click: cptxCloseDialog
                }]);
            },

            success: function(response) {
              $(".ui-dialog-buttonpane").removeAttr("disabled").css("visibility","visible");
              if(response.hasOwnProperty("error")){
                cptxDialog.html(cprotext.L10N.getFail+
                    ":<br/>"+
                    response.error+"<br/>"+
                    cprotext.L10N.cancellation);
                  cptxDialog.dialog("option","buttons",[{
                        text: cprotext.L10N.okButton,
                        click: cptxCloseDialog
                  }]);
              }else{
                $("#waitforit").removeClass("dary");
                $("#cptx_contentVer").
                val(response.version);
                $("#cptx_contentCSS").val(response.css);
                $("#cptx_contentHTML").
                val(response.html);
                $("#cptx_contentEOTE").
                val(response.eote);
                $("#cptx_contentEOTS").
                val(response.eots);
                $("#cptx_contentId").val(response.tid);
                cptxDialog.html("<p>"+cprotext.L10N.getSuccess1+"</p><p>"+
                    cprotext.L10N.getSuccess2+"</p>"
                );
                cptxDialog.dialog("option","buttons",[{
                      text: cprotext.L10N.submitButton,
                      click: function(){
                        cptxCloseDialog();
                        $("#publish").off("click.cptx");
                        while(clickhandlers.length>0){
                          $("#publish").on("click",
                            clickhandlers.shift());
                        }
                        if(!$("#cptx_statusChange").val()){
                          $("#cptx_statusChange").val(0);
                        }
                        var changes=$("#cptx_statusChange").val();
                        changes^=cprotext.STATUS.PROCESSING;
                        $("#cptx_statusChange").val(changes);
                        $("#publish").click();
                      }
                }]);
              }
            }
        });
      }

      function cptxCheckStatus(token,wpcptx,textId,next){
        next = typeof next !== 'undefined' ? next:"status";
        var data={
          'f':"status",
          't': token,
          'tid': textId,
          'n': next
        };
        data[cprotext.API.NONCE_VAR]=wpcptx;
        data=$.param(data);

        var response=$.ajax({
            url: cprotext.API.URL+data,
            dataType: "jsonp",
            error: function(jqXHR,textStatus,errorThrown){
              cptxDialog.html(cprotext.L10N.submitFail+
                  ":<br/>"+
                  textStatus+"<br/>"+
                  cprotext.L10N.cancellation);
                cptxDialog.dialog("option","buttons",[{
                      text: cprotext.L10N.okButton,
                      click: cptxCloseDialog
                }]);
            },

            success: function(response) {
              var string;
              if(response.hasOwnProperty("error")){
                string=cprotext.L10N.statusFail+":<br/>"+
                  response.error+"<br/>"+cprotext.L10N.cancellation;
                cptxDialog.html(string);
                cptxDialog.dialog("option","buttons",[{
                      text: cprotext.L10N.okButton,
                      click: cptxCloseDialog
                }]);
              }else{
                var status=cprotext.L10N.statusUnknown;
                if(response.status===""){
                  status=cprotext.L10N.statusDone;
                }else{
                  switch(response.status[0]){
                  case -1:
                    status=cprotext.L10N.failed+" => ";
                    switch(response.status[1][0]){
                    case 0: status+=cprotext.L10N.statusError0; break;
                    case 1: status+=cprotext.L10N.statusError1; break;
                    case 2: status+=cprotext.L10N.statusError2; break;
                    case 3: status+=response.status[1][4]+
                        " "+cprotext.L10N.statusError3;break;
                    case 4: 
                      if(response.status[1][5] === "eot"){
                        status+=cprotext.L10N.statusError41+
                          " "+response.status[1][4];
                      }else{
                        status+=cprotext.L10N.statusError42+
                          " "+response.status[1][4];
                      }
                      break;
                    case 5: 
                      if(response.status[1][5] === "eot"){
                        status+=cprotext.L10N.statusError51;
                      }else{
                        status+=cprotext.L10N.statusError52;
                      }
                      break;
                    case 6: status+=cprotext.L10N.statusError6; break;
                    case 7: status+=cprotext.L10N.statusError7; break;
                    }
                    break;
                  case -2:
                    var squareBracket={"opened":'[',"closed":']'};
                    status=cprotext.L10N.processing+"<br/>"+
                      squareBracket.opened+" "+cprotext.L10N.step+" ";
                    switch(response.status[1]){
                    case 1: status+="1/3: "+cprotext.L10N.statusProcess1; break;
                    case 2: status+="2/3: "+cprotext.L10N.statusProcess2; break;
                    case 3: status+="3/3: "+cprotext.L10N.statusProcess3; break;
                    }
                    status+=" "+squareBracket.closed;
                    break;
                  case 0:
                    status=cprotext.L10N.statusWaiting;
                    break;
                  default:
                    status=cprotext.L10N.statusQueueing+": "+
                      response.status[1][4]+" "+cprotext.L10N.statusRemaining;
                    break;
                  }
                }

                var statusContent=$('.status');
                if(!statusContent.length){
                  string="<p></p>"+
                    "<div class='legen dary' id='waitforit'>"+
                    "<p class='status'>"+
                    cprotext.L10N.processing+" …"+
                    "</p></div>";
                  cptxDialog.html(string);
                  cptxDialog.dialog("option","buttons",[{
                        text: cprotext.L10N.returnButton,
                        click: function(){
                          cptxCloseDialog();
                          $("#publish").off("click.cptx");
                          while(clickhandlers.length>0){
                            $("#publish").on("click",
                              clickhandlers.shift());
                          }
                          if($("#cptx_contentId").val().charAt(0)==="W"){
                            window.location=$("#wp-admin-bar-view-site>a")[0].href;
                          }else{
                            var changes=$("#cptx_statusChange").val();
                            if(!(changes & cprotext.STATUS.PUBLISHED) &&
                            (changes & cprotext.STATUS.CHANGED) &&
                            (changes & cprotext.STATUS.CPROTEXTED)
                            ){
                              $("#save-post").click();
                            }else{
                              $("#publish").click();
                            }
                          }
                        }
                  }]);
                  cptxDialog.dialog("open");
                }
                statusContent.html(status);
              }
            },

            complete: function(jqXHR,textStatus) {
              // Schedule the next request when the current one's complete
              if(textStatus!="success")
                return;
              if(jqXHR.responseJSON.status !== ""){
                setTimeout(function(){
                  cptxCheckStatus(jqXHR.responseJSON.token,jqXHR.responseJSON.wpcptx,
                    textId);
                }, 2000);
              }else{
                switch(next){
                case "status":
                  cptxCheckStatus(jqXHR.responseJSON.token,jqXHR.responseJSON.wpcptx,
                    textId,"get");
                  break;
                case "get":
                  if(jqXHR.responseJSON.pwyw){
                    cptxDialog.html("<p>"+cprotext.L10N.pwyw+"</p>");
                    cptxDialog.dialog("option","buttons",[{
                          text: "ok",
                          click: function(){
                            cptxCloseDialog();
                            $("#publish").off("click.cptx");
                            while(clickhandlers.length>0){
                              $("#publish").on("click",
                                clickhandlers.shift());
                            }
                            $("#cptx_statusChange").val(0);
                            if(
                              !$("#cptx_contentId").val() ||
                              $("#cptx_contentId").val().charAt(0)==="W"
                            ){
                              $("#save-post").click();
                            }else{
                              window.location=
                                $("#wp-admin-bar-view-site>a")[0].href;
                            }
                          }
                    }]);
                  }else{
                    cptxGetText(jqXHR.responseJSON.token,jqXHR.responseJSON.wpcptx,
                      textId);
                  }
                  break;
                }
              }
            }
        });
      }

      function cptxSubmitText(token,wpcptx,update){
        update=typeof update !== 'undefined' ? update:false;

        var data={
          't': token,
          'n': "status"
        };
        data[cprotext.API.NONCE_VAR]=wpcptx;

        var postData={
        };

        if(!update){
          data.f="submit";
        }else{
          data.f="update";
          data.tid=$("#cptx_contentId").val();
        }

        var changes=$("#cptx_statusChange").val();
        if(!update ||
          (changes & cprotext.STATUS.UPDATE_TITLE)
        ){
          postData.ti= $("#title").val();
        }
        if(!update ||
          (changes & cprotext.STATUS.UPDATE_CONTENT)
        ){
          postData.c=$("#content").html();
          if($("#wp-content-wrap").hasClass("tmce-active")){
            postData.c=tinyMCE.activeEditor.getContent();
          }else{
            postData.c=$("#content").val();
          }
        }

        if(changes & cprotext.STATUS.IE8_ENABLED){
          data.ie=true;
        }

        if(!update ||
          (changes & cprotext.STATUS.UPDATE_PLH)
        ){
          postData.plh= $("#cptx_plh").val();
        }
        if(!update ||
          (changes & cprotext.STATUS.UPDATE_FONT)
        ){
          data.ft= $("#cptx_fontsel").val();
        }

        data=$.param(data);

        var response=$.ajax({
            cptxFunc: update?"update":"submit",
            url: cprotext.API.URL+data,
            type: 'POST',
            data: postData,
            dataType: "jsonp",
            error: function(jqXHR,textStatus,errorThrown){
              cptxDialog.html(cprotext.L10N[this.cptxFunc+"Fail"]+
                  ":<br/>"+
                  jqXHR.responseJSON+"<br/>"+
                  cprotext.L10N.cancellation
              );
              cptxDialog.dialog("option","buttons",[{
                    text: cprotext.L10N.okButton,
                    click: cptxCloseDialog
              }]);
            },
            success: function(response){
              cptxDialog.dialog("close");
              if(response.hasOwnProperty("error")){
                cptxDialog.html(cprotext.L10N[this.cptxFunc+"Fail"]+
                    ":<br/>"+
                    response.error+"<br/>"+
                    cprotext.L10N.cancellation
                );
                cptxDialog.dialog("option","buttons",[{
                      text: cprotext.L10N.okButton,
                      click: cptxCloseDialog
                }]);
                cptxDialog.dialog("open");
              }else{
                $("#cptx_contentId").val(response.tid);

                var changes=$("#cptx_statusChange").val();
                if(update &&
                  (changes & cprotext.STATUS.WPUPDATES) &&
                  !(changes & cprotext.STATUS.UPDATE_CONTENT) &&
                  !(changes & cprotext.STATUS.UPDATE_FONT) &&
                  !(changes & cprotext.STATUS.UPDATE_PLH) &&
                  !(changes & cprotext.STATUS.IE8_ENABLED)
                ){
                  // the only change is the title, which does not require
                  // a feedback from the cprotext site
                  changes^=cprotext.STATUS.PROCESSING;
                  $("#cptx_statusChange").val(changes);
                  cptxCloseDialog();
                  $("#publish").off("click.cptx");
                  while(clickhandlers.length>0){
                    $("#publish").on("click",
                      clickhandlers.shift());
                  }
                  $("#publish").click();
                }

                cptxCheckStatus(response.token,response.wpcptx,
                  response.tid);
              }
            }
        });
      }

      function cptxConfirmSubmission(token,wpcptx,credits,cost){
        cptxDialog.html(
          "<p>"+cprotext.L10N.credits1+" "+credits+" "+
            cprotext.L10N.credits2+"</p>"+
            "<p>"+
            cprotext.L10N.credits3.replace("%1",cost).replace("%2",(cost>1?"s":""))+
            "<p/>"+
            "<p>"+cprotext.L10N.credits4+"<p/>"+
            "<div class='legen' id='waitforit'>"+
            "<p>"+cprotext.L10N.processing+" …</p></div>"
        );
        cptxDialog.dialog("option","buttons",[
            {
              text: cprotext.L10N.noButton,
              click: cptxCloseDialog
            },{
              text: cprotext.L10N.yesButton,
              click: function(){
                if(
                  $("#cptx_contentId").val() === '' ||
                  ($("#cptx_statusChange").val()&cprotext.STATUS.UPDATE_CONTENT)
                ){
                  cptxSubmitText(token,wpcptx);
                }else{
                  cptxSubmitText(token,wpcptx,true);
                }
              }
            }
        ]);
      }

      function cptxCheckCredits(token,wpcptx){
        // get current credits:
        //    send token + getCredit
        //    get value in cptx_credits
        var data={
          'f': "credits",
          't': token,
        };
        data[cprotext.API.NONCE_VAR]=wpcptx;

        var changes=$("#cptx_statusChange").val();
        if(
          $("#cptx_contentId").val() === '' ||
          (changes & cprotext.STATUS.UPDATE_CONTENT)
        ){
          data.n="submit";
        }else{
          data.n="update";
          data.tid=$("#cptx_contentId").val();
        }

        if(changes & cprotext.STATUS.UPDATE_FONT){
          data.ft=true;
        }

        if(changes & cprotext.STATUS.IE8_ENABLED){
          data.ie=true;
        }

        data=$.param(data);

        var response=$.ajax({
            url: cprotext.API.URL,
            data: data,
            dataType: "jsonp",
            error: function(jqXHR,textStatus,errorThrown){
              var string=cprotext.L10N.creditsFail+":<br/>"+
                cprotext.L10N.cancellation;
              cptxDialog.html(string);
              cptxDialog.dialog("option","buttons",[{
                    text: cprotext.L10N.okButton,
                    click: cptxCloseDialog
              }]);
            },
            success: function(response){
              cptxDialog.dialog("close");
              if(response.hasOwnProperty("error")){
                cptxDialog.html(cprotext.L10N.creditsFail);
                cptxDialog.dialog("option","buttons",[{
                      text: cprotext.L10N.okButton,
                      click: cptxCloseDialog
                }]);
              }else{
                cptxConfirmSubmission(response.token,response.wpcptx,
                  response.credits,response.cost);
              }
              cptxDialog.dialog("open");
            }
        });
      }

      function cptxMain(){
        // get cptx token:
        //    send email + pass
        //    get submit_token
        var email=$("#cptx_email").val();
        $("#cptx_email").val("");
        var password=$("#cptx_password").val();
        $("#cptx_password").val("");
        var data={
          'f': "token",
          'e': email,
          'p': password
        };
        data[cprotext.API.NONCE_VAR]=cprotext.API.INITIAL_NONCE;

        if(fontlist.length){
          if(formevent==="fontlist"){
            data.n="fonts";
          }else if(formevent==="fontFile"){
            data.n="font";
          }
        }else if($("#cptx_contentId").val().charAt(0)==='W'){
          data.n="status";
        }else{
          data.n="credits";
        }

        data=$.param(data);
        var response=$.ajax({
            url: cprotext.API.URL,
            data: data,
            dataType: "jsonp",
            error: function(jqXHR,textStatus,errorThrown){
              cptxDialog.children("#cptx_msg").
              html(cprotext.L10N.identifyFail+":<br/>"+textStatus+"/"+errorThrown);
            },
            success: function(response){
              if(response.hasOwnProperty("error")){
                cptxDialog.children("#cptx_msg").
                html(cprotext.L10N.identifyFail+":<br/>"+response.error);
              }else{
                if(fontlist.length){
                  if(formevent==="fontlist"){
                    cptxGetFontList(response.token,response.wpcptx);
                  }else if(formevent==="fontFile"){
                    cptxAddFont(response.token,response.wpcptx);
                  }
                }else if($("#cptx_contentId").val().charAt(0)=='W'){
                  cptxCheckStatus(response.token,response.wpcptx,
                    $("#cptx_contentId").val().substr(1));
                }else{
                  cptxCheckCredits(response.token,response.wpcptx);
                }
              }
            }
        });
        email=undefined;
        password=undefined;
      }

      function cptxAuthenticate(text,noreturn){
        // popup: ask for email and password
        text = (typeof text === 'undefined' || text === "")?"":"<div>"+text+"</div>";
        noreturn = typeof noreturn !== 'undefined' ? noreturn:false;
        var string=text+
          "<div id='cptx_msg' style='color:\'red\''></div>"+
          "<div>"+cprotext.L10N.login+":</div>"+"<div class='cptx_form'>"+
          "<label>"+
          cprotext.L10N.email+": <br/><input type='text' id='cptx_email'/>"+
          "</label><br/>"+
          "<label>"+
          cprotext.L10N.password+
          ": <br/><input type='password' id='cptx_password'/>"+
          "</label>"+"</div>";

        cptxDialog.html(string);
        $("#cptx_email,#cptx_password").on("change",function(e){
          e.stopPropagation();
        });

        cptxDialog.dialog($.extend({},dialogOptions,{
              buttons:[
                {
                  text: cprotext.L10N.cancelButton,
                  click: function(){
                    $("#cptx_email").val("");
                    $("#cptx_password").val("");
                    token=null;
                    if(noreturn){
                      window.location=$("#wp-admin-bar-view-site>a")[0].href;
                    }
                    cptxCloseDialog();
                  }
                },{
                  text: cprotext.L10N.identifyButton,
                  click: cptxMain
                }]
        }));
      }

      function cptxChangeListPopUp(changes){
        var changesList="";

        if(changes & cprotext.STATUS.UPDATE_CONTENT){
          changesList+="<li> - "+cprotext.L10N.commitUpdateContent+"</li>";
        }
        if(changes & cprotext.STATUS.UPDATE_TITLE){
          changesList+="<li> - "+cprotext.L10N.commitUpdateTitle+"</li>";
        }
        if(!initialIE8 && (changes & cprotext.STATUS.IE8_ENABLED)){
          changesList+="<li> - "+cprotext.L10N.commitUpdateIE8+"</li>";
        }
        if(changes & cprotext.STATUS.UPDATE_FONT){
          changesList+="<li> - "+cprotext.L10N.commitUpdateFont+"</li>";
        }
        if(changes & cprotext.STATUS.UPDATE_PLH){
          changesList+="<li> - "+cprotext.L10N.commitUpdatePlh+"</li>";
        }

        if(changesList !== ""){
          $("#cptx_statusChange").val(changes);
          cptxAuthenticate(
            "<div>"+cprotext.L10N.authenticationRequired+"</div>"+
              "<ul>"+changesList+"</ul>"
          );
        }else{
          changes^=cprotext.STATUS.PROCESSING;
          $("#cptx_statusChange").val(changes);
          $("#publish").off("click.cptx");
          while(clickhandlers.length>0){
            $("#publish").on("click",
              clickhandlers.shift());
          }
          $("#publish").click();
        }
      }

      function handler(e){
        formevent=e.target.id;

        if(fontlist.length){
          cptxDialog=$("<div id='cptxDialog'></div>");
          cptxAuthenticate();
        }

        if(cptx.length && 
          (cptx.prop("checked") || cptx[0].getAttribute("checked"))
        ){
          var changes=cprotext.STATUS.CPROTEXTED | cprotext.STATUS.PROCESSING;

          if($('#post_status>option[selected="selected"]').val()!=="publish"){
            // previous status was "draft" or "pending"
            changes|=cprotext.STATUS.NOT_PUBLISHED;
            if($('#publish').length && $('#publish').attr("name")==="publish"){
              // desired status is "publish"
              changes|=cprotext.STATUS.CHANGED;
            }else{
              // should not happen
              changes|=cprotext.STATUS.NOT_CHANGED;
            }
          }else{
            // previous status was "publish"
            changes|=cprotext.STATUS.PUBLISHED;
            if($('#post_status>option:selected').val()==="publish"){
              // desired status is still "published"
              changes|=cprotext.STATUS.NOT_CHANGED;
            }else{
              // desired status is "draft" or "pending"
              changes|=cprotext.STATUS.CHANGED;
            }
          }


          var wpcontent="";
          if($("#wp-content-wrap").hasClass("tmce-active")){
            wpcontent=tinyMCE.activeEditor.getContent();
          }else{
            wpcontent=$("#content").val();
          }

          if(initialContent!=wpcontent){
            changes|=cprotext.STATUS.UPDATE_CONTENT;
          }
          if(initialTitle!==$("#title").val()){
            changes|=cprotext.STATUS.UPDATE_TITLE;
          }
          if($("#cptx_ie8").prop("checked")){
            changes|=cprotext.STATUS.IE8_ENABLED;
          }
          if(initialIE8!==$("#cptx_ie8").prop("checked")){
            changes|=cprotext.STATUS.IE8_CHANGED;
          }
          if(initialFont!==$("#cptx_fontsel").val()){
            changes|=cprotext.STATUS.UPDATE_FONT;
          }
          if(initialPlh!==$("#cptx_plh").val()){
            changes|=cprotext.STATUS.UPDATE_PLH;
          }
          if(initialKw!==$("#cptx_kw").val()){
            changes|=cprotext.STATUS.UPDATE_KW;
          }


          if($("#cptxed").length){
            cptxed=$("#cptxed").val();
          }

          cptxDialog=$("<div id='cptxDialog'></div>");

          var string='';

          switch(changes&(cprotext.STATUS.PUBLISHED|cprotext.STATUS.CHANGED)){
          case (cprotext.STATUS.NOT_PUBLISHED | cprotext.STATUS.NOT_CHANGED):
            cptxDialog.html(
              "<div>"+cprotext.L10N.bugReportRequired+"</div>");
            cptxDialog.dialog($.extend({},dialogOptions,{
                  buttons:[{
                      text:cprotext.L10N.closeButton,
                      click: cptxCloseDialog
                  }]
            }));
            break;

          case (cprotext.STATUS.NOT_PUBLISHED | cprotext.STATUS.CHANGED):
            // Draft or Pending => Publish
            // Orginal cprotext status can not have been checked,
            // but it can already have existing CPROTEXT data.
            // if we are here it means that it is now checked
            // CPROTEXTion required
            if(!cptxed){
              // todo: check if content is empty,
              // if so refuse request for protection
              $("#cptx_statusChange").val(changes);
              cptxAuthenticate();
            }else{
              cptxChangeListPopUp(changes);
            }
            break;

          case (cprotext.STATUS.PUBLISHED | cprotext.STATUS.NOT_CHANGED):
            // Publish => Publish
            if(cptx[0].getAttribute("checked")==="checked"){
              // CPROTEXT check box was checked
              if(cptx.prop("checked")){
                // CPROTEXT check box is still checked
                cptxChangeListPopUp(changes);
              }else{
                // CPROTEXT check box is now not checked
                // if text was not modified, it must be unprotected
                // if text was modified, the previous revision must be kept as
                // is while the new revision goes through vanilla wordpress
                // processing
                changes^=cprotext.STATUS.CPROTEXTED;
                changes^=cprotext.STATUS.PROCESSING;
                $("#cptx_statusChange").val(changes);
                $("#publish").off("click.cptx");
                while(clickhandlers.length>0){
                  $("#publish").on("click",
                    clickhandlers.shift());
                }
                $("#publish").click();
              }
            }else{
              // CPROTEXT check box was not checked
              if(cptx.prop("checked")){
                // CPROTEXT check box is now checked
                // protect the new or current revision
                if(!cptxed){
                  $("#cptx_statusChange").val(changes);
                  cptxAuthenticate();
                }else{
                  cptxChangeListPopUp(changes);
                }
              }else{
                // CPROTEXT check box is still not checked
                // impossible !
                changes^=cprotext.STATUS.CPROTEXTED;
                changes^=cprotext.STATUS.PROCESSING;
                $("#cptx_statusChange").val(changes);
                cptxDialog.html(
                  "<div>"+cprotext.L10N.bugReportRequired+"</div>");
                cptxDialog.dialog($.extend({},dialogOptions,{
                      buttons:[{
                          text:cprotext.L10N.closeButton,
                          click: cptxCloseDialog
                      }]
                }));
              }
            }
            break;

          case (cprotext.STATUS.PUBLISHED | cprotext.STATUS.CHANGED):
            // Publish => Draft or Pending
            // whatever the current status of CPROTEXT check box,
            // there should be no need for CPROTEXTion
            if(cptx[0].getAttribute("checked")==="checked"){
              // CPROTEXT check box was checked
              if(cptx.prop("checked")){
                // CPROTEXT check box is still checked
                // warn user that: 
                // - previous revision will still be associated with CPROTEXT data
                // - new revision won't be protected
                string="<div>"+cprotext.L10N.unpublished;
                if(changes & cprotext.STATUS.WPUPDATES){
                  string+=cprotext.L10N.detachedFromRevision;
                }else{
                  string+=cprotext.L10N.attachedToRevision;
                }
                string+="</div>";
                cptxDialog.html(string);
                cptxDialog.dialog($.extend({},dialogOptions,{
                      buttons:[{
                          text:cprotext.L10N.closeButton,
                          click:  function(){
                            cptxCloseDialog();
                            $("#cptx_statusChange").val(changes);
                            $("#publish").off("click.cptx");
                            while(clickhandlers.length>0){
                              $("#publish").on("click",
                                clickhandlers.shift());
                            }
                            $("#publish").click();
                          }
                      }]
                }));
              }else{
                // CPROTEXT check box is now not checked
                // nothing special to do:
                // if user unchecked the box himself, he knows what he is doing
                // if user unckecked the box by mistake, he will just have to
                // check it again
                changes^=cprotext.STATUS.CPROTEXTED;
                changes^=cprotext.STATUS.PROCESSING;

                // unprotect previous revision (but don't delete CPROTEXT data)
                // new revision goes through vanilla wordpress process
                $("#cptx_statusChange").val(changes);
                $("#publish").off("click.cptx");
                while(clickhandlers.length>0){
                  $("#publish").on("click",
                    clickhandlers.shift());
                }
                $("#publish").click();
              }
            }else{
              // CPROTEXT check box was not checked
              if(cptx.prop("checked")){
                // CPROTEXT check box is now checked
                // why would user want to protect a draft or a pending text ?
                // warn then go through vanilla wordpress process
                string="<div>"+cprotext.L10N.uselessProtection+"</div>";
                cptxDialog.html(string);
                cptxDialog.dialog($.extend({},dialogOptions,{
                      buttons:[{
                          text:cprotext.L10N.closeButton,
                          click:  function(){
                            cptxCloseDialog();
                            changes^=cprotext.STATUS.PROCESSING;
                            $("#cptx_statusChange").val(changes);
                            $("#publish").off("click.cptx");
                            while(clickhandlers.length>0){
                              $("#publish").on("click",
                                clickhandlers.shift());
                            }
                            $("#publish").click();
                          }
                      }]
                }));
              }else{
                // CPROTEXT check box is still not checked
                // impossible !
                changes^=cprotext.STATUS.CPROTEXTED;
                changes^=cprotext.STATUS.PROCESSING;
                $("#cptx_statusChange").val(changes);
                string="<div>"+cprotext.L10N.bugReportRequired+"</div>";
                cptxDialog.html(string);
                cptxDialog.dialog($.extend({},dialogOptions,{
                      buttons:[{
                          text:cprotext.L10N.closeButton,
                          click: cptxCloseDialog
                      }]
                }));
              }
            }
            break;
          }
        }
        if(cptxDialog!==""){
          cptxDialog.dialog('open');
          e.stopImmediatePropagation();
          return false;
        }
      }

      if(cptx.length || fontlist.length){
        $("#cptx_check").on("click",function(){
          if($(this).attr("checked")==="checked"){
            $("#cptx_ie8").removeAttr("disabled");
            $("#cptx_fontsel, #cptx_fontsel option").
            removeAttr("disabled");
            $("#cptx_kw").removeAttr("disabled");
            $("#cptx_plh").removeAttr("disabled");
            initialIE8=$("#cptx_ie8").prop("checked");
            initialFont=$("#cptx_fontsel").val();
            initialPlh=$("#cptx_plh").val();
            initialKw=$("#cptx_kw").val();
          }else{
            $("#cptx_fontsel, #cptx_fontsel option").
            attr("disabled","disabled");
            $("#cptx_kw").attr("disabled","disabled");
            $("#cptx_plh").attr("disabled","disabled");
          }
        });

        if(cptx.length && $("#cptx_contentId").val().charAt(0)==='W'){
          cptxDialog=$("<div id='cptxDialog'></div>");
          cptxAuthenticate("<p>"+cprotext.L10N.resume+"</p>",true);
          cptxDialog.dialog('open');
        }

        if($("#publish").length){
          var events=$._data($("#publish")[0],'events');
          for(var i=0,len=events.click.length;i<len;i++){
            clickhandlers.push(events.click[i].handler);
          }
          $("#publish").off("click");
        }else{
          $("#cptx_optionform").on("change",
            "input,select",cptxEnableSaveButton);
        }

        $("#publish, #fontlist").on("click.cptx",handler);
        $("#fontFile").on("change.cptx",handler);

      }

      if(cptx.length){
        initialTitle=$("#title").val();
        if($("#wp-content-wrap").hasClass("tmce-active")){
          initialContent=tinyMCE.activeEditor.getContent();
        }else{
          initialContent=$("#content").val();
        }
        initialIE8=$("#cptx_ie8").prop("checked");
        initialFont=$("#cptx_fontsel").val();
        initialPlh=$("#cptx_plh").val();
        initialKw=$("#cptx_kw").val();
      }
    };

    var oldOnLoad=window.onload;
    if(typeof window.onload !== 'function'){
      window.onload=cprotextOnLoad;
    } else {
      window.onload=function(){
        if(oldOnLoad){
          oldOnLoad();
        }
        cprotextOnLoad();
      };
    }
}(jQuery));
