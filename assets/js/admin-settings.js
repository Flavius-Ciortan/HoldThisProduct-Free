(function ($) {
	'use strict';

	$(function () {
		var params = new URLSearchParams(window.location.search);
		var activeTab = params.get('active_tab') || window.localStorage.getItem('htp_active_tab') || 'general';

		function activateTab(target) {
			var $content = $('#htp-' + target);
			var $tabs = $('.htp-nav-tab');
			if (!$content.length) {
				target = 'general';
				$content = $('#htp-general');
			}
			$tabs
				.removeClass('htp-nav-tab-active')
				.attr({'aria-selected': 'false', 'tabindex': '-1'});
			$tabs
				.filter('[data-target="' + target + '"]')
				.addClass('htp-nav-tab-active')
				.attr({'aria-selected': 'true', 'tabindex': '0'});
			$('.htp-tab-content').removeClass('htp-tab-active').hide().attr('hidden', true);
			$content.addClass('htp-tab-active').show().removeAttr('hidden');
			$('#htp-active-tab-field').val(target);
			window.localStorage.setItem('htp_active_tab', target);
		}

		$('<input>', {
			type: 'hidden',
			name: 'active_tab',
			id: 'htp-active-tab-field'
		}).appendTo('.htp-settings-form');

		activateTab(activeTab);
		$('.htp-form-actions').addClass('htp-ready');

		$('.htp-nav-tab').on('click', function () {
			activateTab(String($(this).data('target')));
		});

		$('.htp-nav-tab').on('keydown', function (event) {
			var $tabs = $('.htp-nav-tab');
			var current = $tabs.index(this);
			var next = current;

			if (event.key === 'ArrowRight') {
				next = (current + 1) % $tabs.length;
			} else if (event.key === 'ArrowLeft') {
				next = (current - 1 + $tabs.length) % $tabs.length;
			} else if (event.key === 'Home') {
				next = 0;
			} else if (event.key === 'End') {
				next = $tabs.length - 1;
			} else {
				return;
			}

			event.preventDefault();
			activateTab(String($tabs.eq(next).data('target')));
			$tabs.eq(next).trigger('focus');
		});

		$('.htp-popup-tab').on('click', function () {
			var tab = String($(this).data('popup-tab'));
			$('.htp-popup-tab').removeClass('htp-popup-tab-active');
			$(this).addClass('htp-popup-tab-active');
			$('.htp-popup-tab-content').hide();
			$('.htp-popup-tab-content-' + tab).show();
		});

		var $toggle = $('input[name="holdthisproduct_options[enable_popup_customization_logged_in]"]');
		var $fields = $('.htp-popup-customization-fields-logged-in');
		$toggle.on('change', function () {
			$fields.stop(true, true)[$(this).is(':checked') ? 'slideDown' : 'slideUp']();
		});
	});
})(jQuery);
