'use strict';

(function($) {
  var $gallery = null;
  var gallery_loading = false;

  $(function() {
    if ($('.wooco-wrap').length) {
      wooco_init_selector();

      $('.wooco-wrap').each(function() {
        wooco_init($(this), 'loaded');
      });
    }
  });

  $(document).on('woosq_loaded', function() {
    // composite products in quick view popup
    if ($('#woosq-popup .wooco-wrap').length) {
      wooco_init_selector();
      wooco_init($('#woosq-popup .wooco-wrap'), 'woosq_loaded');
    }
  });

  $(document).on('wooco_save_ids', function(e, ids, $wrap, context, $selected) {
    const block_ui_params = {
      message: null, overlayCSS: {
        background: '#fff', opacity: 0.6,
      },
    };

    if ((wooco_vars.change_image === 'yes') &&
        (context === 'loaded' || context === 'on_select' || context ===
            'on_click')) {
      var product_id = $wrap.data('id');
      var $all_gallery = $(wooco_vars.gallery_selector);
      var $main_gallery = $(wooco_vars.main_gallery_selector);

      if ($all_gallery.length && $main_gallery.length) {
        if (!ids.length) {
          $(document).
              find('.woocommerce-product-gallery--wooco').
              unblock().
              hide();
          $main_gallery.unblock().show();
        } else {
          var key = btoa(ids.toString()).replace(/[^a-zA-Z0-9]/g, '');
          var $key_gallery = $('.woocommerce-product-gallery--wooco-' + key);

          if ($key_gallery.length) {
            $all_gallery.unblock().hide();
            $key_gallery.unblock().show();
          } else {
            $all_gallery.block(block_ui_params);

            if (gallery_loading && ($gallery != null)) {
              $gallery.abort();
            }

            var data = {
              action: 'wooco_load_gallery',
              nonce: wooco_vars.nonce,
              product_id: product_id,
              key: key,
              ids: ids,
            };

            gallery_loading = true;

            $gallery = $.post(wooco_vars.wc_ajax_url.toString().
                    replace('%%endpoint%%', 'wooco_load_gallery'), data,
                function(response) {
                  if (response.gallery) {
                    var $wooco_gallery = $(response.gallery);

                    $all_gallery.unblock().hide();
                    $wooco_gallery.insertAfter($main_gallery);
                    $(document).
                        trigger('wooco_gallery_loaded', product_id, key, ids);

                    $wooco_gallery.imagesLoaded(function() {
                      $wooco_gallery.wc_product_gallery();

                      // scroll to selected image
                      if ($selected) {
                        var selected_image = $selected.data('image_gallery');
                        var $gallery_nav = $wooco_gallery.find(
                            '.flex-control-nav');

                        if ($gallery_nav.length &&
                            (selected_image !== undefined) &&
                            (selected_image !== '')) {
                          var $scroll_image = $gallery_nav.find(
                              'li img[src="' + selected_image + '"]');

                          if ($scroll_image.length) {
                            window.setTimeout(function() {
                              $scroll_image.trigger('click');
                              $(window).trigger('resize');
                            }, 100);
                          }
                        }
                      }

                      $(document).
                          trigger('wooco_gallery_images_loaded', product_id,
                              key, ids);
                    });
                  } else {
                    $all_gallery.unblock();
                  }

                  gallery_loading = false;
                });
          }
        }
      }
    }
  });

  $(document).on('click touch', '.single_add_to_cart_button', function(e) {
    if ($(this).hasClass('wooco-disabled')) {
      if (wooco_vars.show_alert === 'change') {
        wooco_show_alert($(this).closest('.wooco-wrap'));
      }

      e.preventDefault();
    }
  });

  $(document).on('click touch', '.wooco-plus, .wooco-minus', function() {
    // get values
    var $qty = $(this).closest('.wooco-qty').find('.qty'),
        val = parseFloat($qty.val()), max = parseFloat($qty.attr('max')),
        min = parseFloat($qty.attr('min')), step = $qty.attr('step');

    // format values
    if (!val || val === '' || val === 'NaN') {
      val = 0;
    }

    if (max === '' || max === 'NaN') {
      max = '';
    }

    if (min === '' || min === 'NaN') {
      min = 0;
    }

    if (step === 'any' || step === '' || step === undefined ||
        parseFloat(step) === 'NaN') {
      step = 1;
    } else {
      step = parseFloat(step);
    }

    // change the value
    if ($(this).is('.wooco-plus')) {
      if (max && (max == val || val > max)) {
        $qty.val(max);
      } else {
        $qty.val((val + step).toFixed(wooco_decimal_places(step)));
      }
    } else {
      if (min && (min == val || val < min)) {
        $qty.val(min);
      } else if (val > 0) {
        $qty.val((val - step).toFixed(wooco_decimal_places(step)));
      }
    }

    // trigger change event
    $qty.trigger('change');
  });

  $(document).
      on('keyup change', '.wooco_component_product_qty_input', function() {
        var $this = $(this);
        var $wrap = $this.closest('.wooco-wrap');
        var val = parseFloat($this.val());
        var min = parseFloat($this.attr('min'));
        var max = parseFloat($this.attr('max'));

        if ((val < min) || isNaN(val)) {
          val = min;
          $this.val(val);
        }

        if (val > max) {
          val = max;
          $this.val(val);
        }

        $this.closest('.wooco_component_product_selection_item').
            attr('data-qty', val);
        $this.closest('.wooco_component_product').attr('data-qty', val);

        wooco_init($wrap, 'update_qty');
      });

  $(document).on('change', '.wooco-checkbox', function() {
    var $wrap = $(this).closest('.wooco-wrap');

    wooco_init($wrap, 'checked');
  });
})(jQuery);

