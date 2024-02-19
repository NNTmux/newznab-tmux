//enable bootstrap tooltips
let tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
let tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});
// event bindings
jQuery(function ($) {
    const base_url = window.location.origin;
    $('.cartadd').click(function (e) {
        if ($(this).hasClass('icon_cart_clicked')) return false;
        let guid = $('.guid').attr('id').substring(4);
        //alert(guid);
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
        });
        $.post(base_url + '/cart/add?id=' + guid, function (resp) {
            $(e.target).addClass('icon_cart_clicked').attr('title', 'Added to Cart');
            PNotify.defaults.icons = 'fontawesome5';
            PNotify.success({
                title: 'Release added to your Download Basket!',
                type: 'success',
                icon: 'fa fa-info fa-3x',
                Animate: {
                    animate: true,
                    in_class: 'bounceInLeft',
                    out_class: 'bounceOutRight',
                },
                desktop: {
                    desktop: true,
                    fallback: true,
                },
            });
        });
        return false;
    });

    // browse.tpl, search.tpl -- show icons on hover
    let orig_opac = $('table.data tr').children('td.icons').children('div.icon').css('opacity');
    $('table.data tr').hover(
        function () {
            $(this).children('td.icons').children('div.icon').css('opacity', 1);
        },
        function () {
            $(this).children('td.icons').children('div.icon').css('opacity', orig_opac);
        }
    );

    $('.forumpostsubmit').click(function (e) {
        if ($.trim($('#addMessage').val()) == '' || $.trim($('#addSubject').val()) == '') {
            alert('Please enter a subject and message.');
            return false;
        }
    });

    $('.forumreplysubmit').click(function (e) {
        if ($.trim($('#addMessage').val()) == '') {
            alert('Please enter a message.');
            return false;
        }
    });

    $('.check').click(function (e) {
        if (!$(e.target).is('input'))
            $(this)
                .children('.nzb_check')
                .attr('checked', !$(this).children('.nzb_check').attr('checked'));
    });

    $('.descmore').click(function (e) {
        $(this).prev('.descinitial').hide();
        $(this).next('.descfull').show();
        $(this).hide();
        return false;
    });

    $('.nzb_check_all').change(function () {
        if ($(this).attr('checked')) {
            $('table.data tr td input:checkbox').attr('checked', $(this).attr('checked'));
        } else {
            $('table.data tr td input:checkbox').removeAttr('checked');
        }
    });

    $('.nzb_check_all_season').change(function () {
        let season = $(this).attr('name');
        $('table.data tr td input:checkbox').each(function (i, row) {
            if ($(row).attr('name') == season) {
                $(row).attr('checked', !$(row).attr('checked'));
            }
        });
    });

    // browse.tpl, search.tpl
    $('.icon_cart').click(function (e) {
        if ($(this).hasClass('icon_cart_clicked')) return false;
        let guid = $(this).attr('id').substring(4);
        //alert(guid);
        //alert(base_url + "/cart/add?id=" + guid);
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
        });
        $.post(base_url + '/cart/add?id=' + guid, function (resp) {
            $(e.target).addClass('icon_cart_clicked').attr('title', ' Release added to Cart');
            PNotify.defaults.icons = 'fontawesome5';
            PNotify.success({
                title: 'Release added to your download basket!',
                type: 'success',
                icon: 'fa fa-info fa-3x',
                Animate: {
                    animate: true,
                    in_class: 'bounceInLeft',
                    out_class: 'bounceOutRight',
                },
                desktop: {
                    desktop: true,
                    fallback: true,
                },
            });
        });
        return false;
    });

    $('table.data a.modal_nfo').colorbox({
        // NFO modal
        href: function () {
            return $(this).attr('href') + '&modal';
        },
        title: function () {
            return $(this).parent().parent().children('a.title').text();
        },
        innerWidth: '800px',
        innerHeight: '90%',
        initialWidth: '800px',
        initialHeight: '90%',
        speed: 0,
        opacity: 0.7,
    });
    // Screenshot modal
    $('table.data a.modal_prev').colorbox({
        scrolling: false,
        maxWidth: '800px',
        maxHeight: '450px',
    });

    $('table.data a.modal_imdb')
        .colorbox({
            // IMDB modal
            href: function () {
                return base_url + 'movie/' + $(this).attr('name').substring(4) + '&modal';
            },
            title: function () {
                return $(this).parent().parent().children('a.title').text();
            },
            innerWidth: '800px',
            innerHeight: '450px',
            initialWidth: '800px',
            initialHeight: '450px',
            speed: 0,
            opacity: 0.7,
        })
        .click(function () {
            $('#colorbox').removeClass().addClass('cboxMovie');
        });

    $('a.modal_imdbtrailer')
        .colorbox({
            // IMDB trailer modal
            href: function () {
                return base_url + 'movietrailer/' + $(this).attr('name').substring(4) + '&modal';
            },
            title: function () {
                return $(this).parent().parent().children('a.title').text();
            },
            innerWidth: '800px',
            innerHeight: '450px',
            initialWidth: '800px',
            initialHeight: '450px',
            speed: 0,
            opacity: 0.7,
        })
        .click(function () {
            $('#colorbox').removeClass().addClass('cboxMovie');
        });

    $('table.data a.modal_music')
        .colorbox({
            // Music modal
            href: function () {
                return base_url + 'musicmodal/' + $(this).attr('name').substring(4) + '&modal';
            },
            title: function () {
                return $(this).parent().parent().children('a.title').text();
            },
            innerWidth: '800px',
            innerHeight: '450px',
            initialWidth: '800px',
            initialHeight: '450px',
            speed: 0,
            opacity: 0.7,
        })
        .click(function () {
            $('#colorbox').removeClass().addClass('cboxMusic');
        });
    $('table.data a.modal_console')
        .colorbox({
            // Console modal
            href: function () {
                return base_url + 'consolemodal/' + $(this).attr('name').substring(4) + '&modal';
            },
            title: function () {
                return $(this).parent().parent().children('a.title').text();
            },
            innerWidth: '800px',
            innerHeight: '450px',
            initialWidth: '800px',
            initialHeight: '450px',
            speed: 0,
            opacity: 0.7,
        })
        .click(function () {
            $('#colorbox').removeClass().addClass('cboxConsole');
        });
    $('table.data a.modal_book')
        .colorbox({
            // Book modal
            href: function () {
                return base_url + 'bookmodal/' + $(this).attr('name').substring(4) + '&modal';
            },
            title: function () {
                return $(this).parent().parent().children('a.title').text();
            },
            innerWidth: '800px',
            innerHeight: '450px',
            initialWidth: '800px',
            initialHeight: '450px',
            speed: 0,
            opacity: 0.7,
        })
        .click(function () {
            $('#colorbox').removeClass().addClass('cboxBook');
        });

    $('#nzb_multi_operations_form').submit(function () {
        return false;
    });

    $('button.nzb_multi_operations_download').on('click', function () {
        let ids = '';
        $("table.data INPUT[type='checkbox']:checked").each(function (i, row) {
            if ($(row).val() != 'on') ids += $(row).val() + ',';
        });
        ids = ids.substring(0, ids.length - 1);
        if (ids) window.location = base_url + '/getnzb?zip=1&id=' + ids;
    });

    $('input.nzb_multi_operations_download_cart').on('click', function () {
        let ids = '';
        $("table.data INPUT[type='checkbox']:checked").each(function (i, row) {
            if ($(row).val() != 'on') ids += $(row).val() + ',';
        });
        ids = ids.substring(0, ids.length - 1);
        if (ids) window.location = base_url + '/getnzb?zip=1&id=' + ids;
    });

    $('button.nzb_multi_operations_cart').on('click', function () {
        let guids = new Array();
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
        });
        $("table.data INPUT[type='checkbox']:checked").each(function (i, row) {
            let guid = $(row).val();
            let $cartIcon = $(row).parent().children('div.icons').children('.icon_cart');
            if (guid && !$cartIcon.hasClass('icon_cart_clicked')) {
                $cartIcon.addClass('icon_cart_clicked').attr('title', 'Added to Cart');
                guids.push(guid);
                PNotify.defaults.icons = 'fontawesome5';
                PNotify.success({
                    title: 'Release added to your Download Basket!',
                    type: 'success',
                    icon: 'fa fa-info fa-3x',
                    Animate: {
                        animate: true,
                        in_class: 'bounceInLeft',
                        out_class: 'bounceOutRight',
                    },
                    desktop: {
                        desktop: true,
                        fallback: true,
                    },
                });
            }
            $(this).attr('checked', false);
        });
        let guidstring = guids.toString();
        //alert (guidstring); // This is just for testing shit
        $.post(base_url + '/cart/add?id=' + guidstring);
    });
    $('button.nzb_multi_operations_sab').on('click', function () {
        $("table.data INPUT[type='checkbox']:checked").each(function (i, row) {
            let $sabIcon = $(row).parent().parent().children('td.icons').children('.icon_sab');
            let guid = $(row).val();
            //alert(guid);
            if (guid && !$sabIcon.hasClass('icon_sab_clicked')) {
                let nzburl = base_url + '/sendtoqueue/' + guid;
                // alert(nzburl);
                $.post(nzburl, function (resp) {
                    $sabIcon.addClass('icon_sab_clicked').attr('title', 'Added to Queue');
                    PNotify.defaults.icons = 'fontawesome5';
                    PNotify.success({
                        title: 'Release added to your download queue!',
                        type: 'success',
                        icon: 'fa fa-info fa-3x',
                        Animate: {
                            animate: true,
                            in_class: 'bounceInLeft',
                            out_class: 'bounceOutRight',
                        },
                        desktop: {
                            desktop: true,
                            fallback: true,
                        },
                    });
                });
            }
            $(this).attr('checked', false);
        });
    });
    $('input.nzb_multi_operations_nzbget').on('click', function () {
        $("table.data INPUT[type='checkbox']:checked").each(function (i, row) {
            let $nzbgetIcon = $(row)
                .parent()
                .parent()
                .children('td.icons')
                .children('.icon_nzbget');
            let guid = $(row).val();
            if (guid && !$nzbgetIcon.hasClass('icon_nzbget_clicked')) {
                let nzburl = base_url + '/sendtoqueue/' + guid;
                $.post(nzburl, function (resp) {
                    $nzbgetIcon.addClass('icon_nzbget_clicked').attr('title', 'Added to Queue');
                    PNotify.defaults.icons = 'fontawesome5';
                    PNotify.success({
                        title: 'Release added to your download queue!',
                        type: 'success',
                        icon: 'fa fa-info fa-3x',
                        Animate: {
                            animate: true,
                            in_class: 'bounceInLeft',
                            out_class: 'bounceOutRight',
                        },
                        desktop: {
                            desktop: true,
                            fallback: true,
                        },
                    });
                });
            }
            $(this).attr('checked', false);
        });
    });

    //front end admin functions
    $('input.nzb_multi_operations_edit').click(function () {
        let ids = '';
        $("table.data INPUT[type='checkbox']:checked").each(function (i, row) {
            if ($(row).val() != 'on') ids += '&id[]=' + $(row).val();
        });
        if (ids)
            $('input.nzb_multi_operations_edit').colorbox({
                href: function () {
                    return (
                        base_url +
                        'ajax_release-admin?action=edit' +
                        ids +
                        '&from=' +
                        encodeURIComponent(window.location)
                    );
                },
                title: 'Edit Release',
                innerWidth: '400px',
                innerHeight: '250px',
                initialWidth: '400px',
                initialHeight: '250px',
                speed: 0,
                opacity: 0.7,
            });
    });
    $('input.nzb_multi_operations_delete').click(function () {
        let ids = '';
        $("table.data INPUT[type='checkbox']:checked").each(function (i, row) {
            if ($(row).val() != 'on') ids += '&id[]=' + $(row).val();
        });
        if (ids) {
            PNotify.defaults.icons = 'fontawesome5';
            const notice = PNotify.alert({
                title: 'Confirmation Needed',
                text: 'Are you sure you want to delete the selected releases?',
                icon: 'glyphicon glyphicon-question-sign',
                hide: false,
                modules: {
                    Confirm: {
                        confirm: true,
                    },
                },
                Buttons: {
                    closer: false,
                    sticker: false,
                },
                History: {
                    history: false,
                },
            });
            notice.on('pnotify.confirm', function () {
                $.post(base_url + 'ajax_release-admin?action=dodelete' + ids, function (resp) {
                    location.reload(true);
                });
            });
            notice.on('pnotify.cancel', function () {
                alert('Cancelled');
            });
        }
    });
    $('input.nzb_multi_operations_rebuild').click(function () {
        let ids = '';
        $("table.data INPUT[type='checkbox']:checked").each(function (i, row) {
            if ($(row).val() != 'on') ids += '&id[]=' + $(row).val();
        });
        if (ids)
            if (confirm('Are you sure you want to rebuild the selected releases?')) {
                $.post(base_url + 'ajax_release-admin?action=dorebuild' + ids, function (resp) {
                    location.reload(true);
                });
            }
    });
    //cart functions
    $('input.nzb_multi_operations_cartdelete').click(function () {
        let ids = new Array();
        $("table.data INPUT[type='checkbox']:checked").each(function (i, row) {
            if ($(row).val() != 'on') ids.push($(row).val());
        });
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
        });

        //alert(base_url + "/cart/delete/" + ids);
        if (ids) {
            PNotify.defaults.icons = 'fontawesome5';
            const notice = PNotify.alert({
                title: 'Confirmation Needed',
                text: 'Are you sure you want to delete the selected releases from your cart?',
                hide: false,
                modules: {
                    Confirm: {
                        confirm: true,
                    },
                },
                Buttons: {
                    closer: false,
                    sticker: false,
                },
                History: {
                    history: false,
                },
            });
            notice.on('pnotify.confirm', function () {
                $.post(base_url + '/cart/delete/' + ids);
            });
            notice.on('pnotify.cancel', function () {
                alert('Cancelled');
            });
        }
    });
    $('input.nzb_multi_operations_cartsab').click(function () {
        let ids = new Array();
        $("table.data INPUT[type='checkbox']:checked").each(function (i, row) {
            let guid = $(row).val();
            let nzburl = base_url + '/sendtoqueue/' + guid;
            $.post(nzburl, function () {
                PNotify.defaults.icons = 'fontawesome5';
                PNotify.success({
                    title: 'Releases sent to queue!',
                    type: 'success',
                    icon: 'fa fa-info fa-3x',
                    Animate: {
                        animate: true,
                        in_class: 'bounceInLeft',
                        out_class: 'bounceOutRight',
                    },
                    desktop: {
                        desktop: true,
                        fallback: true,
                    },
                });
            });
        });
    });

    // headermenu.tpl
    $('#headsearch')
        .focus(function () {
            if (this.value === 'Search...') this.value = '';
            else this.select();
        })
        .blur(function () {
            if (this.value === '') this.value = 'Search...';
        });
    $('#headsearch_form').submit(function () {
        $('#headsearch_go').trigger('click');
        return false;
    });
    document.getElementById('headsearch_go').addEventListener('click', function () {
        let searchInput = document.getElementById('headsearch');
        let categoryInput = document.getElementById('headcat');
        if (searchInput.value && searchInput.value != 'Search...') {
            let sText = searchInput.value;
            let sCat = categoryInput.value !== '-1' ? '&t=' + categoryInput.value : 't=-1';
            window.location.href = base_url + '/search?' + sCat + '&search=' + sText;
        } else {
            PNotify.defaults.icons = 'fontawesome5';
            PNotify.alert({
                title: 'You need to enter a search term!',
                type: 'error',
                icon: 'fa fa-info fa-3x',
                Animate: {
                    animate: true,
                    in_class: 'bounceInLeft',
                    out_class: 'bounceOutRight',
                },
                desktop: {
                    desktop: true,
                    fallback: true,
                },
            });
        }
    });

    // search.tpl
    $('#search_search_button').click(function () {
        if ($('#search').val())
            document.location =
                base_url +
                '/search?id=' +
                $('#search').val() +
                ($('#search_cat').val() != -1 ? '&t=' + $('#search_cat').val() : '');
        return false;
    });

    $('#search').focus(function () {
        this.select();
    });

    // searchraw.tpl
    $('#searchraw_search_button').click(function () {
        if ($('#search').val()) document.location = base_url + '/searchraw/' + $('#search').val();
        return false;
    });
    $('#searchraw_download_selected').click(function () {
        if ($('#dl input:checked').length) $('#dl').trigger('submit');
        return false;
    });

    // login.tpl, register.tpl, search.tpl, searchraw.tpl
    if ($('#username').length) $('#username').focus();
    if ($('#search').length) $('#search').focus();

    // viewfilelist.tpl
    $('#viewfilelist_download_selected').click(function () {
        if ($('#fileform input:checked').length) $('#fileform').trigger('submit');
        return false;
    });

    // misc
    $('.confirm_action').click(function () {
        return confirm('Are you sure?');
    });

    // play audio preview
    $('.audioprev').click(function () {
        let a = document.getElementById($(this).next('audio').attr('ID'));
        if (a != null) {
            if ($(this).text() == 'Listen') {
                a.play();
                $(this).text('Stop');
            } else {
                a.pause();
                a.currentTime = 0;
                $(this).text('Listen');
            }
        }

        a.addEventListener('ended', function () {
            $(this).prev().text('Listen');
        });

        return false;
    });

    // mmenu
    $('.mmenu').click(function () {
        document.location = $(this).children('a').attr('href');
        return false;
    });

    // mmenu_new
    $('.mmenu_new').click(function () {
        window.open($(this).children('a').attr('href'));
        return false;
    });

    // searchraw.tpl, viewfilelist.tpl -- checkbox operations
    // selections
    let last1, last2;
    $('.checkbox_operations .select_all').click(function () {
        $("table.data INPUT[type='checkbox']").attr('checked', true).trigger('change');
        return false;
    });
    $('.checkbox_operations .select_none').click(function () {
        $("table.data INPUT[type='checkbox']").attr('checked', false).trigger('change');
        return false;
    });
    $('.checkbox_operations .select_invert').click(function () {
        $("table.data INPUT[type='checkbox']").each(function () {
            $(this).attr('checked', !$(this).attr('checked')).trigger('change');
        });
        return false;
    });
    $('.checkbox_operations .select_range').click(function () {
        if (last1 && last2 && last1 < last2)
            $("table.data INPUT[type='checkbox']")
                .slice(last1, last2)
                .attr('checked', true)
                .trigger('change');
        else if (last1 && last2)
            $("table.data INPUT[type='checkbox']")
                .slice(last2, last1)
                .attr('checked', true)
                .trigger('change');
        return false;
    });
    $('table.data td.check INPUT[type="checkbox"]').click(function (e) {
        // range event interaction -- see further above
        let rowNum = $(e.target).parent().parent()[0].rowIndex;
        if (last1) last2 = last1;
        last1 = rowNum;

        // perform range selection
        if (e.shiftKey && last1 && last2) {
            if (last1 < last2)
                $("table.data INPUT[type='checkbox']")
                    .slice(last1, last2)
                    .attr('checked', true)
                    .trigger('change');
            else
                $("table.data INPUT[type='checkbox']")
                    .slice(last2, last1)
                    .attr('checked', true)
                    .trigger('change');
        }
    });
    $('table.data a.data_filename').click(function (e) {
        // click filenames to select
        // range event interaction -- see further above
        let rowNum = $(e.target).parent().parent()[0].rowIndex;
        if (last1) last2 = last1;
        last1 = rowNum;

        let $checkbox = $(
            'table.data tr:nth-child(' + (rowNum + 1) + ') td.selection INPUT[type="checkbox"]'
        );
        $checkbox.attr('checked', !$checkbox.attr('checked'));

        return false;
    });

    // show/hide previews
    $('#showmoviepreviews').click(function () {
        $('#moviepreviews').next('form').toggle('fast');
        $('#moviepreviews').toggle('fast', function () {
            $('#showmoviepreviews').text(
                ($('#moviepreviews').is(':visible') ? 'Hide' : 'Show') + ' previews'
            );
        });
        return false;
    });

    // show/hide invite form
    $('#lnkSendInvite').click(function () {
        $('#divInvite').slideToggle('fast');
    });

    // send an invite
    $('#frmSendInvite').submit(function () {
        let inputEmailto = $('#txtInvite').val();
        if (isValidEmailAddress(inputEmailto)) {
            // no caching of results
            $.ajax({
                url: base_url + '/ajax_profile?action=1&rand=' + $.now(),
                data: { emailto: inputEmailto },
                dataType: 'html',
                success: function (data) {
                    $('#txtInvite').val('');
                    $('#divInvite').slideToggle('fast');
                    $('#divInviteSuccess').text(data).show();
                    $('#divInviteError').hide();
                },
                error: function (xhr, err, e) {
                    alert('Error in ajax_profile: ' + err);
                },
            });
        } else {
            $('#divInviteSuccess').hide();
            $('#divInviteError').text('Invalid email').show();
        }
        return false;
    });

    // movie.tpl
    $('.mlmore').click(function () {
        // show more movies
        $(this).parent().parent().hide();
        $(this).parent().parent().parent().children('.mlextra').show();
        return false;
    });

    // lookup tmdb for a movie
    $('#frmMyMovieLookup').submit(function () {
        let movSearchText = $('#txtsearch').val();
        // no caching of results
        $.ajax({
            url: base_url + '/ajax_mymovies?rand=' + $.now(),
            data: { id: movSearchText },
            dataType: 'html',
            success: function (data) {
                $('#divMovResults').html(data);
            },
            error: function (xhr, err, e) {
                alert('Error in ajax_mymovies: ' + err);
            },
        });

        return false;
    });

    // season selector in TV
    $('a[id^="seas_"]').click(function () {
        seas = $(this).attr('ID').replace('seas_', '');

        $('table[class^="tb_"]').hide();
        $('.tb_' + seas).show();

        $('li', $(this).closest('ul')).removeClass('active');
        $(this).closest('li').addClass('active');

        return false;
    });
});

