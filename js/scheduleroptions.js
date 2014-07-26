jQuery(document).ready(function($) {
  var timeSlotCount = $('.hour_input').length;

  $('#time_slots').val(timeSlotCount);
  $('#existing_count').val(timeSlotCount);

  $('#set_time_slots').on('submit', function(e) {
    var overlay = $('.overlay', $(this));
    overlay.show();
    e.preventDefault();
    var timeSlotCount = $('.hour_input').length;
    var existingCount = timeSlotCount;
    var slotsAskedFor = $('[name="time_slots"]').val();
    if (slotsAskedFor == existingCount) {
      //nothing to do here
    } else if (slotsAskedFor > existingCount) {

      for (i = existingCount; i < slotsAskedFor; i++) {
        existingCount = i;

        $('#new_time_slots').append('<div class="slot_input"><span class="label"><label for="hh[' + i + ']">slot ' + (i + 1) + ' : </label></span><input type="text" class="hour_input" id="hh[' + i + ']" value="" name="hh[' + i + ']" size="2" maxlength="2" autocomplete="off"> : <input type="text" id="mn[' + i + ']" class="mn_input" value="" name="mn[' + i + ']" size="2" maxlength="2" autocomplete="off"> <select name="ampm[' + i + ']" class="ampm_sel"><option value="am">am</option><option value="pm">pm</option></select><br /></div>');
      }

    } else {
      for (i = existingCount; i > slotsAskedFor; i--) {
        $('.slot_input:last').remove();
        existingCount = i;
      }
    }
   overlay.hide();
  });

  $('#assign_time_slots').on('submit', function(e) {
    e.preventDefault();
    var overlay = $('.overlay', $(this));
    overlay.show();
    $.post(ajaxurl, $(this).serializeArray(), function(data) {
      var i = 0;
      var timeSlotCount = $('.hour_input').length;
      var existingCount = timeSlotCount;
      var slotsAskedFor = $(data).length;

      $(data).each(function() {
        hh = $(this)[0];
        mn = $(this)[1];
        ampm = $(this)[2];
        $('[name="hh[' + i + ']"]').val(hh);
        $('[name="mn[' + i + ']"]').val(mn);
        $('[name="ampm[' + i + ']"]').val(ampm);
        i++;
      });

      $('#time_slots').val(slotsAskedFor);
      if (slotsAskedFor < existingCount) {
        for (i = existingCount; i > slotsAskedFor; i--) {
          $('.slot_input:last').remove();
          existingCount = i;
        }
      }
     overlay.hide();
    }, 'json');
  });

  $('.datepicker').datepicker({
    onClose: function(date) {
      $(this).attr('value', date);
    },
    altField: $(this).prev()
  });

  $('#excluded-dates').on('submit', function(e) {
    e.preventDefault();
    var overlay = $('.overlay', $(this));
    overlay.show();
    $.post(ajaxurl, $(this).serializeArray(), function(data) {
      if (data.error) {
        alert(data.error);
        overlay.hide();
      } else {
        location.reload(true);
      }
    }, 'json');
  });

  $('#excluded-days').on('submit', function(e) {
    e.preventDefault();
    var overlay = $('.overlay', $(this));
    overlay.show();

    $.post(ajaxurl, $(this).serializeArray(), function(data) {
      if (data.error) {
        alert(data.error);
        overlay.hide();
      } else {
        location.reload(true);
      }
    }, 'json');
  });

  $('.defined-dates').on('change', 'input.remove', function() {
    $(this).parent().remove();
  });

  $('#excluded-dates').on('change', 'input.allow', function() {
    var counter;
    if (true == $(this).prop('checked')) {
      counter = (function() {
        i = 0;
        $('#excluded-dates input[name^="dates_allowed"]').each(function() {
          $(this).attr('name', 'dates_allowed[' + i + ']');
          i++;
        });
        return i;
      })();
      newName = 'dates_allowed[' + counter + ']';
      $(this).prev().attr('name', newName);
    } else {
      counter = (function() {
        i = 0;
        $('#excluded-dates input[name^="dates_denied"]').each(function() {
          $(this).attr('name', 'dates_denied[' + i + ']');
          i++;
        });
        return i;
      })();
      newName = 'dates_denied[' + counter + ']';
      $(this).prev().attr('name', newName);
    }
  });
  $('#tabs').tabs();

  $('form#general-options').on('submit', function(e) {
    e.preventDefault()
    overlay = $('.overlay', $(this) )
    overlay.show()
    $.post(ajaxurl, $(this).serializeArray(), function(data) {
      if ( true != data.success ) {
        alert(data.data)
      }
    }).fail(function(xhr) {
      alert('error code: ' + xhr.status + ' error message: ' + xhr.statusText)
      $('form#general-options').trigger('reset')
    }).always(function() {
      overlay.hide()
    })
  })
});