<?php
/**
 * @copyright	Copyright (C) 2011 Stefan Agner. All rights reserved.
 * @license		GNU General Public License version 3 or later; see LICENSE.txt
 */

defined('JPATH_BASE') or die;
define('TAG', 'geoadmin');

/**
 * Swiss GeoAdmin map plugin
 *
 * @package		SwissGeoAdmin
 * @version		0.1.0
 */
class plgContentSwissGeoAdmin extends JPlugin
{

	/**
	 * Constructor
	 *
	 * @access      public
	 * @param       object  $subject The object to observe
	 * @param       array   $config  An array that holds the plugin configuration
	 * @version		1.6
	 */
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage();
	}

	/**
	 * @param    string    The context of the content being passed to the plugin.
	 * @param    object    The article object.  Note $article->text is also available
	 * @param    object    The article params
	 * @param    int       The 'page' number
	 *
	 * @return    void
	 * @since    1.6
	 */
	function onContentPrepare( $context, &$row, &$params, $page) {
		// Search for {geoadmin ...} tags
		$regex = "/\{".TAG."\s+(.*?)\}/is";
		$contents = $row->text;
		if (preg_match_all( $regex, $contents, $matches, PREG_SET_ORDER )) {
			// We found at least one map, activate plugin...
			$plugin =& JPluginHelper::getPlugin('content', 'SwissGeoAdmin');
			
			// Add common stylesheets/javascripts
			$doc = &JFactory::getDocument();
			$doc->addScript('http://api.geo.admin.ch/loader.js');
			$doc->addStyleSheet('plugins/content/swissgeoadmin/css/swissgeoadmin.css');
			
			// Default parameters
			$param = $this->params;
			
			// Set the system language as default
			$lang = & JFactory::getLanguage();
			$langcode = $lang->getTag();
			$param->set('lang', $langcode);
			
			// Loop through each match (support multiple maps on one page)
			foreach($matches as $match)
			{
				$wholetag = $match[0];
				$options = $match[1];
				
				// Create a map object, give it a clone of the default parameters..
				$map = new SwissGeoAdminMap(clone $param, $options);
				
				// Get Javascript and HTML for this map...
				$js = $map->getJs();
				$html = $map->getHtml();
				
				// Add javascript...
				$doc->addScriptDeclaration($js);
				
				// ...and inject HTML into the article
				$row->text = str_replace($wholetag, $html, $row->text);
			}
		}
		
		return true;
	}
	
}

/**
 * A single GeoAdmin Map
 *
 * @package		SwissGeoAdmin
 * @version		1.0
 */
class SwissGeoAdminMap
{
	//private static int $test = 1;

	/**
	 * Constructor
	 *
	 * @access      protected
	 * @param       object  $defaultparam Default parameters
	 * @param       string  $options Options string for this specific map
	 *
	 * @since       1.0
	 */
	public function __construct($defaultparam, $options = '') {
		$param = new JRegistry();
		
		// Filter options out of the string provided by user
		if (preg_match('/map=([^\s]+)/', $options, $option))
			$param->set('map', $option[1]);
		if (preg_match('/layers=([^\s]+)/', $options, $option))
			$param->set('layers', $option[1]);
		if (preg_match('/easting=([^\s]+)/', $options, $option))
			$param->set('easting', $option[1]);
		if (preg_match('/northing=([^\s]+)/', $options, $option))
			$param->set('northing', $option[1]);
		if (preg_match('/zoom=([^\s]+)/', $options, $option))
			$param->set('zoom', $option[1]);
		if (preg_match('/showmousepos=([^\s]+)/', $options, $option))
			$param->set('showmousepos', $option[1]);
		if (preg_match('/height=([^\s]+)/', $options, $option))
			$param->set('height', $option[1]);
		if (preg_match('/showlayertree=([^\s]+)/', $options, $option))
			$param->set('showlayertree', $option[1]);
		if (preg_match('/layertreeopen=([^\s]+)/', $options, $option))
			$param->set('layertreeopen', $option[1]);
		if (preg_match('/lang=([^\s]+)/', $options, $option))
			$param->set('lang', $option[1]);
	
		// Merge the user provided parameters with the default paramters...
		$defaultparam->merge($param);
		$this->param = $defaultparam;
		$this->id = '';
	}

