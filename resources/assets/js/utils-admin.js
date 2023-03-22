/**
 * ajax_group_status()
 *
 * @param id        group id
 * @param status    0 = deactive, 1 = activate
 */

const base_url = window.location.origin;
function ajax_group_status(id, what) {
    // no caching of results
    let rand_no = Math.random();
    if (what != undefined) {
        $.ajax({
            url: base_url + '/admin/ajax?action=toggle_group_active_status&rand=' + rand_no,
            data: { group_id: id, group_status: what },
            dataType: 'html',
            success: function (data) {
                $('div#message').html(data);
                $('div#message').show('fast', function () {});

                // switch some links around
                if (what == 0) {
                    $('td#group-' + id).html(
                        '<a href="javascript:ajax_group_status(' +
                            id +
                            ', 1)" class="group_deactive">Activate</a>'
                    );
                } else {
                    $('td#group-' + id).html(
                        '<a href="javascript:ajax_group_status(' +
                            id +
                            ', 0)" class="group_active">Deactivate</a>'
                    );
                }

                // fade.. mm
                $('#message').fadeOut(5000);
            },
            error: function (xhr, err, e) {
                alert('Error in ajax_group_status: ' + err);
            },
        });
    } else {
        alert('Weird.. what group id are looking for?');
    }
}

/**
 * ajax_sharing_site_status()
 *
 * @param id        site id
 * @param status    0 = deactive, 1 = activate
 */
function ajax_sharing_site_status(id, status) {
    // no caching of results
    let rand_no = Math.random();
    if (status != undefined) {
        $.ajax({
            url: base_url + '/admin/ajax?action=sharing_toggle_status&rand=' + rand_no,
            data: { site_id: id, site_status: status },
            dataType: 'html',
            success: function (data) {
                $('div#message').html(data);
                $('div#message').show('fast', function () {});

                // switch some links around
                if (status == 0) {
                    $('td#site-' + id).html(
                        '<a href="javascript:ajax_sharing_site_status(' +
                            id +
                            ', 1)" class="sharing_site_deactive">Enable</a>'
                    );
                } else {
                    $('td#site-' + id).html(
                        '<a href="javascript:ajax_sharing_site_status(' +
                            id +
                            ', 0)" class="sharing_site_active">Disable</a>'
                    );
                }

                // fade.. mm
                $('#message').fadeOut(5000);
            },
            error: function (xhr, err, e) {
                alert('Error in ajax_sharing_site_status: ' + err);
            },
        });
    } else {
        alert('Weird.. what site id are looking for?');
    }
}

/**
 * ajax_sharing_enabled()
 *
 * @param id
 * @param status    0 = deactive, 1 = activate
 */
function ajax_sharing_enabled(id, status) {
    // no caching of results
    let rand_no = Math.random();
    if (status != undefined) {
        $.ajax({
            url: base_url + '/admin/ajax?action=sharing_toggle_enabled&rand=' + rand_no,
            data: { enabled_status: status },
            dataType: 'html',
            success: function (data) {
                $('div#message').html(data);
                $('div#message').show('fast', function () {});

                // switch some links around
                if (status == 0) {
                    $('strong#enabled-' + id).html(
                        '<a title="Click this to enable sharing." href="javascript:ajax_sharing_enabled(' +
                            id +
                            ', 1)" class="sharing_enabled_deactive">[ENABLE]</a>'
                    );
                } else {
                    $('strong#enabled-' + id).html(
                        '<a title="Click this to disable sharing." href="javascript:ajax_sharing_enabled(' +
                            id +
                            ', 0)" class="sharing_enabled_active">[DISABLE]</a>'
                    );
                }

                // fade.. mm
                $('#message').fadeOut(5000);
            },
            error: function (xhr, err, e) {
                alert('Error in ajax_sharing_enabled: ' + err);
            },
        });
    } else {
        alert('Weird.. what enabled id are looking for?');
    }
}

/**
 * ajax_sharing_startposition()
 *
 * @param id
 * @param status    0 = deactive, 1 = activate
 */
