// Ansel Image Plugin for HTMLArea-3.0
// Plugin that allows the insertion of images from the
// Horde framework module Ansel into a text
//
// Module reuses HTMLArea code
//
// Implementation: Roel Gloudemans <roel@gloudemans.info>
//
// (c) 2004.
// Distributed under the same terms as HTMLArea itself.
// This notice MUST stay intact for use (see license.txt).
//
// $Id: ansel-image.js,v 1.1 2004/09/08 16:06:13 lancer Exp $


function AnselImage(editor)
{
	this.editor = editor;
	var i18n = AnselImage.I18N;
};


AnselImage._pluginInfo = {
	name          : "AnselImage",
	version       : "1.0",
	developer     : "Roel Gloudemans",
	developer_url : "http://www.gloudemans.info/",
        sponsor       : "Horde Project",
        sponsor_url   : "http://www.horde.org/",
	license       : "htmlArea"
};


// Called when the user clicks on "InsertImage" button.  If an image is already
// there, it will just modify it's properties.
// This function overrides the native HTMLArea function
HTMLArea.prototype._insertImage = function(image) {
	var editor = this;	// for nested functions
	var outparam = null;

	if (typeof image == "undefined") {
		image = this.getParentElement();
		if (image && !/^img$/i.test(image.tagName))
			image = null;
	}
	if (image) outparam = {
		f_base   : editor.config.baseURL,
		f_url    : HTMLArea.is_ie ? editor.stripBaseURL(image.src) : image.getAttribute("src"),
		f_alt    : image.alt,
		f_border : image.border,
		f_align  : image.align,
		f_vert   : image.vspace,
		f_horiz  : image.hspace
	};

    var manager = _editor_url + 'plugins/AnselImage/insert_image.php';

	Dialog(manager, function(param) {
//	this._popupDialog(manager, function(param) {
		if (!param) {	// user must have pressed Cancel
			return false;
		}
		var img = image;
		if (!img) {
			var sel = editor._getSelection();
			var range = editor._createRange(sel);
			editor._doc.execCommand("insertimage", false, param.f_url);
			if (HTMLArea.is_ie) {
				img = range.parentElement();
				// wonder if this works...
				if (img.tagName.toLowerCase() != "img") {
					img = img.previousSibling;
				}
			} else {
				img = range.startContainer.previousSibling;
			}
		} else {
			img.src = param.f_url;
		}

		for (var field in param) {
			var value = param[field];
			switch (field) {
			    case "f_alt"    : img.alt	 = value; break;
			    case "f_border" : img.border = parseInt(value || "0"); break;
			    case "f_align"  : img.align	 = value; break;
			    case "f_vert"   : img.vspace = parseInt(value || "0"); break;
			    case "f_horiz"  : img.hspace = parseInt(value || "0"); break;
			}
		}
	}, outparam);
};
