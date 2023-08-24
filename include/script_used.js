jQuery(function($)
{
	$(".column-used .fa.fa-check.green").each(function()
	{
		$(this).parent(".column-used").siblings(".column-title").find(".row-actions .delete").addClass('hide');
	});
});