function ajax_sharing_startposition(id, status) {
    // no caching of results
    let rand_no = Math.random();
    if (status != undefined) {
        $.ajax({
            url: base_url + '/admin/ajax?action=sharing_start_position&rand=' + rand_no,
            data: { start_position: status },
            dataType: 'html',
            success: function (data) {
                $('div#message').html(data);
                $('div#message').show('fast', function () {});

                // switch some links around
                if (status == 0) {
                    $('strong#startposition-' + id).html(
                        '<a title="Click this to enable backfill." href="javascript:ajax_sharing_startposition(' +
                            id +
                            ', 1)" class="sharing_enabled_deactive">[ENABLE]</a>'
                    );
                } else {
                    $('strong#startposition-' + id).html(
                        '<a title="Click this to disable backfill." href="javascript:ajax_sharing_startposition(' +
                            id +
                            ', 0)" class="sharing_enabled_active">[DISABLE]</a>'
                    );
                }

                // fade.. mm
                $('#message').fadeOut(5000);
            },
            error: function (xhr, err, e) {
                alert('Error in ajax_sharing_startposition: ' + err);
            },
        });
    } else {
        alert('Weird.. what enabled id are looking for?');
    }
}

/**
 * ajax_sharing_reset()
 *
 * @param id
 */
function ajax_sharing_reset(id) {
    let rand_no = Math.random();
    $.ajax({
        url: base_url + '/admin/ajax?action=sharing_reset_settings&rand=' + rand_no,
        data: { reset_settings: id },
        dataType: 'html',
        success: function (data) {
            $('div#message').html(data);
            $('div#message').show('fast', function () {});

            // fade.. mm
            $('#message').fadeOut(5000);
            setTimeout('history.go(0);', 1500);
        },
        error: function (xhr, err, e) {
            alert('Error in ajax_sharing_reset: ' + err);
        },
    });
}

/**
 * ajax_sharing_site_purge()
 *
 * @param id
 */
function ajax_sharing_site_purge(id) {
    let rand_no = Math.random();
    $.ajax({
        url: base_url + '/admin/ajax?action=sharing_purge_site&rand=' + rand_no,
        data: { purge_site: id },
        dataType: 'html',
        success: function (data) {
            $('div#message').html(data);
            $('div#message').show('fast', function () {});

            // fade.. mm
            $('#message').fadeOut(5000);
            setTimeout('history.go(0);', 1500);
        },
        error: function (xhr, err, e) {
            alert('Error in ajax_sharing_site_purge: ' + err);
        },
    });
}

/**
 * ajax_sharing_posting()
 *
 * @param id
 * @param status    0 = deactive, 1 = activate
 */
function ajax_sharing_posting(id, status) {
    // no caching of results
    let rand_no = Math.random();
    if (status != undefined) {
        $.ajax({
            url: base_url + '/admin/ajax?action=sharing_toggle_posting&rand=' + rand_no,
            data: { posting_status: status },
            dataType: 'html',
            success: function (data) {
                $('div#message').html(data);
                $('div#message').show('fast', function () {});

                // switch some links around
                if (status == 0) {
                    $('strong#posting-' + id).html(
                        '<a title="Click this to enable posting." href="javascript:ajax_sharing_posting(' +
                            id +
                            ', 1)" class="sharing_posting_deactive">[ENABLE]</a>'
                    );
                } else {
                    $('strong#posting-' + id).html(
                        '<a title="Click this to disable posting." href="javascript:ajax_sharing_posting(' +
                            id +
                            ', 0)" class="sharing_posting_active">[DISABLE]</a>'
                    );
                }

                // fade.. mm
                $('#message').fadeOut(5000);
            },
            error: function (xhr, err, e) {
                alert('Error in ajax_sharing_posting: ' + err);
            },
        });
    } else {
        alert('Weird.. what posting id are looking for?');
    }
}

/**
 * ajax_sharing_fetching()
 *
 * @param id
 * @param status    0 = deactive, 1 = activate
 */