$.extend({
    // http://plugins.jquery.com/project/URLEncode
    URLEncode: function (c) {
        let o = '';
        let x = 0;
        c = c.toString();
        let r = /(^[a-zA-Z0-9_.]*)/;
        while (x < c.length) {
            let m = r.exec(c.substr(x));
            if (m != null && m.length > 1 && m[1] != '') {
                o += m[1];
                x += m[1].length;
            } else {
                if (c[x] == ' ') o += '+';
                else {
                    let d = c.charCodeAt(x);
                    let h = d.toString(16);
                    o += '%' + (h.length < 2 ? '0' : '') + h.toUpperCase();
                }
                x++;
            }
        }
        return o;
    },
    URLDecode: function (s) {
        let o = s;
        let binVal, t;
        let r = /(%[^%]{2})/;
        while ((m = r.exec(o)) != null && m.length > 1 && m[1] != '') {
            b = parseInt(m[1].substr(1), 16);
            t = String.fromCharCode(b);
            o = o.replace(m[1], t);
        }
        return o;
    },
});

function isValidEmailAddress(emailAddress) {
    let pattern = new RegExp(
        /^(("[\w-\s]+")|([\w-]+(?:\.[\w-]+)*)|("[\w-\s]+")([\w-]+(?:\.[\w-]+)*))(@((?:[\w-]+\.)*\w[\w-]{0,66})\.([a-z]{2,6}(?:\.[a-z]{2})?)$)|(@\[?((25[0-5]\.|2[0-4][0-9]\.|1[0-9]{2}\.|[0-9]{1,2}\.))((25[0-5]|2[0-4][0-9]|1[0-9]{2}|[0-9]{1,2})\.){2}(25[0-5]|2[0-4][0-9]|1[0-9]{2}|[0-9]{1,2})\]?$)/i
    );
    return pattern.test(emailAddress);
}