function wooco_init($wrap, context = null, $selected = null) {
  if (context === 'loaded' || context === 'woosq_loaded') {
    // update qty
    $wrap.find('.qty').trigger('change');
  }

  wooco_check_ready($wrap, context, $selected);
  wooco_save_ids($wrap, context, $selected);

  if (context === null || context === 'on_select' || context ===
      wooco_vars.show_alert) {
    wooco_show_alert($wrap, context, $selected);
  }

  jQuery(document).trigger('wooco_init', [$wrap, context, $selected]);
}

function wooco_check_ready($wrap, context = null, $selected = null) {
  var wid = $wrap.attr('data-id');
  var $components = $wrap.find('.wooco-components');
  var $ids = jQuery('.wooco-ids-' + wid);
  var $btn = $ids.closest('form.cart').find('.single_add_to_cart_button');
  var $alert = $wrap.find('.wooco-alert');
  var is_selection = false;
  var c_name = '';
  var is_min = false;
  var is_max = false;
  var is_m_min = false;
  var is_m_max = false;
  var is_same = false;
  var is_total_min = false;
  var is_total_max = false;
  var is_count = false;
  var selected_products = [];
  var allow_same = $components.attr('data-same');
  var qty = 0;
  var m_min = 0;
  var m_max = 10000;
  var qty_min = parseFloat($components.attr('data-min'));
  var qty_max = parseFloat($components.attr('data-max'));
  var total_min = parseFloat($components.attr('data-total-min'));
  var total_max = parseFloat($components.attr('data-total-max'));
  var $total = $wrap.find('.wooco-total');
  var $count = $wrap.find('.wooco-count');
  var $price = jQuery('.wooco-price-' + wid);
  var $woobt = jQuery('.woobt-wrap-' + wid);
  var pricing = $components.attr('data-pricing');
  var price = wooco_format_number($components.attr('data-price'));
  var regular_price = wooco_format_number(
      $components.attr('data-regular-price'));
  var percent = wooco_format_number($components.attr('data-percent'));
  var total = 0;
  var total_regular = 0;

  if (!$components.length ||
      !$components.find('.wooco_component_product').length) {
    return;
  }

  // calculate price

  if (pricing === 'only') {
    total = price;
    total_regular = regular_price;
  } else {
    // calc price
    $components.find('.wooco_component_product').each(function() {
      var $this = jQuery(this);
      var $checkbox = $this.find('.wooco-checkbox');
      var _price = wooco_format_number($this.attr('data-price'));
      var _regular_price = wooco_format_number(
          $this.attr('data-regular-price'));
      var _qty = wooco_format_number($this.attr('data-qty'));
      var _multiple = $this.attr('data-multiple');

      if ($checkbox.length && !$checkbox.prop('checked')) {
        return;
      }

      if (_multiple === 'yes') {
        // multiple selection

        $this.find('.wooco_item_selected').each(function() {
          var $_this = jQuery(this);
          var __price = wooco_format_number($_this.attr('data-price'));
          var __regular_price = wooco_format_number(
              $_this.attr('data-regular-price'));
          var __qty = wooco_format_number($_this.attr('data-qty'));

          if ((__price > 0) && (__qty > 0)) {
            total += __price * __qty;
          }

          if ((__regular_price > 0) && (__qty > 0)) {
            total_regular += __regular_price * __qty;
          }
        });
      } else {
        // single selection

        if ((_price > 0) && (_qty > 0)) {
          total += _price * _qty;
        }

        if ((_regular_price > 0) && (_qty > 0)) {
          total_regular += _regular_price * _qty;
        }
      }
    });

    // discount
    if ((percent > 0) && (percent < 100)) {
      total = total * (100 - percent) / 100;
    }

    if (pricing === 'include') {
      total += price;
      total_regular += regular_price;
    }
  }

  var total_html = wooco_price_html(total_regular, total);

  if ((pricing !== 'only') && (percent > 0) && (percent < 100)) {
    total_html += ' <small class="woocommerce-price-suffix">' +
        wooco_vars.saved_text.replace('[d]', percent + '%') + '</small>';
  }

  $total.html(wooco_vars.total_text + ' ' + total_html).slideDown();

  if ((wooco_vars.change_price !== 'no') && (pricing !== 'only')) {
    if ((wooco_vars.change_price === 'yes_custom') &&
        (wooco_vars.price_selector !== null) &&
        (wooco_vars.price_selector !== '')) {
      $price = jQuery(wooco_vars.price_selector);
    }

    $price.html(total_html);
  }

  if ($woobt.length) {
    $woobt.find('.woobt-products').attr('data-product-price-html', total_html);
    $woobt.find('.woobt-product-this').
        attr('data-price', total).
        attr('data-regular-price', total_regular);

    woobt_init($woobt);
  }

  jQuery(document).
      trigger('wooco_calc_price',
          [total, total_regular, total_html, $wrap, context, $selected]);

  // check ready

  $components.find('.wooco_component_product').each(function() {
    var $this = jQuery(this);
    var $checkbox = $this.find('.wooco-checkbox');
    var _selected = false;
    var _name = $this.attr('data-name');
    var _id = parseInt($this.attr('data-id'));
    var _qty = parseFloat($this.attr('data-qty'));
    var _required = $this.attr('data-required');
    var _multiple = $this.attr('data-multiple');
    var _custom_qty = $this.attr('data-custom-qty');

    if ($checkbox.length && !$checkbox.prop('checked')) {
      return;
    }

    if (_custom_qty === 'yes' || _multiple === 'yes') {
      is_count = true;
    }

    if (_multiple === 'yes') {
      // multiple selection
      var _m_qty = 0;
      var _m_min = parseFloat($this.attr('data-m-min'));
      var _m_max = parseFloat($this.attr('data-m-max'));

      $this.find('.wooco_item_selected').each(function() {
        var $_this = jQuery(this);
        var __id = parseInt($_this.attr('data-id'));
        var __qty = parseFloat($_this.attr('data-qty'));

        if (__id > 0) {
          qty += __qty;
          _m_qty += __qty;
          _selected = true;
        }

        if (allow_same === 'no') {
          if (selected_products.includes(__id)) {
            is_same = true;
          } else {
            if (__id > 0) {
              selected_products.push(__id);
            }
          }
        }
      });

      if (_m_qty < _m_min) {
        is_m_min = true;
        m_min = _m_min;

        if (c_name === '') {
          c_name = _name;
        }
      }

      if (_m_qty > _m_max) {
        is_m_max = true;
        m_max = _m_max;

        if (c_name === '') {
          c_name = _name;
        }
      }
    } else {
      // single selection

      if (_id > 0) {
        qty += _qty;
        _selected = true;
      }

      if (allow_same === 'no') {
        if (selected_products.includes(_id)) {
          is_same = true;
        } else {
          if (_id > 0) {
            selected_products.push(_id);
          }
        }
      }
    }

    if (!_selected && (_required === 'yes')) {
      is_selection = true;

      if (c_name === '') {
        c_name = _name;
      }
    }
  });

  if (is_count) {
    $count.html('<span class="wooco-count-label">' + wooco_vars.selected_text +
        '</span> <span class="wooco-count-value">' + qty + '</span>').
        slideDown();
    jQuery(document).
        trigger('wooco_change_count', [$count, qty, qty_min, qty_max]);
  }

  if (qty < qty_min) {
    is_min = true;
  }

  if (qty > qty_max) {
    is_max = true;
  }

  if ((pricing !== 'only')) {
    // check total min
    if (total_min > 0 && total < total_min) {
      is_total_min = true;
    }

    // check total max
    if (total_max > 0 && total > total_max) {
      is_total_max = true;
    }
  }

  if (is_selection || is_min || is_max || is_m_min || is_m_max || is_same ||
      is_total_min || is_total_max) {
    $btn.addClass('wooco-disabled');
    $alert.addClass('alert-active');

    if (is_selection) {
      $alert.addClass('alert-selection').
          html(wooco_vars.alert_selection.replace('[name]',
              '<strong>' + c_name + '</strong>'));
    } else if (is_m_min) {
      $alert.addClass('alert-min').
          html(wooco_vars.alert_m_min.replace('[min]', m_min).
              replace('[name]', '<strong>' + c_name + '</strong>'));
    } else if (is_m_max) {
      $alert.addClass('alert-max').
          html(wooco_vars.alert_m_max.replace('[max]', m_max).
              replace('[name]', '<strong>' + c_name + '</strong>'));
    } else if (is_min) {
      $alert.addClass('alert-min').
          html(wooco_vars.alert_min.replace('[min]', qty_min));
    } else if (is_max) {
      $alert.addClass('alert-max').
          html(wooco_vars.alert_max.replace('[max]', qty_max));
    } else if (is_same) {
      $alert.addClass('alert-same').html(wooco_vars.alert_same);
    } else if (is_total_min) {
      $alert.addClass('alert-total-min').
          html(wooco_vars.alert_total_min.replace('[min]',
              wooco_format_price(total_min)).
              replace('[total]', wooco_format_price(total)));
    } else if (is_total_max) {
      $alert.addClass('alert-total-max').
          html(wooco_vars.alert_total_max.replace('[max]',
              wooco_format_price(total_max)).
              replace('[total]', wooco_format_price(total)));
    }

    $alert.slideDown();

    jQuery(document).
        trigger('wooco_check_ready', [
          false,
          is_selection,
          is_same,
          is_min,
          is_max,
          $wrap,
          context,
          $selected]);
  } else {
    $alert.removeClass(
        'alert-active alert-selection alert-min alert-max alert-total-min alert-total-max').
        slideUp(300, function() {
          $alert.html('');
        });
    $btn.removeClass('wooco-disabled');

    // ready
    jQuery(document).
        trigger('wooco_check_ready', [
          true,
          is_selection,
          is_same,
          is_min,
          is_max,
          $wrap,
          context,
          $selected]);
  }
}

