<?php

define('MAX_PROPERTIES_TO_RETRIEVE', 100);
define('MAX_VALUES_TO_RETRIEVE', 100);		// per property
define('DEFAULT_PROPERTY_LIMIT', 10);
define('DEFAULT_VALUE_LIMIT', 50);

class SFBProperty {
	/**
	 * The wiki-name of the property (with spaces).
	 */
	public $name;
	
	/**
	 * The valid HTML id string of the property.
	 */
	public $id;
	
	/**
	 * The number of times this property is used for the given category.
	 */
	public $count;
	
	/**
	 * The type string from the database, e.g. '_wpg'
	 */
	public $type;
	
	/**
	 * An array of SFBValue objects for this property.
	 */
	public $values = array();
	
	/**
	 * Used by SFBSpecialPage.
	 */
	public $currentValue;
	
}

class SFBValue {
	/**
	 * The string representation of this value.
	 */
	public $value;
	
	/*
	 * The number of times this value occurs for the given property.
	 */
	public $count;	
}

class NonexistentCategoryError extends Exception {};

/**
 * Provides access to the most common properties for pages in a given category and the most common
 * values for each of those properties.
 */
class SFBFacetRetriever {	
	protected $db;
	
	public $catName;
	
	/**
	 * An array of SFBValue objects for category names.
	 */
	public $categories;
	
	/**
	 * SFBCategory object for the super-category of the main category.
	 */
	public $supercat;
	
	/**
	 * An array of SFBProperty objects for properties of pages in the main category.
	 */
	public $properties;	
	
	/**
	 * @param string $categoryName the name of the category to look at
	 * @param int $propertyLimit the maximum number of properties to gather data for
	 * @param int $valueLimit the maximum number of values to gather data for for each property
	 */
	public function __construct($categoryName, $propertyLimit = false, $valueLimit = false) {				
		$this->db =& wfGetDB(DB_SLAVE);
		
		$category = Category::newFromName($categoryName);
		if (trim($categoryName) == "" || $category->getID() === false) {
			throw new NonexistentCategoryError();
		} else {			
			$this->catName = $category->getName();
			//$this->getSubcats($this->catName);
			$this->getProperties($this->catName);
		}		
	}
	
	public function normalizeNameAsHtmlId($name) {
		$name = preg_replace('/[^A-Za-z0-9\-_\.]/', '-', $name);
		
		if (!preg_match('/[A-Za-z]/', $name[0])) {
			$name = "a$name";
		}		
		return $name;
	}
	
	protected function getProperties($categoryName, $propertyLimit, $valueLimit) {
		if (!is_numeric($propertyLimit)) {
			$propertyLimit = DEFAULT_PROPERTY_LIMIT;
		}		
		if (!is_numeric($valueLimit)) {
			$valueLimit = DEFAULT_VALUE_LIMIT;
		}				
		$propertyLimit = min($propertyLimit, MAX_PROPERTIES_TO_RETRIEVE);
		$valueLimit = min($valueLimit, MAX_VALUES_TO_RETRIEVE);
		
		$sql = $this->getPropertiesQuerySql($categoryName, $propertyLimit, $valueLimit);
		$res = $this->db->query($sql);
		
		$this->properties = array();		
				
		while ($row = $this->db->fetchRow($res)) {
			$curId = $this->normalizeNameAsHtmlId($row['prop_name']);
			
			if (!isset($this->properties[$curId])) {
				$prop = new SFBProperty;
				$prop->name = $row['prop_name'];
				$prop->type = $row['prop_type'];
				$prop->id = $curId;
				$this->properties[$curId] = $prop;
			}
			
			$val = new SFBValue;
			$val->value = $row['value'];
			$val->count = $row['count'];
			$this->properties[$curId]->values[] = $val;
		}
		$this->db->freeResult($res);
		
		// would be nice to figure out how to do this in SQL
		foreach ($this->properties as &$p) {
			$p->count = count($p->values);
		}
		
		uasort($this->properties, 
			create_function('$p1,$p2', 'if ($p1->count == $p2->count){ return 0; }'
										.'else{ return $p1->count < $p2->count ? 1 : -1;}'));
	}
	
