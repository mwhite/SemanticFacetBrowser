SemanticFacetBrowser is intended to provide a more traditional faceted browsing
interface to a [Semantic MediaWiki][] installation's categories and properties than
existing tools like [Semantic Drilldown][].  It is inspired by the [DBPedia
Faceted Search](http://dbpedia.neofonie.de/browse/).

It is not being actively developed, and shouldn't be run on a production site.
It does all calculations on the database in realtime, including calculating what
properties occur on pages in a given categories, and provides autocompletion for
all values of all properties on all pages in a given category with one query.

Any production usage of SemanticFacetBrowser would be dependent on switching to
a calculated table of category-property relationships that is updated whenever
pages are saved using hooks.

Installation
------------

Add the following to your `LocalSettings.php` file:

    require_once("$IP/extensions/SemanticFacetBrowser/SemanticFacetBrowser.php");

Usage
-----

Navigate to Special:BrowseFacets/SomeCategoryName.

  [Semantic MediaWiki]: http://semantic-mediawiki.org/
  [Semantic Drilldown]: http://www.mediawiki.org/wiki/Extension:Semantic_Drilldown
