(function ($) {

	$(window).on("load", function(event) {

		var method = $(".woocommerce-checkout");
		var gateway = $(".woocommerce-checkout input[type='radio']:checked");

		method.on('change', function() {
			var gateway = $(".woocommerce-checkout input[type='radio']:checked");
			var field_cart_online = $(".cart-online");
			var field_cart_cash = $(".cart-cash");

			if(gateway.val() == "kT_PayPal_Gateway") { 
				field_cart_online.show("slow");
				field_cart_cash.show("slow");
			}else{
				field_cart_online.hide("fast");
				field_cart_cash.hide("fast");
			}
		});

		$("body").on('click', '#place_order', function() {
			//$(".preloader").css("display", "");
		});
	});

})(jQuery);