function ajax_sharing_fetching(id, status) {
    // no caching of results
    let rand_no = Math.random();
    if (status != undefined) {
        $.ajax({
            url: base_url + '/admin/ajax?action=sharing_toggle_fetching&rand=' + rand_no,
            data: { fetching_status: status },
            dataType: 'html',
            success: function (data) {
                $('div#message').html(data);
                $('div#message').show('fast', function () {});

                // switch some links around
                if (status == 0) {
                    $('strong#fetching-' + id).html(
                        '<a title="Click this to enable posting." href="javascript:ajax_fetching_posting(' +
                            id +
                            ', 1)" class="sharing_fetching_deactive">[ENABLE]</a>'
                    );
                } else {
                    $('strong#fetching-' + id).html(
                        '<a title="Click this to disable sharing." href="javascript:ajax_fetching_posting(' +
                            id +
                            ', 0)" class="sharing_fetching_active">[DISABLE]</a>'
                    );
                }

                // fade.. mm
                $('#message').fadeOut(5000);
            },
            error: function (xhr, err, e) {
                alert('Error in ajax_sharing_fetching: ' + err);
            },
        });
    } else {
        alert('Weird.. what fetching id are looking for?');
    }
}

/**
 * ajax_sharing_auto()
 *
 * @param id
 * @param status    0 = deactive, 1 = activate
 */
function ajax_sharing_auto(id, status) {
    // no caching of results
    let rand_no = Math.random();
    if (status != undefined) {
        $.ajax({
            url: base_url + '/admin/ajax?action=sharing_toggle_site_auto_enabling&rand=' + rand_no,
            data: { auto_status: status },
            dataType: 'html',
            success: function (data) {
                $('div#message').html(data);
                $('div#message').show('fast', function () {});

                // switch some links around
                if (status == 0) {
                    $('strong#auto-' + id).html(
                        '<a title="Click this to enable auto-enable." href="javascript:ajax_auto_posting(' +
                            id +
                            ', 1)" class="sharing_auto_deactive">[ENABLE]</a>'
                    );
                } else {
                    $('strong#auto-' + id).html(
                        '<a title="Click this to disable auto-enable." href="javascript:ajax_auto_posting(' +
                            id +
                            ', 0)" class="sharing_auto_active">[DISABLE]</a>'
                    );
                }

                // fade.. mm
                $('#message').fadeOut(5000);
            },
            error: function (xhr, err, e) {
                alert('Error in ajax_sharing_auto: ' + err);
            },
        });
    } else {
        alert('Weird.. what auto id are looking for?');
    }
}

/**
 * ajax_sharing_hide()
 *
 * @param id
 * @param status    0 = deactive, 1 = activate
 */
function ajax_sharing_hide(id, status) {
    // no caching of results
    let rand_no = Math.random();
    if (status != undefined) {
        $.ajax({
            url: base_url + '/admin/ajax?action=sharing_toggle_hide_users&rand=' + rand_no,
            data: { hide_status: status },
            dataType: 'html',
            success: function (data) {
                $('div#message').html(data);
                $('div#message').show('fast', function () {});

                // switch some links around
                if (status == 0) {
                    $('strong#hide-' + id).html(
                        '<a title="Click this to enable hiding users." href="javascript:ajax_hide_posting(' +
                            id +
                            ', 1)" class="sharing_hide_deactive">[ENABLE]</a>'
                    );
                } else {
                    $('strong#hide-' + id).html(
                        '<a title="Click this to disable hiding users." href="javascript:ajax_hide_posting(' +
                            id +
                            ', 0)" class="sharing_hide_active">[DISABLE]</a>'
                    );
                }

                // fade.. mm
                $('#message').fadeOut(5000);
            },
            error: function (xhr, err, e) {
                alert('Error in ajax_sharing_hide: ' + err);
            },
        });
    } else {
        alert('Weird.. what hide id are looking for?');
    }
}

/**
 * ajax_sharing_toggle_all()
 *
 *  @param status
 */
function ajax_sharing_toggle_all(status) {
    // no caching of results
    let rand_no = Math.random();
    if (status != undefined) {
        $.ajax({
            url: base_url + '/admin/ajax?action=sharing_toggle_all_sites&rand=' + rand_no,
            data: { toggle_all: status },
            dataType: 'html',
        });
    } else {
        alert('Weird.. what toggle status are you looking for?');
    }
}

/**
 * ajax_backfill_status()
 *
 * @param id        group id
 * @param status    0 = deactive, 1 = activate
 */