function wooco_save_ids($wrap, context = null, $selected = null) {
  var wid = $wrap.attr('data-id');
  var $components = $wrap.find('.wooco-components');
  var $ids = jQuery('.wooco-ids-' + wid);
  var ids = Array();

  $components.find('.wooco_component_product').each(function() {
    var $this = jQuery(this);
    var $checkbox = $this.find('.wooco-checkbox');
    var key = $this.data('key');

    if ($checkbox.length && !$checkbox.prop('checked')) {
      return;
    }

    if ($this.attr('data-multiple') === 'yes') {
      // multiple selection
      $this.find('.wooco_item_selected').each(function() {
        var $_this = jQuery(this);

        if (($_this.attr('data-id') > 0) && ($_this.attr('data-qty') > 0)) {
          ids.push(
              $_this.attr('data-id') + '/' + $_this.attr('data-qty') + '/' +
              key);
        }
      });
    } else {
      // single selection

      if (($this.attr('data-id') > 0) && ($this.attr('data-qty') > 0)) {
        ids.push(
            $this.attr('data-id') + '/' + $this.attr('data-qty') + '/' + key);
      }
    }
  });

  $ids.val(ids.join(','));
  jQuery(document).trigger('wooco_save_ids', [ids, $wrap, context, $selected]);
}

