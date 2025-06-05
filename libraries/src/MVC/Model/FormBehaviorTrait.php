<?php

/**
 * Joomla! Content Management System
 *
 * @copyright  (C) 2019 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\CMS\MVC\Model;

use Joomla\CMS\Event\Model;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\CurrentUserInterface;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Event\DispatcherAwareInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Utilities\ArrayHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Trait which supports form behavior.
 *
 * @since  4.0.0
 */
trait FormBehaviorTrait
{
    use FormFilesTrait;
    use FormadminTrait;

    /**
     * Array of form objects.
     *
     * @var    Form[]
     * @since  4.0.0
     */
    protected $_forms = [];

    private $pathAdded = false;

    /**
     * Method to get a form object.
     *
     * @param   string   $name     The name of the form.
     * @param   string   $source   The form source. Can be XML string if file flag is set to false.
     * @param   array    $options  Optional array of options for the form creation.
     * @param   boolean  $clear    Optional argument to force load a new form.
     * @param   string   $xpath    An optional xpath to search for the fields.
     *
     * @return  Form
     *
     * @see     Form
     * @since   4.0.0
     * @throws  \Exception
     */
    protected function loadForm($name, $source = null, $options = [], $clear = false, $xpath = null)
    {
        if (substr($source, 0, 1) === '<') {
            return $this->loadFormLegacy($name, $source, $options, $clear, $xpath);
        }

        $this->addFormPaths();

        // Find form.xml
        $file = $this->findFormFile($source);

        if ($file === false) {
            throw new \RuntimeException('Could not find form file');
        }

        // Find form.layout.xml
        $layout = $this->findLayoutFile($file);

        if ($layout === false) {
            return $this->loadFormLegacy($name, $source, $options, $clear, $xpath);
        }

        try {
            $formFactory = $this->getFormFactory();
        } catch (\UnexpectedValueException $e) {
            $formFactory = Factory::getContainer()->get(FormFactoryInterface::class);
        }

        $formLayout = $formFactory->createLayout($source);
        $form = $formFactory->createForm($name, $options);

        $form->setFormType('next');

        // We have a form.layout.xml file, check if we have an entry for it in the formadmin table
        if ($this->hasFormDefinition($source)) {
            $layoutDefinition = $this->getLayoutDefinion($source);
            $formLayout->load($layoutDefinition);

            $formDefinition = $this->getFormDefinion($source);
            $form->load($formDefinition);
        } else {
            $formLayout->loadFile($layout);
            $form->loadFile($source);
        }

        if ($form instanceof CurrentUserInterface && method_exists($this, 'getCurrentUser')) {
            $form->setCurrentUser($this->getCurrentUser());
        }

        if (isset($options['load_data']) && $options['load_data']) {
            // Get the data for the form.
            $data = $this->loadFormData();
        } else {
            $data = [];
        }

        if (\is_array($data)) {
            $data = (object) $data;
        }

        $jform = Factory::getApplication()->getInput()->get('jform', [], 'array');

        if (empty($data) || !isset($data->catid)) {
            $data->catid = $jform['catid'];
        }

        // We might need a special handling for categories

        // Load CF
        $parts = FieldsHelper::extract($name, $form);

        if ($parts) {
            FieldsHelper::prepareForm($parts[0] . '.' . $parts[1], $form, $data);
        }

        // Allow for additional modification of the form, and events to be triggered.
        // We pass the data because plugins may require it.
        $this->preprocessForm($form, $data);

        // Load the data into the form after the plugins have operated.
        $form->bind($data);

        $form->setLayout($formLayout);

        // Store the form for later.
        // $hash = $this->createHash();
        // $this->_forms[$hash] = $form;

        return $form;
    }

