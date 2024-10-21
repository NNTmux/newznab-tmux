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
