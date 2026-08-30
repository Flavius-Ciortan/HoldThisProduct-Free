jQuery(document).ready(function($) {
	var modalOpener = null;
	var $modal = $('#reservation-modal');

	function closeReservationModal() {
		$modal.hide().attr('aria-hidden', 'true');
		if (modalOpener) {
			$(modalOpener).trigger('focus');
		}
	}
	$(document).on('click', '.htp-reservations-table .cancel-reservation[data-reservation-id]', function(e) {
		e.preventDefault();
		var $link = $(this);
		if (!window.confirm($link.data('confirm'))) {
			return;
		}
		$('<form>', { method: 'post', action: window.location.href })
			.append($('<input>', { type: 'hidden', name: 'htp_cancel_res', value: $link.data('reservation-id') }))
			.append($('<input>', { type: 'hidden', name: '_wpnonce', value: $link.data('cancel-nonce') }))
			.appendTo('body')
			.trigger('submit');
	});

    function renderNotice(message, type) {
        var $notice = $('#reservation-form').find('.htp-reservation-notice');

        if (!$notice.length) {
            return;
        }

        $notice
            .removeClass('htp-reservation-notice--success htp-reservation-notice--error')
            .addClass('htp-reservation-notice--' + type)
            .text(message)
            .show();
    }

    function clearNotice() {
        $('#reservation-form').find('.htp-reservation-notice').hide().text('').removeClass('htp-reservation-notice--success htp-reservation-notice--error');
    }

    $('#htp_reserve_product').on('click', function(e) {
        e.preventDefault();
        var productId = $(this).data('productid');
        modalOpener = this;

        if (holdthisproduct_ajax.is_logged_in == 0) {
			alert(holdthisproduct_ajax.i18n.loginRequired);
            return;
        }

        clearNotice();
        $('#reservation-form').find('input[name="product_id"]').val(productId);
        
		$modal.show().attr('aria-hidden', 'false');
		$modal.find('.modal-box').trigger('focus');
    });

    $(document).on('click', '.modal-overlay', function(e) {
        if (e.target === this) {
			closeReservationModal();
        }
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
			closeReservationModal();
		} else if (e.key === 'Tab' && $modal.is(':visible')) {
			var $focusable = $modal.find('button:not([disabled]), [href], input:not([disabled]), [tabindex]:not([tabindex="-1"])').filter(':visible');
			if ($focusable.length) {
				var first = $focusable.get(0);
				var last = $focusable.get($focusable.length - 1);
				if (e.shiftKey && document.activeElement === first) {
					e.preventDefault();
					$(last).trigger('focus');
				} else if (!e.shiftKey && document.activeElement === last) {
					e.preventDefault();
					$(first).trigger('focus');
				}
			}
        }
    });

    $('#reservation-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var formData = new FormData(this);
        clearNotice();
        
        var ajaxData = {
            action: 'holdthisproduct_reserve',
            product_id: formData.get('product_id'),
            security: holdthisproduct_ajax.nonce
        };

        var $submitBtn = $form.find('button[type="submit"]');
        var originalText = $submitBtn.text();
		$submitBtn.prop('disabled', true).text(holdthisproduct_ajax.i18n.processing);

        $.post(holdthisproduct_ajax.ajax_url, ajaxData)
        .done(function(response) {
            if (response.success) {
                var successMessage = response.data || 'Reservation successful!';
                renderNotice(successMessage, 'success');
                window.setTimeout(function() {
                    closeReservationModal();
                    location.reload();
                }, 2200);
            } else {
                renderNotice(response.data || 'Reservation could not be completed.', 'error');
            }
        })
        .fail(function() {
            renderNotice('Request failed. Please try again.', 'error');
        })
        .always(function() {
            $submitBtn.prop('disabled', false).text(originalText);
        });
    });
});
