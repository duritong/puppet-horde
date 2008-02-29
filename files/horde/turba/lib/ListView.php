<?php
/**
 * The Turba_ListView:: class provides an interface for objects that
 * visualize Turba_lists.
 *
 * $Horde: turba/lib/ListView.php,v 1.17.10.14 2007/03/29 22:45:00 jan Exp $
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Jon Parise <jon@csh.rit.edu>
 * @package Turba
 */
class Turba_ListView {

    /**
     * The Turba_List object that we are visualizing.
     *
     * @var Turba_List
     */
    var $list;

    /**
     * The header template.
     *
     * @var string
     */
    var $templateHeader;

    /**
     * The footer template.
     *
     * @var string
     */
    var $templateFooter;

    /**
     * The template used to display each row of the list.
     *
     * @var string
     */
    var $templateRow;

    /**
     * Show/hide "mark" column in the display.
     *
     * @var boolean
     */
    var $showMark = false;

    /**
     * Show/hide "edit" column in the display.
     *
     * @var boolean
     */
    var $showEdit = false;

    /**
     * Show/hide "vcard" column in the display.
     *
     * @var boolean
     */
    var $showVcard = false;

    /**
     * Show/hide "group" column in the display.
     *
     * @var boolean
     */
    var $showGroup = false;

    /**
     * Show/hide "sort" column in the display.
     *
     * @var boolean
     */
    var $showSort = false;

    /**
     * Type of list.
     *
     * @var string
     */
    var $type;

    /**
     * Constructs a new Turba_ListView object.
     *
     * @param Turba_List $list  List of contacts to display.
     */
    function Turba_ListView(&$list, $columns = null)
    {
        if (is_null($columns)) {
            $columns = array('Mark' => true,
                             'Edit' => true,
                             'Vcard' => true,
                             'Group' => true,
                             'Sort' => true);
        }

        $this->list = &$list;
        $this->setColumns($columns);
        $this->setTemplates(array('Header' => TURBA_TEMPLATES . '/browse/column_headers.inc',
                                  'Footer' => TURBA_TEMPLATES . '/browse/column_footers.inc',
                                  'Row' => TURBA_TEMPLATES . '/browse/row.inc'));
    }

    /**
     * Control which columns are shown by the display templates.
     *
     * @param array $columns
     */
    function setColumns($columns)
    {
        foreach ($columns as $column => $show) {
            $key = 'show' . $column;
            $this->$key = (bool)$show;
        }
    }

    /**
     * Set the header, footer, and row templates for display.
     *
     * @param array $templates
     */
    function setTemplates($templates)
    {
        foreach ($templates as $template => $file) {
            $key = 'template' . $template;
            $this->$key = $file;
        }
    }

    function setType($type)
    {
        $this->type = $type;
    }

    function getType()
    {
        return $this->type;
    }

    /**
     * Returns the number of Turba_Objects that are in the list. Use this to
     * hide internal implementation details from client objects.
     *
     * @return integer  The number of objects in the list.
     */
    function count()
    {
        return $this->list->count();
    }

    function display()
    {
        global $prefs, $default_source, $copymove_source_options;

        $driver = &Turba_Driver::singleton($default_source);
        $hasDelete = false;
        $hasEdit = false;
        $hasExport = false;
        if (!is_a($driver, 'PEAR_Error')) {
            if ($driver->hasPermission(PERMS_DELETE)) {
                $hasDelete = true;
            }
            if ($driver->hasPermission(PERMS_EDIT)) {
                $hasEdit = true;
            }
            if ($GLOBALS['conf']['menu']['import_export']
                && !empty($GLOBALS['cfgSources'][$default_source]['export'])) {
                $hasExport = true;
            }
        }
        list($addToList, $addToListSources) = $this->getAddSources();
        require TURBA_TEMPLATES . '/browse/actions.inc';

        $viewurl = Util::addParameter('browse.php', array(
            'sortby' => Util::getFormData('sortby'),
            'sortdir' => Util::getFormData('sortdir'),
            'key' => Util::getFormData('key'),
            ));

        if ($this->type == 'search') {
            $page = Util::getFormData('page', 0);
            $numitem = $this->count();
            $maxpage = $prefs->getValue('maxpage');
            $perpage = $prefs->getValue('perpage');

            $min = $page * $perpage;
            while ($min > $numitem) {
                $page--;
                $min = $page * $perpage;
            }

            $max = $min + $perpage;
            $start = ($page * $perpage) + 1;
            $end = min($numitem, $start + $perpage - 1);

            $numDisplayed = $this->displayPage($min, $max);

            require_once 'Horde/Variables.php';
            $vars = &Variables::getDefaultVariables();
            $crit = array();
            if ($_SESSION['turba']['search_mode'] == 'advanced') {
                $map = $driver->getCriteria();
                foreach ($map as $key => $value) {
                    if ($key != '__key') {
                        $val = Util::getFormData($key);
                        if (!empty($val)) {
                            $crit[$key] = $val;
                        }
                    }
                }
            }
            $params = array_merge($crit, array(
                'source' => $default_source,
                'criteria' => Util::getFormData('criteria'),
                'val' => Util::getFormData('val')
            ));
            $viewurl = Util::addParameter('search.php', $params);

            require_once 'Horde/UI/Pager.php';
            $pager = &new Horde_UI_Pager('page', $vars,
                                         array('num' => $numDisplayed,
                                               'url' => $viewurl,
                                               'page_limit' => $maxpage,
                                               'perpage' => $perpage));

            $footer = 'footer.inc';
        } else {
            $page = Util::getFormData('page', '*');
            if ($this->count() > $prefs->getValue('perpage') &&
                (Util::getFormData('show') != 'lists' ||
                !Util::getFormData('key'))) {
                $page = Util::getFormData('page', 'A');
            }

            if (empty($page) || !preg_match('/^[A-Za-z*]$/', $page)) {
                $page = '*';
            }

            $this->displayAlpha($page);
            $footer = 'footerAlpha.inc';
        }

        require TURBA_TEMPLATES . '/browse/actions.inc';
        require TURBA_TEMPLATES . '/browse/' . $footer;
    }

