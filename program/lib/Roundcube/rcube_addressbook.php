<?php

/*
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2006-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Interface to the local address book database                        |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/


/**
 * Abstract skeleton of an address book/repository
 *
 * @package    Framework
 * @subpackage Addressbook
 */
abstract class rcube_addressbook
{
    /** constants for error reporting **/
    const ERROR_READ_ONLY = 1;
    const ERROR_NO_CONNECTION = 2;
    const ERROR_VALIDATE = 3;
    const ERROR_SAVING = 4;
    const ERROR_SEARCH = 5;

    /** public properties (mandatory) */
    public $primary_key;
    public $groups = false;
    public $export_groups = true;
    public $readonly = true;
    public $searchonly = false;
    public $undelete = false;
    public $ready = false;
    public $group_id = null;
    public $list_page = 1;
    public $page_size = 10;
    public $sort_col = 'name';
    public $sort_order = 'ASC';
    public $coltypes = array('name' => array('limit'=>1), 'firstname' => array('limit'=>1), 'surname' => array('limit'=>1), 'email' => array('limit'=>1));
    public $date_cols = array();

    protected $error;

    /**
     * Returns addressbook name (e.g. for addressbooks listing)
     */
    abstract function get_name();

    /**
     * Save a search string for future listings
     *
     * @param mixed Search params to use in listing method, obtained by get_search_set()
     */
    abstract function set_search_set($filter);

    /**
     * Getter for saved search properties
     *
     * @return mixed Search properties used by this class
     */
    abstract function get_search_set();

    /**
     * Reset saved results and search parameters
     */
    abstract function reset();

    /**
     * Refresh saved search set after data has changed
     *
     * @return mixed New search set
     */
    function refresh_search()
    {
        return $this->get_search_set();
    }

    /**
     * List the current set of contact records
     *
     * @param  array  List of cols to show
     * @param  int    Only return this number of records, use negative values for tail
     * @return array  Indexed list of contact records, each a hash array
     */
    abstract function list_records($cols=null, $subset=0);

    /**
     * Search records
     *
     * @param array   List of fields to search in
     * @param string  Search value
     * @param int     Matching mode:
     *                0 - partial (*abc*),
     *                1 - strict (=),
     *                2 - prefix (abc*)
     * @param boolean True if results are requested, False if count only
     * @param boolean True to skip the count query (select only)
     * @param array   List of fields that cannot be empty
     * @return object rcube_result_set List of contact records and 'count' value
     */
    abstract function search($fields, $value, $mode=0, $select=true, $nocount=false, $required=array());

    /**
     * Count number of available contacts in database
     *
     * @return rcube_result_set Result set with values for 'count' and 'first'
     */
    abstract function count();

    /**
     * Return the last result set
     *
     * @return rcube_result_set Current result set or NULL if nothing selected yet
     */
    abstract function get_result();

    /**
     * Get a specific contact record
     *
     * @param mixed record identifier(s)
     * @param boolean True to return record as associative array, otherwise a result set is returned
     *
     * @return mixed Result object with all record fields or False if not found
     */
    abstract function get_record($id, $assoc=false);

    /**
     * Returns the last error occured (e.g. when updating/inserting failed)
     *
     * @return array Hash array with the following fields: type, message
     */
    function get_error()
    {
        return $this->error;
    }

    /**
     * Setter for errors for internal use
     *
     * @param int Error type (one of this class' error constants)
     * @param string Error message (name of a text label)
     */
    protected function set_error($type, $message)
    {
        $this->error = array('type' => $type, 'message' => $message);
    }

    /**
     * Close connection to source
     * Called on script shutdown
     */
    function close() { }

    /**
     * Set internal list page
     *
     * @param  number  Page number to list
     * @access public
     */
    function set_page($page)
    {
        $this->list_page = (int)$page;
    }

    /**
     * Set internal page size
     *
     * @param  number  Number of messages to display on one page
     * @access public
     */
    function set_pagesize($size)
    {
        $this->page_size = (int)$size;
    }

    /**
     * Set internal sort settings
     *
     * @param string $sort_col Sort column
     * @param string $sort_order Sort order
     */
    function set_sort_order($sort_col, $sort_order = null)
    {
        if ($sort_col != null && ($this->coltypes[$sort_col] || in_array($sort_col, $this->coltypes))) {
            $this->sort_col = $sort_col;
        }
        if ($sort_order != null) {
            $this->sort_order = strtoupper($sort_order) == 'DESC' ? 'DESC' : 'ASC';
        }
    }

