window.wp = window.wp || {};

(function($)
{
	$(document).ready(function() /* Has to be here, otherwise it does not work */
	{
		function change_selected()
		{
			if(script_media.current_media_category > 0)
			{
				$('.attachment-' + script_media.taxonomy + '-filter').val(script_media.current_media_category).change();
			}
		}

		var media = wp.media,
			curAttachmentsBrowser = media.view.AttachmentsBrowser,
			term_list = eval(script_media.term_list);

		if(term_list.length > 0)
		{
			/* New version */
			/*var html = "";

			html += "<select name='category' class='" + script_media.taxonomy + '-filter attachment-' + script_media.taxonomy + '-filter' + "'>"
				+ "<option value=''>" + script_media.list_title + "</option>";

				$.each(term_list, function(key, value)
				{
					console.log(key , value);

					html += "<option value='" + value.term_id + "'>" + value.term_name + "</option>";
				});

			html += "</select>";

			$("#media-attachment-date-filters").after(html);*/

			/* Old version */
			media.view.AttachmentFilters.Taxonomy = media.view.AttachmentFilters.extend(
			{
				tagName: 'select',
				createFilters: function()
				{
					var filters = {},
						self = this;

					_.each(self.options.termList || {}, function(term, key)
					{
						var term_id = term['term_id'],
							term_name = $("<div/>").html(term['term_name']).text();

						filters[term_id] = {
							text: term_name,
							priority: key + 2
						};

						filters[term_id]['props'] = {};
						filters[term_id]['props'][self.options.taxonomy] = term_id;
					});

					filters.all = {
						text: self.options.termListTitle,
						priority: 1
					};

					filters['all']['props'] = {};
					filters['all']['props'][self.options.taxonomy] = null;

					this.filters = filters;
				}
			});

			media.view.AttachmentsBrowser = media.view.AttachmentsBrowser.extend(
			{
				createToolbar: function()
				{
					var filters = this.options.filters;

					curAttachmentsBrowser.prototype.createToolbar.apply(this, arguments);

					var self = this,
						i = 1;

					if(term_list && filters)
					{
						self.toolbar.set(script_media.taxonomy + '-filter', new media.view.AttachmentFilters.Taxonomy(
						{
							controller: self.controller,
							model: self.collection.props,
							priority: -80 + 10 * i++,
							taxonomy: script_media.taxonomy,
							termList: term_list,
							termListTitle: script_media.list_title,
							className: script_media.taxonomy + '-filter attachment-' + script_media.taxonomy + '-filter'
						}).render());

						setTimeout(function()
						{
							change_selected();
						}, 1000);
					}
				}
			});
		}
	});
})(jQuery);