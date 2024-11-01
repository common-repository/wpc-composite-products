'use strict';

(function($) {
  $(function() {
    wooco_active_options();
    wooco_active_settings();
    wooco_option_none_image();
    wooco_type_init();
    wooco_terms_init();
    wooco_products_init();
    wooco_total_limits();
    wooco_component_multiple();
    wooco_component_custom_qty();
    wooco_arrange();
  });

  // choose background image
  var wooco_media;

  $(document).
      on('click touch', '#wooco_option_none_image_upload', function(event) {
        event.preventDefault();

        // If the media frame already exists, reopen it.
        if (wooco_media) {
          // Open frame
          wooco_media.open();
          return;
        }

        // Create the media frame.
        wooco_media = wp.media.frames.wooco_media = wp.media({
          title: 'Select a image to upload', button: {
            text: 'Use this image',
          }, multiple: false,	// Set to true to allow multiple files to be selected
        });

        // When an image is selected, run a callback.
        wooco_media.on('select', function() {
          // We set multiple to false so only get one image from the uploader
          var attachment = wooco_media.state().
              get('selection').
              first().
              toJSON();

          // Do something with attachment.id and/or attachment.url here
          if ($('#wooco_option_none_image_preview img').length) {
            $('#wooco_option_none_image_preview img').
                attr('src', attachment.url);
          } else {
            $('#wooco_option_none_image_preview').
                html('<img src="' + attachment.url + '"/>');
          }
          $('#wooco_option_none_image_id').val(attachment.id);
        });

        // Finally, open the modal
        wooco_media.open();
      });

  $(document).on('change', '.wooco_option_none_image', function() {
    wooco_option_none_image();
  });

  $(document).
      on('change', '.wooco_change_price,.wooco_settings_checkbox', function() {
        wooco_active_options();
      });

  $(document).on('change', '#product-type', function() {
    wooco_active_settings();
  });

  $(document).on('change', '.wooco_component_multiple_val', function() {
    wooco_component_multiple();
  });

  $(document).on('change', '.wooco_component_custom_qty_val', function() {
    wooco_component_custom_qty();
  });

  $(document).on('click touch', '.wooco_expand_all', function(e) {
    e.preventDefault();

    $('.wooco_component_inner').addClass('active');
  });

  $(document).on('click touch', '.wooco_collapse_all', function(e) {
    e.preventDefault();

    $('.wooco_component_inner').removeClass('active');
  });

  $(document).on('click touch', '.wooco_add_component', function(e) {
    e.preventDefault();
    $('.wooco_components').addClass('wooco_components_loading');

    var data = {
      action: 'wooco_add_component', component: {}, nonce: wooco_vars.nonce,
    };

    $.post(ajaxurl, data, function(response) {
      $('.wooco_components tbody').append(response);
      wooco_type_init();
      wooco_terms_init();
      wooco_products_init();
      wooco_component_multiple();
      wooco_component_custom_qty();
      wooco_arrange();
      $('.wooco_components').removeClass('wooco_components_loading');
    });
  });

  $(document).on('click touch', '.wooco_duplicate_component', function(e) {
    e.preventDefault();
    $('.wooco_components').addClass('wooco_components_loading');

    var $component = $(this).closest('.wooco_component');
    var form_data = $component.
        find('input, select, button, textarea').
        serialize() || 0;
    var data = {
      action: 'wooco_add_component',
      form_data: form_data,
      nonce: wooco_vars.nonce,
    };

    $.post(ajaxurl, data, function(response) {
      $(response).insertAfter($component);
      wooco_type_init();
      wooco_terms_init();
      wooco_products_init();
      wooco_component_multiple();
      wooco_component_custom_qty();
      wooco_arrange();
      $('.wooco_components').removeClass('wooco_components_loading');
    });
  });

  $(document).on('click touch', '.wooco_save_components', function(e) {
    e.preventDefault();

    var $this = $(this);

    $this.addClass('wooco_disabled');
    $('.wooco_components').addClass('wooco_components_loading');

    var form_data = $('#wooco_settings').
        find('input, select, button, textarea').
        serialize() || 0;
    var data = {
      action: 'wooco_save_components',
      pid: $('#post_ID').val(),
      form_data: form_data,
      nonce: wooco_vars.nonce,
    };

    $.post(ajaxurl, data, function(response) {
      $('.wooco_components').removeClass('wooco_components_loading');
      $this.removeClass('wooco_disabled');
    });
  });

  $(document).on('click touch', '.wooco_export_components', function(e) {
    e.preventDefault();

    if (!$('#wooco_export_dialog').length) {
      $('body').append('<div id=\'wooco_export_dialog\'></div>');
    }

    $('#wooco_export_dialog').html('Loading...');

    $('#wooco_export_dialog').dialog({
      minWidth: 460,
      title: 'Export',
      modal: true,
      dialogClass: 'wpc-dialog',
      open: function() {
        $('.ui-widget-overlay').bind('click', function() {
          $('#wooco_export_dialog').dialog('close');
        });
      },
    });

    var data = {
      action: 'wooco_export_components',
      pid: $('#post_ID').val(),
      nonce: wooco_vars.nonce,
    };

    $.post(ajaxurl, data, function(response) {
      $('#wooco_export_dialog').html(response);
    });
  });

  $(document).on('click touch', '.wooco_remove_component', function(e) {
    e.preventDefault();

    if (confirm('Are you sure?')) {
      $(this).closest('.wooco_component').remove();
    }
  });

  $(document).on('click touch', '.wooco_component_heading', function(e) {
    if (($(e.target).closest('.wooco_duplicate_component').length === 0) &&
        ($(e.target).closest('.wooco_remove_component').length === 0)) {
      $(this).closest('.wooco_component_inner').toggleClass('active');
    }
  });

  $(document).on('change, keyup', '.wooco_component_name_val', function() {
    var _val = $(this).val();

    $(this).
        closest('.wooco_component_inner').
        find('.wooco_component_name').
        html(_val.replace(/(<([^>]+)>)/ig, ''));
  });

  // set total limits
  $(document).on('click touch', '#wooco_total_limits', function() {
    wooco_total_limits();
  });

  $(document).on('change', '.wooco_component_type', function() {
    wooco_type_init_component($(this));
    wooco_terms_init_component($(this));
    wooco_products_init_component($(this));
  });

  function wooco_type_init() {
    $('.wooco_component_type').each(function() {
      wooco_type_init_component($(this));
    });
  }

  // search terms
  $(document).on('change', '.wooco_terms', function() {
    var $this = $(this);
    var val = $this.val();
    var type = $this.closest('.wooco_component').
        find('.wooco_component_type').
        val();

    $this.data(type, val.join());
  });

  function wooco_type_init_component($this) {
    var $component = $this.closest('.wooco_component');
    var $type = $component.find('.wooco_component_type');
    var type = $type.val();
    var label = $type.find(':selected').text().trim();

    $component.find('.wooco_hide').hide();
    $component.find('.wooco_component_type_label').text(label);

    if (type !== '') {
      if (type === 'products') {
        $component.find('.wooco_show_if_products').
            show().
            css('display', 'flex');
      } else {
        $component.find('.wooco_show_if_other').show().css('display', 'flex');
      }
    }

    $component.find('.wooco_show').show();
    $component.find('.wooco_hide_if_' + type).hide();
  }

  function wooco_total_limits() {
    if ($('#wooco_total_limits').is(':checked')) {
      $('.wooco_show_if_total_limits').show();
    } else {
      $('.wooco_show_if_total_limits').hide();
    }
  }

  function wooco_terms_init() {
    $('.wooco_terms').each(function() {
      wooco_terms_init_component($(this));
    });
  }

  function wooco_products_init() {
    $('.wooco_products').each(function() {
      wooco_products_init_component($(this));
    });

    wooco_products_sortable();
  }

  function wooco_terms_init_component($this) {
    var $component = $this.closest('.wooco_component');
    var $terms = $component.find('.wooco_terms');
    var type = $component.find('.wooco_component_type').val();

    if (type === 'types') {
      type = 'product_type';
    }

    $terms.selectWoo({
      ajax: {
        url: ajaxurl, dataType: 'json', delay: 250, data: function(params) {
          return {
            term: params.term,
            action: 'wooco_search_term',
            taxonomy: type,
            nonce: wooco_vars.nonce,
          };
        }, processResults: function(data) {
          var options = [];

          if (data) {
            $.each(data, function(index, text) {
              options.push({id: text[0], text: text[1]});
            });
          }

          return {
            results: options,
          };
        }, cache: true,
      }, minimumInputLength: 1,
    });

    if ((typeof $terms.data(type) === 'string' || $terms.data(type) instanceof
        String) && $terms.data(type) !== '') {
      $terms.val($terms.data(type).split(',')).change();
    } else {
      $terms.val([]).change();
    }
  }

  function wooco_products_init_component($this) {
    var $component = $this.closest('.wooco_component');
    var $products = $component.find('.wooco_products');

    $products.selectWoo({
      allowClear: true, ajax: {
        url: ajaxurl, dataType: 'json', delay: 250, data: function(params) {
          return {
            term: params.term,
            action: 'wooco_search_product',
            nonce: wooco_vars.nonce,
          };
        }, processResults: function(data) {
          var options = [];

          if (data) {
            $.each(data, function(index, text) {
              options.push({id: text[0], text: text[1]});
            });
          }

          return {
            results: options,
          };
        }, cache: true,
      }, minimumInputLength: 1,
    });
  }

  function wooco_products_sortable() {
    $('ul.select2-selection__rendered').each(function() {
      var $this = $(this);
      var $products = $this.closest('.wooco_component_content_line_value').
          find('.wooco_products');

      $this.sortable({
        containment: 'parent', update: function() {
          $($this.find('.select2-selection__choice').get().reverse()).
              each(function() {
                var id = $(this).data('data').id;
                var option = $products.find('option[value="' + id + '"]')[0];

                $products.prepend(option);
              });
        },
      });
    });
  }

  function wooco_arrange() {
    $('.wooco_components tbody').sortable({
      handle: '.wooco_move_component',
    });
  }

  function wooco_option_none_image() {
    if ($('.wooco_option_none_image').val() === 'custom') {
      $('.wooco_option_none_image_custom').show();
    } else {
      $('.wooco_option_none_image_custom').hide();
    }
  }

  function wooco_active_options() {
    if ($('.wooco_change_price').val() === 'yes_custom') {
      $('.wooco_change_price_custom').show();
    } else {
      $('.wooco_change_price_custom').hide();
    }

    let checkbox = $('.wooco_settings_checkbox').val();

    $('.wooco_settings_checkbox_hide').hide();
    $('.wooco_settings_checkbox_show_' + checkbox).show();
  }

  function wooco_active_settings() {
    if ($('#product-type').val() === 'composite') {
      $('li.general_tab').addClass('show_if_composite');
      $('#general_product_data .pricing').addClass('show_if_composite');
      $('.composite_tab').addClass('active');
      $('#_downloadable').
          closest('label').
          addClass('show_if_composite').
          removeClass('show_if_simple');
      $('#_virtual').
          closest('label').
          addClass('show_if_composite').
          removeClass('show_if_simple');
      $('.show_if_external').hide();
      $('.show_if_simple').show();
      $('.show_if_composite').show();
      $('.product_data_tabs li').removeClass('active');
      $('.panel-wrap .panel').hide();
      $('#wooco_settings').show();
    } else {
      $('li.general_tab').removeClass('show_if_composite');
      $('#general_product_data .pricing').removeClass('show_if_composite');
      $('#_downloadable').
          closest('label').
          removeClass('show_if_composite').
          addClass('show_if_simple');
      $('#_virtual').
          closest('label').
          removeClass('show_if_composite').
          addClass('show_if_simple');
      $('.show_if_composite').hide();
      $('.show_if_' + $('#product-type').val()).show();
    }
  }

  function wooco_component_multiple() {
    $('.wooco_component_multiple_val').each(function() {
      if ($(this).val() == 'yes') {
        var $selector = $(this).
            closest('.wooco_component').
            find('.wooco_component_selector_val');

        $selector.find('option[value="default"]').attr('disabled', 'disabled');

        if ($selector.find('option:selected').attr('value') == 'default') {
          $selector.val('grid_3').trigger('change');
        }
      } else {
        $(this).
            closest('.wooco_component').
            find('.wooco_component_selector_val option').
            removeAttr('disabled');
      }
    });
  }

  function wooco_component_custom_qty() {
    $('.wooco_component_custom_qty_val').each(function() {
      if ($(this).val() == 'yes') {
        $(this).
            closest('.wooco_component').
            find('.wooco_show_if_custom_qty').
            show();
      } else {
        $(this).
            closest('.wooco_component').
            find('.wooco_show_if_custom_qty').
            hide();
      }
    });
  }
})(jQuery);