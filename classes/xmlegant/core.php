<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Kohana 3 module XMLegant 
 * port of XMLegant class by Bill Zeller (http://from.bz/)
 *
 * @author Avis (SMGladkovskiy@gmail.com)
 * @license BSD http://creativecommons.org/licenses/BSD/
 */

abstract class XMLegant_Core implements ArrayAccess {

    protected $name = NULL;
    protected $text = FALSE;
    protected $attrs = array();
    protected $children = array();
    protected $parent = NULL;

	// map of child names to a list of objects
    protected $child_names = array();
    
    // replacing foo_bar by foo:bar if TRUE
    protected $replace_underscores = TRUE;

	/**
	 *
	 * @param string $parent
	 * @param string $name
	 * @param boolean $text
	 * @param array $attrs
	 */
	public function __construct($parent = NULL, $name = NULL, $text = FALSE, $attrs = array())
	{
		$this->parent = $parent;
		$this->name = $name;
		$this->text = $text;
		$this->attrs = $attrs;
	}

	/**
	 *
	 * @param boolean $first_child
	 * @return object
	 */
    public function create($first_child = FALSE)
    {
        $x = new XMLegant();
        if($first_child == FALSE)
		{
            return $x;
        }
		else
		{
            return $x->add_child($first_child);
        }
        
    }

	/**
	 *
	 * @return string
	 */
    public function get_root()
    {
        $node = $this;
        while($node->get_parent() != NULL)
		{
            $node = $node->get_parent();
        }
        
        return $node;
    }

	/**
	 *
	 * @return string
	 */
    public function get_parent()
    {
        return $this->parent;
    }

	/**
	 *
	 * @param boolean $replace
	 */
    public function set_replace_underscores($replace = TRUE)
    {
        $this->replace_underscores = $replace;
        foreach($this->children as $child)
		{
            $child->set_replace_underscores($replace);
        }
    }

	/**
	 *
	 * @return boolean
	 */
    public function has_attrs()
    {
        return ! empty($this->attrs);
    }
    
    /**
	 *
	 * @param integer $offset
	 * @return boolean
	 */
    public function offsetExists($offset)
    {
        if(empty($offset))
		{
            return FALSE;
        }
		elseif(is_int($offset) || ctype_digit($offset))
		{
            isset($this->parent->child_names[$this->name][$offset]);
        }
		else
		{
            return isset($this->attrs[$offset]);
        }        
        
    }

	/**
	 *
	 * @param integer $offset
	 * @return boolean
	 */
    public function offsetGet($offset)
    {
        if(empty($offset))
		{

			/**
			 * We need to prevent:
			 *  $x->a->b[];
			 * from creating two 'b' nodes. Without this, one 'b' node would
			 * be created with the call to '$x->a->b' and another one
			 * would be created with a call to '[]'. To prevent this, we
			 * return the most recently created node if there is only one of
			 * them and if that node is empty.
			 */
            if(count($this->parent->child_names[$this->name]) == 1
                AND $this->parent->child_names[$this->name][0]->is_empty())
			{
                return $this->parent->child_names[$this->name][0];
            }
			else
			{
                return $this->parent->add_child($this->name);
            }
        }
		elseif(is_int($offset) || ctype_digit($offset))
		{
            return
				isset($this->parent->child_names[$this->name][$offset])
					? $this->parent->child_names[$this->name][$offset]
					: NULL;
        }
		else
		{
            return
				isset($this->attrs[$offset])
					? $this->attrs[$offset]
					: NULL;
        }
    }

	/**
	 *
	 * @param integer $offset
	 * @param string $value
	 */
    function offsetSet($offset, $value)
    {
        if(empty($offset))
		{
            if(count($this->parent->child_names[$this->name]) == 1
                AND $this->parent->child_names[$this->name][0]->is_empty())
			{
                $this->parent->__set($this->name, $value);
            }
			else
			{
                $this->parent->add_child($this->name);
                $this->parent->__set($this->name, $value);
            }            
        }
		elseif(is_int($offset) || ctype_digit($offset))
		{
            $child = $this->parent->child_names[$this->name][$offset];
            $this->set_child($child, $value);
        }
		else
		{
            $this->attrs[$offset] = (string) $value;
        }
    }

	/**
	 *
	 * @param integer $offset
	 */
    function offsetUnset($offset)
    {
        unset($this->attrs[$offset]);
    }
    

	/**
	 *
	 * @return boolean
	 */
    function has_children()
    {
        return ! empty($this->children);
    }
        
    /**
	 * Get the last child with name $name
	 * If one doesn't exist, create it (if $create is TRUE)
	 *
	 * @param string $name
	 * @param boolean $create
	 * @return object
	 */
    protected function last_child_by_name($name, $create = TRUE)
    {
        if(isset($this->child_names[$name]))
		{
            return $this->child_names[$name][count($this->child_names[$name])-1];
        }
		elseif($create)
		{
            return $this->add_child($name);
        }
		else
		{
            return NULL;
        }
    }
    
    /**
	 * Create a new child
	 *
	 * @param string $name
	 * @return object
	 */
    protected function add_child($name)
    {
        return $this->add_child_object(new XMLegant($this, $name));
    }

	/**
	 * Adding child object
	 *
	 * @param XMLegant_Core $child
	 * @return object $child
	 */
    protected function add_child_object(XMLegant_Core $child)
    {
        $this->child_names[$child->name][] = $child;
        $this->children[] = $child;
        $this->text = FALSE;
        $child->parent = $this;
        return $child;
    }

	/**
	 * Setting child node
	 *
	 * @param object $child
	 * @param array $value
	 */
    protected static function set_child($child, $value)
    {
        $child->delete_children();
        
        // if given an associative array, assume these are attributes
        if(is_array($value)
			AND array_keys($value) != range(0, count($value)-1))
		{
            $child->attrs = $value;
        }
		elseif(is_a($value, 'XMLegant_Core'))
		{
            // Each XMLegant object has a "dummy" top node. When adding
            // an XMLegant object as a child node, we reach through this wrapper
            // to obtain the child node.
            if($value->has_children())
			{
                foreach($value->children as $valChild)
				{
                    $child->add_child_object(clone $valChild);
                }
            }
        }
		else
		{
            if($value === FALSE)
			{
                $child->text = FALSE;
            }
			else
			{
                $child->text = (string) $value;
            }
        }        
    }

	/**
	 * Deleting children nodes
	 */
    public function delete_children()
    {
        $this->child_names = array();
        $this->children = array();
    }
    
    /**
	 * Set empty node if it has no attributes, no children and no text
	 *
	 * @return array
	 */
    protected function is_empty()
    {
        return empty($this->attrs) 
                AND empty($this->children)
                AND $this->text === FALSE;
    }

	/**
	 * Convert XMLegant object to xml
	 *
	 * @param bool $header
	 * @param XMLWriter $writer
	 * @return string
	 */
    function to_xml($header = TRUE, XMLWriter $writer = NULL)
    {
        if($writer === NULL)
        {
            $version = '1.0';
            $enc = NULL;
            $standalone = NULL;
            if($this->parent === NULL)
			{
                if(isset($this['version']))
                    $version = $this['version'];

                if(isset($this['encoding']))
                    $enc = $this['encoding'];
				
                if(isset($this['standalone']))
                    $standalone = $this['standalone'];
            }
            
            $writer = new XMLWriter();
            $writer->openMemory();
            if($header)
			{
                $writer->startDocument($version, $enc, $standalone);
            }
			else
			{
                $writer->startDocument();
            }
            
            if($this->parent === NULL)
			{
                if($this->has_children())
				{
                    foreach($this->children as $child)
					{
                        $child->to_xml($header, $writer);
                    }
                }    
            }
			else
			{
                $this->to_xml($header, $writer);
            }
            
            $xml = $writer->outputMemory(true);
            
            if( ! $header)
                $xml = str_replace("<?xml version=\"1.0\"?>\n", '', $xml);
            return $xml;
        }
		else
		{
            if($this->replace_underscores)
			{
                $writer->startElement(str_replace('_', ':', $this->name));
            }
			else
			{
                $writer->startElement($this->name);
            }     
            
            if($this->has_attrs())
			{
                foreach($this->attrs as $key=>$val)
				{
                    if($this->replace_underscores)
					{
                        $writer->writeAttribute(str_replace('_', ':', $key), $val);
                    }
					else
					{
                        $writer->writeAttribute($key, $val);
                    }
                }    
            }                   
            
            if($this->has_children())
			{
                foreach($this->children as $child)
				{
                    $child->to_xml($header, $writer);
                }
            }
			else
			{
                if($this->text !== FALSE)
				{
                    $writer->text($this->text);
                }
            }            

            $writer->endElement();            
        }
               
    }

	/**
	 * Converting xml to a SimpleXML object
	 *
	 * @return object
	 */
    function to_simplexml()
    {
        return simplexml_load_string($this->to_xml());
    }

	/**
	 *
	 * @param string $name
	 * @param string $value
	 */
	public function __set($name, $value)
    {
        $this->set_child($this->last_child_by_name($name), $value);
    }

	/**
	 *
	 * @param string $name
	 * @return string
	 */
    public function __get($name)
    {
        return $this->last_child_by_name($name);
    }

	/**
	 *
	 * @param string $name
	 * @param array $args
	 * @return object
	 */
    public function __call($name, $args)
    {
        $child = $this->add_child($name);
        switch(count($args))
		{
            case 0:
                return $child;
            case 1:
                XMLegant::set_child($child, $args[0]);
                break;
            case 2:
                $child->offsetSet($args[0], $args[1]);
                break;
        }

        return $this;
    }

	/**
	 * Object Cloning
	 */
    public function __clone()
    {
        $this->parent = NULL;
        $children = $this->children;
        $this->delete_children();

        foreach($children as $child)
        {
            $this->add_child_object(clone $child);
        }
    }

    /**
	 * @return string
	 */
    public function __toString()
    {
        $s = $this->name.': (';

        if($this->has_children())
		{
            foreach($this->children as $child)
			{
                $s .= $child->name.': ';
                if($child->has_children())
				{
                    $s .= count($child->children);
                }
				else
				{
                    $s .= "\"{$child->text}\"";
                }

            }
            $s .= ',';
        }
		else
		{
            $s .= "\"{$this->text}\"";
        }

        $s .= ")\n";

        return $s;
    }
} // End XMLegant_Core