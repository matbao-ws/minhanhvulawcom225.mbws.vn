jQuery(document).ready(function($) {
   
      $('#taxonomy-filter').change(function() {
        var selectedText = $("#taxonomy-filter option:selected").text();
        $('#chuyen-khoa').val(selectedText);
      });
      $('#taxonomy-filter-2').change(function() {
        var selectedText2 = $("#taxonomy-filter-2 option:selected").text();
        $('#chuyen-khoa').val(selectedText2);
      });
      $('#post-select').change(function() {
        var selectedText = $("#post-select option:selected").text();
        $('#ten-bac-si').val(selectedText);
      });

      $('.mbws_button-popup').click(function() {
        $('#popup').fadeIn();
      });


      $('.mbws_wrap-form-register-ctf7').hide();
      $('#mbws_next-step').click(function(){
        $('.mbws_wrap-form-register-ctf7').show();
        $('.mbws_wrap-form-register').hide();
      });

      $('#close-popup, #popup').click(function(event) {
          if (event.target.id == 'popup' || event.target.id == 'close-popup') {
              $('#popup').fadeOut();
          }
      });

    
  });