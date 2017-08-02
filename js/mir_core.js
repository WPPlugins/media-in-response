function mir_init()
{
  var c = $("comment");
  var f = $("commentform");
  
  //if (f) mir_orig_action = f.action;
  //else mir_orig_action ='';

  //
  // This saves the original form action, to post, the comment
  //  
  if (f != undefined && f.action != null) mir_orig_action = f.action;
  
  //
  // This creates an element after the comment form
  // but before the comment send button
  //
  if (c)
  {
    var afterform = document.createElement('div');
    afterform.setAttribute('id', 'media_in_response');
      
    c.parentNode.insertBefore(afterform, c.nextSibling);  
  }
  
  if ($('comments'))
  {
    mir_list_media();
  }
  
  if ($('submit'))
  {
    myMessage = new Element('div', {id: 'commentform_message'});
    myMessage.inject('commentform', 'before');  
  
    $('submit').addEvent('click', function(e) {
      if ($('commentform'))
      {
        //
        // This plugin will handle the comment sending
        //
        $('commentform').action = mir_url;
      }
      
      new Event(e).stop();
      
  		//make the ajax call
  		var req = new Request(
      {
  		  evalResponse:true,
  		  async:true,
  		  data: $('commentform').toQueryString(),
  			url: $('commentform').get('action'),  // Gets the URL from the form
  			onRequest: function() 
        { 
  			  $('respond').innerHTML='';
  			  $('commentform_message').innerHTML='<strong>Sending your comment...</strong><br />';
          $('commentform').setStyle('display','none');
        },
  			//onComplete: function(response) { alert(response); }
  			onFailure:function(instance)
        {
         $('commentform_message').innerHTML='<strong>'+instance.responseText+'</strong>';
         $('commentform').setStyle('display','block'); 
        }, 
  			onSuccess: function(responseText, responseXML)
        {
          //
          // Some nice feature for those
          // who have firebug
          //
          if (window.console)
          {
            console.log(responseText);
            console.log(responseXML);
          }
          window.location.reload();
        }
  		}).send();  // Submits the form fields    
    });
  }
  
  var url = mir_url+'?'+'form=common';
	new Request({
	  url: url,
		method: 'get',
		onComplete: function(respond)
		{     
	      var p = $('media_in_response');
	      if (p)
	      {
		      p.innerHTML = respond;
	       
      		$('mir_select_type').addEvent('click', function(e) 
          {
      			mir_init_type_selection();
      		});
	      }
	    }
	}).send();	
}

function mir_init_type_selection()
{
  var url = mir_url+'?form=buttons';
  
	new Request({
	  url: url,
		method: 'get',
		onComplete: function(respond)
		{
      var p = $('mir_buttons');
      p.innerHTML = respond;

      var swiffyPicture = new FancyUpload2($('mir-picture-status'), $('mir-picture-image'), $('mir-picture-file'), $('mir-picture-message'), 
      {
        'url': $('commentform').action,
        'fieldName': 'mir-picture',
        'path': mir_swiff_url,
        'onLoad': function() {
        }
      });
      
      var swiffyVideo = new FancyUpload2($('mir-video-status'), $('mir-video-image'), $('mir-video-file'), $('mir-video-message'), 
      {
        'url': $('commentform').action,
        'fieldName': 'mir-video',
        'path': mir_swiff_url,
        'onLoad': function() {
        }
      });      
      
      $('mir_upload_picture').addEvent('click', function(e) 
      {
          swiffyPicture.browse({'Images (*.jpg, *.jpeg, *.gif, *.png)': '*.jpg; *.jpeg; *.gif; *.png'});
          
          $('commentform').action = mir_url;
          $('mir-picture').removeClass('hide');
           
        	return false;
      });

      $('mir_upload_video').addEvent('click', function(e) 
      {
          swiffyVideo.browse({'Videos (*.mp4, *.mpg, *.wmv, *.avi, *.mov, *.3gp)': '*.mp4; *.mpg; *.wmv; *.avi; *.mov; *.3gp'});
          
          $('commentform').action = mir_url;
          $('mir-video').removeClass('hide');
           
        	return false;
      });
    }
	}).send();
}

function mir_list_media()
{
  document.getElements('[id^=comment-]').each( 
  function(el)
  {
    len = el.id.length;
    pos = el.id.indexOf('-')+1;
    
    commentId = el.id.substring(pos,len);
    
    if (commentId > 0)
    {
      mir_show_media(commentId);
    }
    //alert(el.id); 
  });
}

function mir_show_media(id)
{

	var req = new Request(
  {
	  evalResponse:true,
	  async:true,
		url: mir_url+'?comment_id='+id+'&show=media',  // Gets the URL from the form
		onRequest: function() 
    {
      console.log("start");
    },
		onFailure:function(instance)
    { 
      console.log("error");
      console.log(instance);
    }, 
		onSuccess: function(responseText, responseXML)
    {
      
          
      len = responseText.length;
      pos = responseText.indexOf('|');
      commentId = responseText.substring(0,pos);
      cleanContent = responseText.substring(pos+1,len);
      
      if (len > 0 && cleanContent.length > 0)
      {
      
        var mediaContent = document.createElement('span');
        mediaContent.setAttribute('id', 'media-'+commentId);
      
        mediaContent.innerHTML=cleanContent;
      
        mediaContent.inject($('comment-'+commentId),'bottom');
      
      //XXX
      
      //console.log("siker");
      //console.log(responseText);
      }
    }
	}).send();
}