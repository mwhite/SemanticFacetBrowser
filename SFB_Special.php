<?php

/*
 *	This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

define('MAX_VISIBLE_PROPERTIES', 10);

class SFBSpecialPage extends SpecialPage {
	/** Whether the jQuery UI datepicker has already been included. */
	protected $datePickerIncluded = false;
	
	/** SFBFacetRetriever object */
	protected $facets;	
	
	/** URL parameters **/	
	/** Name of the requested category. */
	protected $categoryName;
	
	/** Array of property values/limits keyed by property name. */
	protected $properties;
	
	/** Array of labels keyed by property name. */
	protected $printouts;
	
	/** Maximum number of properties to output in facet selector.  Not implemented. */
	protected $propertyLimit;	
	
	
	public function __construct() {
		parent::__construct('BrowseFacets');
	}	
	
	public function execute($p) {
		global $wgOut, $wgRequest, $sfbgScriptPath, $wgScriptPath;
		
		wfProfileIn('SFB:execute');
		
		// process input parameters	
		$this->categoryName = $p;
		$this->properties = $wgRequest->getArray('p');
		$this->properties = ($this->properties === NULL) ? array() : $this->properties;
		$this->printouts = $wgRequest->getArray('po');
		$this->printouts = ($this->printouts === NULL) ? array() : $this->printouts;
		$this->propertyLimit = $wgRequest->getText('plim');
		
		if ($wgRequest->getText('ajax') == '1') {
			$wgOut->disable();
			echo $this->getResultOutput();
			return;			
		} else {			
			// add JSS and CSS
			$this->addJQuery();				
			$wgOut->addScriptFile($sfbgScriptPath . '/main.js');
			$wgOut->addStyle($sfbgScriptPath . '/style.css');
			
			try {
				$this->facets = new SFBFacetRetriever($this->categoryName);
				$this->printResultsPage();
			} catch (NonexistentCategoryError $e) {
				$this->printLandingPage();
			}				
		}
			
		wfProfileOut('SFB:execute');
	}
	
	protected function getResultOutput() {	
		global $wgOut;
		
		$wikiText = "{{#ask: [[Category:{$this->categoryName}]] ";
		foreach ($this->properties as $name => $value) {
			if (preg_match("/^min\:(.*);max\:(.*)$/", $value, $m)) {
				if ($m[1] !== "") {
					$wikiText .= "[[$name::>{$m[1]}]] ";
				}
				if ($m[2] !== "") {
					$wikiText .= "[[$name::<{$m[2]}]] ";
				}
			} else {
				$wikiText .= "[[$name::$value]] ";
			}
		}
		
		foreach ($this->printouts as $name => $label) {
			if ($name == $label) {
				$wikiText .= "\n|?$name";
			} else {
				$wikiText .= "\n|?$name = $label";
			}
		}	
		
		$wikiText .= "\n|format=" . ($this->printouts ? "broadtable" : "category" ) . "}}";	
		
		return $wgOut->parse($wikiText);
		//return "<pre>" . print_r(array('cat' => $this->categoryName, 'p' => $this->properties, 'po' => $this->printouts), true) . "</pre>";
	}
	
	protected function printLandingPage() {
	}
	
	protected function printResultsPage() {
		global $wgOut, $wgUseAjax;
		
		// build html		
		$wgOut->setHtmlTitle("Browse facets");
		$wgOut->setPageTitle("Browse facets");
		
		$wgOut->addHtml('<div id="sfb_sidebar">');
		$count = count($this->facets->properties);
		$category = new SFBProperty;
		$category->name = "Category";
		$category->id = SFBFacetRetriever::normalizeNameAsHtmlId("Category");
		$category->values = $this->facets->getCategories();		
		$category->currentValue = $this->categoryName;
				
		$this->facets->properties = array_merge(array($category->id => $category), $this->facets->properties);
		$wgOut->addInlineScript('var sfb_property_data = ' . json_encode($this->facets->properties) . ';' .
								"var sfb_base_url = '$wgScriptPath';" .
								"var datepicker_locale = 'en';" .
								"var sfb_cat = '{$this->categoryName}';" .
								"var wg_use_ajax = $wgUseAjax;" );		
		$i = 0;
		foreach ($this->facets->properties as $p) {
			$i++;
			$this->printPropertyInput($p);
			
			if ($i == MAX_VISIBLE_PROPERTIES && $count > MAX_VISIBLE_PROPERTIES) {
				$wgOut->addHtml('<div id="props_extra" style="display:none">');
			}
		}
		if ($count > MAX_VISIBLE_PROPERTIES) {
			$wgOut->addHTML('</div><button class="ui-state-default" id="show_extra_props">More</button><div class="clear"></div>');
		}
		
		$wgOut->addHtml('</div><div id="sfb_results">');		
		
		$wgOut->addHtml($this->getResultOutput());
		$wgOut->addHtml('</div>');
		
	}
	
	
	protected function printPropertyInput($property) {
		global $wgOut;
		
		switch ($property->type) {
			case '_num':
				$extra = $this->getNumberSelector($property->id);
				break;
			case '_boo':
				$extra = $this->getBooleanSelector();
				break;
			case '_dat':
				$extra = $this->getDateSelector();
				break;
			case '_tem':
				$extra = $this->getTemperatureSelector();
				break;
			case '_tel':
				$extra = $this->getPhoneSelector();
				break;
			case '_rec':
				$extra = $this->getRecordSelector();
				break;
			case '_geo':
				$extra = $this->getCoordSelector();
				break;
			default:
				$extra = '';
		}
		
		if ($extra) {
			$extra = "<br>$extra";
		}
		
		$display = ($property->name == 'Category') ? 'none' : 'none';
		$arrowdir = ($display == 'none') ? 'e' : 's';
		$value = str_replace('"', '\"', $property->currentValue);
		
		$status = ($property->name == 'Category') 
			? '' 
			: '<input type="checkbox" style="float:right" class="property-status" data-prop="'
				. $property->name .'" />';
		
		$m = <<<END

<div class="prop-box" data-prop="{$property->name}">
	<div class="prop-box-header">
		<div class="prop-box-clicker"><span class="ui-icon ui-icon-triangle-1-$arrowdir" style="float:left"></span>
		<a href="#" title="{$property->count}" class="sfb-prop-label" style="float:left">{$property->name}</a>	
		</div>
		$status
		<div class="clear"></div>			
	</div>
	
	<div class="prop-box-content" style="display:$display">
		<input type="text" class="property-value" id="value-{$property->id}" value="$value" />	
		$extra
	</div>
</div>
	
END;
		$wgOut->addHTML($m);
	}
	
	
	protected function getNumberSelector($propId) {
		return "from <input class='min' id='min-$propId' type='text'/> to <input class='max' id='max-$propId' type='text'/>";
	}
	
	protected function getBooleanSelector() {
		return '<select><option value="1">true</option><option value="0">false</option></select>';
	}
	
	protected function getDateSelector($propId) {
		global $wgOut;
		
		if (!$this->datePickerIncluded) {
			//$wgOut->addScriptFile("$smwgScriptPath/libs/jquery-ui/jquery.ui.datepicker.min.js");
		}
		
		return "from <input class='min' id='min-$propId' type='text' class='datepicker'/>" .
				" to <input class='max' id='max-$propId' type='text' class='datepicker'/>";
	}
	
	protected function getTemperatureSelector() {
		// min with unit selector
		// max with unit selector
	}
	
	protected function getPhoneSelector() {
		// multi-box, auto-tabbing entry
	}
	
	protected function getRecordSelector() {
	}
	
	protected function getCoordSelector() {
		// min/max latitude selector
		// min/max longitude selector
	}
	
	/**
	 * Include jQuery and jQuery UI.  Copied from SMWAskPage.
	 */
	protected function addJQuery() {
		global $wgOut, $smwgScriptPath, $smwgJQueryIncluded, $smwgJQueryUIIncluded;
		// Add CSS and JavaScript for jQuery and jQuery UI.
		$wgOut->addExtensionStyle( "$smwgScriptPath/skins/jquery-ui/base/jquery.ui.all.css" );
		
		$scripts = array();
		if ( !$smwgJQueryIncluded ) {
			 if ( method_exists( 'OutputPage', 'includeJQuery' ) ) {
				 $wgOut->includeJQuery();
			 } else {
				 $scripts[] = "$smwgScriptPath/libs/jquery-1.4.2.min.js";
			 }
			 
			 $smwgJQueryIncluded = true;
		}

		if ( !$smwgJQueryUIIncluded ) {
			 $scripts[] = "$smwgScriptPath/libs/jquery-ui/jquery.ui.core.min.js";
			 $scripts[] = "$smwgScriptPath/libs/jquery-ui/jquery.ui.widget.min.js";
			 $scripts[] = "$smwgScriptPath/libs/jquery-ui/jquery.ui.position.min.js";
			 $scripts[] = "$smwgScriptPath/libs/jquery-ui/jquery.ui.autocomplete.min.js";
			 $smwgJQueryUIIncluded = true;
		}
		foreach ( $scripts as $js ) {
			 $wgOut->addScriptFile( $js );
		}
	}
	
	protected function doAjaxResultsAction() {
	}
	
}