    /**
     * Renders the list contents into an HTML view.
     *
     * @return integer $count The number of objects in the list.
     */
    function displayPage($min = null, $max = null)
    {
        if (is_null($min)) {
            $min = 0;
        }
        if (is_null($max)) {
            $max = $this->list->count();
        }

        $sortby = $GLOBALS['prefs']->getValue('sortby');
        $sortdir = (int)$GLOBALS['prefs']->getValue('sortdir');
        $width = floor(90 / (count($GLOBALS['columns']) + 1));

        include $this->templateHeader;

        $i = 0;
        $this->list->reset();
        while ($ob = $this->list->next()) {
            if ($i++ < $min || $i > $max) {
                continue;
            }

            include $this->templateRow;
        }

        include $this->templateFooter;
        return $i;
    }

    /**
     * Renders the list contents that match $alpha into and HTML view.
     *
     * @param $alpha    The letter to display.
     */
    function displayAlpha($alpha)
    {
        $sortby = $GLOBALS['prefs']->getValue('sortby');
        $sortdir = (int)$GLOBALS['prefs']->getValue('sortdir');
        $width = floor(90 / (count($GLOBALS['columns']) + 1));

        include $this->templateHeader;

        $alpha = String::lower($alpha);

        $i = 0;
        $this->list->reset();
        while ($ob = $this->list->next()) {
            $name = Turba::formatName($ob);

            if ($alpha != '*' && String::lower($name{0}) != $alpha) {
                continue;
            }

            include $this->templateRow;
            $i++;
        }

        include $this->templateFooter;
        return $i;
    }

    function getAddSources()
    {
        global $addSources;

        // Create list of lists for Add to.
        $addToList = array();
        $addToListSources = array();
        foreach ($addSources as $src => $srcConfig) {
            if (!empty($srcConfig['map']['__type'])) {
                $addToListSources[] = array('key' => '',
                                            'name' => '&nbsp;&nbsp;' . htmlspecialchars($srcConfig['title']),
                                            'source' => htmlspecialchars($src));

                $srcDriver = &Turba_Driver::singleton($src);
                $listList = $srcDriver->search(array('__type' => 'Group'),
                                               'name', 'AND', 0, array('name'));
                if (is_a($listList, 'PEAR_Error')) {
                    $GLOBALS['notification']->push($listList, 'horde.error');
                } else {
                    $listList->reset();
                    $currentList = Util::getFormData('key');
                    while ($listObject = $listList->next()) {
                        if ($listObject->getValue('__key') != $currentList) {
                            $addToList[] = array('name' => htmlspecialchars($listObject->getValue('name')),
                                                 'source' => htmlspecialchars($src),
                                                 'key' => htmlspecialchars($listObject->getValue('__key')));
                        }
                    }
                }
            }
        }
        if ($addToListSources) {
            if ($addToList) {
                array_unshift($addToList, '-----');
            }
            $addToList = array_merge(array(_("Create a new Contact List in:")), $addToListSources, $addToList);
            $addToListSources = null;
        }

        return array($addToList, $addToListSources);
    }

}