	/**
	 * Generates HTML code needed for map
	 *
	 * @access		protected
	 * @return		string HTML string placed at maps appearance
	 
	 * @since		0.1.0
	 */
	public function getHtml() {
		$height = $this->param->get('height');
		$width = $this->param->get('width');
		$showmousepos = intval($this->param->get('showmousepos'));
		$showlayertree = intval($this->param->get('showlayertree'));
		
		$style_geoadmin = '';
		if(isset($width) && is_int($width) && intval($width) > 0)
		{
			$style_geoadmin .= 'width:'.$width.'px;';
		}
		
		
		
		$id = $this->id;
		$html = '
			<!-- PlugIn SwissGeoAdmin -->
			<div class="geoadmin" id="geoadmin'.$id.'" style="'.$style_geoadmin.'">
			';
		if ($showlayertree)
			$html = '<a href="#" id="geoadmin'.$id.'_togglelayertree" onclick="layertreefx'.$id.'.toggle().chain(changeText());">Auswahl anzeigen</a>
			
		';
		$html .= '<table style="height:'.$height.'px;">
					<tbody>
					<tr>
						<td class="layertreewrapper" id="geoadmin'.$id.'_layertreewrapper">
								<div class="layertree" id="geoadmin'.$id.'_layertree"  style="width:200px;"></div>
						</td>
						<td style="width: 100%">
							<div class="map" id="geoadmin'.$id.'_map" style="height:'.$height.'px;"></div>
						</td>
					</tr>
					</tbody>
				</table>
			';
		
		if($showmousepos)
			$html .= '<div class="mousepos" id="geoadmin'.$id.'_mousepos" style="width:250px;height:25px;"></div>';
			
		$html .= "</div>\n";
		return $html;
	}
	
	/**
	 * Generates JavaScript code needed for map
	 *
	 * @access		protected
	 * @return		string JavaScript code
	 
	 * @since		0.1.0
	 */
	public function getJs() {
		// Load default parameters
		$layers = implode(',', $this->param->get('layers'));
		$bglayer = $this->param->get('map');
		$easting = $this->param->get('easting');
		$northing = $this->param->get('northing');
		$zoom = $this->param->get('zoom');
		$showmousepos = intval($this->param->get('showmousepos'));
		$height = intval($this->param->get('height'));
		$showlayertree = intval($this->param->get('showlayertree'));
		$layertreeopen = intval($this->param->get('layertreeopen'));
		$lang = $this->param->get('lang');
		 
		$opentext = JText::_('Open Layers');
		$closetext = JText::_('Close Layers');
		
		// Javascript needed
		$js = "
		
	var layertreefx;
	
	function changeText() {
		console.log($('geoadmin_togglelayertree'));
		if(layertreefx.open)
			$('geoadmin_togglelayertree').innerText = '$opentext';
		else
			$('geoadmin_togglelayertree').innerText = '$closetext';
	}
	
	window.addEvent('load', function() {
		// Create an instance of the GeoAdmin API
		api = new GeoAdmin.API({lang: '$lang'});

		//Create a GeoExt map panel placed in the geoadminmap div
		var map = api.createMap({
			div: 'geoadmin_map',
			layers: '$layers',
			bgLayer: '$bglayer',
			easting: $easting,
			northing: $northing,
			zoom: $zoom
		});
		
		
		// LayerTree
		var layertree = new GeoAdmin.LayerTree({
			map: map,
			renderTo: 'geoadmin_layertree',
			height: $height
		});
		
		// Layertree slide effect..
		if($showlayertree)
		{
			layertreefx = new Fx.Slide('geoadmin_layertree', { mode: 'horizontal' });		
			if(!$layertreeopen)
				layertreefx.hide();
				
			if(layertreefx.open)
				$('geoadmin_togglelayertree').innerText = '$closetext';
			else
				$('geoadmin_togglelayertree').innerText = '$opentext';
		}
		
		// Add a tooltip
		var tooltip = new GeoAdmin.Tooltip({ baseUrl: 'http://www.google.ch/'});
		map.addControl(tooltip);
		tooltip.activate();";
	
		if($showmousepos) {
			$js .= "
				var mousePosition = new GeoAdmin.MousePositionBox({
						renderTo: 'geoadmin_mousepos',
						map: map
					});
				console.log(mousePosition);
			";
		}
		$js .= "\n});";
		
		return $js;
	}
	
}