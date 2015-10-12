jQuery(document).on('click', '.delete a', function () {
    var id = this.id;
    console.log(id);
    jQuery.ajax({
        type: 'POST',
        url: ajaxurl,
        data: {"action": "delete_invitation", "element_id": id},
        success: function (data) {
            //run stuff on success here.  You can use `data` var in the 
           //return so you could post a message.  
		console.log('El id es: ' + id);
          jQuery(this).parent().fadeOut('slow', function() {jQuery(this).remove();});
		  // jQuery(this).parent().parent().slideUp( function(){jQuery(this).remove()}); return false;
        }
    });
});
