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
			$doc->addScript('plugins/content/swissgeoadmin/js/swissgeoadmin.js');
			$doc->addStyleSheet('plugins/content/swissgeoadmin/css/swissgeoadmin.css');
			
			// Default parameters
			$param = $this->params;
			
			// Set the system language as default
			$lang = & JFactory::getLanguage();
			$langcode = $lang->getTag();
			$param->set('lang', $langcode);
			
			// Loop through each match (support multiple maps on one page)
			$pararray = array();
			foreach($matches as $match)
			{
				$wholetag = $match[0];
				$options = $match[1];
				
				// Create a map object, give it a clone of the default parameters..
				$map = new SwissGeoAdminMap(clone $param, $options);
				
				// Add settings to array for Javascript...
				$pararray[] = $map->getParams()->toArray();
				
				// HTML for this map...
				$html = $map->getHtml();
				
				// Inject HTML into the article
				$row->text = str_replace($wholetag, $html, $row->text);
			}
			// Add javascript...
			$doc->addScriptDeclaration(SwissGeoAdminMap::getCommonJs($pararray));
			
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
	// Count's the map to generate and address elements with unique identities
	private static $idcounter = 0;

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
		if (preg_match('/width=([^\s]+)/', $options, $option))
			$param->set('width', $option[1]);
		if (preg_match('/showlayertree=([^\s]+)/', $options, $option))
			$param->set('showlayertree', $option[1]);
		if (preg_match('/layertreeopen=([^\s]+)/', $options, $option))
			$param->set('layertreeopen', $option[1]);
		if (preg_match('/lang=([^\s]+)/', $options, $option))
			$param->set('lang', $option[1]);
	
		// Generate an id for this map
		$param->set('id', self::$idcounter++);
		$param->set('opentext', JText::_('Open Layers'));
		$param->set('closetext', JText::_('Close Layers'));
	
		// Merge the user provided parameters with the default paramters...
		$defaultparam->merge($param);
		$this->param = $defaultparam;
	}
	
	
	public function getParams() {
		return $this->param;
	}

	public static function getCommonJs($pararray)
	{
		// Common JS which loops through settings array to create maps
		$json = json_encode($pararray);
		$js = "
	var options = ".$json.";
	window.addEvent('load', function() {
		// Create map(s) with options from settings array
		for(i=0;i<options.length;i++)
			createMap(options[i]);
		});
		";
		
		return $js;
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
		
		$style_geoadmin = '';
		if(isset($width) && intval($width) > 0)
			$style_geoadmin .= 'width:'.$width.'px;';
		else
			$style_geoadmin .= 'width:100%;';
		
		$id = intval($this->param->get('id'));
		$html = '
			<!-- PlugIn SwissGeoAdmin -->
			<div class="geoadmin" id="geoadmin'.$id.'" style="'.$style_geoadmin.'">
				<table style="height:'.$height.'px;'.$style_geoadmin.'">
					<tbody>
					<tr id="geoadmin'.$id.'_maprow">
						<td style="width: 100%">
							<div class="map" id="geoadmin'.$id.'_map" style="height:'.$height.'px;"></div>
						</td>
					</tr>
					</tbody>
				</table>
			</div>
		';
		return $html;
	}
	
	
}