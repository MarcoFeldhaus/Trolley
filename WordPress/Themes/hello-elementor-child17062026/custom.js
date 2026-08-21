jQuery( document ).ready(function($) {

    $('.dynamic-gallery-item-inner .dynamic-gallery-thumbnail .buttons a').each(function() {
    	$(this).insertBefore($(this).parent());
    	$(this).addClass("modal-link");
    	$(this).empty();
    });
/*
    $( ".modal-wrapper.show" ).ready(function() {
	  $('body').addClass("no-scroll");
	});

*/
   $(function() {
		(function($) {
		    var MutationObserver = window.MutationObserver || window.WebKitMutationObserver || window.MozMutationObserver;

		    $.fn.attrchange = function(callback) {
		        if (MutationObserver) {
		            var options = {
		                subtree: false,
		                attributes: true
		            };

		            var observer = new MutationObserver(function(mutations) {
		                mutations.forEach(function(e) {
		                    callback.call(e.target, e.attributeName);
		                });
		            });

		            return this.each(function() {
		                observer.observe(this, options);
		            });

		        }
		    }
		})(jQuery);

		$('.eael-load-more-button').attrchange(function(attrName) {

		    if(attrName=='class'){
		         $('.dynamic-gallery-item-inner .dynamic-gallery-thumbnail .buttons a').each(function() {
			    	$(this).insertBefore($(this).parent());
			    	$(this).addClass("modal-link");
			    	$(this).empty();
			    });
		    }

		});
	});
 
});