    /**
     * Method to get a form object.
     *
     * @param   string   $name     The name of the form.
     * @param   string   $source   The form source. Can be XML string if file flag is set to false.
     * @param   array    $options  Optional array of options for the form creation.
     * @param   boolean  $clear    Optional argument to force load a new form.
     * @param   string   $xpath    An optional xpath to search for the fields.
     *
     * @return  Form
     *
     * @see     Form
     * @since   4.0.0
     * @throws  \Exception
     */
    protected function loadFormLegacy($name, $source = null, $options = [], $clear = false, $xpath = null)
    {
        // Handle the optional arguments.
        $options['control'] = ArrayHelper::getValue((array) $options, 'control', false);

        // Create a signature hash. But make sure, that loading the data does not create a new instance
        $sigoptions = $options;

        if (isset($sigoptions['load_data'])) {
            unset($sigoptions['load_data']);
        }

        $hash = md5($source . serialize($sigoptions));

        // Check if we can use a previously loaded form.
        if (!$clear && isset($this->_forms[$hash])) {
            return $this->_forms[$hash];
        }

        $this->addFormPaths();

        // Get the form.
        try {
            $formFactory = $this->getFormFactory();
        } catch (\UnexpectedValueException $e) {
            $formFactory = Factory::getContainer()->get(FormFactoryInterface::class);
        }

        $form = $formFactory->createForm($name, $options);

        if ($form instanceof CurrentUserInterface && method_exists($this, 'getCurrentUser')) {
            $form->setCurrentUser($this->getCurrentUser());
        }

        // Load the data.
        if (substr($source, 0, 1) === '<') {
            if ($form->load($source, false, $xpath) == false) {
                throw new \RuntimeException('Form::loadForm could not load form');
            }
        } else {
            if ($form->loadFile($source, false, $xpath) == false) {
                throw new \RuntimeException('Form::loadForm could not load file');
            }
        }

        if (isset($options['load_data']) && $options['load_data']) {
            // Get the data for the form.
            $data = $this->loadFormData();
        } else {
            $data = [];
        }

        // Allow for additional modification of the form, and events to be triggered.
        // We pass the data because plugins may require it.
        $this->preprocessForm($form, $data);

        // Load the data into the form after the plugins have operated.
        $form->bind($data);

        // Store the form for later.
        $this->_forms[$hash] = $form;

        return $form;
    }

    protected function addFormPaths()
    {
        if ($this->pathAdded) {
            return;
        }

        // Try to figure out what component we are using
        $extension = '';

        if (!empty($this->typeAlias)) {
            [$extension] = explode('.', $this->typeAlias);
        }

        if (empty($extension) && !empty($this->option)) {
            $extension = $this->option;
        }

        $basePath = JPATH_BASE . '/compoents/' . $extension;

        Form::addFormPath($basePath . '/forms');
        Form::addFormPath($basePath . '/models/forms');
        Form::addFieldPath($basePath . '/models/fields');
        Form::addFormPath($basePath . '/model/form');
        Form::addFieldPath($basePath . '/model/field');

        $this->pathAdded = true;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  array  The default data is an empty array.
     *
     * @since   4.0.0
     */
    protected function loadFormData()
    {
        return [];
    }

    /**
     * Method to allow derived classes to preprocess the data.
     *
     * @param   string  $context  The context identifier.
     * @param   mixed   &$data    The data to be processed. It gets altered directly.
     * @param   string  $group    The name of the plugin group to import (defaults to "content").
     *
     * @return  void
     *
     * @since   4.0.0
     */
    protected function preprocessData($context, &$data, $group = 'content')
    {
        if ($this instanceof DispatcherAwareInterface) {
            $dispatcher = $this->getDispatcher();
        } else {
            $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
        }

        // Get the dispatcher and load the users plugins.
        PluginHelper::importPlugin($group, null, true, $dispatcher);

        // Trigger the data preparation event.
        $data = $dispatcher->dispatch(
            'onContentPrepareData',
            new Model\PrepareDataEvent('onContentPrepareData', [
                'context' => $context,
                'data'    => &$data, // @todo: Remove reference in Joomla 6, see PrepareDataEvent::__constructor()
                'subject' => new \stdClass(),
            ])
        )->getArgument('data', $data);
    }

    /**
     * Method to allow derived classes to preprocess the form.
     *
     * @param   Form    $form   A Form object.
     * @param   mixed   $data   The data expected for the form.
     * @param   string  $group  The name of the plugin group to import (defaults to "content").
     *
     * @return  void
     *
     * @see     FormField
     * @since   4.0.0
     * @throws  \Exception if there is an error in the form event.
     */
    protected function preprocessForm(Form $form, $data, $group = 'content')
    {
        if ($this instanceof DispatcherAwareInterface) {
            $dispatcher = $this->getDispatcher();
        } else {
            $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
        }

        // Import the appropriate plugin group.
        PluginHelper::importPlugin($group, null, true, $dispatcher);

        // Trigger the form preparation event.
        $dispatcher->dispatch(
            'onContentPrepareForm',
            new Model\PrepareFormEvent('onContentPrepareForm', ['subject' => $form, 'data' => $data])
        );
    }

    /**
     * Get the FormFactoryInterface.
     *
     * @return  FormFactoryInterface
     *
     * @since   4.0.0
     * @throws  \UnexpectedValueException May be thrown if the FormFactory has not been set.
     */
    abstract public function getFormFactory(): FormFactoryInterface;
}
