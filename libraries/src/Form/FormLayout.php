<?php

namespace Joomla\CMS\Form;

use Joomla\CMS\MVC\Model\FormFilesTrait;

/**
 * Class FormLayout
 *
 * @since    __DEPLOY_VERSION__
 */
class FormLayout {

    use FormFilesTrait;

    /**
     * The name of the formlayout.
     *
     * @var    string
     * @since  __DEPLOY_VERSION__
     */
    protected $name;

    /**
     * The formlayout layout options
     *
     * @var    array
     * @since  __DEPLOY_VERSION__
     */
    protected $options = [];

    /**
     * The formlayout XML definition.
     *
     * @var    \SimpleXMLElement
     * @since  __DEPLOY_VERSION__
     */
    protected $xml;

    /**
     * Method to instantiate the form object.
     *
     * @param   string  $name     The name of the form.
     * @param   array   $options  An array of form options.
     *
     * @since   __DEPLOY_VERSION__
     */
    public function __construct($name, array $options = [])
    {
        // Set the name for the form.
        $this->name = $name;

        // Set the options if specified.
        $this->options = $options;
    }

    /**
     * Method to load the form layout from an XML string or object.
     *
     * The replace option works per field.  If a field being loaded already exists in the current
     * form definition then the behavior or load will vary depending upon the replace flag.  If it
     * is set to true, then the existing field will be replaced in its exact location by the new
     * field being loaded.  If it is false, then the new field being loaded will be ignored and the
     * method will move on to the next field to load.
     *
     * @param   string|\SimpleXMLElement   $data     The name of an XML string or object.
     *
     * @return  boolean  True on success, false otherwise.
     *
     * @since   __DEPLOY_VERSION__
     */
    public function load($data)
    {
        // If the data to load isn't already an XML element or string return false.
        if (!($data instanceof \SimpleXMLElement) && !\is_string($data)) {
            return false;
        }

        // Attempt to load the XML if a string.
        if (\is_string($data)) {
            try {
                $this->xml = new \SimpleXMLElement($data);

                return true;
            } catch (\Exception $e) {
                return false;
            }
        }

        $this->xml = $data;

        return true;
    }

    /**
     * Method to load the form layout from an XML file.
     *
     * The reset option works on a group basis. If the XML file references
     * groups that have already been created they will be replaced with the
     * fields in the new XML file unless the $reset parameter has been set
     * to false.
     *
     * @param   string   $file   The filesystem path of an XML file.
     * @param   boolean  $reset  Flag to toggle whether form fields should be replaced if a field
     *                           already exists with the same group/name.
     * @param   string   $xpath  An optional xpath to search for the fields.
     *
     * @return  boolean  True on success, false otherwise.
     *
     * @since   __DEPLOY_VERSION__
     */
    public function loadFile($file, $reset = true, $xpath = null)
    {
        $file = $this->findLayoutFile($file);

        if ($file === false) {
            return false;
        }

        // Attempt to load the XML file.
        $xml = simplexml_load_file($file);

        return $this->load($xml, $reset, $xpath);
    }
}
