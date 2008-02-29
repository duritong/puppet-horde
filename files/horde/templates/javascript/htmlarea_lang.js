HTMLArea.I18N = {

	// the following should be the filename without .js extension
	// it will be used for automatically load plugin language.
	lang: "en",

	tooltips: {
		bold:           "<?php echo addslashes(_("Bold")) ?>",
		italic:         "<?php echo addslashes(_("Italic")) ?>",
		underline:      "<?php echo addslashes(_("Underline")) ?>",
		strikethrough:  "<?php echo addslashes(_("Strikethrough")) ?>",
		subscript:      "<?php echo addslashes(_("Subscript")) ?>",
		superscript:    "<?php echo addslashes(_("Superscript")) ?>",
		justifyleft:    "<?php echo addslashes(_("Justify Left")) ?>",
		justifycenter:  "<?php echo addslashes(_("Justify Center")) ?>",
		justifyright:   "<?php echo addslashes(_("Justify Right")) ?>",
		justifyfull:    "<?php echo addslashes(_("Justify Full")) ?>",
		orderedlist:    "<?php echo addslashes(_("Ordered List")) ?>",
		unorderedlist:  "<?php echo addslashes(_("Bulleted List")) ?>",
		outdent:        "<?php echo addslashes(_("Decrease Indent")) ?>",
		indent:         "<?php echo addslashes(_("Increase Indent")) ?>",
		forecolor:      "<?php echo addslashes(_("Font Color")) ?>",
		hilitecolor:    "<?php echo addslashes(_("Background Color")) ?>",
		inserthorizontalrule: "<?php echo addslashes(_("Horizontal Rule")) ?>",
		createlink:     "<?php echo addslashes(_("Insert Web Link")) ?>",
		insertimage:    "<?php echo addslashes(_("Insert/Modify Image")) ?>",
		inserttable:    "<?php echo addslashes(_("Insert Table")) ?>",
		htmlmode:       "<?php echo addslashes(_("Toggle HTML Source")) ?>",
		popupeditor:    "<?php echo addslashes(_("Enlarge Editor")) ?>",
		about:          "<?php echo addslashes(_("About this editor")) ?>",
		showhelp:       "<?php echo addslashes(_("Help using editor")) ?>",
		textindicator:  "<?php echo addslashes(_("Current style")) ?>",
		undo:           "<?php echo addslashes(_("Undoes your last action")) ?>",
		redo:           "<?php echo addslashes(_("Redoes your last action")) ?>",
		cut:            "<?php echo addslashes(_("Cut selection")) ?>",
		copy:           "<?php echo addslashes(_("Copy selection")) ?>",
		paste:          "<?php echo addslashes(_("Paste from clipboard")) ?>",
		lefttoright:    "<?php echo addslashes(_("Direction left to right")) ?>",
		righttoleft:    "<?php echo addslashes(_("Direction right to left")) ?>",
		removeformat:   "<?php echo addslashes(_("Remove formatting")) ?>",
		print:          "<?php echo addslashes(_("Print document")) ?>",
		killword:       "<?php echo addslashes(_("Clear MSOffice tags")) ?>"
	},

	buttons: {
		"ok":           "<?php echo addslashes(_("OK")) ?>",
		"cancel":       "<?php echo addslashes(_("Cancel")) ?>"
	},

	msg: {
		"Path":         "<?php echo addslashes(_("Path")) ?>",
		"TEXT_MODE":    "<?php echo addslashes(_("You are in TEXT MODE.  Use the [<>] button to switch back to WYSIWYG.")) ?>",

		"IE-sucks-full-screen" :
		// translate here
		"<?php echo addslashes(_("The full screen mode is known to cause problems with Internet Explorer, due to browser bugs that we weren't able to workaround.  You might experience garbage display, lack of editor functions and/or random browser crashes.  If your system is Windows 9x it's very likely that you'll get a 'General Protection Fault' and need to reboot.\\n\\nYou have been warned.  Please press OK if you still want to try the full screen editor.")) ?>",

		"Moz-Clipboard" :
		"<?php echo addslashes(_("Unprivileged scripts cannot access Cut/Copy/Paste programatically for security reasons.  Click OK to see a technical note at mozilla.org which shows you how to allow a script to access the clipboard.")) ?>"
	},

	dialogs: {
		// Common
		"OK"                                                : "<?php echo addslashes(_("OK")) ?>",
		"Cancel"                                            : "<?php echo addslashes(_("Cancel")) ?>",

		"Alignment:"                                        : "<?php echo addslashes(_("Alignment:")) ?>",
		"Not set"                                           : "<?php echo addslashes(_("Not set")) ?>",
		"Left"                                              : "<?php echo addslashes(_("Left")) ?>",
		"Right"                                             : "<?php echo addslashes(_("Right")) ?>",
		"Texttop"                                           : "<?php echo addslashes(_("Texttop")) ?>",
		"Absmiddle"                                         : "<?php echo addslashes(_("Absmiddle")) ?>",
		"Baseline"                                          : "<?php echo addslashes(_("Baseline")) ?>",
		"Absbottom"                                         : "<?php echo addslashes(_("Absbottom")) ?>",
		"Bottom"                                            : "<?php echo addslashes(_("Bottom")) ?>",
		"Middle"                                            : "<?php echo addslashes(_("Middle")) ?>",
		"Top"                                               : "<?php echo addslashes(_("Top")) ?>",

		"Layout"                                            : "<?php echo addslashes(_("Layout")) ?>",
		"Spacing"                                           : "<?php echo addslashes(_("Spacing")) ?>",
		"Horizontal:"                                       : "<?php echo addslashes(_("Horizontal:")) ?>",
		"Horizontal padding"                                : "<?php echo addslashes(_("Horizontal padding")) ?>",
		"Vertical:"                                         : "<?php echo addslashes(_("Vertical:")) ?>",
		"Vertical padding"                                  : "<?php echo addslashes(_("Vertical padding")) ?>",
		"Border thickness:"                                 : "<?php echo addslashes(_("Border thickness:")) ?>",
		"Leave empty for no border"                         : "<?php echo addslashes(_("Leave empty for no border")) ?>",

		// Insert Link
		"Insert/Modify Link"                                : "<?php echo addslashes(_("Insert/Modify Link")) ?>",
		"None (use implicit)"                               : "<?php echo addslashes(_("None (use implicit)")) ?>",
		"New window (_blank)"                               : "<?php echo addslashes(_("New window (_blank)")) ?>",
		"Same frame (_self)"                                : "<?php echo addslashes(_("Same frame (_self)")) ?>",
		"Top frame (_top)"                                  : "<?php echo addslashes(_("Top frame (_top)")) ?>",
		"Other"                                             : "<?php echo addslashes(_("Other")) ?>",
		"Target:"                                           : "<?php echo addslashes(_("Target:")) ?>",
		"Title (tooltip):"                                  : "<?php echo addslashes(_("Title (tooltip):")) ?>",
		"URL:"                                              : "<?php echo addslashes(_("URL:")) ?>",
		"You must enter the URL where this link points to"  : "<?php echo addslashes(_("You must enter the URL where this link points to")) ?>",
		// Insert Table
		"Insert Table"                                      : "<?php echo addslashes(_("Insert Table")) ?>",
		"Rows:"                                             : "<?php echo addslashes(_("Rows:")) ?>",
		"Number of rows"                                    : "<?php echo addslashes(_("Number of rows")) ?>",
		"Cols:"                                             : "<?php echo addslashes(_("Cols:")) ?>",
		"Number of columns"                                 : "<?php echo addslashes(_("Number of columns")) ?>",
		"Width:"                                            : "<?php echo addslashes(_("Width:")) ?>",
		"Width of the table"                                : "<?php echo addslashes(_("Width of the table")) ?>",
		"Percent"                                           : "<?php echo addslashes(_("Percent")) ?>",
		"Pixels"                                            : "<?php echo addslashes(_("Pixels")) ?>",
		"Em"                                                : "<?php echo addslashes(_("Em")) ?>",
		"Width unit"                                        : "<?php echo addslashes(_("Width unit")) ?>",
		"Positioning of this table"                         : "<?php echo addslashes(_("Positioning of this table")) ?>",
		"Cell spacing:"                                     : "<?php echo addslashes(_("Cell spacing:")) ?>",
		"Space between adjacent cells"                      : "<?php echo addslashes(_("Space between adjacent cells")) ?>",
		"Cell padding:"                                     : "<?php echo addslashes(_("Cell padding:")) ?>",
		"Space between content and border in cell"          : "<?php echo addslashes(_("Space between content and border in cell")) ?>",
		// Insert Image
		"Insert Image"                                      : "<?php echo addslashes(_("Insert Image")) ?>",
		"Image URL:"                                        : "<?php echo addslashes(_("Image URL:")) ?>",
		"Enter the image URL here"                          : "<?php echo addslashes(_("Enter the image URL here")) ?>",
		"Preview"                                           : "<?php echo addslashes(_("Preview")) ?>",
		"Preview the image in a new window"                 : "<?php echo addslashes(_("Preview the image in a new window")) ?>",
		"Alternate text:"                                   : "<?php echo addslashes(_("Alternate text:")) ?>",
		"For browsers that don't support images"            : "<?php echo addslashes(_("For browsers that don't support images")) ?>",
		"Positioning of this image"                         : "<?php echo addslashes(_("Positioning of this image")) ?>",
		"Image Preview:"                                    : "<?php echo addslashes(_("Image Preview:")) ?>"
	}
};
