<?php
use Luracast\Restler\RestException;

/**
 * API class used for fetching data for any object.
 *
 */
class Mintapelda extends Thirdparties
{
	/**
	 * List objects
	 *
	 * Get a list of objects
	 *
	 * @param   string  $object   Name of the Object we want to receive the list of
	 * @param   string  $sortfield  Sort field
	 * @param   string  $sortorder  Sort order
	 * @param   int     $limit      Limit for list
	 * @param   int     $page       Page number
	 * @param   string  $sqlfilters Other criteria to filter answers separated by a comma. Syntax example "((t.nom:like:'TheCompany%') or (t.name_alias:like:'TheCompany%')) and (t.datec:<:'20160101')"
	 * @return  array               Array of objects
	 */
	public function getObjects($object, $sortfield = "t.rowid", $sortorder = 'ASC', $limit = 50, $page = 0, $sqlfilters = '')
	{
		$obj_ret = array();

		// Check access to Object
		if (!DolibarrApiAccess::$user->hasRight($object, 'lire')) {
			throw new RestException(401);
		}

		$sql = "SELECT t.rowid, ref, label";
		$sql .= " FROM ".MAIN_DB_PREFIX."$object as t";
		$sql .= ", ".MAIN_DB_PREFIX."c_stcomm as st";
		$sql .= " WHERE t.entity IN (".getEntity(''.$object.'').")";
		$sql .= " AND t.fk_stcomm = st.id";

		// Add sql filters
		if ($sqlfilters) {
			$errormessage = '';
			if (!DolibarrApi::_checkFilters($sqlfilters, $errormessage)) {
				throw new RestException(503, 'Error when validating parameter sqlfilters -> '.$errormessage);
			}
			$regexstring = '\(([^:\'\(\)]+:[^:\'\(\)]+:[^\(\)]+)\)';
			$sql .= " AND (".preg_replace_callback('/'.$regexstring.'/', 'DolibarrApi::_forge_criteria_callback', $sqlfilters).")";
		}

		$sql .= $this->db->order($sortfield, $sortorder);

		if ($limit) {
			if ($page < 0) {
				$page = 0;
			}
			$offset = $limit * $page;

			$sql .= $this->db->plimit($limit + 1, $offset);
		}

		// TODO: When executing query, result should be cached on the server. So next time a call is made with the same parameters, server can
		// return the cached result without even having to access the database. Keep in mind that this cached data should be expired if a CREATE, UPDATE or
		// DELETE operation was made on the affected table, to avoid returning obsolete data.
		$result = $this->db->query($sql);

		if ($result) {
			$num = $this->db->num_rows($result);
			$min = min($num, ($limit <= 0 ? $num : $limit));
			$i = 0;
			while ($i < $min) {
				$obj = $this->db->fetch_object($result);
				try {
					$obj_static = new $object($this->db);
					if ($obj_static->fetch($obj->rowid)) {
						if (isModEnabled('mailing')) {
							$obj_static->getNoEmail();
						}
						$obj_ret[] = $this->_cleanObjectDatas($obj_static);
					}
					$i++;
				}
				catch (Exception $exception) {
					throw new RestException(500, "Failed to retrieve $object list : ".$exception->getMessage());
				}
			}
		} else {
			throw new RestException(503, "Error when retrieve $object : ".$this->db->lasterror());
		}
		if (!count($obj_ret)) {
			throw new RestException(404, "$object not found");
		}
		return $obj_ret;
	}
}
