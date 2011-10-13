function createMap(options)
{	
	// Create an instance of the GeoAdmin API for each map
	api = new GeoAdmin.API({lang: options.lang});
	var id = 'geoadmin' + options.id;
	// Create layertree elements if needed
	if(options.showlayertree == "1")
	{
		var lt = new Element('td#' + id + '_layertreewrapper');
		lt.inject($(id + '_map').getParent(), 'before');
		var ltdiv = new Element('div#' + id + '_layertree');
		ltdiv.inject(lt);
		
		// Create link with slide effect
		var layertreefx = new Fx.Slide(id + '_layertree', { mode: 'horizontal' });
		var ltlink = new Element('a').set({
				href: 'javascript:void()', 
				events: {
					click: function() {
						// Change text according to (old) state
						if(layertreefx.open)
							ltlink.innerText = options.opentext;
						else
							ltlink.innerText = options.closetext;
						layertreefx.toggle();
					}
				}
			});
		ltlink.inject($(id), 'top');
	}
	
	
	//Create a GeoExt map panel placed in the geoadminmap div
	var map = api.createMap({
		div: id + '_map',
		layers: options.layers,
		layers_opacity: options.layers_opacity,
		layers_visibility: options.layers_visibility,
		bgLayer: options.map,
		easting: parseFloat(options.easting),
		northing: parseFloat(options.northing),
		zoom: parseInt(options.zoom)
	});
	
	// Add a tooltip
	var tooltip = new GeoAdmin.Tooltip({ baseUrl: 'http://www.google.ch/'});
	map.addControl(tooltip);
	tooltip.activate();

	// Add LayerTree
	if(options.showlayertree == "1")
	{	
		var layertree = new GeoAdmin.LayerTree({
			map: map,
			renderTo: id + '_layertree',
			height: parseInt(options.height)
		});
		
		// Set inital state...
		if(options.layertreeopen == "1")
			ltlink.innerText = options.closetext;
		else
		{
			ltlink.innerText = options.opentext;
			layertreefx.hide();
		}
	}
	if(options.showmousepos == "1")
	{
		var mpdiv = new Element('div#' + id + '_mousepos').setStyle('height', '18px');
		mpdiv.inject($(id), 'bottom');
		var mousePosition = new GeoAdmin.MousePositionBox({
				renderTo: id + '_mousepos',
				map: map
			});
	}}