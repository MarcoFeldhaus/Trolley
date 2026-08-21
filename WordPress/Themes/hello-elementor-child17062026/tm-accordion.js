


jQuery( document ).ready(function($) {

  var plusIcon = '<span class="plus-icon"><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" id="Ebene_1" x="0px" y="0px" viewBox="0 0 253 253" style="enable-background:new 0 0 253 253;" xml:space="preserve"><style type="text/css">.st0{fill:none;stroke:#050503;stroke-width:8;}</style><line class="st0" x1="126.5" y1="0" x2="126.5" y2="253"></line><line class="st0" x1="253" y1="126.5" x2="0" y2="126.5"></line></svg></span><span style="clear: both;"></span>';
  var minusIcon = '<span class="minus-icon"><svg xmlns="http://www.w3.org/2000/svg" id="Ebene_1" data-name="Ebene 1" viewBox="0 0 253 8"><defs><style>.cls-1{fill:none;stroke:#050503;stroke-width:8px;}</style></defs><line class="cls-1" x1="253" y1="4" y2="4"></line></svg></span><span style="clear: both;"></span>';
  jQuery('.accordion-question').append(plusIcon);
  

  jQuery('.accordion-question').on("click", function() {
    $( this ).toggleClass("active");

    if($( this ).hasClass("active")) {
      $( this ).children('.plus-icon').remove();
      $( this ).append(minusIcon);
    } else {
      $( this ).children('.minus-icon').remove();
      $( this ).append(plusIcon);
    }
    
    var panel = this.nextElementSibling;
    if (panel.style.maxHeight) {
      panel.style.maxHeight = null;
    } else {
      panel.style.maxHeight = panel.scrollHeight + "px";
    } 
  });
});
