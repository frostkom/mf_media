window.wp = window.wp || {};

(function($)
{
	function change_selected()
	{
		if(script_media.current_media_category > 0)
		{
			$('.attachment-' + script_media.taxonomy + '-filter').val(script_media.current_media_category).change();
		}
	}

	var media = wp.media;

	media.view.AttachmentFilters.Taxonomy = media.view.AttachmentFilters.extend(
	{
		tagName: 'select',
		createFilters: function()
		{
			var filters = {},
				that = this;

			_.each(that.options.termList || {}, function(term, key)
			{
				var term_id = term['term_id'],
					term_name = $("<div/>").html(term['term_name']).text();

				filters[ term_id ] = {
					text: term_name,
					priority: key + 2
				};

				filters[term_id]['props'] = {};
				filters[term_id]['props'][that.options.taxonomy] = term_id;
			});

			filters.all = {
				text: that.options.termListTitle,
				priority: 1
			};

			filters['all']['props'] = {};
			filters['all']['props'][that.options.taxonomy] = null;

			this.filters = filters;
		}
	});

	var curAttachmentsBrowser = media.view.AttachmentsBrowser;

	media.view.AttachmentsBrowser = media.view.AttachmentsBrowser.extend(
	{
		createToolbar: function()
		{
			var filters = this.options.filters;

			curAttachmentsBrowser.prototype.createToolbar.apply(this, arguments);

			var that = this,
				i = 1,
				term_list = eval(script_media.term_list);

			if(term_list && filters)
			{
				that.toolbar.set(script_media.taxonomy + '-filter', new media.view.AttachmentFilters.Taxonomy(
				{
					controller: that.controller,
					model: that.collection.props,
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
})(jQuery);