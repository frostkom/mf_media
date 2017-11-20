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

		/*media.view.Attachment = media.view.Attachment.extend(
		{
			template: wp.template('mf-attachment'),
			render: function()
			{
				console.log("Render" , this.$el);
			}
		});*/

		if(term_list.length > 1)
		{
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