function wooco_show_alert($wrap, context = null, $selected = null) {
  var $alert = $wrap.find('.wooco-alert');

  if ($alert.hasClass('alert-active')) {
    $alert.slideDown();
  } else {
    $alert.slideUp();
  }

  jQuery(document).trigger('wooco_show_alert', [$wrap, context, $selected]);
}

function wooco_init_selector() {
  if (wooco_vars.selector === 'ddslick') {
    jQuery('.wooco_component_product_select').each(function() {
      var $this = jQuery(this);
      var $selection = $this.closest('.wooco_component_product_selection');
      var $component = $this.closest('.wooco_component_product');
      var $wrap = $this.closest('.wooco-wrap');

      $selection.data('select', 0);

      $this.ddslick({
        width: '100%', onSelected: function(data) {
          var _select = $selection.data('select');
          var $selected = jQuery(data.original[0].children[data.selectedIndex]);

          if (data.selectedData.value == '-1') {
            if (!$selection.find('.dd-selected .dd-desc').length) {
              $selection.find('.dd-selected').
                  addClass('dd-option-without-desc');
              $selection.find('.dd-option-selected').
                  addClass('dd-option-without-desc');
            } else {
              $selection.find('.dd-selected').
                  removeClass('dd-option-without-desc');
              $selection.find('.dd-option-selected').
                  removeClass('dd-option-without-desc');
            }
          }

          // check empty desc
          if (!$selection.hasClass('wooco-ddslick-checked-desc')) {
            $selection.find('.dd-selected, .dd-option').each(function() {
              if (!jQuery(this).find('.dd-desc').length) {
                jQuery(this).addClass('dd-option-without-desc');
              } else {
                jQuery(this).removeClass('dd-option-without-desc');
              }
            });

            $selection.addClass('wooco-ddslick-checked-desc');
          }

          // check disabled
          if (!$selection.hasClass('wooco-ddslick-checked-disabled')) {
            $selection.find('.dd-selected, .dd-option').each(function() {
              if (parseInt(jQuery(this).find('.dd-option-value').val()) === 0) {
                jQuery(this).addClass('dd-option-disabled');
              } else {
                jQuery(this).removeClass('dd-option-disabled');
              }
            });

            $selection.addClass('wooco-ddslick-checked-disabled');
          }

          wooco_selected($selected, $selection, $component);

          if (_select > 0) {
            wooco_init($wrap, 'on_select', $selected);
          } else {
            // selected on init_selector
            wooco_init($wrap, 'selected', $selected);
          }

          $selection.data('select', _select + 1);
        },
      });
    });
  } else if (wooco_vars.selector === 'select2') {
    jQuery('.wooco_component_product_select').each(function() {
      var $this = jQuery(this);
      var $selection = $this.closest('.wooco_component_product_selection');
      var $component = $this.closest('.wooco_component_product');
      var $wrap = $this.closest('.wooco-wrap');

      if ($this.val() !== '') {
        var $default = jQuery('option:selected', this);

        wooco_selected($default, $selection, $component);
        wooco_init($wrap, 'selected', $default);
      }

      $this.select2({
        templateResult: wooco_select2_state,
        width: '100%',
        containerCssClass: 'wpc-select2-container',
        dropdownCssClass: 'wpc-select2-dropdown',
      });
    });

    jQuery('.wooco_component_product_select').on('select2:select', function(e) {
      var $this = jQuery(this);
      var $selection = $this.closest('.wooco_component_product_selection');
      var $component = $this.closest('.wooco_component_product');
      var $wrap = $this.closest('.wooco-wrap');
      var $selected = jQuery(e.params.data.element);

      wooco_selected($selected, $selection, $component);
      wooco_init($wrap, 'on_select', $selected);
    });
  } else {
    jQuery('.wooco_component_product_select').each(function() {
      // check on start
      var $this = jQuery(this);
      var $selection = $this.closest('.wooco_component_product_selection');
      var $component = $this.closest('.wooco_component_product');
      var $wrap = $this.closest('.wooco-wrap');
      var $selected = jQuery('option:selected', this);

      wooco_selected($selected, $selection, $component);
      wooco_init($wrap, 'selected', $selected);
    });

    jQuery('body').on('change', '.wooco_component_product_select', function() {
      // check on select
      var $this = jQuery(this);
      var $selection = $this.closest('.wooco_component_product_selection');
      var $component = $this.closest('.wooco_component_product');
      var $wrap = $this.closest('.wooco-wrap');
      var $selected = jQuery('option:selected', this);

      wooco_selected($selected, $selection, $component);
      wooco_init($wrap, 'on_select', $selected);
    });
  }

  jQuery('.wooco_component_product_selection_item.wooco_item_selected').
      each(function() {
        var $this = jQuery(this);
        var $selection = $this.closest('.wooco_component_product_selection');
        var $component = $this.closest('.wooco_component_product');
        var $wrap = $this.closest('.wooco-wrap');

        wooco_selected($this, $selection, $component);
        wooco_init($wrap, 'selected', $this);
      });

  jQuery('body').
      on('click touch', '.wooco_component_product_selection_item', function(e) {
        if (jQuery(e.target).
                closest('.wooco_component_product_selection_item_qty').length ===
            0 && !jQuery(e.target).is('a, a *')) {
          // check on select
          var $this = jQuery(this);
          var $selection = $this.closest('.wooco_component_product_selection');
          var $component = $this.closest('.wooco_component_product');
          var $wrap = $this.closest('.wooco-wrap');

          if ($component.attr('data-multiple') === 'yes') {
            // multiple selection
            $this.toggleClass('wooco_item_selected');
          } else {
            // single selection
            if ($this.hasClass('wooco_item_selected')) {
              // remove
              $component.attr('data-id', '-1');
              $component.attr('data-price', '');
              $component.attr('data-price-html', '');
              $component.attr('data-regular-price', '');
              $this.removeClass('wooco_item_selected');
            } else {
              $selection.find('.wooco_component_product_selection_item').
                  removeClass('wooco_item_selected');
              $this.addClass('wooco_item_selected');
              wooco_selected($this, $selection, $component);
            }
          }

          wooco_init($wrap, 'on_click', $this);
        }
      });
}

