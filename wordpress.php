<?php

/*
|---------------------------------------------------------------
| WordPress-CI
|---------------------------------------------------------------
|
| A model designed for interfacing with a WordPress database.
| Built for WordPress version 3.0.4
|
| @author     James Brumond
| @version    0.1.1-dev
| @copyright  Copyright 2011 James Brumond
| @license    Dual licensed under MIT and GPL
| @requires   CodeIgniter
|
*/

class Wordpress extends Model {
	
	/**
	 * The master CI object
	 */
	var $CI = null;
	
	/**
	 * The table prefix
	 *
	 * @access  public
	 * @type    string
	 */
	var $_table_prefix = 'wp_';
	
	/**
	 * Logged in cookie
	 *
	 * @access  public
	 * @type    string
	 */
	var $_logged_in_cookie = false;
	
	/**
	 * Constructor
	 */
	function Wordpress()
	{
		$this->CI =& get_instance();
	}
	
	/**
	 * Change the table prefix
	 *
	 * @access  public
	 * @param   stirng    the new prefix
	 * @return  void
	 */
	function set_table_prefix($prefix = 'wp_')
	{
		if (is_string($prefix))
		{
			$this->_table_prefix = $prefix;
		}
	}
	
	/**
	 * Read an option
	 *
	 * @access  public
	 * @param   string    the option
	 * @return  mixed
	 */
	function get_option($option)
	{
		$this->CI->db->select('option_value');
		$this->CI->db->from($this->_table_prefix.'options');
		$this->CI->db->where('option_name', $option);
		
		$query = $this->CI->db->get();
		
		if ($query->num_rows())
		{
			$row = $query->row_array();
			return $row['option_value'];
		}
		return null;
	}
	
	/**
	 * Read a post
	 *
	 * @access  public
	 * @param   int       the post id
	 * @return  object
	 */
	function get_post($id)
	{
		$this->CI->db->select('*');
		$this->CI->db->from($this->_table_prefix.'posts');
		$this->CI->db->where('ID', $id);
		
		$query = $this->CI->db->get();
		
		if ($query->num_rows())
		{
			return $query->row();
		}
	}
	
	/**
	 * Read posts, optionally by type
	 *
	 * @access  public
	 * @param   string    the types
	 * @return  array
	 */
	function get_posts($types = null)
	{
		$this->CI->db->select('*');
		$this->CI->db->from($this->_table_prefix.'posts');
		
		if (is_string($types))
		{
			$types = explode('|', $types);
			$this->CI->db->where_in('post_type', $types);
		}
		
		$query = $this->CI->db->get();
		
		$result = array();
		if ($query->num_rows())
		{
			$result = $query->result();
		}
		return $result;
	}
	
	/**
	 * Reads a user's meta data
	 *
	 * @access  public
	 * @param   int       the user id
	 * @return  array
	 */
	function get_user_meta($id)
	{
		$this->CI->db->select('meta_key, meta_value');
		$this->CI->db->from($this->_table_prefix.'usermeta');
		$this->CI->db->where('ID', $id);
		
		$query = $this->CI->db->get();
		
		if ($query->num_rows())
		{
			$result = array();
			foreach ($query->result() as $row)
			{
				$result[$row->meta_key] = $row->meta_value;
			}
			return $result;
		}
		
		return false;
	}
	
	/**
	 * Reads a user
	 *
	 * @access  public
	 * @param   int       the user id
	 * @return  object
	 */
	function get_user($id)
	{
		$meta = $this->get_user_meta($id);
		
		$this->CI->db->select('*');
		$this->CI->db->from($this->_table_prefix.'users');
		$this->CI->db->where('user_id', $id);
		
		$query = $this->CI->db->get();
		
		if ($query->num_rows())
		{
			$row = $query->row_array();
			$row['meta'] = $meta;
			return ((object) $row);
		}
		
		return false;
	}
	
	/**
	 * Reads all users into an array
	 *
	 * @access  public
	 * @return  array
	 */
	function get_all_users()
	{
		$this->CI->db->select('ID');
		$this->CI->db->from($this->_table_prefix.'users');
		
		$query = $this->CI->db->get();
		
		$result = array();
		if ($query->num_rows())
		{
			foreach ($query->result() as $row)
			{
				$result[] = $this->get_user($row->id);
			}
		}
		return $result;
	}
	
	/**
	 * Defines the logged in cookie
	 *
	 * @access  public
	 * @return  void
	 */
	function _define_logged_in_cookie()
	{
		$site_url = $this->get_option('site_url');
		$cookie_hash = $site_url ? md5($cookie_hash) : '';
		$this->_logged_in_cookie = 'wordpress_logged_in_'.$cookie_hash;
	}
	
	/**
	 * Parse a WordPress auth cookie
	 *
	 * @access  public
	 * @return  string
	 */
	function _parse_auth_cookie()
	{
		if (! $this->_logged_in_cookie)
		{
			$this->_define_logged_in_cookie();
		}
		
		
	}
	
	
	
	
	
	
	
}

/* End of file wordpress.php */
/* Location ./application/models/wordpress.php */
