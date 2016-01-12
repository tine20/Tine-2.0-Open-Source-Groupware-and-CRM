/*!
 * Expresso Lite
 * Main layout object code.
 *
 * @package   Lite
 * @license   http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author    Charles Wust <charles.wust@serpro.gov.br>
 * @author    Rodrigo Dias <rodrigo.dias@serpro.gov.br>
 * @copyright Copyright (c) 2014-2015 Serpro (http://www.serpro.gov.br)
 */

define(['jquery',
    'common-js/App',
    'common-js/UrlStack',
    'common-js/ContextMenu'
],
function($, App, UrlStack, ContextMenu) {
App.LoadCss('common-js/Layout.css');
return function(options) {
    var userOpts = $.extend({
        userMail: '',   // string with user email, for displaying purposes
        $menu: null,    // jQuery object with the DIV for the left menu
        $middle: null,
        $right: null,
        showMenuTime: 250, // animation time, in ms
        hideMenuTime: 200,
        keepAliveTime: 10 * 60 * 1000 // 10 minutes, Tine default
    }, options);

    var THIS               = this;
    var contextMenu        = null; // ContextMenu object
    var onKeepAliveCB      = null; // user callbacks
    var onHideRightPanelCB = null;
    var onSearchCB         = null;

    THIS.load = function() {
        var defer = $.Deferred();
        App.LoadTemplate('../common-js/Layout.html').done(function() {
            $('#Layout_userMail').text(userOpts.userMail);
            userOpts.$menu.appendTo('#Layout_leftContent'); // detach from page, attach to DIV
            userOpts.$middle.appendTo('#Layout_middleContent');
            userOpts.$right.appendTo('#Layout_rightContent');
            _SetEvents();
            contextMenu = new ContextMenu({ $btn:$('#Layout_context') });
            THIS.setContextMenuVisible(false); // initially hidden
            THIS.setRightPanelVisible(false);
            _SetCurrentModuleAsBold();
            defer.resolve();
        });
        return defer.promise();
    };

    THIS.setRightPanelVisible = function(isVisible) {
        var noChange = (isVisible && THIS.isRightPanelVisible()) ||
            (!isVisible && !THIS.isRightPanelVisible());
        if (noChange) return; // do nothing

        $('#Layout_center').toggleClass('wide', isVisible);
        $('#Layout_left').toggleClass('away', isVisible).scrollTop(0);
        $('#Layout_middleContent').toggleClass('narrow', isVisible).toggleClass('wide', !isVisible);
        $('#Layout_rightContent').toggleClass('shown', isVisible).toggleClass('away', !isVisible);

        if (isVisible) {
            $('#Layout_logo3Lines').css('display', 'none');
            $('#Layout_arrowLeft').css('display', '');
            UrlStack.push('#fullContent', function() { THIS.setRightPanelVisible(false); });
        } else {
            $('#Layout_logo3Lines').css('display', '');
            $('#Layout_arrowLeft').css('display', 'none');
            UrlStack.pop('#fullContent');
            if (onHideRightPanelCB !== null) {
                onHideRightPanelCB(); // invoke user callback
            }
        }

        return THIS;
    };

    THIS.isRightPanelVisible = function() {
        return $('#Layout_rightContent').is(':visible');
    };

    THIS.setLeftMenuVisibleOnPhone = function(isVisible) {
        // Intended to be used when the page is loading, so any loading occurring
        // on the left menu is shown, after that user calls method(false) to hide it.

        var defer = $.Deferred();
        var $leftSec = $('#Layout_left');

        if (!App.IsPhone()) {
            window.setTimeout(function() { defer.resolve(); }, 10);
        } else if (isVisible) { // show left menu on phones, does nothing on desktops
            _DarkBackground(true);
            var cx = $leftSec.outerWidth();
            $leftSec.css({
                left: '-'+cx+'px',
                display: 'block'
            });
            $leftSec.scrollTop(0);
            $leftSec.animate({ left:'0' }, userOpts.showMenuTime, function() {
                UrlStack.push('#leftMenu', function() { THIS.setLeftMenuVisibleOnPhone(false); });
                defer.resolve();
            });
        } else { // hides left menu on phones, does nothing on desktops
            _DarkBackground(false);
            UrlStack.pop('#leftMenu');
            var cx = $leftSec.outerWidth();
            $leftSec.css('left', '0');
            $leftSec.animate({ left:'-'+cx+'px' }, userOpts.hideMenuTime, function() {
                $leftSec.css({
                    left: '',
                    display: '' // reverting from "block"
                });
                defer.resolve();
            });
        }
        return defer.promise();
    };

    THIS.getContextMenu = function() {
        return contextMenu; // simply return the object itself, for whatever purpose
    };

    THIS.setContextMenuVisible = function(isVisible) {
        $('#Layout_context').css('display', isVisible ? '' : 'none');
        return THIS;
    };

    THIS.setTitle = function(title) {
        $('#Layout_title').html(title);
        return THIS;
    };

    THIS.onKeepAlive = function(callback) {
        onKeepAliveCB = callback; // onKeepAlive()
        return THIS;
    };

    THIS.onHideRightPanel = function(callback) {
        onHideRightPanelCB = callback; // onHideRightPanel()
        return THIS;
    };

    THIS.onSearch = function(callback) {
        onSearchCB = callback; // onSearch(text)
        return THIS;
    };

    function _SetEvents() {
        $('#Layout_logo3Lines').on('click', function() {
            THIS.setLeftMenuVisibleOnPhone(true);
        });

        $('#Layout_arrowLeft').on('click', function() {
            THIS.setRightPanelVisible(false);
        });

        $('#Layout_txtSearch').on('keypress', function(ev) {
            if (ev.which === 13) {
                $('#Layout_btnSearch').trigger('click'); // submit search on Enter
            }
        });

        $('#Layout_btnSearch').on('click', function() {
            if (onSearchCB !== null) {
                var searchTerm = App.IsPhone() ?
                    window.prompt('Busca') : $('#Layout_txtSearch').val();
                if (searchTerm === null) searchTerm = '';
                onSearchCB(searchTerm);
            }
        });

        $('#Layout_logo,#Layout_menuArrowLeft').on('click', function() {
            THIS.setLeftMenuVisibleOnPhone(false);
        });

        $('#Layout_logoff').on('click', function(ev) { // logoff the whole application
            ev.stopImmediatePropagation();
            $('#Layout_logoff').css('display', 'none');
            $('#Layout_loggingOff').css('display', 'inline-block');
            App.Post('logoff')
            .done(function(data) {
                App.ReturnToLoginScreen();
            }).fail(function(error) {
                console.error('Logout error: ' + error.responseText);
                location.href = '.';
                // server side processing will usually invalidate the current
                // session even if it throws an error. So, it's reasonably
                // safe to fail silently and go back to the login screen
            });
        });

        $('#Layout_modules li,#Layout_modules a').on('click', function(ev) { // click on a module
            ev.preventDefault();
            ev.stopImmediatePropagation();
            var href = $(this).attr('href') !== undefined ?
                $(this).attr('href') : $(this).find('a').attr('href');
            App.GoToFolder(href);
        });

        $(document).ajaxComplete(function AjaxComplete() {
            if (onKeepAliveCB !== null) {
                if (AjaxComplete.timer !== undefined && AjaxComplete.timer !== null) {
                    window.clearTimeout(AjaxComplete.timer);
                }
                AjaxComplete.timer = window.setTimeout(function() {
                    AjaxComplete.timer = null;
                    onKeepAliveCB(); // invoke user callback
                }, userOpts.keepAliveTime); // X minutes after the last request, an update should be performed (keep-alive)
            }
        });
    }

    function _SetCurrentModuleAsBold() {
        var curModule = location.href;
        curModule = curModule.split('/');
        curModule = curModule[curModule.length - 1] !== '' ?
            curModule[curModule.length - 1] : curModule[curModule.length - 2];
        $('#Layout_modules li').each(function(i, li) {
            var module = $(li).find('a:first').attr('href');
            if (module !== undefined) {
                module = module.substr(module.indexOf('/') + 1);
                if (module === curModule) {
                    $(li).find('span').css('font-weight', 'bold');
                    return false;
                }
            }
        });
    }

    function _DarkBackground(isCover) {
        if (isCover && _DarkBackground.$div === undefined) {
            _DarkBackground.$div = $(document.createElement('div'));
            _DarkBackground.$div.css({
                position: 'absolute',
                width: '100%',
                height: '100%',
                opacity: .4,
                'background-color': 'black'
            });
            _DarkBackground.$div.insertBefore('#Layout_left'); // will appear below left menu
            _DarkBackground.$div.on('click', function() {
                THIS.setLeftMenuVisibleOnPhone(false);
            });
        } else if (!isCover && _DarkBackground.$div !== undefined) {
            _DarkBackground.$div.remove();
            delete _DarkBackground.$div;
        }
    }
};
});