function ajax_backfill_status(id, what) {
    // no caching of results
    let rand_no = Math.random();
    if (what != undefined) {
        $.ajax({
            url: base_url + '/admin/ajax?action=toggle_group_backfill_status&rand=' + rand_no,
            data: { group_id: id, backfill_status: what },
            dataType: 'html',
            success: function (data) {
                $('div#message').html(data);
                $('div#message').show('fast', function () {});

                // switch some links around
                if (what == 0) {
                    $('td#backfill-' + id).html(
                        '<a href="javascript:ajax_backfill_status(' +
                            id +
                            ', 1)" class="backfill_deactive">Activate</a>'
                    );
                } else {
                    $('td#backfill-' + id).html(
                        '<a href="javascript:ajax_backfill_status(' +
                            id +
                            ', 0)" class="backfill_active">Deactivate</a>'
                    );
                }

                // fade.. mm
                $('#message').fadeOut(5000);
            },
            error: function (xhr, err, e) {
                alert('Error in ajax_backfill_status: ' + err);
            },
        });
    } else {
        alert('Weird.. what group id are looking for?');
    }
}

/**
 * ajax_group_delete()
 *
 * @param id        group id
 */
function ajax_group_delete(id) {
    // no caching of results
    let rand_no = Math.random();
    $.ajax({
        url: base_url + '/admin/ajax?action=group_edit_delete_single&rand=' + rand_no,
        data: { group_id: id },
        dataType: 'html',
        success: function (data) {
            $('div#message').html(data);
            $('div#message').show('fast', function () {});
            $('#grouprow-' + id).fadeOut(2000);
            $('#message').fadeOut(5000);
        },
        error: function (xhr, err, e) {
            alert('Error in ajax_group_delete: ' + err);
        },
    });
}

/**
 * ajax_group_reset()
 *
 * @param id        group id
 */
function ajax_group_reset(id) {
    // no caching of results
    let rand_no = Math.random();
    $.ajax({
        url: base_url + '/admin/ajax?action=group_edit_reset_single&rand=' + rand_no,
        data: { group_id: id },
        dataType: 'html',
        success: function (data) {
            $('div#message').html(data);
            $('div#message').show('fast', function () {});
            $('#grouprow-' + id).fadeTo(2000, 0.5);
            $('#message').fadeOut(5000);
        },
        error: function (xhr, err, e) {
            alert('Error in ajax_group_reset: ' + err);
        },
    });
}

/**
 * ajax_group_purge()
 *
 * @param id        group id
 */
function ajax_group_purge(id) {
    // no caching of results
    let rand_no = Math.random();
    $.ajax({
        url: base_url + '/admin/ajax?action=group_edit_purge_single&rand=' + rand_no,
        data: { group_id: id },
        dataType: 'html',
        success: function (data) {
            $('div#message').html(data);
            $('div#message').show('fast', function () {});
            $('#grouprow-' + id).fadeTo(2000, 0.5);
            $('#message').fadeOut(5000);
        },
        error: function (xhr, err, e) {
            alert('Error in ajax_group_purge: ' + err);
        },
    });
}

/**
 * ajax_all_reset()
 *
 *
 */
function ajax_all_reset() {
    // no caching of results
    let rand_no = Math.random();
    $.ajax({
        url: base_url + '/admin/ajax?action=group_edit_reset_all&rand=' + rand_no,
        data: 'All groups reset.',
        dataType: 'html',
        success: function (data) {
            $('div#message').html(data);
            $('div#message').show('fast', function () {});
            $('#grouprow-' + id).fadeTo(2000, 0.5);
            $('#message').fadeOut(5000);
        },
        error: function (xhr, err, e) {
            alert('Error in ajax_all_reset: ' + err);
        },
    });
}

/**
 * ajax_all_purge()
 */
function ajax_all_purge() {
    // no caching of results
    let rand_no = Math.random();
    $.ajax({
        url: base_url + '/admin/ajax?action=group_edit_purge_all&rand=' + rand_no,
        data: 'All groups purged',
        dataType: 'html',
        success: function (data) {
            $('div#message').html(data);
            $('div#message').show('fast', function () {});
            $('#grouprow-' + id).fadeTo(2000, 0.5);
            $('#message').fadeOut(5000);
        },
        error: function (xhr, err, e) {
            alert('Error in ajax_all_purge: ' + err);
        },
    });
}

/**
 * ajax_binaryblacklist_delete()
 *
 * @param id        binary id
 */
