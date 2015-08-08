jQuery(document).ready(function($) {

		$('input[type="submit"]').on('click', function() {
				$('#quick-press').data('button', this.name);
		});


		$('#quick-press').on('submit', function(e) {
				var submitButton = $(this).data('button') || $('input[type="submit"]').get(0).name;
				if (submitButton == 'publish') {
						myalert("Thanks for your article!", alertText);
				}
		});

		function myalert(title, text) {
				var div = $('<div>').html(text).dialog({
						title: title,
						modal: false,
						width: '40%',
						close: function() {
								$(div).dialog('destroy').remove();
						},
						buttons: [{
								text: "Ok",
								click: function() {
										$(this).dialog("close");
								}
						}]
				})
		};
		var alertText = "<p>The article you submitted has been scheduled for a later time to ensure articles are spaced out and published during higher traffic periods. Do not be alarmed, we are trying to make sure your article gets the visibility it deserves!</p>  <p>Lower traffic periods: Late Nights, Early Mornings, and Weekends are generally avoided.</p>";


		$('a.publish-now').on('click', function(e) {
				e.preventDefault();
				var nonce = $(this).attr('data-nonce');
				var split = $(this).attr('href').replace('?', '').split('&').map(function(val) {
						return val.split('=');
				});
				postID = split[0][1];
				$.post(ajaxurl, {
						'action': 'publish_now',
						'pid': postID,
						'_wp_nonce': nonce
				}, function(data) {
						if (data.error) alert(data.error);
						else location.reload(true);

				});
		});
});