function wooco_selected($selected, $selection, $component) {
  var id = $selected.attr('data-id');
  var pid = $selected.attr('data-pid');
  var price = $selected.attr('data-price');
  var purchasable = $selected.attr('data-purchasable');
  var regular_price = $selected.attr('data-regular-price');
  var link = $selected.attr('data-link');
  var image = '<img src="' + $selected.attr('data-imagesrc') + '"/>';
  var price_html = $selected.attr('data-price-html');
  var availability = $selected.attr('data-availability');
  var custom_qty = $component.attr('data-custom-qty');
  var qid = id; // product ID for quick view

  if (purchasable === 'yes') {
    $component.attr('data-id', id);
  } else {
    $component.attr('data-id', 0);
  }

  $component.attr('data-price', price);
  $component.attr('data-price-html', price_html);
  $component.attr('data-regular-price', regular_price);

  if (custom_qty === 'yes' && $selected.find('.qty').length) {
    $component.attr('data-qty', $selected.find('.qty').val());
  }

  if ((wooco_vars.quickview_variation === 'parent') && pid) {
    qid = pid;
  }

  if (wooco_vars.product_link !== 'no') {
    $selection.find('.wooco_component_product_link').remove();
    if (link !== '') {
      if (wooco_vars.product_link === 'yes_popup') {
        $selection.append(
            '<a class="wooco_component_product_link woosq-link" data-id="' +
            qid + '" data-context="wooco" href="' + link +
            '" target="_blank"> &nbsp; </a>');
      } else {
        $selection.append(
            '<a class="wooco_component_product_link" href="' + link +
            '" target="_blank"> &nbsp; </a>');
      }
    }
  }

  $component.find('.wooco_component_product_image').html(image);
  $component.find('.wooco_component_product_price').html(price_html);
  $component.find('.wooco_component_product_availability').html(availability);

  jQuery(document).
      trigger('wooco_selected', [$selected, $selection, $component]);
}