function ajax_binaryblacklist_delete(id) {
    // no caching of results
    let rand_no = Math.random();
    $.ajax({
        url: base_url + '/admin/ajax?action=binary_blacklist_delete&rand=' + rand_no,
        data: { row_id: id },
        dataType: 'html',
        success: function (data) {
            $('div#message').html(data);
            $('div#message').show('fast', function () {});
            $('#row-' + id).fadeOut(2000);
            $('#message').fadeOut(5000);
        },
        error: function (xhr, err, e) {
            alert('Error in ajax_binaryblacklist_delete: ' + err);
        },
    });
}

/**
 * ajax_category_regex_delete()
 *
 * @param id        binary id
 */
function ajax_category_regex_delete(id) {
    // no caching of results
    let rand_no = Math.random();
    $.ajax({
        url: base_url + '/admin/ajax?action=category_regex_delete&rand=' + rand_no,
        data: { row_id: id },
        dataType: 'html',
        success: function (data) {
            $('div#message').html(data);
            $('div#message').show('fast', function () {});
            $('#row-' + id).fadeOut(2000);
            $('#message').fadeOut(5000);
        },
        error: function (xhr, err, e) {
            alert('Error in ajax_category_regex_delete: ' + err);
        },
    });
}

/**
 * ajax_collection_regex_delete()
 *
 * @param id        binary id
 */
function ajax_collection_regex_delete(id) {
    // no caching of results
    let rand_no = Math.random();
    $.ajax({
        url: base_url + '/admin/ajax?action=collection_regex_delete&rand=' + rand_no,
        data: { row_id: id },
        dataType: 'html',
        success: function (data) {
            $('div#message').html(data);
            $('div#message').show('fast', function () {});
            $('#row-' + id).fadeOut(2000);
            $('#message').fadeOut(5000);
        },
        error: function (xhr, err, e) {
            alert('Error in ajax_collection_regex_delete: ' + err);
        },
    });
}

/**
 * ajax_release_naming_regex_delete()
 *
 * @param id        binary id
 */
function ajax_release_naming_regex_delete(id) {
    // no caching of results
    let rand_no = Math.random();
    $.ajax({
        url: base_url + '/admin/ajax?action=release_naming_regex_delete&rand=' + rand_no,
        data: { row_id: id },
        dataType: 'html',
        success: function (data) {
            $('div#message').html(data);
            $('div#message').show('fast', function () {});
            $('#row-' + id).fadeOut(2000);
            $('#message').fadeOut(5000);
        },
        error: function (xhr, err, e) {
            alert('Error in ajax_release_naming_regex_delete: ' + err);
        },
    });
}

jQuery(function ($) {
    $('#regexGroupSelect').change(function () {
        document.location = '?group=' + $('#regexGroupSelect option:selected').attr('value');
    });

    // misc
    $('.confirm_action').click(function () {
        return confirm('Are you sure?');
    });
});

//enable Custom checkboxes for fix crap releases
function enableFixCrapCustom() {
    let inputs = document.getElementsByName('fix_crap_opt');
    if (inputs[2].checked == true) {
        let checks = document.getElementsByName('fix_crap[]');
        for (let t = 0; t < checks.length; t++) {
            checks[t].disabled = false;
            checks[t].readonly = false;
        }
    } else {
        let checks = document.getElementsByName('fix_crap[]');
        for (let t = 0; t < checks.length; t++) {
            checks[t].disabled = true;
            checks[t].readonly = true;
        }
    }
}

/** ****** tinyMCE ***************************/
tinyMCE.init({
    selector: 'textarea#body',
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

tinyMCE.init({
    selector: 'textarea#metadescription',
    theme: 'silver',
    plugins: [
        'advlist autolink link image lists charmap print preview hr anchor pagebreak spellchecker',
        'searchreplace wordcount visualblocks visualchars code fullscreen insertdatetime media nonbreaking',
        'save table contextmenu directionality emoticons template paste textcolor code',
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

tinyMCE.init({
    selector: 'textarea#metakeywords',
    theme: 'silver',
    plugins: [
        'advlist autolink link image lists charmap print preview hr anchor pagebreak spellchecker',
        'searchreplace wordcount visualblocks visualchars code fullscreen insertdatetime media nonbreaking',
        'save table contextmenu directionality emoticons template paste textcolor code',
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