	/**
	 * Returns the database query sql to fetch properties and their values.
	 * @param string $categoryName the unescaped title of the category to look up properties for
	 * @param int $propertyLimit the maximum number of properties to fetch
	 * @param int $valueLimit the maximum number of values for each property to fetch
	 * @return string the sql
	 */
	protected function getPropertiesQuerySql($categoryName, $propertyLimit, $valueLimit) {
		$escapedName = $this->db->strencode(str_replace(" ", "_", $categoryName));
		
		// get the correct database table names
		$smw_ids = $this->db->tableName('smw_ids');
		$cl = $this->db->tableName('categorylinks');
		$atts = $this->db->tableName('smw_atts2');
		$rels = $this->db->tableName('smw_rels2');
		$spec = $this->db->tableName('smw_spec2');
		$page = $this->db->tableName('page');		
		
		/* The subquery selects name, occurrence count, and data type for all properties
		 * possessed by pages in the category, sorted by count. This is then used to select all 
		 * values of these properties sorted by the number of occurrences of each value.
		 */
		$sql = <<<END
SELECT 
	s.smw_sortkey AS prop_name, spec.value_string AS prop_type,
	foo.value AS value, COUNT(*) AS count 
FROM (
	SELECT * FROM (
		(
			SELECT 
				r.p_id, prop.smw_sortkey AS value
			FROM
				$cl cl
			INNER JOIN ($page p, $smw_ids s, $rels r, $smw_ids prop)
				ON (cl.cl_from = p.page_id
					AND p.page_title = s.smw_title 
					AND s.smw_id = r.s_id
					AND r.o_id = prop.smw_id)
					
			WHERE cl.cl_to = '$escapedName'		
		)
		
		UNION ALL
		
		(
			SELECT 
				a.p_id, CONCAT(a.value_xsd, ' ', a.value_unit) AS value
			FROM
				$cl cl
			INNER JOIN ($page p, $smw_ids s, $atts a)
				ON (cl.cl_from = p.page_id
					AND p.page_title = s.smw_title 
					AND s.smw_id = a.s_id)
					
			WHERE cl.cl_to = '$escapedName'	
		)
	) AS bar
	
) as foo

INNER JOIN ($smw_ids s, $spec spec)
	ON (foo.p_id = s.smw_id AND s.smw_id = spec.s_id)

GROUP BY foo.p_id, foo.value
ORDER BY foo.p_id, count DESC
		
END;
	
		return $sql;
	}
	
	protected function getSubcats($categoryName) {
		$escapedName = $this->db->strencode(str_replace(" ", "_", $categoryName));
		
		// get the correct database table names
		$cl = $this->db->tableName('categorylinks');
		$page = $this->db->tableName('page');		
		
		$sql = <<<END
SELECT
	p.page_title AS subcat, COUNT(p.page_title) AS count
FROM
	$cl cl
INNER JOIN
	($page p, $cl cl2)
ON (cl.cl_from = p.page_id
	AND p.page_title = cl2.cl_to)
WHERE
	cl.cl_to = '$escapedName'
	AND cl.cl_type = 'subcat'

GROUP BY cl.cl_from
ORDER BY count DESC

END;

		$res = $this->db->query($sql);
		
		while ($row = $this->db->fetchRow($res)) {
			echo "bar";
		}
	}
	
	public function getCategories() {
		$c = $this->db->tableName('category');
		$res = $this->db->query("SELECT c.cat_title AS value FROM $c c");
		
		$cats = array();
		while ($row = $this->db->fetchObject($res)) {
			$row->value = str_replace('_', ' ', $row->value);
			$cats[] = $row;
		}
		
		return $cats;
	}
}