    /**
     * Check the given data before saving.
     * If input isn't valid, the message to display can be fetched using get_error()
     *
     * @param array Assoziative array with data to save
     * @param boolean Attempt to fix/complete record automatically
     * @return boolean True if input is valid, False if not.
     */
    public function validate(&$save_data, $autofix = false)
    {
        $rcube = rcube::get_instance();

        // check validity of email addresses
        foreach ($this->get_col_values('email', $save_data, true) as $email) {
            if (strlen($email)) {
                if (!rcube_utils::check_email(rcube_utils::idn_to_ascii($email))) {
                    $error = $rcube->gettext(array('name' => 'emailformaterror', 'vars' => array('email' => $email)));
                    $this->set_error(self::ERROR_VALIDATE, $error);
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Create a new contact record
     *
     * @param array Assoziative array with save data
     *  Keys:   Field name with optional section in the form FIELD:SECTION
     *  Values: Field value. Can be either a string or an array of strings for multiple values
     * @param boolean True to check for duplicates first
     * @return mixed The created record ID on success, False on error
     */
    function insert($save_data, $check=false)
    {
        /* empty for read-only address books */
    }

    /**
     * Create new contact records for every item in the record set
     *
     * @param object rcube_result_set Recordset to insert
     * @param boolean True to check for duplicates first
     * @return array List of created record IDs
     */
    function insertMultiple($recset, $check=false)
    {
        $ids = array();
        if (is_object($recset) && is_a($recset, rcube_result_set)) {
            while ($row = $recset->next()) {
                if ($insert = $this->insert($row, $check))
                    $ids[] = $insert;
            }
        }
        return $ids;
    }

    /**
     * Update a specific contact record
     *
     * @param mixed Record identifier
     * @param array Assoziative array with save data
     *  Keys:   Field name with optional section in the form FIELD:SECTION
     *  Values: Field value. Can be either a string or an array of strings for multiple values
     * @return boolean True on success, False on error
     */
    function update($id, $save_cols)
    {
        /* empty for read-only address books */
    }

    /**
     * Mark one or more contact records as deleted
     *
     * @param array  Record identifiers
     * @param bool   Remove records irreversible (see self::undelete)
     */
    function delete($ids, $force=true)
    {
        /* empty for read-only address books */
    }

    /**
     * Unmark delete flag on contact record(s)
     *
     * @param array  Record identifiers
     */
    function undelete($ids)
    {
        /* empty for read-only address books */
    }

    /**
     * Mark all records in database as deleted
     */
    function delete_all()
    {
        /* empty for read-only address books */
    }

    /**
     * Setter for the current group
     * (empty, has to be re-implemented by extending class)
     */
    function set_group($gid) { }

    /**
     * List all active contact groups of this source
     *
     * @param string  Optional search string to match group name
     * @param int     Matching mode:
     *                0 - partial (*abc*),
     *                1 - strict (=),
     *                2 - prefix (abc*)
     *
     * @return array  Indexed list of contact groups, each a hash array
     */
    function list_groups($search = null, $mode = 0)
    {
        /* empty for address books don't supporting groups */
        return array();
    }

    /**
     * Get group properties such as name and email address(es)
     *
     * @param string Group identifier
     * @return array Group properties as hash array
     */
    function get_group($group_id)
    {
        /* empty for address books don't supporting groups */
        return null;
    }

    /**
     * Create a contact group with the given name
     *
     * @param string The group name
     * @return mixed False on error, array with record props in success
     */
    function create_group($name)
    {
        /* empty for address books don't supporting groups */
        return false;
    }

    /**
     * Delete the given group and all linked group members
     *
     * @param string Group identifier
     * @return boolean True on success, false if no data was changed
     */
    function delete_group($gid)
    {
        /* empty for address books don't supporting groups */
        return false;
    }

    /**
     * Rename a specific contact group
     *
     * @param string Group identifier
     * @param string New name to set for this group
     * @param string New group identifier (if changed, otherwise don't set)
     * @return boolean New name on success, false if no data was changed
     */
    function rename_group($gid, $newname, &$newid)
    {
        /* empty for address books don't supporting groups */
        return false;
    }

    /**
     * Add the given contact records the a certain group
     *
     * @param string       Group identifier
     * @param array|string List of contact identifiers to be added
     *
     * @return int Number of contacts added
     */
    function add_to_group($group_id, $ids)
    {
        /* empty for address books don't supporting groups */
        return 0;
    }

    /**
     * Remove the given contact records from a certain group
     *
     * @param string       Group identifier
     * @param array|string List of contact identifiers to be removed
     *
     * @return int Number of deleted group members
     */
    function remove_from_group($group_id, $ids)
    {
        /* empty for address books don't supporting groups */
        return 0;
    }

    /**
     * Get group assignments of a specific contact record
     *
     * @param mixed Record identifier
     *
     * @return array List of assigned groups as ID=>Name pairs
     * @since 0.5-beta
     */
    function get_record_groups($id)
    {
        /* empty for address books don't supporting groups */
        return array();
    }

    /**
     * Utility function to return all values of a certain data column
     * either as flat list or grouped by subtype
     *
     * @param string Col name
     * @param array  Record data array as used for saving
     * @param boolean True to return one array with all values, False for hash array with values grouped by type
     * @return array List of column values
     */
    public static function get_col_values($col, $data, $flat = false)
    {
        $out = array();
        foreach ((array)$data as $c => $values) {
            if ($c === $col || strpos($c, $col.':') === 0) {
                if ($flat) {
                    $out = array_merge($out, (array)$values);
                }
                else {
                    list(, $type) = explode(':', $c);
                    $out[$type] = array_merge((array)$out[$type], (array)$values);
                }
            }
        }

        // remove duplicates
        if ($flat && !empty($out)) {
            $out = array_unique($out);
        }

        return $out;
    }

    /**
     * Normalize the given string for fulltext search.
     * Currently only optimized for Latin-1 characters; to be extended
     *
     * @param string Input string (UTF-8)
     * @return string Normalized string
     * @deprecated since 0.9-beta
     */
    protected static function normalize_string($str)
    {
        return rcube_utils::normalize_string($str);
    }

    /**
     * Compose a valid display name from the given structured contact data
     *
     * @param array  Hash array with contact data as key-value pairs
     * @param bool   Don't attempt to extract components from the email address
     *
     * @return string Display name
     */
    public static function compose_display_name($contact, $full_email = false)
    {
        $contact = rcube::get_instance()->plugins->exec_hook('contact_displayname', $contact);
        $fn = $contact['name'];

        if (!$fn)  // default display name composition according to vcard standard
            $fn = trim(join(' ', array_filter(array($contact['prefix'], $contact['firstname'], $contact['middlename'], $contact['surname'], $contact['suffix']))));

        // use email address part for name
        $email = self::get_col_values('email', $contact, true);
        $email = $email[0];

        if ($email && (empty($fn) || $fn == $email)) {
            // return full email
            if ($full_email)
                return $email;

            list($emailname) = explode('@', $email);
            if (preg_match('/(.*)[\.\-\_](.*)/', $emailname, $match))
                $fn = trim(ucfirst($match[1]).' '.ucfirst($match[2]));
            else
                $fn = ucfirst($emailname);
        }

        return $fn;
    }

    /**
     * Compose the name to display in the contacts list for the given contact record.
     * This respects the settings parameter how to list conacts.
     *
     * @param array  Hash array with contact data as key-value pairs
     * @return string List name
     */
    public static function compose_list_name($contact)
    {
        static $compose_mode;

        if (!isset($compose_mode))  // cache this
            $compose_mode = rcube::get_instance()->config->get('addressbook_name_listing', 0);

        if ($compose_mode == 3)
            $fn = join(' ', array($contact['surname'] . ',', $contact['firstname'], $contact['middlename']));
        else if ($compose_mode == 2)
            $fn = join(' ', array($contact['surname'], $contact['firstname'], $contact['middlename']));
        else if ($compose_mode == 1)
            $fn = join(' ', array($contact['firstname'], $contact['middlename'], $contact['surname']));
        else
            $fn = !empty($contact['name']) ? $contact['name'] : join(' ', array($contact['prefix'], $contact['firstname'], $contact['middlename'], $contact['surname'], $contact['suffix']));

        $fn = trim($fn, ', ');

        // fallback to display name
        if (empty($fn) && $contact['name'])
            $fn = $contact['name'];

        // fallback to email address
        if (empty($fn) && ($email = self::get_col_values('email', $contact, true)) && !empty($email)) {
            return $email[0];
        }

        return $fn;
    }

    /**
     * Create a unique key for sorting contacts
     */
    public static function compose_contact_key($contact, $sort_col)
    {
        $key = $contact[$sort_col] . ':' . $contact['sourceid'];

        // add email to a key to not skip contacts with the same name (#1488375)
        if (($email = self::get_col_values('email', $contact, true)) && !empty($email)) {
            $key .= ':' . implode(':', (array)$email);
        }

        return $key;
    }

    /**
     * Compare search value with contact data
     *
     * @param string       $colname Data name
     * @param string|array $value   Data value
     * @param string       $search  Search value
     * @param int          $mode    Search mode
     *
     * @return bool Comparision result
     */
    protected function compare_search_value($colname, $value, $search, $mode)
    {
        // The value is a date string, for date we'll
        // use only strict comparison (mode = 1)
        // @TODO: partial search, e.g. match only day and month
        if (in_array($colname, $this->date_cols)) {
            return (($value = rcube_utils::anytodatetime($value))
                && ($search = rcube_utils::anytodatetime($search))
                && $value->format('Ymd') == $search->format('Ymd'));
        }

        // composite field, e.g. address
        foreach ((array)$value as $val) {
            $val = mb_strtolower($val);
            switch ($mode) {
            case 1:
                $got = ($val == $search);
                break;

            case 2:
                $got = ($search == substr($val, 0, strlen($search)));
                break;

            default:
                $got = (strpos($val, $search) !== false);
            }

            if ($got) {
                return true;
            }
        }

        return false;
    }

}

