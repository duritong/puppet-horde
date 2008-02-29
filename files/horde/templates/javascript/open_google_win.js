function open_google_win(args)
{
    var val, url = '';

    for (var i = 0; i < document.google.area.length; i++) {
        if (document.google.area[i].checked) {
            val = document.google.area[i].value;
            break;
        }
    }

    switch (val) {
	case 'web':
    	url = 'http://www.google.com/search?';
	    break;

	case 'images':
	    url = 'http://images.google.com/images?';
        break;

	case 'groups':
	    url = 'http://groups.google.com/groups?';
        break;

	case 'directory':
	    url = 'http://www.google.com/search?cat=gwd%2FTop&';
        break;

	case 'news':
	    url = 'http://news.google.com/news?';
        break;

	default:
    	url = 'http://www.google.com/search?';
    }
    url += 'q=';
    if (typeof encodeURIComponent == 'undefined') {
        url += escape(document.google.q.value);
    } else {
        url += encodeURIComponent(document.google.q.value);
    }

    var name = 'Google';
    var param = 'toolbar=yes,location=yes,status=yes,scrollbars=yes,resizable=yes,width=800,height=600,left=0,top=0';

    eval('name = window.open(url, name, param)');
    if (!eval('name.opener')) {
        eval('name.opener = self');
    }
}
