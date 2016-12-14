(function($) {
	$(function() {
		$("input[name='sitemaplite_file_path']").on("click", function() {
			if ($(this).val() === "files" && $(this).is(":checked")) {
				$("p.hidden-unless-files").show();
			} else {
				$("p.hidden-unless-files").hide();
			}
		}).each(function() {
			if ($(this).val() === "files") {
				$(this).triggerHandler("click");
			}
		});
	});
})(jQuery);