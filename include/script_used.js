jQuery(function($)
{
	$(".column-used .fa.fa-check.green").each(function()
	{
		var dom_obj = $(this).parent(".column-used");

		dom_obj.siblings(".column-title").find(".row-actions .delete").addClass('hide');
		dom_obj.siblings(".check-column").find("input").remove();
	});
});