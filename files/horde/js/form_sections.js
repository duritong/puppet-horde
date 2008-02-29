/**
 * Horde Form Sections Javascript Class
 *
 * Provides the javascript class for handling tabbed sections in Horde Forms.
 *
 * Copyright 2003-2007 Marko Djukic <marko@oblo.com>
 *
 * See the enclosed file COPYING for license information (LGPL). If you did not
 * receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * $Horde: horde/js/form_sections.js,v 1.1.2.3 2007/01/02 13:55:04 jan Exp $
 *
 * @author  Marko Djukic <marko@oblo.com>
 * @package Horde_Form
 */
function Horde_Form_Sections(instanceName, openSection)
{
    /* Set up this class instance for function calls from the page. */
    this._instanceName = instanceName;

    /* The currently showed section. */
    var _openSection;

    this.toggle = function(sectionId)
    {
        /* Get the currently open section object. */
        openSectionId = this._get();
        if (document.getElementById('_section_' + openSectionId)) {
            document.getElementById('_section_' + openSectionId).style.display = 'none';
        }
        document.getElementById('_tab_' + openSectionId).className = null;

        /* Get the newly opened section object. */
        if (document.getElementById('_section_' + sectionId)) {
            document.getElementById('_section_' + sectionId).style.display = 'block';
        }
        document.getElementById('_tab_' + sectionId).className = 'activeTab';

        /* Store the newly opened section. */
        this._set(sectionId);
    }

    this._get = function()
    {
        return this._openSection;
    }

    this._set = function(sectionId)
    {
        var form = eval('document.' + this._instanceName);
        if (form != null &&
            typeof form.__formOpenSection != 'undefined') {
            form.__formOpenSection.value = escape(sectionId);
        }
        this._openSection = sectionId;
    }

    this._set(openSection);
}