function wooco_select2_state(state) {
  if (!state.id) {
    return state.text;
  }

  var $state = {};

  if (jQuery(state.element).attr('data-imagesrc') !== '') {
    $state = jQuery('<span class="image"><img src="' +
        jQuery(state.element).attr('data-imagesrc') +
        '"/></span><span class="info"><span class="name">' + state.text +
        '</span> <span class="desc">' +
        jQuery(state.element).attr('data-description') + '</span></span>');
  } else {
    $state = jQuery('<span class="info"><span class="name">' + state.text +
        '</span> <span class="desc">' +
        jQuery(state.element).attr('data-description') + '</span></span>');
  }

  return $state;
}

function wooco_round(value) {
  return Number(Math.round(value + 'e' + wooco_vars.price_decimals) + 'e-' +
      wooco_vars.price_decimals);
}

function wooco_decimal_places(num) {
  var match = ('' + num).match(/(?:\.(\d+))?(?:[eE]([+-]?\d+))?$/);

  if (!match) {
    return 0;
  }

  return Math.max(0, // Number of digits right of decimal point.
      (match[1] ? match[1].length : 0)
      // Adjust for scientific notation.
      - (match[2] ? +match[2] : 0));
}

function wooco_format_money(number, places, symbol, thousand, decimal) {
  number = number || 0;
  places = !isNaN(places = Math.abs(places)) ? places : 2;
  symbol = symbol !== undefined ? symbol : '$';
  thousand = thousand || '';
  decimal = decimal || '';

  var negative = number < 0 ? '-' : '',
      i = parseInt(number = Math.abs(+number || 0).toFixed(places), 10) + '',
      j = 0;

  if (i.length > 3) {
    j = i.length % 3;
  }

  if (wooco_vars.trim_zeros === '1') {
    return symbol + negative + (j ? i.substr(0, j) + thousand : '') +
        i.substr(j).replace(/(\d{3})(?=\d)/g, '$1' + thousand) +
        (places && (parseFloat(number) > parseFloat(i)) ? decimal +
            Math.abs(number - i).
                toFixed(places).
                slice(2).
                replace(/(\d*?[1-9])0+$/g, '$1') : '');
  } else {
    return symbol + negative + (j ? i.substr(0, j) + thousand : '') +
        i.substr(j).replace(/(\d{3})(?=\d)/g, '$1' + thousand) +
        (places ? decimal + Math.abs(number - i).toFixed(places).slice(2) : '');
  }
}