function mymovie_del(imdbID, btn) {
    $.ajax({
        url: base_url + '/ajax_mymovies?rand=' + $.now(),
        data: { del: imdbID },
        dataType: 'html',
        success: function (data) {
            $(btn).hide();
            $(btn).prev('a').show();
        },
        error: function (xhr, err, e) {},
    });

    return false;
}

function mymovie_add(imdbID, btn) {
    $(btn).hide();
    $(btn).next('a').show();

    $.ajax({
        url: base_url + '/ajax_mymovies?rand=' + $.now(),
        data: { add: imdbID },
        dataType: 'html',
        success: function (data) {},
        error: function (xhr, err, e) {},
    });

    return false;
}

function getNzbGetQueue() {
    $.ajax({
        url: 'queuedata?type=nzbget&id=' + $.now(),
        cache: false,
        success: function (html) {
            $('.nzbget_queue').html(html);
            setTimeout('getNzbGetQueue()', 2500);
        },
        error: function () {
            $('.nzbget_queue').html(
                'Could not contact your queue. <a href="javascript:location.reload(true)">Refresh</a>'
            );
        },
        timeout: 5000,
    });
}

function getHistory() {
    $.ajax({
        url: 'queuedata?type=history&id=' + $.now(),
        cache: false,
        success: function (html) {
            $('.sab_history').html(html);
            setTimeout('getHistory()', 10000);
        },
        error: function () {
            //$(".sab_history").html("Could not contact your queue. <a href=\"javascript:location.reload(true)\">Refresh</a>");
        },
        timeout: 5000,
    });
}

