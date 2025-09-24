(function($) {
	$(function() {

		$("input[name='sitemaplite_file_path']").on("click", function() {
			if ($(this).val() === "files" && $(this).is(":checked")) {
				$("p.hidden-unless-files").show();
			} else {
				$("p.hidden-unless-files").hide();
			}
			if ($(this).val() === "domains" && $(this).is(":checked")) {
				$("p.hidden-unless-domains").show();
			} else {
				$("p.hidden-unless-domains").hide();
			}
		}).each(function() {
			if ($(this).val() === "files" || $(this).val() === "domains") {
				$(this).triggerHandler("click");
			}
		});

		$("#domain-select").on('change', function() {
			var selected_domain = $(this).val();
			$(".domain-options").hide().filter("#domain-options-" + selected_domain).show();
		}).triggerHandler('change');

	});
})(jQuery);
