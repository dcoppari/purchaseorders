
function purchaseOrders(jsonObject)
{

  jsonObject.action = "purchaseorders";
  
  jQuery.ajax({
	url  : purchaseordersAjaxurl,
	type : 'post',
	data : jsonObject,
    	
	beforeSend : function() {
      jQuery('.purchaceorderswidgetwait').css('display','block');
	},
	success : function( response ) { 
	  toastr["info"]("Hecho..");

	  if(jQuery('.purchaceorderswidget').length > 0)
  		jQuery('.purchaceorderswidget').html(response);
	  
	},
	always : function() {
      jQuery('.purchaceorderswidgetwait').css('display','none');
	}
	
  });
											  
}

jQuery(document).ready ( function () { 

  toastr.options = {
    "closeButton": true,
    "positionClass": "toast-bottom-center",
    "showDuration": "300",
    "hideDuration": "1000",
    "timeOut": "5000",
    "extendedTimeOut": "1000",
    "showEasing": "swing",
    "hideEasing": "linear",
    "showMethod": "fadeIn",
	"hideMethod": "fadeOut"
  }

  jQuery('.purchaceorderswidget').on('change', 'input[name="quantity"]', function() {   
	  data = { "cmd" : "_update" , "item_order" : this.dataset.itemorder, "quantity" : this.value }
	  purchaseOrders(data);
  });

  jQuery('.purchaceorderswidget').on('change', 'input[name="profit"]', function() {   
	  data = { "cmd" : "_profit" , "profit" : this.value }
	  purchaseOrders(data);
  });

});