/** ******  iswitch  *********************** **/

$(function () {
    let checkAll = $('input.flat-all');
    let checkboxes = $('input.flat');

    $('input').iCheck({
        checkboxClass: 'icheckbox_flat-green',
        radioClass: 'iradio_flat-green',
    });

    checkAll.on('ifChecked ifUnchecked', function (event) {
        if (event.type === 'ifChecked') {
            checkboxes.iCheck('check');
        } else {
            checkboxes.iCheck('uncheck');
        }
    });

    checkboxes.on('ifChanged', function (event) {
        if (checkboxes.filter(':checked').length === checkboxes.length) {
            checkAll.prop('checked', 'checked');
        } else {
            checkAll.prop('checked', false);
        }
        checkAll.iCheck('update');
    });
});
/** ******  /iswitch  *********************** **/

/** ****** tinyMCE ************************* **/
tinyMCE.init({
    selector: 'textarea#addMessage',
    theme: 'silver',
    plugins: [
        'advlist autolink link image lists charmap print preview hr anchor pagebreak spellchecker',
        'searchreplace wordcount visualblocks visualchars code fullscreen insertdatetime media nonbreaking',
        'save table directionality emoticons template paste code',
    ],
    theme_advanced_toolbar_location: 'top',
    theme_advanced_toolbar_align: 'left',
    toolbar:
        'insertfile undo redo | styleselect | fontselect |sizeselect | fontsizeselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image | print preview media fullpage | forecolor backcolor emoticons | code',
    fontsize_formats: '8pt 9pt 10pt 11pt 12pt 13pt 14pt 15pt 16pt 17pt 18pt 24pt 36pt',
    mode: 'exact',
    relative_urls: false,
    remove_script_host: false,
    convert_urls: true,
});
/** ****** /tinyMCE ************************* **/