function wooco_format_number(number) {
  return parseFloat(number.replace(/[^0-9.,]/g, '').replace(',', '.'));
}

function wooco_format_price(price) {
  var price_html = '<span class="woocommerce-Price-amount amount">';
  var price_formatted = wooco_format_money(price, wooco_vars.price_decimals, '',
      wooco_vars.price_thousand_separator, wooco_vars.price_decimal_separator);

  switch (wooco_vars.price_format) {
    case '%1$s%2$s':
      //left
      price_html += '<span class="woocommerce-Price-currencySymbol">' +
          wooco_vars.currency_symbol + '</span>' + price_formatted;
      break;
    case '%1$s %2$s':
      //left with space
      price_html += '<span class="woocommerce-Price-currencySymbol">' +
          wooco_vars.currency_symbol + '</span> ' + price_formatted;
      break;
    case '%2$s%1$s':
      //right
      price_html += price_formatted +
          '<span class="woocommerce-Price-currencySymbol">' +
          wooco_vars.currency_symbol + '</span>';
      break;
    case '%2$s %1$s':
      //right with space
      price_html += price_formatted +
          ' <span class="woocommerce-Price-currencySymbol">' +
          wooco_vars.currency_symbol + '</span>';
      break;
    default:
      //default
      price_html += '<span class="woocommerce-Price-currencySymbol">' +
          wooco_vars.currency_symbol + '</span> ' + price_formatted;
  }

  price_html += '</span>';

  return price_html;
}

function wooco_price_html(regular_price, sale_price) {
  var price_html = '';

  if (wooco_round(sale_price) !== wooco_round(regular_price)) {
    if (wooco_round(sale_price) < wooco_round(regular_price)) {
      price_html = '<del>' + wooco_format_price(regular_price) +
          '</del> <ins>' + wooco_format_price(sale_price) + '</ins>';
    } else {
      price_html = wooco_format_price(sale_price);
    }
  } else {
    price_html = wooco_format_price(regular_price);
  }

  return price_html;
}